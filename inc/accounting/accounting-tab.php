<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Accounting Sub-Navigation Router Matrix
 * File: accounting-tab.php
 */
function educore_accounting_tab() {
    // Security Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage financial ledger records.', 'ifsedu-sms' ) );
    }

    // Default sub-tab strictly set to 'list'
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( wp_unslash( $_GET['sub'] ) ) : 'list';

    // Submenu URLs
    $list_url = admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=list' );
    $add_url  = admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=add' );
    ?>

    <style>
        .dpt-acct-nav-root { margin: 20px 20px 24px 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .afdp-top-nav-wrapper { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
        .dpt-nav-button-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .dpt-nav-link { height: 38px; padding: 0 16px; border-radius: 8px; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; text-decoration: none; transition: all 0.2s ease; border: 1px solid transparent; }
        .dpt-nav-link-active { background: #006a4e; color: #ffffff; font-weight: 700; box-shadow: 0 4px 12px rgba(0, 106, 78, 0.15); }
        .dpt-nav-link-inactive { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
        .dpt-nav-link-inactive:hover { background: #f1f5f9; color: #0f172a; }
        .afdp-notice-card { background: #f0fdf4; border-left: 4px solid #10b981; padding: 16px 20px; border-radius: 0 8px 8px 0; color: #15803d; font-weight: 500; }
    </style>

    <div class="dpt-acct-nav-root">
        
        <!-- Top Sub-Navigation Menu Bar -->
        <div class="afdp-top-nav-wrapper no-print">
            <div class="dpt-nav-button-group">
                <a href="<?php echo esc_url( $list_url ); ?>" class="dpt-nav-link <?php echo ( $sub_tab === 'list' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php esc_html_e( 'Financial Ledger', 'ifsedu-sms' ); ?>
                </a>
                
                <a href="<?php echo esc_url( $add_url ); ?>" class="dpt-nav-link <?php echo ( $sub_tab === 'add' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Record Transaction', 'ifsedu-sms' ); ?>
                </a>
            </div>
        </div>

        <!-- Router Engine Execution -->
        <div class="dpt-module-viewport-container">
            <?php
            switch ( $sub_tab ) {
                case 'add':
                case 'edit':
                    if ( function_exists( 'educore_accounting_add_edit_view' ) ) {
                        educore_accounting_add_edit_view();
                    } else {
                        echo '<div class="afdp-notice-card">' . esc_html__( 'Record Transaction Module initializing.', 'ifsedu-sms' ) . '</div>';
                    }
                    break;

                case 'delete':
                    if ( function_exists( 'educore_accounting_delete_handler' ) ) {
                        educore_accounting_delete_handler();
                    }
                    break;

                case 'list':
                default:
                    if ( function_exists( 'educore_accounting_list_view' ) ) {
                        educore_accounting_list_view();
                    } else {
                        echo '<div class="afdp-notice-card">' . esc_html__( 'Accounting Ledger View initializing.', 'ifsedu-sms' ) . '</div>';
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}