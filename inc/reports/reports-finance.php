<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Premium Financial Analytics & Transaction Audit Module
 * Theme Aesthetic: Elite Neo-Bento Grid & Glassmorphism System
 * Custom Prefixes Applied: dpt-, afdp-
 */
function educore_reports_finance_view() {
    global $wpdb;
    $table_fees = $wpdb->prefix . 'sms_fees';

    $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : date('Y-m-01'); // 1st day of current month
    $end_date   = isset( $_GET['end_date'] )   ? sanitize_text_field( $_GET['end_date'] )   : date('Y-m-t');  // Last day of current month

    // Fetch Aggregated Financial Statistics
    $query = $wpdb->prepare( "
        SELECT 
            SUM(amount) as total_amount,
            SUM(discount) as total_discount,
            SUM(net_payable) as total_net,
            SUM(paid_amount) as total_paid,
            SUM(due_amount) as total_due
        FROM $table_fees 
        WHERE DATE(payment_date) BETWEEN %s AND %s
    ", $start_date, $end_date );
    
    $report = $wpdb->get_row( $query );

    // Fetch Detailed Transaction Audit Logs
    $logs = $wpdb->get_results( $wpdb->prepare( "
        SELECT invoice_id, fee_type, net_payable, paid_amount, due_amount, payment_status, payment_date 
        FROM $table_fees 
        WHERE DATE(payment_date) BETWEEN %s AND %s 
        ORDER BY payment_date DESC
    ", $start_date, $end_date ) );
    ?>

    <style>
        /* ==========================================================================
           FINANCE REPORTING SYSTEM - NEO-BENTO ARCHITECTURE
           ========================================================================== */
        .dpt-finance-root {
            margin: 20px 20px 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #0f172a;
        }

        /* Top Header Action Banner */
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
            letter-spacing: -0.5px;
        }

        .afdp-header-content h2 .dashicons {
            font-size: 26px;
            width: 26px;
            height: 26px;
            color: #10b981;
        }

        .afdp-header-content p {
            margin: 0;
            font-size: 13px;
            color: #94a3b8;
            font-weight: 500;
        }

        /* Filter Control Matrix */
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
            grid-template-columns: 1fr 1fr 200px;
            gap: 20px;
            align-items: flex-end;
        }

        @media (max-width: 768px) {
            .dpt-filter-grid {
                grid-template-columns: 1fr;
            }
        }

        .dpt-field-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dpt-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            letter-spacing: -0.1px;
        }

        .dpt-input-date {
            width: 100%;
            height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0 14px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            box-sizing: border-box;
            transition: all 0.2s;
        }

        .dpt-input-date:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
            outline: none;
        }

        .dpt-btn-generate {
            height: 42px;
            background: #006a4e;
            border: none;
            color: #ffffff;
            font-weight: 700;
            font-size: 13.5px;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .dpt-btn-generate:hover {
            background: #00523c;
            transform: translateY(-1px);
        }

        /* Neo-Bento Financial Metrics Matrix */
        .dpt-metrics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        @media (max-width: 991px) {
            .dpt-metrics-grid {
                grid-template-columns: 1fr;
            }
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

        .dpt-metric-card.dpt-card-emerald::before { background: #10b981; }
        .dpt-metric-card.dpt-card-rose::before { background: #f43f5e; }
        .dpt-metric-card.dpt-card-amber::before { background: #f59e0b; }

        .dpt-metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .dpt-metric-label {
            font-size: 12px;
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

        .dpt-card-emerald .dpt-metric-icon { background: #ecfdf5; color: #10b981; }
        .dpt-card-rose .dpt-metric-icon { background: #fff1f2; color: #f43f5e; }
        .dpt-card-amber .dpt-metric-icon { background: #fffbeb; color: #f59e0b; }

        .dpt-metric-value {
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.5px;
        }

        .dpt-card-emerald .dpt-metric-value { color: #047857; }
        .dpt-card-rose .dpt-metric-value { color: #be123c; }
        .dpt-card-amber .dpt-metric-value { color: #b45309; }

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
        }

        .dpt-data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13.5px;
        }

        .dpt-data-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
            white-space: nowrap;
        }

        .dpt-data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            text-align: center;
            vertical-align: middle;
        }

        .dpt-data-table tbody tr:hover td {
            background-color: #f8fafc;
        }

        .dpt-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .dpt-badge-paid { background: #d1fae5; color: #065f46; }
        .dpt-badge-partial { background: #fef3c7; color: #92400e; }
        .dpt-badge-unpaid { background: #ffe4e6; color: #9f1239; }

        /* ==========================================================================
           TACTICAL PRINT MEDIA STYLES
           ========================================================================== */
        @media print {
            body * {
                visibility: hidden;
            }
            .dpt-finance-root, .dpt-finance-root * {
                visibility: visible;
            }
            .dpt-finance-root {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
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
                    <span class="dashicons dashicons-chart-bar"></span> Financial Statement & Revenue Audit
                </h2>
                <p>Generate period-wise fee collection reports, pending dues breakdowns, and transaction logs.</p>
            </div>
        </div>

        <!-- Date Range Filter Card -->
        <div class="dpt-filter-card no-print">
            <form method="GET" action="">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="reports">
                <input type="hidden" name="sub" value="finance">
                
                <div class="dpt-filter-grid">
                    <div class="dpt-field-group">
                        <label class="dpt-label">Date From</label>
                        <input type="date" name="start_date" class="dpt-input-date" value="<?php echo esc_attr( $start_date ); ?>" required>
                    </div>
                    <div class="dpt-field-group">
                        <label class="dpt-label">Date To</label>
                        <input type="date" name="end_date" class="dpt-input-date" value="<?php echo esc_attr( $end_date ); ?>" required>
                    </div>
                    <button type="submit" class="dpt-btn-generate">
                        <span class="dashicons dashicons-filter"></span> Generate Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Metric Bento Cards -->
        <div class="dpt-metrics-grid">
            
            <!-- Card 1: Total Collected -->
            <div class="dpt-metric-card dpt-card-emerald">
                <div class="dpt-metric-header">
                    <span class="dpt-metric-label">Total Collection</span>
                    <div class="dpt-metric-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                </div>
                <div class="dpt-metric-value">
                    ৳<?php echo number_format( $report->total_paid ?? 0, 2 ); ?>
                </div>
            </div>

            <!-- Card 2: Total Pending Dues -->
            <div class="dpt-metric-card dpt-card-rose">
                <div class="dpt-metric-header">
                    <span class="dpt-metric-label">Total Pending Dues</span>
                    <div class="dpt-metric-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                </div>
                <div class="dpt-metric-value">
                    ৳<?php echo number_format( $report->total_due ?? 0, 2 ); ?>
                </div>
            </div>

            <!-- Card 3: Discounts Given -->
            <div class="dpt-metric-card dpt-card-amber">
                <div class="dpt-metric-header">
                    <span class="dpt-metric-label">Discounts Waived</span>
                    <div class="dpt-metric-icon">
                        <span class="dashicons dashicons-tag"></span>
                    </div>
                </div>
                <div class="dpt-metric-value">
                    ৳<?php echo number_format( $report->total_discount ?? 0, 2 ); ?>
                </div>
            </div>

        </div>

        <!-- Transaction Audit Log Table -->
        <div class="dpt-table-card">
            <div class="dpt-table-header">
                <h3 class="dpt-table-title">
                    <span class="dashicons dashicons-list-view" style="color:#006a4e;"></span> 
                    Transaction Logs (<?php echo esc_html( date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)) ); ?>)
                </h3>
                <button onclick="window.print()" class="dpt-btn-print no-print">
                    <span class="dashicons dashicons-printer"></span> Print Statement
                </button>
            </div>

            <div class="dpt-table-wrapper">
                <table class="dpt-data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice ID</th>
                            <th>Fee Category</th>
                            <th>Net Payable (৳)</th>
                            <th>Paid Amount (৳)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $logs ) : foreach ( $logs as $log ) : 
                            $status_class = 'dpt-badge-unpaid';
                            if ( $log->payment_status === 'Paid' ) {
                                $status_class = 'dpt-badge-paid';
                            } elseif ( $log->payment_status === 'Partial' ) {
                                $status_class = 'dpt-badge-partial';
                            }
                        ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($log->payment_date)); ?></td>
                            <td><strong><?php echo esc_html( $log->invoice_id ); ?></strong></td>
                            <td><?php echo esc_html( $log->fee_type ); ?></td>
                            <td><?php echo number_format( $log->net_payable, 2 ); ?></td>
                            <td style="color:#047857; font-weight:700;"><?php echo number_format( $log->paid_amount, 2 ); ?></td>
                            <td>
                                <span class="dpt-badge <?php echo esc_attr( $status_class ); ?>">
                                    <?php echo esc_html( $log->payment_status ); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; else : ?>
                        <tr>
                            <td colspan="6" style="padding: 30px; color: #94a3b8;">No transaction records found within this specific date range.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    <?php
}