<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Tmonier\SyliusCompanyInsightsPlugin\FailingWidgetDecorator;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

final class WidgetsDashboardTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const URL = '/account/company/insights';

    protected function tearDown(): void
    {
        FailingWidgetDecorator::$failing = [];
        parent::tearDown();
    }

    public function testAnOrderWaitingForFourDaysShowsInTheApprovalsWidgetWithALinkToTheApprovals(): void
    {
        [$isabelle, $company] = $this->administrator();
        $claire = $this->buyer($company, 'Claire', 'Martin');
        $this->catalog()->pendingApproval($this->catalog()->order($claire, 25_000, '-4 days'), createdAt: '-4 days');

        $crawler = $this->open($isabelle);

        self::assertResponseIsSuccessful();
        $widget = $this->widget($crawler, 'approvals');
        self::assertSame('Approvals', trim($widget->filter('h3')->text()));
        self::assertSame('1', $this->text($widget, 'tmonier-insights-approvals-pending-count'));
        self::assertStringContainsString('250.00', $this->text($widget, 'tmonier-insights-approvals-pending-amount'));
        self::assertStringContainsString('4 day(s)', $this->text($widget, 'tmonier-insights-approvals-oldest'));
        self::assertStringContainsString('Claire Martin', $this->text($widget, 'tmonier-insights-approvals-pending-row'));
        self::assertStringEndsWith('/account/company/approvals', (string) $widget->filter('[data-test-tmonier-insights-approvals-link]')->attr('href'));
    }

    public function testABuyerMostlyAskingForApprovalsAppearsInTheWidget(): void
    {
        [$isabelle, $company] = $this->administrator();
        $claire = $this->buyer($company, 'Claire', 'Martin');
        for ($index = 0; $index < 4; ++$index) {
            $this->catalog()->pendingApproval($this->catalog()->order($claire, 10_000, '-10 days'), 'approved', '-10 days', '-9 days');
        }
        $this->catalog()->order($claire, 1_000, '-8 days');

        $widget = $this->widget($this->open($isabelle), 'approvals');

        $row = $this->text($widget, 'tmonier-insights-approvals-buyer');
        self::assertStringContainsString('Claire Martin', $row);
        self::assertStringContainsString('80 %', $row);
        self::assertStringContainsString('24 h', $this->text($widget, 'tmonier-insights-approvals-median'));
        self::assertStringContainsString('0 %', $this->text($widget, 'tmonier-insights-approvals-refusal'));
    }

    public function testAnInvoiceOverdueForEighteenDaysShowsInTheCreditWidgetWithAGaugeAndItsTextEquivalent(): void
    {
        [$isabelle, $company] = $this->administrator();
        $claire = $this->buyer($company, 'Claire', 'Martin');
        $account = $this->catalog()->tradeCreditAccount($company, 1_000_000, 720_000);
        $this->catalog()->openInvoice($account, $this->catalog()->order($claire, 180_000, '-50 days'), 180_000, '-18 days');
        $this->catalog()->openInvoice($account, $this->catalog()->order($claire, 120_000, '-20 days'), 120_000, '+10 days');

        $crawler = $this->open($isabelle);

        self::assertResponseIsSuccessful();
        $widget = $this->widget($crawler, 'credit');
        self::assertSame('Credit', trim($widget->filter('h3')->text()));
        self::assertStringContainsString('1,800.00', $this->text($widget, 'tmonier-insights-credit-overdue'));
        self::assertStringContainsString('18 day(s)', $this->text($widget, 'tmonier-insights-credit-overdue'));
        self::assertStringContainsString('72 % of the credit limit used: €7,200.00 of €10,000.00', $this->text($widget, 'tmonier-insights-credit-usage'));
        $gauge = $widget->filter('progress');
        self::assertSame('72', $gauge->attr('value'));
        self::assertSame('100', $gauge->attr('max'));
        self::assertCount(1, $widget->filter('label[for="insights-credit-usage"]'), 'The gauge has a label, so its text equivalent is read with it.');
        self::assertStringContainsString('1,200.00', $this->text($widget, 'tmonier-insights-credit-next-due'));
        self::assertStringEndsWith('/account/trade-credit', (string) $widget->filter('[data-test-tmonier-insights-credit-link]')->attr('href'));
    }

    public function testACompanyWithoutPaymentOnAccountLineSaysSoInFrench(): void
    {
        $this->enableLocale('fr_FR');
        [$isabelle] = $this->administrator();
        $this->loginShopUser($isabelle);

        $crawler = $this->client->request('GET', $this->shopUrl(self::URL, 'fr_FR'));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Pas de compte de paiement à terme', $this->text($this->widget($crawler, 'credit'), 'tmonier-insights-credit-none'));
        self::assertSame('Encours', trim($this->widget($crawler, 'credit')->filter('h3')->text()));
        self::assertSame('Approbations', trim($this->widget($crawler, 'approvals')->filter('h3')->text()));
    }

    public function testTheCreditOfAnotherCompanyIsNotShown(): void
    {
        [$isabelle] = $this->administrator();
        $other = $this->catalog()->company('TechParts');
        $this->catalog()->tradeCreditAccount($other, 9_999_900, 5_000_000);

        $crawler = $this->open($isabelle);

        self::assertCount(1, $crawler->filter('[data-test-tmonier-insights-credit-none]'));
        self::assertStringNotContainsString('99,999.00', (string) $this->client->getResponse()->getContent());
    }

    public function testAFailingCompanionServiceMakesOnlyItsWidgetUnavailable(): void
    {
        [$isabelle, $company] = $this->administrator();
        $claire = $this->buyer($company, 'Claire', 'Martin');
        $this->catalog()->tradeCreditAccount($company, 1_000_000, 720_000);
        $this->catalog()->order($claire, 10_000, '-4 days');
        $this->open($isabelle);
        FailingWidgetDecorator::$failing = ['approvals'];

        $started = microtime(true);
        $crawler = $this->open($isabelle);
        $elapsed = microtime(true) - $started;

        self::assertResponseStatusCodeSame(200);
        self::assertLessThan(2.0, $elapsed);
        self::assertStringContainsString('Data temporarily unavailable', $this->text($this->widget($crawler, 'approvals'), 'tmonier-insights-widget-unavailable'));
        self::assertCount(1, $this->widget($crawler, 'credit')->filter('progress'), 'The other widgets stay.');
        self::assertCount(1, $crawler->filter('[data-test-tmonier-insights-orders]'), 'The dashboard stays.');
        $content = (string) $this->client->getResponse()->getContent();
        foreach (['RuntimeException', 'owes 1200', 'claire@acme.example', 'Stack trace', 'service is down'] as $leak) {
            self::assertStringNotContainsString($leak, $content);
        }
    }

    public function testAFailingServiceIsAlsoAnnouncedInFrench(): void
    {
        $this->enableLocale('fr_FR');
        [$isabelle] = $this->administrator();
        FailingWidgetDecorator::$failing = ['credit'];
        $this->loginShopUser($isabelle);

        $crawler = $this->client->request('GET', $this->shopUrl(self::URL, 'fr_FR'));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Données momentanément indisponibles', $this->text($this->widget($crawler, 'credit'), 'tmonier-insights-widget-unavailable'));
    }

    public function testAnApproverWhoIsNotAnAdministratorCannotSeeTheDashboardAndSoNeitherTheWidgets(): void
    {
        [, $company] = $this->administrator();
        $approver = $this->catalog()->customer('approver');
        $this->catalog()->member($company, $approver, CompanyMemberInterface::ROLE_APPROVER);

        $this->open($approver);

        self::assertResponseStatusCodeSame(403);
        self::assertStringNotContainsString('data-test-tmonier-insights-widget', (string) $this->client->getResponse()->getContent());
    }

    /** @return array{CustomerInterface, CompanyInterface} */
    private function administrator(): array
    {
        $company = $this->catalog()->company();
        $admin = $this->catalog()->customer('isabelle', 'Isabelle', 'Lovelace');
        $this->catalog()->member($company, $admin, CompanyMemberInterface::ROLE_ADMIN);

        return [$admin, $company];
    }

    private function buyer(CompanyInterface $company, string $first, string $last): CustomerInterface
    {
        $customer = $this->catalog()->customer(strtolower($first), $first, $last);
        $this->catalog()->member($company, $customer);

        return $customer;
    }

    private function open(CustomerInterface $customer): Crawler
    {
        $this->loginShopUser($customer);

        return $this->client->request('GET', $this->shopUrl(self::URL));
    }

    private function widget(Crawler $crawler, string $code): Crawler
    {
        $widget = $crawler->filter(sprintf('[data-test-tmonier-insights-widget="%s"]', $code));
        self::assertCount(1, $widget, $code . ' widget is on the page');

        return $widget;
    }

    private function text(Crawler $crawler, string $attribute): string
    {
        $node = $crawler->filter('[data-test-' . $attribute . ']');
        self::assertGreaterThan(0, $node->count(), $attribute . ' is on the page');

        return trim((string) preg_replace('/\s+/', ' ', $node->first()->text()));
    }
}
