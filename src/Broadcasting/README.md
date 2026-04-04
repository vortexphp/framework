# Broadcasting

Thin in-process pub/sub and HTTP Server-Sent Events support elsewhere in **`Http`**.

## Sync broadcaster

With **`broadcasting.driver`** **`sync`** (default), **`Broadcaster`** resolves to **`SyncBroadcaster`**: **`listen($channel, $callback)`** and **`publish($channel, $event, $payload)`** stay in-process.

## Redis broadcaster

Set **`broadcasting.driver`** to **`redis`** and fill **`broadcasting.redis`** (same shape as **`queue.redis`**: host, port, auth, database, …). **`Application`** registers **`RedisBroadcaster`**, which calls the shared **`SyncBroadcaster`** first, then **`Redis::publish`**. Resolve **`SyncBroadcaster::class`** for **`listen()`**; resolve **`Broadcaster::class`** for **`publish()`**. Messages on Redis use channel **`{prefix}{channel}`** (default prefix **`vortex:broadcast:`**) and body **`{"event","payload"}`** JSON.

## SSE

Use **`Response::serverSentEvents(static function (SseEmitter $sse): void { ... })`** (see **`Http/README.md`**) for **`text/event-stream`** responses.
