<?php

namespace App\Support;

class DashboardViews
{
    public const PRODUCTION = 'production';

    public const SALES = 'sales';

    public const PACKING = 'packing';

    public const EXECUTIVE = 'executive';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::PRODUCTION,
            self::SALES,
            self::PACKING,
            self::EXECUTIVE,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::PRODUCTION, 'label' => 'Production'],
            ['value' => self::SALES, 'label' => 'Sales'],
            ['value' => self::PACKING, 'label' => 'Packing'],
            ['value' => self::EXECUTIVE, 'label' => 'Executive'],
        ];
    }

    public static function label(string $view): string
    {
        foreach (self::options() as $option) {
            if ($option['value'] === $view) {
                return $option['label'];
            }
        }

        return 'Executive';
    }

    public static function isValid(?string $view): bool
    {
        return in_array($view, self::keys(), true);
    }

    public static function defaultForRoleName(string $name): string
    {
        $normalized = strtolower(trim($name));

        if (in_array($normalized, ['admin', 'administrator'], true) || str_contains($normalized, 'admin')) {
            return self::EXECUTIVE;
        }

        if (str_contains($normalized, 'finance') || str_contains($normalized, 'sales') || str_contains($normalized, 'invoice')) {
            return self::SALES;
        }

        if (str_contains($normalized, 'pack')) {
            return self::PACKING;
        }

        if (
            str_contains($normalized, 'production')
            || str_contains($normalized, 'qc')
            || str_contains($normalized, 'quality')
        ) {
            return self::PRODUCTION;
        }

        return self::EXECUTIVE;
    }
}
