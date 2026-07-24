<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * EduCore Fee Management Module Loader
 */
$fees_dir = plugin_dir_path( __FILE__ ) . 'fees/';

// Core Fee Views & Handlers
$fee_files = array(
    'fees-tabs.php', 
    'fees-list.php',
    'fees-collect.php', 
    'fees-invoice-print.php', 
);

foreach ( $fee_files as $file ) {
    $file_path = $fees_dir . $file;
    if ( file_exists( $file_path ) ) {
        require_once $file_path;
    }
}