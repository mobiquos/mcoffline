<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\SyncEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process-sync-files',
    description: 'Process uploaded sync files and update client data',
)]
class ProcessSyncFilesCommand extends Command
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command processes pending sync files and updates client data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find pending sync events
        $pendingSyncEvents = $this->entityManager->getRepository(SyncEvent::class)->findBy(['status' => SyncEvent::STATUS_PENDING]);

        if (empty($pendingSyncEvents)) {
            $io->info('No pending sync events found.');
            return Command::SUCCESS;
        }

        $uploadsDirectory = $this->getApplication()->getKernel()->getProjectDir() . '/var/uploads/sync';

        foreach ($pendingSyncEvents as $syncEvent) {
            $io->info(sprintf('Processing sync event ID: %d', $syncEvent->getId()));

            // Update status to in progress
            $syncEvent->setStatus(SyncEvent::STATUS_INPROGRESS);
            $this->entityManager->flush();

            try {
                // Get the file path
                $filename = $syncEvent->getId() . '.csv';
                $filepath = $uploadsDirectory . '/' . $filename;

                // Check if file exists
                if (!file_exists($filepath)) {
                    $io->error(sprintf('File not found for sync event ID: %d', $syncEvent->getId()));
                    $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
                    $this->entityManager->flush();
                    continue;
                }

                // Clear existing clients
                $this->entityManager->createQuery('DELETE FROM App\Entity\Client')->execute();

                // Process the file
                ini_set('memory_limit', '-1');
                ini_set('max_execution_time', '300'); // 5 minutes

                $file = fopen($filepath, 'r');
                if (!$file) {
                    throw new \Exception('Could not open file: ' . $filepath);
                }

                $lineNumber = 0;
                while (($line = fgets($file)) !== false) {
                    $lineNumber++;
                    if ($lineNumber == 1) continue;
                    try {
                        $client = $this->parseClient($line);
                        $this->entityManager->persist($client);

                        // Flush every 1000 records to avoid memory issues
                        if ($lineNumber % 1 === 0) {
                            $this->entityManager->flush();
                            $this->entityManager->clear();
                            // $this->entityManager->resetManager();
                        }
                    } catch (\Exception $e) {
                        dump($line);
                        $io->warning(sprintf('Error parsing line %d: %s', $lineNumber, $e->getMessage()));
                        break;
                        // Continue with next line
                    }
                }
                fclose($file);

                // Final flush for remaining records
                $this->entityManager->flush();

                // Update status to success
                $syncEvent->setStatus(SyncEvent::STATUS_SUCCESS);
                $this->entityManager->flush();

                $io->success(sprintf('Sync event ID: %d processed successfully', $syncEvent->getId()));

                // Optionally remove the file after processing
                unlink($filepath);
            } catch (\Exception $e) {
                $io->error(sprintf('Error processing sync event ID: %d - %s', $syncEvent->getId(), $e->getMessage()));
                $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
                $this->entityManager->flush();
            }
        }

        return Command::SUCCESS;
    }

    private function parseClient(string $string): Client
    {
        $newstr = str_replace("\"", '', $string);
        $data = explode(";", preg_replace('/[[:^print:]]/', '', $newstr));

        $client = new Client();
        $client->setRut(str_replace(["-", "."], "", trim($data[0], "0")));
        $client->setFirstLastName($data[1]);
        $client->setSecondLastName($data[2]);
        $client->setName($data[3]);
        $client->setCreditLimit((int)$data[4]);
        $client->setCreditAvailable((int)$data[5]);
        $client->setBlockComment($data[6]);
        $client->setOverdue((int)$data[7]);
        $client->setNextBillingAt(\DateTime::createFromFormat("d/m/Y", $data[8]));
        $client->setLastUpdatedAt(new \DateTime());

        return $client;
    }
}
