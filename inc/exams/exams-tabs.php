<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_exams_tab() {
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( $_GET['sub'] ) : 'list';

    echo '<div class="educore-module-container">';
    
    switch ( $sub_tab ) {
        case 'marks':
            if ( function_exists( 'educore_exams_marks_view' ) ) {
                educore_exams_marks_view();
            }
            break;
        case 'report':
            if ( function_exists( 'educore_exams_report_view' ) ) {
                educore_exams_report_view();
            }
            break;
        case 'list':
        default:
            if ( function_exists( 'educore_exams_list_view' ) ) {
                educore_exams_list_view();
            }
            break;
    }

    echo '</div>';
}
?>