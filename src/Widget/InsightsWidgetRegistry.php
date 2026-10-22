<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Widget;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class InsightsWidgetRegistry
{
    private LoggerInterface $logger;

    /** @param iterable<InsightsWidgetProviderInterface> $providers */
    public function __construct(
        private iterable $providers,
        private AuthorizationCheckerInterface $authorizationChecker,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /** @return list<InsightsWidget> */
    public function widgets(WidgetContext $context): array
    {
        $widgets = [];
        foreach ($this->providers as $provider) {
            $permission = $provider->permission();
            if (null !== $permission && !$this->authorizationChecker->isGranted($permission, $context->company)) {
                continue;
            }
            $widget = $this->widget($provider, $context);
            if ($widget instanceof InsightsWidget) {
                $widgets[] = $widget;
            }
        }

        return $widgets;
    }

    private function widget(InsightsWidgetProviderInterface $provider, WidgetContext $context): ?InsightsWidget
    {
        try {
            return $provider->provide($context);
        } catch (\Throwable $exception) {
            // Class and widget only: the message of a companion plugin may carry names or amounts.
            $this->logger->error('Company insights: a widget could not be computed.', ['widget' => $provider->code(), 'exception_class' => $exception::class]);

            return InsightsWidget::unavailable($provider->code(), $provider->title());
        }
    }
}
