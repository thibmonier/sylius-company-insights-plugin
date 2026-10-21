<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * Test application only: services the tests read directly (removed from the container
 * otherwise, as nothing else uses them).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->alias('tmonier_company_insights.test.statistics', 'tmonier_company_insights.statistics.purchase')->public();
    $services->alias('tmonier_company_insights.test.dashboard_provider', 'tmonier_company_insights.dashboard_provider')->public();
    $services->alias('tmonier_company_insights.test.rankings_provider', 'tmonier_company_insights.rankings_provider')->public();
};
