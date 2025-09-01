<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Contingency;
use App\Entity\Device;
use App\Entity\Quote;
use App\Entity\Sale;
use App\Entity\User;
use App\Form\QuoteSearchFormType;
use App\Form\SaleFormType;
use App\Repository\ContingencyRepository;
use App\Repository\QuoteRepository;
use App\Service\PrintVoucherService;
use App\Service\QuoteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SalesController extends AbstractController
{
    #[Route(name: 'app_sales', path: '/secure/sales')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(QuoteSearchFormType::class, [], ['method' => 'GET']);
        $form->handleRequest($request);
        $quote = null;
        $client = null;
        $sale = new Sale();
        $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);

        if ($form->isSubmitted()) {
            $quoteId = $form->getData()['quote_id'];

        } else {
            // Check if quoteId was passed as a query parameter
            $quoteId = $request->query->get('quoteId');
        }


        if ($quoteId) {
            // Handle quote selection from the quotes list
            $quote = current($em->getRepository(Quote::class)->findByPublicId($quoteId));
            if ($quote === false) { $quote = null; }

            if (!$quote) {
                $this->addFlash('danger', 'La cotización no existe.');
            } else {
                $client = $em->getRepository(Client::class)->findOneBy(['rut' => $quote->getRut()]);
                if ($quote->getSale()) {
                    $this->addFlash('danger', 'Cotización no vigente.');
                    $quote = null;
                }
            }
        } elseif ($form->isSubmitted() && $form->isValid()) {
            // Handle manual quote search
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
    public function accept(Request $request, EntityManagerInterface $em, TokenStorageInterface $tokenStorage, PrintVoucherService $printVoucherService): Response
    {
        $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
        $sale = new Sale();
        $sale->setContingency($contingency);

        /** @var UserRepository */
        $userRepository = $em->getRepository(User::class);
        $user = $userRepository->find($this->getUser()->getOriginalUser()->getId());

        // Find device by IP address
        $ipAddress = $request->getClientIp();
        $device = $em->getRepository(Device::class)->findOneBy(['ipAddress' => $ipAddress]);

        $sale->setCreatedBy($user);
        $sale->setDevice($device);

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
    public function todayQuotes(ContingencyRepository $contingencyRepository, QuoteRepository $quoteRepository): Response
    {
        $contingency = $contingencyRepository->findOneBy(['endedAt' => null]);
        $quotes = $quoteRepository->createQueryBuilder('q')
            ->select("q.id as id, q.publicId as publicId, q.quoteDate as quoteDate, q.rut as rut, q.amount as amount, CONCAT(c.firstLastName, ' ', c.secondLastName, ' ', c.name) as clientName")
            ->leftJoin('App\Entity\Client', 'c', 'WITH', 'q.rut = c.rut')
            ->leftJoin('q.sale', 's')
            ->andWhere('q.contingency = :contingency')
            ->andWhere('DATE(q.quoteDate) = :today')
            ->andWhere('s.id IS NULL')
            ->setParameter('contingency', $contingency)
            ->setParameter('today', (new \DateTime())->format("Y-m-d"))
            ->orderBy('q.quoteDate', 'DESC')
            ->getQuery()
            ->getScalarResult();

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

    #[Route(name: 'app_sales_print_voucher', path: '/sales/{id}/print-voucher', methods: ['POST'])]
    public function printVoucher(Sale $sale, PrintVoucherService $printVoucherService): Response
    {
        try {
            // Print the voucher using the service
            $success = $printVoucherService->printSaleVoucher($sale);

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

    private function generateVoucher(Sale $sale, EntityManagerInterface $em): void
    {
        $voucherContent = $this->renderView('sales/voucher.txt.twig', ['sale' => $sale]);
        $sale->setVoucherContent($voucherContent);

        $em->persist($sale);
        $em->flush();
    }
}
