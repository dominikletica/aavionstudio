<?php

declare(strict_types=1);

namespace App\Form\Security;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('display_name', TextType::class, [
                'label' => 'Display name',
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 2, max: 190),
                ],
            ])
            ->add('locale', TextType::class, [
                'label' => 'Locale',
                'empty_data' => 'en',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 12),
                ],
            ])
            ->add('timezone', TextType::class, [
                'label' => 'Timezone',
                'empty_data' => 'UTC',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 64),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Account status',
                'choices' => [
                    'Active' => 'active',
                    'Pending' => 'pending',
                    'Disabled' => 'disabled',
                    'Archived' => 'archived',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Choice(choices: ['active', 'pending', 'disabled', 'archived']),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Roles',
                'choices' => $options['role_choices'],
                'multiple' => true,
                'expanded' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('role_choices');
        $resolver->setAllowedTypes('role_choices', ['array']);
        $resolver->setDefaults([
            'csrf_token_id' => 'admin_user_profile',
        ]);
    }
}
