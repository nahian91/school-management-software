<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Fees Directory & Financial Ledger View Engine
 * File: fees-list.php
 * Theme Aesthetic: Elite Neo-Bento UI
 * Custom Prefixes Applied: dpt-, afdp-
 */
function educore_fees_list_view() {
    global $wpdb;
    $table_fees     = $wpdb->prefix . 'sms_fees';
    $table_students = $wpdb->prefix . 'sms_students';

    // 1. Capability Security Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view financial ledger records.', 'ifsedu-sms' ) );
    }

    // 2. Aggregate Ledger Totals
    $totals = $wpdb->get_row( "SELECT 
        SUM(net_payable) as total_invoiced, 
        SUM(paid_amount) as total_collected, 
        SUM(due_amount) as total_due 
        FROM {$table_fees}" );

    // 3. Fetch Ledger Records
    $query = "SELECT f.*, s.full_name, s.student_id as s_id, s.class_name 
              FROM {$table_fees} f 
              LEFT JOIN {$table_students} s ON f.student_id = s.id 
              ORDER BY f.id DESC";
    $fees_records = $wpdb->get_results( $query );
    
    $collect_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=collect' );
    ?>

    <style>
        /* ==========================================================================
           FEES LEDGER - NEO-BENTO ARCHITECTURE
           ========================================================================== */
        .dpt-fees-list-container {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #0f172a;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
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

        /* DataTables Custom Theme Fix */
        .dataTables_wrapper .dataTables_filter input {
            padding: 6px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            outline: none;
            margin-left: 8px;
        }

        .dataTables_wrapper .dataTables_length select {
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
        }
    </style>

    <div class="dpt-fees-list-container">

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
                        <th style="text-align: right; width: 90px;"><?php esc_html_e( 'Action', 'ifsedu-sms' ); ?></th>
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
                    ?>
                    <tr>
                        <td>
                            <span class="dpt-invoice-code">#<?php echo esc_html( $fee->invoice_id ); ?></span>
                        </td>
                        <td>
                            <strong style="color: #0f172a;"><?php echo esc_html( $fee->full_name ? $fee->full_name : 'N/A Record' ); ?></strong><br>
                            <span style="font-size: 11.5px; color: #64748b;">
                                <?php echo esc_html( sprintf( 'ID: %s | Class: %s', $fee->s_id ? $fee->s_id : 'Deleted', $fee->class_name ? $fee->class_name : 'Unassigned' ) ); ?>
                            </span>
                        </td>
                        <td>
                            <span style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 11.5px;">
                                <?php echo esc_html( ucfirst( $fee->fee_month ) . ' ' . $fee->fee_year ); ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: #475569;"><?php echo esc_html( $fee->fee_type ); ?></strong>
                        </td>
                        <td>৳<?php echo esc_html( number_format( $fee->net_payable, 2 ) ); ?></td>
                        <td><strong style="color: #006a4e;">৳<?php echo esc_html( number_format( $fee->paid_amount, 2 ) ); ?></strong></td>
                        <td><strong style="color: #dc2626;">৳<?php echo esc_html( number_format( $fee->due_amount, 2 ) ); ?></strong></td>
                        <td>
                            <span class="afdp-status-badge <?php echo esc_attr( $status_class ); ?>">
                                <?php echo esc_html( $fee->payment_status ); ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <a href="<?php echo esc_url( $print_url ); ?>" class="dpt-btn-action-print" target="_blank" title="<?php esc_attr_e( 'Print Invoice Receipt', 'ifsedu-sms' ); ?>">
                                <span class="dashicons dashicons-printer" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                <?php esc_html_e( 'Print', 'ifsedu-sms' ); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- DataTable Execution Engine -->
    <script type="text/javascript">
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