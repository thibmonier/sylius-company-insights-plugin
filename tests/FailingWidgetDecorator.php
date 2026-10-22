<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin;

use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidget;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidgetProviderInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\WidgetContext;

/**
 * Test application only: lets a functional test make the widget of a companion plugin raise, as its
 * public service would when it fails (the switch is off by default).
 */
final class FailingWidgetDecorator implements InsightsWidgetProviderInterface
{
    /** @var list<string> codes of the widgets that raise */
    public static array $failing = [];

    public function __construct(private readonly InsightsWidgetProviderInterface $inner)
    {
    }

    public function code(): string
    {
        return $this->inner->code();
    }

    public function title(): string
    {
        return $this->inner->title();
    }

    public function permission(): ?string
    {
        return $this->inner->permission();
    }

    public function provide(WidgetContext $context): ?InsightsWidget
    {
        if (in_array($this->inner->code(), self::$failing, true)) {
            throw new \RuntimeException('Claire Martin (claire@acme.example) owes 1200 EUR: the service is down.');
        }

        return $this->inner->provide($context);
    }
}
