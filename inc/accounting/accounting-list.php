<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Master Financial Ledger Table View (High-End Neo-Bento Design)
 * File: accounting-list.php
 */
function educore_accounting_list_view() {
    global $wpdb;

    $table_accounting = $wpdb->prefix . 'sms_accounting';

    // Filter handling
    $filter_type = isset( $_GET['entry_type'] ) ? sanitize_text_field( wp_unslash( $_GET['entry_type'] ) ) : 'all';

    // SQL Query construction based on filter
    $where_clause = "";
    if ( in_array( $filter_type, array( 'Income', 'Expense' ), true ) ) {
        $where_clause = $wpdb->prepare( " WHERE entry_type = %s", $filter_type );
    }

    // Fetch Ledger Records
    $ledger_records = $wpdb->get_results( "SELECT * FROM {$table_accounting}{$where_clause} ORDER BY entry_date DESC, id DESC" );

    // Overall Financial Totals
    $total_income  = (float) $wpdb->get_var( "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Income'" ) ?: 0.00;
    $total_expense = (float) $wpdb->get_var( "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Expense'" ) ?: 0.00;
    $net_balance   = $total_income - $total_expense;

    // Current Month Analytics
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

    // Base URL for Tab Navigation
    $base_tab_url = admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=list' );
    ?>

    <style>
        /* ==========================================================================
           ACCOUNTING LEDGER BENTO ARCHITECTURE
           ========================================================================== */
        .educore-acct-container {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        /* Top Metric Cards Matrix */
        .dpt-bento-grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
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
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s ease;
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

        .dpt-stat-card.income-card::before  { background: #10b981; }
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

        .dpt-stat-icon svg {
            width: 22px;
            height: 22px;
            fill: currentColor;
        }

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

        /* Bento Table Card */
        .dpt-bento-table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        /* Filter Toolbar Bar */
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

        .dpt-table-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
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

        .dpt-filter-pill-btn:hover {
            color: #0f172a;
        }

        .dpt-filter-pill-btn.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
        }

        /* Matrix Data Table System */
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
            padding: 14px 18px;
            border-bottom: 1px solid #e2e8f0;
        }

        .dpt-matrix-table td {
            padding: 16px 18px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: #334155;
            background: #ffffff;
            vertical-align: middle;
        }

        .dpt-matrix-table tr:last-child td {
            border-bottom: none;
        }

        .dpt-matrix-table tr:hover td {
            background: #f8fafc;
        }

        /* Styled Codes and Badges */
        .dpt-ref-code {
            background: #f1f5f9;
            color: #475569;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            border: 1px solid #cbd5e1;
        }

        .badge-type-income {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
            padding: 4px 12px;
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
            padding: 4px 12px;
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
            padding: 3px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            display: inline-block;
        }

        /* Deletion SVG Button */
        .afdp-action-btn-svg {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #64748b;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .afdp-action-btn-svg svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        .afdp-action-btn-svg.delete:hover {
            border-color: #ef4444;
            color: #ffffff;
            background: #ef4444;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
        }

        /* Empty State System */
        .dpt-empty-state-wrapper {
            padding: 50px 20px;
            text-align: center;
        }

        .dpt-empty-icon {
            width: 60px;
            height: 60px;
            background: #f1f5f9;
            color: #94a3b8;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .dpt-empty-icon svg {
            width: 28px;
            height: 28px;
            fill: currentColor;
        }
    </style>

    <div class="educore-acct-container">

        <!-- Top Metrics Bar -->
        <div class="dpt-bento-grid-stats">
            
            <!-- Income Total Card -->
            <div class="dpt-stat-card income-card">
                <div class="dpt-stat-icon" style="background: #ecfdf5; color: #10b981;">
                    <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 3.93 2.5.42 3 1.34 3 2.22 0 1.02-.9 1.83-2.7 1.83-2.1 0-2.88-.95-2.98-2.25H6.88c.11 2.25 1.77 3.45 3.62 3.97V21h3v-2.11c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-5.2-4.44z"/></svg>
                </div>
                <div class="dpt-stat-meta">
                    <span class="dpt-stat-label"><?php esc_html_e( 'Total Revenue (+)', 'ifsedu-sms' ); ?></span>
                    <span class="dpt-stat-value" style="color: #059669;">৳<?php echo esc_html( number_format( $total_income, 2 ) ); ?></span>
                </div>
            </div>

            <!-- Expenses Total Card -->
            <div class="dpt-stat-card expense-card">
                <div class="dpt-stat-icon" style="background: #fef2f2; color: #ef4444;">
                    <svg viewBox="0 0 24 24"><path d="M19 13H5v-2h14v2z"/></svg>
                </div>
                <div class="dpt-stat-meta">
                    <span class="dpt-stat-label"><?php esc_html_e( 'Total Expenses (-)', 'ifsedu-sms' ); ?></span>
                    <span class="dpt-stat-value" style="color: #dc2626;">৳<?php echo esc_html( number_format( $total_expense, 2 ) ); ?></span>
                </div>
            </div>

            <!-- Net Balance Card -->
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

            <!-- Current Month Net Card -->
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

        <!-- Master Financial Table Container -->
        <div class="dpt-bento-table-card">

            <!-- Toolbar Header -->
            <div class="dpt-table-header-toolbar">
                <h4 class="dpt-table-title">
                    <span class="dashicons dashicons-list-view" style="color: #10b981;"></span>
                    <?php esc_html_e( 'Master Financial Ledger Directory', 'ifsedu-sms' ); ?>
                </h4>

                <!-- Quick Category Filter Pills -->
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

            <!-- Responsive Table View -->
            <div class="dpt-table-responsive">
                <table class="dpt-matrix-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date & Voucher', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Flow Type', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Purpose & Category', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Method', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Amount', 'ifsedu-sms' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Action', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $ledger_records ) ) : foreach ( $ledger_records as $item ) : 
                            $delete_url = wp_nonce_url(
                                admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=delete&id=' . absint( $item->id ) ),
                                'delete_acct_' . $item->id
                            );
                            $is_income = ( $item->entry_type === 'Income' );
                        ?>
                            <tr>
                                <td>
                                    <strong style="color:#0f172a; font-weight: 700;"><?php echo esc_html( date_i18n( 'd M Y', strtotime( $item->entry_date ) ) ); ?></strong><br>
                                    <span class="dpt-ref-code"><?php echo esc_html( $item->voucher_no ); ?></span>
                                </td>
                                <td>
                                    <?php if ( $is_income ) : ?>
                                        <span class="badge-type-income">
                                            <span class="dashicons dashicons-arrow-up-alt2" style="font-size: 12px; width: 12px; height: 12px;"></span>
                                            <?php esc_html_e( 'Income', 'ifsedu-sms' ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="badge-type-expense">
                                            <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 12px; width: 12px; height: 12px;"></span>
                                            <?php esc_html_e( 'Expense', 'ifsedu-sms' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color:#0f172a; font-size:14px;"><?php echo esc_html( $item->title ); ?></strong>
                                    <?php if ( ! empty( $item->note ) ) : ?>
                                        <p style="margin: 2px 0 0 0; color: #64748b; font-size: 12px;"><?php echo esc_html( $item->note ); ?></p>
                                    <?php endif; ?>
                                    <div style="margin-top: 2px;"><small style="color: #64748b; font-weight: 600;"><?php echo esc_html( $item->category_name ); ?></small></div>
                                </td>
                                <td>
                                    <span class="dpt-payment-chip"><?php echo esc_html( $item->payment_method ); ?></span>
                                </td>
                                <td style="font-weight:800; font-size:15px; color: <?php echo $is_income ? '#059669' : '#dc2626'; ?>;">
                                    <?php echo $is_income ? '+' : '-'; ?>৳<?php echo esc_html( number_format( $item->amount, 2 ) ); ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?php echo esc_url( $delete_url ); ?>" class="afdp-action-btn-svg delete" title="<?php esc_attr_e( 'Delete Ledger Record', 'ifsedu-sms' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to permanently delete this transaction record?', 'ifsedu-sms' ) ); ?>');">
                                        <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr>
                                <td colspan="6">
                                    <div class="dpt-empty-state-wrapper">
                                        <div class="dpt-empty-icon">
                                            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                                        </div>
                                        <h4 style="margin: 0; color: #0f172a; font-weight: 700; font-size: 15px;"><?php esc_html_e( 'No Financial Records Found', 'ifsedu-sms' ); ?></h4>
                                        <p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;"><?php esc_html_e( 'There are no income or expense transactions matching your selection.', 'ifsedu-sms' ); ?></p>
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