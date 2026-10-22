<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Bridge\TradeCredit\CreditWidgetProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\PurchaseFilter;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodComparison;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodKey;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidget;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\WidgetContext;

final class CreditWidgetTest extends KernelTestCase
{
    use IntegrationTestTrait;

    public function testTheLimitTheAmountInUseAndWhatIsLeftAreShownWithTheUsageGauge(): void
    {
        [$company, $admin] = $this->company();
        $this->catalog()->tradeCreditAccount($company, 1_000_000, 720_000);

        $data = $this->widget($company, $admin)->data;

        self::assertTrue($this->at($data, 'account'));
        self::assertSame('EUR', $this->at($data, 'currency'));
        self::assertSame(1_000_000, $this->at($data, 'limit'));
        self::assertSame(720_000, $this->at($data, 'used'));
        self::assertSame(280_000, $this->at($data, 'available'));
        self::assertSame(72, $this->at($data, 'used_percent'));
        self::assertFalse($this->at($data, 'on_hold'));
        self::assertIsString($this->at($data, 'url'));
        self::assertStringEndsWith('/account/trade-credit', $this->at($data, 'url'));
    }

    public function testAnInvoiceOverdueForEighteenDaysIsReportedWithTheNextDueDate(): void
    {
        [$company, $admin] = $this->company();
        $account = $this->catalog()->tradeCreditAccount($company, 1_000_000, 300_000);
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $this->catalog()->openInvoice($account, $this->catalog()->order($claire, 180_000, '-50 days'), 180_000, '-18 days');
        $this->catalog()->openInvoice($account, $this->catalog()->order($claire, 120_000, '-20 days'), 120_000, '+10 days');
        $this->catalog()->openInvoice($account, $this->catalog()->order($claire, 40_000, '-5 days'), 40_000, '+25 days');

        $data = $this->widget($company, $admin)->data;

        self::assertSame(180_000, $this->at($data, 'overdue_amount'));
        self::assertSame(1, $this->at($data, 'overdue_count'));
        self::assertSame(18, $this->at($data, 'oldest_overdue_days'));
        self::assertSame(120_000, $this->at($data, 'next_due', 'amount'));
        $date = $this->at($data, 'next_due', 'date');
        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame((new \DateTimeImmutable('+10 days'))->format('Y-m-d'), $date->format('Y-m-d'));
    }

    public function testNothingOverdueAndNothingToComeIsSaidAsSuch(): void
    {
        [$company, $admin] = $this->company();
        $this->catalog()->tradeCreditAccount($company, 500_000, 0);

        $data = $this->widget($company, $admin)->data;

        self::assertSame(0, $this->at($data, 'overdue_count'));
        self::assertNull($this->at($data, 'oldest_overdue_days'));
        self::assertNull($this->at($data, 'next_due'));
        self::assertSame(0, $this->at($data, 'used_percent'));
    }

    public function testACompanyWithoutPaymentOnAccountLineHasNoAccount(): void
    {
        [$company, $admin] = $this->company();

        $widget = $this->widget($company, $admin);

        self::assertFalse($widget->unavailable);
        self::assertFalse($this->at($widget->data, 'account'));
    }

    public function testTheCreditLineOfAnotherCompanyIsNeverShown(): void
    {
        [$acme, $isabelle] = $this->company();
        [$other] = $this->company('TechParts');
        $this->catalog()->tradeCreditAccount($other, 9_000_000, 8_000_000);

        self::assertFalse($this->at($this->widget($acme, $isabelle)->data, 'account'));
    }

    public function testALineOnHoldAndAnExceededLimitAreReportedWithoutNegativeAvailability(): void
    {
        [$company, $admin] = $this->company();
        $this->catalog()->tradeCreditAccount($company, 100_000, 130_000, hold: true);

        $data = $this->widget($company, $admin)->data;

        self::assertTrue($this->at($data, 'on_hold'));
        self::assertSame(0, $this->at($data, 'available'));
        self::assertSame(100, $this->at($data, 'used_percent'));
    }

    public function testAnAccountWithoutLimitHasNoGaugeAndNoDivisionByZero(): void
    {
        [$company, $admin] = $this->company();
        $this->catalog()->tradeCreditAccount($company, 0, 0);

        self::assertNull($this->at($this->widget($company, $admin)->data, 'used_percent'));
    }

    /** @return array{CompanyInterface, CompanyMemberInterface} */
    private function company(string $name = 'Acme'): array
    {
        $company = $this->catalog()->company($name);

        return [$company, $this->catalog()->member($company, $this->catalog()->customer('admin-' . strtolower($name), 'Isabelle', 'Admin'), CompanyMemberInterface::ROLE_ADMIN)];
    }

    private function widget(CompanyInterface $company, CompanyMemberInterface $member): InsightsWidget
    {
        $period = new Period(new \DateTimeImmutable('-90 days'), new \DateTimeImmutable('+1 day'));
        $provider = $this->service('tmonier_company_insights.test.bridge.credit', CreditWidgetProvider::class);
        $widget = $provider->provide(new WidgetContext($company, $member, new PeriodComparison(PeriodKey::Custom, $period, $period), new PurchaseFilter(), 'en_US'));
        self::assertSame('credit', $widget->code);

        return $widget;
    }

    private function at(mixed $value, string|int ...$path): mixed
    {
        foreach ($path as $key) {
            self::assertIsArray($value);
            $value = $value[$key];
        }

        return $value;
    }
}
