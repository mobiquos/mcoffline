<?php

namespace App\Service;

use App\Entity\Contingency;
use App\Entity\Location;
use App\Entity\Sale;
use App\Entity\Payment;
use App\Entity\SyncEvent;
use App\Entity\SystemParameter;
use App\Repository\SaleRepository;
use App\Repository\PaymentRepository;
use App\Repository\SystemParameterRepository;
use App\Repository\SyncEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LocationToAdminSyncService
{
    // Batch size for processing large datasets
    private const BATCH_SIZE = 1000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly SaleRepository $saleRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly SystemParameterRepository $systemParameterRepository,
        private readonly SyncEventRepository $syncEventRepository,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * Synchronize sales and payments from location to admin system
     *
     * @param Location $location The location to synchronize data from
     * @param Contingency $contingency The contingency period to sync
     * @param SyncEvent|null $existingSyncEvent An existing sync event to update (optional)
     * @return array Results of the synchronization
     */
    public function syncToAdmin(Location $location, Contingency $contingency, ?SyncEvent $existingSyncEvent = null): array
    {
        // Create or use existing sync event
        if ($existingSyncEvent) {
            $syncEvent = $existingSyncEvent;
            // Reset status if it was failed
            if ($syncEvent->getStatus() === SyncEvent::STATUS_FAILED) {
                $syncEvent->setStatus(SyncEvent::STATUS_INPROGRESS);
                $this->em->flush();
            }
        } else {
            // Create a new sync event
            $syncEvent = new SyncEvent();
            $syncEvent->setLocation($location);
            $syncEvent->setStatus(SyncEvent::STATUS_INPROGRESS);
            $syncEvent->setType(SyncEvent::TYPE_PUSH);

            $this->em->persist($syncEvent);
            $this->em->flush();
        }

        $results = [
            'sales_synced' => 0,
            'payments_synced' => 0,
            'errors' => []
        ];

        try {
            // Get admin server address from system parameters
            $serverParam = $this->systemParameterRepository->findByCode(SystemParameter::PARAM_SERVER_ADDRESS);
            if (!$serverParam) {
                $results['errors'][] = 'Server address not configured in system parameters';
                $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
                $this->em->flush();
                return $results;
            }

            $adminServerAddress = $serverParam->getValue();

            // Create CSV files for sales and payments
            $csvResults = $this->createCsvFiles($location, [$contingency]);
            if (!empty($csvResults['errors'])) {
                $results['errors'] = array_merge($results['errors'], $csvResults['errors']);
                $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
                $this->em->flush();
                return $results;
            }

            // Upload CSV files to admin system
            $uploadResults = $this->uploadCsvFiles($adminServerAddress, $csvResults['sales_file'], $csvResults['payments_file']);
            $results['sales_synced'] = $uploadResults['sales_synced'];
            $results['payments_synced'] = $uploadResults['payments_synced'];
            if (!empty($uploadResults['errors'])) {
                $results['errors'] = array_merge($results['errors'], $uploadResults['errors']);
            }

            // Update sync event status
            if (empty($results['errors'])) {
                $syncEvent->setStatus(SyncEvent::STATUS_SUCCESS);
            } else {
                $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
            }

            $this->em->flush();

            // Clean up temporary CSV files
            if (file_exists($csvResults['sales_file'])) {
                unlink($csvResults['sales_file']);
            }
            if (file_exists($csvResults['payments_file'])) {
                unlink($csvResults['payments_file']);
            }

            return $results;
        } catch (\Exception $e) {
            $results['errors'][] = 'Synchronization failed: ' . $e->getMessage();
            $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
            $this->em->flush();
            return $results;
        }
    }

    /**
     * Synchronize sales and payments from location to admin system for multiple contingencies
     *
     * @param Location $location The location to synchronize data from
     * @param Contingency[] $contingencies The contingency periods to sync
     * @return array Results of the synchronization
     */
    public function syncMultipleContingenciesToAdmin(Location $location, array $contingencies): array
    {
        // Create a new sync event
        $syncEvent = new SyncEvent();
        $syncEvent->setLocation($location);
        $syncEvent->setStatus(SyncEvent::STATUS_INPROGRESS);
        $syncEvent->setType(SyncEvent::TYPE_PUSH);

        $this->em->persist($syncEvent);
        $this->em->flush();

        $results = [
            'sales_synced' => 0,
            'payments_synced' => 0,
            'errors' => []
        ];

        try {
            // Get admin server address from system parameters
            $serverParam = $this->systemParameterRepository->findByCode(SystemParameter::PARAM_SERVER_ADDRESS);
            if (!$serverParam) {
                $results['errors'][] = 'Server address not configured in system parameters';
                $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
                $this->em->flush();
                return $results;
            }

            $adminServerAddress = $serverParam->getValue();

            // Create CSV files for sales and payments across all contingencies
            $csvResults = $this->createCsvFiles($location, $contingencies);
            if (!empty($csvResults['errors'])) {
                $results['errors'] = array_merge($results['errors'], $csvResults['errors']);
                $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
                $this->em->flush();
                return $results;
            }

            // Upload CSV files to admin system
            $uploadResults = $this->uploadCsvFiles($adminServerAddress, $csvResults['sales_file'], $csvResults['payments_file']);
            $results['sales_synced'] = $uploadResults['sales_synced'];
            $results['payments_synced'] = $uploadResults['payments_synced'];
            if (!empty($uploadResults['errors'])) {
                $results['errors'] = array_merge($results['errors'], $uploadResults['errors']);
            }

            // Update sync event status
            if (empty($results['errors'])) {
                $syncEvent->setStatus(SyncEvent::STATUS_SUCCESS);
            } else {
                $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
            }

            $this->em->flush();

            // Clean up temporary CSV files
            if (file_exists($csvResults['sales_file'])) {
                unlink($csvResults['sales_file']);
            }
            if (file_exists($csvResults['payments_file'])) {
                unlink($csvResults['payments_file']);
            }

            return $results;
        } catch (\Exception $e) {
            $results['errors'][] = 'Synchronization failed: ' . $e->getMessage();
            $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
            $this->em->flush();
            return $results;
        }
    }

    /**
     * Create CSV files for sales and payments data across multiple contingencies
     *
     * @param Location $location The location to synchronize data from
     * @param Contingency[] $contingencies The contingency periods to sync
     * @return array Results of the CSV file creation
     */
    private function createCsvFiles(Location $location, array $contingencies): array
    {
        $results = [
            'sales_file' => null,
            'payments_file' => null,
            'errors' => []
        ];

        try {
            // Create temporary directory if it doesn't exist
            $tempDir = sys_get_temp_dir() . '/sync_data';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Create sales CSV file
            $salesFile = $tempDir . '/sales_' . $location->getId() . '_' . time() . '.csv';
            $salesResults = $this->createSalesCsv($salesFile, $contingencies);
            if (!empty($salesResults['errors'])) {
                $results['errors'] = array_merge($results['errors'], $salesResults['errors']);
                return $results;
            }
            $results['sales_file'] = $salesFile;

            // Create payments CSV file
            $paymentsFile = $tempDir . '/payments_' . $location->getId() . '_' . time() . '.csv';
            $paymentsResults = $this->createPaymentsCsv($paymentsFile, $contingencies);
            if (!empty($paymentsResults['errors'])) {
                $results['errors'] = array_merge($results['errors'], $paymentsResults['errors']);
                return $results;
            }
            $results['payments_file'] = $paymentsFile;

            return $results;
        } catch (\Exception $e) {
            $results['errors'][] = 'Failed to create CSV files: ' . $e->getMessage();
            return $results;
        }
    }

    /**
     * Create a CSV file with sales data for multiple contingencies
     *
     * @param string $filePath The path to the CSV file
     * @param Contingency[] $contingencies The contingency periods to sync
     * @return array Results of the CSV creation
     */
    private function createSalesCsv(string $filePath, array $contingencies): array
    {
        $results = [
            'count' => 0,
            'errors' => []
        ];

        try {
            $file = fopen($filePath, 'w');
            if (!$file) {
                $results['errors'][] = 'Failed to create sales CSV file';
                return $results;
            }

            // Write CSV header
            $headers = [
                'id', 'folio', 'createdAt', 'rut', 'clientFullName', 'locationCode',
                'quote_id', 'quote_amount', 'quote_paymentMethod', 'quote_tbkNumber',
                'quote_locationCode', 'quote_quoteDate', 'quote_downPayment',
                'quote_deferredPayment', 'quote_installments', 'quote_interest',
                'quote_installmentAmount', 'quote_totalAmount', 'quote_publicId',
                'quote_billingDate', 'createdById', 'deviceId', 'contingencyId'
            ];
            fputcsv($file, $headers);

            // Process each contingency
            foreach ($contingencies as $contingency) {
                // Get all sales for the contingency period
                $sales = $this->saleRepository->findBy(['contingency' => $contingency]);

                // Process sales in batches
                $batchCount = ceil(count($sales) / self::BATCH_SIZE);

                for ($i = 0; $i < $batchCount; $i++) {
                    $batch = array_slice($sales, $i * self::BATCH_SIZE, self::BATCH_SIZE);

                    foreach ($batch as $sale) {
                        try {
                            // Prepare sale data for CSV
                            $saleData = $this->prepareSaleData($sale);

                            // Flatten the data for CSV
                            $csvRow = [
                                $saleData['id'],
                                $saleData['folio'],
                                $saleData['createdAt'],
                                $saleData['rut'],
                                $saleData['clientFullName'],
                                $saleData['locationCode'],
                                $saleData['quote']['id'] ?? '',
                                $saleData['quote']['amount'] ?? '',
                                $saleData['quote']['paymentMethod'] ?? '',
                                $saleData['quote']['tbkNumber'] ?? '',
                                $saleData['quote']['locationCode'] ?? '',
                                $saleData['quote']['quoteDate'] ?? '',
                                $saleData['quote']['downPayment'] ?? '',
                                $saleData['quote']['deferredPayment'] ?? '',
                                $saleData['quote']['installments'] ?? '',
                                $saleData['quote']['interest'] ?? '',
                                $saleData['quote']['installmentAmount'] ?? '',
                                $saleData['quote']['totalAmount'] ?? '',
                                $saleData['quote']['publicId'] ?? '',
                                $saleData['quote']['billingDate'] ?? '',
                                $saleData['createdById'],
                                $saleData['deviceId'],
                                $saleData['contingencyId']
                            ];

                            fputcsv($file, $csvRow);
                            $results['count']++;
                        } catch (\Exception $e) {
                            $results['errors'][] = sprintf(
                                'Error processing sale ID %d for CSV: %s',
                                $sale->getId(),
                                $e->getMessage()
                            );
                        }
                    }

                    // Clear the entity manager to free memory
                    $this->em->clear();
                }
            }

            fclose($file);
            return $results;
        } catch (\Exception $e) {
            $results['errors'][] = 'Failed to create sales CSV: ' . $e->getMessage();
            return $results;
        }
    }

    /**
     * Create a CSV file with payments data for multiple contingencies
     *
     * @param string $filePath The path to the CSV file
     * @param Contingency[] $contingencies The contingency periods to sync
     * @return array Results of the CSV creation
     */
    private function createPaymentsCsv(string $filePath, array $contingencies): array
    {
        $results = [
            'count' => 0,
            'errors' => []
        ];

        try {
            $file = fopen($filePath, 'w');
            if (!$file) {
                $results['errors'][] = 'Failed to create payments CSV file';
                return $results;
            }

            // Write CSV header
            $headers = [
                'id', 'amount', 'createdAt', 'paymentMethod', 'voucherId', 'publicId',
                'rut', 'clientFullName', 'createdById', 'deviceId', 'contingencyId'
            ];
            fputcsv($file, $headers);

            // Process each contingency
            foreach ($contingencies as $contingency) {
                // Get all payments for the contingency period
                $payments = $this->paymentRepository->findBy(['contingency' => $contingency]);

                // Process payments in batches
                $batchCount = ceil(count($payments) / self::BATCH_SIZE);

                for ($i = 0; $i < $batchCount; $i++) {
                    $batch = array_slice($payments, $i * self::BATCH_SIZE, self::BATCH_SIZE);

                    foreach ($batch as $payment) {
                        try {
                            // Prepare payment data for CSV
                            $paymentData = $this->preparePaymentData($payment);

                            // Flatten the data for CSV
                            $csvRow = [
                                $paymentData['id'],
                                $paymentData['amount'],
                                $paymentData['createdAt'],
                                $paymentData['paymentMethod'],
                                $paymentData['voucherId'],
                                $paymentData['publicId'],
                                $paymentData['rut'],
                                $paymentData['clientFullName'],
                                $paymentData['createdById'],
                                $paymentData['deviceId'],
                                $paymentData['contingencyId']
                            ];

                            fputcsv($file, $csvRow);
                            $results['count']++;
                        } catch (\Exception $e) {
                            $results['errors'][] = sprintf(
                                'Error processing payment ID %d for CSV: %s',
                                $payment->getId(),
                                $e->getMessage()
                            );
                        }
                    }

                    // Clear the entity manager to free memory
                    $this->em->clear();
                }
            }

            fclose($file);
            return $results;
        } catch (\Exception $e) {
            $results['errors'][] = 'Failed to create payments CSV: ' . $e->getMessage();
            return $results;
        }
    }

    /**
     * Upload CSV files to the admin system
     *
     * @param string $adminServerAddress The admin server address
     * @param string $salesFile The path to the sales CSV file
     * @param string $paymentsFile The path to the payments CSV file
     * @return array Results of the upload
     */
    private function uploadCsvFiles(string $adminServerAddress, string $salesFile, string $paymentsFile): array
    {
        $results = [
            'sales_synced' => 0,
            'payments_synced' => 0,
            'errors' => []
        ];

        try {
            // Upload sales CSV file
            if (file_exists($salesFile)) {
                $url = $adminServerAddress . $this->urlGenerator->generate('app_sync_push_sales_csv', [], UrlGeneratorInterface::ABSOLUTE_PATH);
                $response = $this->httpClient->request('POST', $url, [
                    'body' => [
                        'file' => fopen($salesFile, 'r'),
                    ]
                ]);

                if ($response->getStatusCode() === 200 || $response->getStatusCode() === 201) {
                    $responseData = json_decode($response->getContent(), true);
                    $results['sales_synced'] = $responseData['count'] ?? 0;
                } else {
                    $results['errors'][] = sprintf(
                        'Failed to upload sales CSV: HTTP %d - %s',
                        $response->getStatusCode(),
                        $response->getContent(false)
                    );
                }
            }

            // Upload payments CSV file
            if (file_exists($paymentsFile)) {
                $url = $adminServerAddress . $this->urlGenerator->generate('app_sync_push_payments_csv', [], UrlGeneratorInterface::ABSOLUTE_PATH);
                $response = $this->httpClient->request('POST', $url, [
                    'body' => [
                        'file' => fopen($paymentsFile, 'r'),
                    ]
                ]);

                if ($response->getStatusCode() === 200 || $response->getStatusCode() === 201) {
                    $responseData = json_decode($response->getContent(), true);
                    $results['payments_synced'] = $responseData['count'] ?? 0;
                } else {
                    $results['errors'][] = sprintf(
                        'Failed to upload payments CSV: HTTP %d - %s',
                        $response->getStatusCode(),
                        $response->getContent(false)
                    );
                }
            }

            return $results;
        } catch (\Exception $e) {
            $results['errors'][] = 'Failed to upload CSV files: ' . $e->getMessage();
            return $results;
        }
    }

    /**
     * Prepare sale data for synchronization
     *
     * @param Sale $sale The sale entity
     * @return array The prepared data
     */
    private function prepareSaleData(Sale $sale): array
    {
        $quote = $sale->getQuote();
        $client = $this->em->getRepository('App\Entity\Client')->findOneBy(['rut' => $sale->getRut()]);

        return [
            'id' => $sale->getId(),
            'folio' => $sale->getFolio(),
            'createdAt' => $sale->getCreatedAt()->format('Y-m-d H:i:s'),
            'rut' => $sale->getRut(),
            'clientFullName' => $client ? $client->getFullName() : null,
            'locationCode' => $quote ? $quote->getLocationCode() : null,
            'quote' => $quote ? [
                'id' => $quote->getId(),
                'amount' => $quote->getAmount(),
                'paymentMethod' => $quote->getPaymentMethod(),
                'tbkNumber' => $quote->getTbkNumber(),
                'locationCode' => $quote->getLocationCode(),
                'quoteDate' => $quote->getQuoteDate()->format('Y-m-d'),
                'downPayment' => $quote->getDownPayment(),
                'deferredPayment' => $quote->getDeferredPayment(),
                'installments' => $quote->getInstallments(),
                'interest' => $quote->getInterest(),
                'installmentAmount' => $quote->getInstallmentAmount(),
                'totalAmount' => $quote->getTotalAmount(),
                'publicId' => $quote->getPublicId(),
                'billingDate' => $quote->getBillingDate() ? $quote->getBillingDate()->format('Y-m-d') : null
            ] : null,
            'createdById' => $sale->getCreatedBy() ? $sale->getCreatedBy()->getId() : null,
            'deviceId' => $sale->getDevice() ? $sale->getDevice()->getId() : null,
            'contingencyId' => $sale->getContingency() ? $sale->getContingency()->getId() : null
        ];
    }

    /**
     * Prepare payment data for synchronization
     *
     * @param Payment $payment The payment entity
     * @return array The prepared data
     */
    private function preparePaymentData(Payment $payment): array
    {
        $client = $this->em->getRepository('App\Entity\Client')->findOneBy(['rut' => $payment->getRut()]);

        return [
            'id' => $payment->getId(),
            'amount' => $payment->getAmount(),
            'createdAt' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
            'paymentMethod' => $payment->getPaymentMethod(),
            'voucherId' => $payment->getVoucherId(),
            'publicId' => $payment->getPublicId(),
            'rut' => $payment->getRut(),
            'clientFullName' => $client ? $client->getFullName() : null,
            'createdById' => $payment->getCreatedBy() ? $payment->getCreatedBy()->getId() : null,
            'deviceId' => $payment->getDevice() ? $payment->getDevice()->getId() : null,
            'contingencyId' => $payment->getContingency() ? $payment->getContingency()->getId() : null
        ];
    }
}
