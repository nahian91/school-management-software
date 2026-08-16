<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Fees Directory & Financial Ledger View Engine (Role-Filtered for Accountant & Admin)
 * File: inc/fees/fees-list.php
 * Theme Aesthetic: Elite Neo-Bento UI
 * Custom Prefixes Applied: dpt-, afdp-
 */

// Handle Fee Invoice AJAX Update Action
add_action( 'wp_ajax_dpt_update_fee_invoice', 'dpt_handle_update_fee_invoice_ajax' );
function dpt_handle_update_fee_invoice_ajax() {
    check_ajax_referer( 'dpt_edit_fee_nonce', 'security' );

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'ifsedu-sms' ) ) );
    }

    global $wpdb;
    $table_fees = $wpdb->prefix . 'sms_fees';

    $fee_id         = isset( $_POST['fee_id'] ) ? absint( $_POST['fee_id'] ) : 0;
    $fee_type       = isset( $_POST['fee_type'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_type'] ) ) : '';
    $fee_month      = isset( $_POST['fee_month'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_month'] ) ) : '';
    $fee_year       = isset( $_POST['fee_year'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_year'] ) ) : '';
    $amount         = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0.00;
    $late_fine      = isset( $_POST['late_fine'] ) ? floatval( $_POST['late_fine'] ) : 0.00;
    $discount       = isset( $_POST['discount'] ) ? floatval( $_POST['discount'] ) : 0.00;
    $net_payable    = isset( $_POST['net_payable'] ) ? floatval( $_POST['net_payable'] ) : 0.00;
    $paid_amount    = isset( $_POST['paid_amount'] ) ? floatval( $_POST['paid_amount'] ) : 0.00;
    $due_amount     = isset( $_POST['due_amount'] ) ? floatval( $_POST['due_amount'] ) : 0.00;
    $payment_status = isset( $_POST['payment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_status'] ) ) : 'Unpaid';

    if ( ! $fee_id ) {
        wp_send_json_error( array( 'message' => __( 'Invalid invoice record specified.', 'ifsedu-sms' ) ) );
    }

    $updated = $wpdb->update(
        $table_fees,
        array(
            'fee_type'       => $fee_type,
            'fee_month'      => $fee_month,
            'fee_year'       => $fee_year,
            'amount'         => $amount,
            'late_fine'      => $late_fine,
            'discount'       => $discount,
            'net_payable'    => $net_payable,
            'paid_amount'    => $paid_amount,
            'due_amount'     => $due_amount,
            'payment_status' => $payment_status,
        ),
        array( 'id' => $fee_id ),
        array( '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%s' ),
        array( '%d' )
    );

    if ( false !== $updated ) {
        if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
            IFSEdu_School_Management_System::log_activity( "Updated Fee Invoice ID #{$fee_id}" );
        }
        wp_send_json_success( array( 'message' => __( 'Fee record updated successfully.', 'ifsedu-sms' ) ) );
    } else {
        wp_send_json_error( array( 'message' => __( 'Failed to update database record.', 'ifsedu-sms' ) ) );
    }
}

function educore_fees_list_view() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $roles        = (array) $current_user->roles;

    // 1. Multi-Role Capability Security Matrix (Admins & Accountants)
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view financial ledger records.', 'ifsedu-sms' ) );
    }

    $table_fees     = $wpdb->prefix . 'sms_fees';
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_staff    = $wpdb->prefix . 'sms_staff';

    // 2. Sanitize and Extract Filter Request Inputs
    $filter_class     = isset( $_GET['filter_class'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_class'] ) ) : '';
    $filter_section   = isset( $_GET['filter_section'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_section'] ) ) : '';
    $filter_shift     = isset( $_GET['filter_shift'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_shift'] ) ) : '';
    $filter_student   = isset( $_GET['filter_student'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_student'] ) ) : '';
    $filter_date_from = isset( $_GET['filter_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_from'] ) ) : '';
    $filter_date_to   = isset( $_GET['filter_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_to'] ) ) : '';
    $filter_status    = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';

    // 3. Fetch Dropdown Options Dynamically
    $raw_classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name IS NOT NULL AND class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    $available_classes = array();
    if ( ! empty( $raw_classes ) ) {
        usort( $raw_classes, function( $a, $b ) {
            return strnatcasecmp( $a->class_name, $b->class_name );
        });
        foreach ( $raw_classes as $cls_obj ) {
            $available_classes[] = $cls_obj->class_name;
        }
    }

    $all_units = $wpdb->get_results( "SELECT id, class_name, section_name FROM {$table_units} WHERE section_name != '' ORDER BY section_name ASC" );

    // 4. Construct SQL Query WHERE Conditions
    $where_clauses = array();
    $query_args    = array();

    if ( ! empty( $filter_class ) ) {
        $where_clauses[] = 's.class_name = %s';
        $query_args[]    = $filter_class;
    }

    if ( ! empty( $filter_section ) ) {
        $where_clauses[] = 's.section_name = %s';
        $query_args[]    = $filter_section;
    }

    if ( ! empty( $filter_shift ) ) {
        $where_clauses[] = 's.shift = %s';
        $query_args[]    = $filter_shift;
    }

    if ( ! empty( $filter_student ) ) {
        $where_clauses[] = '(s.full_name LIKE %s OR s.student_id LIKE %s OR f.invoice_id LIKE %s)';
        $student_like    = '%' . $wpdb->esc_like( $filter_student ) . '%';
        $query_args[]    = $student_like;
        $query_args[]    = $student_like;
        $query_args[]    = $student_like;
    }

    if ( ! empty( $filter_date_from ) ) {
        $where_clauses[] = 'DATE(f.payment_date) >= %s';
        $query_args[]    = $filter_date_from;
    }

    if ( ! empty( $filter_date_to ) ) {
        $where_clauses[] = 'DATE(f.payment_date) <= %s';
        $query_args[]    = $filter_date_to;
    }

    if ( ! empty( $filter_status ) ) {
        $where_clauses[] = 'f.payment_status = %s';
        $query_args[]    = $filter_status;
    }

    $where_sql = '';
    if ( ! empty( $where_clauses ) ) {
        $where_sql = ' WHERE ' . implode( ' AND ', $where_clauses );
    }

    // 5. Aggregate Ledger Totals with Active Filters Applied
    $totals_sql = "SELECT 
        SUM(f.net_payable) as total_invoiced, 
        SUM(f.paid_amount) as total_collected, 
        SUM(f.due_amount) as total_due 
        FROM {$table_fees} f 
        LEFT JOIN {$table_students} s ON f.student_id = s.id" . $where_sql;

    if ( ! empty( $query_args ) ) {
        $totals = $wpdb->get_row( $wpdb->prepare( $totals_sql, $query_args ) );
    } else {
        $totals = $wpdb->get_row( $totals_sql );
    }

    // 6. Fetch Filtered Ledger Records with Student & Waiver Details
    $query = "SELECT f.*, s.full_name, s.student_id as s_id, s.class_name, s.section_name, s.shift, s.waiver_percentage, st.full_name as ref_staff_name 
              FROM {$table_fees} f 
              LEFT JOIN {$table_students} s ON f.student_id = s.id
              LEFT JOIN {$table_staff} st ON s.waiver_staff_id = st.id" . $where_sql . " 
              ORDER BY f.id DESC";

    if ( ! empty( $query_args ) ) {
        $fees_records = $wpdb->get_results( $wpdb->prepare( $query, $query_args ) );
    } else {
        $fees_records = $wpdb->get_results( $query );
    }

    $collect_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=collect' );
    $page_url    = admin_url( 'admin.php?page=school_management_system&tab=fees' );
    $months_list = array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' );
    ?>

    <style>
        .dpt-fees-list-container {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .dpt-notice-banner {
            padding: 14px 18px;
            margin-bottom: 5px;
            background: #ecfdf5;
            border-left: 4px solid #006a4e;
            color: #065f46;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .dpt-notice-banner.updated {
            background: #eff6ff;
            border-left-color: #2563eb;
            color: #1e40af;
        }

        /* Top Metric Bento Grid */
        .afdp-metrics-bento {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        @media (max-width: 768px) {
            .afdp-metrics-bento {
                grid-template-columns: 1fr;
            }
        }

        .dpt-metric-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 18px -2px rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .dpt-metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .dpt-metric-card.invoiced::before { background: #2563eb; }
        .dpt-metric-card.collected::before { background: #006a4e; }
        .dpt-metric-card.due::before { background: #dc2626; }

        .dpt-metric-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }

        .dpt-metric-value {
            font-size: 24px;
            font-weight: 800;
            margin-top: 6px;
            letter-spacing: -0.5px;
        }

        .dpt-metric-value.blue { color: #1e40af; }
        .dpt-metric-value.green { color: #006a4e; }
        .dpt-metric-value.red { color: #b91c1c; }

        /* Filter Panel Bento Card */
        .afdp-filter-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            align-items: flex-end;
        }

        .afdp-filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .afdp-filter-group label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
        }

        .afdp-filter-input,
        .afdp-filter-select {
            width: 100%;
            height: 38px;
            padding: 0 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            color: #0f172a;
            background-color: #f8fafc;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .afdp-filter-input:focus,
        .afdp-filter-select:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
        }

        .afdp-filter-actions {
            display: flex;
            gap: 8px;
            grid-column: 1 / -1;
            justify-content: flex-end;
            padding-top: 4px;
            border-top: 1px solid #f1f5f9;
            margin-top: 4px;
        }

        .dpt-btn-filter-submit {
            height: 38px;
            padding: 0 18px;
            background: #006a4e;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: background 0.2s ease;
        }

        .dpt-btn-filter-submit:hover {
            background: #00523c;
        }

        .dpt-btn-filter-reset {
            height: 38px;
            padding: 0 16px;
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .dpt-btn-filter-reset:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* Actions Bar */
        .afdp-actions-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .afdp-title {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.3px;
        }

        .dpt-btn-collect {
            padding: 10px 20px;
            background: #006a4e;
            color: #ffffff;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
            transition: all 0.2s ease;
        }

        .dpt-btn-collect:hover {
            background: #00523c;
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Main Data Table Wrapper */
        .dpt-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        .dpt-table {
            width: 100% !important;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13.5px;
        }

        .dpt-table thead th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            padding: 12px 14px;
            border-bottom: 2px solid #e2e8f0;
        }

        .dpt-table tbody td {
            padding: 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .dpt-table tbody tr:hover td {
            background: #f8fafc;
        }

        .dpt-invoice-code {
            background: #f1f5f9;
            color: #0f172a;
            padding: 3px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-weight: 700;
            font-size: 12px;
            border: 1px solid #cbd5e1;
        }

        /* Status Pills */
        .afdp-status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .afdp-status-badge.paid {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .afdp-status-badge.partial {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .afdp-status-badge.unpaid {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .dpt-waiver-tag {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 3px;
        }

        .dpt-action-group {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .dpt-square-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .dpt-btn-edit {
            background: #eff6ff;
            color: #2563eb;
            border-color: #bfdbfe;
        }

        .dpt-btn-edit:hover {
            background: #2563eb;
            color: #ffffff;
        }

        .dpt-btn-action-print {
            padding: 6px 12px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
        }

        .dpt-btn-action-print:hover {
            border-color: #006a4e;
            color: #006a4e;
            background: #f0fdf4;
        }

        /* Modal Backdrop */
        .dpt-modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.25s ease;
        }

        .dpt-modal-backdrop.is-visible {
            opacity: 1;
            visibility: visible;
        }

        .dpt-modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 520px;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            transform: translateY(20px);
            transition: transform 0.25s ease;
        }

        .dpt-modal-backdrop.is-visible .dpt-modal-card {
            transform: translateY(0);
        }

        .dpt-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }

        .dpt-modal-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }

        .dpt-modal-close {
            background: transparent;
            border: none;
            color: #64748b;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dpt-modal-close:hover {
            color: #dc2626;
        }

        .dpt-modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }

        .dpt-btn-cancel {
            padding: 9px 18px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 13px;
        }

        .dpt-btn-cancel:hover {
            background: #e2e8f0;
        }
    </style>

    <div class="dpt-fees-list-container">

        <!-- Flash Notice Feedback Banner -->
        <?php if ( isset( $_GET['msg'] ) ) : 
            $msg_type = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
        ?>
            <?php if ( $msg_type === 'collected' || $msg_type === 'success' ) : ?>
                <div class="dpt-notice-banner">
                    <span class="dashicons dashicons-yes-alt" style="font-size:20px; width:20px; height:20px;"></span>
                    <span><?php esc_html_e( 'ফি সফলভাবে গ্রহণ করা হয়েছে এবং রেকর্ড সংরক্ষিত হয়েছে।', 'ifsedu-sms' ); ?></span>
                </div>
            <?php elseif ( $msg_type === 'updated' ) : ?>
                <div class="dpt-notice-banner updated">
                    <span class="dashicons dashicons-saved" style="font-size:20px; width:20px; height:20px;"></span>
                    <span><?php esc_html_e( 'ফি ইনভয়েস রেকর্ড সফলভাবে আপডেট করা হয়েছে।', 'ifsedu-sms' ); ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Financial Ledger Overview Metrics Bento Box -->
        <div class="afdp-metrics-bento">
            <div class="dpt-metric-card invoiced">
                <span class="dpt-metric-label"><?php esc_html_e( 'Total Invoiced Amount', 'ifsedu-sms' ); ?></span>
                <div class="dpt-metric-value blue">৳<?php echo esc_html( number_format( $totals ? $totals->total_invoiced : 0, 2 ) ); ?></div>
            </div>
            <div class="dpt-metric-card collected">
                <span class="dpt-metric-label"><?php esc_html_e( 'Total Fees Collected', 'ifsedu-sms' ); ?></span>
                <div class="dpt-metric-value green">৳<?php echo esc_html( number_format( $totals ? $totals->total_collected : 0, 2 ) ); ?></div>
            </div>
            <div class="dpt-metric-card due">
                <span class="dpt-metric-label"><?php esc_html_e( 'Total Outstanding Dues', 'ifsedu-sms' ); ?></span>
                <div class="dpt-metric-value red">৳<?php echo esc_html( number_format( $totals ? $totals->total_due : 0, 2 ) ); ?></div>
            </div>
        </div>

        <!-- Dynamic Filter Controls Card -->
        <div class="afdp-filter-card">
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="afdp-filter-form">
                <input type="hidden" name="page" value="school_management_system" />
                <input type="hidden" name="tab" value="fees" />

                <!-- Class Filter -->
                <div class="afdp-filter-group">
                    <label for="filter_class"><?php esc_html_e( 'Class', 'ifsedu-sms' ); ?></label>
                    <select name="filter_class" id="filter_class" class="afdp-filter-select">
                        <option value=""><?php esc_html_e( 'All Classes', 'ifsedu-sms' ); ?></option>
                        <?php if ( ! empty( $available_classes ) ) : foreach ( $available_classes as $class ) : ?>
                            <option value="<?php echo esc_attr( $class ); ?>" <?php selected( $filter_class, $class ); ?>>
                                <?php printf( esc_html__( 'Class %s', 'ifsedu-sms' ), esc_html( $class ) ); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>

                <!-- Section Filter (Dynamic Dropdown via JS) -->
                <div class="afdp-filter-group">
                    <label for="filter_section"><?php esc_html_e( 'Section', 'ifsedu-sms' ); ?></label>
                    <select name="filter_section" id="filter_section" class="afdp-filter-select">
                        <option value=""><?php esc_html_e( 'All Sections', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>

                <!-- Shift Filter -->
                <div class="afdp-filter-group">
                    <label for="filter_shift"><?php esc_html_e( 'Shift', 'ifsedu-sms' ); ?></label>
                    <select name="filter_shift" id="filter_shift" class="afdp-filter-select">
                        <option value=""><?php esc_html_e( 'All Shifts', 'ifsedu-sms' ); ?></option>
                        <option value="No Shift" <?php selected( $filter_shift, 'No Shift' ); ?>><?php esc_html_e( 'No Shift', 'ifsedu-sms' ); ?></option>
                        <option value="Morning Shift" <?php selected( $filter_shift, 'Morning Shift' ); ?>><?php esc_html_e( 'Morning Shift', 'ifsedu-sms' ); ?></option>
                        <option value="Day Shift" <?php selected( $filter_shift, 'Day Shift' ); ?>><?php esc_html_e( 'Day Shift', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>

                <!-- Payment Status Filter -->
                <div class="afdp-filter-group">
                    <label for="filter_status"><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></label>
                    <select name="filter_status" id="filter_status" class="afdp-filter-select">
                        <option value=""><?php esc_html_e( 'All Statuses', 'ifsedu-sms' ); ?></option>
                        <option value="Paid" <?php selected( $filter_status, 'Paid' ); ?>><?php esc_html_e( 'Paid', 'ifsedu-sms' ); ?></option>
                        <option value="Partial" <?php selected( $filter_status, 'Partial' ); ?>><?php esc_html_e( 'Partial', 'ifsedu-sms' ); ?></option>
                        <option value="Unpaid" <?php selected( $filter_status, 'Unpaid' ); ?>><?php esc_html_e( 'Unpaid', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>

                <!-- Student Filter -->
                <div class="afdp-filter-group">
                    <label for="filter_student"><?php esc_html_e( 'Student / Invoice', 'ifsedu-sms' ); ?></label>
                    <input type="text" name="filter_student" id="filter_student" class="afdp-filter-input" placeholder="<?php esc_attr_e( 'Name, ID, or Invoice...', 'ifsedu-sms' ); ?>" value="<?php echo esc_attr( $filter_student ); ?>" />
                </div>

                <!-- Date Range From -->
                <div class="afdp-filter-group">
                    <label for="filter_date_from"><?php esc_html_e( 'From Date', 'ifsedu-sms' ); ?></label>
                    <input type="date" name="filter_date_from" id="filter_date_from" class="afdp-filter-input" value="<?php echo esc_attr( $filter_date_from ); ?>" />
                </div>

                <!-- Date Range To -->
                <div class="afdp-filter-group">
                    <label for="filter_date_to"><?php esc_html_e( 'To Date', 'ifsedu-sms' ); ?></label>
                    <input type="date" name="filter_date_to" id="filter_date_to" class="afdp-filter-input" value="<?php echo esc_attr( $filter_date_to ); ?>" />
                </div>

                <!-- Filter Action Buttons -->
                <div class="afdp-filter-actions">
                    <button type="submit" class="dpt-btn-filter-submit">
                        <span class="dashicons dashicons-filter" style="font-size:16px; width:16px; height:16px;"></span>
                        <?php esc_html_e( 'Filter Ledger', 'ifsedu-sms' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $page_url ); ?>" class="dpt-btn-filter-reset" title="<?php esc_attr_e( 'Reset Filters', 'ifsedu-sms' ); ?>">
                        <span class="dashicons dashicons-dismiss" style="font-size:16px; width:16px; height:16px;"></span>
                        <?php esc_html_e( 'Reset', 'ifsedu-sms' ); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Action Header -->
        <div class="afdp-actions-bar">
            <h2 class="afdp-title">
                <span class="dashicons dashicons-money-alt" style="color:#006a4e; font-size:24px; width:24px; height:24px;"></span>
                <?php esc_html_e( 'Fee Collection & Due Ledger', 'ifsedu-sms' ); ?>
            </h2>
            <a href="<?php echo esc_url( $collect_url ); ?>" class="dpt-btn-collect">
                <span class="dashicons dashicons-plus-alt2" style="font-size:16px; width:16px; height:16px;"></span>
                <?php esc_html_e( 'Collect New Fee', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <!-- Main Invoices Table Card -->
        <div class="dpt-bento-card">
            <table class="dpt-table educore-datatable">
                <thead>
                    <tr>
                        <th style="width: 110px;"><?php esc_html_e( 'Invoice ID', 'ifsedu-sms' ); ?></th>
                        <th><?php esc_html_e( 'Student Details', 'ifsedu-sms' ); ?></th>
                        <th><?php esc_html_e( 'Month / Year', 'ifsedu-sms' ); ?></th>
                        <th><?php esc_html_e( 'Fee Category', 'ifsedu-sms' ); ?></th>
                        <th><?php esc_html_e( 'Net Payable', 'ifsedu-sms' ); ?></th>
                        <th><?php esc_html_e( 'Paid', 'ifsedu-sms' ); ?></th>
                        <th><?php esc_html_e( 'Due', 'ifsedu-sms' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></th>
                        <th style="text-align: right; width: 110px;"><?php esc_html_e( 'Actions', 'ifsedu-sms' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $fees_records ) ) : foreach ( $fees_records as $fee ) : 
                        $print_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=print&invoice=' . urlencode( $fee->invoice_id ) );
                        
                        // Status Badge Mapping
                        $status_class = 'unpaid';
                        if ( $fee->payment_status === 'Paid' ) { 
                            $status_class = 'paid'; 
                        } elseif ( $fee->payment_status === 'Partial' ) { 
                            $status_class = 'partial'; 
                        }

                        $student_id_str = $fee->s_id ? strtoupper( $fee->s_id ) : 'DELETED';
                        $class_str      = $fee->class_name ? $fee->class_name : 'Unassigned';
                        $section_str    = ! empty( $fee->section_name ) ? $fee->section_name : 'N/A';
                        $shift_str      = ( ! empty( $fee->shift ) && $fee->shift !== 'No Shift' ) ? ' | ' . $fee->shift : '';
                    ?>
                    <tr data-fee-id="<?php echo esc_attr( $fee->id ); ?>">
                        <td>
                            <span class="dpt-invoice-code">#<?php echo esc_html( $fee->invoice_id ); ?></span>
                        </td>
                        <td>
                            <strong style="color: #0f172a;" class="cell-student-name"><?php echo esc_html( $fee->full_name ? $fee->full_name : 'N/A Record' ); ?></strong><br>
                            <span style="font-size: 11.5px; color: #64748b;">
                                <?php echo esc_html( sprintf( 'ID: %s | Class: %s (%s)%s', $student_id_str, $class_str, $section_str, $shift_str ) ); ?>
                            </span>
                            <?php if ( ! empty( $fee->waiver_percentage ) && floatval( $fee->waiver_percentage ) > 0 ) : ?>
                                <br><span class="dpt-waiver-tag">
                                    <?php echo esc_html( floatval( $fee->waiver_percentage ) ); ?>% Waiver <?php echo ! empty( $fee->ref_staff_name ) ? esc_html( '[' . $fee->ref_staff_name . ']' ) : ''; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 11.5px;" class="cell-month-year">
                                <?php echo esc_html( ucfirst( $fee->fee_month ) . ' ' . $fee->fee_year ); ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: #475569;" class="cell-fee-type"><?php echo esc_html( $fee->fee_type ); ?></strong>
                        </td>
                        <td class="cell-net-payable">৳<?php echo esc_html( number_format( $fee->net_payable, 2 ) ); ?></td>
                        <td class="cell-paid-amount"><strong style="color: #006a4e;">৳<?php echo esc_html( number_format( $fee->paid_amount, 2 ) ); ?></strong></td>
                        <td class="cell-due-amount"><strong style="color: #dc2626;">৳<?php echo esc_html( number_format( $fee->due_amount, 2 ) ); ?></strong></td>
                        <td>
                            <span class="afdp-status-badge <?php echo esc_attr( $status_class ); ?> cell-status">
                                <?php echo esc_html( $fee->payment_status ); ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <div class="dpt-action-group">
                                <!-- Trigger Edit Modal -->
                                <button type="button" 
                                        class="dpt-square-btn dpt-btn-edit btn-trigger-edit-fee" 
                                        data-id="<?php echo esc_attr( $fee->id ); ?>"
                                        data-invoice="<?php echo esc_attr( $fee->invoice_id ); ?>"
                                        data-class="<?php echo esc_attr( $fee->class_name ); ?>"
                                        data-type="<?php echo esc_attr( $fee->fee_type ); ?>"
                                        data-month="<?php echo esc_attr( ucfirst( $fee->fee_month ) ); ?>"
                                        data-year="<?php echo esc_attr( $fee->fee_year ); ?>"
                                        data-amount="<?php echo esc_attr( $fee->amount ); ?>"
                                        data-fine="<?php echo esc_attr( $fee->late_fine ); ?>"
                                        data-discount="<?php echo esc_attr( $fee->discount ); ?>"
                                        data-net="<?php echo esc_attr( $fee->net_payable ); ?>"
                                        data-paid="<?php echo esc_attr( $fee->paid_amount ); ?>"
                                        data-due="<?php echo esc_attr( $fee->due_amount ); ?>"
                                        data-status="<?php echo esc_attr( $fee->payment_status ); ?>"
                                        title="<?php esc_attr_e( 'Edit Invoice Record', 'ifsedu-sms' ); ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>

                                <a href="<?php echo esc_url( $print_url ); ?>" class="dpt-btn-action-print" target="_blank" title="<?php esc_attr_e( 'Print Invoice Receipt', 'ifsedu-sms' ); ?>">
                                    <span class="dashicons dashicons-printer" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                    <?php esc_html_e( 'Print', 'ifsedu-sms' ); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Dynamic Edit Fee Invoice Modal -->
    <div class="dpt-modal-backdrop" id="dpt-edit-fee-modal">
        <div class="dpt-modal-card">
            <div class="dpt-modal-header">
                <h4 class="dpt-modal-title"><?php esc_html_e( 'Edit Fee Invoice Record', 'ifsedu-sms' ); ?></h4>
                <button type="button" class="dpt-modal-close" id="dpt-close-fee-modal">&times;</button>
            </div>
            <form id="dpt-edit-fee-form">
                <input type="hidden" id="edit_fee_id" name="fee_id" value="">
                <input type="hidden" id="edit_fee_class" name="fee_class" value="">
                <input type="hidden" id="edit_fee_amount" name="amount" value="0.00">
                <input type="hidden" id="edit_fee_fine" name="late_fine" value="0.00">
                <input type="hidden" id="edit_fee_discount" name="discount" value="0.00">

                <?php wp_nonce_field( 'dpt_edit_fee_nonce', 'edit_fee_nonce_field' ); ?>

                <div class="afdp-filter-group" style="margin-bottom: 12px;">
                    <label><?php esc_html_e( 'Fee Category Type', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                    <select id="edit_fee_type" name="fee_type" class="afdp-filter-select" required>
                        <option value=""><?php esc_html_e( '-- Choose Category --', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="afdp-filter-group">
                        <label><?php esc_html_e( 'Fee Month', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                        <select id="edit_fee_month" name="fee_month" class="afdp-filter-select" required>
                            <?php foreach ( $months_list as $m ) : ?>
                                <option value="<?php echo esc_attr( $m ); ?>"><?php echo esc_html( $m ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="afdp-filter-group">
                        <label><?php esc_html_e( 'Fee Year', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                        <input type="number" id="edit_fee_year" name="fee_year" class="afdp-filter-input" min="2020" max="2099" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div class="afdp-filter-group">
                        <label><?php esc_html_e( 'Net Payable', 'ifsedu-sms' ); ?></label>
                        <input type="number" step="0.01" id="edit_net_payable" name="net_payable" class="afdp-filter-input" required>
                    </div>
                    <div class="afdp-filter-group">
                        <label><?php esc_html_e( 'Paid Amount', 'ifsedu-sms' ); ?></label>
                        <input type="number" step="0.01" id="edit_paid_amount" name="paid_amount" class="afdp-filter-input" required>
                    </div>
                    <div class="afdp-filter-group">
                        <label><?php esc_html_e( 'Due Amount', 'ifsedu-sms' ); ?></label>
                        <input type="number" step="0.01" id="edit_due_amount" name="due_amount" class="afdp-filter-input" readonly style="background:#fffbeb; color:#b45309; font-weight:800;">
                    </div>
                </div>

                <div class="afdp-filter-group">
                    <label><?php esc_html_e( 'Payment Status', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                    <select id="edit_payment_status" name="payment_status" class="afdp-filter-select" required>
                        <option value="Paid"><?php esc_html_e( 'Paid', 'ifsedu-sms' ); ?></option>
                        <option value="Partial"><?php esc_html_e( 'Partial', 'ifsedu-sms' ); ?></option>
                        <option value="Unpaid"><?php esc_html_e( 'Unpaid', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>

                <div class="dpt-modal-footer">
                    <button type="button" class="dpt-btn-cancel" id="dpt-cancel-fee-edit"><?php esc_html_e( 'Cancel', 'ifsedu-sms' ); ?></button>
                    <button type="submit" class="dpt-btn-collect" id="dpt-save-fee-edit-btn" style="height: auto; padding: 9px 20px;">
                        <span class="dashicons dashicons-saved" style="font-size:16px; width:16px; height:16px;"></span>
                        <?php esc_html_e( 'Update Invoice', 'ifsedu-sms' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Dynamic Script Layer: Section Chaining, Modal Control & DataTables Engine -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const unitsMap       = <?php echo wp_json_encode( ! empty( $all_units ) ? $all_units : array() ); ?>;
        const currentSection = "<?php echo esc_js( $filter_section ); ?>";
        const classSelect    = document.getElementById('filter_class');
        const sectionSelect  = document.getElementById('filter_section');

        // Populate Sections based on selected Class
        function populateSections(selectedClass, selectedSecName = '') {
            if (!sectionSelect) return;
            sectionSelect.innerHTML = '<option value=""><?php esc_html_e( 'All Sections', 'ifsedu-sms' ); ?></option>';
            if (!selectedClass) return;

            const filtered = unitsMap.filter(item => item.class_name == selectedClass);
            const uniqueSections = [...new Set(filtered.map(item => item.section_name).filter(Boolean))];

            uniqueSections.forEach(secName => {
                const opt = document.createElement('option');
                opt.value = secName;
                opt.textContent = secName;
                if (secName == selectedSecName) {
                    opt.selected = true;
                }
                sectionSelect.appendChild(opt);
            });
        }

        if (classSelect && sectionSelect) {
            populateSections(classSelect.value, currentSection);

            classSelect.addEventListener('change', function() {
                populateSections(this.value);
            });
        }

        // --------------------------------------------------------------------------
        // EDIT MODAL AJAX ENGINE FOR FEES LEDGER
        // --------------------------------------------------------------------------
        const modal          = document.getElementById('dpt-edit-fee-modal');
        const closeModalBtn  = document.getElementById('dpt-close-fee-modal');
        const cancelModalBtn = document.getElementById('dpt-cancel-fee-edit');
        const editForm       = document.getElementById('dpt-edit-fee-form');

        function hideModal() {
            if (modal) modal.classList.remove('is-visible');
        }

        if (closeModalBtn) closeModalBtn.addEventListener('click', hideModal);
        if (cancelModalBtn) cancelModalBtn.addEventListener('click', hideModal);

        // Auto-calculate Due Amount on Paid or Net input changes in Modal
        const netInput     = document.getElementById('edit_net_payable');
        const paidInput    = document.getElementById('edit_paid_amount');
        const dueInput     = document.getElementById('edit_due_amount');
        const statusSelect = document.getElementById('edit_payment_status');

        function updateModalCalculations() {
            const net  = parseFloat(netInput.value) || 0;
            const paid = parseFloat(paidInput.value) || 0;
            const due  = Math.max(0, net - paid);
            
            dueInput.value = due.toFixed(2);

            if (paid >= net && net > 0) {
                statusSelect.value = 'Paid';
            } else if (paid > 0 && paid < net) {
                statusSelect.value = 'Partial';
            } else {
                statusSelect.value = 'Unpaid';
            }
        }

        if (netInput && paidInput) {
            netInput.addEventListener('input', updateModalCalculations);
            paidInput.addEventListener('input', updateModalCalculations);
        }

        // Trigger Modal Open & Load Class Specific Categories
        document.addEventListener('click', function(e) {
            const editBtn = e.target.closest('.btn-trigger-edit-fee');
            if (editBtn) {
                const id        = editBtn.getAttribute('data-id');
                const className = editBtn.getAttribute('data-class');
                const type      = editBtn.getAttribute('data-type');
                const month     = editBtn.getAttribute('data-month');
                const year      = editBtn.getAttribute('data-year');
                const amount    = editBtn.getAttribute('data-amount');
                const fine      = editBtn.getAttribute('data-fine');
                const discount  = editBtn.getAttribute('data-discount');
                const net       = editBtn.getAttribute('data-net');
                const paid      = editBtn.getAttribute('data-paid');
                const due       = editBtn.getAttribute('data-due');
                const status    = editBtn.getAttribute('data-status');

                document.getElementById('edit_fee_id').value         = id;
                document.getElementById('edit_fee_class').value      = className;
                document.getElementById('edit_fee_month').value      = month;
                document.getElementById('edit_fee_year').value       = year;
                document.getElementById('edit_fee_amount').value     = amount;
                document.getElementById('edit_fee_fine').value       = fine;
                document.getElementById('edit_fee_discount').value   = discount;
                document.getElementById('edit_net_payable').value    = net;
                document.getElementById('edit_paid_amount').value    = paid;
                document.getElementById('edit_due_amount').value     = due;
                document.getElementById('edit_payment_status').value = status;

                // Load Class-specific Fee Types
                const $feeTypeSelect = jQuery('#edit_fee_type');
                $feeTypeSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Categories... --', 'ifsedu-sms' ) ); ?></option>');

                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'educore_get_fee_types_by_class',
                        security: '<?php echo esc_js( wp_create_nonce( "educore_fee_nonce" ) ); ?>',
                        class_name: className
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            let options = '<option value=""><?php echo esc_js( __( '-- Select Fee Category --', 'ifsedu-sms' ) ); ?></option>';
                            let matched = false;
                            response.data.forEach(function(item) {
                                let isSelected = (item.fee_title === type) ? 'selected' : '';
                                if (isSelected) matched = true;
                                options += '<option value="' + item.fee_title + '" data-amount="' + item.amount + '" ' + isSelected + '>' + item.fee_title + ' (৳' + parseFloat(item.amount).toFixed(2) + ')</option>';
                            });
                            if (!matched && type) {
                                options += '<option value="' + type + '" selected>' + type + ' (Custom/Legacy)</option>';
                            }
                            $feeTypeSelect.html(options);
                        } else {
                            $feeTypeSelect.html('<option value="' + type + '" selected>' + type + '</option>');
                        }
                    },
                    error: function() {
                        $feeTypeSelect.html('<option value="' + type + '" selected>' + type + '</option>');
                    }
                });

                modal.classList.add('is-visible');
            }
        });

        // When Fee Type changed in Edit Modal, update Net Payable accordingly
        jQuery('#edit_fee_type').on('change', function() {
            const opt = jQuery(this).find(':selected');
            const newAmt = parseFloat(opt.data('amount'));
            if (!isNaN(newAmt)) {
                document.getElementById('edit_fee_amount').value = newAmt.toFixed(2);
                document.getElementById('edit_net_payable').value = newAmt.toFixed(2);
                updateModalCalculations();
            }
        });

        // Submit AJAX Handler
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const submitBtn    = document.getElementById('dpt-save-fee-edit-btn');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled  = true;
                submitBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Save...';

                const formData = new FormData();
                formData.append('action', 'dpt_update_fee_invoice');
                formData.append('security', document.getElementById('edit_fee_nonce_field').value);
                formData.append('fee_id', document.getElementById('edit_fee_id').value);
                formData.append('fee_type', document.getElementById('edit_fee_type').value);
                formData.append('fee_month', document.getElementById('edit_fee_month').value);
                formData.append('fee_year', document.getElementById('edit_fee_year').value);
                formData.append('amount', document.getElementById('edit_fee_amount').value);
                formData.append('late_fine', document.getElementById('edit_fee_fine').value);
                formData.append('discount', document.getElementById('edit_fee_discount').value);
                formData.append('net_payable', document.getElementById('edit_net_payable').value);
                formData.append('paid_amount', document.getElementById('edit_paid_amount').value);
                formData.append('due_amount', document.getElementById('edit_due_amount').value);
                formData.append('payment_status', document.getElementById('edit_payment_status').value);

                const ajaxUrl = '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>';

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(async response => {
                    const isJson = response.headers.get('content-type')?.includes('application/json');
                    const data = isJson ? await response.json() : null;

                    if (!response.ok) {
                        const error = (data && data.data && data.data.message) || response.statusText;
                        return Promise.reject(error);
                    }
                    return data;
                })
                .then(data => {
                    submitBtn.disabled  = false;
                    submitBtn.innerHTML = originalText;

                    if (data && data.success) {
                        hideModal();
                        const url = new URL(window.location.href);
                        url.searchParams.set('msg', 'updated');
                        window.location.href = url.toString();
                    } else {
                        alert((data && data.data && data.data.message) || 'Error occurred while updating fee invoice.');
                    }
                })
                .catch(err => {
                    submitBtn.disabled  = false;
                    submitBtn.innerHTML = originalText;
                    console.error('AJAX Error:', err);
                    alert('Request failed: ' + (typeof err === 'string' ? err : 'Connection/Server error.'));
                });
            });
        }
    });

    jQuery(document).ready(function($) {
        if ($.fn.DataTable) {
            $('.educore-datatable').DataTable({ 
                "pageLength": 15, 
                "ordering": false,
                "responsive": true,
                "language": {
                    "search": "<?php esc_attr_e( 'Search Ledger:', 'ifsedu-sms' ); ?>",
                    "lengthMenu": "<?php esc_attr_e( 'Show _MENU_ entries', 'ifsedu-sms' ); ?>"
                }
            });
        }
    });
    </script>
    <?php
}