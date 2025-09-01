<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Device;
use App\Entity\Location;
use App\Entity\SyncEvent;
use App\Entity\SystemParameter;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\DeviceRepository;
use App\Repository\SyncEventRepository;
use App\Repository\SystemParameterRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LocationSyncService
{
    // Batch size for processing large datasets
    private const BATCH_SIZE = 1000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly ClientRepository $clientRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly SystemParameterRepository $systemParameterRepository,
        private readonly UserRepository $userRepository,
        private readonly SyncEventRepository $syncEventRepository,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * Synchronize data from main system to a specific location
     *
     * @param Location $location The location to synchronize data to
     * @param int $remoteSyncId The sync event ID from the main system
     * @return SyncEvent The sync event created for this synchronization
     */
    public function syncToLocation(Location $location, int $remoteSyncId): SyncEvent
    {
        // Create a sync event
        $syncEvent = new SyncEvent();
        $syncEvent->setLocation($location);
        $syncEvent->setStatus(SyncEvent::STATUS_INPROGRESS);
        $syncEvent->setType(SyncEvent::TYPE_PULL);

        $this->em->persist($syncEvent);
        $this->em->flush();

        // Get the server address from system parameters
        $serverParam = $this->systemParameterRepository->findByCode(SystemParameter::PARAM_SERVER_ADDRESS);
        if (!$serverParam) {
            $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
            $this->em->flush();
            throw new \Exception('Server address not configured');
        }

        $serverAddress = $serverParam->getValue();

        // Start transaction
        $this->em->beginTransaction();
        try {
            // Sync clients
            $this->syncClients($serverAddress, $location);

            // Sync devices
            $this->syncDevices($serverAddress, $location);

            // Sync system parameters
            $this->syncSystemParameters($serverAddress);

            // Sync users (only sellers and cashiers linked to the specific location)
            $this->syncUsers($serverAddress, $location);

            // Commit transaction
            $this->em->commit();

            // Confirm sync with main system
            $this->confirmSync($serverAddress, $remoteSyncId);

            // Mark sync event as successful
            $syncEvent->setStatus(SyncEvent::STATUS_SUCCESS);
            $this->em->flush();

            return $syncEvent;
        } catch (\Exception $e) {
            $this->em->rollback();

            // Mark sync event as failed
            $syncEvent->setStatus(SyncEvent::STATUS_FAILED);
            $this->em->flush();

            throw $e;
        }
    }

    /**
     * Synchronize clients from main system to location
     *
     * @param string $serverAddress The main server address
     * @param Location $location The location to sync to
     * @return void
     */
    private function syncClients(string $serverAddress, Location $location): void
    {
        $url = $serverAddress . $this->urlGenerator->generate('app_sync_pull_clients', [], UrlGeneratorInterface::ABSOLUTE_PATH);
        $response = $this->httpClient->request('GET', $url);
        $data = $response->toArray();

        // Clear existing clients
        $this->clientRepository->removeAll();

        // Process clients in batches to avoid memory issues
        $clients = $data['clients'];
        $batchCount = ceil(count($clients) / self::BATCH_SIZE);

        for ($i = 0; $i < $batchCount; $i++) {
            $batch = array_slice($clients, $i * self::BATCH_SIZE, self::BATCH_SIZE);

            foreach ($batch as $clientData) {
                $client = new Client();
                $client->setRut($clientData['rut']);
                $client->setFirstLastName($clientData['firstLastName']);
                $client->setSecondLastName($clientData['secondLastName']);
                $client->setName($clientData['name']);
                $client->setCreditLimit($clientData['creditLimit']);
                $client->setCreditAvailable($clientData['creditAvailable']);
                $client->setBlockComment($clientData['blockComment']);
                $client->setOverdue($clientData['overdue']);
                $client->setNextBillingAt(new \DateTime($clientData['nextBillingAt']));
                $client->setLastUpdatedAt(new \DateTime($clientData['lastUpdatedAt']));

                $this->em->persist($client);
            }

            $this->em->flush();
            $this->em->clear(); // Clear the entity manager to free memory
        }
    }

    /**
     * Synchronize devices from main system to location
     *
     * @param string $serverAddress The main server address
     * @param Location $location The location to sync to
     * @return void
     */
    private function syncDevices(string $serverAddress, Location $location): void
    {
        $url = $serverAddress . $this->urlGenerator->generate('app_sync_pull_devices', [], UrlGeneratorInterface::ABSOLUTE_PATH);
        $response = $this->httpClient->request('GET', $url);
        $data = $response->toArray();

        // Clear existing devices for this location
        $this->deviceRepository->removeByLocation($location);

        // Process devices in batches
        $devices = $data['devices'];
        $batchCount = ceil(count($devices) / self::BATCH_SIZE);

        for ($i = 0; $i < $batchCount; $i++) {
            $batch = array_slice($devices, $i * self::BATCH_SIZE, self::BATCH_SIZE);

            foreach ($batch as $deviceData) {
                // Only sync devices for this location
                if ($deviceData['location'] !== $location->getId()) {
                    continue;
                }

                $device = new Device();
                $device->setLocation($location);
                $device->setIpAddress($deviceData['ipAddress']);
                $device->setName($deviceData['name']);
                $device->setNumber($deviceData['number']);
                $device->setEnabled($deviceData['enabled']);

                $this->em->persist($device);
            }

            $this->em->flush();
            $this->em->clear(); // Clear the entity manager to free memory
        }
    }

    /**
     * Synchronize system parameters from main system to location
     *
     * @param string $serverAddress The main server address
     * @return void
     */
    private function syncSystemParameters(string $serverAddress): void
    {
        $url = $serverAddress . $this->urlGenerator->generate('app_sync_pull_system_parameters', [], UrlGeneratorInterface::ABSOLUTE_PATH);
        $response = $this->httpClient->request('GET', $url);
        $data = $response->toArray();

        // Process parameters in batches
        $parameters = $data['parameters'];
        $batchCount = ceil(count($parameters) / self::BATCH_SIZE);

        for ($i = 0; $i < $batchCount; $i++) {
            $batch = array_slice($parameters, $i * self::BATCH_SIZE, self::BATCH_SIZE);

            foreach ($batch as $paramData) {
                $param = $this->systemParameterRepository->findByCode($paramData['code']);

                if (!$param) {
                    $param = new SystemParameter();
                    $param->setCode($paramData['code']);
                }

                $param->setValue($paramData['value']);
                $this->em->persist($param);
            }

            $this->em->flush();
            $this->em->clear(); // Clear the entity manager to free memory
        }
    }

    /**
     * Synchronize users from main system to location
     *
     * @param string $serverAddress The main server address
     * @param Location $location The location to sync to
     * @return void
     */
    private function syncUsers(string $serverAddress, Location $location): void
    {
        $url = $serverAddress . $this->urlGenerator->generate('app_sync_pull_users', [], UrlGeneratorInterface::ABSOLUTE_PATH);
        $response = $this->httpClient->request('GET', $url);
        $data = $response->toArray();

        // Remove existing sellers and cashiers for this location
        $this->userRepository->removeSellersAndCashiersByLocation($location);

        // Process users in batches to avoid memory issues
        $users = $data['users'];
        $batchCount = ceil(count($users) / self::BATCH_SIZE);

        for ($i = 0; $i < $batchCount; $i++) {
            $batch = array_slice($users, $i * self::BATCH_SIZE, self::BATCH_SIZE);

            foreach ($batch as $userData) {
                // Only sync sellers and cashiers linked to this location
                $userRoles = $userData['roles'];
                $isSellerOrCashier = in_array(User::ROLE_SELLER, $userRoles) || in_array(User::ROLE_CASHIER, $userRoles);

                if (!$isSellerOrCashier || $userData['location'] !== $location->getId()) {
                    continue;
                }

                $user = new User();
                $user->setRut($userData['rut']);
                $user->setFullName($userData['fullName']);
                $user->setCode($userData['code']);
                $user->setRoles($userData['roles']);
                $user->setEnabled($userData['enabled']);
                $user->setLocation($location);

                $this->em->persist($user);
            }

            $this->em->flush();
            $this->em->clear(); // Clear the entity manager to free memory
        }
    }

    /**
     * Confirm synchronization with main system
     *
     * @param string $serverAddress The main server address
     * @param int $remoteSyncId The sync event ID from the main system
     * @return void
     */
    private function confirmSync(string $serverAddress, int $remoteSyncId): void
    {
        $url = $serverAddress . $this->urlGenerator->generate('app_sync_pull_confirm', ['id' => $remoteSyncId], UrlGeneratorInterface::ABSOLUTE_PATH);
        $this->httpClient->request('POST', $url);
    }
}
