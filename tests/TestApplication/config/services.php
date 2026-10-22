<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tests\Tmonier\SyliusCompanyInsightsPlugin\FailingWidgetDecorator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * Test application only: services the tests read directly (removed from the container
 * otherwise, as nothing else uses them).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->alias('tmonier_company_insights.test.statistics', 'tmonier_company_insights.statistics.purchase')->public();
    $services->alias('tmonier_company_insights.test.dashboard_provider', 'tmonier_company_insights.dashboard_provider')->public();
    $services->alias('tmonier_company_insights.test.rankings_provider', 'tmonier_company_insights.rankings_provider')->public();
    $services->alias('tmonier_company_insights.test.breakdown.sites', 'tmonier_company_insights.breakdown.sites')->public();
    $services->alias('tmonier_company_insights.test.breakdown.order_spend', 'tmonier_company_insights.breakdown.order_spend')->public();
    $services->alias('tmonier_company_insights.test.export.lines', 'tmonier_company_insights.export.lines')->public();
    $services->alias('tmonier_company_insights.test.bridge.approvals', 'tmonier_company_insights.test.failing.purchase_approval.inner')->public();
    $services->alias('tmonier_company_insights.test.bridge.credit', 'tmonier_company_insights.test.failing.trade_credit.inner')->public();
    foreach (['purchase_approval', 'trade_credit'] as $bridge) {
        $services->set('tmonier_company_insights.test.failing.' . $bridge, FailingWidgetDecorator::class)
            ->decorate('tmonier_company_insights.bridge.' . $bridge . '.widget')
            ->args([service('.inner')])
        ;
    }
};
