<?php

declare(strict_types=1);

namespace Engine\Core;

use Engine\Contracts\PluginInterface;
use Engine\Exceptions\PluginException;

final class PluginLoader
{
    public function __construct(
        private readonly string $pluginsPath
    ) {}

    /**
     * @return list<PluginDescriptor>
     */
    public function discover(): array
    {
        if (!is_dir($this->pluginsPath)) {
            return [];
        }

        $descriptors = [];

        foreach (scandir($this->pluginsPath) ?: [] as $directory) {
            if ($directory === '.' || $directory === '..') {
                continue;
            }

            $pluginDirectory = $this->pluginsPath
                . DIRECTORY_SEPARATOR
                . $directory;

            $manifest = $pluginDirectory
                . DIRECTORY_SEPARATOR
                . 'plugin.json';

            if (is_file($manifest)) {
                $descriptors[] = PluginDescriptor::fromFile($manifest);
            }
        }

        return $descriptors;
    }

    public function load(PluginRecord $record): void
    {
        $descriptor = $record->descriptor;

        $class = $descriptor->entryClass();

        /*
         * A plugin saját src könyvtárában az entry osztály fájlneve
         * az entry mező alapján kerül meghatározásra.
         *
         * Például:
         *
         * namespace: Theme
         * entry: ThemePlugin
         *
         * => src/ThemePlugin.php
         */
        $classFile = $descriptor->path
            . DIRECTORY_SEPARATOR
            . 'src'
            . DIRECTORY_SEPARATOR
            . $descriptor->entry
            . '.php';

        if (!is_file($classFile)) {
            throw new PluginException(
                "Plugin entry file not found: {$classFile}"
            );
        }

        require_once $classFile;

        if (!class_exists($class)) {
            throw new PluginException(
                "Plugin entry class not found: {$class}"
            );
        }

        if (!is_subclass_of($class, PluginInterface::class)) {
            throw new PluginException(
                "{$class} must implement " . PluginInterface::class
            );
        }

        $instance = new $class();

        if ($instance->getName() !== $descriptor->name) {
            throw new PluginException(
                "Plugin name mismatch for {$descriptor->name}"
            );
        }

        if ($instance->getVersion() !== $descriptor->version) {
            throw new PluginException(
                "Plugin version mismatch for {$descriptor->name}"
            );
        }

        $record->instance = $instance;
        $record->state = PluginState::LOADED;
        $record->error = null;
    }
}