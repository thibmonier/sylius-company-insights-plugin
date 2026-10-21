<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Integration;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\BuyerRow;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\CategoryRow;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\PageRequest;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\ProductRow;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\ProductSort;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\Ranking;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingScope;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingsProvider;
use Webmozart\Assert\Assert;

final class RankingsTest extends KernelTestCase
{
    use IntegrationTestTrait;

    public function testTheBuyersAreRankedBySpendWithTheirShareOfTheTotal(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $paul = $this->catalog()->customer('paul', 'Paul', 'Durand');
        $this->catalog()->member($company, $claire);
        $this->catalog()->member($company, $paul);
        $this->catalog()->order($claire, 100_000, '2026-10-02 10:00:00');
        $this->catalog()->order($claire, 160_000, '2026-10-03 10:00:00');
        $this->catalog()->order($paul, 160_000, '2026-10-04 10:00:00');

        $ranking = $this->buyers($company);

        self::assertSame(['Martin', 'Durand'], array_map(static fn (BuyerRow $row): ?string => $row->lastName, $ranking->rows));
        self::assertSame([260_000, 160_000], array_map(static fn (BuyerRow $row): int => $row->spend, $ranking->rows));
        self::assertSame([2, 1], array_map(static fn (BuyerRow $row): int => $row->ordersCount, $ranking->rows));
        self::assertSame([62, 38], $ranking->shares);
        self::assertSame(420_000, $ranking->totalSpend);
    }

    public function testAMemberDeactivatedAfterHisOrdersStaysInTheRankingAndIsFlagged(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $old = $this->catalog()->customer('old', 'Olivier', 'Ancien');
        $this->catalog()->member($company, $claire);
        $this->catalog()->member($company, $old, enabled: false);
        $this->catalog()->order($claire, 100_000, '2026-10-02 10:00:00');
        $this->catalog()->order($old, 300_000, '2026-10-03 10:00:00');

        $rows = $this->buyers($company)->rows;

        self::assertSame(['Ancien', 'Martin'], array_map(static fn (BuyerRow $row): ?string => $row->lastName, $rows));
        self::assertSame([false, true], array_map(static fn (BuyerRow $row): bool => $row->active, $rows));
    }

    public function testNeitherTheBuyersNorTheLinesOfAnotherCompanyAppear(): void
    {
        $acme = $this->catalog()->company('Acme');
        $techParts = $this->catalog()->company('TechParts');
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $marc = $this->catalog()->customer('marc', 'Marc', 'Autre');
        $this->catalog()->member($acme, $claire);
        $this->catalog()->member($techParts, $marc);
        $taxon = $this->catalog()->taxon('EPI', 'Protective equipment');
        $gloves = $this->catalog()->product('EPI-GANT-001', ['en_US' => 'Gloves'], $taxon);
        $this->catalog()->orderOfLines($claire, [[$gloves, 10, 10_000]], '2026-10-02 10:00:00');
        $this->catalog()->orderOfLines($marc, [[$gloves, 500, 900_000]], '2026-10-02 10:00:00');

        self::assertSame(['Martin'], array_map(static fn (BuyerRow $row): ?string => $row->lastName, $this->buyers($acme)->rows));
        self::assertSame(10_000, $this->buyers($acme)->totalSpend);
        $products = $this->products($acme, ProductSort::Quantity)->rows;
        self::assertCount(1, $products);
        self::assertSame(10, $products[0]->quantity);
        self::assertSame(10_000, $this->categories($acme)->totalSpend);
        self::assertSame([900_000], array_map(static fn (CategoryRow $row): int => $row->spend, $this->categories($techParts)->rows));
    }

    public function testTheSameOrdersAsTheIndicatorsAreCounted(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $variant = $this->catalog()->product('VIS-001', ['en_US' => 'Screw']);
        $this->catalog()->orderOfLines($claire, [[$variant, 1, 10_000]], '2026-10-02 10:00:00');
        $this->catalog()->orderOfLines($claire, [[$variant, 1, 40_000]], '2026-10-02 10:00:00', state: OrderInterface::STATE_CANCELLED);
        $this->catalog()->pendingApproval($this->catalog()->orderOfLines($claire, [[$variant, 1, 25_000]], '2026-10-02 10:00:00'));
        $this->catalog()->orderOfLines($claire, [[$variant, 1, 5_000]], '2026-08-31 23:59:59');
        $this->catalog()->orderOfLines($claire, [[$variant, 1, 7_000]], '2026-10-22 00:00:00');
        $this->catalog()->orderOfLines($claire, [[$variant, 1, 3_000]], '2026-10-02 10:00:00', 'CHF');

        self::assertSame(10_000, $this->buyers($company)->totalSpend);
        self::assertSame(10_000, $this->products($company)->totalSpend);
        self::assertSame(10_000, $this->categories($company)->totalSpend);
        self::assertSame([3_000], array_map(static fn (ProductRow $row): int => $row->spend, $this->products($company, currency: 'CHF')->rows));
    }

    public function testTheProductsAreRankedBySpendOrByQuantityWithTheirLastPurchaseDate(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $gloves = $this->catalog()->product('EPI-GANT-001', ['en_US' => 'Gloves'], variantName: 'Size M');
        $screws = $this->catalog()->product('VIS-INOX-004', ['en_US' => 'Screws']);
        $this->catalog()->orderOfLines($claire, [[$gloves, 60, 30_000], [$screws, 5, 80_000]], '2026-10-02 10:00:00');
        $this->catalog()->orderOfLines($claire, [[$gloves, 40, 20_000]], '2026-10-15 09:30:00');

        $bySpend = $this->products($company)->rows;
        $byQuantity = $this->products($company, ProductSort::Quantity)->rows;

        self::assertSame([80_000, 50_000], array_map(static fn (ProductRow $row): int => $row->spend, $bySpend));
        self::assertSame([100, 5], array_map(static fn (ProductRow $row): int => $row->quantity, $byQuantity));
        self::assertSame('EPI-GANT-001-' . $this->catalog()->suffix, $byQuantity[0]->code);
        self::assertSame(['Gloves', 'Size M'], [$byQuantity[0]->productName, $byQuantity[0]->variantName]);
        self::assertSame('2026-10-15', $byQuantity[0]->lastPurchase->format('Y-m-d'));
        self::assertSame('2026-10-02', $byQuantity[1]->lastPurchase->format('Y-m-d'));
    }

    public function testTheAmountOfALineIsTaxExclusiveAfterTheDiscountsOfTheLine(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $gloves = $this->catalog()->product('EPI-GANT-001', ['en_US' => 'Gloves']);
        $this->catalog()->orderOfLines($claire, [[$gloves, 2, 12_000, 2_000]], '2026-10-02 10:00:00');

        self::assertSame(10_000, $this->products($company)->rows[0]->spend);
        self::assertSame(10_000, $this->categories($company)->totalSpend);
    }

    public function testAProductWithoutTranslationInTheLocaleIsKnownByItsCode(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $gloves = $this->catalog()->product('EPI-GANT-001', ['fr_FR' => 'Gants']);
        $this->catalog()->orderOfLines($claire, [[$gloves, 1, 10_000]], '2026-10-02 10:00:00');

        $english = $this->products($company)->rows[0];
        $french = $this->products($company, locale: 'fr_FR')->rows[0];

        self::assertNull($english->productName);
        self::assertSame('EPI-GANT-001-' . $this->catalog()->suffix, $english->code);
        self::assertSame('Gants', $french->productName);
    }

    public function testTheCategoriesAddUpToOneHundredPercentWithAnUncategorizedBucket(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $epi = $this->catalog()->product('EPI-1', ['en_US' => 'Gloves'], $this->catalog()->taxon('EPI', 'Protective equipment'));
        $screws = $this->catalog()->product('VIS-1', ['en_US' => 'Screws'], $this->catalog()->taxon('VISSERIE', 'Fasteners'));
        $tool = $this->catalog()->product('OUT-1', ['en_US' => 'Drill'], $this->catalog()->taxon('OUTILLAGE', 'Tools'));
        $none = $this->catalog()->product('DIV-1', ['en_US' => 'Misc']);
        $this->catalog()->orderOfLines($claire, [[$epi, 1, 10_000], [$screws, 1, 10_000], [$tool, 1, 10_000], [$none, 1, 5_000]], '2026-10-02 10:00:00');

        $ranking = $this->categories($company);

        self::assertCount(4, $ranking->rows);
        self::assertSame(100, array_sum($ranking->shares));
        self::assertSame(35_000, $ranking->totalSpend);
        $last = $ranking->rows[3];
        self::assertNull($last->code);
        self::assertSame(5_000, $last->spend);
        self::assertContains('Protective equipment', array_map(static fn (CategoryRow $row): ?string => $row->name, $ranking->rows));
    }

    public function testTheRankingIsPaginatedAndAPageBeyondTheLastIsTheLastPage(): void
    {
        $company = $this->catalog()->company();
        for ($index = 1; $index <= 12; ++$index) {
            $customer = $this->catalog()->customer('buyer' . $index, 'Buyer', sprintf('N%02d', $index));
            $this->catalog()->member($company, $customer);
            $this->catalog()->order($customer, 1_000 * (13 - $index), '2026-10-02 10:00:00');
        }

        $first = $this->buyers($company, new PageRequest(1, 10));
        $second = $this->buyers($company, new PageRequest(2, 10));
        $beyond = $this->buyers($company, new PageRequest(99, 10));

        self::assertCount(10, $first->rows);
        self::assertSame(12, $first->totalRows);
        self::assertSame(2, $first->pages());
        self::assertSame(['N11', 'N12'], array_map(static fn (BuyerRow $row): ?string => $row->lastName, $second->rows));
        self::assertSame(2, $beyond->page->page);
        self::assertSame(['N11', 'N12'], array_map(static fn (BuyerRow $row): ?string => $row->lastName, $beyond->rows));
    }

    public function testAnEmptyCompanyHasEmptyRankings(): void
    {
        $company = $this->catalog()->company();

        self::assertSame([], $this->buyers($company)->rows);
        self::assertSame([], $this->products($company)->rows);
        self::assertSame([], $this->categories($company)->rows);
        self::assertSame(0, $this->buyers($company)->totalSpend);
        self::assertSame(1, $this->buyers($company)->pages());
    }

    private function buyers(CompanyInterface $company, PageRequest $page = new PageRequest(1, 10)): Ranking
    {
        return $this->provider()->buyers($this->scope($company), $page);
    }

    private function products(CompanyInterface $company, ProductSort $sort = ProductSort::Spend, string $currency = 'EUR', string $locale = 'en_US'): Ranking
    {
        return $this->provider()->products($this->scope($company, $currency, $locale), $sort, new PageRequest(1, 10));
    }

    private function categories(CompanyInterface $company): Ranking
    {
        return $this->provider()->categories($this->scope($company), new PageRequest(1, 10));
    }

    private function scope(CompanyInterface $company, string $currency = 'EUR', string $locale = 'en_US'): RankingScope
    {
        Assert::notNull($company->getId());

        return new RankingScope($company->getId(), new Period(new \DateTimeImmutable('2026-09-01 00:00:00'), new \DateTimeImmutable('2026-10-22 00:00:00')), $currency, $locale);
    }

    private function provider(): RankingsProvider
    {
        return $this->service('tmonier_company_insights.rankings_provider', RankingsProvider::class);
    }
}
