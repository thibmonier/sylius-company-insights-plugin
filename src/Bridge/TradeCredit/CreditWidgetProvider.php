<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Bridge\TradeCredit;

use Psr\Clock\ClockInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidget;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidgetProviderInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\WidgetContext;
use Tmonier\SyliusTradeCreditPlugin\Ageing\AgeingBuckets;
use Tmonier\SyliusTradeCreditPlugin\Statement\Statement;
use Tmonier\SyliusTradeCreditPlugin\Statement\StatementBuilder;
use Tmonier\SyliusTradeCreditPlugin\Statement\StatementItem;

/**
 * Where the credit of the company stands, from the statement of tmonier/sylius-trade-credit-plugin (its public
 * `StatementBuilder`): limit, amount used, available, overdue amount and invoices, next due date. Nothing
 * is read from the tables of the plugin. A company without a payment-on-account line gets a notice.
 */
final readonly class CreditWidgetProvider implements InsightsWidgetProviderInterface
{
    public const TEMPLATE = '@TmonierSyliusCompanyInsightsPlugin/shop/account/company/insights/widget/credit.html.twig';

    public function __construct(
        private StatementBuilder $statementBuilder,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function code(): string
    {
        return 'credit';
    }

    public function title(): string
    {
        return 'tmonier_company_insights.widget.credit.title';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function provide(WidgetContext $context): InsightsWidget
    {
        $customer = $context->member->getCustomer();
        $statement = $customer instanceof \Sylius\Component\Core\Model\CustomerInterface ? $this->statementBuilder->forCustomer($customer) : null;
        if (!$statement instanceof Statement) {
            return new InsightsWidget($this->code(), $this->title(), self::TEMPLATE, ['account' => false]);
        }

        $position = $statement->position;
        $overdue = array_values(array_filter($statement->items, static fn (StatementItem $item): bool => $item->overdue));
        $upcoming = array_values(array_filter($statement->items, static fn (StatementItem $item): bool => !$item->overdue));

        return new InsightsWidget($this->code(), $this->title(), self::TEMPLATE, [
            'account' => true,
            'currency' => (string) $statement->account->getCurrencyCode(),
            'limit' => $position->creditLimit,
            'used' => $position->committedAmount + $position->externalCommittedAmount,
            'available' => max(0, $position->getAvailableAmount()),
            'used_percent' => $position->creditLimit > 0 ? min(100, (int) round(($position->committedAmount + $position->externalCommittedAmount) * 100 / $position->creditLimit)) : null,
            'overdue_amount' => $statement->getOverdueAmount(),
            'overdue_count' => count($overdue),
            'oldest_overdue_days' => [] === $overdue ? null : max(array_map(fn (StatementItem $item): int => max(0, AgeingBuckets::daysPastDue($item->dueDate, $this->clock->now())), $overdue)),
            'next_due' => [] === $upcoming ? null : ['date' => $upcoming[0]->dueDate, 'amount' => $upcoming[0]->amount, 'count' => count(array_filter($upcoming, static fn (StatementItem $item): bool => $item->dueDate == $upcoming[0]->dueDate))],
            'on_hold' => $statement->account->isCreditHold(),
            'url' => $this->url(),
        ]);
    }

    private function url(): ?string
    {
        try {
            return $this->urlGenerator->generate('tmonier_trade_credit_shop_statement');
        } catch (RoutingException) {
            return null;
        }
    }
}
