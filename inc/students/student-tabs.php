<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_students_tab() {
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( $_GET['sub'] ) : 'list';

    echo '<div class="educore-module-container">';
    
    switch ( $sub_tab ) {
        case 'add':
        case 'edit':
            if ( function_exists( 'educore_student_add_edit_view' ) ) {
                educore_student_add_edit_view();
            }
            break;
        case 'view':
            if ( function_exists( 'educore_student_profile_view' ) ) {
                educore_student_profile_view();
            }
            break;
        case 'delete':
            if ( function_exists( 'educore_student_delete_action' ) ) {
                educore_student_delete_action();
            }
            break;
        case 'list':
        default:
            if ( function_exists( 'educore_students_list_view' ) ) {
                educore_students_list_view();
            }
            break;
    }

    echo '</div>';
}
?>