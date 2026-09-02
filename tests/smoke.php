<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Engine\Core\Engine;
use Engine\Core\PluginState;
use Engine\Rendering\RenderEvent;

$engine = new Engine(dirname(__DIR__) . '/plugins');
$engine->boot();

assert($engine->plugins()->get('hello')->state === PluginState::LOADED);
assert($engine->plugins()->get('theme')->state === PluginState::LOADED);

$engine->plugins()->activate('theme');
$engine->plugins()->activate('hello');

$event = new RenderEvent('home');
$engine->events()->dispatch($event);

assert(count($event->styles()) === 2);
assert(count($event->scripts()) === 1);
assert(count($event->sections()) === 1);
assert($event->getTitle() === 'Hello World — Plugin Engine');

$engine->plugins()->deactivate('hello');
$event = new RenderEvent('home');
$engine->events()->dispatch($event);
assert(count($event->sections()) === 0);
assert(count($event->styles()) === 1);

echo "Smoke test passed.\n";
