<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Tmonier\SyliusCompanyInsightsPlugin\DependencyInjection\TmonierSyliusCompanyInsightsExtension;

/**
 * The widgets of the companion plugins exist only when the companion is installed: without it the
 * container has no such service, so the dashboard has no such widget and nothing fails.
 */
final class CompanionBridgesTest extends TestCase
{
    private const APPROVALS = 'tmonier_company_insights.bridge.purchase_approval.widget';

    private const CREDIT = 'tmonier_company_insights.bridge.trade_credit.widget';

    public function testWithoutAnyCompanionThereIsNoCompanionWidgetButTheRegistryStays(): void
    {
        $container = $this->container();

        self::assertFalse($container->hasDefinition(self::APPROVALS));
        self::assertFalse($container->hasDefinition(self::CREDIT));
        self::assertTrue($container->hasDefinition('tmonier_company_insights.widget_registry'));
        self::assertFalse($container->getParameter('tmonier_company_insights.purchase_approval_bridge'));
        self::assertFalse($container->getParameter('tmonier_company_insights.trade_credit_bridge'));
    }

    public function testOnlyTheInstalledCompanionHasItsWidget(): void
    {
        $withApproval = $this->container('tmonier_sylius_purchase_approval');
        $withCredit = $this->container('tmonier_sylius_trade_credit');

        self::assertTrue($withApproval->hasDefinition(self::APPROVALS));
        self::assertFalse($withApproval->hasDefinition(self::CREDIT));
        self::assertFalse($withCredit->hasDefinition(self::APPROVALS));
        self::assertTrue($withCredit->hasDefinition(self::CREDIT));
    }

    public function testBothCompanionsGiveBothWidgetsWithTheirOrder(): void
    {
        $container = $this->container('tmonier_sylius_purchase_approval', 'tmonier_sylius_trade_credit');

        self::assertSame([['priority' => 20]], $container->getDefinition(self::APPROVALS)->getTag('tmonier_company_insights.widget_provider'));
        self::assertSame([['priority' => 10]], $container->getDefinition(self::CREDIT)->getTag('tmonier_company_insights.widget_provider'));
    }

    public function testAnApplicationWidgetIsAutoconfigured(): void
    {
        $container = $this->container();

        self::assertArrayHasKey(\Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidgetProviderInterface::class, $container->getAutoconfiguredInstanceof());
    }

    private function container(string ...$companions): ContainerBuilder
    {
        $container = new ContainerBuilder();
        foreach ($companions as $alias) {
            $container->registerExtension(new class($alias) extends Extension {
                public function __construct(private readonly string $alias)
                {
                }

                public function load(array $configs, ContainerBuilder $container): void
                {
                }

                public function getAlias(): string
                {
                    return $this->alias;
                }
            });
        }
        $extension = new TmonierSyliusCompanyInsightsExtension();
        $extension->prepend($container);
        $extension->load([], $container);

        return $container;
    }
}
