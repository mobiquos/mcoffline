<?php

namespace App\Controller\Admin;

use App\Entity\SystemParameter;
use App\Form\SystemParametersForm;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;

class SystemParameterCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SystemParameter::class;
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
    #[AdminAction(routePath: '/config', routeName: 'config', methods: ['POST', 'GET'])]
    public function config(AdminContext $context, EntityManagerInterface $em): Response
    {
        $data = $em->getRepository(SystemParameter::class)->findAll();
        $form = $this->createForm(SystemParametersForm::class, $data);
        $form->handleRequest($context->getRequest());
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // $em->getRepository(SystemParameter::class)->createQueryBuilder('e')->delete()->getQuery()->getSingleScalarResult();


            $em->flush();
        }

        return $this->render('system_parameter/form.html.twig', [
            'form' => $form
        ]);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
          ->setEntityLabelInPlural("Parámetros de sistema")
          ->setEntityLabelInSingular("Parámetro de sistema")
            ->setHelp(Crud::PAGE_INDEX, "Listado de parametros.")
            ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ;
    }
}
