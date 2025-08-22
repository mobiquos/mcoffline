<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Contingency;
use App\Entity\Quote;
use App\Entity\Sale;
use App\Entity\User;
use App\Form\QuoteSearchFormType;
use App\Form\SaleFormType;
use App\Service\QuoteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SalesController extends AbstractController
{
    private const VOUCHER_PATH = '/var/vouchers';

    #[Route(name: 'app_sales', path: '/secure/sales')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(QuoteSearchFormType::class);
        $form->handleRequest($request);
        $quote = null;
        $client = null;
        $sale = new Sale();
        $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $quoteId = $data['quote_id'];

            $quote = $em->getRepository(Quote::class)->find($quoteId);

            if (!$quote) {
                $this->addFlash('danger', 'La cotización no existe.');
            } else {
                $client = $em->getRepository(Client::class)->findOneBy(['rut' => $quote->getRut()]);
                if ($quote->getSale()) {
                    $this->addFlash('danger', 'Cotización no vigente.');
                    $quote = null;
                }
            }
        }

        if ($quote) {
            $sale->setQuote($quote);
        }

        $saleForm = $this->createForm(SaleFormType::class, $sale);

        return $this->render('sales/index.html.twig', [
            'form' => $form->createView(),
            'quote' => $quote,
            'client' => $client,
            'contingency' => $contingency,
            'sale_form' => $saleForm->createView(),
        ]);
    }

    #[Route(name: 'app_sales_accept', path: '/sales/accept', methods: ['POST'])]
    public function accept(Request $request, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
        $sale = new Sale();
        $sale->setContingency($contingency);

        /** @var UserRepository */
        $userRepository = $em->getRepository(User::class);
        $user = $userRepository->find($this->getUser()->getOriginalUser()->getId());

        $sale->setCreatedBy($user);

        $form = $this->createForm(SaleFormType::class, $sale);
        $form->handleRequest($request);

        $quote = $sale->getQuote();
        $sale->setRut($quote->getRut());

        if ($form->isSubmitted() && $form->isValid()) {

            $client = $em->getRepository(Client::class)->findOneBy(['rut' => $sale->getRut()]);
            $client->setCreditAvailable($client->getCreditAvailable() - $sale->getQuote()->getAmount());

            $em->persist($sale);
            $em->persist($client);
            $em->flush();

            $this->generateVoucher($sale, $em);

            // Invalidate the session
            $tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            return $this->render('sales/success.html.twig', [
                'contingency' => $contingency,
                'entity' => $sale
            ]);
        }

        $client = $em->getRepository(Client::class)->findOneBy(['rut' => $sale->getRut()]);
        $searchForm = $this->createForm(QuoteSearchFormType::class, ['quote_id' => $quote->getId()]);

        return $this->render('sales/index.html.twig', [
            'form' => $searchForm->createView(),
            'quote' => $quote,
            'client' => $client,
            'sale_form' => $form->createView(),
            'contingency' => $contingency
        ]);
    }

    #[Route(name: 'app_sales_quotes', path: '/secure/sales/quotes')]
    public function todayQuotes(EntityManagerInterface $em): Response
    {
        $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
        $quotes = $em->getRepository(Quote::class)->findWithClients($contingency);

        return $this->render('sales/today_quotes.html.twig', [
            'quotes' => $quotes,
            'contingency' => $contingency,
        ]);
    }

    #[Route('/secure/sales/quote/{id}', name: 'app_sales_quote_detail')]
    public function quoteDetail(Quote $quote, EntityManagerInterface $em): Response
    {
        $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
        $client = $em->getRepository(Client::class)->findOneBy(['rut' => $quote->getRut()]);

        return $this->render('sales/quote_detail.html.twig', [
            'quote' => $quote,
            'contingency' => $contingency,
            'client' => $client,
        ]);
    }

    #[Route('/secure/sales/{id}/edit', name: 'app_sales_edit', methods: ['GET', 'POST'])]
    public function edit(Sale $sale, Request $request, EntityManagerInterface $em, QuoteService $quoteService): Response
    {
        $form = $this->createForm(SaleFormType::class, $sale);
        $form->handleRequest($request);

        $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
        $client = $em->getRepository(Client::class)->findOneBy(['rut' => $sale->getRut()]);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Sale $sale */
            $sale = $form->getData();
            $quote = $sale->getQuote();

            $calculation = $quoteService->calculateInstallment($quote);

            $quote->setInterest($calculation['interest']);
            $quote->setInstallmentAmount($calculation['installment_amount']);
            $quote->setTotalAmount($calculation['total']);

            $em->persist($quote);
            $em->persist($sale);
            $em->flush();

            $this->addFlash('success', 'Venta actualizada exitosamente.');

            return $this->redirectToRoute('app_sales_edit', ['id' => $sale->getId()]);
        }

        return $this->render('sales/edit.html.twig', [
            'form' => $form->createView(),
            'sale' => $sale,
            'quote' => $sale->getQuote(),
            'client' => $client,
            'contingency' => $contingency,
        ]);
    }

    private function generateVoucher(Sale $sale, EntityManagerInterface $em): void
    {
        $voucherContent = $this->renderView('sales/voucher.txt.twig', ['sale' => $sale]);
        $sale->setVoucherContent($voucherContent);

        $projectDir = $this->getParameter('kernel.project_dir');
        $voucherDir = $projectDir . self::VOUCHER_PATH;

        if (!is_dir($voucherDir)) {
            mkdir($voucherDir, 0777, true);
        }

        $filename = sprintf('/sale_%s_%s.txt', $sale->getId(), time());
        file_put_contents($voucherDir . $filename, $voucherContent);

        $em->persist($sale);
        $em->flush();
    }
}
