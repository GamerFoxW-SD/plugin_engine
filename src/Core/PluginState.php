<?php

declare(strict_types=1);

namespace Engine\Core;

enum PluginState: string
{
    case DISCOVERED = 'discovered';
    case LOADED = 'loaded';
    case INACTIVE = 'inactive';
    case ACTIVE = 'active';
    case FAILED = 'failed';
}
