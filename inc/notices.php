<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access lockdown
}

/**
 * Main Notice, Events & Gallery Module Includer
 */

// Load module components
require_once plugin_dir_path(__FILE__) . 'notices/notice-tab.php';
require_once plugin_dir_path(__FILE__) . 'notices/notice-events-list.php';
require_once plugin_dir_path(__FILE__) . 'notices/notice-events-add.php';
require_once plugin_dir_path(__FILE__) . 'notices/notice-events-view.php';
require_once plugin_dir_path(__FILE__) . 'notices/gallery.php';