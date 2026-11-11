<?php
/**
 * Plugin Name: Simple Realtime Search
 * Plugin URI:  https://github.com/isaachiroman/simple-realtime-search
 * Description: Realtime search using JSON data.
 * Version:     1.1.0
 * Author:      isaachiroman
 * Author URI:  https://isaachiroman.com/
 * License:     GPLv2 or later
 * Text Domain: simple-realtime-search
 * Domain Path: /languages
 *
 * @package Simple_Realtime_Search
 */

namespace SimpleRealtimeSearch;

defined('ABSPATH') || exit;

// Define Constants.
define('SRS_VERSION', '1.1.0');
define('SRS_PATH', plugin_dir_path(__FILE__));
define('SRS_URL', plugin_dir_url(__FILE__));

// Load Classes.
require_once SRS_PATH . 'includes/Settings.php';
require_once SRS_PATH . 'includes/JsonGenerator.php';
require_once SRS_PATH . 'includes/FrontendSearch.php';

// Initialize Plugin.
add_action('plugins_loaded', function () {
    // Initialize Settings (Admin).
    new Settings();

    // Initialize JsonGenerator (for auto-sync).
    new JsonGenerator();

    // Initialize FrontendSearch if enabled.
    $options = get_option('srs_settings', array());
    if (!empty($options['enabled'])) {
        new FrontendSearch();
    }
});
