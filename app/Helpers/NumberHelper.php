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
