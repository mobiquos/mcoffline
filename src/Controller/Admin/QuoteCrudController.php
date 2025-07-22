<?php

namespace App\Controller\Admin;

use App\Entity\Contingency;
use App\Entity\Quote;
use App\Entity\Sale;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditor;
use Symfony\Component\HttpFoundation\StreamedResponse;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class QuoteCrudController extends AbstractCrudController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Quote::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
          ->setEntityLabelInPlural("Cotizaciones")
          ->setEntityLabelInSingular("Cotización")
          ->setPageTitle(Crud::PAGE_NEW, "Registrar cotización")
            ->setHelp(Crud::PAGE_INDEX, "Listado de cotizaciones realizadas durante la contingencia en curso.")
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID'),
            AssociationField::new('createdBy', 'Registrado por'),
            DateTimeField::new('quoteDate', 'Fecha/Hora'),
            TextField::new('rut', 'RUT'),
            IntegerField::new('amount', 'Total'),
            IntegerField::new('installments', 'Plazo'),
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
