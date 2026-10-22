<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\OrderDimensionProviderInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\OrderReferenceProviderInterface;
use Tmonier\SyliusPurchaseApprovalPlugin\Entity\OrderApprovalInterface;

final class TmonierSyliusCompanyInsightsExtension extends Extension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    private const PURCHASE_APPROVAL_PARAMETER = 'tmonier_company_insights.purchase_approval_bridge';

    private const TIMEZONE_PARAMETER = 'tmonier_company_insights.timezone';

    public function load(array $configs, ContainerBuilder $container): void
    {
        // Timezone of the months of the report; null follows the default timezone of PHP.
        if (!$container->hasParameter(self::TIMEZONE_PARAMETER)) {
            $container->setParameter(self::TIMEZONE_PARAMETER, null);
        }

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');

        $container->registerForAutoconfiguration(OrderDimensionProviderInterface::class)->addTag('tmonier_company_insights.order_dimension_provider');
        $container->registerForAutoconfiguration(OrderReferenceProviderInterface::class)->addTag('tmonier_company_insights.order_reference_provider');

        // Optional: orders held by tmonier/sylius-purchase-approval-plugin are reported apart.
        if (true === ($container->hasParameter(self::PURCHASE_APPROVAL_PARAMETER) ? $container->getParameter(self::PURCHASE_APPROVAL_PARAMETER) : false)) {
            $loader->load('services/purchase_approval.php');
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Known at prepend time only, when all the extensions of the application are registered.
        $container->setParameter(
            self::PURCHASE_APPROVAL_PARAMETER,
            $container->hasExtension('tmonier_sylius_purchase_approval') && interface_exists(OrderApprovalInterface::class),
        );

        $this->prependDoctrineMigrations($container);
    }

    public function getAlias(): string
    {
        return 'tmonier_sylius_company_insights';
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Tmonier\SyliusCompanyInsightsPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@TmonierSyliusCompanyInsightsPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
            'Tmonier\SyliusCompanyAccountsPlugin\Migrations',
        ];
    }
}
