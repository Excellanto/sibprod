<?php

namespace App\Support;

class AdminRoles
{
    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return ['Admin', 'admin', 'Administrator'];
    }

    public static function is(?string $name): bool
    {
        if ($name === null || $name === '') {
            return false;
        }

        return in_array(strtolower($name), ['admin', 'administrator'], true);
    }
}
