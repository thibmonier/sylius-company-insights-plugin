<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Monthly;

/** The 12 months of one currency, oldest first. Amounts of different currencies are never added. */
final readonly class MonthlySeries
{
    /** @param list<MonthlyPoint> $points */
    public function __construct(
        public string $currencyCode,
        public array $points,
    ) {
    }

    public function spendTotal(): int
    {
        return array_sum(array_map(static fn (MonthlyPoint $point): int => $point->spend, $this->points));
    }

    public function ordersTotal(): int
    {
        return array_sum(array_map(static fn (MonthlyPoint $point): int => $point->ordersCount, $this->points));
    }
}
