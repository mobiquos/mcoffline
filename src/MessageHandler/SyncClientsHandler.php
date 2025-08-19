<?php

namespace App\MessageHandler;

use App\Entity\SyncEvent;
use App\Message\SyncClients;
use App\Repository\SyncEventRepository;
use App\Service\ClientSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use SyncEvent as SyncEventSyncEvent;

#[AsMessageHandler]
final class SyncClientsHandler
{
    public function __construct(
        private readonly ClientSyncService $clientSyncService,
        private readonly SyncEventRepository $syncEventRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncClients $message): void
    {
        $syncEvent = $this->syncEventRepository->findOneBy(['status' => SyncEvent::STATUS_INPROGRESS], ['id' => 'DESC'], 1);
        if (!$syncEvent) {
            $this->logger->error(sprintf("SyncEvent in progress not found"));
            return;
        }

        try {
            $this->clientSyncService->sync($message->getSyncEventId());
            $syncEvent->setStatus(SyncEvent::STATUS_SUCCESS);
        } catch (\Exception $e) {
            $this->logger->error(sprintf("Client sync failed: %s", $e->getMessage()));
            $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
        } finally {
            $this->em->flush();
        }
    }
}
