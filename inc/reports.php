<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Master Reports Module Loader
 * File: inc/reports.php
 */

$reports_dir = plugin_dir_path( __FILE__ ) . 'reports/';

$reports_files = array(
    'reports-finance.php',     // Financial reports view
    'reports-attendance.php',  // Attendance analytics view
    'reports-tabs.php',        // Sub-navigation router: educore_reports_tab()
);

foreach ( $reports_files as $file ) {
    $file_path = $reports_dir . $file;
    if ( file_exists( $file_path ) ) {
        require_once $file_path;
    } else {
        error_log( 'EduCore Reports Error: Missing file ' . $file_path );
    }
}