<?php

namespace App\Controller\Admin;

use App\Entity\Payment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PaymentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Pagos')
            ->setEntityLabelInSingular('Pago')
            ->setPageTitle(Crud::PAGE_INDEX, 'Pagos')
            ->setHelp(Crud::PAGE_INDEX, 'Listado de pagos registrados en el sistema.');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('contingency', 'Contingencia'),
            AssociationField::new('createdBy', 'Registrado por'),
            DateTimeField::new('createdAt', 'Fecha/Hora'),
            TextField::new('rut', 'RUT'),
            IntegerField::new('amount', 'Monto'),
            TextField::new('paymentMethod', 'Método de pago'),
            TextField::new('voucherId', 'ID de voucher'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::DELETE, Action::EDIT);
    }
}
