<?php

namespace App\Controller\Admin;

use App\Entity\Contingency;
use App\Entity\Sale;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleCrudController extends AbstractCrudController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Sale::class;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $contingency = $this->em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);

        if ($contingency) {
            $qb
                ->andWhere('entity.contingency = :contingency')
                ->setParameter('contingency', $contingency)
            ;
        }

        return $qb;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
          ->setEntityLabelInPlural("Ventas")
          ->setEntityLabelInSingular("Venta")
          ->setPageTitle(Crud::PAGE_INDEX, "Ventas")
          ->setHelp(Crud::PAGE_INDEX, "Listado de ventas realizadas durante la contigencia en curso.")
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('contingency', 'Contingencia')->setFieldFqcn(Contingency::class),
            AssociationField::new('createdBy', 'Registrado por')->setFieldFqcn(User::class),
            DateTimeField::new('createdAt', 'Fecha/Hora'),
            IdField::new('quote.id', 'Cotización'),
            TextField::new('rut', 'RUT'),
            TextField::new('folio', 'TBK'),
            IntegerField::new('quote.amount', 'Total'),
            IntegerField::new('quote.installments', 'Plazo'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportAction = Action::new('export', 'Exportar', 'fa fa-download')
            ->linkToCrudAction('exportCsv')
            ->setCssClass('btn btn-success')
            ->createAsGlobalAction();

        return $actions
            ->add(Crud::PAGE_INDEX, $exportAction)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
        ;
    }

    #[AdminAction(routeName: 'export_csv', routePath: '/export/csv')]
    public function exportCsv(AdminContext $context): StreamedResponse
    {
        $contingency = $this->em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
        $sales = $this->em->getRepository(Sale::class)->findBy(['contingency' => $contingency]);

        $response = new StreamedResponse(function () use ($sales) {
            $handle = fopen('php://output', 'w+');
            fputcsv($handle, ['Fecha y Hora', 'Código de Local', 'RUT Cliente', 'Monto', 'Cuotas'], ';');

            foreach ($sales as $sale) {
                fputcsv($handle, [
                    $sale->getCreatedAt()->format('d-m-Y H:i:s'),
                    $sale->getQuote()->getLocationCode(),
                    $sale->getRut(),
                    $sale->getQuote()->getAmount(),
                    $sale->getQuote()->getInstallments(),
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="contingency_sales.csv"');

        return $response;
    }
}
