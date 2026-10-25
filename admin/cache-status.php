<?php
session_start();
require_once __DIR__ . "/functions/admin_guard.php";
admin_require(); // admins only: this page can clear caches
require_once __DIR__ . "/../functions/redis_cache.php";

$_SESSION["csrf"] = $_SESSION["csrf"] ?? bin2hex(random_bytes(16));
$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);

/* ---------- Handle POST (before any output so we can redirect) ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    if (!hash_equals($_SESSION["csrf"], (string) ($_POST["csrf"] ?? ""))) {
        $_SESSION["flash"] = ["danger", "Your session expired. Please try again."];
    } elseif ($action === "clear_redis") {
        $_SESSION["flash"] = ["success", "Redis cache cleared (" . rcache_flush() . " entries removed)."];
    } elseif ($action === "reset_opcache") {
        $done = function_exists("opcache_reset") && @opcache_reset();
        $_SESSION["flash"] = $done
            ? ["success", "OPcache reset. Pages are compiled again on their next request."]
            : ["warning", "OPcache is not running, so there was nothing to reset."];
    }
    header("Location: " . strtok($_SERVER["REQUEST_URI"], "?"));
    exit;
}

/* ---------- Collect the facts ---------- */
$redis = rcache_status();

$opcache = ["enabled" => false, "reason" => ""];
if (!function_exists("opcache_get_status")) {
    $opcache["reason"] = "The OPcache extension is not loaded.";
} else {
    $raw = @opcache_get_status(false);
    if (!$raw || empty($raw["opcache_enabled"])) {
        $opcache["reason"] = "OPcache is loaded but turned off (opcache.enable=0).";
    } else {
        $config = @opcache_get_configuration()["directives"] ?? [];
        $opcache = [
            "enabled" => true,
            "hit_rate" => round((float) $raw["opcache_statistics"]["opcache_hit_rate"], 1),
            "hits" => (int) $raw["opcache_statistics"]["hits"],
            "misses" => (int) $raw["opcache_statistics"]["misses"],
            "scripts" => (int) $raw["opcache_statistics"]["num_cached_scripts"],
            "max_scripts" => (int) $raw["opcache_statistics"]["max_cached_keys"],
            "used_mb" => round($raw["memory_usage"]["used_memory"] / 1048576, 1),
            "free_mb" => round($raw["memory_usage"]["free_memory"] / 1048576, 1),
            "validate" => !empty($config["opcache.validate_timestamps"]),
            "revalidate" => (int) ($config["opcache.revalidate_freq"] ?? 0),
        ];
    }
}

$esc = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
$_cache_page = compact("redis", "opcache", "flash", "esc");

include "includes/header.php";
include "includes/navbar.php";
extract($_cache_page);
unset($_cache_page);
?>
<div class="container-xl">
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-1">Cache status</h1>
        <p class="text-body-secondary mb-0">Redis keeps the schedule lists. OPcache keeps compiled PHP.</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $esc($flash[0]) ?>" role="status"><?= $esc($flash[1]) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h5 fw-bold mb-0">Redis</h2>
                        <?php if ($redis["connected"]): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Connected</span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger-emphasis">Not in use</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!$redis["connected"]): ?>
                        <div class="alert alert-warning small">
                            <?= $esc($redis["error"] ?? "Redis is not available.") ?>
                            The pages still work: they read from MySQL until Redis is running.
                        </div>
                    <?php endif; ?>

                    <dl class="row small mb-3">
                        <dt class="col-5 text-body-secondary fw-normal">PHP extension</dt>
                        <dd class="col-7"><?= $redis["extension"] ? "Loaded" : "Missing" ?></dd>
                        <dt class="col-5 text-body-secondary fw-normal">Server</dt>
                        <dd class="col-7"><?= $esc($redis["host"]) ?></dd>
                        <?php if ($redis["version"]): ?>
                            <dt class="col-5 text-body-secondary fw-normal">Version</dt>
                            <dd class="col-7"><?= $esc($redis["version"]) ?></dd>
                        <?php endif; ?>
                        <dt class="col-5 text-body-secondary fw-normal">Cached entries</dt>
                        <dd class="col-7 mb-0"><?= count($redis["keys"]) ?></dd>
                    </dl>

                    <?php if ($redis["keys"]): ?>
                        <table class="table table-sm small align-middle">
                            <thead>
                                <tr class="text-body-secondary">
                                    <th scope="col">Key</th>
                                    <th scope="col" class="text-end">Expires in</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($redis["keys"] as $key => $ttl): ?>
                                    <tr>
                                        <td><code><?= $esc($key) ?></code></td>
                                        <td class="text-end"><?= $ttl >= 0 ? $esc($ttl) . " s" : "-" ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <form method="post" class="mt-3">
                        <input type="hidden" name="csrf" value="<?= $esc($_SESSION["csrf"]) ?>">
                        <input type="hidden" name="action" value="clear_redis">
                        <button type="submit" class="btn btn-outline-danger btn-sm" <?= $redis["connected"] ? "" : "disabled" ?>>
                            <i class="fas fa-broom me-1"></i>Clear Redis cache
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h5 fw-bold mb-0">OPcache</h2>
                        <?php if ($opcache["enabled"]): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Running</span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger-emphasis">Off</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!$opcache["enabled"]): ?>
                        <div class="alert alert-warning small">
                            <?= $esc($opcache["reason"]) ?> Enable it in <code>php.ini</code>, then restart Apache.
                        </div>
                    <?php else: ?>
                        <dl class="row small mb-3">
                            <dt class="col-5 text-body-secondary fw-normal">Hit rate</dt>
                            <dd class="col-7"><?= $esc($opcache["hit_rate"]) ?>%
                                <span class="text-body-secondary">(<?= number_format($opcache["hits"]) ?> hits, <?= number_format($opcache["misses"]) ?> misses)</span>
                            </dd>
                            <dt class="col-5 text-body-secondary fw-normal">Cached scripts</dt>
                            <dd class="col-7"><?= number_format($opcache["scripts"]) ?> of <?= number_format($opcache["max_scripts"]) ?></dd>
                            <dt class="col-5 text-body-secondary fw-normal">Memory</dt>
                            <dd class="col-7"><?= $esc($opcache["used_mb"]) ?> MB used, <?= $esc($opcache["free_mb"]) ?> MB free</dd>
                            <dt class="col-5 text-body-secondary fw-normal">File changes</dt>
                            <dd class="col-7 mb-0">
                                <?= $opcache["validate"]
                                    ? "Checked every " . $esc($opcache["revalidate"]) . " s (edits show up on their own)"
                                    : "Not checked (reset OPcache after editing PHP files)" ?>
                            </dd>
                        </dl>
                    <?php endif; ?>

                    <form method="post" class="mt-3">
                        <input type="hidden" name="csrf" value="<?= $esc($_SESSION["csrf"]) ?>">
                        <input type="hidden" name="action" value="reset_opcache">
                        <button type="submit" class="btn btn-outline-secondary btn-sm" <?= $opcache["enabled"] ? "" : "disabled" ?>>
                            <i class="fas fa-rotate me-1"></i>Reset OPcache
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
include "includes/footer.php";
exit;
