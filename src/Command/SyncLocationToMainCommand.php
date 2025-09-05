<?php

namespace App\Command;

use App\Entity\Contingency;
use App\Entity\SyncEvent;
use App\Entity\SystemParameter;
use App\Service\LocationToAdminSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:location:push',
    description: 'Synchronize sales and payments from location to main system',
)]
class SyncLocationToMainCommand extends Command
{
    public function __construct(
        private readonly LocationToAdminSyncService $locationToAdminSyncService,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command synchronizes sales and payments from a location to the admin system.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get location code from system parameters
        $locationParam = $this->entityManager->getRepository(SystemParameter::class)->findByCode(SystemParameter::PARAM_LOCATION_CODE);
        if (!$locationParam) {
            $io->error('Location code not configured in system parameters.');
            return Command::FAILURE;
        }

        $locationCode = $locationParam->getValue();

        // Find the location by code
        $location = $this->entityManager->getRepository('App\Entity\Location')->findOneBy(['code' => $locationCode]);

        if (!$location) {
            $io->error(sprintf('Location with code "%s" not found.', $locationCode));
            return Command::FAILURE;
        }

        // Check if there's another synchronization in progress
        $inProgressSync = $this->entityManager->getRepository(SyncEvent::class)->findOneBy([
            'status' => SyncEvent::STATUS_INPROGRESS,
            'type' => SyncEvent::TYPE_PUSH,
            'location' => $location
        ]);

        if ($inProgressSync) {
            // Check if the in-progress sync is older than 10 minutes
            $tenMinutesAgo = new \DateTime('-10 minutes');
            $createdAt = $inProgressSync->getCreatedAt();

            if ($createdAt < $tenMinutesAgo) {
                // Mark the old in-progress sync as failed
                $inProgressSync->setStatus(SyncEvent::STATUS_FAILED);
                $inProgressSync->setComments($inProgressSync->getComments() . '\nSynchronization timed out after 10 minutes');
                $this->entityManager->persist($inProgressSync);
                $this->entityManager->flush();

                $io->warning(sprintf(
                    'Previous synchronization (ID: %d) timed out after 10 minutes. Marking as failed and continuing with new sync.',
                    $inProgressSync->getId()
                ));
            } else {
                $io->error(sprintf(
                    'Another synchronization is already in progress for this location (ID: %d, started at: %s). Please wait for it to complete.',
                    $inProgressSync->getId(),
                    $inProgressSync->getCreatedAt()->format('Y-m-d H:i:s')
                ));
                return Command::FAILURE;
            }
        }

        try {
            // Determine which contingencies to sync
            $contingenciesToSync = [];

            // Sync all contingencies
            $contingenciesToSync = $this->findContingenciesToSync();
            $io->info('Syncing all contingencies');

            $io->info(sprintf(
                'Starting synchronization for location "%s" with %d contingencies',
                $locationCode,
                count($contingenciesToSync)
            ));

            $totalResults = [
                'sales_synced' => 0,
                'payments_synced' => 0,
                'quotes_synced' => 0,
                'contingencies_synced' => 0,
                'errors' => []
            ];

            $io->info(sprintf('Syncing %d contingencies together', count($contingenciesToSync)));

            // Initiate the synchronization process for all contingencies
            $results = $this->locationToAdminSyncService->syncMultipleContingenciesToAdmin($location, $contingenciesToSync);

            $totalResults['sales_synced'] += $results['sales_synced'];
            $totalResults['payments_synced'] += $results['payments_synced'];
            $totalResults['quotes_synced'] += $results['quotes_synced'];
            $totalResults['contingencies_synced'] += $results['contingencies_synced'];
            if (!empty($results['errors'])) {
                $totalResults['errors'] = array_merge($totalResults['errors'], $results['errors']);
            }

            if (!empty($results['errors'])) {
                $io->warning('Errors encountered while syncing contingencies:');
                foreach ($results['errors'] as $error) {
                    $io->text('  - ' . $error);
                }
            }

            if (empty($totalResults['errors'])) {
                $io->success(sprintf(
                    'Synchronization completed successfully. Total sales synced: %d, Total payments synced: %d, Total quotes synced: %d, Total contingencies synced: %d',
                    $totalResults['sales_synced'],
                    $totalResults['payments_synced'],
                    $totalResults['quotes_synced'],
                    $totalResults['contingencies_synced']
                ));
            } else {
                $io->warning(sprintf(
                    'Synchronization completed with errors. Total sales synced: %d, Total payments synced: %d, Total quotes synced: %d, Total contingencies synced: %d',
                    $totalResults['sales_synced'],
                    $totalResults['payments_synced'],
                    $totalResults['quotes_synced'],
                    $totalResults['contingencies_synced']
                ));

                $io->error(sprintf('Encountered %d errors during synchronization:', count($totalResults['errors'])));
                foreach ($totalResults['errors'] as $error) {
                    $io->text('  - ' . $error);
                }

                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Synchronization failed: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    /**
     * Find contingencies to sync based on criteria
     *
     * @return Contingency[] Array of contingencies to sync
     */
    private function findContingenciesToSync(): array
    {
        $qb = $this->entityManager->getRepository(Contingency::class)->createQueryBuilder('c');

        $qb
            ->where('DATE(c.startedAt) = :today')
            ->setParameter('today', (new \DateTime())->format("Y-m-d"));

        $qb->orderBy('c.startedAt', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
