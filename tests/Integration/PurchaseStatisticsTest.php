<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Integration;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CurrencyTotals;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\PeriodStatistics;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\PurchaseStatisticsInterface;

final class PurchaseStatisticsTest extends KernelTestCase
{
    use IntegrationTestTrait;

    public function testItAggregatesTheSpendTheOrdersAndTheBuyersOfTheCompany(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $bob = $this->catalog()->customer('bob');
        $this->catalog()->member($company, $alice, CompanyMemberInterface::ROLE_ADMIN);
        $this->catalog()->member($company, $bob);
        foreach ([60_000, 70_000, 80_000] as $total) {
            $this->catalog()->order($alice, $total, '-5 days');
        }
        foreach ([70_000, 70_000, 70_000] as $total) {
            $this->catalog()->order($bob, $total, '-9 days');
        }

        $statistics = $this->counted($company);

        self::assertEquals(new CurrencyTotals('EUR', 6, 420_000), $statistics->totals['EUR']);
        self::assertSame(70_000, $statistics->totals['EUR']->averageBasket());
        self::assertSame(6, $statistics->ordersCount());
        self::assertSame(2, $statistics->activeBuyers);
    }

    public function testActiveBuyersAreCountedOnceWhateverTheirNumberOfOrders(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        $this->catalog()->order($alice, 1_000, '-1 day');
        $this->catalog()->order($alice, 1_000, '-2 days');

        self::assertSame(1, $this->counted($company)->activeBuyers);
    }

    public function testAnEmptyCompanyHasNoOrderAndNoError(): void
    {
        $statistics = $this->counted($this->catalog()->company());

        self::assertSame([], $statistics->totals);
        self::assertSame(0, $statistics->ordersCount());
        self::assertSame(0, $statistics->activeBuyers);
        self::assertSame([], $this->pending($this->catalog()->company()));
    }

    public function testAnotherCompanyIsNeverCounted(): void
    {
        $acme = $this->catalog()->company('Acme');
        $buildCo = $this->catalog()->company('BuildCo');
        $alice = $this->catalog()->customer('alice');
        $bea = $this->catalog()->customer('bea');
        $this->catalog()->member($acme, $alice);
        $this->catalog()->member($buildCo, $bea);
        $this->catalog()->order($alice, 10_000, '-1 day');
        $this->catalog()->order($bea, 99_000, '-1 day');

        self::assertSame(10_000, $this->counted($acme)->totals['EUR']->spend);
        self::assertSame(99_000, $this->counted($buildCo)->totals['EUR']->spend);
    }

    public function testPersonalOrdersOfAMemberAreNotCompanyOrders(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $freelance = $this->catalog()->customer('freelance');
        $this->catalog()->member($company, $alice);
        $this->catalog()->order($alice, 10_000, '-1 day');
        // Placed before the customer joined the company: no company link.
        $this->catalog()->order($freelance, 50_000, '-1 day');

        self::assertSame(10_000, $this->counted($company)->totals['EUR']->spend);
    }

    public function testCancelledOrdersAreNotCountedAnywhere(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        $this->catalog()->order($alice, 10_000, '-1 day');
        $cancelled = $this->catalog()->order($alice, 40_000, '-1 day', state: OrderInterface::STATE_CANCELLED);
        $this->catalog()->pendingApproval($cancelled);

        self::assertSame(10_000, $this->counted($company)->totals['EUR']->spend);
        self::assertSame([], $this->pending($company));
    }

    public function testOrdersWhoseCheckoutIsNotCompletedAreNotCounted(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        $this->catalog()->order($alice, 10_000, '-1 day');
        $open = $this->catalog()->order($alice, 40_000, '-1 day');
        $this->connection()->executeStatement("UPDATE sylius_order SET checkout_state = 'payment_selected' WHERE id = ?", [$open->getId()]);

        self::assertSame(1, $this->counted($company)->ordersCount());
    }

    public function testOnlyTheOrdersOfThePeriodAreCounted(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        $period = new Period(new \DateTimeImmutable('2026-03-01'), new \DateTimeImmutable('2026-04-01'));
        $this->catalog()->order($alice, 1_000, '2026-02-28 23:59:59');
        $this->catalog()->order($alice, 2_000, '2026-03-01 00:00:00');
        $this->catalog()->order($alice, 4_000, '2026-03-31 23:59:59');
        $this->catalog()->order($alice, 8_000, '2026-04-01 00:00:00');

        $statistics = $this->statistics()->counted($this->idOf($company), $period);

        self::assertSame(6_000, $statistics->totals['EUR']->spend);
        self::assertSame(2, $statistics->totals['EUR']->ordersCount);
    }

    public function testSpendIsTaxExclusiveWhateverTheLevelOfTheTax(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        $this->catalog()->order($alice, 12_000, '-1 day', tax: 2_000);
        $this->catalog()->order($alice, 12_000, '-1 day', unitTax: 2_000);
        $this->catalog()->order($alice, 12_000, '-1 day', shipmentTax: 2_000);
        $this->catalog()->order($alice, 12_000, '-1 day', tax: 1_000, unitTax: 500, shipmentTax: 500);

        self::assertSame(40_000, $this->counted($company)->totals['EUR']->spend);
    }

    public function testTotalsAreSeparatedByCurrencyWithoutConversion(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        $this->catalog()->order($alice, 10_000, '-1 day', 'EUR');
        $this->catalog()->order($alice, 30_000, '-2 days', 'EUR');
        $this->catalog()->order($alice, 5_000, '-3 days', 'USD');

        $statistics = $this->counted($company);

        self::assertEquals(new CurrencyTotals('EUR', 2, 40_000), $statistics->totals['EUR']);
        self::assertEquals(new CurrencyTotals('USD', 1, 5_000), $statistics->totals['USD']);
        self::assertSame(3, $statistics->ordersCount());
        self::assertSame(1, $statistics->activeBuyers);
    }

    public function testOrdersWaitingForApprovalAreCountedApartAndNotInTheSpend(): void
    {
        $company = $this->catalog()->company();
        $alice = $this->catalog()->customer('alice');
        $this->catalog()->member($company, $alice);
        $this->catalog()->order($alice, 10_000, '-1 day');
        $this->catalog()->pendingApproval($this->catalog()->order($alice, 25_000, '-2 days', tax: 5_000));
        $this->catalog()->pendingApproval($this->catalog()->order($alice, 7_000, '-3 days', 'USD'));
        $this->catalog()->pendingApproval($this->catalog()->order($alice, 99_000, '-3 days'), 'approved');

        $counted = $this->counted($company);
        $pending = $this->pending($company);

        self::assertSame(109_000, $counted->totals['EUR']->spend, 'Approved orders are purchases, pending ones are not.');
        self::assertArrayNotHasKey('USD', $counted->totals);
        self::assertSame(2, $counted->ordersCount());
        self::assertEquals([new CurrencyTotals('EUR', 1, 20_000), new CurrencyTotals('USD', 1, 7_000)], $pending);
    }

    public function testThePendingOrdersOfAnotherCompanyAreNotListed(): void
    {
        $acme = $this->catalog()->company('Acme');
        $buildCo = $this->catalog()->company('BuildCo');
        $bea = $this->catalog()->customer('bea');
        $this->catalog()->member($buildCo, $bea);
        $this->catalog()->pendingApproval($this->catalog()->order($bea, 25_000, '-2 days'));

        self::assertSame([], $this->pending($acme));
        self::assertCount(1, $this->pending($buildCo));
    }

    private function counted(CompanyInterface $company): PeriodStatistics
    {
        return $this->statistics()->counted($this->idOf($company), $this->period());
    }

    /** @return list<CurrencyTotals> */
    private function pending(CompanyInterface $company): array
    {
        return $this->statistics()->pending($this->idOf($company), $this->period());
    }

    private function period(): Period
    {
        return new Period(new \DateTimeImmutable('-30 days midnight'), new \DateTimeImmutable('tomorrow midnight'));
    }

    private function statistics(): PurchaseStatisticsInterface
    {
        return $this->service('tmonier_company_insights.test.statistics', PurchaseStatisticsInterface::class);
    }

    private function idOf(CompanyInterface $company): int
    {
        $id = $company->getId();
        self::assertIsInt($id);

        return $id;
    }
}
