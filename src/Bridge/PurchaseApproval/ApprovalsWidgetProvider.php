<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Bridge\PurchaseApproval;

use Doctrine\DBAL\Types\Types;
use Psr\Clock\ClockInterface;
use Sylius\Component\Customer\Model\CustomerInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidget;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidgetProviderInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\WidgetContext;
use Tmonier\SyliusPurchaseApprovalPlugin\Entity\OrderApprovalInterface;
use Tmonier\SyliusPurchaseApprovalPlugin\Repository\OrderApprovalRepositoryInterface;
use Tmonier\SyliusPurchaseApprovalPlugin\Security\ApprovalPermissions;

/**
 * How the approvals go, from the public repository of tmonier/sylius-purchase-approval-plugin: the orders
 * waiting (oldest first), the median time to decide, the share of refusals, and the buyers whose orders
 * are most often submitted to approval (a sign that their limit may be too low). The plugin offers no
 * aggregate for the last three: they are computed here from the approvals of the period (the 2 000 latest
 * at most). The amounts are those of the requests, tax included, as the approvers see them.
 */
final readonly class ApprovalsWidgetProvider implements InsightsWidgetProviderInterface
{
    public const TEMPLATE = '@TmonierSyliusCompanyInsightsPlugin/shop/account/company/insights/widget/approvals.html.twig';

    private const MAX_APPROVALS = 2000;

    private const LISTED = 5;

    public function __construct(
        private OrderApprovalRepositoryInterface $approvals,
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
        $period = $context->periods->current;
        $inPeriod = $this->inPeriod($company, $period);
        $decided = array_values(array_filter($inPeriod, static fn (OrderApprovalInterface $approval): bool => $approval->getDecidedAt() instanceof \DateTimeImmutable && in_array($approval->getState(), [OrderApprovalInterface::STATE_APPROVED, OrderApprovalInterface::STATE_REJECTED], true)));
        $refused = count(array_filter($decided, static fn (OrderApprovalInterface $approval): bool => OrderApprovalInterface::STATE_REJECTED === $approval->getState()));

        return new InsightsWidget($this->code(), $this->title(), self::TEMPLATE, [
            'pending' => $this->pending($company),
            'decided' => count($decided),
            'refused' => $refused,
            'refusal_rate' => ApprovalMetrics::percent($refused, count($decided)),
            'median_seconds' => ApprovalMetrics::median(array_map(static fn (OrderApprovalInterface $approval): int => ($approval->getDecidedAt()?->getTimestamp() ?? 0) - $approval->getCreatedAt()->getTimestamp(), $decided)),
            'buyers' => $this->frequentBuyers($company, $period, $inPeriod),
            'url' => $this->url($company),
        ]);
    }

    /** @return list<OrderApprovalInterface> */
    private function inPeriod(CompanyInterface $company, Period $period): array
    {
        $query = $this->approvals->createListQueryBuilderByCompany($company, false);
        $alias = $query->getRootAliases()[0];
        $query
            ->andWhere(sprintf('%1$s.createdAt >= :insights_from AND %1$s.createdAt < :insights_to', $alias))
            ->setParameter('insights_from', $period->from, Types::DATETIME_IMMUTABLE)
            ->setParameter('insights_to', $period->to, Types::DATETIME_IMMUTABLE)
            ->setMaxResults(self::MAX_APPROVALS)
        ;

        return $this->onlyApprovals($query->getQuery()->getResult());
    }

    /** @return array{count: int, amounts: array<string, int>, items: list<array{number: string, buyer: string, amount: int, currency: string, days: int}>, oldest_days: ?int} */
    private function pending(CompanyInterface $company): array
    {
        $waiting = $this->onlyApprovals($this->approvals->createListQueryBuilderByCompany($company, true)->setMaxResults(self::MAX_APPROVALS)->getQuery()->getResult());
        usort($waiting, static fn (OrderApprovalInterface $a, OrderApprovalInterface $b): int => [$a->getCreatedAt(), $a->getId()] <=> [$b->getCreatedAt(), $b->getId()]);

        $amounts = [];
        foreach ($waiting as $approval) {
            $currency = (string) $approval->getCurrencyCode();
            $amounts[$currency] = ($amounts[$currency] ?? 0) + $approval->getTotal();
        }
        ksort($amounts);
        $items = array_map(fn (OrderApprovalInterface $approval): array => [
            'number' => (string) $approval->getOrder()?->getNumber(),
            'buyer' => $this->name($approval->getOrder()?->getCustomer()),
            'amount' => $approval->getTotal(),
            'currency' => (string) $approval->getCurrencyCode(),
            'days' => $this->daysSince($approval->getCreatedAt()),
        ], array_slice($waiting, 0, self::LISTED));

        return ['count' => count($waiting), 'amounts' => $amounts, 'items' => $items, 'oldest_days' => $items[0]['days'] ?? null];
    }

    /**
     * @param list<OrderApprovalInterface> $approvals
     *
     * @return list<array{name: string, approvals: int, orders: int, rate: int}>
     */
    private function frequentBuyers(CompanyInterface $company, Period $period, array $approvals): array
    {
        $perBuyer = [];
        foreach ($approvals as $approval) {
            $customer = $approval->getOrder()?->getCustomer();
            if (!$customer instanceof CustomerInterface || !is_int($customer->getId())) {
                continue;
            }
            $perBuyer[$customer->getId()] ??= ['name' => $this->name($customer), 'approvals' => 0];
            ++$perBuyer[$customer->getId()]['approvals'];
        }
        if ([] === $perBuyer) {
            return [];
        }

        $orders = $this->orderCounts->perCustomer($company->getId() ?? 0, $period);
        $rows = [];
        foreach ($perBuyer as $customerId => $buyer) {
            $submitted = max($buyer['approvals'], $orders[$customerId] ?? 0);
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
    private function url(CompanyInterface $company): ?string
    {
        if (!$this->authorizationChecker->isGranted(ApprovalPermissions::APPROVAL_LIST, $company)) {
            return null;
        }

        try {
            return $this->urlGenerator->generate('tmonier_purchase_approval_shop_approvals');
        } catch (RoutingException) {
            return null;
        }
    }

    private function name(?CustomerInterface $customer): string
    {
        return $customer instanceof \Sylius\Component\Core\Model\CustomerInterface ? trim(($customer->getFirstName() ?? '') . ' ' . ($customer->getLastName() ?? '')) : '';
    }

    /**
     * @return list<OrderApprovalInterface>
     */
    private function onlyApprovals(mixed $result): array
    {
        return is_array($result) ? array_values(array_filter($result, static fn (mixed $row): bool => $row instanceof OrderApprovalInterface)) : [];
    }
}
