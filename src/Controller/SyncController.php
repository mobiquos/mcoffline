<?php

namespace App\Controller;

use App\Entity\SyncEvent;
use App\Repository\ClientRepository;
use App\Repository\LocationRepository;
use App\Repository\DeviceRepository;
use App\Repository\SyncEventRepository;
use App\Repository\SystemParameterRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SyncController extends AbstractController
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly LocationRepository $locationRepository,
        private readonly SystemParameterRepository $systemParameterRepository,
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

    #[Route('/sync/pull/system-parameters', name: 'app_sync_pull_system_parameters', methods: ['GET'])]
    public function pullSystemParameters(Request $request): JsonResponse
    {
        $parameters = array_map(fn($parameter) => [
            'code' => $parameter->getCode(),
            'value' => $parameter->getValue(),
        ], $this->systemParameterRepository->findAll());

        return $this->json([
            'parameters' => $parameters,
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

    #[Route('/sync/push/sales-csv', name: 'app_sync_push_sales_csv', methods: ['POST'])]
    public function pushSalesCsv(Request $request): JsonResponse
    {
        try {
            // Handle CSV file upload for sales
            $uploadedFile = $request->files->get('file');

            if (!$uploadedFile) {
                return $this->json(['error' => 'No file uploaded'], 400);
            }

            // Validate file type
            if ($uploadedFile->getMimeType() !== 'text/csv' && $uploadedFile->getExtension() !== 'csv') {
                return $this->json(['error' => 'Invalid file type. Only CSV files are allowed.'], 400);
            }

            // Move file to a temporary location
            $tempDir = sys_get_temp_dir() . '/pending_sync';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $fileName = 'sales_' . time() . '_' . uniqid() . '.csv';
            $filePath = $tempDir . '/' . $fileName;
            $uploadedFile->move($tempDir, $fileName);

            // Create a pending sync event
            $syncEvent = new SyncEvent();
            $syncEvent->setStatus(SyncEvent::STATUS_PENDING);
            $syncEvent->setType(SyncEvent::TYPE_PUSH);
            // Location will be determined when processing the file

            $this->entityManager->persist($syncEvent);
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Sales CSV file uploaded successfully',
                'file_path' => $filePath,
                'sync_event_id' => $syncEvent->getId(),
                'count' => 0 // Will be updated when processing
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to upload sales CSV: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/sync/push/payments-csv', name: 'app_sync_push_payments_csv', methods: ['POST'])]
    public function pushPaymentsCsv(Request $request): JsonResponse
    {
        try {
            // Handle CSV file upload for payments
            $uploadedFile = $request->files->get('file');

            if (!$uploadedFile) {
                return $this->json(['error' => 'No file uploaded'], 400);
            }

            // Validate file type
            if ($uploadedFile->getMimeType() !== 'text/csv' && $uploadedFile->getExtension() !== 'csv') {
                return $this->json(['error' => 'Invalid file type. Only CSV files are allowed.'], 400);
            }

            // Move file to a temporary location
            $tempDir = sys_get_temp_dir() . '/pending_sync';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $fileName = 'payments_' . time() . '_' . uniqid() . '.csv';
            $filePath = $tempDir . '/' . $fileName;
            $uploadedFile->move($tempDir, $fileName);

            // Create a pending sync event
            $syncEvent = new SyncEvent();
            $syncEvent->setStatus(SyncEvent::STATUS_PENDING);
            $syncEvent->setType(SyncEvent::TYPE_PUSH);
            // Location will be determined when processing the file

            $this->entityManager->persist($syncEvent);
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Payments CSV file uploaded successfully',
                'file_path' => $filePath,
                'sync_event_id' => $syncEvent->getId(),
                'count' => 0 // Will be updated when processing
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to upload payments CSV: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/sync/push/quotes-csv', name: 'app_sync_push_quotes_csv', methods: ['POST'])]
    public function pushQuotesCsv(Request $request): JsonResponse
    {
        try {
            // Handle CSV file upload for quotes
            $uploadedFile = $request->files->get('file');

            if (!$uploadedFile) {
                return $this->json(['error' => 'No file uploaded'], 400);
            }

            // Validate file type
            if ($uploadedFile->getMimeType() !== 'text/csv' && $uploadedFile->getExtension() !== 'csv') {
                return $this->json(['error' => 'Invalid file type. Only CSV files are allowed.'], 400);
            }

            // Move file to a temporary location
            $tempDir = sys_get_temp_dir() . '/pending_sync';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $fileName = 'quotes_' . time() . '_' . uniqid() . '.csv';
            $filePath = $tempDir . '/' . $fileName;
            $uploadedFile->move($tempDir, $fileName);

            // Create a pending sync event
            $syncEvent = new SyncEvent();
            $syncEvent->setStatus(SyncEvent::STATUS_PENDING);
            $syncEvent->setType(SyncEvent::TYPE_PUSH);
            // Location will be determined when processing the file

            $this->entityManager->persist($syncEvent);
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Quotes CSV file uploaded successfully',
                'file_path' => $filePath,
                'sync_event_id' => $syncEvent->getId(),
                'count' => 0 // Will be updated when processing
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to upload quotes CSV: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/sync/push/contingency', name: 'app_sync_push_contingency', methods: ['POST'])]
    public function pushContingency(Request $request): JsonResponse
    {
        try {
            // Handle contingency data push
            $contingencyData = json_decode($request->getContent(), true);

            if (!$contingencyData) {
                return $this->json(['error' => 'No contingency data provided'], 400);
            }

            // Check if contingency already exists
            $existingContingency = $this->entityManager->getRepository('App\Entity\Contingency')->find($contingencyData['id']);

            if ($existingContingency) {
                // Update existing contingency
                $contingency = $existingContingency;
            } else {
                // Create new contingency
                $contingency = new \App\Entity\Contingency();
                $contingency->setId($contingencyData['id']);
            }

            // Set contingency properties
            $contingency->setLocationCode($contingencyData['locationCode']);
            $contingency->setStartedAt(new \DateTime($contingencyData['startedAt']));

            if (!empty($contingencyData['endedAt'])) {
                $contingency->setEndedAt(new \DateTime($contingencyData['endedAt']));
            }

            $contingency->setStartedByName($contingencyData['startedByName']);
            $contingency->setComment($contingencyData['comment'] ?? null);

            // Set location if provided
            if (!empty($contingencyData['locationId'])) {
                $location = $this->entityManager->getRepository('App\Entity\Location')->find($contingencyData['locationId']);
                if ($location) {
                    $contingency->setLocation($location);
                }
            }

            // Set started by user if provided
            if (!empty($contingencyData['startedById'])) {
                $user = $this->entityManager->getRepository('App\Entity\User')->find($contingencyData['startedById']);
                if ($user) {
                    $contingency->setStartedBy($user);
                }
            }

            // Persist contingency
            $this->entityManager->persist($contingency);

            // Create a pending sync event
            $syncEvent = new SyncEvent();
            $syncEvent->setStatus(SyncEvent::STATUS_PENDING);
            $syncEvent->setType(SyncEvent::TYPE_PUSH);
            // Location will be determined when processing the data

            $this->entityManager->persist($syncEvent);
            $syncEvent->setStatus(SyncEvent::STATUS_SUCCESS);
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Contingency data received and saved successfully',
                'contingency_id' => $contingency->getId(),
                'sync_event_id' => $syncEvent->getId()
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to process contingency data: ' . $e->getMessage()], 500);
        }
    }
}
