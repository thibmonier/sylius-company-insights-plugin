<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

final class FilteredDashboardTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const URL = '/account/company/insights';

    public function testTheBreakdownPerSiteShowsTheSpendAndTheShareOfEachSite(): void
    {
        [$isabelle, $company] = $this->administrator();
        $this->catalog()->addressBookEntry($company, 'Atelier de Lyon', '12 rue des Ateliers', 'Lyon');
        $this->catalog()->addressBookEntry($company, 'Depot de Grenoble', '3 avenue du Depot', 'Grenoble', '38000');
        $this->catalog()->deliverTo($this->catalog()->order($isabelle, 75_000, '-10 days'), '12 rue des Ateliers', 'Lyon');
        $this->catalog()->deliverTo($this->catalog()->order($isabelle, 25_000, '-9 days'), '3 avenue du Depot', 'Grenoble', '38000');

        $crawler = $this->open($isabelle);

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('[data-test-tmonier-insights-breakdown="sites-EUR"] [data-test-tmonier-insights-breakdown-row]');
        self::assertCount(2, $rows);
        self::assertStringContainsString('Atelier de Lyon', $rows->eq(0)->text());
        self::assertStringContainsString('750.00', $rows->eq(0)->text());
        self::assertStringContainsString('75 %', $rows->eq(0)->text());
        self::assertStringContainsString('Depot de Grenoble', $rows->eq(1)->text());
        self::assertStringContainsString('25 %', $rows->eq(1)->text());
    }

    public function testEveryScrollableTableIsAFocusableLabelledRegionAndTheFiltersCanWrap(): void
    {
        [$isabelle, $company] = $this->administrator();
        $this->catalog()->addressBookEntry($company, 'Atelier de Lyon', '12 rue des Ateliers', 'Lyon');
        $locale = $this->catalog()->localeCode();
        $gloves = $this->catalog()->product('EPI-1', [$locale => 'Gloves'], $this->catalog()->taxon('EPI', 'Protective equipment'));
        $order = $this->catalog()->orderOfLines($isabelle, [[$gloves, 1, 10_000]], '-10 days');
        $this->catalog()->deliverTo($order, '12 rue des Ateliers', 'Lyon');

        $crawler = $this->open($isabelle);

        $regions = $crawler->filter('.table-responsive');
        self::assertGreaterThanOrEqual(4, $regions->count());
        foreach ($regions as $node) {
            $region = new Crawler($node);
            self::assertSame('0', $region->attr('tabindex'));
            self::assertSame('region', $region->attr('role'));
            $label = trim((string) $region->attr('aria-label'));
            self::assertNotSame('', $label);
            self::assertSame($label, trim($region->filter('table > caption')->text()));
        }
        self::assertStringContainsString('flex-wrap', (string) $crawler->filter('[data-test-tmonier-insights-filter-apply]')->ancestors()->first()->attr('class'));
        self::assertStringContainsString('text-wrap', (string) $crawler->filter('[data-test-tmonier-insights-filter-apply]')->attr('class'));
    }

    public function testWithoutAxisOfTheApplicationOnlyTheSiteBlockIsShown(): void
    {
        [$isabelle] = $this->administrator();
        $this->catalog()->order($isabelle, 10_000, '-1 day');

        $crawler = $this->open($isabelle);

        self::assertCount(1, $crawler->filter('[data-test-tmonier-insights-breakdown]'));
        self::assertCount(0, $crawler->filter('[data-test-tmonier-insights-breakdown="cost-center-EUR"]'));
    }

    public function testFilteringOnAMemberNarrowsTheIndicatorsTheChartTheTopsAndTheBreakdown(): void
    {
        [$isabelle, $company] = $this->administrator();
        $paul = $this->catalog()->customer('paul', 'Paul', 'Durand');
        $this->catalog()->member($company, $paul);
        $taxon = $this->catalog()->taxon('EPI', 'Protective equipment');
        $gloves = $this->catalog()->product('EPI-GANT-001', ['en_US' => 'Gloves'], $taxon);
        $this->catalog()->addressBookEntry($company, 'Atelier de Lyon', '12 rue des Ateliers', 'Lyon');
        $this->catalog()->deliverTo($this->catalog()->orderOfLines($isabelle, [[$gloves, 5, 50_000]], '-10 days'), '12 rue des Ateliers', 'Lyon');
        $this->catalog()->deliverTo($this->catalog()->orderOfLines($paul, [[$gloves, 2, 20_000]], '-5 days'), '12 rue des Ateliers', 'Lyon');

        $crawler = $this->open($isabelle, '?member=' . $this->catalog()->id($paul));

        self::assertResponseIsSuccessful();
        self::assertSame('1', $this->value($crawler, 'tmonier-insights-orders'));
        self::assertStringContainsString('200.00', $this->text($crawler, 'tmonier-insights-spend'));
        self::assertSame('1', $this->value($crawler, 'tmonier-insights-buyers'));
        self::assertStringContainsString('200.00', $this->text($crawler, 'tmonier-insights-ranking', 'buyers-EUR'));
        self::assertStringNotContainsString('Lovelace', $this->text($crawler, 'tmonier-insights-ranking', 'buyers-EUR'));
        self::assertStringContainsString('200.00', $this->text($crawler, 'tmonier-insights-ranking', 'categories-EUR'));
        self::assertStringContainsString('200.00', $this->text($crawler, 'tmonier-insights-breakdown', 'sites-EUR'));
        $values = $crawler->filter('[data-test-tmonier-insights-monthly-spend]')->each(static fn (Crawler $cell): string => (string) $cell->attr('data-value'));
        self::assertSame(20_000, array_sum(array_map(intval(...), $values)));
        self::assertSame((string) $this->catalog()->id($paul), $crawler->filter('#insights-filter-member option[selected]')->attr('value'));
    }

    public function testTheFiltersFollowTheLinksOfThePage(): void
    {
        [$isabelle, $company] = $this->administrator();
        $paul = $this->catalog()->customer('paul', 'Paul', 'Durand');
        $this->catalog()->member($company, $paul);
        $site = $this->catalog()->addressBookEntry($company, 'Atelier de Lyon', '12 rue des Ateliers', 'Lyon');
        $gloves = $this->catalog()->product('EPI-GANT-001', ['en_US' => 'Gloves']);
        $this->catalog()->deliverTo($this->catalog()->orderOfLines($paul, [[$gloves, 2, 20_000]], '-5 days'), '12 rue des Ateliers', 'Lyon');

        $crawler = $this->open($isabelle, sprintf('?member=%d&site=%d&period=current_year', $this->catalog()->id($paul), $this->catalog()->id($site)));

        foreach ([
            '[data-test-tmonier-insights-period="last_30_days"]',
            '[data-test-tmonier-insights-metric="orders"]',
            '[data-test-tmonier-insights-export-link]',
        ] as $selector) {
            $href = (string) $crawler->filter($selector)->attr('href');
            self::assertStringContainsString('member=' . $this->catalog()->id($paul), $href, $selector);
            self::assertStringContainsString('site=' . $this->catalog()->id($site), $href, $selector);
        }
        self::assertStringContainsString('period=current_year', (string) $crawler->filter('[data-test-tmonier-insights-export-link]')->attr('href'));
    }

    public function testAMemberOrASiteOfAnotherCompanyIsIgnored(): void
    {
        [$isabelle] = $this->administrator();
        $other = $this->catalog()->company('TechParts');
        $marc = $this->catalog()->customer('marc', 'Marc', 'Autre');
        $this->catalog()->member($other, $marc);
        $foreign = $this->catalog()->addressBookEntry($other, 'Their site', '1 rue Etrangere', 'Paris', '75001');
        $this->catalog()->order($isabelle, 10_000, '-1 day');
        $this->catalog()->order($marc, 990_000, '-1 day');

        $crawler = $this->open($isabelle, sprintf('?member=%d&site=%d', $this->catalog()->id($marc), $this->catalog()->id($foreign)));

        self::assertResponseIsSuccessful();
        self::assertSame('1', $this->value($crawler, 'tmonier-insights-orders'));
        self::assertCount(0, $crawler->filter('#insights-filter-member option[selected]'));
        self::assertStringNotContainsString('Their site', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('Autre', (string) $this->client->getResponse()->getContent());
    }

    public function testACustomPeriodOfTwoDatesRestrictsTheDashboard(): void
    {
        [$isabelle] = $this->administrator();
        $this->catalog()->order($isabelle, 10_000, '2026-03-15 10:00:00');
        $this->catalog()->order($isabelle, 90_000, '2026-05-15 10:00:00');

        $crawler = $this->open($isabelle, '?from=2026-03-01&to=2026-03-31');

        self::assertResponseIsSuccessful();
        self::assertSame('1', $this->value($crawler, 'tmonier-insights-orders'));
        self::assertStringContainsString('100.00', $this->text($crawler, 'tmonier-insights-spend'));
        self::assertStringContainsString('Custom period', $this->text($crawler, 'tmonier-insights-period-title'));
        self::assertSame('2026-03-01', $crawler->filter('#insights-filter-from')->attr('value'));
        self::assertSame('2026-03-31', $crawler->filter('#insights-filter-to')->attr('value'));
        self::assertCount(0, $crawler->filter('[data-test-tmonier-insights-periods] a[aria-current]'));
    }

    public function testTheFiltersAreWrittenInFrench(): void
    {
        $this->enableLocale('fr_FR');
        [$isabelle] = $this->administrator();
        $this->loginShopUser($isabelle);

        $crawler = $this->client->request('GET', $this->shopUrl(self::URL, 'fr_FR'));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Site de livraison', $crawler->filter('[data-test-tmonier-insights-filters]')->text());
        self::assertStringContainsString('Exporter en CSV', $crawler->filter('[data-test-tmonier-insights-export]')->text());
        self::assertStringContainsString('Achats par site de livraison', $crawler->filter('[data-test-tmonier-insights-breakdown]')->first()->text());
    }

    public function testAPeriodOfMoreThanTwentyFourMonthsOffersNoExportLink(): void
    {
        [$isabelle] = $this->administrator();

        $crawler = $this->open($isabelle, '?from=2023-10-01&to=2026-10-21');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-test-tmonier-insights-export-link]'));
        self::assertStringContainsString('24 months at most', $this->text($crawler, 'tmonier-insights-export-too-long'));
    }

    /** @return array{CustomerInterface, \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface} */
    private function administrator(): array
    {
        $company = $this->catalog()->company();
        $admin = $this->catalog()->customer('isabelle', 'Isabelle', 'Lovelace');
        $this->catalog()->member($company, $admin, CompanyMemberInterface::ROLE_ADMIN);

        return [$admin, $company];
    }

    private function open(CustomerInterface $customer, string $query = ''): Crawler
    {
        $this->loginShopUser($customer);

        return $this->client->request('GET', $this->shopUrl(self::URL . $query));
    }

    private function text(Crawler $crawler, string $attribute, ?string $value = null): string
    {
        $selector = null === $value ? '[data-test-' . $attribute . ']' : sprintf('[data-test-%s="%s"]', $attribute, $value);
        $node = $crawler->filter($selector);
        self::assertGreaterThan(0, $node->count(), $selector . ' is on the page');

        return trim((string) preg_replace('/\s+/', ' ', $node->first()->text()));
    }

    private function value(Crawler $crawler, string $attribute): string
    {
        return trim($crawler->filter('[data-test-' . $attribute . '] [data-test-tmonier-insights-value]')->first()->text());
    }
}
