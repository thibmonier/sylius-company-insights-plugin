<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusCompanyInsightsPlugin\Approval\PurchaseApprovalPendingOrderCondition;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('tmonier_company_insights.pending_order_condition', PurchaseApprovalPendingOrderCondition::class)
    ;
};
