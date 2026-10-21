<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Indicators;

use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodComparison;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CurrencyTotals;

final readonly class Dashboard
{
    /**
     * @param list<CurrencyIndicators> $currencies One line per currency, never empty
     * @param list<CurrencyTotals>     $pending Orders waiting for approval, apart from the spend
     */
    public function __construct(
        public PeriodComparison $periods,
        public Indicator $ordersCount,
        public Indicator $activeBuyers,
        public array $currencies,
        public array $pending,
    ) {
    }

    public function isEmpty(): bool
    {
        return 0 === $this->ordersCount->value;
    }

    public function pendingCount(): int
    {
        return array_sum(array_map(static fn (CurrencyTotals $totals): int => $totals->ordersCount, $this->pending));
    }
}
