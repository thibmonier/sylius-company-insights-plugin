<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Tmonier\SyliusCompanyInsightsPlugin\Indicators\Dashboard;
use Tmonier\SyliusCompanyInsightsPlugin\Indicators\DashboardProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Indicators\Trend;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodKey;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodResolver;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CurrencyTotals;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\PurchaseStatisticsInterface;

final class DashboardProviderTest extends KernelTestCase
{
    use IntegrationTestTrait;

    public function testItComparesThePeriodWithThePreviousOneOfTheSameDuration(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $bob = $this->catalog()->customer('bob');
        $this->catalog()->member($company, $alice);
        $this->catalog()->member($company, $bob);
        // Last 30 days at 2026-10-21: 2026-09-22 to 2026-10-21. Before: 2026-08-23 to 2026-09-21.
        $this->catalog()->order($alice, 10_000, '2026-10-10 10:00:00');
        $this->catalog()->order($alice, 20_000, '2026-09-22 00:00:00');
        $this->catalog()->order($bob, 15_000, '2026-09-01 09:00:00');
        $this->catalog()->order($bob, 500_000, '2026-07-01 09:00:00');

        $dashboard = $this->dashboard($company->getId(), PeriodKey::Last30Days);

        self::assertSame(PeriodKey::Last30Days, $dashboard->periods->key);
        self::assertSame(2, $dashboard->ordersCount->value);
        self::assertSame(100.0, $dashboard->ordersCount->variation->percent);
        self::assertSame(1, $dashboard->activeBuyers->value);
        self::assertSame(Trend::Flat, $dashboard->activeBuyers->variation->trend);
        self::assertCount(1, $dashboard->currencies);
        self::assertSame('EUR', $dashboard->currencies[0]->currencyCode);
        self::assertSame(30_000, $dashboard->currencies[0]->spend->value);
        self::assertSame(100.0, $dashboard->currencies[0]->spend->variation->percent);
        self::assertSame(15_000, $dashboard->currencies[0]->averageBasket->value);
        self::assertSame(Trend::Flat, $dashboard->currencies[0]->averageBasket->variation->trend);
        self::assertFalse($dashboard->isEmpty());
    }

    public function testACurrencyOnlySpentInThePreviousPeriodShowsAZeroAndADrop(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        $this->catalog()->order($alice, 10_000, '2026-10-10 10:00:00', 'EUR');
        $this->catalog()->order($alice, 8_000, '2026-09-01 09:00:00', 'USD');

        $dashboard = $this->dashboard($company->getId(), PeriodKey::Last30Days);

        self::assertSame(['EUR', 'USD'], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Indicators\CurrencyIndicators $currency): string => $currency->currencyCode, $dashboard->currencies));
        self::assertSame(0, $dashboard->currencies[1]->spend->value);
        self::assertSame(-100.0, $dashboard->currencies[1]->spend->variation->percent);
    }

    public function testACompanyWithoutOrderGetsZeroIndicatorsInTheFallbackCurrency(): void
    {
        $dashboard = $this->dashboard($this->catalog()->company()->getId(), PeriodKey::default());

        self::assertTrue($dashboard->isEmpty());
        self::assertSame(0, $dashboard->ordersCount->value);
        self::assertSame(0, $dashboard->activeBuyers->value);
        self::assertNull($dashboard->ordersCount->variation->percent);
        self::assertCount(1, $dashboard->currencies);
        self::assertSame('USD', $dashboard->currencies[0]->currencyCode);
        self::assertSame(0, $dashboard->currencies[0]->spend->value);
        self::assertSame(0, $dashboard->currencies[0]->averageBasket->value);
        self::assertSame([], $dashboard->pending);
        self::assertSame(0, $dashboard->pendingCount());
    }

    public function testPendingApprovalsAreReportedApartWithTheirCount(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        $this->catalog()->order($alice, 10_000, '2026-10-10 10:00:00');
        $this->catalog()->pendingApproval($this->catalog()->order($alice, 25_000, '2026-10-11 10:00:00'));
        $this->catalog()->pendingApproval($this->catalog()->order($alice, 5_000, '2026-10-12 10:00:00'));

        $dashboard = $this->dashboard($company->getId(), PeriodKey::Last30Days);

        self::assertSame(10_000, $dashboard->currencies[0]->spend->value);
        self::assertSame(1, $dashboard->ordersCount->value);
        self::assertEquals([new CurrencyTotals('EUR', 2, 30_000)], $dashboard->pending);
        self::assertSame(2, $dashboard->pendingCount());
    }

    private function dashboard(?int $companyId, PeriodKey $key): Dashboard
    {
        self::assertNotNull($companyId);
        $provider = new DashboardProvider(
            new PeriodResolver(new MockClock('2026-10-21 15:30:00')),
            $this->service('tmonier_company_insights.test.statistics', PurchaseStatisticsInterface::class),
        );

        return $provider->provide($companyId, $key, 'USD');
    }
}
