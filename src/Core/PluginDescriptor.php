<?php

declare(strict_types=1);

namespace Engine\Core;

final readonly class PluginDescriptor
{
    public function __construct(
        public string $name,
        public string $version,
        public string $entry,
        public string $namespace,
        public string $path,
        public array $dependencies = [],
    ) {}

    public static function fromFile(string $file): self
    {
        if (!is_file($file)) {
            throw new \InvalidArgumentException("Plugin manifest not found: {$file}");
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException("Invalid plugin manifest: {$file}");
        }

        foreach (['name', 'version', 'entry', 'namespace'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key]) || $data[$key] === '') {
                throw new \InvalidArgumentException("Missing manifest field '{$key}' in {$file}");
            }
        }

        $dependencies = $data['dependencies'] ?? [];
        if (!is_array($dependencies)) {
            throw new \InvalidArgumentException("'dependencies' must be an object/array in {$file}");
        }

        return new self(
            $data['name'],
            $data['version'],
            $data['entry'],
            $data['namespace'],
            dirname($file),
            $dependencies,
        );
    }

    public function entryClass(): string
    {
        return rtrim($this->namespace, '\\') . '\\' . ltrim($this->entry, '\\');
    }
}
