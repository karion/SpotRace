<?php

namespace App\Service;

use App\Entity\AppSetting;

class SettingValueNormalizer
{
    public function normalize(mixed $value, string $type): mixed
    {
        return match ($type) {
            AppSetting::TYPE_STRING => trim((string) $value),
            AppSetting::TYPE_INT => max(0, (int) $value),
            AppSetting::TYPE_BOOL => filter_var($value, FILTER_VALIDATE_BOOL),
            AppSetting::TYPE_STRING_LIST => $this->normalizeStringList($value),
            default => throw new \InvalidArgumentException(sprintf('Nieobsługiwany typ ustawienia: %s.', $type)),
        };
    }

    public function formatForForm(mixed $value, string $type): string
    {
        if (AppSetting::TYPE_BOOL === $type) {
            return true === $value ? '1' : '0';
        }

        if (AppSetting::TYPE_STRING_LIST === $type) {
            if (!is_array($value)) {
                return '';
            }

            return implode(', ', array_map('strval', $value));
        }

        return (string) $value;
    }

    /** @return array<int, string> */
    private function normalizeStringList(mixed $value): array
    {
        if (is_array($value)) {
            $items = array_map('strval', $value);
        } else {
            $items = explode(',', (string) $value);
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => mb_strtolower(trim($item)),
            $items,
        )));
    }
}
