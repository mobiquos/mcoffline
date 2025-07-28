<?php

namespace App\Controller\Admin;

use App\Entity\Location;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class LocationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Location::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addPanel(),
            TextField::new('code', 'Código de local')->setMaxLength(10),
            TextField::new('name', 'Nombre del local')->setMaxLength(80),
            BooleanField::new('enabled', 'Habilitado'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
          ->setEntityLabelInPlural("Locales")
          ->setEntityLabelInSingular("Local")
            ->setHelp(Crud::PAGE_INDEX, "Listado de locales registrados en el sistema.")
            ;
    }
}
