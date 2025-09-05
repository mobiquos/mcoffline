<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Contingency;
use App\Entity\Device;
use App\Entity\Payment;
use App\Entity\Quote;
use App\Entity\Sale;
use App\Entity\SyncEvent;
use App\Entity\User;
use App\Service\LocationToAdminSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:main:process-all',
    description: 'Process pending sync events and register quotes, sales and payments from uploaded files',
)]
class ProcessPendingMainSyncCommand extends Command
{
    private $entityManager;
    private $locationToAdminSyncService;

    public function __construct(
        EntityManagerInterface $entityManager,
        LocationToAdminSyncService $locationToAdminSyncService
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->locationToAdminSyncService = $locationToAdminSyncService;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command processes pending sync events and registers quotes, sales and payments from uploaded files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find pending sync events of type PUSH (these are the ones with uploaded files)
        $pendingSyncEvents = $this->entityManager->getRepository(SyncEvent::class)->findBy([
            'status' => SyncEvent::STATUS_PENDING,
            'type' => SyncEvent::TYPE_PUSH
        ]);

        if (empty($pendingSyncEvents)) {
            $io->info('No pending sync events found.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d pending sync events to process.', count($pendingSyncEvents)));

        // Group sync events by location and identify the latest for each location
        $syncEventsByLocation = [];
        foreach ($pendingSyncEvents as $syncEvent) {
            $locationId = $syncEvent->getLocation() ? $syncEvent->getLocation()->getId() : null;
            if ($locationId) {
                if (!isset($syncEventsByLocation[$locationId])) {
                    $syncEventsByLocation[$locationId] = [];
                }
                $syncEventsByLocation[$locationId][] = $syncEvent;
            }
        }

        // Identify the latest sync event for each location
        $latestSyncEvents = [];
        foreach ($syncEventsByLocation as $locationId => $syncEvents) {
            // Sort by createdAt descending to get the latest first
            usort($syncEvents, function ($a, $b) {
                return $b->getCreatedAt() <=> $a->getCreatedAt();
            });

            // The first one is the latest
            $latestSyncEvents[] = $syncEvents[0];

            // Mark the rest as failed
            for ($i = 1; $i < count($syncEvents); $i++) {
                $syncEvent = $syncEvents[$i];
                $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
                $syncEvent->setComments('Skipped: newer sync event exists for this location');
                $this->entityManager->flush();
                $io->warning(sprintf('Marked sync event ID: %s as failed (skipped - newer event exists)', $syncEvent->getSyncId()));
            }
        }

        $uploadsDirectory = $this->getApplication()->getKernel()->getProjectDir() . '/var/sync';

        foreach ($latestSyncEvents as $syncEvent) {
            $io->info(sprintf('Processing sync event ID: %s', $syncEvent->getSyncId()));

            // Update status to in progress
            $syncEvent->setStatus(SyncEvent::STATUS_INPROGRESS);
            $this->entityManager->flush();

            try {
                $this->processSalesFile($syncEvent, $uploadsDirectory, $io);
                $this->processPaymentsFile($syncEvent, $uploadsDirectory, $io);

                // Update status to success
                $syncEvent->setStatus(SyncEvent::STATUS_SUCCESS);
                $this->entityManager->flush();

                $io->success(sprintf('Sync event ID: %s processed successfully', $syncEvent->getSyncId()));
            } catch (\Exception $e) {
                $io->error(sprintf('Error processing sync event ID: %s - %s', $syncEvent->getSyncId(), $e->getMessage()));
                $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
                $this->entityManager->flush();
            }
        }

        return Command::SUCCESS;
    }

    private function processSalesFile(SyncEvent $syncEvent, string $uploadsDirectory, SymfonyStyle $io): void
    {
        $filename = 'sales_' . str_replace(".", "_", $syncEvent->getSyncId()) . '.csv';
        $filepath = $uploadsDirectory . '/' . $filename;

        // Check if file exists
        if (!file_exists($filepath)) {
            throw new \Exception(sprintf('Sales file not found: %s', $filepath));
        }

        $io->info('Processing sales file: ' . $filename);

        // Read and process the CSV file
        $file = fopen($filepath, 'r');
        if (!$file) {
            throw new \Exception('Could not open sales file: ' . $filepath);
        }

        $lineNumber = 0;
        $salesProcessed = 0;

        // Skip header row
        $headers = fgetcsv($file);

        while (($data = fgetcsv($file)) !== false) {
            $lineNumber++;
            $data = array_combine($headers, $data);
            try {
                $sale = $this->transformDataToSale($data);
                $user = $this->entityManager->getRepository(User::class)->find($data['createdById']);
                $device = $this->entityManager->getRepository(Device::class)->find($data['deviceId']);
                $contingency = $this->entityManager->getRepository(Contingency::class)->find($data['contingencyId']);

                if ($user) {
                    $sale->setCreatedBy($user);
                    $sale->getQuote()->setCreatedBy($user);
                }

                if ($device) {
                    $sale->setDevice($device);
                }

                if ($contingency) {
                    $sale->setContingency($contingency);
                    $sale->getQuote()->setContingency($contingency);
                }

                $this->entityManager->persist($sale);

                $salesProcessed++;
            } catch (\Exception $e) {
                $io->warning(sprintf('Error processing sale at line %d: %s', $lineNumber, $e->getMessage()));
            }
        }

        fclose($file);
        $io->info(sprintf('Processed %d sales', $salesProcessed));

        // Remove the file after processing
        // unlink($filepath);
    }

    private function transformDataToSale(array $d): Sale
    {
        $s = new Sale();
        $q = new Quote();

        $q->setRut($d['rut']);
        $q->setAmount($d['quote_amount']);
        $q->setPaymentMethod($d['quote_paymentMethod']);
        $q->setTbkNumber($d['quote_tbkNumber']);
        $q->setLocationCode($d['quote_locationCode']);
        $q->setQuoteDate((new \DateTime())->createFromFormat("Y-m-d", $d['quote_quoteDate']));
        $q->setDownPayment(0);
        $q->setInstallments($d['quote_installments']);
        $q->setInterest($d['quote_interest']);
        $q->setInstallmentAmount($d['quote_installmentAmount']);
        $q->setTotalAmount($d['quote_totalAmount']);
        $q->setPublicId($d['quote_publicId']);
        $q->setBillingDate((new \DateTime())->createFromFormat("Y-m-d", $d['quote_billingDate']));
        $s->setQuote($q);
        $s->setFolio($d['folio']);
        $s->setRut($d['rut']);
        $s->setCreatedAt((new \DateTimeImmutable())->createFromFormat("Y-m-d H:i:s", $d['createdAt']));

        return $s;
    }

    private function processPaymentsFile(SyncEvent $syncEvent, string $uploadsDirectory, SymfonyStyle $io): void
    {
        $filename = 'payments_' . str_replace(".", "_", $syncEvent->getSyncId()) . '.csv';
        $filepath = $uploadsDirectory . '/' . $filename;

        // Check if file exists
        if (!file_exists($filepath)) {
            throw new \Exception(sprintf('Payments file not found: %s', $filepath));
        }

        $io->info('Processing payments file: ' . $filename);

        // Read and process the CSV file
        $file = fopen($filepath, 'r');
        if (!$file) {
            throw new \Exception('Could not open payments file: ' . $filepath);
        }

        $lineNumber = 0;
        $paymentsProcessed = 0;

        // Skip header row
        $headers = fgetcsv($file);

        while (($data = fgetcsv($file)) !== false) {
            $lineNumber++;
            $data = array_combine($headers, $data);
            try {
                $p = $this->transformDataToPayment($data);
                $user = $this->entityManager->getRepository(User::class)->find($data['createdById']);
                $device = $this->entityManager->getRepository(Device::class)->find($data['deviceId']);
                $contingency = $this->entityManager->getRepository(Contingency::class)->find($data['contingencyId']);

                if ($user) {
                    $p->setCreatedBy($user);
                }

                if ($device) {
                    $p->setDevice($device);
                }

                if ($contingency) {
                    $p->setContingency($contingency);
                }

                $this->entityManager->persist($p);

                $paymentsProcessed++;
            } catch (\Exception $e) {
                $io->warning(sprintf('Error processing payment at line %d: %s', $lineNumber, $e->getMessage()));
            }
        }

        fclose($file);
        $io->info(sprintf('Processed %d payments', $paymentsProcessed));

        // Remove the file after processing
        // unlink($filepath);
    }

    private function transformDataToPayment(array $d): Payment
    {
        $s = new Payment();

        $s->setCreatedAt((new \DateTime())->createFromFormat("Y-m-d H:i:s", $d['createdAt']));
        $s->setAmount($d['amount']);
        $s->setPaymentMethod($d['paymentMethod']);
        $s->setVoucherId($d['voucherId']);
        $s->setRut($d['rut']);
        $s->setPublicId($d['publicId']);

        return $s;
    }
}
