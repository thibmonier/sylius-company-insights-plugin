<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Integration;

use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\Breakdown;
use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\OrderSpendInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\SiteBreakdownInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\PurchaseFilter;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingScope;
use Webmozart\Assert\Assert;

final class BreakdownTest extends KernelTestCase
{
    use IntegrationTestTrait;

    public function testTheSpendIsSplitPerDeliverySiteWithTheLabelOfTheAddressBook(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $this->catalog()->addressBookEntry($company, 'Lyon workshop', '12 rue des Ateliers', 'Lyon');
        $this->catalog()->addressBookEntry($company, 'Grenoble depot', '3 avenue du Depot', 'Grenoble', '38000');
        $this->deliver($this->catalog()->order($claire, 60_000, '2026-10-02 10:00:00'), '12 rue des Ateliers', 'Lyon');
        $this->deliver($this->catalog()->order($claire, 20_000, '2026-10-03 10:00:00'), '12 rue des Ateliers', 'Lyon');
        $this->deliver($this->catalog()->order($claire, 20_000, '2026-10-04 10:00:00'), '3 avenue du Depot', 'Grenoble', '38000');

        $sites = $this->sites($company);

        self::assertSame(['Lyon workshop', 'Grenoble depot'], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow $row): ?string => $row->label, $sites->rows));
        self::assertSame([80_000, 20_000], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow $row): int => $row->spend, $sites->rows));
        self::assertSame([2, 1], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow $row): int => $row->ordersCount, $sites->rows));
        self::assertSame([80, 20], $sites->shares());
        self::assertSame(100_000, $sites->totalSpend);
    }

    public function testAnAddressOutsideTheBookAndAnOrderWithoutAddressFallUnderOtherAddresses(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $this->catalog()->addressBookEntry($company, 'Lyon workshop', '12 rue des Ateliers', 'Lyon');
        $this->deliver($this->catalog()->order($claire, 50_000, '2026-10-02 10:00:00'), '12 rue des Ateliers', 'Lyon');
        $this->deliver($this->catalog()->order($claire, 30_000, '2026-10-03 10:00:00'), '99 rue Inconnue', 'Paris', '75001');
        $this->catalog()->order($claire, 20_000, '2026-10-04 10:00:00');

        $sites = $this->sites($company);

        self::assertSame(['Lyon workshop', null], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow $row): ?string => $row->label, $sites->rows));
        self::assertSame([50_000, 50_000], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow $row): int => $row->spend, $sites->rows));
        self::assertSame([1, 2], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow $row): int => $row->ordersCount, $sites->rows));
    }

    public function testTheAddressBookOfAnotherCompanyDoesNotLabelMyOrders(): void
    {
        $acme = $this->catalog()->company('Acme');
        $other = $this->catalog()->company('TechParts');
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($acme, $claire);
        $this->catalog()->addressBookEntry($other, 'Their secret site', '12 rue des Ateliers', 'Lyon');
        $this->deliver($this->catalog()->order($claire, 50_000, '2026-10-02 10:00:00'), '12 rue des Ateliers', 'Lyon');

        self::assertSame([null], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow $row): ?string => $row->label, $this->sites($acme)->rows));
    }

    public function testTheOrdersOfAnotherCompanyAreNotCounted(): void
    {
        $acme = $this->catalog()->company('Acme');
        $other = $this->catalog()->company('TechParts');
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $marc = $this->catalog()->customer('marc', 'Marc', 'Autre');
        $this->catalog()->member($acme, $claire);
        $this->catalog()->member($other, $marc);
        $this->catalog()->addressBookEntry($acme, 'Lyon workshop', '12 rue des Ateliers', 'Lyon');
        $this->deliver($this->catalog()->order($claire, 10_000, '2026-10-02 10:00:00'), '12 rue des Ateliers', 'Lyon');
        $this->deliver($this->catalog()->order($marc, 900_000, '2026-10-02 10:00:00'), '12 rue des Ateliers', 'Lyon');

        self::assertSame(10_000, $this->sites($acme)->totalSpend);
        self::assertSame(900_000, $this->sites($other)->totalSpend);
    }

    public function testTheFiltersNarrowTheBreakdownToOneMemberAndOneSite(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $paul = $this->catalog()->customer('paul', 'Paul', 'Durand');
        $this->catalog()->member($company, $claire);
        $this->catalog()->member($company, $paul);
        $lyon = $this->catalog()->addressBookEntry($company, 'Lyon workshop', '12 rue des Ateliers', 'Lyon');
        $this->catalog()->addressBookEntry($company, 'Grenoble depot', '3 avenue du Depot', 'Grenoble', '38000');
        $this->deliver($this->catalog()->order($claire, 10_000, '2026-10-02 10:00:00'), '12 rue des Ateliers', 'Lyon');
        $this->deliver($this->catalog()->order($paul, 20_000, '2026-10-02 10:00:00'), '12 rue des Ateliers', 'Lyon');
        $this->deliver($this->catalog()->order($paul, 40_000, '2026-10-03 10:00:00'), '3 avenue du Depot', 'Grenoble', '38000');

        $byPaul = $this->sites($company, new PurchaseFilter(customerId: $this->catalog()->id($paul)));
        $atLyon = $this->sites($company, new PurchaseFilter(addressId: $this->catalog()->id($lyon)));
        $paulAtLyon = $this->sites($company, new PurchaseFilter($this->catalog()->id($paul), $this->catalog()->id($lyon)));

        self::assertSame([40_000, 20_000], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow $row): int => $row->spend, $byPaul->rows));
        self::assertSame([30_000], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow $row): int => $row->spend, $atLyon->rows));
        self::assertSame([20_000], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow $row): int => $row->spend, $paulAtLyon->rows));
    }

    public function testASiteOfAnotherCompanyInTheFilterMatchesNothing(): void
    {
        $acme = $this->catalog()->company('Acme');
        $other = $this->catalog()->company('TechParts');
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($acme, $claire);
        $foreign = $this->catalog()->addressBookEntry($other, 'Their site', '12 rue des Ateliers', 'Lyon');
        $this->deliver($this->catalog()->order($claire, 10_000, '2026-10-02 10:00:00'), '12 rue des Ateliers', 'Lyon');

        self::assertSame([], $this->sites($acme, new PurchaseFilter(addressId: $this->catalog()->id($foreign)))->rows);
    }

    public function testAnAxisOfTheApplicationSplitsTheSpendAndAnOrderWithoutValueIsGatheredApart(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $one = $this->catalog()->order($claire, 50_000, '2026-10-02 10:00:00');
        $two = $this->catalog()->order($claire, 30_000, '2026-10-03 10:00:00');
        $three = $this->catalog()->order($claire, 10_000, '2026-10-04 10:00:00');
        $four = $this->catalog()->order($claire, 10_000, '2026-10-05 10:00:00');
        $provider = new FakeDimensionProvider([$this->catalog()->id($one) => 'ATELIER-01', $this->catalog()->id($two) => 'MAINT-02', $this->catalog()->id($three) => 'ATELIER-01', $this->catalog()->id($four) => '  ']);

        $dimensions = $this->provider($provider)->dimensions($this->scope($company));

        self::assertCount(1, $dimensions);
        self::assertSame('cost-center', $dimensions[0]->code);
        self::assertSame('Cost centre', $dimensions[0]->title);
        self::assertSame(['ATELIER-01', 'MAINT-02', null], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow $row): ?string => $row->label, $dimensions[0]->rows));
        self::assertSame([60_000, 30_000, 10_000], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow $row): int => $row->spend, $dimensions[0]->rows));
        self::assertSame([60, 30, 10], $dimensions[0]->shares());
    }

    public function testTheAxisOnlyKnowsTheOrdersOfTheCompany(): void
    {
        $acme = $this->catalog()->company('Acme');
        $other = $this->catalog()->company('TechParts');
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $marc = $this->catalog()->customer('marc', 'Marc', 'Autre');
        $this->catalog()->member($acme, $claire);
        $this->catalog()->member($other, $marc);
        $mine = $this->catalog()->order($claire, 10_000, '2026-10-02 10:00:00');
        $theirs = $this->catalog()->order($marc, 900_000, '2026-10-02 10:00:00');
        $provider = new FakeDimensionProvider([$this->catalog()->id($mine) => 'ATELIER-01', $this->catalog()->id($theirs) => 'SECRET-99']);

        $dimensions = $this->provider($provider)->dimensions($this->scope($acme));

        self::assertSame(['ATELIER-01'], array_map(static fn (\Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow $row): ?string => $row->label, $dimensions[0]->rows));
    }

    public function testWithoutProviderThereIsNoAxisBlock(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $this->catalog()->order($claire, 10_000, '2026-10-02 10:00:00');

        self::assertSame([], $this->provider()->dimensions($this->scope($company)));
    }

    public function testAFailingAxisIsLeftOutWithoutBreakingTheOthers(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $order = $this->catalog()->order($claire, 10_000, '2026-10-02 10:00:00');
        $good = new FakeDimensionProvider([$this->catalog()->id($order) => 'ATELIER-01']);

        $dimensions = $this->provider($good, new FakeDimensionProvider([], failing: true))->dimensions($this->scope($company));

        self::assertCount(1, $dimensions);
    }

    private function deliver(OrderInterface $order, string $street, string $city, string $postcode = '69007'): void
    {
        $this->catalog()->deliverTo($order, $street, $city, $postcode);
    }

    private function sites(CompanyInterface $company, PurchaseFilter $filter = new PurchaseFilter()): Breakdown
    {
        return $this->provider()->sites($this->scope($company, $filter));
    }

    private function provider(FakeDimensionProvider ...$dimensions): BreakdownProvider
    {
        $sites = $this->service('tmonier_company_insights.test.breakdown.sites', SiteBreakdownInterface::class);
        $spend = $this->service('tmonier_company_insights.test.breakdown.order_spend', OrderSpendInterface::class);

        return new BreakdownProvider($sites, $spend, $dimensions, new NullLogger());
    }

    private function scope(CompanyInterface $company, PurchaseFilter $filter = new PurchaseFilter()): RankingScope
    {
        Assert::notNull($company->getId());

        return new RankingScope($company->getId(), new Period(new \DateTimeImmutable('2026-09-01 00:00:00'), new \DateTimeImmutable('2026-10-22 00:00:00')), 'EUR', 'en_US', $filter);
    }
}
