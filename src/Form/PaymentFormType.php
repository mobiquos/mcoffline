<?php

namespace App\Form;

use App\Entity\Payment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rut', TextType::class, [
                'label' => 'RUT del cliente',
                'required' => true,
            ])
            ->add('amount', IntegerType::class, [
                'label' => 'Monto',
                'attr' => [
                    'min' => 1,
                ],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Método de pago',
                'choices' => [
                    'Efectivo' => Payment::PAYMENT_METHOD_CASH,
                    'Débito' => Payment::PAYMENT_METHOD_DEBIT,
                    'Crédito' => Payment::PAYMENT_METHOD_CREDIT,
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('voucherId', TextType::class, [
                'label' => 'ID de voucher',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
            'constraints' => [new \App\Validator\Payment()]
        ]);
    }
}
