<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Statistics;

use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;

/**
 * Aggregates of the orders of ONE company. The company is always an argument of the caller's
 * session (never of the request): there is no way to ask for several companies at once.
 */
interface PurchaseStatisticsInterface
{
    /** Orders whose checkout is completed and that are neither cancelled nor waiting for approval. */
    public function counted(int $companyId, Period $period): PeriodStatistics;

    /**
     * Orders waiting for approval (none without tmonier/sylius-purchase-approval-plugin).
     *
     * @return list<CurrencyTotals>
     */
    public function pending(int $companyId, Period $period): array;
}
