<?php

namespace App\Form;

use App\Entity\Company;
use App\Entity\CompanyParkingSpot;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CompanyParkingSpotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('company', EntityType::class, [
                'label' => 'Firma',
                'class' => Company::class,
                'choice_label' => 'name',
                'placeholder' => 'Wybierz firmę',
                'constraints' => [new NotBlank(message: 'Wybór firmy jest wymagany.')],
            ])
            ->add('startsAt', DateType::class, [
                'label' => 'Data od',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'Data początku jest wymagana.')],
            ])
            ->add('endsAt', DateType::class, [
                'label' => 'Data do (opcjonalnie)',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('save', SubmitType::class, ['label' => $options['submit_label']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CompanyParkingSpot::class,
            'submit_label' => 'Zapisz przypisanie firmy',
        ]);
        $resolver->setAllowedTypes('submit_label', 'string');
    }
}
