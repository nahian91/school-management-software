<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Master Attendance Module Loader
 * File: inc/attendance.php
 */

$attendance_dir = plugin_dir_path( __FILE__ ) . 'attendance/';

$attendance_files = array(
    'attendance-ajax.php',    // AJAX handlers
    'attendance-daily.php',   // Daily student attendance
    'attendance-monthly.php', // Monthly class summary
    'attendance-staff.php',   // Faculty/Staff attendance
    'attendance-reports.php', // Individual student logs & reports
    'attendance-tab.php',     // Sub-navigation router engine
);

foreach ( $attendance_files as $file ) {
    $file_path = $attendance_dir . $file;

    if ( file_exists( $file_path ) ) {
        require_once $file_path;
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( 'EduCore Attendance Error: Unable to locate required file [%s]', esc_html( $file_path ) ) );
        }
    }
}