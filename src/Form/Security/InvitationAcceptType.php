<?php

declare(strict_types=1);

namespace App\Form\Security;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class InvitationAcceptType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('display_name', TextType::class, [
                'label' => 'security.invitation.form.display_name',
                'constraints' => [
                    new NotBlank(message: 'security.invitation.form.display_name.not_blank'),
                    new Length(
                        min: 2,
                        max: 190,
                        minMessage: 'security.invitation.form.display_name.min_length',
                        maxMessage: 'security.invitation.form.display_name.max_length'
                    ),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'security.invitation.form.password'],
                'second_options' => ['label' => 'security.invitation.form.password_confirmation'],
                'invalid_message' => 'security.invitation.form.password_mismatch',
                'constraints' => [
                    new NotBlank(message: 'security.invitation.form.password.not_blank'),
                    new Length(
                        min: 8,
                        minMessage: 'security.invitation.form.password.min_length'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'invitation_accept',
        ]);
    }
}
