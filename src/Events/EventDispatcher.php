<?php

declare(strict_types=1);

namespace Engine\Events;

final class EventDispatcher
{
    /** @var array<class-string, array<string, callable>> */
    private array $listeners = [];

    private int $nextId = 1;

    public function listen(string $eventClass, callable $listener, ?string $owner = null): string
    {
        $id = 'listener_' . $this->nextId++;
        $this->listeners[$eventClass][$id] = $owner === null
            ? $listener
            : new OwnedListener($owner, $listener);

        return $id;
    }

    public function remove(string $listenerId): void
    {
        foreach ($this->listeners as $eventClass => $listeners) {
            if (isset($listeners[$listenerId])) {
                unset($this->listeners[$eventClass][$listenerId]);
                if ($this->listeners[$eventClass] === []) {
                    unset($this->listeners[$eventClass]);
                }
                return;
            }
        }
    }

    public function removeOwner(string $owner): void
    {
        foreach ($this->listeners as $eventClass => $listeners) {
            foreach ($listeners as $id => $listener) {
                if ($listener instanceof OwnedListener && $listener->owner === $owner) {
                    unset($this->listeners[$eventClass][$id]);
                }
            }
            if ($this->listeners[$eventClass] === []) {
                unset($this->listeners[$eventClass]);
            }
        }
    }

    public function dispatch(object $event): void
    {
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            if ($listener instanceof OwnedListener) {
                ($listener->listener)($event);
            } else {
                $listener($event);
            }
        }
    }
}

final readonly class OwnedListener
{
    public function __construct(
        public string $owner,
        public mixed $listener,
    ) {}
}
