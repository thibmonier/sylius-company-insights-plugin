<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Monthly;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tmonier\SyliusCompanyInsightsPlugin\Monthly\MonthlyPurchasesProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Monthly\MonthlySeries;
use Tmonier\SyliusCompanyInsightsPlugin\Monthly\MonthlyTotals;

final class MonthlyPurchasesProviderTest extends TestCase
{
    public function testItAsksForTheTwelveMonthsAndTheTwelveMonthsOfTheYearBefore(): void
    {
        $statistics = new FakeMonthlyStatistics([]);

        $this->provider($statistics)->provide(7, 'EUR');

        self::assertSame(7, $statistics->companyId);
        self::assertCount(24, $statistics->months);
        self::assertSame('2024-11-01', $statistics->months[0]->from->format('Y-m-d'));
        self::assertSame('2025-11-01', $statistics->months[12]->from->format('Y-m-d'));
        self::assertSame('2026-11-01', $statistics->months[23]->to->format('Y-m-d'));
        foreach ($statistics->months as $index => $month) {
            if ($index > 0) {
                self::assertEquals($statistics->months[$index - 1]->to, $month->from, 'The months are contiguous.');
            }
        }
    }

    public function testEveryMonthIsPresentEvenWithoutOrdersAndTheCurrentOneIsMarkedInProgress(): void
    {
        $series = $this->provider(new FakeMonthlyStatistics([
            new MonthlyTotals('EUR', 12, 2, 30_000),
            new MonthlyTotals('EUR', 23, 1, 5_000),
            new MonthlyTotals('EUR', 17, 4, 12_000),
            new MonthlyTotals('EUR', 0, 3, 9_000),
        ]))->provide(7, 'EUR');

        self::assertCount(1, $series);
        $points = $series[0]->points;
        self::assertCount(12, $points);
        self::assertSame('2025-11', $points[0]->month->format('Y-m'));
        self::assertSame('2026-10', $points[11]->month->format('Y-m'));
        self::assertSame([2, 30_000, 3, 9_000], [$points[0]->ordersCount, $points[0]->spend, $points[0]->previousOrdersCount, $points[0]->previousSpend]);
        // August 2026: nothing bought.
        self::assertSame('2026-08', $points[9]->month->format('Y-m'));
        self::assertSame([0, 0, 0, 0], [$points[9]->ordersCount, $points[9]->spend, $points[9]->previousOrdersCount, $points[9]->previousSpend]);
        self::assertSame([4, 12_000], [$points[5]->ordersCount, $points[5]->spend]);
        self::assertSame([1, 5_000], [$points[11]->ordersCount, $points[11]->spend]);
        self::assertSame([false, true], [$points[10]->inProgress, $points[11]->inProgress]);
        self::assertSame(array_fill(0, 11, false), array_map(static fn ($point): bool => $point->inProgress, \array_slice($points, 0, 11)));
    }

    public function testThereIsOneSeriesPerCurrencyAndNeverATotalMixingThem(): void
    {
        $series = $this->provider(new FakeMonthlyStatistics([
            new MonthlyTotals('EUR', 23, 1, 5_000),
            new MonthlyTotals('CHF', 23, 2, 7_000),
        ]))->provide(7, 'EUR');

        self::assertSame(['CHF', 'EUR'], array_map(static fn (MonthlySeries $one): string => $one->currencyCode, $series));
        self::assertSame(7_000, $series[0]->points[11]->spend);
        self::assertSame(5_000, $series[1]->points[11]->spend);
        self::assertSame(7_000, $series[0]->spendTotal());
        self::assertSame(2, $series[0]->ordersTotal());
    }

    public function testACompanyWithoutOrderGetsAZeroSeriesInTheFallbackCurrency(): void
    {
        $series = $this->provider(new FakeMonthlyStatistics([]))->provide(7, 'USD');

        self::assertCount(1, $series);
        self::assertSame('USD', $series[0]->currencyCode);
        self::assertCount(12, $series[0]->points);
        self::assertSame(0, $series[0]->spendTotal());
    }

    public function testTheMonthsFollowTheTimezone(): void
    {
        // 2026-10-31 23:30 UTC: already November in Paris (UTC+1 since the end of summer time is the 25th).
        $statistics = new FakeMonthlyStatistics([]);
        $provider = new MonthlyPurchasesProvider(new MockClock('2026-10-31 23:30:00', 'UTC'), $statistics, 'Europe/Paris');

        $series = $provider->provide(7, 'EUR');

        self::assertSame('2026-11', $series[0]->points[11]->month->format('Y-m'));
        self::assertTrue($series[0]->points[11]->inProgress);
        self::assertSame('Europe/Paris', $statistics->months[0]->from->getTimezone()->getName());
    }

    private function provider(FakeMonthlyStatistics $statistics): MonthlyPurchasesProvider
    {
        return new MonthlyPurchasesProvider(new MockClock('2026-10-21 12:00:00', 'UTC'), $statistics);
    }
}
