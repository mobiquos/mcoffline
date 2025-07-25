<?php

namespace App\Form;

use App\Entity\Quote;
use App\Entity\SystemParameter;
use App\Validator\SimulationValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SimulationForm extends AbstractType
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $params = $this->em->getRepository(SystemParameter::class)->findAll();
        $params = array_combine(array_map(fn($d) => $d->getCode(), $params), $params);

        $builder
            ->add('save', HiddenType::class, [
                'mapped' => false,
                'empty_data' => false,
            ])
            ->add('rut', TextType::class, [
                'label' => 'RUT',
                'attr' => [
                    'maxlength' => 10,
                ],
                'required' => true,
            ])
            ->add('amount', IntegerType::class, [
                'label' => 'MONTO A FINANCIAR',
                'attr' => [
                    'min' => 0,
                    // 'max' => $params[SystemParameter::PARAM_MAX_TOTAL]->getValue(),
                ],
                'required' => true,
            ])
            ->add('installments', IntegerType::class, [
                'label' => 'NÚMERO DE CUOTAS (PLAZO)',
                'attr' => [
                    'validationMessage' => sprintf("El rango permitido es entre 1 y %d cuotas.", $params[SystemParameter::PARAM_MAX_INSTALLMENTS]->getValue()),
                    // 'max' => $params[SystemParameter::PARAM_MAX_INSTALLMENTS]->getValue(),
                    'min' => 1,
                    'step' => 1
                ],
                'required' => true,
            ])
            // ->add('deferredPayment', IntegerType::class, [
            //     'label' => 'PAGO DIFERIDO',
            //     'help' => 'Indique la cantidad de días que se agregarán a la fecha de vencimiento del primer pago.',
            //     'attr' => [
            //         'min' => 0
            //     ]
            // ])
            // ->add('downPayment', IntegerType::class, [
            //     'label' => 'PIE',
            //     'help' => 'Indique el monto de pie a cancelar.'
            // ])
            // ->add('paymentMetod', ChoiceType::class, [
            //     'label' => 'FORMA DE PAGO',
            //     'choices' => Quote::PAYMENT_METHODS
            // ])
            // ->add('tbkNumber')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quote::class,
            'constraints' => [new \App\Validator\Simulation()]
        ]);
    }
}
