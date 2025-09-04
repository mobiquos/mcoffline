<?php

namespace App\Controller\Admin;

use App\Entity\Contingency;
use App\Entity\Sale;
use App\Entity\SystemParameter;
use App\Entity\User;
use App\Filter\DateFilter;
use App\Service\PrintVoucherService;
use App\Service\QuoteService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleMainCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly QuoteService $quoteService,
        private readonly PrintVoucherService $printVoucherService
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Sale::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(DateFilter::new('createdAt', 'Fecha de registro'))
            ->add(EntityFilter::new('contingency', 'Contingencia'))
            ->add(TextFilter::new('rut', 'RUT cliente'))
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
          ->setEntityLabelInPlural("Ventas")
          ->setEntityLabelInSingular("Venta")
            ->setDefaultSort(['id' => 'DESC'])
          ->setPageTitle(Crud::PAGE_INDEX, "Ventas")
          ->setHelp(Crud::PAGE_INDEX, "Listado de ventas realizadas durante la contigencia en curso.")
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            DateTimeField::new('createdAt', 'Fecha')->setFormat('dd-MM-YYYY')->onlyOnIndex(),
            TextField::new('contingency.location.code', 'Agencia')->onlyOnIndex(),
            TextField::new('rut', 'RUT')->setDisabled(),
            TextField::new('folio', 'Número de Boleta'),
            IntegerField::new('quote.amount', 'Monto Capital')->setDisabled(),
            IntegerField::new('quote.installments', 'Número Cuotas')->setDisabled(),
            DateField::new('quote.billingDate', 'Primer Vencimiento')->setFormat('dd-MM-YYYY'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportAction = Action::new('export', 'Exportar', 'fa fa-download')
            ->linkToCrudAction('exportCsv')
            ->setCssClass('btn btn-success')
            ->createAsGlobalAction();

        $showVoucherAction = Action::new('showVoucher', 'Ver Voucher', 'fa fa-file-text-o')
            ->linkToCrudAction('showVoucher');

        $reprintVoucherAction = Action::new('reprintVoucher', 'Reimprimir Voucher', 'fa fa-print')
            ->linkToCrudAction('reprintVoucher');

        return $actions
            ->add(Crud::PAGE_INDEX, $exportAction)
            ->add(Crud::PAGE_INDEX, $showVoucherAction)
            ->add(Crud::PAGE_INDEX, $reprintVoucherAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $action) => $action->displayIf(fn($entity) => $entity->getCreatedAt()->format("Ymd") == (new \DateTime)->format("Ymd")))
            ->setPermission('showVoucher', User::ROLE_SUPER_ADMIN)
            ->setPermission('reprintVoucher', User::ROLE_SUPER_ADMIN)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            // ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
        ;
    }

    #[AdminAction(routeName: 'export_csv', routePath: '/export/csv')]
    public function exportCsv(AdminContext $context): StreamedResponse
    {
        $fields = FieldCollection::new($this->configureFields(Crud::PAGE_INDEX));
        $filters = $this->container->get(FilterFactory::class)->create($context->getCrud()->getFiltersConfig(), $fields, $context->getEntity());
        $queryBuilder = $this->createIndexQueryBuilder($context->getSearch(), $context->getEntity(), $fields, $filters);
        $sales = $queryBuilder->getQuery()->getResult();

        $response = new StreamedResponse(function () use ($sales) {
            $handle = fopen('php://output', 'w+');
            fputcsv($handle, ['Fecha', 'Agencia', 'RUT Cliente', 'Número de Boleta', 'Monto', 'Cuotas', 'Primer Vencimiento'], ';');

            foreach ($sales as $sale) {
                fputcsv($handle, [
                    $sale->getContingency()->getStartedAt()->format('d-m-Y'),
                    $sale->getQuote()->getLocationCode(),
                    $sale->getRut(),
                    $sale->getFolio(),
                    $sale->getQuote()->getAmount(),
                    $sale->getQuote()->getInstallments(),
                    $sale->getQuote()->getBillingDate()->format('d-m-Y'),
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="Contingencia_Ventas_Compania.csv"'));

        return $response;
    }

    #[AdminAction(routeName: 'show_voucher', routePath: '/{entityId}/voucher')]
    public function showVoucher(AdminContext $context): Response
    {
        $sale = $context->getEntity()->getInstance();
        return $this->render('admin/voucher.html.twig', [
            'voucher_content' => $sale->getVoucherContent(),
        ]);
    }

    #[AdminAction(routeName: 'reprint_voucher', routePath: '/{entityId}/reprint-voucher')]
    public function reprintVoucher(AdminContext $context): Response
    {
        $sale = $context->getEntity()->getInstance();

        try {
            // Print the voucher using the service
            $success = $this->printVoucherService->printSaleVoucher($sale);

            if ($success) {
                $this->addFlash('success', 'Voucher reimprimido exitosamente.');
            } else {
                $this->addFlash('danger', 'Error al reimprimir el voucher.');
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Error al reimprimir el voucher: ' . $e->getMessage());
        }

        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        // Recalculate quote values when sale is updated
        if ($entityInstance instanceof Sale) {
            $quote = $entityInstance->getQuote();
            if ($quote) {
                $calculation = $this->quoteService->calculateInstallment($quote);

                $quote->setInterest($calculation['interest']);
                $quote->setInstallmentAmount($calculation['installment_amount']);
                $quote->setTotalAmount($calculation['total']);

                $entityManager->persist($quote);
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
