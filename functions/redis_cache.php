<?php

/**
 * Small Redis cache helper (phpredis extension).
 *
 * Every function is safe to call when Redis is missing or down: reads fall
 * through to the loader (the database), writes are skipped, and the page keeps
 * working exactly as it did before. Redis only makes it faster.
 *
 * Settings (environment variables, all optional):
 *   REDIS_HOST (127.0.0.1)  REDIS_PORT (6379)  REDIS_PASS ("")  REDIS_DB (0)
 *   REDIS_DISABLED=1 turns the cache off without removing any code.
 */

const RCACHE_PREFIX = "lms:admin:";

/**
 * The shared Redis connection, or null when Redis is not usable.
 * Pass $drop = true to forget a connection that just failed.
 */
function rcache_conn(bool $drop = false): ?Redis
{
    static $state = ["tried" => false, "redis" => null, "error" => null];

    if ($drop) {
        $state["redis"] = null;
        return null;
    }
    if ($state["tried"]) {
        return $state["redis"];
    }
    $state["tried"] = true;

    if (getenv("REDIS_DISABLED")) {
        $state["error"] = "Redis is turned off (REDIS_DISABLED is set).";
        return null;
    }
    if (!class_exists("Redis")) {
        $state["error"] = "The PHP redis extension (php_redis.dll) is not enabled.";
        return null;
    }

    try {
        $redis = new Redis();
        $host = getenv("REDIS_HOST") ?: "127.0.0.1";
        $port = (int) (getenv("REDIS_PORT") ?: 6379);
        if (!$redis->connect($host, $port, 0.5)) {
            $state["error"] = "Could not connect to Redis at {$host}:{$port}.";
            return null;
        }
        $pass = getenv("REDIS_PASSWORD") ?: getenv("REDIS_PASS");
        if ($pass) {
            $redis->auth($pass);
        }
        $redis->select((int) (getenv("REDIS_DB") ?: 0));
        $redis->setOption(Redis::OPT_READ_TIMEOUT, 1.0);
        $state["redis"] = $redis;
    } catch (Throwable $e) {
        $state["error"] = $e->getMessage();
        error_log("Redis connect failed: " . $e->getMessage());
    }
    return $state["redis"];
}

/** Why Redis is not in use (null when it is working). */
function rcache_last_error(): ?string
{
    if (rcache_conn() !== null) return null;
    if (getenv("REDIS_DISABLED")) return "Redis is turned off (REDIS_DISABLED is set).";
    if (!class_exists("Redis")) return "The PHP redis extension (php_redis.dll) is not enabled.";
    return "Could not connect to Redis at " . (getenv("REDIS_HOST") ?: "127.0.0.1") . ":" . (getenv("REDIS_PORT") ?: 6379) . ".";
}

function rcache_key(string $key): string
{
    return RCACHE_PREFIX . $key;
}

/** A Redis call failed mid-request: log it and stop using Redis for the rest of the request. */
function rcache_fail(Throwable $e): void
{
    error_log("Redis error: " . $e->getMessage());
    rcache_conn(true);
}

/**
 * Return the cached value for $key, or run $load, cache its result for $ttl
 * seconds and return it. The value must be JSON-friendly (arrays, strings, numbers).
 */
function rcache_remember(string $key, int $ttl, callable $load): mixed
{
    $redis = rcache_conn();
    if ($redis) {
        try {
            $hit = $redis->get(rcache_key($key));
            if ($hit !== false) {
                $data = json_decode($hit, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }
        } catch (Throwable $e) {
            rcache_fail($e);
            $redis = null;
        }
    }

    $value = $load();

    if ($redis) {
        try {
            $redis->setex(
                rcache_key($key),
                $ttl,
                json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
            );
        } catch (Throwable $e) {
            rcache_fail($e);
        }
    }
    return $value;
}

/** Delete one or more cached values (call this after the data changes). */
function rcache_forget(string ...$keys): void
{
    $redis = rcache_conn();
    if (!$redis || !$keys) return;
    try {
        $redis->del(array_map("rcache_key", $keys));
    } catch (Throwable $e) {
        rcache_fail($e);
    }
}

/** Store a plain string for $ttl seconds. */
function rcache_put(string $key, string $value, int $ttl): void
{
    $redis = rcache_conn();
    if (!$redis) return;
    try {
        $redis->setex(rcache_key($key), $ttl, $value);
    } catch (Throwable $e) {
        rcache_fail($e);
    }
}

/**
 * "Only one caller every $ttl seconds" timer shared by all users.
 * Returns true for the caller that may do the work. Without Redis it always
 * returns true, so the caller's own throttling still applies.
 */
function rcache_claim(string $key, int $ttl): bool
{
    $redis = rcache_conn();
    if (!$redis) return true;
    try {
        return (bool) $redis->set(rcache_key($key), "1", ["nx", "ex" => $ttl]);
    } catch (Throwable $e) {
        rcache_fail($e);
        return true;
    }
}

/** Delete every key this app created. Returns how many were removed. */
function rcache_flush(): int
{
    $redis = rcache_conn();
    if (!$redis) return 0;
    $deleted = 0;
    try {
        $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        $iterator = null;
        while (($keys = $redis->scan($iterator, RCACHE_PREFIX . "*", 100)) !== false) {
            if ($keys) $deleted += (int) $redis->del($keys);
        }
    } catch (Throwable $e) {
        rcache_fail($e);
    }
    return $deleted;
}

/** Facts for the cache status page. */
function rcache_status(): array
{
    $redis = rcache_conn();
    $status = [
        "extension" => class_exists("Redis"),
        "connected" => false,
        "error" => null,
        "host" => (getenv("REDIS_HOST") ?: "127.0.0.1") . ":" . (getenv("REDIS_PORT") ?: 6379),
        "version" => null,
        "keys" => [],
    ];
    if (!$redis) {
        $status["error"] = rcache_last_error();
        return $status;
    }
    try {
        $redis->ping();
        $status["connected"] = true;
        $status["version"] = $redis->info("server")["redis_version"] ?? null;
        $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        $iterator = null;
        while (($keys = $redis->scan($iterator, RCACHE_PREFIX . "*", 100)) !== false) {
            foreach ($keys as $key) {
                $status["keys"][substr($key, strlen(RCACHE_PREFIX))] = (int) $redis->ttl($key);
            }
        }
        ksort($status["keys"]);
    } catch (Throwable $e) {
        rcache_fail($e);
        $status["connected"] = false;
        $status["error"] = $e->getMessage();
    }
    return $status;
}
