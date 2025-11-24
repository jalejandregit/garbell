<?php
/**
 * Plugin Name: Garbell - Plugin per Scraping Configurable
 * Description: Admin define límites; colaboradores lanzan scrapes, seleccionan posts/imágenes y los importan.
 * Version: 1.0
 * Author: Triapedres
 * Text Domain: garbell
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define('garbell_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('garbell_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once garbell_PLUGIN_DIR . 'includes/admin-page.php';
require_once garbell_PLUGIN_DIR . 'includes/admin-plantilles-page.php';
require_once garbell_PLUGIN_DIR . 'includes/contributor-page.php';
require_once garbell_PLUGIN_DIR . 'includes/registres-page.php';
require_once garbell_PLUGIN_DIR . 'includes/scraper.php';
require_once garbell_PLUGIN_DIR . 'includes/importer.php';

/**
 * Activation: set default options
 */
function garbell_activate() {
    $defaults = array(
        'maxDepth' => 2,
        'maxPages' => 50,
        'maxExecutionSeconds' => 5,
    );
    add_option('garbell_settings', $defaults);
}
register_activation_hook(__FILE__, 'garbell_activate');

/**
 * Enqueue admin scripts/styles
 */
function garbell_admin_assets($hook) {
    if (strpos($hook, 'garbell') === false && $hook !== 'toplevel_page_garbell-admin') {
        // only load on our plugin pages (names set in admin-page and contributor-page)
    }
    wp_enqueue_style('garbell-admin-css', garbell_PLUGIN_URL . 'assets/css/garbell.css');
    wp_enqueue_script('garbell-admin-js', garbell_PLUGIN_URL . 'assets/js/garbell.js', array('jquery'), false, true);
    wp_localize_script('garbell-admin-js', 'garbell_ajax',
        array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('garbell_nonce'))
    );
}
add_action('admin_enqueue_scripts', 'garbell_admin_assets');

/**
 * Register admin menu for administrators
 * /admin.php?page=garbell-admin
 * /admin.php?page=admin-plantilles-page
 */
function garbell_register_menus() {
    add_menu_page('Garbell Admin', 'Garbell', 'manage_options', 'garbell-admin', 'garbell_admin_page', 'dashicons-download', 80);
    add_submenu_page('garbell-admin', 'Plantilles', 'Plantilles', 'manage_options', 'admin-plantilles-page', 'admin_plantilles_page');
    add_submenu_page('garbell-admin', 'Scraper', 'Scraper', 'edit_posts', 'garbell-contributor', 'garbell_contributor_page');
    add_submenu_page('garbell-admin', 'Registres', 'Registres', 'edit_posts', 'registres-contributor', 'registres_contributor_page');
    
    
}
add_action('admin_menu', 'garbell_register_menus');

/**
 * AJAX handlers
 */
add_action('wp_ajax_garbell_run_scrape', 'garbell_ajax_run_scrape');
add_action('wp_ajax_nopriv_garbell_run_scrape', 'garbell_ajax_run_scrape');

add_action('wp_ajax_garbell_import_selected', 'garbell_ajax_import_selected');

