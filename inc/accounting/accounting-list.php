<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Master Financial Ledger Table View (Enterprise Neo-Bento Dashboard)
 * File: accounting-list.php
 */
function educore_accounting_list_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access the financial ledger.', 'ifsedu-sms' ) );
    }

    global $wpdb;
    $table_accounting = $wpdb->prefix . 'sms_accounting';

    // --------------------------------------------------------------------------
    // 1. FILTER & SEARCH QUERY PROCESSING
    // --------------------------------------------------------------------------
    $filter_type     = isset( $_GET['entry_type'] ) ? sanitize_text_field( wp_unslash( $_GET['entry_type'] ) ) : 'all';
    $filter_category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
    $filter_method   = isset( $_GET['payment_method'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_method'] ) ) : '';
    $search_query    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    $from_date       = isset( $_GET['from_date'] ) ? sanitize_text_field( wp_unslash( $_GET['from_date'] ) ) : '';
    $to_date         = isset( $_GET['to_date'] ) ? sanitize_text_field( wp_unslash( $_GET['to_date'] ) ) : '';

    $where_clauses = array();
    $query_params  = array();

    if ( in_array( $filter_type, array( 'Income', 'Expense' ), true ) ) {
        $where_clauses[] = "entry_type = %s";
        $query_params[]  = $filter_type;
    }

    if ( ! empty( $filter_category ) ) {
        $where_clauses[] = "category_name = %s";
        $query_params[]  = $filter_category;
    }

    if ( ! empty( $filter_method ) ) {
        $where_clauses[] = "payment_method = %s";
        $query_params[]  = $filter_method;
    }

    if ( ! empty( $from_date ) ) {
        $where_clauses[] = "entry_date >= %s";
        $query_params[]  = $from_date;
    }

    if ( ! empty( $to_date ) ) {
        $where_clauses[] = "entry_date <= %s";
        $query_params[]  = $to_date;
    }

    if ( ! empty( $search_query ) ) {
        $where_clauses[] = "(title LIKE %s OR voucher_no LIKE %s OR party_name LIKE %s OR note LIKE %s)";
        $search_like     = '%' . $wpdb->esc_like( $search_query ) . '%';
        $query_params[]  = $search_like;
        $query_params[]  = $search_like;
        $query_params[]  = $search_like;
        $query_params[]  = $search_like;
    }

    $where_sql = ! empty( $where_clauses ) ? ' WHERE ' . implode( ' AND ', $where_clauses ) : '';

    // Fetch Filtered Ledger Records
    $sql_query = "SELECT * FROM {$table_accounting}{$where_sql} ORDER BY entry_date DESC, id DESC";
    if ( ! empty( $query_params ) ) {
        $ledger_records = $wpdb->get_results( $wpdb->prepare( $sql_query, ...$query_params ) );
    } else {
        $ledger_records = $wpdb->get_results( $sql_query );
    }

    // Dynamic Categories for Dropdown
    $available_categories = $wpdb->get_col( "SELECT DISTINCT category_name FROM {$table_accounting} WHERE category_name != '' ORDER BY category_name ASC" );

    // --------------------------------------------------------------------------
    // 2. FINANCIAL METRICS & ANALYTICS
    // --------------------------------------------------------------------------
    $total_income  = (float) $wpdb->get_var( "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Income'" ) ?: 0.00;
    $total_expense = (float) $wpdb->get_var( "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Expense'" ) ?: 0.00;
    $net_balance   = $total_income - $total_expense;

    $current_month_start = current_time( 'Y-m-01' );
    $current_month_end   = current_time( 'Y-m-t' );

    $month_income = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Income' AND entry_date BETWEEN %s AND %s",
        $current_month_start, $current_month_end
    ) ) ?: 0.00;

    $month_expense = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Expense' AND entry_date BETWEEN %s AND %s",
        $current_month_start, $current_month_end
    ) ) ?: 0.00;

    $month_net = $month_income - $month_expense;

    // Navigation URLs
    $base_tab_url = admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=list' );
    $add_new_url  = admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=add' );
    ?>

    <style>
        .educore-acct-container {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            margin: 20px 20px 0 0;
        }

        /* Top Action / Headline Bar */
        .dpt-header-headline-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 14px;
        }

        .dpt-page-title {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dpt-btn-action {
            height: 40px;
            padding: 0 18px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }

        .dpt-btn-primary {
            background: #006a4e;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }
        .dpt-btn-primary:hover { background: #00523c; color: #ffffff; }

        .dpt-btn-secondary {
            background: #ffffff;
            border-color: #cbd5e1;
            color: #475569;
        }
        .dpt-btn-secondary:hover { background: #f8fafc; color: #0f172a; }

        /* Metric Bento Grid Matrix */
        .dpt-bento-grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .dpt-stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dpt-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -4px rgba(0, 0, 0, 0.06);
        }

        .dpt-stat-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }

        .dpt-stat-card.income-card::before  { background: #006a4e; }
        .dpt-stat-card.expense-card::before { background: #ef4444; }
        .dpt-stat-card.net-card::before     { background: #3b82f6; }
        .dpt-stat-card.month-card::before   { background: #0284c7; }

        .dpt-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .dpt-stat-icon svg { width: 22px; height: 22px; fill: currentColor; }

        .dpt-stat-meta {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .dpt-stat-label {
            font-size: 11.5px;
            color: #64748b;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dpt-stat-value {
            font-size: 22px;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.5px;
        }

        /* Filter Bento Box */
        .dpt-filter-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }

        .dpt-filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px;
            align-items: flex-end;
        }

        .dpt-filter-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dpt-filter-field label {
            font-size: 11.5px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
        }

        .dpt-filter-field input, .dpt-filter-field select {
            height: 38px;
            padding: 0 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            color: #0f172a;
            background: #f8fafc;
            width: 100%;
            box-sizing: border-box;
        }

        .dpt-filter-field input:focus, .dpt-filter-field select:focus {
            border-color: #006a4e;
            background: #ffffff;
            outline: none;
        }

        /* Table Card Container */
        .dpt-bento-table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .dpt-table-header-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            padding-bottom: 18px;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 20px;
        }

        .dpt-filter-pills {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #f8fafc;
            padding: 4px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        .dpt-filter-pill-btn {
            padding: 6px 14px;
            border-radius: 7px;
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
            color: #64748b;
            transition: all 0.2s ease;
        }

        .dpt-filter-pill-btn:hover { color: #0f172a; }
        .dpt-filter-pill-btn.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
        }

        /* Matrix Table */
        .dpt-table-responsive {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .dpt-matrix-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
        }

        .dpt-matrix-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .dpt-matrix-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: #334155;
            background: #ffffff;
            vertical-align: middle;
        }

        .dpt-matrix-table tr:last-child td { border-bottom: none; }
        .dpt-matrix-table tr:hover td { background: #f8fafc; }

        .dpt-ref-code {
            background: #f1f5f9;
            color: #0f172a;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 11.5px;
            font-weight: 700;
            border: 1px solid #cbd5e1;
        }

        .badge-type-income {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-type-expense {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .dpt-payment-chip {
            background: #f8fafc;
            color: #475569;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            display: inline-block;
        }

        .dpt-row-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .dpt-action-btn-sm {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #64748b;
            transition: all 0.2s ease;
            text-decoration: none;
            cursor: pointer;
        }

        .dpt-action-btn-sm.edit:hover { background: #006a4e; color: #ffffff; border-color: #006a4e; }
        .dpt-action-btn-sm.delete:hover { background: #dc2626; color: #ffffff; border-color: #dc2626; }
        .dpt-action-btn-sm.attachment:hover { background: #2563eb; color: #ffffff; border-color: #2563eb; }

        .dpt-feedback-banner {
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dpt-feedback-banner.success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    </style>

    <div class="educore-acct-container">

        <!-- Top Headline & Action -->
        <div class="dpt-header-headline-bar">
            <h2 class="dpt-page-title">
                <span class="dashicons dashicons-money-alt" style="font-size:26px; width:26px; height:26px; color:#006a4e;"></span>
                <?php esc_html_e( 'General Accounting & Financial Ledger', 'ifsedu-sms' ); ?>
            </h2>
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="window.print();" class="dpt-btn-action dpt-btn-secondary">
                    <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Report', 'ifsedu-sms' ); ?>
                </button>
                <a href="<?php echo esc_url( $add_new_url ); ?>" class="dpt-btn-action dpt-btn-primary">
                    <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Record Transaction', 'ifsedu-sms' ); ?>
                </a>
            </div>
        </div>

        <!-- Feedback Alert Messages -->
        <?php if ( isset( $_GET['msg'] ) ) : 
            $msg = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
        ?>
            <?php if ( $msg === 'success' ) : ?>
                <div class="dpt-feedback-banner success">
                    <span class="dashicons dashicons-yes-alt" style="color:#006a4e;"></span>
                    <span><?php esc_html_e( 'Financial transaction recorded successfully.', 'ifsedu-sms' ); ?></span>
                </div>
            <?php elseif ( $msg === 'updated' ) : ?>
                <div class="dpt-feedback-banner success" style="background:#eff6ff; border-color:#bfdbfe; color:#1e40af;">
                    <span class="dashicons dashicons-saved" style="color:#2563eb;"></span>
                    <span><?php esc_html_e( 'Ledger entry updated successfully.', 'ifsedu-sms' ); ?></span>
                </div>
            <?php elseif ( $msg === 'deleted' ) : ?>
                <div class="dpt-feedback-banner success" style="background:#fef2f2; border-color:#fecaca; color:#991b1b;">
                    <span class="dashicons dashicons-trash" style="color:#dc2626;"></span>
                    <span><?php esc_html_e( 'Transaction record deleted permanently.', 'ifsedu-sms' ); ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Top Metrics Stats Grid -->
        <div class="dpt-bento-grid-stats">
            <div class="dpt-stat-card income-card">
                <div class="dpt-stat-icon" style="background: #ecfdf5; color: #006a4e;">
                    <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 3.93 2.5.42 3 1.34 3 2.22 0 1.02-.9 1.83-2.7 1.83-2.1 0-2.88-.95-2.98-2.25H6.88c.11 2.25 1.77 3.45 3.62 3.97V21h3v-2.11c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-5.2-4.44z"/></svg>
                </div>
                <div class="dpt-stat-meta">
                    <span class="dpt-stat-label"><?php esc_html_e( 'Total Revenue (+)', 'ifsedu-sms' ); ?></span>
                    <span class="dpt-stat-value" style="color: #059669;">৳<?php echo esc_html( number_format( $total_income, 2 ) ); ?></span>
                </div>
            </div>

            <div class="dpt-stat-card expense-card">
                <div class="dpt-stat-icon" style="background: #fef2f2; color: #ef4444;">
                    <svg viewBox="0 0 24 24"><path d="M19 13H5v-2h14v2z"/></svg>
                </div>
                <div class="dpt-stat-meta">
                    <span class="dpt-stat-label"><?php esc_html_e( 'Total Expenses (-)', 'ifsedu-sms' ); ?></span>
                    <span class="dpt-stat-value" style="color: #dc2626;">৳<?php echo esc_html( number_format( $total_expense, 2 ) ); ?></span>
                </div>
            </div>

            <div class="dpt-stat-card net-card">
                <div class="dpt-stat-icon" style="background: #eff6ff; color: #3b82f6;">
                    <svg viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H3c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h16c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
                </div>
                <div class="dpt-stat-meta">
                    <span class="dpt-stat-label"><?php esc_html_e( 'Net Cash Balance', 'ifsedu-sms' ); ?></span>
                    <span class="dpt-stat-value" style="color: <?php echo $net_balance >= 0 ? '#059669' : '#dc2626'; ?>;">
                        ৳<?php echo esc_html( number_format( $net_balance, 2 ) ); ?>
                    </span>
                </div>
            </div>

            <div class="dpt-stat-card month-card">
                <div class="dpt-stat-icon" style="background: #f0f9ff; color: #0284c7;">
                    <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                </div>
                <div class="dpt-stat-meta">
                    <span class="dpt-stat-label"><?php esc_html_e( 'Current Month Net', 'ifsedu-sms' ); ?></span>
                    <span class="dpt-stat-value" style="color: #0284c7;">৳<?php echo esc_html( number_format( $month_net, 2 ) ); ?></span>
                </div>
            </div>
        </div>

        <!-- Filter Controls Bento Box -->
        <div class="dpt-filter-bento-card">
            <form method="GET" action="" class="dpt-filter-grid">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="accounting">
                <input type="hidden" name="sub" value="list">

                <!-- Search Input -->
                <div class="dpt-filter-field" style="grid-column: span 2;">
                    <label><?php esc_html_e( 'Search Keyword', 'ifsedu-sms' ); ?></label>
                    <input type="text" name="s" placeholder="<?php esc_attr_e( 'Search Title, Voucher No, or Payer/Payee...', 'ifsedu-sms' ); ?>" value="<?php echo esc_attr( $search_query ); ?>">
                </div>

                <!-- Category -->
                <div class="dpt-filter-field">
                    <label><?php esc_html_e( 'Category', 'ifsedu-sms' ); ?></label>
                    <select name="category">
                        <option value=""><?php esc_html_e( '-- All Categories --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $available_categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $filter_category, $cat ); ?>><?php echo esc_html( $cat ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Payment Method -->
                <div class="dpt-filter-field">
                    <label><?php esc_html_e( 'Method', 'ifsedu-sms' ); ?></label>
                    <select name="payment_method">
                        <option value=""><?php esc_html_e( '-- All Methods --', 'ifsedu-sms' ); ?></option>
                        <option value="Cash" <?php selected( $filter_method, 'Cash' ); ?>>Cash</option>
                        <option value="Bank Transfer" <?php selected( $filter_method, 'Bank Transfer' ); ?>>Bank Transfer</option>
                        <option value="bKash" <?php selected( $filter_method, 'bKash' ); ?>>bKash</option>
                        <option value="Nagad" <?php selected( $filter_method, 'Nagad' ); ?>>Nagad</option>
                        <option value="Cheque" <?php selected( $filter_method, 'Cheque' ); ?>>Cheque</option>
                    </select>
                </div>

                <!-- From Date -->
                <div class="dpt-filter-field">
                    <label><?php esc_html_e( 'From Date', 'ifsedu-sms' ); ?></label>
                    <input type="date" name="from_date" value="<?php echo esc_attr( $from_date ); ?>">
                </div>

                <!-- To Date -->
                <div class="dpt-filter-field">
                    <label><?php esc_html_e( 'To Date', 'ifsedu-sms' ); ?></label>
                    <input type="date" name="to_date" value="<?php echo esc_attr( $to_date ); ?>">
                </div>

                <!-- Submit & Reset Action -->
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="dpt-btn-action dpt-btn-primary" style="height:38px; width:100%;">
                        <span class="dashicons dashicons-filter"></span> Filter
                    </button>
                    <a href="<?php echo esc_url( $base_tab_url ); ?>" class="dpt-btn-action dpt-btn-secondary" style="height:38px;">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Master Financial Table Container -->
        <div class="dpt-bento-table-card">

            <div class="dpt-table-header-toolbar">
                <h4 class="dpt-page-title" style="font-size:16px;">
                    <span class="dashicons dashicons-list-view" style="color: #006a4e;"></span>
                    <?php esc_html_e( 'Financial Ledger Entries', 'ifsedu-sms' ); ?>
                </h4>

                <div class="dpt-filter-pills">
                    <a href="<?php echo esc_url( add_query_arg( 'entry_type', 'all', $base_tab_url ) ); ?>" class="dpt-filter-pill-btn <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                        <?php esc_html_e( 'All Entries', 'ifsedu-sms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'entry_type', 'Income', $base_tab_url ) ); ?>" class="dpt-filter-pill-btn <?php echo $filter_type === 'Income' ? 'active' : ''; ?>">
                        <?php esc_html_e( 'Incomes', 'ifsedu-sms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'entry_type', 'Expense', $base_tab_url ) ); ?>" class="dpt-filter-pill-btn <?php echo $filter_type === 'Expense' ? 'active' : ''; ?>">
                        <?php esc_html_e( 'Expenses', 'ifsedu-sms' ); ?>
                    </a>
                </div>
            </div>

            <!-- Table Matrix -->
            <div class="dpt-table-responsive">
                <table class="dpt-matrix-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date & Voucher', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Flow', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Particulars / Title', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Payer / Payee', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Method', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Amount', 'ifsedu-sms' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Actions', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $ledger_records ) ) : foreach ( $ledger_records as $item ) : 
                            $edit_url   = admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=add&sub_mode=edit&id=' . absint( $item->id ) );
                            $delete_url = wp_nonce_url(
                                admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=delete&id=' . absint( $item->id ) ),
                                'delete_acct_' . $item->id
                            );
                            $is_income = ( $item->entry_type === 'Income' );
                        ?>
                            <tr>
                                <td>
                                    <strong style="color:#0f172a; font-weight:700;"><?php echo esc_html( date_i18n( 'd M Y', strtotime( $item->entry_date ) ) ); ?></strong><br>
                                    <span class="dpt-ref-code"><?php echo esc_html( $item->voucher_no ); ?></span>
                                </td>
                                <td>
                                    <?php if ( $is_income ) : ?>
                                        <span class="badge-type-income">
                                            <span class="dashicons dashicons-arrow-up-alt2" style="font-size:12px; width:12px; height:12px;"></span> Income
                                        </span>
                                    <?php else : ?>
                                        <span class="badge-type-expense">
                                            <span class="dashicons dashicons-arrow-down-alt2" style="font-size:12px; width:12px; height:12px;"></span> Expense
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color:#0f172a; font-size:13.5px;"><?php echo esc_html( $item->title ); ?></strong>
                                    <div style="margin-top:2px; font-size:12px; color:#64748b; font-weight:600;">
                                        <?php echo esc_html( $item->category_name ); ?>
                                    </div>
                                    <?php if ( ! empty( $item->note ) ) : ?>
                                        <p style="margin:3px 0 0 0; color:#94a3b8; font-size:11.5px;"><?php echo esc_html( $item->note ); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( ! empty( $item->party_name ) ) : ?>
                                        <span style="font-weight:700; color:#334155;"><?php echo esc_html( $item->party_name ); ?></span>
                                    <?php else : ?>
                                        <span style="color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="dpt-payment-chip"><?php echo esc_html( $item->payment_method ); ?></span>
                                </td>
                                <td style="font-weight:800; font-size:15px; color: <?php echo $is_income ? '#059669' : '#dc2626'; ?>;">
                                    <?php echo $is_income ? '+' : '-'; ?>৳<?php echo esc_html( number_format( $item->amount, 2 ) ); ?>
                                </td>
                                <td style="text-align: right;">
                                    <div class="dpt-row-actions">
                                        <?php if ( ! empty( $item->attachment_url ) ) : ?>
                                            <a href="<?php echo esc_url( $item->attachment_url ); ?>" target="_blank" class="dpt-action-btn-sm attachment" title="<?php esc_attr_e( 'View Attached Bill / Slip', 'ifsedu-sms' ); ?>">
                                                <span class="dashicons dashicons-media-document"></span>
                                            </a>
                                        <?php endif; ?>

                                        <a href="<?php echo esc_url( $edit_url ); ?>" class="dpt-action-btn-sm edit" title="<?php esc_attr_e( 'Edit Entry', 'ifsedu-sms' ); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>

                                        <a href="<?php echo esc_url( $delete_url ); ?>" class="dpt-action-btn-sm delete" title="<?php esc_attr_e( 'Delete Ledger Record', 'ifsedu-sms' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to permanently delete this transaction record?', 'ifsedu-sms' ) ); ?>');">
                                            <span class="dashicons dashicons-trash"></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr>
                                <td colspan="7">
                                    <div style="padding:40px 20px; text-align:center;">
                                        <span class="dashicons dashicons-money-alt" style="font-size:36px; width:36px; height:36px; color:#cbd5e1; margin-bottom:8px;"></span>
                                        <h4 style="margin:0; color:#0f172a; font-weight:700;"><?php esc_html_e( 'No Financial Records Found', 'ifsedu-sms' ); ?></h4>
                                        <p style="margin:4px 0 0 0; color:#64748b; font-size:13px;"><?php esc_html_e( 'No income or expense transactions matched your current search parameters.', 'ifsedu-sms' ); ?></p>
                                    </div>
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