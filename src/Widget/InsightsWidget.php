<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Widget;

final readonly class InsightsWidget
{
    /**
     * @param string $template Twig template that draws the widget; it receives `widget`, `data` and `locale`
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $code,
        public string $title,
        public string $template,
        public array $data = [],
        public bool $unavailable = false,
    ) {
    }

    /** The widget whose figures could not be computed: a short notice replaces them. */
    public static function unavailable(string $code, string $title): self
    {
        return new self($code, $title, '', [], true);
    }
}
