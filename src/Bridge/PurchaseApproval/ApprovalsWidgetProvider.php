<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Bridge\PurchaseApproval;

use Psr\Clock\ClockInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidget;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidgetProviderInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\WidgetContext;
use Tmonier\SyliusPurchaseApprovalPlugin\Security\ApprovalPermissions;

/**
 * How the approvals go, from the table of tmonier/sylius-purchase-approval-plugin: the orders waiting
 * (oldest first), the median time to decide, the share of refusals, and the buyers whose orders are most
 * often submitted to approval (a sign that their limit may be too low). The plugin offers no aggregate for
 * these: they are SQL aggregates ({@see DbalApprovalStatistics}), with no limit, for the company of the
 * signed-in member only. The amounts are tax exclusive like the rest of the dashboard, and the period is
 * the one of the checkout of the order, like the orders counted in the indicators.
 */
final readonly class ApprovalsWidgetProvider implements InsightsWidgetProviderInterface
{
    public const TEMPLATE = '@TmonierSyliusCompanyInsightsPlugin/shop/account/company/insights/widget/approvals.html.twig';

    private const LISTED = 5;

    public function __construct(
        private DbalApprovalStatistics $statistics,
        private SubmittedOrderCounts $orderCounts,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urlGenerator,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function code(): string
    {
        return 'approvals';
    }

    public function title(): string
    {
        return 'tmonier_company_insights.widget.approvals.title';
    }

    /** The dashboard is for the administrators: they see the figures; only the link depends on the rules of purchase approval. */
    public function permission(): ?string
    {
        return null;
    }

    public function provide(WidgetContext $context): InsightsWidget
    {
        $company = $context->company;
        $companyId = $company->getId() ?? 0;
        $period = $context->periods->current;
        ['decided' => $decided, 'refused' => $refused] = $this->statistics->decisions($companyId, $period);

        return new InsightsWidget($this->code(), $this->title(), self::TEMPLATE, [
            'pending' => $this->pending($companyId),
            'decided' => $decided,
            'refused' => $refused,
            'refusal_rate' => ApprovalMetrics::percent($refused, $decided),
            'median_seconds' => $this->statistics->medianSecondsToDecide($companyId, $period, $decided),
            'buyers' => $this->frequentBuyers($companyId, $period),
            'url' => $this->url($company, $context->localeCode),
        ]);
    }

    /** @return array{count: int, amounts: array<string, int>, items: list<array{number: string, buyer: string, amount: int, currency: string, days: int}>, oldest_days: ?int} */
    private function pending(int $companyId): array
    {
        $totals = $this->statistics->pendingTotals($companyId);
        $items = array_map(fn (array $item): array => [
            'number' => $item['number'],
            'buyer' => $item['buyer'],
            'amount' => $item['amount'],
            'currency' => $item['currency'],
            'days' => $this->daysSince($item['createdAt']),
        ], $totals['count'] > 0 ? $this->statistics->oldestPending($companyId, self::LISTED) : []);

        return ['count' => $totals['count'], 'amounts' => $totals['amounts'], 'items' => $items, 'oldest_days' => $items[0]['days'] ?? null];
    }

    /** @return list<array{name: string, approvals: int, orders: int, rate: int}> */
    private function frequentBuyers(int $companyId, Period $period): array
    {
        $perBuyer = $this->statistics->approvalsPerBuyer($companyId, $period);
        if ([] === $perBuyer) {
            return [];
        }

        $orders = $this->orderCounts->perCustomer($companyId, $period);
        $rows = [];
        foreach ($perBuyer as $buyer) {
            $submitted = max($buyer['approvals'], $orders[$buyer['customerId']] ?? 0);
            $rows[] = ['name' => $buyer['name'], 'approvals' => $buyer['approvals'], 'orders' => $submitted, 'rate' => ApprovalMetrics::percent($buyer['approvals'], $submitted) ?? 0];
        }
        usort($rows, static fn (array $a, array $b): int => [$b['rate'], $b['approvals'], $a['name']] <=> [$a['rate'], $a['approvals'], $b['name']]);

        return array_slice($rows, 0, self::LISTED);
    }

    private function daysSince(\DateTimeImmutable $date): int
    {
        return max(0, intdiv($this->clock->now()->getTimestamp() - $date->getTimestamp(), 86400));
    }

    /** The list of approvals, when purchase approval lets this member open it (by default its approvers only). */
    private function url(CompanyInterface $company, string $localeCode): ?string
    {
        if (!$this->authorizationChecker->isGranted(ApprovalPermissions::APPROVAL_LIST, $company)) {
            return null;
        }

        try {
            return $this->urlGenerator->generate('tmonier_purchase_approval_shop_approvals', ['_locale' => $localeCode]);
        } catch (RoutingException) {
            return null;
        }
    }
}
