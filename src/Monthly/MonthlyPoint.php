<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Monthly;

/** A month with the same month of the previous year. The month in progress is not a complete month. */
final readonly class MonthlyPoint
{
    public function __construct(
        public \DateTimeImmutable $month,
        public bool $inProgress,
        public int $ordersCount,
        public int $spend,
        public int $previousOrdersCount,
        public int $previousSpend,
    ) {
    }

    /** Name of the timezone the month is in, to format its name without moving it. */
    public function timezoneName(): string
    {
        return $this->month->getTimezone()->getName();
    }
}
