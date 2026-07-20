<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fetch ALL staff information from the database including salary, 
 * joining date, and status.
 * 
 * @return array Array of comprehensive staff data
 */
function dpt_get_all_staff_data() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sms_staff';
    
    // Failsafe: Check if the table exists to prevent fatal theme errors
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
        return array();
    }

    // Retrieve all columns for all staff members (both Active and Inactive)
    $results = $wpdb->get_results( 
        "SELECT * FROM {$table_name} ORDER BY id DESC", 
        ARRAY_A 
    );

    return $results ? $results : array();
}