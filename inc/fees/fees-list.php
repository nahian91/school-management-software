<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_fees_list_view() {
    global $wpdb;
    $table_fees     = $wpdb->prefix . 'sms_fees';
    $table_students = $wpdb->prefix . 'sms_students';

    // Get all fees with student details
    $query = "SELECT f.*, s.full_name, s.student_id as s_id, s.class_name 
              FROM $table_fees f 
              LEFT JOIN $table_students s ON f.student_id = s.id 
              ORDER BY f.id DESC";
    $fees_records = $wpdb->get_results( $query );
    
    $collect_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=collect' );
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><span class="dashicons dashicons-money-alt"></span> Fee Collection & Due Tracking</h2>
        <a href="<?php echo esc_url( $collect_url ); ?>" class="btn btn-success" style="background-color: #10b981; border: none;">
            + Collect New Fee
        </a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border">
        <table class="table table-striped table-hover educore-datatable">
            <thead style="background-color: #f8fafc;">
                <tr>
                    <th>Invoice ID</th>
                    <th>Student Info</th>
                    <th>Month/Year</th>
                    <th>Fee Type</th>
                    <th>Net Amount</th>
                    <th>Paid</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $fees_records ) : foreach ( $fees_records as $fee ) : 
                    $print_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=print&invoice=' . $fee->invoice_id );
                    
                    // Determine Status Badge Color
                    if ($fee->payment_status === 'Paid') { $badge = 'bg-success'; }
                    elseif ($fee->payment_status === 'Partial') { $badge = 'bg-warning text-dark'; }
                    else { $badge = 'bg-danger'; }
                ?>
                <tr>
                    <td><strong>#<?php echo esc_html( $fee->invoice_id ); ?></strong></td>
                    <td>
                        <?php echo esc_html( $fee->full_name ); ?><br>
                        <small class="text-muted">ID: <?php echo esc_html( $fee->s_id ); ?> | <?php echo esc_html( $fee->class_name ); ?></small>
                    </td>
                    <td><?php echo esc_html( ucfirst($fee->fee_month) . ' ' . $fee->fee_year ); ?></td>
                    <td><?php echo esc_html( $fee->fee_type ); ?></td>
                    <td>৳<?php echo number_format( $fee->net_payable, 2 ); ?></td>
                    <td class="text-success fw-bold">৳<?php echo number_format( $fee->paid_amount, 2 ); ?></td>
                    <td class="text-danger fw-bold">৳<?php echo number_format( $fee->due_amount, 2 ); ?></td>
                    <td><span class="badge <?php echo $badge; ?>"><?php echo esc_html( $fee->payment_status ); ?></span></td>
                    <td>
                        <a href="<?php echo esc_url( $print_url ); ?>" class="btn btn-sm btn-secondary" target="_blank">
                            <span class="dashicons dashicons-printer" style="font-size: 16px; margin-top: 2px;"></span> Print
                        </a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.educore-datatable').DataTable({ "pageLength": 15, "ordering": false });
    });
    </script>
    <?php
}
?>