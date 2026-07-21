<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_student_delete_action() {
    global $wpdb;

    // 1. Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'educore' ) );
    }

    // 2. Get ID properly
    $id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';

    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );

    if ( empty( $id ) ) {
        educore_safe_redirect_helper( $redirect_url );
        exit;
    }

    // 3. Security Nonce Check
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_student_' . $id ) ) {
        wp_die( __( 'Security check failed. You do not have permission to delete this record.', 'educore' ) );
    }

    $table_name = $wpdb->prefix . 'sms_students';

    // 4. Primary Key Logic
    $primary_key = is_numeric( $id ) ? 'id' : 'student_id';
    $format      = is_numeric( $id ) ? '%d' : '%s';

    // 5. Fetch for Activity Log
    $student = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT full_name FROM `{$table_name}` WHERE `{$primary_key}` = {$format}",
            $id
        )
    );

    if ( $student ) {
        if ( function_exists( 'educore_log_activity' ) ) {
            educore_log_activity( "Deleted student record: " . $student->full_name );
        }

        // 6. Delete Execution
        $wpdb->delete(
            $table_name,
            array( $primary_key => $id ),
            array( $format )
        );
    }

    // 7. Safe Hybrid Redirection
    $redirect_url = add_query_arg( 'message', 'deleted', $redirect_url );
    educore_safe_redirect_helper( $redirect_url );
    exit;
}

/**
 * Safe redirect invoker to prevent redeclaration & fatal errors
 */
function educore_safe_redirect_helper( $url ) {
    if ( function_exists( 'educore_safe_redirect' ) ) {
        educore_safe_redirect( $url );
    } elseif ( ! headers_sent() ) {
        wp_safe_redirect( $url );
        exit;
    } else {
        echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $url ) . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url_raw( $url ) . '" /></noscript>';
        exit;
    }
}