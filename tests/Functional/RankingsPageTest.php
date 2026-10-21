<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Webmozart\Assert\Assert;

final class RankingsPageTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const URL = '/account/company/insights';

    public function testTheBuyersAreRankedWithTheirShareAndWithoutAnyEmail(): void
    {
        [$company, $admin] = $this->companyWithAdministrator();
        $claire = $this->buyer($company, 'claire', 'Claire', 'Martin');
        $paul = $this->buyer($company, 'paul', 'Paul', 'Durand');
        $this->catalog()->order($claire, 260_000, '-10 days');
        $this->catalog()->order($paul, 160_000, '-10 days');

        $crawler = $this->open($admin);

        $rows = $crawler->filter('[data-test-tmonier-insights-ranking="buyers-EUR"] [data-test-tmonier-insights-ranking-row]');
        self::assertCount(2, $rows);
        self::assertStringContainsString('Claire Martin', $rows->eq(0)->text());
        self::assertStringContainsString('Paul Durand', $rows->eq(1)->text());
        self::assertSame(['62', '38'], $rows->each(static fn (Crawler $row): string => (string) $row->filter('[data-test-tmonier-insights-ranking-share]')->attr('data-value')));
        self::assertStringContainsString('62%', $rows->eq(0)->text());
        $html = (string) $this->client->getResponse()->getContent();
        foreach (['claire', 'paul'] as $name) {
            self::assertStringNotContainsString(sprintf('%s-%s@', $name, $this->catalog()->suffix), $html, 'No e-mail address of a member.');
        }
        self::assertStringNotContainsString('mailto:', $html);
    }

    public function testADeactivatedMemberIsMarkedAndAMemberOfAnotherCompanyIsAbsent(): void
    {
        [$company, $admin] = $this->companyWithAdministrator();
        $other = $this->catalog()->company('TechParts');
        $old = $this->catalog()->customer('old', 'Olivier', 'Ancien');
        $oldMember = $this->catalog()->member($company, $old);
        $marc = $this->catalog()->customer('marc', 'Marc', 'Voisin');
        $this->catalog()->member($other, $marc);
        $this->catalog()->order($old, 100_000, '-10 days');
        $this->catalog()->order($marc, 500_000, '-10 days');
        $oldMember->setEnabled(false);
        $this->catalog()->entityManager()->flush();

        $crawler = $this->open($admin);

        $rows = $crawler->filter('[data-test-tmonier-insights-ranking="buyers-EUR"] [data-test-tmonier-insights-ranking-row]');
        self::assertCount(1, $rows);
        self::assertStringContainsString('Olivier Ancien', $rows->text());
        self::assertStringContainsString('deactivated', $rows->text());
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('Voisin', $html);
        self::assertStringNotContainsString('5,000.00', $html);
    }

    public function testTheTopProductsCanBeSortedByQuantity(): void
    {
        [$company, $admin] = $this->companyWithAdministrator();
        $claire = $this->buyer($company, 'claire', 'Claire', 'Martin');
        $locale = $this->catalog()->localeCode();
        $gloves = $this->catalog()->product('EPI-GANT-001', [$locale => 'Gloves']);
        $screws = $this->catalog()->product('VIS-INOX-004', [$locale => 'Screws']);
        $this->catalog()->orderOfLines($claire, [[$gloves, 100, 30_000], [$screws, 5, 80_000]], '-10 days');

        $bySpend = $this->open($admin);
        $byQuantity = $this->open($admin, '?sort=quantity');

        $codes = static fn (Crawler $crawler): array => $crawler->filter('[data-test-tmonier-insights-ranking="products-EUR"] [data-test-tmonier-insights-ranking-row]')->each(static fn (Crawler $row): string => (string) $row->attr('data-code'));
        self::assertSame(['VIS-INOX-004-' . $this->catalog()->suffix, 'EPI-GANT-001-' . $this->catalog()->suffix], $codes($bySpend));
        self::assertSame(['EPI-GANT-001-' . $this->catalog()->suffix, 'VIS-INOX-004-' . $this->catalog()->suffix], $codes($byQuantity));
        $active = $byQuantity->filter('[data-test-tmonier-insights-sorts] a[aria-current]');
        self::assertCount(1, $active);
        self::assertSame('quantity', $active->attr('data-test-tmonier-insights-sort'));
        self::assertStringContainsString('Gloves', $byQuantity->filter('[data-test-tmonier-insights-ranking="products-EUR"]')->text());
    }

    public function testASortOutsideTheAllowListIsReplacedBySpend(): void
    {
        [$company, $admin] = $this->companyWithAdministrator();
        $this->catalog()->order($this->buyer($company, 'claire', 'Claire', 'Martin'), 10_000, '-10 days');

        foreach (['?sort=price', '?sort=quantity%20DESC', '?sort[]=quantity', '?sort=QUANTITY', '?sort='] as $query) {
            $crawler = $this->open($admin, $query);

            self::assertResponseIsSuccessful($query);
            self::assertSame('spend', $crawler->filter('[data-test-tmonier-insights-sorts] a[aria-current]')->attr('data-test-tmonier-insights-sort'), $query);
        }
    }

    public function testAProductWithoutTranslationIsShownByItsCode(): void
    {
        [$company, $admin] = $this->companyWithAdministrator();
        $gloves = $this->catalog()->product('EPI-GANT-001', ['fr_FR' => 'Gants']);
        $this->catalog()->orderOfLines($this->buyer($company, 'claire', 'Claire', 'Martin'), [[$gloves, 2, 10_000]], '-10 days');

        $crawler = $this->open($admin);

        self::assertResponseIsSuccessful();
        $row = $crawler->filter('[data-test-tmonier-insights-ranking="products-EUR"] [data-test-tmonier-insights-ranking-row]');
        self::assertCount(1, $row);
        self::assertStringContainsString('EPI-GANT-001-' . $this->catalog()->suffix, $row->text());
        self::assertStringNotContainsString('Gants', $row->text());
    }

    public function testTheCategoriesAddUpToOneHundredPercentWithTheirDonutAndTable(): void
    {
        [$company, $admin] = $this->companyWithAdministrator();
        $locale = $this->catalog()->localeCode();
        $epi = $this->catalog()->product('EPI-1', [$locale => 'Gloves'], $this->catalog()->taxon('EPI', 'Protective equipment'));
        $screws = $this->catalog()->product('VIS-1', [$locale => 'Screws'], $this->catalog()->taxon('VISSERIE', 'Fasteners'));
        $tool = $this->catalog()->product('OUT-1', [$locale => 'Drill'], $this->catalog()->taxon('OUTILLAGE', 'Tools'));
        $this->catalog()->orderOfLines($this->buyer($company, 'claire', 'Claire', 'Martin'), [[$epi, 1, 10_000], [$screws, 1, 10_000], [$tool, 1, 10_000]], '-10 days');

        $crawler = $this->open($admin);

        $block = $crawler->filter('[data-test-tmonier-insights-ranking="categories-EUR"]');
        $table = $block->filter('table');
        self::assertCount(1, $table);
        self::assertGreaterThan(0, $table->filter('caption')->count());
        self::assertCount(3, $table->filter('tbody th[scope="row"]'));
        self::assertSame(100, array_sum($table->filter('[data-test-tmonier-insights-ranking-share]')->each(static fn (Crawler $cell): int => (int) $cell->attr('data-value'))));
        self::assertStringContainsString('Protective equipment', $table->text());
        $donut = $block->filter('[data-test-tmonier-insights-donut]');
        self::assertCount(1, $donut);
        self::assertSame('true', $donut->attr('aria-hidden'));
        self::assertSame('tmonier-insights-donut', $donut->attr('data-controller'));
        $values = json_decode((string) $donut->attr('data-tmonier-insights-donut-values-value'), true, flags: \JSON_THROW_ON_ERROR);
        Assert::isArray($values);
        Assert::allNumeric($values);
        self::assertEqualsWithDelta(300.0, array_sum($values), 0.001);
        self::assertCount(0, $block->filter('script, link, img, iframe'));
    }

    public function testEachRankingIsLimitedToTenRowsWithALinkToSeeAll(): void
    {
        [$company, $admin] = $this->companyWithAdministrator();
        foreach (range(1, 11) as $index) {
            $this->catalog()->order($this->buyer($company, 'b' . $index, 'Buyer', sprintf('N%02d', $index)), 1_000 * (12 - $index), '-10 days');
        }

        $crawler = $this->open($admin);

        self::assertCount(10, $crawler->filter('[data-test-tmonier-insights-ranking="buyers-EUR"] [data-test-tmonier-insights-ranking-row]'));
        $link = $crawler->filter('[data-test-tmonier-insights-ranking="buyers-EUR"] a[data-test-tmonier-insights-see-all]');
        self::assertCount(1, $link);
        self::assertStringContainsString('/account/company/insights/buyers', (string) $link->attr('href'));
        self::assertStringContainsString('period=last_12_months', (string) $link->attr('href'));
        self::assertCount(0, $crawler->filter('[data-test-tmonier-insights-ranking="products-EUR"] a[data-test-tmonier-insights-see-all]'));
    }

    public function testTheFullListIsPaginated(): void
    {
        [$company, $admin] = $this->companyWithAdministrator();
        foreach (range(1, 30) as $index) {
            $this->catalog()->order($this->buyer($company, 'b' . $index, 'Buyer', sprintf('N%02d', $index)), 1_000 * (31 - $index), '-10 days');
        }

        $first = $this->open($admin, '/buyers');
        $second = $this->open($admin, '/buyers?page=2');
        $beyond = $this->open($admin, '/buyers?page=99');
        $invalid = $this->open($admin, "/buyers?page=2'%20OR%201=1");

        $names = static fn (Crawler $crawler): array => $crawler->filter('[data-test-tmonier-insights-ranking-row]')->each(static fn (Crawler $row): string => $row->filter('th')->text());
        self::assertCount(25, $names($first));
        self::assertCount(5, $names($second));
        self::assertSame($names($second), $names($beyond), 'Beyond the last page is the last page.');
        self::assertSame($names($first), $names($invalid), 'Not a page number is the first page.');
        $current = $second->filter('[data-test-tmonier-insights-pagination] a[aria-current="page"]');
        self::assertCount(1, $current);
        self::assertSame('2', trim($current->text()));
        self::assertCount(1, $first->filter('[data-test-tmonier-insights-pagination]'));
    }

    public function testTheFullListOfProductsKeepsTheSort(): void
    {
        [$company, $admin] = $this->companyWithAdministrator();
        $locale = $this->catalog()->localeCode();
        $gloves = $this->catalog()->product('EPI-GANT-001', [$locale => 'Gloves']);
        $screws = $this->catalog()->product('VIS-INOX-004', [$locale => 'Screws']);
        $this->catalog()->orderOfLines($this->buyer($company, 'claire', 'Claire', 'Martin'), [[$gloves, 100, 30_000], [$screws, 5, 80_000]], '-10 days');

        $crawler = $this->open($admin, '/products?sort=quantity');

        self::assertResponseIsSuccessful();
        self::assertSame('EPI-GANT-001-' . $this->catalog()->suffix, $crawler->filter('[data-test-tmonier-insights-ranking-row]')->first()->attr('data-code'));
        self::assertSame('quantity', $crawler->filter('[data-test-tmonier-insights-sorts] a[aria-current]')->attr('data-test-tmonier-insights-sort'));
    }

    public function testAnUnknownRankingIsNotFoundAndNothingElseIsAllowed(): void
    {
        [, $admin] = $this->companyWithAdministrator();

        foreach (['/members', '/BUYERS', '/buyers%27', '/categories/extra'] as $path) {
            $this->open($admin, $path);

            self::assertResponseStatusCodeSame(404, $path);
        }
    }

    public function testACurrencyThatIsNotOneOfTheCompanyIsReplacedByItsOwn(): void
    {
        [$company, $admin] = $this->companyWithAdministrator();
        $this->catalog()->order($this->buyer($company, 'claire', 'Claire', 'Martin'), 10_000, '-10 days', 'EUR');
        $this->catalog()->order($this->buyer($company, 'paul', 'Paul', 'Durand'), 7_000, '-10 days', 'CHF');

        $default = $this->open($admin, '/buyers')->filter('[data-test-tmonier-insights-ranking]')->text();
        $chf = $this->open($admin, '/buyers?currency=CHF')->filter('[data-test-tmonier-insights-ranking]')->text();
        $eur = $this->open($admin, '/buyers?currency=EUR')->filter('[data-test-tmonier-insights-ranking]')->text();
        $unknown = $this->open($admin, '/buyers?currency=USD')->filter('[data-test-tmonier-insights-ranking]')->text();
        $injection = $this->open($admin, "/buyers?currency=EUR'%20OR%201=1");

        self::assertStringContainsString('Paul Durand', $chf);
        self::assertStringNotContainsString('Claire Martin', $chf);
        self::assertStringContainsString('Claire Martin', $eur);
        self::assertStringNotContainsString('Paul Durand', $eur);
        self::assertResponseIsSuccessful();
        self::assertSame($default, $unknown, 'A currency of nobody is the default one.');
        self::assertSame($default, $injection->filter('[data-test-tmonier-insights-ranking]')->text());
    }

    public function testTheFullListsOnlyShowTheCompanyOfTheMemberWhateverTheRequest(): void
    {
        [$company, $admin] = $this->companyWithAdministrator();
        $other = $this->catalog()->company('TechParts');
        $this->catalog()->order($this->buyer($company, 'claire', 'Claire', 'Martin'), 10_000, '-10 days');
        $this->catalog()->order($this->buyer($other, 'marc', 'Marc', 'Voisin'), 99_000, '-10 days');

        foreach (['buyers', 'products', 'categories'] as $ranking) {
            $this->open($admin, sprintf('/%s?company=%2$d&company_id=%2$d', $ranking, $other->getId()));

            self::assertResponseIsSuccessful($ranking);
            $html = (string) $this->client->getResponse()->getContent();
            self::assertStringNotContainsString('Voisin', $html, $ranking);
            self::assertStringNotContainsString('990.00', $html, $ranking);
        }
    }

    public function testABuyerOfTheCompanyCannotSeeTheRankings(): void
    {
        [$company] = $this->companyWithAdministrator();
        $buyer = $this->buyer($company, 'claire', 'Claire', 'Martin');
        $this->catalog()->order($buyer, 10_000, '-10 days');

        foreach (['', '/buyers', '/products', '/categories'] as $path) {
            $crawler = $this->open($buyer, $path);

            self::assertResponseStatusCodeSame(403, $path);
            self::assertCount(0, $crawler->filter('[data-test-tmonier-insights-ranking]'));
            self::assertStringNotContainsString('Claire Martin', (string) $this->client->getResponse()->getContent());
        }
    }

    public function testTheRankingsAreAvailableInFrench(): void
    {
        $this->enableLocale('fr_FR');
        [$company, $admin] = $this->companyWithAdministrator();
        $old = $this->catalog()->customer('old', 'Olivier', 'Ancien');
        $oldMember = $this->catalog()->member($company, $old);
        $gloves = $this->catalog()->product('EPI-GANT-001', ['fr_FR' => 'Gants']);
        $this->catalog()->orderOfLines($old, [[$gloves, 2, 10_000]], '-10 days');
        $oldMember->setEnabled(false);
        $this->catalog()->entityManager()->flush();
        $this->loginShopUser($admin);

        $crawler = $this->client->request('GET', $this->shopUrl(self::URL, 'fr_FR'));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Top acheteurs', $crawler->filter('[data-test-tmonier-insights-ranking="buyers-EUR"]')->text());
        self::assertStringContainsString('désactivé', $crawler->filter('[data-test-tmonier-insights-ranking="buyers-EUR"]')->text());
        self::assertStringContainsString('Top produits', $crawler->filter('[data-test-tmonier-insights-ranking="products-EUR"]')->text());
        self::assertStringContainsString('Gants', $crawler->filter('[data-test-tmonier-insights-ranking="products-EUR"]')->text());
        self::assertStringContainsString('Top catégories', $crawler->filter('[data-test-tmonier-insights-ranking="categories-EUR"]')->text());
    }

    /** @return array{0: CompanyInterface, 1: CustomerInterface} */
    private function companyWithAdministrator(): array
    {
        $company = $this->catalog()->company();
        $admin = $this->catalog()->customer('isabelle', 'Isabelle', 'Admin');
        $this->catalog()->member($company, $admin, CompanyMemberInterface::ROLE_ADMIN);

        return [$company, $admin];
    }

    private function buyer(CompanyInterface $company, string $name, string $firstName, string $lastName): CustomerInterface
    {
        $customer = $this->catalog()->customer($name, $firstName, $lastName);
        $this->catalog()->member($company, $customer);

        return $customer;
    }

    private function open(CustomerInterface $customer, string $suffix = ''): Crawler
    {
        $this->loginShopUser($customer);

        return $this->client->request('GET', $this->shopUrl(self::URL . $suffix));
    }
}
