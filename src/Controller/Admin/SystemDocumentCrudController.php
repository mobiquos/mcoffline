<?php

namespace App\Controller\Admin;

use App\Entity\SystemDocument;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use Vich\UploaderBundle\Form\Type\VichFileType;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class SystemDocumentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SystemDocument::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Manual')
            ->setEntityLabelInPlural('Manuales')
            ->setDefaultSort(['id' => 'ASC'])
            ->setPaginatorPageSize(20)
            ->setSearchFields(['title', 'description', 'originalName', 'mimeType'])
            ->setHelp('index', 'Manage user manuals and documentation files (PDF and video formats)')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('title'))
            ->add(TextFilter::new('mimeType', 'File Type'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(DateTimeFilter::new('updatedAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new('id', 'ID');
        $title = TextField::new('title', 'Title')
            ->setRequired(true)
            ->setHelp('Enter a descriptive title for this manual');

        $description = TextareaField::new('description', 'Description')
            ->setRequired(false)
            ->setNumOfRows(4)
            ->setHelp('Optional description of the manual content');

        $fileField = TextField::new('file', 'File')
            ->setFormType(VichFileType::class)
            ->setFormTypeOptions([
                'allow_delete' => false,
                'download_uri' => false,
            ])
            ->setHelp('Upload PDF or video files (max 50MB)');

        $fileName = TextField::new('fileName', 'File Name')
            ->setRequired(false);

        $originalName = TextField::new('originalName', 'Original Name')
            ->setRequired(false);

        $mimeType = TextField::new('mimeType', 'File Type')
            ->setRequired(false);

        $fileSize = IntegerField::new('fileSize', 'File Size (bytes)')
            ->setRequired(false);

        $fileSizeFormatted = TextField::new('formattedFileSize', 'File Size')
            ->setRequired(false);

        $createdAt = DateTimeField::new('createdAt', 'Created At')
            ->setFormat('dd/MM/yyyy HH:mm');

        $updatedAt = DateTimeField::new('updatedAt', 'Updated At')
            ->setFormat('dd/MM/yyyy HH:mm');


        if (Crud::PAGE_INDEX === $pageName) {
            return [
                $id,
                $title,
                $originalName,
                $mimeType,
                $fileSizeFormatted,
                $updatedAt,
            ];
        }

        if (Crud::PAGE_DETAIL === $pageName) {
            return [
                $id,
                $title,
                $description,
                $fileName,
                $originalName,
                $mimeType,
                $fileSizeFormatted,
                $createdAt,
                $updatedAt,
            ];
        }

        if (Crud::PAGE_NEW === $pageName) {
            return [
                $title,
                $description,
        FormField::addPanel('Subir archivo')
            ->setIcon('fas fa-upload'),
                $fileField,
            ];
        }

        if (Crud::PAGE_EDIT === $pageName) {
            return [
                $title,
                $description,
        FormField::addTab('File Upload')
            ->setIcon('fas fa-upload'),
                $fileField,
                $fileName,
                $originalName,
                $mimeType,
                $fileSizeFormatted,
                $updatedAt,
            ];
        }

        return [];
    }
}
