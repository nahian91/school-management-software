<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Main Academic Configuration Module
 * File: academic.php
 * Theme Aesthetic: Elite Neo-Bento UI Router
 * Custom Prefixes Applied: dpt-, afdp-
 */
function educore_academics_tab() {
    // 1. Security & Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this module.', 'ifsedu-sms' ) );
    }

    // 2. Set Base Router Variables
    $current_subtab = isset( $_GET['subtab'] ) ? sanitize_text_field( $_GET['subtab'] ) : 'units';
    $base_url       = admin_url( 'admin.php?page=school_management_system&tab=academics' );

    // 3. Include Shared Academic Header, Styles & Subtab Navigation
    require_once plugin_dir_path( __FILE__ ) . 'academics/academic-header.php';

    // 4. Clean Sub-Router Controller Switch
    switch ( $current_subtab ) {
        
        case 'units':
            require_once plugin_dir_path( __FILE__ ) . 'academics/academic-units.php';
            break;

        case 'subjects':
            require_once plugin_dir_path( __FILE__ ) . 'academics/academic-subjects.php';
            break;

        case 'routine':
            require_once plugin_dir_path( __FILE__ ) . 'academics/academic-routine.php';
            break;

        default:
            ?>
            <style>
                .afdp-bento-error-card {
                    background: #ffffff;
                    border: 1px solid #fee2e2;
                    border-radius: 16px;
                    padding: 40px 24px;
                    text-align: center;
                    box-shadow: 0 4px 20px -2px rgba(220, 38, 38, 0.05);
                }
                .afdp-error-icon {
                    font-size: 42px;
                    width: 42px;
                    height: 42px;
                    color: #dc2626;
                    margin-bottom: 12px;
                }
                .afdp-error-title {
                    font-size: 16px;
                    font-weight: 800;
                    color: #991b1b;
                    margin: 0 0 6px 0;
                }
                .afdp-error-desc {
                    font-size: 13px;
                    color: #7f1d1d;
                    margin: 0;
                }
            </style>
            
            <div class="dpt-bento-card afdp-bento-error-card">
                <span class="dashicons dashicons-warning afdp-error-icon"></span>
                <h4 class="afdp-error-title"><?php esc_html_e( 'Academic Module Not Found', 'ifsedu-sms' ); ?></h4>
                <p class="afdp-error-desc"><?php esc_html_e( 'The requested subtab view does not exist or has been relocated.', 'ifsedu-sms' ); ?></p>
            </div>
            <?php
            break;
    }

    // 5. Close Root Wrapper Opened in academic-header.php
    echo '</div>'; 
}