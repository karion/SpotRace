<?php

namespace App\Form;

use App\Entity\ParkingLocation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParkingLocationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nazwa lokalizacji',
                'empty_data' => '',
                'attr' => ['maxlength' => 120],
            ])
            ->add('save', SubmitType::class, ['label' => $options['submit_label']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ParkingLocation::class,
            'submit_label' => 'Zapisz zmiany',
        ]);
        $resolver->setAllowedTypes('submit_label', 'string');
    }
}
