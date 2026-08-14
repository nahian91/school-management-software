<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Premium Financial Analytics & Transaction Audit Module
 * Theme Aesthetic: Elite Neo-Bento Grid & Glassmorphism System
 * Dynamic Metrics Calculation on Filter Applied
 */
function educore_reports_finance_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access financial analytics.', 'ifsedu-sms' ) );
    }

    global $wpdb;
    $table_fees       = $wpdb->prefix . 'sms_fees';
    $table_accounting = $wpdb->prefix . 'sms_accounting';
    $table_students   = $wpdb->prefix . 'sms_students';

    // --------------------------------------------------------------------------
    // 1. CAPTURE FILTER REQUEST INPUTS
    // --------------------------------------------------------------------------
    $start_date      = isset( $_GET['start_date'] )     ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) )     : date( 'Y-m-01' );
    $end_date        = isset( $_GET['end_date'] )       ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) )       : date( 'Y-m-t' );
    $scope_filter    = isset( $_GET['scope'] )          ? sanitize_text_field( wp_unslash( $_GET['scope'] ) )          : 'all';
    $category_filter = isset( $_GET['fee_category'] )   ? sanitize_text_field( wp_unslash( $_GET['fee_category'] ) )   : '';
    $status_filter   = isset( $_GET['payment_status'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_status'] ) ) : '';
    $method_filter   = isset( $_GET['payment_method'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_method'] ) ) : '';

    // --------------------------------------------------------------------------
    // 2. UNIFIED FILTERED TRANSACTION LOGS QUERY
    // --------------------------------------------------------------------------
    $combined_logs = array();

    // Query Student Fees if matching scope
    if ( in_array( $scope_filter, array( 'all', 'fees' ), true ) ) {
        $where_fees = array( "DATE(f.payment_date) BETWEEN %s AND %s" );
        $params_fees = array( $start_date, $end_date );

        if ( ! empty( $category_filter ) ) {
            $where_fees[] = "f.fee_type = %s";
            $params_fees[] = $category_filter;
        }

        if ( ! empty( $status_filter ) ) {
            $where_fees[] = "f.payment_status = %s";
            $params_fees[] = $status_filter;
        }

        if ( ! empty( $method_filter ) ) {
            $where_fees[] = "f.payment_method = %s";
            $params_fees[] = $method_filter;
        }

        $fees_sql = "SELECT 
            f.payment_date as trans_date,
            f.invoice_id as ref_code,
            'Student Fee' as flow_group,
            'Income' as flow_type,
            f.fee_type as category,
            COALESCE(s.full_name, 'Direct Fee Payment') as party_name,
            f.payment_method,
            f.net_payable,
            f.paid_amount,
            f.due_amount,
            f.payment_status
            FROM {$table_fees} f
            LEFT JOIN {$table_students} s ON f.student_id = s.id
            WHERE " . implode( ' AND ', $where_fees );

        $fees_rows = $wpdb->get_results( $wpdb->prepare( $fees_sql, ...$params_fees ) );
        if ( ! empty( $fees_rows ) ) {
            $combined_logs = array_merge( $combined_logs, $fees_rows );
        }
    }

    // Query General Accounting if matching scope
    if ( in_array( $scope_filter, array( 'all', 'general_income', 'general_expense' ), true ) ) {
        $where_acct = array( "a.entry_date BETWEEN %s AND %s" );
        $params_acct = array( $start_date, $end_date );

        if ( $scope_filter === 'general_income' ) {
            $where_acct[] = "a.entry_type = 'Income'";
        } elseif ( $scope_filter === 'general_expense' ) {
            $where_acct[] = "a.entry_type = 'Expense'";
        }

        if ( ! empty( $category_filter ) ) {
            $where_acct[] = "a.category_name = %s";
            $params_acct[] = $category_filter;
        }

        if ( ! empty( $method_filter ) ) {
            $where_acct[] = "a.payment_method = %s";
            $params_acct[] = $method_filter;
        }

        // Only include general entries if status filter is empty or 'Paid'
        if ( empty( $status_filter ) || $status_filter === 'Paid' ) {
            $acct_sql = "SELECT 
                a.entry_date as trans_date,
                a.voucher_no as ref_code,
                CONCAT('General ', a.entry_type) as flow_group,
                a.entry_type as flow_type,
                a.category_name as category,
                COALESCE(NULLIF(a.party_name, ''), a.title) as party_name,
                a.payment_method,
                a.amount as net_payable,
                a.amount as paid_amount,
                0.00 as due_amount,
                'Paid' as payment_status
                FROM {$table_accounting} a
                WHERE " . implode( ' AND ', $where_acct );

            $acct_rows = $wpdb->get_results( $wpdb->prepare( $acct_sql, ...$params_acct ) );
            if ( ! empty( $acct_rows ) ) {
                $combined_logs = array_merge( $combined_logs, $acct_rows );
            }
        }
    }

    // Sort Combined Logs chronologically descending
    usort( $combined_logs, function( $a, $b ) {
        return strtotime( $b->trans_date ) - strtotime( $a->trans_date );
    } );

    // --------------------------------------------------------------------------
    // 3. DYNAMIC SUMMARY METRICS CALCULATION (FILTER-AWARE)
    // --------------------------------------------------------------------------
    $total_revenue_inflow = 0.00;
    $total_expenses       = 0.00;
    $total_pending_dues   = 0.00;

    foreach ( $combined_logs as $log_item ) {
        if ( $log_item->flow_type === 'Income' ) {
            $total_revenue_inflow += (float) $log_item->paid_amount;
        } elseif ( $log_item->flow_type === 'Expense' ) {
            $total_expenses += (float) $log_item->paid_amount;
        }
        $total_pending_dues += (float) $log_item->due_amount;
    }

    $net_operating_cash = $total_revenue_inflow - $total_expenses;

    $base_report_url = admin_url( 'admin.php?page=school_management_system&tab=reports&sub=finance' );
    ?>

    <style>
        .dpt-finance-root {
            margin: 20px 20px 24px 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .afdp-header-frame {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 24px 28px;
            border-radius: 16px;
            margin-bottom: 24px;
            color: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .afdp-header-content h2 {
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .afdp-header-content p {
            margin: 0;
            font-size: 13px;
            color: #94a3b8;
            font-weight: 500;
        }

        /* Filter Controls Card */
        .dpt-filter-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
        }

        .dpt-filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            align-items: flex-end;
        }

        .dpt-field-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dpt-label {
            font-size: 11.5px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .dpt-input-control {
            width: 100%;
            height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13px;
            color: #0f172a;
            background-color: #f8fafc;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .dpt-input-control:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
            outline: none;
        }

        .dpt-btn-generate {
            height: 40px;
            background: #006a4e;
            border: none;
            color: #ffffff;
            font-weight: 700;
            font-size: 13.5px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
            text-decoration: none;
            width: 100%;
        }

        .dpt-btn-generate:hover {
            background: #00523c;
            color: #ffffff;
        }

        .dpt-btn-reset {
            height: 40px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-weight: 700;
            font-size: 13px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            padding: 0 14px;
        }
        .dpt-btn-reset:hover { background: #e2e8f0; color: #0f172a; }

        /* Dynamic Metrics Bento Grid */
        .dpt-metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }

        @media (max-width: 1024px) {
            .dpt-metrics-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .dpt-metrics-grid { grid-template-columns: 1fr; }
        }

        .dpt-metric-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .dpt-metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.06);
        }

        .dpt-metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .dpt-metric-card.dpt-card-emerald::before { background: #006a4e; }
        .dpt-metric-card.dpt-card-rose::before    { background: #dc2626; }
        .dpt-metric-card.dpt-card-blue::before    { background: #2563eb; }
        .dpt-metric-card.dpt-card-amber::before   { background: #d97706; }

        .dpt-metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .dpt-metric-label {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }

        .dpt-metric-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dpt-card-emerald .dpt-metric-icon { background: #ecfdf5; color: #006a4e; }
        .dpt-card-rose .dpt-metric-icon    { background: #fef2f2; color: #dc2626; }
        .dpt-card-blue .dpt-metric-icon    { background: #eff6ff; color: #2563eb; }
        .dpt-card-amber .dpt-metric-icon   { background: #fffbeb; color: #d97706; }

        .dpt-metric-value {
            font-size: 26px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.5px;
        }

        .dpt-card-emerald .dpt-metric-value { color: #006a4e; }
        .dpt-card-rose .dpt-metric-value    { color: #dc2626; }
        .dpt-card-blue .dpt-metric-value    { color: #1e40af; }
        .dpt-card-amber .dpt-metric-value   { color: #b45309; }

        /* Data Audit Table Card */
        .dpt-table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
        }

        .dpt-table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .dpt-table-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .dpt-btn-print {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #334155;
            font-weight: 700;
            font-size: 12.5px;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .dpt-btn-print:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .dpt-table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .dpt-data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13.5px;
            text-align: left;
        }

        .dpt-data-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
            font-size: 12px;
            text-transform: uppercase;
        }

        .dpt-data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .dpt-data-table tbody tr:hover td {
            background-color: #f8fafc;
        }

        .dpt-ref-badge {
            background: #f1f5f9;
            color: #0f172a;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-weight: 700;
            font-size: 11.5px;
            border: 1px solid #cbd5e1;
        }

        .dpt-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .dpt-badge-income  { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .dpt-badge-expense { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .dpt-badge-paid    { background: #d1fae5; color: #065f46; }
        .dpt-badge-partial { background: #fef3c7; color: #92400e; }
        .dpt-badge-unpaid  { background: #ffe4e6; color: #9f1239; }

        /* Print Media Styles */
        @media print {
            body * { visibility: hidden; }
            .dpt-finance-root, .dpt-finance-root * { visibility: visible; }
            .dpt-finance-root {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 0;
            }
            .no-print { display: none !important; }
            .dpt-table-card, .dpt-metric-card {
                border: 1px solid #cbd5e1 !important;
                box-shadow: none !important;
            }
        }
    </style>

    <div class="dpt-finance-root">
        
        <!-- Header Banner -->
        <div class="afdp-header-frame no-print">
            <div class="afdp-header-content">
                <h2>
                    <span class="dashicons dashicons-chart-bar" style="color:#006a4e;"></span>
                    <?php esc_html_e( 'Financial Statement & Revenue Audit', 'ifsedu-sms' ); ?>
                </h2>
                <p><?php esc_html_e( 'Comprehensive period-wise fee collection, operating expenses, and cash flow audit trail.', 'ifsedu-sms' ); ?></p>
            </div>
        </div>

        <!-- Filter Control Matrix Card -->
        <div class="dpt-filter-card no-print">
            <form method="GET" action="">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="reports">
                <input type="hidden" name="sub" value="finance">
                
                <div class="dpt-filter-grid">
                    
                    <!-- 1. Scope Selector -->
                    <div class="dpt-field-group">
                        <label class="dpt-label"><?php esc_html_e( 'Financial Scope', 'ifsedu-sms' ); ?></label>
                        <select name="scope" class="dpt-input-control">
                            <option value="all" <?php selected( $scope_filter, 'all' ); ?>><?php esc_html_e( 'All Cash & Ledger (Income + Expense)', 'ifsedu-sms' ); ?></option>
                            <option value="fees" <?php selected( $scope_filter, 'fees' ); ?>><?php esc_html_e( 'Student Academic Fees Only', 'ifsedu-sms' ); ?></option>
                            <option value="general_income" <?php selected( $scope_filter, 'general_income' ); ?>><?php esc_html_e( 'General Incomes (+)', 'ifsedu-sms' ); ?></option>
                            <option value="general_expense" <?php selected( $scope_filter, 'general_expense' ); ?>><?php esc_html_e( 'Operating Expenses (-)', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- 2. Category Dropdown -->
                    <div class="dpt-field-group">
                        <label class="dpt-label"><?php esc_html_e( 'Fee / Entry Category', 'ifsedu-sms' ); ?></label>
                        <select name="fee_category" class="dpt-input-control">
                            <option value=""><?php esc_html_e( '-- All Categories --', 'ifsedu-sms' ); ?></option>
                            <optgroup label="<?php esc_attr_e( 'Student Academic Fees', 'ifsedu-sms' ); ?>">
                                <option value="Tuition Fee" <?php selected( $category_filter, 'Tuition Fee' ); ?>><?php esc_html_e( 'Tuition Fee', 'ifsedu-sms' ); ?></option>
                                <option value="Admission Fee" <?php selected( $category_filter, 'Admission Fee' ); ?>><?php esc_html_e( 'Admission Fee', 'ifsedu-sms' ); ?></option>
                                <option value="Exam Fee" <?php selected( $category_filter, 'Exam Fee' ); ?>><?php esc_html_e( 'Exam Fee', 'ifsedu-sms' ); ?></option>
                                <option value="Transport Fee" <?php selected( $category_filter, 'Transport Fee' ); ?>><?php esc_html_e( 'Transport Fee', 'ifsedu-sms' ); ?></option>
                                <option value="Hostel Fee" <?php selected( $category_filter, 'Hostel Fee' ); ?>><?php esc_html_e( 'Hostel Fee', 'ifsedu-sms' ); ?></option>
                                <option value="Other Charges" <?php selected( $category_filter, 'Other Charges' ); ?>><?php esc_html_e( 'Other Charges', 'ifsedu-sms' ); ?></option>
                            </optgroup>
                            <optgroup label="<?php esc_attr_e( 'General Ledger Categories', 'ifsedu-sms' ); ?>">
                                <option value="Staff Salary & Remuneration" <?php selected( $category_filter, 'Staff Salary & Remuneration' ); ?>><?php esc_html_e( 'Staff Salary', 'ifsedu-sms' ); ?></option>
                                <option value="Utility Bills (Electricity/Gas/Water)" <?php selected( $category_filter, 'Utility Bills (Electricity/Gas/Water)' ); ?>><?php esc_html_e( 'Utility Bills', 'ifsedu-sms' ); ?></option>
                                <option value="Maintenance & Infrastructure Repair" <?php selected( $category_filter, 'Maintenance & Infrastructure Repair' ); ?>><?php esc_html_e( 'Maintenance & Repairs', 'ifsedu-sms' ); ?></option>
                                <option value="Government Grant" <?php selected( $category_filter, 'Government Grant' ); ?>><?php esc_html_e( 'Government Grant', 'ifsedu-sms' ); ?></option>
                                <option value="Donation & Sponsorship" <?php selected( $category_filter, 'Donation & Sponsorship' ); ?>><?php esc_html_e( 'Donation & Sponsorship', 'ifsedu-sms' ); ?></option>
                                <option value="Other Expenses" <?php selected( $category_filter, 'Other Expenses' ); ?>><?php esc_html_e( 'Other Expenses', 'ifsedu-sms' ); ?></option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- 3. Payment Status -->
                    <div class="dpt-field-group">
                        <label class="dpt-label"><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></label>
                        <select name="payment_status" class="dpt-input-control">
                            <option value=""><?php esc_html_e( '-- All Statuses --', 'ifsedu-sms' ); ?></option>
                            <option value="Paid" <?php selected( $status_filter, 'Paid' ); ?>><?php esc_html_e( 'Paid (Settled)', 'ifsedu-sms' ); ?></option>
                            <option value="Partial" <?php selected( $status_filter, 'Partial' ); ?>><?php esc_html_e( 'Partial Payment', 'ifsedu-sms' ); ?></option>
                            <option value="Unpaid" <?php selected( $status_filter, 'Unpaid' ); ?>><?php esc_html_e( 'Unpaid / Due', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- 4. Payment Method -->
                    <div class="dpt-field-group">
                        <label class="dpt-label"><?php esc_html_e( 'Method', 'ifsedu-sms' ); ?></label>
                        <select name="payment_method" class="dpt-input-control">
                            <option value=""><?php esc_html_e( '-- All Methods --', 'ifsedu-sms' ); ?></option>
                            <option value="Cash" <?php selected( $method_filter, 'Cash' ); ?>>Cash</option>
                            <option value="Bank Transfer" <?php selected( $method_filter, 'Bank Transfer' ); ?>>Bank Transfer</option>
                            <option value="bKash" <?php selected( $method_filter, 'bKash' ); ?>>bKash</option>
                            <option value="Nagad" <?php selected( $method_filter, 'Nagad' ); ?>>Nagad</option>
                            <option value="Cheque" <?php selected( $method_filter, 'Cheque' ); ?>>Cheque</option>
                        </select>
                    </div>

                    <!-- 5. Date From -->
                    <div class="dpt-field-group">
                        <label class="dpt-label"><?php esc_html_e( 'From Date', 'ifsedu-sms' ); ?></label>
                        <input type="date" name="start_date" class="dpt-input-control" value="<?php echo esc_attr( $start_date ); ?>" required>
                    </div>

                    <!-- 6. Date To -->
                    <div class="dpt-field-group">
                        <label class="dpt-label"><?php esc_html_e( 'To Date', 'ifsedu-sms' ); ?></label>
                        <input type="date" name="end_date" class="dpt-input-control" value="<?php echo esc_attr( $end_date ); ?>" required>
                    </div>

                    <!-- Actions -->
                    <div style="display:flex; gap:8px;">
                        <button type="submit" class="dpt-btn-generate">
                            <span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Filter', 'ifsedu-sms' ); ?>
                        </button>
                        <a href="<?php echo esc_url( $base_report_url ); ?>" class="dpt-btn-reset">
                            <?php esc_html_e( 'Reset', 'ifsedu-sms' ); ?>
                        </a>
                    </div>

                </div>
            </form>
        </div>

        <!-- Summary Metric Bento Cards Matrix (Dynamic on Filter) -->
        <div class="dpt-metrics-grid">
            
            <!-- Card 1: Total Revenue Inflow -->
            <div class="dpt-metric-card dpt-card-emerald">
                <div class="dpt-metric-header">
                    <span class="dpt-metric-label"><?php esc_html_e( 'Total Inflow (+)', 'ifsedu-sms' ); ?></span>
                    <div class="dpt-metric-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                </div>
                <div class="dpt-metric-value">
                    ৳<?php echo esc_html( number_format( $total_revenue_inflow, 2 ) ); ?>
                </div>
            </div>

            <!-- Card 2: Operating Expenses Outflow -->
            <div class="dpt-metric-card dpt-card-rose">
                <div class="dpt-metric-header">
                    <span class="dpt-metric-label"><?php esc_html_e( 'Total Outflow (-)', 'ifsedu-sms' ); ?></span>
                    <div class="dpt-metric-icon">
                        <span class="dashicons dashicons-arrow-down-alt"></span>
                    </div>
                </div>
                <div class="dpt-metric-value">
                    ৳<?php echo esc_html( number_format( $total_expenses, 2 ) ); ?>
                </div>
            </div>

            <!-- Card 3: Net Cash Balance -->
            <div class="dpt-metric-card dpt-card-blue">
                <div class="dpt-metric-header">
                    <span class="dpt-metric-label"><?php esc_html_e( 'Net Operating Cash', 'ifsedu-sms' ); ?></span>
                    <div class="dpt-metric-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                </div>
                <div class="dpt-metric-value">
                    ৳<?php echo esc_html( number_format( $net_operating_cash, 2 ) ); ?>
                </div>
            </div>

            <!-- Card 4: Total Pending Student Dues -->
            <div class="dpt-metric-card dpt-card-amber">
                <div class="dpt-metric-header">
                    <span class="dpt-metric-label"><?php esc_html_e( 'Pending Dues', 'ifsedu-sms' ); ?></span>
                    <div class="dpt-metric-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                </div>
                <div class="dpt-metric-value">
                    ৳<?php echo esc_html( number_format( $total_pending_dues, 2 ) ); ?>
                </div>
            </div>

        </div>

        <!-- Transaction Audit Log Table -->
        <div class="dpt-table-card">
            <div class="dpt-table-header">
                <h3 class="dpt-table-title">
                    <span class="dashicons dashicons-list-view" style="color:#006a4e;"></span> 
                    <?php 
                        printf( 
                            esc_html__( 'Transaction Audit Trail (%1$s - %2$s)', 'ifsedu-sms' ),
                            esc_html( date_i18n( 'd M Y', strtotime( $start_date ) ) ),
                            esc_html( date_i18n( 'd M Y', strtotime( $end_date ) ) )
                        ); 
                    ?>
                </h3>
                <button onclick="window.print()" class="dpt-btn-print no-print">
                    <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Financial Statement', 'ifsedu-sms' ); ?>
                </button>
            </div>

            <div class="dpt-table-wrapper">
                <table class="dpt-data-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date & Voucher', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Flow', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Category', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Student / Payer / Payee', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Method', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Net Payable (৳)', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Paid / Settled (৳)', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $combined_logs ) ) : foreach ( $combined_logs as $log ) : 
                            $is_income = ( $log->flow_type === 'Income' );
                            $status_class = 'dpt-badge-unpaid';
                            if ( $log->payment_status === 'Paid' ) {
                                $status_class = 'dpt-badge-paid';
                            } elseif ( $log->payment_status === 'Partial' ) {
                                $status_class = 'dpt-badge-partial';
                            }
                        ?>
                        <tr>
                            <td>
                                <strong style="color:#0f172a;"><?php echo esc_html( date_i18n( 'd M Y', strtotime( $log->trans_date ) ) ); ?></strong><br>
                                <span class="dpt-ref-badge">#<?php echo esc_html( $log->ref_code ); ?></span>
                            </td>
                            <td>
                                <span class="dpt-badge <?php echo $is_income ? 'dpt-badge-income' : 'dpt-badge-expense'; ?>">
                                    <?php echo esc_html( $log->flow_group ); ?>
                                </span>
                            </td>
                            <td><strong><?php echo esc_html( $log->category ); ?></strong></td>
                            <td>
                                <span style="font-weight:600; color:#1e293b;"><?php echo esc_html( $log->party_name ); ?></span>
                            </td>
                            <td>
                                <span style="background:#f8fafc; border:1px solid #e2e8f0; padding:2px 8px; border-radius:4px; font-weight:600; font-size:12px;">
                                    <?php echo esc_html( $log->payment_method ); ?>
                                </span>
                            </td>
                            <td>৳<?php echo esc_html( number_format( (float) $log->net_payable, 2 ) ); ?></td>
                            <td style="color:<?php echo $is_income ? '#006a4e' : '#dc2626'; ?>; font-weight:800;">
                                <?php echo $is_income ? '+' : '-'; ?>৳<?php echo esc_html( number_format( (float) $log->paid_amount, 2 ) ); ?>
                            </td>
                            <td>
                                <span class="dpt-badge <?php echo esc_attr( $status_class ); ?>">
                                    <?php echo esc_html( $log->payment_status ); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; else : ?>
                        <tr>
                            <td colspan="8" style="padding: 40px; text-align: center; color: #94a3b8;">
                                <span class="dashicons dashicons-chart-bar" style="font-size:32px; width:32px; height:32px; margin-bottom:8px;"></span>
                                <p style="margin:0; font-weight:600;"><?php esc_html_e( 'No financial transaction records matched your filter criteria.', 'ifsedu-sms' ); ?></p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    <?php
}