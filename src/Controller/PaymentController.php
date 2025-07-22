<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Contingency;
use App\Entity\Payment;
use App\Form\PaymentFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PaymentController extends AbstractController
{
    #[Route("/secure/payment", name: "app_payment")]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $payment = new Payment();
        $form = $this->createForm(PaymentFormType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $payment->setCreatedBy($this->getUser());

            $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
            $payment->setContingency($contingency);

            $em->persist($payment);
            $em->flush();

            $this->addFlash('success', 'Pago registrado exitosamente.');

            return $this->redirectToRoute('app_payment');
        }

        return $this->render('payment/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(name: 'app_payment_rut', path: '/payment/rut')]
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

    #[Route(name: 'app_payment_client', path: 'payment/client')]
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
