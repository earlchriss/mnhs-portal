<?php
// Redis cache with automatic file fallback. Same env vars as moodle.php:
// REDIS_HOST (127.0.0.1), REDIS_PORT (6379), REDIS_PASSWORD, REDIS_DB (0)

const CACHE_PREFIX = "portal:";

function cache_redis(): ?Redis
{
    static $redis = null;
    static $tried = false;
    if ($tried) return $redis;
    $tried = true;
    if (!class_exists("Redis")) return null;
    try {
        $client = new Redis();
        if (!$client->connect(getenv("REDIS_HOST") ?: "127.0.0.1", (int) (getenv("REDIS_PORT") ?: 6379), 0.25)) {
            return null;
        }
        $client->setOption(Redis::OPT_READ_TIMEOUT, 1.0);
        if ($password = getenv("REDIS_PASSWORD")) $client->auth($password);
        $client->select((int) (getenv("REDIS_DB") ?: 0));
        $redis = $client;
    } catch (Throwable $e) {
        $redis = null;
    }
    return $redis;
}

function cache_file(string $key): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "portal_cache";
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir . DIRECTORY_SEPARATOR . sha1($key) . ".json";
}

function cache_get(string $key): ?array
{
    if ($redis = cache_redis()) {
        try {
            $raw = $redis->get(CACHE_PREFIX . $key);
            if (is_string($raw)) {
                $v = json_decode($raw, true);
                return is_array($v) ? $v : null;
            }
            return null;
        } catch (Throwable $e) {
        }
    }
    $file = cache_file($key);
    $raw = is_file($file) ? @file_get_contents($file) : false;
    $entry = $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($entry) || ($entry["exp"] ?? 0) < time() || !is_array($entry["v"] ?? null)) return null;
    return $entry["v"];
}

function cache_set(string $key, array $value, int $ttl): void
{
    $json = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) return;
    if ($redis = cache_redis()) {
        try {
            if ($redis->setex(CACHE_PREFIX . $key, max(1, $ttl), $json)) return;
        } catch (Throwable $e) {
        }
    }
    $file = cache_file($key);
    $tmp = $file . "." . bin2hex(random_bytes(4)) . ".tmp";
    if (@file_put_contents($tmp, json_encode(["exp" => time() + $ttl, "v" => $value])) !== false) {
        @rename($tmp, $file);
    }
}

function cache_forget(string $key): void
{
    if ($redis = cache_redis()) {
        try {
            $redis->del(CACHE_PREFIX . $key);
        } catch (Throwable $e) {
        }
    }
    @unlink(cache_file($key));
}

/** Return the cached value, or run $loader (your DB query), cache and return its result. */
function cache_remember(string $key, int $ttl, callable $loader): array
{
    $hit = cache_get($key);
    if ($hit !== null) return $hit;
    $value = $loader();
    cache_set($key, $value, $ttl);
    return $value;
}
