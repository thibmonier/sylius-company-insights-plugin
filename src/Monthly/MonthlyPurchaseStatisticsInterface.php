<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Monthly;

use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;

/** Monthly aggregates of the orders of ONE company (same rules as the period indicators). */
interface MonthlyPurchaseStatisticsInterface
{
    /**
     * Months without any order are absent from the result.
     *
     * @param list<Period> $months Contiguous months in chronological order, bounds in the timezone of the report
     *
     * @return list<MonthlyTotals> `monthIndex` is the position of the month in `$months`
     */
    public function perMonth(int $companyId, array $months): array;
}
