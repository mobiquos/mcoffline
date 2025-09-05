<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Contingency;
use App\Entity\Location;
use App\Entity\Sale;
use App\Entity\Payment;
use App\Entity\Quote;
use App\Entity\SyncEvent;
use App\Entity\SystemParameter;
use App\Repository\SaleRepository;
use App\Repository\PaymentRepository;
use App\Repository\QuoteRepository;
use App\Repository\ContingencyRepository;
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
        private readonly QuoteRepository $quoteRepository,
        private readonly ContingencyRepository $contingencyRepository,
        private readonly SystemParameterRepository $systemParameterRepository,
        private readonly SyncEventRepository $syncEventRepository,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * Synchronize sales, payments, and quotes from location to admin system for multiple contingencies
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
            'quotes_synced' => 0,
            'contingencies_synced' => 0,
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

            // Push all contingencies directly (since they are few and with little data)
            $syncEvent->setComments($syncEvent->getComments() . "\nInicio sincronizacion contingencias");
            $contingencyResults = $this->pushContingencies($adminServerAddress, $syncEvent, $contingencies);
            $results['contingencies_synced'] = $contingencyResults['count'];
            if (!empty($contingencyResults['errors'])) {
                $results['errors'] = array_merge($results['errors'], $contingencyResults['errors']);
                $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
                $this->em->flush();
                return $results;
            }
            $syncEvent->setComments($syncEvent->getComments() . "\nTermino sincronizacion contingencias");

            $syncEvent->setComments($syncEvent->getComments() . "\nInicio sincronizacion ventas y otros.");
            // Create CSV files for sales, payments, and quotes across all contingencies
            $csvResults = $this->createCsvFiles($location, $contingencies);
            if (!empty($csvResults['errors'])) {
                $results['errors'] = array_merge($results['errors'], $csvResults['errors']);
                $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
                $this->em->flush();
                return $results;
            }

            // Upload CSV files to admin system
            $uploadResults = $this->uploadCsvFiles($syncEvent->getSyncId(), $adminServerAddress, $csvResults['sales_file'], $csvResults['payments_file']);
            $results['sales_synced'] = $uploadResults['sales_synced'];
            $results['payments_synced'] = $uploadResults['payments_synced'];
            // $results['quotes_synced'] = $uploadResults['quotes_synced'];
            if (!empty($uploadResults['errors'])) {
                $results['errors'] = array_merge($results['errors'], $uploadResults['errors']);
            }
            $comments = $syncEvent->getComments();
            $syncEvent = $this->syncEventRepository->find($syncEvent->getId());

            $syncEvent->setStatus(SyncEvent::STATUS_SUCCESS);
            // Update sync event status
            if (!empty($results['errors'])) {
                $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
            }

            $syncEvent->setComments($syncEvent->getComments() . "\nTermino sincronizacion ventas y otros.");
            // $this->em->persist($syncEvent);
            $this->em->flush();

            // Clean up temporary CSV files
            if (file_exists($csvResults['sales_file'])) {
                unlink($csvResults['sales_file']);
            }
            if (file_exists($csvResults['payments_file'])) {
                unlink($csvResults['payments_file']);
            }
            // if (file_exists($csvResults['quotes_file'])) {
            //     unlink($csvResults['quotes_file']);
            // }

            return $results;
        } catch (\Exception $e) {
            $results['errors'][] = 'Synchronization failed: ' . $e->getMessage();
            $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
            $this->em->flush();
            return $results;
        }
    }

    /**
     * Create CSV files for sales, payments, quotes, and contingencies data across multiple contingencies
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
            'quotes_file' => null,
            'contingencies_file' => null,
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

            // Create quotes CSV file
            // $quotesFile = $tempDir . '/quotes_' . $location->getId() . '_' . time() . '.csv';
            // $quotesResults = $this->createQuotesCsv($quotesFile, $contingencies);
            // if (!empty($quotesResults['errors'])) {
            //     $results['errors'] = array_merge($results['errors'], $quotesResults['errors']);
            //     return $results;
            // }
            // $results['quotes_file'] = $quotesFile;

            // Create contingencies CSV file
            // $contingenciesFile = $tempDir . '/contingencies_' . $location->getId() . '_' . time() . '.csv';
            // $contingenciesResults = $this->createContingenciesCsv($contingenciesFile, $contingencies);
            // if (!empty($contingenciesResults['errors'])) {
            //     $results['errors'] = array_merge($results['errors'], $contingenciesResults['errors']);
            //     return $results;
            // }
            // $results['contingencies_file'] = $contingenciesFile;

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
     * Create a CSV file with quotes data for multiple contingencies
     *
     * @param string $filePath The path to the CSV file
     * @param Contingency[] $contingencies The contingency periods to sync
     * @return array Results of the CSV creation
     */
    private function createQuotesCsv(string $filePath, array $contingencies): array
    {
        $results = [
            'count' => 0,
            'errors' => []
        ];

        try {
            $file = fopen($filePath, 'w');
            if (!$file) {
                $results['errors'][] = 'Failed to create quotes CSV file';
                return $results;
            }

            // Write CSV header
            $headers = [
                'id', 'rut', 'amount', 'paymentMethod', 'tbkNumber', 'locationCode',
                'quoteDate', 'createdById', 'downPayment', 'deferredPayment',
                'installments', 'interest', 'installmentAmount', 'totalAmount',
                'contingencyId', 'billingDate', 'publicId'
            ];
            fputcsv($file, $headers);

            // Process each contingency
            foreach ($contingencies as $contingency) {
                // Get all quotes for the contingency period
                $quotes = $this->quoteRepository->findBy(['contingency' => $contingency]);

                // Process quotes in batches
                $batchCount = ceil(count($quotes) / self::BATCH_SIZE);

                for ($i = 0; $i < $batchCount; $i++) {
                    $batch = array_slice($quotes, $i * self::BATCH_SIZE, self::BATCH_SIZE);

                    foreach ($batch as $quote) {
                        try {
                            // Prepare quote data for CSV
                            $quoteData = $this->prepareQuoteData($quote);

                            // Flatten the data for CSV
                            $csvRow = [
                                $quoteData['id'],
                                $quoteData['rut'],
                                $quoteData['amount'],
                                $quoteData['paymentMethod'],
                                $quoteData['tbkNumber'],
                                $quoteData['locationCode'],
                                $quoteData['quoteDate'],
                                $quoteData['createdById'],
                                $quoteData['downPayment'],
                                $quoteData['deferredPayment'],
                                $quoteData['installments'],
                                $quoteData['interest'],
                                $quoteData['installmentAmount'],
                                $quoteData['totalAmount'],
                                $quoteData['contingencyId'],
                                $quoteData['billingDate'],
                                $quoteData['publicId']
                            ];

                            fputcsv($file, $csvRow);
                            $results['count']++;
                        } catch (\Exception $e) {
                            $results['errors'][] = sprintf(
                                'Error processing quote ID %d for CSV: %s',
                                $quote->getId(),
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
            $results['errors'][] = 'Failed to create quotes CSV: ' . $e->getMessage();
            return $results;
        }
    }

    /**
     * Create a CSV file with contingencies data
     *
     * @param string $filePath The path to the CSV file
     * @param Contingency[] $contingencies The contingency periods to sync
     * @return array Results of the CSV creation
     */
    private function createContingenciesCsv(string $filePath, array $contingencies): array
    {
        $results = [
            'count' => 0,
            'errors' => []
        ];

        try {
            $file = fopen($filePath, 'w');
            if (!$file) {
                $results['errors'][] = 'Failed to create contingencies CSV file';
                return $results;
            }

            // Write CSV header
            $headers = [
                'id', 'locationId', 'locationCode', 'startedAt', 'endedAt',
                'startedById', 'startedByName', 'comment'
            ];
            fputcsv($file, $headers);

            // Process each contingency
            foreach ($contingencies as $contingency) {
                try {
                    // Prepare contingency data for CSV
                    $contingencyData = $this->prepareContingencyData($contingency);

                    // Flatten the data for CSV
                    $csvRow = [
                        $contingencyData['id'],
                        $contingencyData['locationId'],
                        $contingencyData['locationCode'],
                        $contingencyData['startedAt'],
                        $contingencyData['endedAt'],
                        $contingencyData['startedById'],
                        $contingencyData['startedByName'],
                        $contingencyData['comment']
                    ];

                    fputcsv($file, $csvRow);
                    $results['count']++;
                } catch (\Exception $e) {
                    $results['errors'][] = sprintf(
                        'Error processing contingency ID %s for CSV: %s',
                        $contingency->getId(),
                        $e->getMessage()
                    );
                }
            }

            fclose($file);
            return $results;
        } catch (\Exception $e) {
            $results['errors'][] = 'Failed to create contingencies CSV: ' . $e->getMessage();
            return $results;
        }
    }

    /**
     * Push multiple contingencies to the admin system
     *
     * @param string $adminServerAddress The admin server address
     * @param Contingency[] $contingencies The contingencies to push
     * @return array Results of the push operation
     */
    private function pushContingencies(string $adminServerAddress, SyncEvent $syncEvent, array $contingencies): array
    {
        $results = [
            'count' => 0,
            'errors' => []
        ];

        // If no contingencies, return early
        if (empty($contingencies)) {
            return $results;
        }

        try {
            // Prepare all contingencies data
            $allContingenciesData = [];
            foreach ($contingencies as $contingency) {
                $allContingenciesData[] = $this->prepareContingencyData($contingency);
            }

            // Send all contingencies data in a single request
            $url = $adminServerAddress . $this->urlGenerator->generate('app_sync_push_contingency', [], UrlGeneratorInterface::ABSOLUTE_PATH);
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'contingencies' => $allContingenciesData,
                    'locationCode' => current($allContingenciesData)['locationCode'],
                    'syncId' => $syncEvent->getSyncId(),
                ]
            ]);

            // Check if the request was successful
            if ($response->getStatusCode() === 200 || $response->getStatusCode() === 201) {
                $results['count'] = count($contingencies);
            } else {
                $results['errors'][] = sprintf(
                    'Failed to push contingencies: HTTP %d - %s',
                    $response->getStatusCode(),
                    $response->getContent(false)
                );
            }
        } catch (\Exception $e) {
            $results['errors'][] = sprintf(
                'Error pushing contingencies: %s',
                $e->getMessage()
            );
        }

        return $results;
    }

    /**
     * Upload CSV files to the admin system
     *
     * @param string $adminServerAddress The admin server address
     * @param string $salesFile The path to the sales CSV file
     * @param string $paymentsFile The path to the payments CSV file
     * @param string $quotesFile The path to the quotes CSV file
     * @return array Results of the upload
     */
    private function uploadCsvFiles(string $syncId, string $adminServerAddress, string $salesFile, string $paymentsFile): array
    {
        $results = [
            'sales_synced' => 0,
            'payments_synced' => 0,
            'quotes_synced' => 0,
            'contingencies_synced' => 0,
            'errors' => []
        ];

        try {
            // Upload sales CSV file
            if (file_exists($salesFile)) {
                $url = $adminServerAddress . $this->urlGenerator->generate('app_sync_push_sales_csv', [], UrlGeneratorInterface::ABSOLUTE_PATH);
                $response = $this->httpClient->request('POST', $url, [
                    'body' => [
                        'syncId' => $syncId,
                        'file' => fopen($salesFile, 'r', ),
                    ],
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
                        'syncId' => $syncId,
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
        $client = $this->em->getRepository(Client::class)->findOneBy(['rut' => $sale->getRut()]);

        return [
            'id' => $sale->getId(),
            'folio' => $sale->getFolio(),
            'createdAt' => $sale->getCreatedAt()->format('Y-m-d H:i:s'),
            'rut' => $sale->getRut(),
            'clientFullName' => $client ? $client->getFullName() : null,
            'locationCode' => $quote ? $quote->getLocationCode() : null,
            'quote' => $quote ? [
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
        $client = $this->em->getRepository(Client::class)->findOneBy(['rut' => $payment->getRut()]);

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

    /**
     * Prepare contingency data for synchronization
     *
     * @param Contingency $contingency The contingency entity
     * @return array The prepared data
     */
    private function prepareContingencyData(Contingency $contingency): array
    {
        return [
            'id' => $contingency->getId(),
            'locationId' => $contingency->getLocation() ? $contingency->getLocation()->getId() : null,
            'locationCode' => $contingency->getLocationCode(),
            'startedAt' => $contingency->getStartedAt()->format('Y-m-d H:i:s'),
            'endedAt' => $contingency->getEndedAt() ? $contingency->getEndedAt()->format('Y-m-d H:i:s') : null,
            'startedById' => $contingency->getStartedBy() ? $contingency->getStartedBy()->getId() : null,
            'startedByName' => $contingency->getStartedByName(),
            'comment' => $contingency->getComment()
        ];
    }
}
