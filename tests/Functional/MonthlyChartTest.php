<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Webmozart\Assert\Assert;

final class MonthlyChartTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const URL = '/account/company/insights';

    public function testTheTwelveBarsAddUpToTheSpendOfTheLastTwelveMonthsIndicator(): void
    {
        $admin = $this->administrator();
        foreach ([60_000, 70_000, 80_000] as $total) {
            $this->catalog()->order($admin, $total, '-30 days');
        }
        $this->catalog()->order($admin, 50_000, '-200 days');
        $this->catalog()->order($admin, 40_000, '-400 days');

        $crawler = $this->open($admin);

        $cells = $crawler->filter('[data-test-tmonier-insights-monthly-table="EUR"] tbody [data-test-tmonier-insights-monthly-spend]');
        self::assertCount(12, $cells);
        $sum = array_sum($cells->each(static fn (Crawler $cell): int => (int) $cell->attr('data-value')));
        self::assertSame(260_000, $sum);
        self::assertStringContainsString('2,600.00', $this->text($crawler, 'tmonier-insights-spend'));
        self::assertEqualsWithDelta(2_600.0, array_sum($this->chartData($crawler)[0]['data']), 0.001);
    }

    public function testTheEquivalentTableHasHeadersAndOneRowPerMonthWithTheYearBefore(): void
    {
        $admin = $this->administrator();
        $this->catalog()->order($admin, 10_000, '-30 days');

        $crawler = $this->open($admin);

        $table = $crawler->filter('[data-test-tmonier-insights-monthly-table="EUR"]');
        self::assertCount(1, $table);
        self::assertGreaterThan(0, $table->filter('caption')->count());
        self::assertCount(5, $table->filter('thead th[scope="col"]'));
        self::assertCount(12, $table->filter('tbody th[scope="row"]'));
        $months = $table->filter('tbody tr')->each(static fn (Crawler $row): string => (string) $row->attr('data-month'));
        self::assertCount(12, array_unique($months));
        $sorted = $months;
        sort($sorted);
        self::assertSame($sorted, $months, 'Oldest first.');
        self::assertCount(12, $table->filter('[data-test-tmonier-insights-monthly-previous-spend]'));
        self::assertCount(12, $table->filter('[data-test-tmonier-insights-monthly-orders]'));
    }

    public function testTheCurrentMonthIsMarkedInProgressAndOnlyIt(): void
    {
        $crawler = $this->open($this->administrator());

        $rows = $crawler->filter('[data-test-tmonier-insights-monthly-table] tbody tr');
        self::assertCount(1, $rows->filter('[data-test-tmonier-insights-month-in-progress]'));
        self::assertSame((new \DateTimeImmutable())->format('Y-m'), $rows->last()->attr('data-month'));
        self::assertStringContainsString('in progress', $rows->last()->text());
        self::assertStringNotContainsString('in progress', $rows->first()->text());
    }

    public function testEveryMonthIsInTheChartEvenWithoutOrders(): void
    {
        $crawler = $this->open($this->administrator());

        $chart = $crawler->filter('[data-test-tmonier-insights-chart]');
        self::assertCount(1, $chart);
        $categories = json_decode((string) $chart->attr('data-tmonier-insights-chart-categories-value'), true, flags: \JSON_THROW_ON_ERROR);
        Assert::isArray($categories);
        self::assertCount(12, $categories);
        foreach ($this->chartData($crawler) as $series) {
            self::assertEquals(array_fill(0, 12, 0), $series['data']);
        }
        self::assertCount(12, $crawler->filter('[data-test-tmonier-insights-monthly-table] tbody tr'));
    }

    public function testTheSwitchShowsTheNumberOfOrdersInsteadOfTheAmounts(): void
    {
        $admin = $this->administrator();
        $this->catalog()->order($admin, 10_000, 'now');
        $this->catalog()->order($admin, 30_000, 'now');

        $amounts = $this->open($admin);
        $orders = $this->open($admin, '?metric=orders');

        self::assertSame('amount', $amounts->filter('[data-test-tmonier-insights-chart]')->attr('data-tmonier-insights-chart-metric-value'));
        self::assertSame('orders', $orders->filter('[data-test-tmonier-insights-chart]')->attr('data-tmonier-insights-chart-metric-value'));
        self::assertEquals(2, $this->chartData($orders)[0]['data'][11]);
        self::assertEquals(400, $this->chartData($amounts)[0]['data'][11]);
        $active = $orders->filter('[data-test-tmonier-insights-metrics] a[aria-current]');
        self::assertCount(1, $active);
        self::assertSame('orders', $active->attr('data-test-tmonier-insights-metric'));
        self::assertStringContainsString('metric=orders', (string) $active->attr('href'));
    }

    public function testAMetricOutsideTheAllowListIsReplacedByTheAmounts(): void
    {
        $admin = $this->administrator();

        foreach (['?metric=revenue', "?metric=orders'%20OR%201=1", '?metric[]=orders', '?metric=', '?metric=ORDERS'] as $query) {
            $crawler = $this->open($admin, $query);

            self::assertResponseIsSuccessful($query);
            self::assertSame('amount', $crawler->filter('[data-test-tmonier-insights-chart]')->attr('data-tmonier-insights-chart-metric-value'), $query);
        }
    }

    public function testTheMetricAndThePeriodAreKeptTogetherInTheLinks(): void
    {
        $crawler = $this->open($this->administrator(), '?metric=orders&period=current_year');

        self::assertStringContainsString('period=current_year', (string) $crawler->filter('[data-test-tmonier-insights-metric="amount"]')->attr('href'));
        self::assertStringContainsString('metric=orders', (string) $crawler->filter('[data-test-tmonier-insights-period="last_30_days"]')->attr('href'));
    }

    public function testTwoCurrenciesGiveTwoTablesAndTwoSeriesAndNoMixedTotal(): void
    {
        $admin = $this->administrator();
        $this->catalog()->order($admin, 10_000, '-10 days', 'EUR');
        $this->catalog()->order($admin, 7_000, '-10 days', 'CHF');

        $crawler = $this->open($admin);

        self::assertCount(1, $crawler->filter('[data-test-tmonier-insights-monthly-table="EUR"]'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-insights-monthly-table="CHF"]'));
        self::assertCount(2, $crawler->filter('[data-test-tmonier-insights-monthly-table]'));
        $series = $this->chartData($crawler);
        self::assertCount(4, $series, 'The year and the year before, for each currency.');
        self::assertEqualsCanonicalizing(['CHF', 'EUR'], array_values(array_unique(array_column($series, 'currency'))));
        self::assertEqualsCanonicalizing([100.0, 70.0], array_map(static fn (array $one): float => array_sum($one['data']), array_values(array_filter($series, static fn (array $one): bool => !$one['previous']))));
    }

    public function testTheChartLoadsNothingFromOutsideAndKeepsItsDataInAttributes(): void
    {
        $crawler = $this->open($this->administrator());

        $section = $crawler->filter('[data-test-tmonier-insights-monthly]');
        self::assertCount(1, $section);
        self::assertCount(0, $section->filter('script, link, img, iframe'), 'No script, style sheet or image of its own: the data is in attributes.');
        self::assertDoesNotMatchRegularExpression('#(src|href)="https?://#', $section->html());
        self::assertSame('true', $crawler->filter('[data-test-tmonier-insights-chart]')->attr('aria-hidden'), 'The table is what assistive technologies read.');
    }

    public function testOnlyTheOrdersOfTheCompanyOfTheMemberAreCharted(): void
    {
        $acme = $this->catalog()->company('Acme');
        $buildCo = $this->catalog()->company('BuildCo');
        $isabelle = $this->catalog()->customer('isabelle');
        $bea = $this->catalog()->customer('bea');
        $this->catalog()->member($acme, $isabelle, CompanyMemberInterface::ROLE_ADMIN);
        $this->catalog()->member($buildCo, $bea, CompanyMemberInterface::ROLE_ADMIN);
        $this->catalog()->order($isabelle, 10_000, '-1 day');
        $this->catalog()->order($bea, 99_000, '-1 day');

        $crawler = $this->open($isabelle, sprintf('?company=%1$d&company_id=%1$d', $buildCo->getId()));

        self::assertEqualsWithDelta(100.0, array_sum($this->chartData($crawler)[0]['data']), 0.001);
        self::assertStringNotContainsString('990.00', (string) $this->client->getResponse()->getContent());
    }

    public function testTheMonthlyPurchasesAreAvailableInFrench(): void
    {
        $this->enableLocale('fr_FR');
        $admin = $this->administrator();
        $this->loginShopUser($admin);

        $crawler = $this->client->request('GET', $this->shopUrl(self::URL, 'fr_FR'));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Achats par mois', $this->text($crawler, 'tmonier-insights-monthly'));
        self::assertStringContainsString('en cours', $crawler->filter('[data-test-tmonier-insights-monthly-table] tbody tr')->last()->text());
        self::assertStringContainsString('Nombre de commandes', $this->text($crawler, 'tmonier-insights-metrics'));
    }

    public function testAnAdministratorOfAnotherRoleIsStillRefusedTheChart(): void
    {
        $company = $this->catalog()->company();
        $this->catalog()->member($company, $this->catalog()->customer('isabelle'), CompanyMemberInterface::ROLE_ADMIN);
        $buyer = $this->catalog()->customer('claire');
        $this->catalog()->member($company, $buyer);

        $crawler = $this->open($buyer);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, $crawler->filter('[data-test-tmonier-insights-chart], [data-test-tmonier-insights-monthly-table]'));
    }

    /** @return list<array{name: string, currency: string, previous: bool, data: list<float|int|numeric-string>}> */
    private function chartData(Crawler $crawler): array
    {
        $series = json_decode((string) $crawler->filter('[data-test-tmonier-insights-chart]')->attr('data-tmonier-insights-chart-series-value'), true, flags: \JSON_THROW_ON_ERROR);
        Assert::isList($series);

        return array_map(static function (mixed $one): array {
            Assert::isArray($one);
            Assert::string($one['name'] ?? null);
            Assert::string($one['currency'] ?? null);
            Assert::boolean($one['previous'] ?? null);
            Assert::isList($one['data'] ?? null);
            Assert::allNumeric($one['data']);

            return ['name' => $one['name'], 'currency' => $one['currency'], 'previous' => $one['previous'], 'data' => $one['data']];
        }, $series);
    }

    private function administrator(): CustomerInterface
    {
        $company = $this->catalog()->company();
        $admin = $this->catalog()->customer('isabelle');
        $this->catalog()->member($company, $admin, CompanyMemberInterface::ROLE_ADMIN);

        return $admin;
    }

    private function open(CustomerInterface $customer, string $query = ''): Crawler
    {
        $this->loginShopUser($customer);

        return $this->client->request('GET', $this->shopUrl(self::URL . $query));
    }

    private function text(Crawler $crawler, string $attribute): string
    {
        $node = $crawler->filter('[data-test-' . $attribute . ']');
        self::assertGreaterThan(0, $node->count(), $attribute . ' is on the page');

        return (string) preg_replace('/\s+/', ' ', trim($node->first()->text()));
    }
}
