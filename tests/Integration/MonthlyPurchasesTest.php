<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Integration;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Monthly\MonthlyPurchasesProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Monthly\MonthlyPurchaseStatisticsInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Monthly\MonthlySeries;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodKey;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodResolver;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\PurchaseStatisticsInterface;

/** "Now" is 2026-10-21 12:00 UTC: the window is November 2025 to October 2026, the year before is November 2024 to October 2025. */
final class MonthlyPurchasesTest extends KernelTestCase
{
    use IntegrationTestTrait;

    public function testItGivesTwelveMonthsAndTheSameMonthsOfThePreviousYear(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        $this->catalog()->order($alice, 10_000, '2026-10-05 10:00:00');
        $this->catalog()->order($alice, 20_000, '2026-10-20 10:00:00');
        $this->catalog()->order($alice, 30_000, '2025-10-05 10:00:00');
        $this->catalog()->order($alice, 40_000, '2025-11-01 00:00:00');
        $this->catalog()->order($alice, 50_000, '2024-11-30 23:59:59');
        $this->catalog()->order($alice, 900_000, '2024-10-31 23:59:59');

        $points = $this->series($company)[0]->points;

        self::assertCount(12, $points);
        self::assertSame('2025-11', $points[0]->month->format('Y-m'));
        self::assertSame([1, 40_000, 1, 50_000], [$points[0]->ordersCount, $points[0]->spend, $points[0]->previousOrdersCount, $points[0]->previousSpend]);
        self::assertSame('2026-10', $points[11]->month->format('Y-m'));
        self::assertSame([2, 30_000, 1, 30_000], [$points[11]->ordersCount, $points[11]->spend, $points[11]->previousOrdersCount, $points[11]->previousSpend]);
        self::assertTrue($points[11]->inProgress);
        self::assertFalse($points[10]->inProgress);
    }

    public function testAMonthWithoutOrderIsAZeroAndNotAGap(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        $this->catalog()->order($alice, 10_000, '2026-07-10 10:00:00');
        $this->catalog()->order($alice, 20_000, '2026-09-10 10:00:00');

        $points = $this->series($company)[0]->points;

        self::assertSame('2026-08', $points[9]->month->format('Y-m'));
        self::assertSame([0, 0], [$points[9]->ordersCount, $points[9]->spend]);
        self::assertSame([10_000, 0, 20_000], [$points[8]->spend, $points[9]->spend, $points[10]->spend]);
    }

    public function testAnOrderPlacedOnTheLastNightOfAMonthCountsInTheMonthOfTheTimezone(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        // 2026-03-31 23:30 in Paris (summer time, UTC+2) is 21:30 in UTC: March in both.
        $this->catalog()->order($alice, 10_000, '2026-03-31 21:30:00');
        // 2026-03-31 22:30 in UTC is 00:30 on April 1st in Paris.
        $this->catalog()->order($alice, 20_000, '2026-03-31 22:30:00');

        $paris = $this->series($company, 'Europe/Paris')[0]->points;
        $utc = $this->series($company, 'UTC')[0]->points;

        self::assertSame('2026-03', $paris[4]->month->format('Y-m'));
        self::assertSame([10_000, 20_000], [$paris[4]->spend, $paris[5]->spend]);
        self::assertSame([30_000, 0], [$utc[4]->spend, $utc[5]->spend]);
    }

    public function testCurrenciesAreSeparateSeriesWithoutConversion(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        $this->catalog()->order($alice, 10_000, '2026-09-10 10:00:00', 'EUR');
        $this->catalog()->order($alice, 7_000, '2026-09-11 10:00:00', 'CHF');
        $this->catalog()->order($alice, 5_000, '2025-09-11 10:00:00', 'CHF');

        $series = $this->series($company);

        self::assertSame(['CHF', 'EUR'], array_map(static fn (MonthlySeries $one): string => $one->currencyCode, $series));
        self::assertSame([7_000, 5_000], [$series[0]->points[10]->spend, $series[0]->points[10]->previousSpend]);
        self::assertSame([10_000, 0], [$series[1]->points[10]->spend, $series[1]->points[10]->previousSpend]);
    }

    public function testOnlyTheCountedOrdersOfTheCompanyAreInTheBars(): void
    {
        $acme = $this->catalog()->company('Acme');
        $buildCo = $this->catalog()->company('BuildCo');
        $alice = $this->catalog()->customer('alice');
        $bea = $this->catalog()->customer('bea');
        $this->catalog()->member($acme, $alice);
        $this->catalog()->member($buildCo, $bea);
        $this->catalog()->order($alice, 10_000, '2026-09-10 10:00:00');
        $this->catalog()->order($alice, 40_000, '2026-09-10 10:00:00', state: OrderInterface::STATE_CANCELLED);
        $this->catalog()->pendingApproval($this->catalog()->order($alice, 25_000, '2026-09-10 10:00:00'));
        $this->catalog()->order($bea, 99_000, '2026-09-10 10:00:00');
        $this->catalog()->order($alice, 12_000, '2026-09-12 10:00:00', tax: 2_000);

        $acmePoint = $this->series($acme)[0]->points[10];

        self::assertSame([2, 20_000], [$acmePoint->ordersCount, $acmePoint->spend]);
        self::assertSame(99_000, $this->series($buildCo)[0]->points[10]->spend);
    }

    public function testTheTwelveBarsAddUpToTheSpendOfTheLastTwelveMonthsIndicator(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        foreach (['2025-10-31 23:59:59', '2025-11-01 00:00:00', '2026-01-15 10:00:00', '2026-03-31 21:30:00', '2026-04-01 00:00:00', '2026-10-01 00:00:00', '2026-10-21 11:59:00'] as $index => $date) {
            $this->catalog()->order($alice, 10_000 * ($index + 1), $date, 'EUR', tax: 100 * $index);
        }
        $this->catalog()->order($alice, 7_000, '2026-02-10 10:00:00', 'CHF');
        $clock = new MockClock('2026-10-21 12:00:00', 'UTC');
        $indicator = $this->service('tmonier_company_insights.statistics.purchase', PurchaseStatisticsInterface::class)
            ->counted((int) $company->getId(), (new PeriodResolver($clock))->resolve(PeriodKey::Last12Months)->current);

        $series = $this->series($company);

        foreach ($series as $one) {
            self::assertSame($indicator->totals[$one->currencyCode]->spend, $one->spendTotal(), $one->currencyCode);
            self::assertSame($indicator->totals[$one->currencyCode]->ordersCount, $one->ordersTotal(), $one->currencyCode);
        }
        self::assertSame(['CHF', 'EUR'], array_map(static fn (MonthlySeries $one): string => $one->currencyCode, $series));
    }

    /** @return list<MonthlySeries> */
    private function series(CompanyInterface $company, ?string $timezone = null): array
    {
        $statistics = $this->service('tmonier_company_insights.statistics.monthly', MonthlyPurchaseStatisticsInterface::class);
        $provider = new MonthlyPurchasesProvider(new MockClock('2026-10-21 12:00:00', 'UTC'), $statistics, $timezone);

        return $provider->provide((int) $company->getId(), 'EUR');
    }
}
