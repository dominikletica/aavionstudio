<?php

declare(strict_types=1);

namespace App\Form\Security;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProjectMembershipCollectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('assignments', CollectionType::class, [
            'entry_type' => ProjectMembershipEntryType::class,
            'entry_options' => [
                'role_choices' => $options['role_choices'],
            ],
            'allow_add' => false,
            'allow_delete' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('role_choices');
        $resolver->setAllowedTypes('role_choices', ['array']);
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
