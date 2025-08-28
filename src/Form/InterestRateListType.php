<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InterestRateListType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('first_rate', TextType::class, [
                'label' => 'Primera Tasa',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: 1.2'
                ],
                'required' => false
            ])
            ->add('second_rate', TextType::class, [
                'label' => 'Segunda Tasa',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: 1.3'
                ],
                'required' => false
            ])
            ->add('other_rates', TextType::class, [
                'label' => 'Otras Tasas (separadas por coma)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: 1.4'
                ],
                'required' => false
            ]);

        // Add event listener to transform data
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if (is_array($data)) {
                $rates = $data;
            } elseif (is_string($data)) {
                $rates = explode(',', $data);
            } else {
                $rates = [];
            }

            $formData = [
                'first_rate' => isset($rates[0]) ? $rates[0] : null,
                'second_rate' => isset($rates[1]) ? $rates[1] : null,
                'other_rates' =>  isset($rates[2]) ? $rates[2] : null,
            ];

            $event->setData($formData);
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            $rates = [];

            if (!empty($data['other_rates'])) {
                $rate = $data['other_rates'];
                $rates = array_fill(0, 12, $data['other_rates']);
            }

            if (!empty($data['first_rate'])) {
                $rates[0] = $data['first_rate'];
            }

            if (!empty($data['second_rate'])) {
                $rates[1] = $data['second_rate'];
            }

            // Limit to 12 rates maximum
            $rates = array_slice($rates, 0, 12);

            $event->setData(join(",", $rates));
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => null,
        ]);
    }
}
