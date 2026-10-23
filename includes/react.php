<?php
/**
 * Bridge between PHP and the React build.
 *
 * react_mount($page, $props)  -> prints the <div id="root"> + the JSON props
 * react_assets()              -> prints the <link>/<script> tags for the built bundle
 *                                (call it once, e.g. in footer.php)
 */

function react_mount(string $page, array $props = []): void
{
    // JSON_HEX_* flags make the JSON safe to embed inside a <script> tag (prevents XSS / </script> breakouts)
    $json = json_encode(
        $props,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    echo '<main class="page-content home bg-body-tertiary min-vh-100">';
    echo '<div id="root" data-page="' . htmlspecialchars($page, ENT_QUOTES) . '"></div>';
    echo '<script type="application/json" id="page-props">' . $json . '</script>';
    echo '<noscript><div class="container-xl p-4">Please enable JavaScript to use the student portal.</div></noscript>';
    echo '</main>';
}

function react_assets(): void
{
    $manifestPath = __DIR__ . '/../assets/dist/.vite/manifest.json';
    if (!is_file($manifestPath)) {
        // Vite < 5 wrote it to the outDir root
        $manifestPath = __DIR__ . '/../assets/dist/manifest.json';
    }

    if (!is_file($manifestPath)) {
        echo '<!-- React build not found. Run "npm run build". -->';
        return;
    }

    $manifest = json_decode(file_get_contents($manifestPath), true);
    $entry = $manifest['src/main.jsx'] ?? null;
    if (!$entry) {
        echo '<!-- src/main.jsx missing from Vite manifest -->';
        return;
    }

    foreach ($entry['css'] ?? [] as $css) {
        echo '<link rel="stylesheet" href="/assets/dist/' . htmlspecialchars($css, ENT_QUOTES) . '">' . "\n";
    }
    echo '<script type="module" src="/assets/dist/' . htmlspecialchars($entry['file'], ENT_QUOTES) . '"></script>' . "\n";
}
