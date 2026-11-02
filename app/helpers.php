<?php

use App\Models\SiteSetting;

if (! function_exists('settings')) {
    function settings(string $key, mixed $default = null): mixed
    {
        $cache = cache()->remember('app_settings', 3600, function () {
            return SiteSetting::query()->get()->keyBy('key');
        });
        $item = $cache[$key] ?? null;
        if (! $item) {
            return $default;
        }
        if ($item->type === 'boolean') {
            return $item->value === '1';
        }
        if ($item->type === 'json') {
            $decoded = json_decode($item->value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
        }

        return $item->value;
    }
}

if (! function_exists('json_setting_items_name_is_duplicate')) {
    /**
     * Case-insensitive duplicate check for JSON site_settings lists (id + name).
     *
     * @param  array<int, array{id?: int, name?: string}>  $items
     */
    function json_setting_items_name_is_duplicate(array $items, string $name, ?int $exceptId = null): bool
    {
        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return false;
        }
        foreach ($items as $item) {
            if (! isset($item['name'])) {
                continue;
            }
            if ($exceptId !== null && isset($item['id']) && (int) $item['id'] === (int) $exceptId) {
                continue;
            }
            if (mb_strtolower(trim((string) $item['name'])) === $normalized) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('currency_symbol')) {
    /**
     * Map a stored currency code or common alias to a display symbol (ISO 4217 where applicable).
     */
    function currency_symbol(?string $code): string
    {
        $raw = trim((string) ($code ?? ''));
        if ($raw === '') {
            return '€';
        }

        $upper = strtoupper($raw);
        $aliases = [
            'EURO' => 'EUR',
            'EUROS' => 'EUR',
            'US DOLLAR' => 'USD',
            'US DOLLARS' => 'USD',
            'POUND' => 'GBP',
            'POUNDS' => 'GBP',
            'RUPEE' => 'INR',
            'RUPEES' => 'INR',
        ];
        $c = $aliases[$upper] ?? $upper;

        return match ($c) {
            'EUR' => '€',
            'USD' => '$',
            'INR' => '₹',
            'GBP' => '£',
            'JPY' => '¥',
            'CNY' => '¥',
            'CHF' => 'CHF',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            'AED' => 'د.إ',
            'SAR' => '﷼',
            default => $c !== '' ? $c : '€',
        };
    }
}

if (! function_exists('number_to_words_english')) {
    /**
     * Convert a non-negative integer to English words (e.g. 600 → "Six Hundred").
     */
    function number_to_words_english(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $ones = [
            0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
            5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
            15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
        ];
        $tens = [
            2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
        ];

        $underThousand = static function (int $n) use ($ones, $tens, &$underThousand): string {
            if ($n === 0) {
                return '';
            }
            if ($n < 20) {
                return $ones[$n];
            }
            if ($n < 100) {
                $word = $tens[intdiv($n, 10)];
                $remainder = $n % 10;

                return $remainder === 0 ? $word : $word.' '.$ones[$remainder];
            }
            $word = $ones[intdiv($n, 100)].' Hundred';
            $remainder = $n % 100;
            if ($remainder === 0) {
                return $word;
            }

            return $word.' and '.$underThousand($remainder);
        };

        $parts = [];
        foreach ([1000000000 => 'Billion', 1000000 => 'Million', 1000 => 'Thousand'] as $value => $label) {
            if ($number >= $value) {
                $count = intdiv($number, $value);
                $number %= $value;
                $parts[] = $underThousand($count).' '.$label;
            }
        }

        if ($number > 0) {
            $parts[] = $underThousand($number);
        }

        return implode(' ', $parts);
    }
}

if (! function_exists('amount_in_words')) {
    /**
     * Invoice amount in words, e.g. "Euro € Six Hundred Only".
     */
    function amount_in_words(float $amount, ?string $currencyCode = 'EUR'): string
    {
        $amount = round(max(0, $amount), 2);
        $integer = (int) floor($amount);
        $cents = (int) round(($amount - $integer) * 100);

        if ($cents === 100) {
            $integer++;
            $cents = 0;
        }

        $raw = strtoupper(trim((string) ($currencyCode ?? 'EUR')));
        $aliases = [
            'EURO' => 'EUR',
            'EUROS' => 'EUR',
            'US DOLLAR' => 'USD',
            'US DOLLARS' => 'USD',
            'POUND' => 'GBP',
            'POUNDS' => 'GBP',
            'RUPEE' => 'INR',
            'RUPEES' => 'INR',
        ];
        $code = $aliases[$raw] ?? $raw;
        if ($code === '') {
            $code = 'EUR';
        }

        $currencyLabels = [
            'EUR' => ['Euro', 'Euros', 'Cent', 'Cents'],
            'USD' => ['US Dollar', 'US Dollars', 'Cent', 'Cents'],
            'INR' => ['Rupee', 'Rupees', 'Paise', 'Paise'],
            'GBP' => ['Pound', 'Pounds', 'Penny', 'Pence'],
            'HKD' => ['Hong Kong Dollar', 'Hong Kong Dollars', 'Cent', 'Cents'],
        ];
        [$majorSingular, $majorPlural, $minorSingular, $minorPlural] = $currencyLabels[$code]
            ?? [ucfirst(strtolower($code)), ucfirst(strtolower($code)).'s', 'Cent', 'Cents'];

        $symbol = currency_symbol($code);
        $words = number_to_words_english($integer);

        if ($cents > 0) {
            $minorLabel = $cents === 1 ? $minorSingular : $minorPlural;
            $words .= ' and '.number_to_words_english($cents).' '.$minorLabel;
        }

        return trim($majorSingular.' '.$symbol.' '.$words.' Only');
    }
}

if (! function_exists('invoice_default_agreement_code')) {
    function invoice_default_agreement_code(): string
    {
        return '"W" 9113';
    }
}

if (! function_exists('apply_invoice_agreement_code_to_statement')) {
    /**
     * Replace the origin criterion phrase in the export statement with the invoice agreement code.
     */
    function apply_invoice_agreement_code_to_statement(string $statement, string $agreementCode): string
    {
        $code = trim($agreementCode);
        if ($code === '') {
            $code = invoice_default_agreement_code();
        }

        $pattern = '/THE ORIGIN CRITERION MET IS\s+.+?\./s';
        $replacement = 'THE ORIGIN CRITERION MET IS '.$code.'.';

        if (preg_match($pattern, $statement)) {
            return preg_replace($pattern, $replacement, $statement, 1) ?? $statement;
        }

        return $statement;
    }
}

if (! function_exists('company_default_export_statement_plain')) {
    function company_default_export_statement_plain(): string
    {
        return trim(<<<'TXT'
STATEMENT : THE EXPORTER INREX4101000042EC028 OF THE PRODUCTS COVERED BY THIS DOCUMENT DECLARES THAT, EXCEPT WHERE OTHERWISE
CLEARLY INDICATED, THESE PRODUCTS ARE OF INDIAN PREFERENTIAL ORIGIN ACCORDING TO RULES OF ORIGIN OF THE GENERALIZED SYSTEM OF PREFERENCES
OF THE EUROPEAN UNION AND THAT THE ORIGIN CRITERION MET IS "W" 9113.
TXT);
    }
}

if (! function_exists('export_invoice_statement_html')) {
    /**
     * Plain text from settings: escape, line breaks, and bold key phrases (export invoice PDF).
     * If the stored value already contains HTML tags, output as-is (trusted admin content).
     */
    function export_invoice_statement_html(?string $stored, ?string $agreementCode = null): string
    {
        $code = trim((string) ($agreementCode ?? ''));
        if ($code === '') {
            $code = invoice_default_agreement_code();
        }

        $t = trim((string) $stored);
        if ($t === '') {
            $t = company_default_export_statement_plain();
        }
        $t = apply_invoice_agreement_code_to_statement($t, $code);

        if (preg_match('/<[a-z][\s\S]*>/i', $t)) {
            return $t;
        }
        $out = e($t);
        $out = str_replace('INREX4101000042EC028', '<b>INREX4101000042EC028</b>', $out);
        $out = preg_replace('/\bINDIAN\b/', '<b>INDIAN</b>', $out, 1);
        $escapedCode = e($code);
        $out = str_replace($escapedCode, '<b>'.$escapedCode.'</b>', $out);

        return nl2br($out);
    }
}
