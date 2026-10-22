<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusCompanyInsightsPlugin\Bridge\PurchaseApproval\ApprovalsWidgetProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Bridge\PurchaseApproval\DbalApprovalStatistics;
use Tmonier\SyliusCompanyInsightsPlugin\Bridge\PurchaseApproval\SubmittedOrderCounts;

// Loaded only when tmonier/sylius-purchase-approval-plugin is installed (see the extension).
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('tmonier_company_insights.bridge.purchase_approval.submitted_orders', SubmittedOrderCounts::class)
        ->args([
            service('doctrine.dbal.default_connection'),
            service('tmonier_company_insights.sql.counted_order'),
        ])
    ;

    $services->set('tmonier_company_insights.bridge.purchase_approval.statistics', DbalApprovalStatistics::class)
        ->args([
            service('doctrine.dbal.default_connection'),
            service('tmonier_company_insights.sql.counted_order'),
        ])
    ;

    $services->set('tmonier_company_insights.bridge.purchase_approval.widget', ApprovalsWidgetProvider::class)
        ->args([
            service('tmonier_company_insights.bridge.purchase_approval.statistics'),
            service('tmonier_company_insights.bridge.purchase_approval.submitted_orders'),
            service('clock'),
            service('router'),
            service('security.authorization_checker'),
        ])
        ->tag('tmonier_company_insights.widget_provider', ['priority' => 20])
    ;
};
