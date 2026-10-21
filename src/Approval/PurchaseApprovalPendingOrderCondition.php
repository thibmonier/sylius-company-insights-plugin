<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Approval;

/** Orders held by tmonier/sylius-purchase-approval-plugin until an approver decides. */
final class PurchaseApprovalPendingOrderCondition implements PendingOrderConditionInterface
{
    public function sql(string $orderAlias): string
    {
        return sprintf(
            "EXISTS (SELECT 1 FROM tmonier_purchase_approval_order_approval approval WHERE approval.order_id = %s.id AND approval.state = 'pending')",
            $orderAlias,
        );
    }
}
