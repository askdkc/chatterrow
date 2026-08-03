<?php

namespace App\Support;

use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

final readonly class BestEffortBroadcaster
{
    public function __construct(private Dispatcher $events) {}

    public function broadcast(object $event): void
    {
        try {
            $this->events->dispatch($event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function broadcastToOthers(object $event): void
    {
        if (method_exists($event, 'dontBroadcastToCurrentUser')) {
            $event->dontBroadcastToCurrentUser();
        }

        $this->broadcast($event);
    }
}
