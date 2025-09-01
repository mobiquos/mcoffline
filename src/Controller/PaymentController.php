<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Contingency;
use App\Entity\Device;
use App\Entity\Payment;
use App\Entity\User;
use App\Form\PaymentFormType;
use App\Service\PrintVoucherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PaymentController extends AbstractController
{
    private const VOUCHER_PATH = '/var/vouchers';

    #[Route("/secure/payment", name: "app_payment")]
    public function index(Request $request, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        $payment = new Payment();
        $form = $this->createForm(PaymentFormType::class, $payment);
        $form->handleRequest($request);

        $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($payment->getPaymentMethod() === Payment::PAYMENT_METHOD_CASH) {
                $payment->setVoucherId(null);
            }

            /** @var UserRepository */
            $userRepository = $em->getRepository(User::class);
            $user = $userRepository->find($this->getUser()->getOriginalUser()->getId());
            $payment->setCreatedBy($user);

            // Find device by IP address
            $ipAddress = $request->getClientIp();
            $device = $em->getRepository(Device::class)->findOneBy(['ipAddress' => $ipAddress]);
            $payment->setDevice($device);

            $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
            $payment->setContingency($contingency);

            // Generate the public ID as a correlative for the day
            $paymentRepository = $em->getRepository(Payment::class);
            $nextCorrelative = $paymentRepository->getNextCorrelativeId(new \DateTime());
            $payment->setPublicId(date('Ymd') . '-' . str_pad($nextCorrelative, 4, '0', STR_PAD_LEFT));

            $client = $em->getRepository(Client::class)->findOneBy(['rut' => $payment->getRut()]);
            // $client->setCreditAvailable(min($client->getCreditAvailable() + $payment->getAmount(), $client->getCreditLimit()));

            $em->persist($payment);
            $em->persist($client);
            $em->flush();

            $this->generateVoucher($payment, $em);

            // Invalidate the session
            $tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            return $this->render('payment/success.html.twig', [
                'entity' => $payment,
                'client' => $client,
                'contingency' => $contingency,
            ]);
        }

        $params = [
            'form' => $form->createView(),
            'contingency' => $contingency,
        ];

        if ($payment->getRut()) {
            $client = $em->getRepository(Client::class)->findOneBy(['rut' => str_replace(['.', '-'], '', $payment->getRut())]);
            $params['client'] = $client;
        }

        return $this->render('payment/index.html.twig', $params);
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

    #[Route(name: 'app_payment_print_voucher', path: '/payment/{id}/print-voucher', methods: ['POST'])]
    public function printVoucher(Payment $payment, PrintVoucherService $printVoucherService): Response
    {
        try {
            // Print the voucher using the service
            $success = $printVoucherService->printPaymentVoucher($payment);
            
            if ($success) {
                return new Response(json_encode([
                    'success' => true,
                    'message' => 'Voucher generado correctamente'
                ]), 200, ['Content-Type' => 'application/json']);
            } else {
                return new Response(json_encode([
                    'success' => false,
                    'message' => 'Error al generar el voucher'
                ]), 500, ['Content-Type' => 'application/json']);
            }
        } catch (\Exception $e) {
            return new Response(json_encode([
                'success' => false,
                'message' => 'Error al generar el voucher: ' . $e->getMessage()
            ]), 500, ['Content-Type' => 'application/json']);
        }
    }

    private function generateVoucher(Payment $payment, EntityManagerInterface $em): void
    {
        $client = $em->getRepository(Client::class)->findOneBy(['rut' => $payment->getRut()]);

        $voucherContent = $this->renderView('payment/voucher.txt.twig', [
            'payment' => $payment,
            'client' => $client,
        ]);

        $payment->setVoucherContent($voucherContent);

        $em->persist($payment);
        $em->flush();
    }
}
