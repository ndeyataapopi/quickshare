<?php

if (! function_exists('formatKpi')) {
    /**
     * Abbreviate large numbers for KPI cards.
     * 950 → 950 | 1500 → 1.5K | 150000 → 150K | 1500000 → 1.5M | etc.
     */
    function formatKpi(float|int|string|null $value, int $decimals = 1): string
    {
        $n = (float) ($value ?? 0);

        $abs = abs($n);
        $sign = $n < 0 ? '-' : '';

        if ($abs >= 1_000_000_000_000) {
            return $sign . rtrim(rtrim(number_format($abs / 1_000_000_000_000, $decimals), '0'), '.') . 'T';
        }
        if ($abs >= 1_000_000_000) {
            return $sign . rtrim(rtrim(number_format($abs / 1_000_000_000, $decimals), '0'), '.') . 'B';
        }
        if ($abs >= 1_000_000) {
            return $sign . rtrim(rtrim(number_format($abs / 1_000_000, $decimals), '0'), '.') . 'M';
        }
        if ($abs >= 10_000) {
            return $sign . rtrim(rtrim(number_format($abs / 1_000, $decimals), '0'), '.') . 'K';
        }

        return $sign . number_format($abs, $abs == floor($abs) ? 0 : 2);
    }
}

if (! function_exists('kpiMoney')) {
    /**
     * Format monetary KPI with currency symbol.
     */
    function kpiMoney(float|int|string|null $value, ?string $symbol = null): string
    {
        $sym = $symbol ?? config('loans.currency_symbol', 'N$');
        return $sym . ' ' . formatKpi($value);
    }
}

if (! function_exists('formatCurrencyShort')) {
    /**
     * Format a Namibian Dollar amount in a compact, abbreviated form.
     *
     * 500           -> N$500
     * 999           -> N$999
     * 1000          -> N$1K
     * 2500          -> N$2.5K
     * 12500         -> N$12.5K
     * 100000        -> N$100K
     * 1250000       -> N$1.3M
     * 15000000      -> N$15M
     * 1000000000    -> N$1B
     */
    function formatCurrencyShort(float|int|string|null $value): string
    {
        $n = (float) ($value ?? 0);
        $abs = abs($n);
        $sign = $n < 0 ? '-' : '';

        if ($abs >= 1_000_000_000_000) {
            $formatted = number_format($abs / 1_000_000_000_000, 1);
            $suffix = 'T';
        } elseif ($abs >= 1_000_000_000) {
            $formatted = number_format($abs / 1_000_000_000, 1);
            $suffix = 'B';
        } elseif ($abs >= 1_000_000) {
            $formatted = number_format($abs / 1_000_000, 1);
            $suffix = 'M';
        } elseif ($abs >= 1_000) {
            $formatted = number_format($abs / 1_000, 1);
            $suffix = 'K';
        } else {
            return $sign.'N$'.number_format($abs, $abs == floor($abs) ? 0 : 2);
        }

        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $sign.'N$'.$formatted.$suffix;
    }
}
