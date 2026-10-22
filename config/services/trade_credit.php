<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusCompanyInsightsPlugin\Bridge\TradeCredit\CreditWidgetProvider;

// Loaded only when tmonier/sylius-trade-credit-plugin is installed (see the extension).
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('tmonier_company_insights.bridge.trade_credit.widget', CreditWidgetProvider::class)
        ->args([
            service('tmonier_trade_credit.statement_builder'),
            service('clock'),
            service('router'),
        ])
        ->tag('tmonier_company_insights.widget_provider', ['priority' => 10])
    ;
};
