<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_fees_list_view() {
    global $wpdb;
    $table_fees     = $wpdb->prefix . 'sms_fees';
    $table_students = $wpdb->prefix . 'sms_students';

    // Capability Security Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view financial ledger records.', 'educore' ) );
    }

    // Aggregate Ledger Totals
    $totals = $wpdb->get_row( "SELECT 
        SUM(net_payable) as total_invoiced, 
        SUM(paid_amount) as total_collected, 
        SUM(due_amount) as total_due 
        FROM {$table_fees}" );

    // Get all fees with student details
    $query = "SELECT f.*, s.full_name, s.student_id as s_id, s.class_name 
              FROM {$table_fees} f 
              LEFT JOIN {$table_students} s ON f.student_id = s.id 
              ORDER BY f.id DESC";
    $fees_records = $wpdb->get_results( $query );
    
    $collect_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=collect' );
    ?>

    <!-- Financial Ledger Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="bg-white p-3 rounded shadow-sm border border-start border-primary border-4">
                <span class="text-muted fw-bold text-uppercase small">Total Invoiced Amount</span>
                <h3 class="fw-bold text-slate-800 m-0 mt-1">৳<?php echo number_format( $totals ? $totals->total_invoiced : 0, 2 ); ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="bg-white p-3 rounded shadow-sm border border-start border-success border-4">
                <span class="text-muted fw-bold text-uppercase small">Total Fees Collected</span>
                <h3 class="fw-bold text-success m-0 mt-1">৳<?php echo number_format( $totals ? $totals->total_collected : 0, 2 ); ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="bg-white p-3 rounded shadow-sm border border-start border-danger border-4">
                <span class="text-muted fw-bold text-uppercase small">Total Outstanding Dues</span>
                <h3 class="fw-bold text-danger m-0 mt-1">৳<?php echo number_format( $totals ? $totals->total_due : 0, 2 ); ?></h3>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-slate-800">
            <span class="dashicons dashicons-money-alt text-success me-1"></span> Fee Collection & Due Tracking
        </h2>
        <a href="<?php echo esc_url( $collect_url ); ?>" class="btn btn-success px-4 fw-bold" style="background-color: #006a4e; border: none;">
            + Collect New Fee
        </a>
    </div>

    <!-- Main Invoices Ledger Table -->
    <div class="bg-white p-4 rounded shadow-sm border">
        <table class="table table-striped table-hover align-middle educore-datatable w-100">
            <thead class="table-light">
                <tr>
                    <th style="width: 100px;">Invoice ID</th>
                    <th>Student Details</th>
                    <th>Month / Year</th>
                    <th>Fee Category</th>
                    <th>Net Payable</th>
                    <th>Paid</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th style="text-align: right; width: 100px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $fees_records ) ) : foreach ( $fees_records as $fee ) : 
                    $print_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=print&invoice=' . $fee->invoice_id );
                    
                    // Determine Status Badge Color
                    $badge_class = 'bg-danger';
                    if ( $fee->payment_status === 'Paid' )    { $badge_class = 'bg-success'; }
                    if ( $fee->payment_status === 'Partial' ) { $badge_class = 'bg-warning text-dark'; }
                ?>
                <tr>
                    <td><code>#<?php echo esc_html( $fee->invoice_id ); ?></code></td>
                    <td>
                        <strong class="text-slate-800"><?php echo esc_html( $fee->full_name ? $fee->full_name : 'N/A Record' ); ?></strong><br>
                        <small class="text-muted">ID: <?php echo esc_html( $fee->s_id ? $fee->s_id : 'Deleted' ); ?> | Class: <?php echo esc_html( $fee->class_name ? $fee->class_name : 'Unassigned' ); ?></small>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?php echo esc_html( ucfirst( $fee->fee_month ) . ' ' . $fee->fee_year ); ?></span></td>
                    <td><span class="fw-semibold text-slate-700"><?php echo esc_html( $fee->fee_type ); ?></span></td>
                    <td>৳<?php echo number_format( $fee->net_payable, 2 ); ?></td>
                    <td class="text-success fw-bold">৳<?php echo number_format( $fee->paid_amount, 2 ); ?></td>
                    <td class="text-danger fw-bold">৳<?php echo number_format( $fee->due_amount, 2 ); ?></td>
                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo esc_html( $fee->payment_status ); ?></span></td>
                    <td style="text-align: right;">
                        <a href="<?php echo esc_url( $print_url ); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="Print Invoice Receipt">
                            <span class="dashicons dashicons-printer" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span> Print
                        </a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if ($.fn.DataTable) {
            $('.educore-datatable').DataTable({ 
                "pageLength": 15, 
                "ordering": false,
                "responsive": true
            });
        }
    });
    </script>
    <?php
}
?>