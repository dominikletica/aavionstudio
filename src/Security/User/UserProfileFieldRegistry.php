<?php

declare(strict_types=1);

namespace App\Security\User;

use App\Settings\SystemSettings;

final class UserProfileFieldRegistry
{
    public function __construct(
        private readonly SystemSettings $systemSettings,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFields(): array
    {
        $fields = $this->systemSettings->get('core.users.profile_fields', []);

        if (!\is_array($fields)) {
            return [];
        }

        $normalized = [];

        foreach ($fields as $name => $definition) {
            if (!\is_string($name) || $name === '' || !\is_array($definition)) {
                continue;
            }

            $type = \is_string($definition['type'] ?? null) ? $definition['type'] : 'string';

            $normalized[$name] = array_merge([
                'label' => $this->humanize($name),
                'type' => $type,
                'required' => false,
                'public' => true,
                'max_length' => 190,
            ], $definition);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        $fields = $this->getFields();
        $normalized = [];

        foreach ($fields as $name => $definition) {
            $value = $data[$name] ?? null;
            $type = $definition['type'] ?? 'string';

            switch ($type) {
                case 'boolean':
                    $normalized[$name] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;

                case 'integer':
                    $normalized[$name] = $value === null || $value === '' ? null : (int) $value;
                    break;

                default:
                    if ($value === null) {
                        $normalized[$name] = null;
                        break;
                    }

                    $stringValue = \is_string($value) ? trim($value) : trim((string) $value);
                    $normalized[$name] = $stringValue !== '' ? $stringValue : null;
                    break;
            }
        }

        return array_filter(
            $normalized,
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    private function humanize(string $name): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $name));
    }
}
