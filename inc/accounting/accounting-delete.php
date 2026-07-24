<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Accounting Entry Deletion Handler
 * File: accounting-delete.php
 */
function educore_accounting_delete_handler() {
    global $wpdb;

    $table_accounting = $wpdb->prefix . 'sms_accounting';
    $delete_id        = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

    if ( $delete_id > 0 && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_acct_' . $delete_id ) ) {
        $wpdb->delete( $table_accounting, array( 'id' => $delete_id ), array( '%d' ) );

        // Redirect back to main accounting list
        $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=list' );
        wp_safe_redirect( $redirect_url );
        exit;
    } else {
        wp_die( esc_html__( 'Security check failed or invalid transaction entry ID.', 'ifsedu-sms' ) );
    }
}