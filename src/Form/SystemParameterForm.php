<?php

namespace App\Form;

use App\Entity\SystemParameter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SystemParameterForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function ($event) {
            $data = $event->getData();
            $form = $event->getForm();
            $config = SystemParameter::PARAMS[$data->getCode()];
            
            // Prepare options for the form field
            $fieldOptions = [
                'label' => $config['name'],
                'help' => $config['description']
            ];
            
            // Add choices for ChoiceType
            if (isset($config['choices'])) {
                $fieldOptions['choices'] = $config['choices'];
            }
            
            $form->add('value', $config['formType'], $fieldOptions);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SystemParameter::class,
        ]);
    }
}

