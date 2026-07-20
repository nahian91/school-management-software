<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Main Academic Configuration Module
 * File: academic.php
 */
function educore_academics_tab() {
    // 1. Security Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions.', 'educore' ) );
    }

    // 2. Set Base Variables
    $current_subtab = isset( $_GET['subtab'] ) ? sanitize_text_field( $_GET['subtab'] ) : 'units';
    $base_url       = admin_url( 'admin.php?page=school_management_system&tab=academics' );

    // 3. Include Header, Styles & Navigation
    require_once plugin_dir_path( __FILE__ ) . 'academics/academic-header.php';

    // 4. Include Target Content Based on Subtab
    if ( $current_subtab === 'units' ) {
        
        require_once plugin_dir_path( __FILE__ ) . 'academics/academic-units.php';
        
    } elseif ( $current_subtab === 'subjects' ) {
        
        require_once plugin_dir_path( __FILE__ ) . 'academics/academic-subjects.php';
        
    } elseif ( $current_subtab === 'routine' ) {
        
        // নতুন Routine ট্যাবের জন্য ফাইল ইনক্লুড
        require_once plugin_dir_path( __FILE__ ) . 'academics/academic-routine.php';
        
    } else {
        
        // Fallback: যদি ইউআরএল এ ভুল কোনো সাবট্যাব এর নাম দেওয়া হয়
        echo '<div class="dpt-bento-box">';
        echo '<p style="color: #dc2626; font-weight: 600; text-align: center; margin: 0;">Error: The requested tab content was not found!</p>';
        echo '</div>';
        
    }

    // 5. Close Root Wrapper
    echo '</div>'; // Closes .dpt-academics-root opened in academic-header.php
}