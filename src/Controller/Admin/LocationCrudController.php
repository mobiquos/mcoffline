<?php

namespace App\Controller\Admin;

use App\Entity\Location;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function configureActions(Actions $actions): Actions
    {
        return $actions
                ->update(
                    Crud::PAGE_INDEX,
                    Action::DELETE,
                    fn (Action $action) => $action->displayIf(function (Location $location) {
                    return $location->getContingencies()->isEmpty();
                })
                )
        ;
    }

    public function exportUsers(Request $request): StreamedResponse
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();

        $response = new StreamedResponse(function () use ($users) {
            $handle = fopen('php://output', 'w+');
            fputcsv($handle, ['Código', 'RUT', 'Nombre Completo', 'Tienda', 'Habilitado', 'Perfil']);

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->getCode(),
                    $user->getRut(),
                    $user->getFullName(),
                    $user->getLocation() ?? "",
                    $user->isEnabled() ? 'Si' : 'No',
                    $user->getRolPretty()
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="users.csv"');

        return $response;
    }
}
