<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Master Accounting Module Loader
 * File: inc/accounting.php
 */

// Define directory path safely (already inside inc/)
$accounting_dir = plugin_dir_path( __FILE__ ) . 'accounting/';

// Load required sub-files in order
$accounting_files = array(
    'accounting-delete.php',    // Ledger deletion handler
    'accounting-add-edit.php',  // Record entry form view
    'accounting-list.php',      // Master ledger list view & summary stats
    'accounting-tab.php',       // Router function: educore_accounting_tab()
);

foreach ( $accounting_files as $file ) {
    $file_path = $accounting_dir . $file;
    if ( file_exists( $file_path ) ) {
        require_once $file_path;
    } else {
        error_log( 'EduCore Accounting Error: Missing file ' . $file_path );
    }
}