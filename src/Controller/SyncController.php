<?php

namespace App\Controller;

use App\Entity\SyncEvent;
use App\Repository\ClientRepository;
use App\Repository\LocationRepository;
use App\Repository\DeviceRepository;
use App\Repository\SyncEventRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SyncController extends AbstractController
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly LocationRepository $locationRepository,
        private readonly UserRepository $userRepository,
        private readonly SyncEventRepository $syncEventRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/sync/pull/start', name: 'app_sync_pull_start', methods: ['GET'])]
    public function pullStart(Request $request): JsonResponse
    {
        $locationCode = $request->query->get('locationCode');
        $location = $this->locationRepository->findByCode($locationCode);

        $syncEvent = (new SyncEvent())
            ->setLocation($location)
            ->setStatus(SyncEvent::STATUS_INPROGRESS);

        $this->entityManager->persist($syncEvent);
        $this->entityManager->flush();

        return $this->json([
            'syncEventId' => $syncEvent->getId(),
        ]);
    }

    #[Route('/sync/pull/clients', name: 'app_sync_pull_clients', methods: ['GET'])]
    public function pullClients(Request $request): JsonResponse
    {
        $clients = array_map(fn($client) => [
            'id' => $client->getId(),
            'rut' => $client->getRut(),
            'firstLastName' => $client->getFirstLastName(),
            'secondLastName' => $client->getSecondLastName(),
            'name' => $client->getName(),
            'creditLimit' => $client->getCreditLimit(),
            'creditAvailable' => $client->getCreditAvailable(),
            'blockComment' => $client->getBlockComment(),
            'overdue' => $client->getOverdue(),
            'nextBillingAt' => $client->getNextBillingAt()->format('Y-m-d H:i:s'),
            'lastUpdatedAt' => $client->getLastUpdatedAt()->format('Y-m-d H:i:s'),
        ], $this->clientRepository->findAll());

        return $this->json([
            'clients' => $clients,
        ]);
    }

    #[Route('/sync/pull/users', name: 'app_sync_pull_users', methods: ['GET'])]
    public function pullUsers(Request $request): JsonResponse
    {
        $users = array_map(fn($user) => [
            'id' => $user->getId(),
            'rut' => $user->getRut(),
            'fullName' => $user->getFullName(),
            'code' => $user->getCode(),
            'roles' => $user->getRoles(),
            'enabled' => $user->isEnabled(),
            'location' => $user->getLocation()?->getId(),
        ], $this->userRepository->findAll());

        return $this->json([
            'users' => $users,
        ]);
    }

    #[Route('/sync/pull/devices', name: 'app_sync_pull_devices', methods: ['GET'])]
    public function pullDevices(Request $request): JsonResponse
    {
        $devices = array_map(fn($device) => [
            'id' => $device->getId(),
            'location' => $device->getLocation()->getId(),
            'ipAddress' => $device->getIpAddress(),
            'name' => $device->getName(),
            'number' => $device->getNumber(),
            'enabled' => $device->isEnabled(),
        ], $this->deviceRepository->findAll());

        return $this->json([
            'devices' => $devices,
        ]);
    }

    #[Route('/sync/confirm/{id}', name: 'app_sync_pull_confirm', methods: ['POST'])]
    public function confirm(int $id): JsonResponse
    {
        $syncEvent = $this->syncEventRepository->find($id);

        if (!$syncEvent) {
            return $this->json(['message' => 'Sync event not found'], 404);
        }

        $syncEvent->setStatus(SyncEvent::STATUS_SUCCESS);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Data sync confirmed successfully',
        ]);
    }
}
