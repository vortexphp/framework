# Broadcasting

Thin in-process pub/sub and HTTP Server-Sent Events support elsewhere in **`Http`**.

## Sync broadcaster

The default **`Broadcaster`** binding is **`SyncBroadcaster`**: register listeners with **`listen($channel, $callback)`** and **`publish($channel, $event, $payload)`** from commands, jobs, or HTTP handlers. Replace **`Broadcaster`** in the container when you want Redis fan-out, database notifications, or other transports.

## SSE

Use **`Response::serverSentEvents(static function (SseEmitter $sse): void { ... })`** (see **`Http/README.md`**) for **`text/event-stream`** responses.
