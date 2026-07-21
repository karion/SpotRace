<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CompanyUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $passwordConstraints = $options['password_required'] ? [new NotBlank(message: 'Hasło jest wymagane.')] : [];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Imię',
                'constraints' => [new NotBlank(message: 'Imię jest wymagane.')],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [new NotBlank(message: 'Email jest wymagany.'), new Email(message: 'Podaj poprawny email.')],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $options['password_required'] ? 'Hasło' : 'Nowe hasło (opcjonalnie)',
                'mapped' => false,
                'required' => $options['password_required'],
                'constraints' => [
                    ...$passwordConstraints,
                    new Length(max: 4096),
                ],
            ])
            ->add('save', SubmitType::class, ['label' => $options['submit_label']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'password_required' => true,
            'submit_label' => 'Zapisz użytkownika',
        ]);
        $resolver->setAllowedTypes('password_required', 'bool');
        $resolver->setAllowedTypes('submit_label', 'string');
    }
}
