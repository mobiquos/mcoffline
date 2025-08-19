<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    private UserPasswordHasherInterface $passwordHasher;
    private EntityManagerInterface $entityManager;

    public function __construct(UserPasswordHasherInterface $uphi, EntityManagerInterface $entityManager)
    {
        $this->passwordHasher = $uphi;
        $this->entityManager = $entityManager;
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural("Usuarios")
            ->setEntityLabelInSingular("Usuario")
            ->setHelp(Crud::PAGE_INDEX, "Listado de usuarios registrados en el sistema.");
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportAction = Action::new('export', 'Exportar a CSV')
            ->setIcon('fa fa-file-csv')
            ->linkToCrudAction('exportUsers')
            ->setCssClass('btn btn-success')
            ->createAsGlobalAction();

        return $actions->add(Crud::PAGE_INDEX, $exportAction);
    }

    public function exportUsers(Request $request): StreamedResponse
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();

        $response = new StreamedResponse(function () use ($users) {
            $handle = fopen('php://output', 'w+');
            fputcsv($handle, ['ID', 'RUT', 'Nombre Completo', 'Habilitado', 'Roles']);

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->getId(),
                    $user->getRut(),
                    $user->getFullName(),
                    $user->isEnabled() ? 'Sí' : 'No',
                    implode(', ', $user->getRoles()),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="users.csv"');

        return $response;
    }

    #TODO: Implementar validador de RUT
    public function configureFields(string $pageName): iterable
    {
        return [
            BooleanField::new('enabled', "Habilitado")->renderAsSwitch(),
            TextField::new('rut', "RUT"),
            TextField::new('code', "Código"),
            TextField::new('fullName', "Nombre completo del usuario"),
            AssociationField::new('location', 'Tienda'),
            FormField::addPanel("Credenciales")->onlyOnForms(),
            ChoiceField::new('roles', "Roles dentro del sistema")->onlyOnForms()->setRequired(true)->setChoices(User::ROLES)->allowMultipleChoices(true),
            TextField::new('plainPassword', "Nueva contraseña")->onlyOnForms()->setRequired(true),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->plainPassword != null) {
            $entityInstance->setPassword($this->passwordHasher->hashPassword($entityInstance, $entityInstance->plainPassword));
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->plainPassword != null && $entityInstance->plainPassword != '') {
            $entityInstance->setPassword($this->passwordHasher->hashPassword($entityInstance, $entityInstance->plainPassword));
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
