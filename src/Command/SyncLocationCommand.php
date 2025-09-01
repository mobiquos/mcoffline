<?php

namespace App\Command;

use App\Entity\Location;
use App\Entity\SyncEvent;
use App\Service\LocationSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-location',
    description: 'Initiate synchronization process between location and main instance',
)]
class SyncLocationCommand extends Command
{
    public function __construct(
        private readonly LocationSyncService $locationSyncService,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('location-code', null, InputOption::VALUE_REQUIRED, 'The location code to sync')
            ->addOption('remote-sync-id', null, InputOption::VALUE_REQUIRED, 'The remote sync ID from the main system')
            ->setHelp('This command initiates the synchronization process between a location and the main instance.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $locationCode = $input->getOption('location-code');
        $remoteSyncId = $input->getOption('remote-sync-id');

        if (!$locationCode) {
            $io->error('The location-code option is required.');
            return Command::FAILURE;
        }

        if (!$remoteSyncId) {
            $io->error('The remote-sync-id option is required.');
            return Command::FAILURE;
        }

        // Check if there's another synchronization in progress
        $inProgressSync = $this->entityManager->getRepository(SyncEvent::class)->findOneBy([
            'status' => SyncEvent::STATUS_INPROGRESS,
            'type' => SyncEvent::TYPE_PUSH,
        ]);

        if ($inProgressSync) {
            $io->error(sprintf(
                'Another synchronization is already in progress (ID: %d, started at: %s). Please wait for it to complete.',
                $inProgressSync->getId(),
                $inProgressSync->getCreatedAt()->format('Y-m-d H:i:s')
            ));
            return Command::FAILURE;
        }

        // Find the location by code
        $location = $this->entityManager->getRepository(Location::class)->findOneBy(['code' => $locationCode]);

        if (!$location) {
            $io->error(sprintf('Location with code "%s" not found.', $locationCode));
            return Command::FAILURE;
        }

        try {
            $io->info(sprintf('Starting synchronization for location "%s" with remote sync ID "%s"', $locationCode, $remoteSyncId));

            // Initiate the synchronization process
            $syncEvent = $this->locationSyncService->syncToLocation($location, (int)$remoteSyncId);

            $io->success(sprintf(
                'Synchronization completed successfully for location "%s". Sync event ID: %d',
                $locationCode,
                $syncEvent->getId()
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Synchronization failed: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
