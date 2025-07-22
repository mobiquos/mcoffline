<?php

namespace App\Form;

use App\Entity\Quote;
use App\Entity\Sale;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SaleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quote', EntityType::class, [
                'class' => Quote::class,
                'choice_label' => 'id',
                'attr' => ['class' => 'd-none']
            ])
            ->add('folio', TextType::class, [
                'label' => 'Folio de venta',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sale::class,
            'constraints' => [new \App\Validator\Sale()]
        ]);
    }
}
