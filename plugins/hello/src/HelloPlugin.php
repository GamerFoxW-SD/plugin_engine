<?php

declare(strict_types=1);

namespace Hello;

use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;
use Engine\Rendering\RenderEvent;

final class HelloPlugin implements PluginInterface
{
    public function getName(): string { return 'hello'; }
    public function getVersion(): string { return '1.1.0'; }

    public function register(PluginContext $context): void
    {
        $context->listen(RenderEvent::class, function (RenderEvent $event): void {
            $event->title('Hello World — Plugin Engine');
            $event->addStyle('/plugins/hello/assets/hello.css');
            $event->addScript('/plugins/hello/assets/hello.js');

            $event->addSection(<<<'HTML'
<section class="hello-card" data-hello-plugin>
    <span class="hello-badge">HelloPlugin</span>
    <h1>Hello <span>World</span>!</h1>
    <p>A tartalmat egy különálló plugin biztosítja.</p>
    <button type="button" data-hello-button>Click me</button>
    <p class="hello-message" data-hello-message aria-live="polite">Kattints a gombra.</p>
</section>
HTML);
        });
    }

    public function boot(PluginContext $context): void {}

    public function shutdown(PluginContext $context): void {}
}
