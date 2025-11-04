<?php

declare(strict_types=1);

namespace App\Form\Security;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ApiKeyCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'admin.users.edit.api.form.label',
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 3, max: 190),
                ],
            ])
            ->add('scopes', TextType::class, [
                'label' => 'admin.users.edit.api.form.scopes',
                'required' => false,
                'help' => 'admin.users.edit.api.form.scopes_help',
            ])
            ->add('expires_at', TextType::class, [
                'label' => 'admin.users.edit.api.form.expires_at',
                'required' => false,
                'help' => 'admin.users.edit.api.form.expires_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'admin_api_key_create',
        ]);
    }
}
