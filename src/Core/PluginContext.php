<?php

declare(strict_types=1);

namespace Engine\Core;

use Engine\Events\EventDispatcher;
use Engine\Rendering\RenderEvent;

final readonly class PluginContext
{
    public function __construct(
        private EventDispatcher $events,
        private PluginManager $plugins,
        private string $pluginName,
    ) {}

    public function events(): EventDispatcher { return $this->events; }
    public function plugins(): PluginManager { return $this->plugins; }
    public function pluginName(): string { return $this->pluginName; }

    public function listen(string $eventClass, callable $listener): string
    {
        return $this->events->listen($eventClass, $listener, $this->pluginName);
    }

    public function render(RenderEvent $event): void
    {
        $this->events->dispatch($event);
    }
}
