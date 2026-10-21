<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Monthly;

use Psr\Clock\ClockInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;

/**
 * The purchases of the last 12 calendar months (the month in progress included) next to the same
 * months of the previous year, one series per currency. A month without order is a zero, never a gap.
 */
final readonly class MonthlyPurchasesProvider
{
    private const MONTHS = 12;

    public function __construct(
        private ClockInterface $clock,
        private MonthlyPurchaseStatisticsInterface $statistics,
        private ?string $timezone = null,
    ) {
    }

    /**
     * @param string $fallbackCurrency Currency of the zero series of a company without order
     *
     * @return non-empty-list<MonthlySeries>
     */
    public function provide(int $companyId, string $fallbackCurrency): array
    {
        $months = $this->months();
        $perCurrency = [];
        foreach ($this->statistics->perMonth($companyId, $months) as $totals) {
            $perCurrency[$totals->currencyCode][$totals->monthIndex] = $totals;
        }
        ksort($perCurrency);
        $perCurrency = [] === $perCurrency ? [$fallbackCurrency => []] : $perCurrency;

        $series = [];
        foreach ($perCurrency as $code => $totals) {
            $series[] = $this->series((string) $code, $months, $totals);
        }

        return $series;
    }

    /**
     * @param list<Period>                $months
     * @param array<int, MonthlyTotals>   $totals By index of month
     */
    private function series(string $code, array $months, array $totals): MonthlySeries
    {
        $points = [];
        for ($index = 0; $index < self::MONTHS; ++$index) {
            $current = $totals[self::MONTHS + $index] ?? null;
            $previous = $totals[$index] ?? null;
            $points[] = new MonthlyPoint(
                $months[self::MONTHS + $index]->from,
                self::MONTHS - 1 === $index,
                $current?->ordersCount ?? 0,
                $current?->spend ?? 0,
                $previous?->ordersCount ?? 0,
                $previous?->spend ?? 0,
            );
        }

        return new MonthlySeries($code, $points);
    }

    /**
     * The 12 months of the previous year, then the 12 months ending with the current one.
     *
     * @return list<Period>
     */
    private function months(): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $now = null === $this->timezone ? $now : $now->setTimezone(new \DateTimeZone($this->timezone));
        $first = $now->setTime(0, 0)->modify('first day of this month')->modify(sprintf('-%d months', 2 * self::MONTHS - 1));

        $months = [];
        for ($index = 0; $index < 2 * self::MONTHS; ++$index) {
            $from = $first->modify(sprintf('+%d months', $index));
            $months[] = new Period($from, $from->modify('+1 month'));
        }

        return $months;
    }
}
