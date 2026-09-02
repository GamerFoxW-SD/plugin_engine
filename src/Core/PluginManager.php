<?php

declare(strict_types=1);

namespace Engine\Core;

use Engine\Events\EventDispatcher;
use Engine\Exceptions\PluginDependencyException;
use Engine\Exceptions\PluginException;

final class PluginManager
{
    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly PluginLoader $loader,
        private readonly EventDispatcher $events,
    ) {}

    public function discover(): void
    {
        foreach ($this->loader->discover() as $descriptor) {
            $this->registry->add(new PluginRecord($descriptor));
        }
    }

    /**
     * Loads every discovered plugin. A broken plugin is isolated and marked FAILED;
     * it does not prevent unrelated plugins from loading.
     */
    public function loadAll(): void
    {
        foreach ($this->registry->all() as $record) {
            if ($record->state !== PluginState::DISCOVERED) {
                continue;
            }

            try {
                $this->loader->load($record);
            } catch (\Throwable $e) {
                $record->state = PluginState::FAILED;
                $record->error = $e->getMessage();
            }
        }
    }

    public function activate(string $name): void
    {
        $record = $this->registry->get($name);

        if ($record->state === PluginState::ACTIVE) {
            return;
        }

        if ($record->state === PluginState::DISCOVERED) {
            try {
                $this->loader->load($record);
            } catch (\Throwable $e) {
                $record->state = PluginState::FAILED;
                $record->error = $e->getMessage();
                throw new PluginException("Cannot activate plugin '{$name}': {$e->getMessage()}", 0, $e);
            }
        }

        if ($record->state === PluginState::FAILED || $record->instance === null) {
            throw new PluginException("Cannot activate plugin '{$name}': plugin is not loadable.");
        }

        $this->activateDependencies($record, []);

        $context = new PluginContext($this->events, $this, $name);

        try {
            $record->instance->register($context);
            $record->instance->boot($context);
            $record->state = PluginState::ACTIVE;
            $record->error = null;
        } catch (\Throwable $e) {
            $this->events->removeOwner($name);
            $record->state = PluginState::FAILED;
            $record->error = $e->getMessage();
            throw new PluginException("Plugin '{$name}' failed during activation: {$e->getMessage()}", 0, $e);
        }
    }

    public function deactivate(string $name): void
    {
        $record = $this->registry->get($name);

        if ($record->state !== PluginState::ACTIVE || $record->instance === null) {
            return;
        }

        foreach ($this->registry->all() as $other) {
            if ($other->descriptor->name === $name || $other->state !== PluginState::ACTIVE) {
                continue;
            }

            if (array_key_exists($name, $other->descriptor->dependencies)) {
                throw new PluginDependencyException(
                    "Cannot deactivate '{$name}': active plugin '{$other->descriptor->name}' depends on it."
                );
            }
        }

        try {
            $record->instance->shutdown(new PluginContext($this->events, $this, $name));
        } finally {
            $this->events->removeOwner($name);
            $record->state = PluginState::INACTIVE;
        }
    }

    public function isActive(string $name): bool
    {
        return $this->registry->get($name)->state === PluginState::ACTIVE;
    }

    public function get(string $name): PluginRecord
    {
        return $this->registry->get($name);
    }

    /** @return array<string, PluginRecord> */
    public function all(): array
    {
        return $this->registry->all();
    }

    private function activateDependencies(PluginRecord $record, array $stack): void
    {
        $name = $record->descriptor->name;
        if (in_array($name, $stack, true)) {
            throw new PluginDependencyException(
                'Circular plugin dependency: ' . implode(' -> ', [...$stack, $name])
            );
        }

        foreach ($record->descriptor->dependencies as $dependency => $constraint) {
            if (!is_string($dependency) || $dependency === '') {
                throw new PluginDependencyException("Invalid dependency declaration in '{$name}'.");
            }

            if (!$this->registry->has($dependency)) {
                throw new PluginDependencyException("Plugin '{$name}' requires missing plugin '{$dependency}'.");
            }

            $dependencyRecord = $this->registry->get($dependency);

            if ($dependencyRecord->state === PluginState::FAILED) {
                throw new PluginDependencyException(
                    "Plugin '{$name}' requires '{$dependency}', but it failed: {$dependencyRecord->error}"
                );
            }

            if ($dependencyRecord->state === PluginState::DISCOVERED) {
                $this->loader->load($dependencyRecord);
            }

            if ($dependencyRecord->state !== PluginState::ACTIVE) {
                $this->activateDependencies($dependencyRecord, [...$stack, $name]);
                $this->activate($dependency);
            }
        }
    }
}
