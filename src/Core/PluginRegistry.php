<?php

declare(strict_types=1);

namespace Engine\Core;

final class PluginRegistry
{
    /** @var array<string, PluginRecord> */
    private array $records = [];

    public function add(PluginRecord $record): void
    {
        $name = $record->descriptor->name;
        if (isset($this->records[$name])) {
            throw new \RuntimeException("Duplicate plugin: {$name}");
        }
        $this->records[$name] = $record;
    }

    public function has(string $name): bool { return isset($this->records[$name]); }

    public function get(string $name): PluginRecord
    {
        return $this->records[$name] ?? throw new \RuntimeException("Plugin not found: {$name}");
    }

    /** @return array<string, PluginRecord> */
    public function all(): array { return $this->records; }
}
