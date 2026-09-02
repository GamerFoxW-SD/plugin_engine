<?php

declare(strict_types=1);

namespace Theme;

use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;
use Engine\Rendering\RenderEvent;

final class ThemePlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'theme';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function register(PluginContext $context): void
    {
        $context->events()->listen(
            RenderEvent::class,
            function (RenderEvent $event): void {
                $event->addStyle('/plugins/theme/assets/theme.css');
            }
        );
    }

    public function boot(PluginContext $context): void
    {
    }

    public function shutdown(PluginContext $context): void
    {
    }
}