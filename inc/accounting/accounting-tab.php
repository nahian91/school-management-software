<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Accounting Sub-Navigation Router Matrix (Multi-Role Support for Admin & Accountant)
 * File: inc/accounting.php
 */

// Load Modular Dependency Sub-Files if segregated
$acct_dir = defined( 'EDUCORE_PATH' ) ? EDUCORE_PATH . 'inc/accounting/' : plugin_dir_path( __FILE__ ) . 'accounting/';

if ( file_exists( $acct_dir . 'accounting-list.php' ) ) {
    require_once $acct_dir . 'accounting-list.php';
}
if ( file_exists( $acct_dir . 'accounting-add.php' ) ) {
    require_once $acct_dir . 'accounting-add.php';
}

function educore_accounting_tab() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $table_staff  = $wpdb->prefix . 'sms_staff';

    // 1. Procedural Capability & Role Verification
    $is_admin = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    
    $is_accountant = false;
    if ( function_exists( 'educore_has_access' ) ) {
        $is_accountant = educore_has_access( array( 'accountant', 'accounts_officer', 'finance', 'staff' ) );
    }

    if ( ! $is_admin && ! $is_accountant ) {
        $staff_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT designation, staff_type FROM {$table_staff} WHERE wp_user_id = %d OR email = %s LIMIT 1",
            $current_user->ID,
            $current_user->user_email
        ) );
        if ( $staff_row ) {
            $desig = strtolower( $staff_row->designation . ' ' . $staff_row->staff_type );
            if ( strpos( $desig, 'account' ) !== false || strpos( $desig, 'finance' ) !== false || strpos( $desig, 'cash' ) !== false ) {
                $is_accountant = true;
            }
        }
    }

    if ( ! $is_admin && ! $is_accountant ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage financial ledger records.', 'ifsedu-sms' ) );
    }

    // Default sub-tab set to 'list'
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'list';

    // Submenu URLs
    $list_url = admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=list' );
    $add_url  = admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=add' );
    ?>

    <style>
        .dpt-acct-nav-root { 
            margin: 20px 20px 24px 0; 
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            color: #0f172a;
        }
        .afdp-top-nav-wrapper { 
            background: #ffffff; 
            border: 1px solid #e2e8f0; 
            border-radius: 14px; 
            padding: 14px 20px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            flex-wrap: wrap; 
            gap: 16px; 
            margin-bottom: 24px; 
        }
        .dpt-nav-button-group { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            flex-wrap: wrap; 
        }
        .dpt-nav-link { 
            height: 38px; 
            padding: 0 16px; 
            border-radius: 8px; 
            font-size: 13.5px; 
            font-weight: 700; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            cursor: pointer; 
            text-decoration: none; 
            transition: all 0.2s ease; 
            border: 1px solid transparent; 
        }
        .dpt-nav-link-active { 
            background: #006a4e; 
            color: #ffffff !important; 
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.15); 
        }
        .dpt-nav-link-active:hover { background: #00523c; }
        .dpt-nav-link-inactive { 
            background: #f8fafc; 
            border-color: #cbd5e1; 
            color: #475569; 
        }
        .dpt-nav-link-inactive:hover { 
            background: #f1f5f9; 
            border-color: #94a3b8; 
            color: #0f172a; 
        }
        .dpt-assigned-context-pill {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .afdp-notice-card { 
            background: #f0fdf4; 
            border-left: 4px solid #006a4e; 
            padding: 16px 20px; 
            border-radius: 0 8px 8px 0; 
            color: #15803d; 
            font-weight: 600; 
        }
        @media print {
            .no-print, .afdp-top-nav-wrapper { display: none !important; }
            .dpt-acct-nav-root { margin: 0 !important; }
        }
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

            <div>
                <?php if ( ! $is_admin ) : ?>
                    <span class="dpt-assigned-context-pill">
                        <span class="dashicons dashicons-businessman" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php esc_html_e( 'Accounts & Cashier Desk', 'ifsedu-sms' ); ?>
                    </span>
                <?php endif; ?>
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