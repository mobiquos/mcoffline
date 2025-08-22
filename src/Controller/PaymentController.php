<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Contingency;
use App\Entity\Payment;
use App\Entity\User;
use App\Form\PaymentFormType;
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

            $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
            $payment->setContingency($contingency);

            $client = $em->getRepository(Client::class)->findOneBy(['rut' => $payment->getRut()]);
            $client->setCreditAvailable(min($client->getCreditAvailable() + $payment->getAmount(), $client->getCreditLimit()));

            $em->persist($payment);
            $em->persist($client);
            $em->flush();

            $this->generateVoucher($payment, $em);

            // Invalidate the session
            $tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            return $this->render('payment/success.html.twig', [
                'entity' => $payment,
                'contingency' => $contingency,
            ]);
        }

        return $this->render('payment/index.html.twig', [
            'form' => $form->createView(),
            'contingency' => $contingency,
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

    private function generateVoucher(Payment $payment, EntityManagerInterface $em): void
    {
        $voucherContent = $this->renderView('payment/voucher.txt.twig', ['payment' => $payment]);
        $payment->setVoucherContent($voucherContent);

        $projectDir = $this->getParameter('kernel.project_dir');
        $voucherDir = $projectDir . self::VOUCHER_PATH;

        if (!is_dir($voucherDir)) {
            mkdir($voucherDir, 0777, true);
        }

        $filename = sprintf('/payment_%s_%s.txt', $payment->getId(), time());
        file_put_contents($voucherDir . $filename, $voucherContent);

        $em->persist($payment);
        $em->flush();
    }
}
