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
                'label' => 'Label',
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 3, max: 190),
                ],
            ])
            ->add('scopes', TextType::class, [
                'label' => 'Scopes',
                'required' => false,
                'help' => 'Comma or space separated capability keys (e.g. content.read content.write). Leave empty for read-only.',
            ])
            ->add('expires_at', TextType::class, [
                'label' => 'Expires at (optional)',
                'required' => false,
                'help' => 'Accepts YYYY-MM-DD or ISO 8601 date/time',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'admin_api_key_create',
        ]);
    }
}
