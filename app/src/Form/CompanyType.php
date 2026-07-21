<?php

namespace App\Form;

use App\Entity\Company;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class CompanyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nazwa firmy',
                'constraints' => [
                    new NotBlank(message: 'Nazwa firmy jest wymagana.'),
                    new Length(max: 160, maxMessage: 'Nazwa może mieć maksymalnie {{ limit }} znaków.'),
                ],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'constraints' => [
                    new NotBlank(message: 'Slug firmy jest wymagany.'),
                    new Length(max: 160, maxMessage: 'Slug może mieć maksymalnie {{ limit }} znaków.'),
                    new Regex(pattern: '/^[a-z0-9-]+$/', message: 'Slug może zawierać małe litery, cyfry i myślniki.'),
                ],
            ])
            ->add('save', SubmitType::class, ['label' => $options['submit_label']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Company::class,
            'submit_label' => 'Zapisz firmę',
        ]);
        $resolver->setAllowedTypes('submit_label', 'string');
    }
}
