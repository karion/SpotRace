<?php

namespace App\Form;

use App\Entity\Vehicle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VehicleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('licensePlate', TextType::class, [
                'label' => 'Numer rejestracyjny',
                'attr' => ['maxlength' => 20, 'autocomplete' => 'off'],
            ])
            ->add('save', SubmitType::class, ['label' => $options['submit_label']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vehicle::class,
            'submit_label' => 'Zapisz pojazd',
        ]);
        $resolver->setAllowedTypes('submit_label', 'string');
    }
}
