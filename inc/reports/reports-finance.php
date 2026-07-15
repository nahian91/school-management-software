<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_reports_finance_view() {
    global $wpdb;
    $table_fees = $wpdb->prefix . 'sms_fees';

    $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : date('Y-m-01'); // Default: 1st day of current month
    $end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : date('Y-m-t'); // Default: Last day of current month

    // Fetch Aggregated Data
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

    // Fetch Detailed Logs for the table
    $logs = $wpdb->get_results( $wpdb->prepare( "
        SELECT invoice_id, fee_type, net_payable, paid_amount, payment_status, payment_date 
        FROM $table_fees 
        WHERE DATE(payment_date) BETWEEN %s AND %s 
        ORDER BY payment_date DESC
    ", $start_date, $end_date ) );
    ?>

    <div class="bg-white p-4 rounded shadow-sm border mb-4 no-print">
        <form method="GET" action="">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="reports">
            <input type="hidden" name="sub" value="finance">
            
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Date From</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo esc_attr( $start_date ); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Date To</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo esc_attr( $end_date ); ?>" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-success w-100" style="background-color: #10b981; border: none;">Generate Report</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="p-3 bg-light rounded border border-primary text-center">
                <h6 class="text-muted text-uppercase">Total Collection</h6>
                <h3 class="text-primary mb-0">৳<?php echo number_format( $report->total_paid ?? 0, 2 ); ?></h3>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="p-3 bg-light rounded border border-danger text-center">
                <h6 class="text-muted text-uppercase">Total Pending Dues</h6>
                <h3 class="text-danger mb-0">৳<?php echo number_format( $report->total_due ?? 0, 2 ); ?></h3>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="p-3 bg-light rounded border border-warning text-center">
                <h6 class="text-muted text-uppercase">Discounts Given</h6>
                <h3 class="text-warning mb-0">৳<?php echo number_format( $report->total_discount ?? 0, 2 ); ?></h3>
            </div>
        </div>
    </div>

    <!-- Detailed Transactions -->
    <div class="bg-white p-4 rounded shadow-sm border">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 text-primary">Transaction Logs</h5>
            <button onclick="window.print()" class="btn btn-sm btn-secondary no-print"><span class="dashicons dashicons-printer"></span> Print Report</button>
        </div>
        <table class="table table-bordered table-striped text-center">
            <thead style="background-color: #f8fafc;">
                <tr>
                    <th>Date</th>
                    <th>Invoice ID</th>
                    <th>Fee Type</th>
                    <th>Net Payable (৳)</th>
                    <th>Paid Amount (৳)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $logs ) : foreach ( $logs as $log ) : ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($log->payment_date)); ?></td>
                    <td><strong><?php echo esc_html( $log->invoice_id ); ?></strong></td>
                    <td><?php echo esc_html( $log->fee_type ); ?></td>
                    <td><?php echo number_format( $log->net_payable, 2 ); ?></td>
                    <td class="text-success fw-bold"><?php echo number_format( $log->paid_amount, 2 ); ?></td>
                    <td>
                        <span class="badge <?php echo $log->payment_status === 'Paid' ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo esc_html( $log->payment_status ); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; else : ?>
                <tr><td colspan="6" class="text-muted">No transactions found in this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <style>
        @media print {
            body * { visibility: hidden; }
            .educore-right-box, .educore-right-box * { visibility: visible; }
            .educore-right-box { position: absolute; left: 0; top: 0; width: 100%; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
    <?php
}
?>