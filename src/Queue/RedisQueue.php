<?php

declare(strict_types=1);

namespace Vortex\Queue;

use JsonException;
use Redis;
use Vortex\Queue\Contracts\Job;
use Vortex\Queue\Contracts\QueueDriver;

/**
 * Redis list + sorted-set delayed queue. Values are JSON envelopes around base64 job payloads.
 * {@code staleReserveSeconds} is ignored (jobs are removed on POP).
 */
final class RedisQueue implements QueueDriver
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $prefix,
    ) {
    }

    public function push(string $queue, Job $job, int $delaySeconds = 0): void
    {
        $this->pushSerialized($queue, serialize($job), $delaySeconds);
    }

    public function pushSerialized(string $queue, string $serializedPayload, int $delaySeconds = 0): void
    {
        $wrapped = $this->encodeEnvelope($serializedPayload, 0);
        $delaySeconds = max(0, $delaySeconds);
        if ($delaySeconds > 0) {
            $id = $this->uniqueId();
            $this->redis->set($this->payloadKey($id), $wrapped);
            $this->redis->zAdd($this->delayedKey($queue), (float) (time() + $delaySeconds), $id);

            return;
        }

        $this->promoteDelayed($queue);
        $this->redis->rPush($this->readyKey($queue), $wrapped);
    }

    public function reserve(string $queue, int $staleReserveSeconds): ?ReservedJob
    {
        $this->promoteDelayed($queue);
        $raw = $this->redis->lPop($this->readyKey($queue));
        if ($raw === false || $raw === '') {
            return null;
        }

        try {
            $decoded = $this->decodeEnvelope((string) $raw);
        } catch (JsonException) {
            return null;
        }

        $id = (int) $this->redis->incr($this->prefix . 'seq');

        return new ReservedJob($id, $decoded['payload'], $decoded['attempts'], $queue);
    }

    public function delete(ReservedJob $reserved): void
    {
    }

    public function release(ReservedJob $reserved, int $attempts, int $delaySeconds): void
    {
        $queue = $reserved->queue;
        if ($queue === '') {
            return;
        }

        $wrapped = $this->encodeEnvelope($reserved->payload, $attempts);
        $due = time() + max(0, $delaySeconds);
        if ($due > time()) {
            $id = $this->uniqueId();
            $this->redis->set($this->payloadKey($id), $wrapped);
            $this->redis->zAdd($this->delayedKey($queue), (float) $due, $id);

            return;
        }

        $this->redis->rPush($this->readyKey($queue), $wrapped);
    }

    private function promoteDelayed(string $queue): void
    {
        $zKey = $this->delayedKey($queue);
        $readyKey = $this->readyKey($queue);
        $now = time();

        for ($guard = 0; $guard < 1000; ++$guard) {
            /** @var list<string>|false $ids */
            $ids = $this->redis->zRangeByScore($zKey, '-inf', (string) $now, ['LIMIT' => [0, 100]]);
            if ($ids === false || $ids === []) {
                break;
            }
            foreach ($ids as $mid) {
                $data = $this->redis->get($this->payloadKey($mid));
                if (is_string($data) && $data !== '') {
                    $this->redis->rPush($readyKey, $data);
                }
                $this->redis->del($this->payloadKey($mid));
                $this->redis->zRem($zKey, $mid);
            }
        }
    }

    /**
     * @return array{payload: string, attempts: int}
     */
    private function decodeEnvelope(string $raw): array
    {
        /** @var mixed $data */
        $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new JsonException('Invalid envelope');
        }
        $b64 = $data['b'] ?? '';
        $attempts = isset($data['a']) ? (int) $data['a'] : 0;
        if (! is_string($b64) || $b64 === '') {
            throw new JsonException('Missing payload');
        }
        $payload = base64_decode($b64, true);
        if ($payload === false) {
            throw new JsonException('Bad base64');
        }

        return ['payload' => $payload, 'attempts' => max(0, $attempts)];
    }

    private function encodeEnvelope(string $jobBytes, int $attempts): string
    {
        return json_encode(
            ['b' => base64_encode($jobBytes), 'a' => max(0, $attempts)],
            JSON_THROW_ON_ERROR,
        );
    }

    private function readyKey(string $queue): string
    {
        return $this->prefix . 'ready:' . $queue;
    }

    private function delayedKey(string $queue): string
    {
        return $this->prefix . 'delayed:' . $queue;
    }

    private function payloadKey(string $id): string
    {
        return $this->prefix . 'payload:' . $id;
    }

    private function uniqueId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
