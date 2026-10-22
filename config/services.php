<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusCompanyInsightsPlugin\Approval\NoPendingOrderCondition;
use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\DbalOrderSpend;
use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\DbalSiteBreakdown;
use Tmonier\SyliusCompanyInsightsPlugin\Controller\Shop\InsightsController;
use Tmonier\SyliusCompanyInsightsPlugin\Doctrine\CompanyOrderIndexListener;
use Tmonier\SyliusCompanyInsightsPlugin\Export\CsvExporter;
use Tmonier\SyliusCompanyInsightsPlugin\Export\DbalExportLines;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\DbalFilterChoices;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\SelectionFactory;
use Tmonier\SyliusCompanyInsightsPlugin\Indicators\DashboardProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Monthly\DbalMonthlyPurchaseStatistics;
use Tmonier\SyliusCompanyInsightsPlugin\Monthly\MonthlyPurchasesProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodResolver;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\DbalBuyerRanking;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\DbalCategoryRanking;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\DbalProductRanking;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingsProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Security\InsightsVoter;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidgetRegistry;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\DbalPurchaseStatistics;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('tmonier_company_insights.period_resolver', PeriodResolver::class)
        ->args([service('clock'), param('tmonier_company_insights.timezone')])
    ;

    // Replaced by config/services/purchase_approval.php when that plugin is installed.
    $services->set('tmonier_company_insights.pending_order_condition', NoPendingOrderCondition::class);

    $services->set('tmonier_company_insights.sql.counted_order', CountedOrderSql::class)
        ->args([service('tmonier_company_insights.pending_order_condition')])
    ;

    $services->set('tmonier_company_insights.statistics.purchase', DbalPurchaseStatistics::class)
        ->args([
            service('doctrine.dbal.default_connection'),
            service('tmonier_company_insights.sql.counted_order'),
        ])
    ;

    $services->set('tmonier_company_insights.statistics.monthly', DbalMonthlyPurchaseStatistics::class)
        ->args([
            service('doctrine.dbal.default_connection'),
            service('tmonier_company_insights.sql.counted_order'),
        ])
    ;

    $services->set('tmonier_company_insights.monthly_purchases_provider', MonthlyPurchasesProvider::class)
        ->args([
            service('clock'),
            service('tmonier_company_insights.statistics.monthly'),
            param('tmonier_company_insights.timezone'),
        ])
    ;

    foreach (['buyers' => DbalBuyerRanking::class, 'products' => DbalProductRanking::class, 'categories' => DbalCategoryRanking::class] as $name => $class) {
        $services->set('tmonier_company_insights.ranking.' . $name, $class)
            ->args([
                service('doctrine.dbal.default_connection'),
                service('tmonier_company_insights.sql.counted_order'),
                param('tmonier_company_insights.timezone'),
            ])
        ;
    }

    $services->set('tmonier_company_insights.rankings_provider', RankingsProvider::class)
        ->args([
            service('tmonier_company_insights.ranking.buyers'),
            service('tmonier_company_insights.ranking.products'),
            service('tmonier_company_insights.ranking.categories'),
        ])
    ;

    foreach (['sites' => DbalSiteBreakdown::class, 'order_spend' => DbalOrderSpend::class] as $name => $class) {
        $services->set('tmonier_company_insights.breakdown.' . $name, $class)
            ->args([
                service('doctrine.dbal.default_connection'),
                service('tmonier_company_insights.sql.counted_order'),
                param('tmonier_company_insights.timezone'),
            ])
        ;
    }

    $services->set('tmonier_company_insights.breakdown_provider', BreakdownProvider::class)
        ->args([
            service('tmonier_company_insights.breakdown.sites'),
            service('tmonier_company_insights.breakdown.order_spend'),
            tagged_iterator('tmonier_company_insights.order_dimension_provider'),
            service('logger'),
        ])
    ;

    $services->set('tmonier_company_insights.export.lines', DbalExportLines::class)
        ->args([
            service('doctrine.dbal.default_connection'),
            service('tmonier_company_insights.sql.counted_order'),
            param('tmonier_company_insights.timezone'),
        ])
    ;

    $services->set('tmonier_company_insights.export.csv_exporter', CsvExporter::class)
        ->args([
            service('tmonier_company_insights.export.lines'),
            service('translator'),
            tagged_iterator('tmonier_company_insights.order_dimension_provider'),
            tagged_iterator('tmonier_company_insights.order_reference_provider'),
            service('logger'),
            service('clock'),
        ])
    ;

    $services->set('tmonier_company_insights.filter.choices', DbalFilterChoices::class)
        ->args([service('doctrine.dbal.default_connection')])
    ;

    $services->set('tmonier_company_insights.filter.selection_factory', SelectionFactory::class)
        ->args([
            service('tmonier_company_insights.period_resolver'),
            service('tmonier_company_insights.filter.choices'),
        ])
    ;

    $services->set('tmonier_company_insights.widget_registry', InsightsWidgetRegistry::class)
        ->args([
            tagged_iterator('tmonier_company_insights.widget_provider'),
            service('security.authorization_checker'),
            service('logger'),
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
            service('tmonier_company_insights.monthly_purchases_provider'),
            service('tmonier_company_insights.rankings_provider'),
            service('sylius.context.currency'),
            service('tmonier_company_insights.filter.selection_factory'),
            service('tmonier_company_insights.filter.choices'),
            service('tmonier_company_insights.breakdown_provider'),
            service('tmonier_company_insights.export.csv_exporter'),
            service('tmonier_company_insights.widget_registry'),
        ])
        ->call('setContainer', [service(\Psr\Container\ContainerInterface::class)])
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->public()
    ;
};
