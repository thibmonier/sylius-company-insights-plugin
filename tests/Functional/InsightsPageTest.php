<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Functional;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

final class InsightsPageTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const URL = '/account/company/insights';

    public function testAnAdministratorSeesTheIndicatorsOfTheLastTwelveMonths(): void
    {
        $company = $this->catalog()->company();
        $admin = $this->catalog()->customer('isabelle');
        $this->catalog()->member($company, $admin, CompanyMemberInterface::ROLE_ADMIN);
        foreach ([60_000, 70_000, 80_000, 70_000, 70_000, 70_000] as $total) {
            $this->catalog()->order($admin, $total, '-30 days');
        }

        $crawler = $this->open($admin);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('4,200.00', $this->text($crawler, 'tmonier-insights-spend'));
        self::assertSame('6', $this->value($crawler, 'tmonier-insights-orders'));
        self::assertStringContainsString('700.00', $this->text($crawler, 'tmonier-insights-average'));
        self::assertSame('1', $this->value($crawler, 'tmonier-insights-buyers'));
        self::assertStringContainsString('Last 12 months', $this->text($crawler, 'tmonier-insights-period-title'));
        self::assertCount(0, $crawler->filter('[data-test-tmonier-insights-empty]'));
        // Nothing in the 12 months before: no comparison, said in words.
        self::assertCount(4, $crawler->filter('[data-test-tmonier-insights-variation="unknown"]'));
    }

    public function testTheVariationAgainstThePreviousPeriodIsShownWithItsDirection(): void
    {
        $company = $this->catalog()->company();
        $admin = $this->catalog()->customer('isabelle');
        $this->catalog()->member($company, $admin, CompanyMemberInterface::ROLE_ADMIN);
        $this->catalog()->order($admin, 30_000, '-10 days');
        $this->catalog()->order($admin, 10_000, '-50 days');
        $this->catalog()->order($admin, 10_000, '-51 days');

        $crawler = $this->open($admin, '?period=last_30_days');

        self::assertSame('1', $this->value($crawler, 'tmonier-insights-orders'));
        self::assertSame('down', $crawler->filter('[data-test-tmonier-insights-orders] [data-test-tmonier-insights-variation]')->attr('data-test-tmonier-insights-variation'));
        self::assertStringContainsString('-50.0%', $this->text($crawler, 'tmonier-insights-orders'));
        self::assertSame('up', $crawler->filter('[data-test-tmonier-insights-spend] [data-test-tmonier-insights-variation]')->attr('data-test-tmonier-insights-variation'));
        self::assertStringContainsString('+50.0%', $this->text($crawler, 'tmonier-insights-spend'));
    }

    public function testChangingThePeriodRestrictsTheIndicatorsAndIsRememberedInTheTitleAndTheUrl(): void
    {
        $company = $this->catalog()->company();
        $admin = $this->catalog()->customer('isabelle');
        $this->catalog()->member($company, $admin, CompanyMemberInterface::ROLE_ADMIN);
        $this->catalog()->order($admin, 10_000, 'now');
        $this->catalog()->order($admin, 90_000, '-200 days');

        $crawler = $this->open($admin, '?period=current_quarter');

        self::assertStringContainsString('Current quarter', $this->text($crawler, 'tmonier-insights-period-title'));
        self::assertSame('1', $this->value($crawler, 'tmonier-insights-orders'));
        self::assertStringContainsString('100.00', $this->text($crawler, 'tmonier-insights-spend'));
        $active = $crawler->filter('[data-test-tmonier-insights-periods] a[aria-current]');
        self::assertCount(1, $active);
        self::assertSame('current_quarter', $active->attr('data-test-tmonier-insights-period'));
        self::assertStringEndsWith('?period=current_quarter', (string) $active->attr('href'));
        self::assertCount(4, $crawler->filter('[data-test-tmonier-insights-period]'));

        $crawler = $this->open($admin, '?period=last_12_months');
        self::assertSame('2', $this->value($crawler, 'tmonier-insights-orders'));
    }

    public function testAPeriodOutsideTheAllowListIsReplacedByTheDefault(): void
    {
        $admin = $this->administrator();

        foreach (['?period=last_week', "?period=last_30_days'%20OR%201=1", '?period[]=last_30_days', '?period=', '?period=CURRENT_YEAR'] as $query) {
            $crawler = $this->open($admin, $query);

            self::assertResponseIsSuccessful($query);
            self::assertStringContainsString('Last 12 months', $this->text($crawler, 'tmonier-insights-period-title'), $query);
        }
    }

    public function testAParameterTargetingAnotherCompanyIsIgnored(): void
    {
        $acme = $this->catalog()->company('Acme');
        $buildCo = $this->catalog()->company('BuildCo');
        $isabelle = $this->catalog()->customer('isabelle');
        $bea = $this->catalog()->customer('bea');
        $this->catalog()->member($acme, $isabelle, CompanyMemberInterface::ROLE_ADMIN);
        $this->catalog()->member($buildCo, $bea, CompanyMemberInterface::ROLE_ADMIN);
        $this->catalog()->order($isabelle, 10_000, '-1 day');
        $this->catalog()->order($bea, 99_000, '-1 day');

        $query = sprintf('?company=%1$d&company_id=%1$d&companyId=%1$d&company[id]=%1$d&code=%2$s', $buildCo->getId(), $buildCo->getCode());
        $crawler = $this->open($isabelle, $query);

        self::assertResponseIsSuccessful();
        self::assertSame('1', $this->value($crawler, 'tmonier-insights-orders'));
        self::assertStringContainsString('100.00', $this->text($crawler, 'tmonier-insights-spend'));
        self::assertStringNotContainsString('990.00', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString($acme->getName() ?? '', $crawler->filter('[data-test-tmonier-company-name]')->text());
    }

    public function testACompanyWithoutOrderSeesZerosAndAMessage(): void
    {
        $admin = $this->administrator();

        $crawler = $this->open($admin);

        self::assertResponseIsSuccessful();
        self::assertSame('0', $this->value($crawler, 'tmonier-insights-orders'));
        self::assertSame('0', $this->value($crawler, 'tmonier-insights-buyers'));
        self::assertStringContainsString('0.00', $this->text($crawler, 'tmonier-insights-spend'));
        self::assertStringContainsString('0.00', $this->text($crawler, 'tmonier-insights-average'));
        self::assertStringContainsString('No orders in this period', $this->text($crawler, 'tmonier-insights-empty'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-insights-pending-none]'));
    }

    public function testCancelledOrdersAreNowhereAndPendingOnesOnlyInTheirOwnBlock(): void
    {
        $company = $this->catalog()->company();
        $admin = $this->catalog()->customer('isabelle');
        $this->catalog()->member($company, $admin, CompanyMemberInterface::ROLE_ADMIN);
        $this->catalog()->order($admin, 10_000, '-1 day');
        $this->catalog()->order($admin, 70_000, '-1 day', state: OrderInterface::STATE_CANCELLED);
        $this->catalog()->pendingApproval($this->catalog()->order($admin, 25_000, '-1 day'));

        $crawler = $this->open($admin);

        self::assertSame('1', $this->value($crawler, 'tmonier-insights-orders'));
        self::assertStringContainsString('100.00', $this->text($crawler, 'tmonier-insights-spend'));
        self::assertSame('1', $this->text($crawler, 'tmonier-insights-pending-count'));
        self::assertStringContainsString('250.00', $this->text($crawler, 'tmonier-insights-pending'));
        self::assertStringNotContainsString('700.00', (string) $this->client->getResponse()->getContent());
    }

    public function testBuyersAndApproversGetAnExplainedRefusal(): void
    {
        $company = $this->catalog()->company();
        $this->catalog()->member($company, $this->catalog()->customer('isabelle'), CompanyMemberInterface::ROLE_ADMIN);

        foreach ([CompanyMemberInterface::ROLE_BUYER, CompanyMemberInterface::ROLE_APPROVER] as $role) {
            $customer = $this->catalog()->customer($role);
            $this->catalog()->member($company, $customer, $role);
            $this->client->restart();
            $this->client->disableReboot();

            $crawler = $this->open($customer);

            self::assertResponseStatusCodeSame(403, $role);
            self::assertStringContainsString('reserved for company administrators', $this->text($crawler, 'tmonier-company-insights-forbidden'));
            self::assertCount(0, $crawler->filter('[data-test-tmonier-insights-orders]'), 'No figure leaks in the refusal.');
        }
    }

    public function testADisabledAdministratorOrOneOfADisabledCompanyIsRefused(): void
    {
        $company = $this->catalog()->company();
        $disabled = $this->catalog()->customer('disabled');
        $this->catalog()->member($company, $disabled, CompanyMemberInterface::ROLE_ADMIN, false);
        $closed = $this->catalog()->company('Closed', false);
        $closedAdmin = $this->catalog()->customer('closed');
        $this->catalog()->member($closed, $closedAdmin, CompanyMemberInterface::ROLE_ADMIN);

        $this->open($disabled);
        self::assertResponseStatusCodeSame(403);

        $this->client->restart();
        $this->client->disableReboot();
        $this->open($closedAdmin);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAMenuTabLeadsToTheDashboardForAdministratorsOnly(): void
    {
        $company = $this->catalog()->company();
        $admin = $this->catalog()->customer('isabelle');
        $buyer = $this->catalog()->customer('claire');
        $this->catalog()->member($company, $admin, CompanyMemberInterface::ROLE_ADMIN);
        $this->catalog()->member($company, $buyer);

        $this->loginShopUser($buyer);
        $crawler = $this->client->request('GET', $this->shopUrl('/account/company'));
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-test-tmonier-company-insights-link]'));

        $this->client->restart();
        $this->client->disableReboot();
        $this->loginShopUser($admin);
        $crawler = $this->client->request('GET', $this->shopUrl('/account/company'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-company-insights-link]'));
        self::assertStringEndsWith('/account/company/insights', (string) $crawler->filter('[data-test-tmonier-company-insights-link]')->attr('href'));

        $crawler = $this->client->click($crawler->filter('[data-test-tmonier-company-insights-link]')->link());
        self::assertResponseIsSuccessful();
        self::assertSame('page', $crawler->filter('[data-test-tmonier-company-insights-link]')->attr('aria-current'));
    }

    public function testACustomerWithoutCompanyGetsA404AndAVisitorIsSentToTheLogin(): void
    {
        $nobody = $this->catalog()->customer('nobody');

        $this->client->request('GET', $this->shopUrl(self::URL));
        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));

        $this->open($nobody);
        self::assertResponseStatusCodeSame(404);
    }

    public function testThePageIsAvailableInFrench(): void
    {
        $this->enableLocale('fr_FR');
        $company = $this->catalog()->company();
        $admin = $this->catalog()->customer('isabelle');
        $this->catalog()->member($company, $admin, CompanyMemberInterface::ROLE_ADMIN);
        $this->catalog()->order($admin, 10_000, '-1 day');
        $this->loginShopUser($admin);

        $crawler = $this->client->request('GET', $this->shopUrl(self::URL . '?period=current_year', 'fr_FR'));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Tableau de bord des achats', $crawler->filter('title')->text());
        self::assertStringContainsString('Année en cours', $this->text($crawler, 'tmonier-insights-period-title'));
        self::assertStringContainsString('Dépense HT', $this->text($crawler, 'tmonier-insights-spend'));
        self::assertStringContainsString('En attente d\'approbation', $this->text($crawler, 'tmonier-insights-pending'));
    }

    private function administrator(): \Sylius\Component\Core\Model\CustomerInterface
    {
        $company = $this->catalog()->company();
        $admin = $this->catalog()->customer('isabelle');
        $this->catalog()->member($company, $admin, CompanyMemberInterface::ROLE_ADMIN);

        return $admin;
    }

    private function open(\Sylius\Component\Core\Model\CustomerInterface $customer, string $query = ''): Crawler
    {
        $this->loginShopUser($customer);

        return $this->client->request('GET', $this->shopUrl(self::URL . $query));
    }

    /** Whitespace-normalised text of the element carrying the test attribute. */
    private function text(Crawler $crawler, string $attribute): string
    {
        $node = $crawler->filter('[data-test-' . $attribute . ']');
        self::assertGreaterThan(0, $node->count(), $attribute . ' is on the page');

        return (string) preg_replace('/\s+/', ' ', trim($node->first()->text()));
    }

    private function value(Crawler $crawler, string $attribute): string
    {
        return trim($crawler->filter('[data-test-' . $attribute . '] [data-test-tmonier-insights-value]')->first()->text());
    }
}
