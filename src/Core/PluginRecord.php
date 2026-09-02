<?php

declare(strict_types=1);

namespace Engine\Core;

use Engine\Contracts\PluginInterface;

final class PluginRecord
{
    public ?PluginInterface $instance = null;
    public PluginState $state = PluginState::DISCOVERED;
    public ?string $error = null;

    public function __construct(public readonly PluginDescriptor $descriptor) {}
}
