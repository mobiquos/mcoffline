<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Contingency;
use App\Entity\Quote;
use App\Entity\SystemParameter;
use App\Form\SimulationForm;
use App\Repository\ClientRepository;
use App\Repository\SystemParameterRepository;
use App\Service\QuoteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class QuoteController extends AbstractController
{
    #[Route(name: 'app_quotes', path: '/secure/quotes')]
    public function index(Request $request, EntityManagerInterface $em, QuoteService $quoteService, TokenStorageInterface $tokenStorage): Response
    {
        $form = $this->createForm(SimulationForm::class, new Quote());
        $form->handleRequest($request);

        $templateParams = ['form' => $form->createView()];

        if ($form->isSubmitted() && !$form->isValid()) {
            $clientRepository = $em->getRepository(Client::class);
            $client = $clientRepository->findOneBy(['rut' => str_replace(['.', '-'], '', $form->get('rut')->getData())]);
            $templateParams['client'] = $client;
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Quote */
            $data = $form->getData();

            /** @var ClientRepository */
            $clientRepository = $em->getRepository(Client::class);
            $client = $clientRepository->findOneBy(['rut' => str_replace(['.', '-'], '', $data->getRut())]);

            /** @var SystemParameterRepository */
            $systemParameterRepository = $em->getRepository(SystemParameter::class);

            $calculation = $quoteService->calculateInstallment($data);
            $dueDate = $client->getNextBillingAt();

            if ($form->get('save')->getData() != "true") {
                $templateParams['installment_amount'] = $calculation['installment_amount'];
                $templateParams['total'] = $calculation['total'];
                $templateParams['due_date'] = $dueDate;
                $templateParams['interest'] = $calculation['interest'];
                $templateParams['saved'] = false;
                $templateParams['client'] = $client;

                return $this->render('quotes/index.html.twig', $templateParams);
            }

            $location = $systemParameterRepository->findOneBy(['code' => SystemParameter::PARAM_LOCATION_CODE]);
            $data->setLocationCode($location->getValue());
            $data->setCreatedBy($this->getUser());
            $data->setInterest($calculation['interest']);
            $data->setInstallmentAmount($calculation['installment_amount']);
            $data->setTotalAmount($calculation['total']);

            $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
            $data->setContingency($contingency);

            $em->persist($data);
            $em->flush();

            // Invalidate the session
            $tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            return $this->render('quotes/success.html.twig', ['entity' => $data]);
        }

        return $this->render('quotes/index.html.twig', $templateParams);
    }

    #[Route(name: 'app_quotes_rut', path: '/quotes/rut')]
    public function validateRut(Request $request, EntityManagerInterface $em): Response
    {
        $rut = $request->query->get('rut');
        $client = $em->getRepository(Client::class)->findOneBy(['rut' => str_replace(['.', '-'], '', $rut)]);
        $statusCode = Client::validateRut($rut) && $client !== null ? 200 : 406;

        return $this->render('quotes/alerts_client.html.twig', [
            'client' => $client,
            'rut' => $rut,
        ], new Response(null, $statusCode));
    }

    #[Route(name: 'app_quotes_client', path: 'quotes/client')]
    public function getInfoClient(Request $request, EntityManagerInterface $em): Response
    {
        $rut = $request->query->get('rut');
        $client = $em->getRepository(Client::class)->findOneBy(['rut' => str_replace(['.', '-'], '', $rut)]);

        if (!Client::validateRut($rut) || !$client) {
            return new Response('', 404);
        }

        return $this->render('quotes/info_client.html.twig', [
            'client' => $client,
        ]);
    }
}
