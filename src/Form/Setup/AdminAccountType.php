<?php

declare(strict_types=1);

namespace App\Form\Setup;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Administrator email',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                ],
            ])
            ->add('display_name', TextType::class, [
                'label' => 'Display name',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'required' => true,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirm password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 12]),
                ],
            ])
            ->add('locale', TextType::class, [
                'label' => 'Preferred locale',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('timezone', TextType::class, [
                'label' => 'Preferred timezone',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('require_mfa', CheckboxType::class, [
                'label' => 'Require multi-factor authentication on first login',
                'required' => false,
            ])
            ->add('recovery_email', EmailType::class, [
                'label' => 'Recovery email (optional)',
                'required' => false,
            ])
            ->add('recovery_phone', TextType::class, [
                'label' => 'Recovery phone (optional)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'setup_admin',
        ]);
    }
}
