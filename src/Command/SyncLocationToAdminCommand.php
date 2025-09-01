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
    name: 'app:sync-location-to-admin',
    description: 'Synchronize sales and payments from location to admin system',
)]
class SyncLocationToAdminCommand extends Command
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
            ->addOption('contingency-id', null, InputOption::VALUE_REQUIRED, 'The contingency ID to sync (optional, if not provided will sync all since last successful sync)')
            ->addOption('all-contingencies', null, InputOption::VALUE_NONE, 'Sync all contingencies regardless of last sync')
            ->setHelp('This command synchronizes sales and payments from a location to the admin system.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $contingencyId = $input->getOption('contingency-id');
        $allContingencies = $input->getOption('all-contingencies');

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
            $io->error(sprintf(
                'Another synchronization is already in progress for this location (ID: %d, started at: %s). Please wait for it to complete.',
                $inProgressSync->getId(),
                $inProgressSync->getCreatedAt()->format('Y-m-d H:i:s')
            ));
            return Command::FAILURE;
        }

        try {
            // Determine which contingencies to sync
            $contingenciesToSync = [];
            
            if ($contingencyId) {
                // Sync specific contingency
                $contingency = $this->entityManager->getRepository(Contingency::class)->find($contingencyId);
                if (!$contingency) {
                    $io->error(sprintf('Contingency with ID "%s" not found.', $contingencyId));
                    return Command::FAILURE;
                }
                $contingenciesToSync[] = $contingency;
                $io->info(sprintf('Syncing specific contingency ID "%s"', $contingencyId));
            } else if ($allContingencies) {
                // Sync all contingencies
                $contingenciesToSync = $this->entityManager->getRepository(Contingency::class)->findAll();
                $io->info('Syncing all contingencies');
            } else {
                // Sync contingencies since last successful sync
                $lastSuccessfulSync = $this->entityManager->getRepository(SyncEvent::class)->findOneBy([
                    'status' => SyncEvent::STATUS_SUCCESS,
                    'type' => SyncEvent::TYPE_PUSH,
                    'location' => $location
                ], ['createdAt' => 'DESC']);
                
                $criteria = [];
                if ($lastSuccessfulSync) {
                    $criteria['createdAt'] = $lastSuccessfulSync->getCreatedAt();
                    $io->info(sprintf(
                        'Syncing contingencies created since last successful sync (%s)',
                        $lastSuccessfulSync->getCreatedAt()->format('Y-m-d H:i:s')
                    ));
                } else {
                    $io->info('Syncing all contingencies (no previous successful sync found)');
                }
                
                $contingenciesToSync = $this->findContingenciesToSync($criteria);
            }

            if (empty($contingenciesToSync)) {
                $io->info('No contingencies found to sync');
                return Command::SUCCESS;
            }

            $io->info(sprintf(
                'Starting synchronization for location "%s" with %d contingencies',
                $locationCode,
                count($contingenciesToSync)
            ));

            $totalResults = [
                'sales_synced' => 0,
                'payments_synced' => 0,
                'errors' => []
            ];

            // Sync each contingency or all together
            if (count($contingenciesToSync) == 1) {
                $contingency = $contingenciesToSync[0];
                $io->info(sprintf(
                    'Syncing contingency ID "%s" (created at: %s)',
                    $contingency->getId(),
                    $contingency->getCreatedAt()->format('Y-m-d H:i:s')
                ));

                // Initiate the synchronization process for this contingency
                $results = $this->locationToAdminSyncService->syncToAdmin($location, $contingency);
                
                $totalResults['sales_synced'] += $results['sales_synced'];
                $totalResults['payments_synced'] += $results['payments_synced'];
                if (!empty($results['errors'])) {
                    $totalResults['errors'] = array_merge($totalResults['errors'], $results['errors']);
                }

                if (!empty($results['errors'])) {
                    $io->warning(sprintf(
                        'Errors encountered while syncing contingency ID "%s":',
                        $contingency->getId()
                    ));
                    foreach ($results['errors'] as $error) {
                        $io->text('  - ' . $error);
                    }
                }
            } else {
                $io->info(sprintf('Syncing %d contingencies together', count($contingenciesToSync)));
                
                // Initiate the synchronization process for all contingencies
                $results = $this->locationToAdminSyncService->syncMultipleContingenciesToAdmin($location, $contingenciesToSync);
                
                $totalResults['sales_synced'] += $results['sales_synced'];
                $totalResults['payments_synced'] += $results['payments_synced'];
                if (!empty($results['errors'])) {
                    $totalResults['errors'] = array_merge($totalResults['errors'], $results['errors']);
                }

                if (!empty($results['errors'])) {
                    $io->warning('Errors encountered while syncing contingencies:');
                    foreach ($results['errors'] as $error) {
                        $io->text('  - ' . $error);
                    }
                }
            }

            if (empty($totalResults['errors'])) {
                $io->success(sprintf(
                    'Synchronization completed successfully. Total sales synced: %d, Total payments synced: %d',
                    $totalResults['sales_synced'],
                    $totalResults['payments_synced']
                ));
            } else {
                $io->warning(sprintf(
                    'Synchronization completed with errors. Total sales synced: %d, Total payments synced: %d',
                    $totalResults['sales_synced'],
                    $totalResults['payments_synced']
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
     * @param array $criteria Search criteria
     * @return Contingency[] Array of contingencies to sync
     */
    private function findContingenciesToSync(array $criteria = []): array
    {
        $qb = $this->entityManager->getRepository(Contingency::class)->createQueryBuilder('c');
        
        if (isset($criteria['createdAt'])) {
            $qb->andWhere('c.createdAt > :createdAt')
               ->setParameter('createdAt', $criteria['createdAt']);
        }
        
        $qb->orderBy('c.createdAt', 'ASC');
        
        return $qb->getQuery()->getResult();
    }
}
