<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Contingency;
use App\Entity\Device;
use App\Entity\Quote;
use App\Entity\SystemParameter;
use App\Entity\User;
use App\Form\SimulationForm;
use App\Repository\ClientRepository;
use App\Repository\SystemParameterRepository;
use App\Repository\UserRepository;
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

        $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
        $templateParams = ['form' => $form->createView(), 'contingency' => $contingency];

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

            $minInstallmentAmount = $systemParameterRepository->findByCode(SystemParameter::PARAM_MIN_INSTALLMENT_AMOUNT);

            $dueDate = $client->getNextBillingAt();

            if ($form->get('save')->getData() != "true") {
                $templateParams['installment_amount'] = $calculation['installment_amount'];
                $templateParams['total'] = $calculation['total'];
                $templateParams['due_date'] = $dueDate;
                $templateParams['interest'] = $calculation['interest'];
                $templateParams['saved'] = false;
                $templateParams['client'] = $client;
                $templateParams['min_installment_amount'] = $minInstallmentAmount->getValue();

                return $this->render('quotes/index.html.twig', $templateParams);
            }

            /** @var UserRepository */
            $userRepository = $em->getRepository(User::class);
            $user = $userRepository->find($this->getUser()->getOriginalUser()->getId());

            $location = $systemParameterRepository->findOneBy(['code' => SystemParameter::PARAM_LOCATION_CODE]);
            $data->setLocationCode($location->getValue());
            $data->setCreatedBy($user);
            $data->setInterest($calculation['interest']);
            $data->setInstallmentAmount($calculation['installment_amount']);
            $data->setTotalAmount($calculation['total']);
            $data->setBillingDate($dueDate);

            $data->setContingency($contingency);

            // Set the publicId as a correlative number for the day
            $today = new \DateTime();
            $data->setQuoteDate($today);
            
            $maxPublicId = $em->getRepository(Quote::class)->findMaxPublicIdForDate($today, $contingency);
            $data->setPublicId($maxPublicId + 1);

            $em->persist($data);
            $em->flush();

            // Invalidate the session
            $tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            return $this->render('quotes/success.html.twig', [
                'entity' => $data,
                'client' => $client,
                'contingency' => $contingency,
            ]);
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
        if (!Client::validateRut($rut)) {
            return new Response('El RUT no es valido', 404);
        }

        $client = $em->getRepository(Client::class)->findOneBy(['rut' => str_replace(['.', '-'], '', $rut)]);
        if (!$client) {
            return new Response('Cliente no encontrado', 404);
        }

        if (strpos(strtoupper($client->getBlockComment()), "BLOQUEO") !== false) {
            return new Response('Bloqueo(s) impide simulaciones/compras.', 403);
        }

        return $this->render('quotes/info_client.html.twig', [
            'client' => $client,
        ]);
    }
}
