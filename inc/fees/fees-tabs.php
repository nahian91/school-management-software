<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_fees_tab() {
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( $_GET['sub'] ) : 'list';

    // Construct URLs for top submenu links
    $all_fees_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=list' );
    $collect_url  = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=collect' );
    ?>

    <!-- Top Sub-Navigation Menu Bar -->
    <div class="educore-top-nav mb-4 pb-2 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?php echo esc_url( $all_fees_url ); ?>" 
               class="btn <?php echo ( $sub_tab === 'list' ) ? 'btn-success fw-bold' : 'btn-outline-secondary'; ?>">
                <span class="dashicons dashicons-money-alt align-middle me-1"></span> All Fee Invoices
            </a>
            <a href="<?php echo esc_url( $collect_url ); ?>" 
               class="btn <?php echo ( $sub_tab === 'collect' ) ? 'btn-success fw-bold' : 'btn-outline-secondary'; ?>">
                <span class="dashicons dashicons-plus-alt2 align-middle me-1"></span> + Collect Student Fee
            </a>
        </div>

        <?php if ( $sub_tab === 'print' ) : ?>
            <div>
                <span class="badge bg-info text-dark fs-6 px-3 py-2">
                    Printing Invoice Receipt
                </span>
            </div>
        <?php endif; ?>
    </div>

    <div class="educore-module-container">
        <?php
        switch ( $sub_tab ) {
            case 'collect':
                if ( function_exists( 'educore_fees_collect_view' ) ) {
                    educore_fees_collect_view();
                } else {
                    echo '<div class="alert alert-info">Fee Collection module is initializing. Define <code>educore_fees_collect_view()</code>.</div>';
                }
                break;

            case 'print':
                if ( function_exists( 'educore_fees_invoice_print_view' ) ) {
                    educore_fees_invoice_print_view();
                } else {
                    echo '<div class="alert alert-info">Invoice Print module is initializing. Define <code>educore_fees_invoice_print_view()</code>.</div>';
                }
                break;

            case 'list':
            default:
                if ( function_exists( 'educore_fees_list_view' ) ) {
                    educore_fees_list_view();
                } else {
                    echo '<div class="alert alert-info">Fees List View module is initializing. Define <code>educore_fees_list_view()</code>.</div>';
                }
                break;
        }
        ?>
    </div>
    <?php
}
?>