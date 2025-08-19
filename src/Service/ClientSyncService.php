<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\SystemParameter;
use App\Repository\ClientRepository;
use App\Repository\SystemParameterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ClientSyncService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly ClientRepository $clientRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SystemParameterRepository $systemParameterRepository
    ) {
    }

    public function sync(int $remoteSyncId): void
    {
        $param = $this->systemParameterRepository->findByCode(SystemParameter::PARAM_SERVER_ADDRESS);
        $response = $this->httpClient->request('GET', $param->getValue() . $this->urlGenerator->generate('app_sync_pull_clients', [], UrlGeneratorInterface::RELATIVE_PATH));

        $data = $response->toArray();

        $this->em->beginTransaction();
        try {
            $this->clientRepository->removeAll();

            foreach ($data as $clientData) {
                $client = new Client();
                $client->setRut(str_replace(["-", "."], "", trim($clientData['rut'], "0")));
                $client->setFirstLastName($clientData['first_last_name']);
                $client->setSecondLastName($clientData['second_last_name']);
                $client->setName($clientData['name']);
                $client->setCreditLimit($clientData['credit_limit']);
                $client->setCreditAvailable($clientData['credit_available']);
                $client->setBlockComment($clientData['block_comment']);
                $client->setOverdue($clientData['overdue']);
                $client->setNextBillingAt(new \DateTime($clientData['next_billing_at']));
                $client->setLastUpdatedAt(new \DateTime());
                $this->em->persist($client);
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }

        $response = $this->httpClient->request('GET', $param->getValue() + $this->urlGenerator->generate('app_sync_pull_confirm', ['id' => $remoteSyncId]));
    }
}
