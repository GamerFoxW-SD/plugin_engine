<?php

declare(strict_types=1);

namespace Engine\Core;

use Engine\Events\EventDispatcher;

final class Engine
{
    private bool $booted = false;

    public function __construct(private readonly string $pluginsPath)
    {
        $this->events = new EventDispatcher();
        $this->registry = new PluginRegistry();
        $this->loader = new PluginLoader($pluginsPath);
        $this->pluginManager = new PluginManager($this->registry, $this->loader, $this->events);
    }

    private EventDispatcher $events;
    private PluginRegistry $registry;
    private PluginLoader $loader;
    private PluginManager $pluginManager;

    public function boot(): void
    {
        if ($this->booted) return;
        $this->pluginManager->discover();
        $this->pluginManager->loadAll();
        $this->booted = true;
    }

    public function plugins(): PluginManager { return $this->pluginManager; }
    public function events(): EventDispatcher { return $this->events; }
}
