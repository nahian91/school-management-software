<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_fees_tab() {
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( $_GET['sub'] ) : 'list';

    echo '<div class="educore-module-container">';
    
    switch ( $sub_tab ) {
        case 'collect':
            if ( function_exists( 'educore_fees_collect_view' ) ) {
                educore_fees_collect_view();
            }
            break;
        case 'print':
            if ( function_exists( 'educore_fees_invoice_print_view' ) ) {
                educore_fees_invoice_print_view();
            }
            break;
        case 'list':
        default:
            if ( function_exists( 'educore_fees_list_view' ) ) {
                educore_fees_list_view();
            }
            break;
    }

    echo '</div>';
}
?>