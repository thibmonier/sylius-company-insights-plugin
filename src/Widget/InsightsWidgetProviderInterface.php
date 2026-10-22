<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Widget;

/**
 * A block of the purchasing dashboard supplied by another plugin or by the application (the bridges for
 * purchase approval and trade credit are two of them). Tag the service `tmonier_company_insights.widget_provider`
 * (autoconfigured when the service implements this interface); a plugin that is not installed has no
 * provider, so its widget is simply absent.
 *
 * Figures come from the public services of the plugin the widget talks about, never from its tables, and
 * are those of the company of the signed-in member (`WidgetContext`), never of the request.
 */
interface InsightsWidgetProviderInterface
{
    /** Stable identifier of the widget (letters, digits, dashes). */
    public function code(): string;

    /** Translation key of the title, also shown when the widget is unavailable. */
    public function title(): string;

    /**
     * A security attribute checked against the company before the widget is computed (null: the permission
     * of the dashboard is enough).
     */
    public function permission(): ?string;

    /** The widget, or null when there is nothing to show. An exception makes the widget "unavailable", it never breaks the page. */
    public function provide(WidgetContext $context): ?InsightsWidget;
}
