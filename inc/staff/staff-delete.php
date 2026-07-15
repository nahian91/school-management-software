<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_staff_delete_action() {
    global $wpdb;
    
    $staff_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    // Security check
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_staff_' . $staff_id ) ) {
        wp_die( 'Security check failed. You do not have permission to delete this record.' );
    }

    if ( $staff_id ) {
        $table_name = $wpdb->prefix . 'sms_staff';
        
        // Log activity before deletion
        $staff = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM $table_name WHERE id = %d", $staff_id ) );
        if ( $staff ) {
            educore_log_activity( "Deleted staff record: " . $staff->full_name );
        }

        // Execute Delete
        $wpdb->delete( $table_name, array( 'id' => $staff_id ), array( '%d' ) );
    }

    // Redirect safely back to the list
    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=list' );
    echo '<script>window.location.href="' . esc_url( $redirect_url ) . '";</script>';
    exit;
}
?>