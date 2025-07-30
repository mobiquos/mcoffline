<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Quote;
use App\Entity\Sale;
use App\Form\QuoteSearchFormType;
use App\Form\SaleFormType;
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
        $form = $this->createForm(QuoteSearchFormType::class);
        $form->handleRequest($request);
        $quote = null;
        $client = null;
        $sale = new Sale();

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $quoteId = $data['quote_id'];

            $quote = $em->getRepository(Quote::class)->find($quoteId);

            if (!$quote) {
                $this->addFlash('danger', 'La cotización no existe.');
            } else {
                if ($quote->getSale()) {
                    $this->addFlash('danger', 'La cotización ya fue aceptada.');
                    $quote = null;
                }
                $client = $em->getRepository(Client::class)->findOneBy(['rut' => $quote->getRut()]);
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
            'sale_form' => $saleForm->createView(),
        ]);
    }

    #[Route(name: 'app_sales_accept', path: '/sales/accept', methods: ['POST'])]
    public function accept(Request $request, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        $sale = new Sale();
        $form = $this->createForm(SaleFormType::class, $sale);
        $form->handleRequest($request);

        $quote = $sale->getQuote();
        $sale->setRut($quote->getRut());

        if ($form->isSubmitted() && $form->isValid()) {
            $sale->setContingency($quote->getContingency());

            $client = $em->getRepository(Client::class)->findOneBy(['rut' => $sale->getRut()]);
            $client->setCreditAvailable($client->getCreditAvailable() - $sale->getQuote()->getAmount());

            $em->persist($sale);
            $em->persist($client);
            $em->flush();

            // Invalidate the session
            $tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            return $this->render('sales/success.html.twig');
        }

        $searchForm = $this->createForm(QuoteSearchFormType::class, ['quote_id' => $quote->getId()]);

        return $this->render('sales/index.html.twig', [
            'form' => $searchForm->createView(),
            'quote' => $quote,
            'sale_form' => $form->createView(),
        ]);
    }
}
