<?php

declare(strict_types=1);

namespace Engine\Contracts;

use Engine\Core\PluginContext;

interface PluginInterface
{
    public function getName(): string;
    public function getVersion(): string;
    public function register(PluginContext $context): void;
    public function boot(PluginContext $context): void;
    public function shutdown(PluginContext $context): void;
}
