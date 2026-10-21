<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Approval;

/**
 * How to tell that an order waits for an approval, so that it is reported apart and not counted
 * in the spend. Optional port: the default implementation knows no pending order.
 */
interface PendingOrderConditionInterface
{
    /**
     * SQL predicate that is true for an order waiting for approval, on the given alias of the
     * sylius_order table. Null when no approval workflow is installed.
     */
    public function sql(string $orderAlias): ?string;
}
