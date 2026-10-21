<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusCompanyInsightsPlugin\Approval\NoPendingOrderCondition;
use Tmonier\SyliusCompanyInsightsPlugin\Controller\Shop\InsightsController;
use Tmonier\SyliusCompanyInsightsPlugin\Doctrine\CompanyOrderIndexListener;
use Tmonier\SyliusCompanyInsightsPlugin\Indicators\DashboardProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodResolver;
use Tmonier\SyliusCompanyInsightsPlugin\Security\InsightsVoter;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\DbalPurchaseStatistics;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('tmonier_company_insights.period_resolver', PeriodResolver::class)
        ->args([service('clock')])
    ;

    // Replaced by config/services/purchase_approval.php when that plugin is installed.
    $services->set('tmonier_company_insights.pending_order_condition', NoPendingOrderCondition::class);

    $services->set('tmonier_company_insights.statistics.purchase', DbalPurchaseStatistics::class)
        ->args([
            service('doctrine.dbal.default_connection'),
            service('tmonier_company_insights.pending_order_condition'),
        ])
    ;

    $services->set('tmonier_company_insights.dashboard_provider', DashboardProvider::class)
        ->args([
            service('tmonier_company_insights.period_resolver'),
            service('tmonier_company_insights.statistics.purchase'),
        ])
    ;

    $services->set('tmonier_company_insights.voter.insights', InsightsVoter::class)
        ->args([service('tmonier_company_accounts.current_member_resolver')])
        ->tag('security.voter')
    ;

    $services->set('tmonier_company_insights.doctrine.company_order_index', CompanyOrderIndexListener::class)
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata'])
    ;

    $services->set('tmonier_company_insights.controller.shop.insights', InsightsController::class)
        ->args([
            service('tmonier_company_accounts.current_member_resolver'),
            service('tmonier_company_insights.dashboard_provider'),
            service('sylius.context.currency'),
        ])
        ->call('setContainer', [service(\Psr\Container\ContainerInterface::class)])
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->public()
    ;
};
