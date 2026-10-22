<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

final class ExportEndpointTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const URL = '/account/company/insights/export';

    public function testAnAdministratorDownloadsTheCsvOfHisCompanyOnly(): void
    {
        $acme = $this->catalog()->company('Acme');
        $other = $this->catalog()->company('TechParts');
        $isabelle = $this->catalog()->customer('isabelle', 'Isabelle', 'Lovelace');
        $marc = $this->catalog()->customer('marc', 'Marc', 'Autre');
        $this->catalog()->member($acme, $isabelle, CompanyMemberInterface::ROLE_ADMIN);
        $this->catalog()->member($other, $marc, CompanyMemberInterface::ROLE_ADMIN);
        $variant = $this->catalog()->product('VIS-001', ['en_US' => 'Screws']);
        $mine = $this->catalog()->orderOfLines($isabelle, [[$variant, 2, 10_000]], '-3 days');
        $this->catalog()->orderOfLines($marc, [[$variant, 9, 990_000]], '-3 days');

        $content = $this->download($isabelle, sprintf('?company=%d', $this->catalog()->id($other)));

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertStringStartsWith('text/csv; charset=UTF-8', (string) $response->headers->get('Content-Type'));
        self::assertMatchesRegularExpression('/^attachment; filename=purchases-\d{4}-\d{2}-\d{2}-\d{4}-\d{2}-\d{2}\.csv$/', (string) $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertStringContainsString((string) $mine->getNumber(), $content);
        self::assertStringContainsString('100,00', $content);
        self::assertStringNotContainsString('Autre', $content);
        self::assertStringNotContainsString('9900,00', $content);
        self::assertCount(2, array_filter(explode("\r\n", $content)));
    }

    public function testTheFiltersOfTheDashboardApplyToTheFile(): void
    {
        $company = $this->catalog()->company();
        $isabelle = $this->catalog()->customer('isabelle', 'Isabelle', 'Lovelace');
        $paul = $this->catalog()->customer('paul', 'Paul', 'Durand');
        $this->catalog()->member($company, $isabelle, CompanyMemberInterface::ROLE_ADMIN);
        $this->catalog()->member($company, $paul);
        $variant = $this->catalog()->product('VIS-001', ['en_US' => 'Screws']);
        $this->catalog()->orderOfLines($isabelle, [[$variant, 1, 10_000]], '-3 days');
        $this->catalog()->orderOfLines($paul, [[$variant, 1, 30_000]], '-3 days');

        $content = $this->download($isabelle, '?member=' . $this->catalog()->id($paul));

        self::assertStringContainsString('300,00', $content);
        self::assertStringNotContainsString('Lovelace', $content);
    }

    public function testAPeriodOfThirtySixMonthsProducesNoFileAndInvitesToReduceIt(): void
    {
        $company = $this->catalog()->company();
        $isabelle = $this->catalog()->customer('isabelle');
        $this->catalog()->member($company, $isabelle, CompanyMemberInterface::ROLE_ADMIN);
        $variant = $this->catalog()->product('VIS-001', ['en_US' => 'Screws']);
        $this->catalog()->orderOfLines($isabelle, [[$variant, 1, 10_000]], '-3 days');

        $this->loginShopUser($isabelle);
        $crawler = $this->client->request('GET', $this->shopUrl(self::URL . '?from=2023-10-01&to=2026-10-21'));

        self::assertResponseStatusCodeSame(422);
        self::assertStringNotContainsString('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertNull($this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('24 months at most', $crawler->filter('[data-test-tmonier-insights-export-error]')->text());
    }

    public function testBuyersAndApproversAreRefusedLikeForTheDashboard(): void
    {
        $company = $this->catalog()->company();
        $this->catalog()->member($company, $this->catalog()->customer('isabelle'), CompanyMemberInterface::ROLE_ADMIN);

        foreach ([CompanyMemberInterface::ROLE_BUYER, CompanyMemberInterface::ROLE_APPROVER] as $role) {
            $customer = $this->catalog()->customer($role);
            $this->catalog()->member($company, $customer, $role);
            $this->client->restart();
            $this->client->disableReboot();
            $this->loginShopUser($customer);

            $this->client->request('GET', $this->shopUrl(self::URL));

            self::assertResponseStatusCodeSame(403, $role);
            self::assertNull($this->client->getResponse()->headers->get('Content-Disposition'), $role);
        }
    }

    public function testAVisitorIsSentToTheLoginAndACustomerWithoutCompanyGetsA404(): void
    {
        $this->client->request('GET', $this->shopUrl(self::URL));
        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));

        $this->loginShopUser($this->catalog()->customer('nobody'));
        $this->client->request('GET', $this->shopUrl(self::URL));
        self::assertResponseStatusCodeSame(404);
    }

    private function download(CustomerInterface $customer, string $query = ''): string
    {
        $this->loginShopUser($customer);
        $this->client->request('GET', $this->shopUrl(self::URL . $query));

        return $this->client->getInternalResponse()->getContent();
    }
}
