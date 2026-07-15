<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_student_delete_action() {
    global $wpdb;
    
    $student_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    // Security check
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_student_' . $student_id ) ) {
        wp_die( 'Security check failed. You do not have permission to delete this record.' );
    }

    if ( $student_id ) {
        $table_name = $wpdb->prefix . 'sms_students';
        
        // Log activity before deletion
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM $table_name WHERE id = %d", $student_id ) );
        if ( $student ) {
            educore_log_activity( "Deleted student record: " . $student->full_name );
        }

        // Execute Delete
        $wpdb->delete( $table_name, array( 'id' => $student_id ), array( '%d' ) );
    }

    // Redirect safely back to the list
    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );
    echo '<script>window.location.href="' . esc_url( $redirect_url ) . '";</script>';
    exit;
}
?>