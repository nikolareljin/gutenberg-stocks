<?php
// Usage: wp eval-file /workspace/wpcli-plugin-meta-check.php
// Prints JSON and exits 1 when any check fails.

if (!defined('ABSPATH')) {
    exit(1);
}

$slug = 'nr-stocks';
$plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
$plugin_file = $plugin_dir . '/nr-stocks.php';
$readme_file = $plugin_dir . '/readme.txt';

if (!is_readable($plugin_file)) {
    fwrite(STDERR, "Plugin file not found: {$plugin_file}\n");
    exit(1);
}

if (!function_exists('get_file_data')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}

$plugin_data = get_file_data(
    $plugin_file,
    array(
        'Name'            => 'Plugin Name',
        'Version'         => 'Version',
        'RequiresAtLeast' => 'Requires at least',
        'TestedUpTo'      => 'Tested up to',
        'RequiresPHP'     => 'Requires PHP',
        'TextDomain'      => 'Text Domain',
        'DomainPath'      => 'Domain Path',
        'License'         => 'License',
    ),
    'plugin'
);

$readme = array(
    'StableTag'       => null,
    'RequiresAtLeast' => null,
    'TestedUpTo'      => null,
    'RequiresPHP'     => null,
    'License'         => null,
);
$readme_text = '';
if (is_readable($readme_file)) {
    $readme_text = (string) file_get_contents($readme_file);
    foreach (explode("\n", $readme_text) as $line) {
        if (stripos($line, 'Stable tag:') === 0) {
            $readme['StableTag'] = trim(substr($line, strlen('Stable tag:')));
        } elseif (stripos($line, 'Requires at least:') === 0) {
            $readme['RequiresAtLeast'] = trim(substr($line, strlen('Requires at least:')));
        } elseif (stripos($line, 'Tested up to:') === 0) {
            $readme['TestedUpTo'] = trim(substr($line, strlen('Tested up to:')));
        } elseif (stripos($line, 'Requires PHP:') === 0) {
            $readme['RequiresPHP'] = trim(substr($line, strlen('Requires PHP:')));
        } elseif (stripos($line, 'License:') === 0) {
            $readme['License'] = trim(substr($line, strlen('License:')));
        }
    }
}

$results = array();

$results['text_domain_matches_slug'] = array(
    'expected' => $slug,
    'actual'   => $plugin_data['TextDomain'] ?? '',
    'ok'       => ($plugin_data['TextDomain'] ?? '') === $slug,
);

$results['domain_path_languages'] = array(
    'expected' => '/languages',
    'actual'   => $plugin_data['DomainPath'] ?? '',
    'ok'       => ($plugin_data['DomainPath'] ?? '') === '/languages',
);

$results['requires_at_least_present'] = array(
    'plugin' => $plugin_data['RequiresAtLeast'] ?? '',
    'readme' => $readme['RequiresAtLeast'] ?? '',
    'ok'     => (string) ($plugin_data['RequiresAtLeast'] ?? '') !== '' && (string) ($readme['RequiresAtLeast'] ?? '') !== '',
);

$results['requires_php_present'] = array(
    'plugin' => $plugin_data['RequiresPHP'] ?? '',
    'readme' => $readme['RequiresPHP'] ?? '',
    'ok'     => (string) ($plugin_data['RequiresPHP'] ?? '') !== '' && (string) ($readme['RequiresPHP'] ?? '') !== '',
);

$results['tested_up_to_present'] = array(
    'readme' => $readme['TestedUpTo'] ?? '',
    'ok'     => (string) ($readme['TestedUpTo'] ?? '') !== '',
);

$stable  = (string) ($readme['StableTag'] ?? '');
$version = (string) ($plugin_data['Version'] ?? '');
$results['version_consistency'] = array(
    'plugin_version' => $version,
    'stable_tag'     => $stable,
    'ok'             => ($stable === 'trunk') || ($stable === $version && $version !== ''),
);

$license = (string) ($plugin_data['License'] ?? '');
$results['gpl_compatible_license'] = array(
    'actual' => $license,
    'ok'     => (bool) preg_match('/GPL|GNU General Public License|MIT|BSD|Apache/i', $license),
);

$results['readme_sections_present'] = array(
    'ok' => false !== strpos($readme_text, '== Description ==')
        && false !== strpos($readme_text, '== Installation ==')
        && false !== strpos($readme_text, '== Changelog =='),
);

$results['external_services_disclosed'] = array(
    'ok' => false !== stripos($readme_text, 'External Services')
        && false !== stripos($readme_text, 'alphavantage'),
);

$results['uninstall_present'] = array(
    'ok' => is_readable($plugin_dir . '/uninstall.php'),
);

$results['pot_present'] = array(
    'ok' => is_readable($plugin_dir . '/languages/nr-stocks.pot'),
);

$results['build_present'] = array(
    'ok' => is_readable($plugin_dir . '/build/blocks/stock-info/block.json'),
);

// Directory guideline #4: minified code must ship with its source.
$results['source_shipped'] = array(
    'ok' => is_readable($plugin_dir . '/src/blocks/stock-info/block.json')
        && is_readable($plugin_dir . '/src/blocks/stock-info/index.js'),
);

$results['no_dev_leftovers'] = array(
    'ok' => !is_readable($plugin_dir . '/test/docker-compose.yml')
        && !is_readable($plugin_dir . '/node_modules')
        && !is_readable($plugin_dir . '/vendor/autoload.php')
        && !is_readable($plugin_dir . '/bin/build-zip.sh')
        && !is_readable($plugin_dir . '/.distignore'),
);

echo wp_json_encode(
    array(
        'plugin_headers' => $plugin_data,
        'readme'         => $readme,
        'checks'         => $results,
    )
) . "\n";

foreach ($results as $check) {
    if (empty($check['ok'])) {
        exit(1);
    }
}
