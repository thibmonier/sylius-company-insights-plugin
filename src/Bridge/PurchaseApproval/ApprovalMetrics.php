<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Bridge\PurchaseApproval;

final class ApprovalMetrics
{
    private function __construct()
    {
    }

    /** @param list<int> $values */
    public static function median(array $values): ?int
    {
        if ([] === $values) {
            return null;
        }
        sort($values);
        $middle = intdiv(count($values), 2);

        return 1 === count($values) % 2 ? $values[$middle] : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    /** Whole percent of `$part` in `$total`, null when there is nothing to measure. */
    public static function percent(int $part, int $total): ?int
    {
        return $total <= 0 ? null : min(100, (int) round($part * 100 / $total));
    }
}
