<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Approval;

final class NoPendingOrderCondition implements PendingOrderConditionInterface
{
    public function sql(string $orderAlias): ?string
    {
        return null;
    }
}
