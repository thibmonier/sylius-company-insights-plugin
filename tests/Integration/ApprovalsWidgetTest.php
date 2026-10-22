<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Bridge\PurchaseApproval\ApprovalsWidgetProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\PurchaseFilter;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodComparison;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodKey;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidget;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\WidgetContext;

final class ApprovalsWidgetTest extends KernelTestCase
{
    use IntegrationTestTrait;

    public function testAnOrderWaitingForFourDaysIsCountedWithItsAmountAndItsAge(): void
    {
        [$company, $admin] = $this->company();
        $claire = $this->buyer($company, 'Claire', 'Martin');
        $this->catalog()->pendingApproval($this->catalog()->order($claire, 25_000, '-4 days'), createdAt: '-4 days');

        $data = $this->widget($company, $admin)->data;

        self::assertSame(1, $this->at($data, 'pending', 'count'));
        self::assertSame(['EUR' => 25_000], $this->at($data, 'pending', 'amounts'));
        self::assertSame(4, $this->at($data, 'pending', 'oldest_days'));
        self::assertSame('Claire Martin', $this->at($data, 'pending', 'items', 0, 'buyer'));
        self::assertSame(4, $this->at($data, 'pending', 'items', 0, 'days'));
        self::assertNull($this->at($data, 'url'), 'Without a signed-in approver there is no link to the approvals.');
    }

    public function testTheOldestOrderIsFirst(): void
    {
        [$company, $admin] = $this->company();
        $claire = $this->buyer($company, 'Claire', 'Martin');
        foreach ([2, 9, 5] as $days) {
            $this->catalog()->pendingApproval($this->catalog()->order($claire, 10_000, sprintf('-%d days', $days)), createdAt: sprintf('-%d days', $days));
        }

        $data = $this->widget($company, $admin)->data;

        self::assertSame(3, $this->at($data, 'pending', 'count'));
        self::assertSame([9, 5, 2], $this->column($this->at($data, 'pending', 'items'), 'days'));
        self::assertSame(9, $this->at($data, 'pending', 'oldest_days'));
    }

    public function testTheMedianTimeToDecideAndTheRefusalRateComeFromTheDecisionsOfThePeriod(): void
    {
        [$company, $admin] = $this->company();
        $claire = $this->buyer($company, 'Claire', 'Martin');
        $decisions = [['approved', '+2 hours'], ['approved', '+10 hours'], ['rejected', '+4 hours']];
        foreach ($decisions as [$state, $delay]) {
            $order = $this->catalog()->order($claire, 10_000, '-3 days');
            $this->catalog()->pendingApproval($order, $state, '-3 days', (new \DateTimeImmutable('-3 days'))->modify($delay)->format('Y-m-d H:i:s'));
        }

        $data = $this->widget($company, $admin)->data;

        self::assertSame(4 * 3600, $this->at($data, 'median_seconds'));
        self::assertSame(3, $this->at($data, 'decided'));
        self::assertSame(1, $this->at($data, 'refused'));
        self::assertSame(33, $this->at($data, 'refusal_rate'));
        self::assertSame(0, $this->at($data, 'pending', 'count'));
    }

    public function testExpiredAndCancelledRequestsAreNotDecisionsOfAnApprover(): void
    {
        [$company, $admin] = $this->company();
        $claire = $this->buyer($company, 'Claire', 'Martin');
        $this->catalog()->pendingApproval($this->catalog()->order($claire, 10_000, '-3 days'), 'approved', '-3 days', '-3 days +1 hour');
        $this->catalog()->pendingApproval($this->catalog()->order($claire, 10_000, '-3 days'), 'expired', '-3 days', '-1 day');
        $this->catalog()->pendingApproval($this->catalog()->order($claire, 10_000, '-3 days'), 'cancelled', '-3 days', '-2 days');

        $data = $this->widget($company, $admin)->data;

        self::assertSame(1, $this->at($data, 'decided'));
        self::assertSame(0, $this->at($data, 'refusal_rate'));
        self::assertSame(3600, $this->at($data, 'median_seconds'));
    }

    public function testABuyerWhoseOrdersMostlyNeedAnApprovalIsListedWithTheShare(): void
    {
        [$company, $admin] = $this->company();
        $claire = $this->buyer($company, 'Claire', 'Martin');
        $paul = $this->buyer($company, 'Paul', 'Durand');
        for ($index = 0; $index < 4; ++$index) {
            $this->catalog()->pendingApproval($this->catalog()->order($claire, 10_000, '-10 days'), 'approved', '-10 days', '-9 days');
        }
        $this->catalog()->order($claire, 1_000, '-8 days');
        $this->catalog()->pendingApproval($this->catalog()->order($paul, 10_000, '-7 days'), 'approved', '-7 days', '-6 days');
        foreach (range(1, 9) as $ignored) {
            $this->catalog()->order($paul, 1_000, '-6 days');
        }

        $buyers = $this->at($this->widget($company, $admin)->data, 'buyers');

        self::assertSame(['Claire Martin', 'Paul Durand'], $this->column($buyers, 'name'));
        self::assertSame([4, 1], $this->column($buyers, 'approvals'));
        self::assertSame([5, 10], $this->column($buyers, 'orders'));
        self::assertSame([80, 10], $this->column($buyers, 'rate'));
    }

    public function testNothingOfAnotherCompanyAppears(): void
    {
        [$acme, $isabelle] = $this->company();
        [$other] = $this->company('TechParts');
        $marc = $this->buyer($other, 'Marc', 'Autre');
        $this->catalog()->pendingApproval($this->catalog()->order($marc, 99_000, '-4 days'), createdAt: '-4 days');
        $this->catalog()->pendingApproval($this->catalog()->order($marc, 99_000, '-4 days'), 'rejected', '-4 days', '-3 days');

        $data = $this->widget($acme, $isabelle)->data;

        self::assertSame(0, $this->at($data, 'pending', 'count'));
        self::assertSame([], $this->at($data, 'pending', 'items'));
        self::assertSame(0, $this->at($data, 'decided'));
        self::assertSame([], $this->at($data, 'buyers'));
        self::assertNotContains('Autre', $this->column($this->at($data, 'buyers'), 'name'));
    }

    public function testRequestsOutsideThePeriodAreNotMeasuredButWhatWaitsIsAlwaysShown(): void
    {
        [$company, $admin] = $this->company();
        $claire = $this->buyer($company, 'Claire', 'Martin');
        $this->catalog()->pendingApproval($this->catalog()->order($claire, 10_000, '-200 days'), 'rejected', '-200 days', '-199 days');
        $this->catalog()->pendingApproval($this->catalog()->order($claire, 10_000, '-40 days'), createdAt: '-40 days');

        $data = $this->widget($company, $admin, new Period(new \DateTimeImmutable('-30 days'), new \DateTimeImmutable('+1 day')))->data;

        self::assertSame(0, $this->at($data, 'decided'));
        self::assertNull($this->at($data, 'refusal_rate'));
        self::assertSame(1, $this->at($data, 'pending', 'count'));
    }

    public function testWithoutAnyApprovalThereIsNothingToMeasureButTheWidgetIsThere(): void
    {
        [$company, $admin] = $this->company();

        $widget = $this->widget($company, $admin);

        self::assertFalse($widget->unavailable);
        self::assertSame(0, $this->at($widget->data, 'pending', 'count'));
        self::assertNull($this->at($widget->data, 'pending', 'oldest_days'));
        self::assertNull($this->at($widget->data, 'median_seconds'));
        self::assertNull($this->at($widget->data, 'refusal_rate'));
        self::assertSame([], $this->at($widget->data, 'buyers'));
    }

    /** @return array{CompanyInterface, CompanyMemberInterface} */
    private function company(string $name = 'Acme'): array
    {
        $company = $this->catalog()->company($name);

        return [$company, $this->catalog()->member($company, $this->catalog()->customer('admin-' . strtolower($name), 'Isabelle', 'Admin'), CompanyMemberInterface::ROLE_ADMIN)];
    }

    private function buyer(CompanyInterface $company, string $first, string $last): \Sylius\Component\Core\Model\CustomerInterface
    {
        $customer = $this->catalog()->customer(strtolower($first), $first, $last);
        $this->catalog()->member($company, $customer);

        return $customer;
    }

    private function widget(CompanyInterface $company, CompanyMemberInterface $member, ?Period $period = null): InsightsWidget
    {
        $period ??= new Period(new \DateTimeImmutable('-90 days'), new \DateTimeImmutable('+1 day'));
        $provider = $this->service('tmonier_company_insights.test.bridge.approvals', ApprovalsWidgetProvider::class);
        $widget = $provider->provide(new WidgetContext($company, $member, new PeriodComparison(PeriodKey::Custom, $period, $period), new PurchaseFilter(), 'en_US'));
        self::assertSame('approvals', $widget->code);

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

    /** @return list<mixed> */
    private function column(mixed $rows, string $key): array
    {
        self::assertIsArray($rows);

        return array_column($rows, $key);
    }
}
