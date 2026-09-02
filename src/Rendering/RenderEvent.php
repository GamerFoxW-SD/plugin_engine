<?php

declare(strict_types=1);

namespace Engine\Rendering;

final class RenderEvent
{
    /** @var list<string> */
    private array $styles = [];

    /** @var list<string> */
    private array $scripts = [];

    /** @var list<string> */
    private array $sections = [];

    private ?string $title = null;

    public function __construct(private readonly string $page = 'default') {}

    public function page(): string { return $this->page; }

    public function title(string $title): void { $this->title = $title; }
    public function getTitle(): ?string { return $this->title; }

    public function addStyle(string $href): void
    {
        if (!in_array($href, $this->styles, true)) {
            $this->styles[] = $href;
        }
    }

    public function addScript(string $src): void
    {
        if (!in_array($src, $this->scripts, true)) {
            $this->scripts[] = $src;
        }
    }

    public function addSection(string $html): void
    {
        $this->sections[] = $html;
    }

    /** @return list<string> */
    public function styles(): array { return $this->styles; }

    /** @return list<string> */
    public function scripts(): array { return $this->scripts; }

    /** @return list<string> */
    public function sections(): array { return $this->sections; }
}
