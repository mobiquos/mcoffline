<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BooleanStringType extends AbstractType implements DataTransformerInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer($this);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'false_values' => [null, 'false', ''],
            'required' => false,
        ]);
    }

    public function getParent(): string
    {
        return CheckboxType::class;
    }

    public function transform($value): mixed
    {
        return (bool) $value;
    }

    public function reverseTransform($value): mixed
    {
        return (string) $value;
    }
}
