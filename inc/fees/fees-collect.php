<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_fees_collect_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_fees     = $wpdb->prefix . 'sms_fees';

    // Handle Form Submission
    if ( isset( $_POST['educore_collect_fee'] ) && wp_verify_nonce( $_POST['educore_fee_nonce'], 'collect_fee_action' ) ) {
        
        $amount      = floatval( $_POST['amount'] );
        $discount    = floatval( $_POST['discount'] );
        $paid_amount = floatval( $_POST['paid_amount'] );
        
        $net_payable = $amount - $discount;
        $due_amount  = $net_payable - $paid_amount;
        
        // Determine Status
        $payment_status = 'Unpaid';
        if ( $paid_amount >= $net_payable ) {
            $payment_status = 'Paid';
            $due_amount = 0; // Fix negative due
        } elseif ( $paid_amount > 0 && $paid_amount < $net_payable ) {
            $payment_status = 'Partial';
        }

        // Generate Unique Invoice ID (e.g., INV-2607-12345)
        $invoice_id = 'INV-' . date('ym') . '-' . rand(10000, 99999);

        $data = array(
            'invoice_id'     => $invoice_id,
            'student_id'     => intval( $_POST['student_id'] ),
            'fee_month'      => sanitize_text_field( $_POST['fee_month'] ),
            'fee_year'       => sanitize_text_field( $_POST['fee_year'] ),
            'fee_type'       => sanitize_text_field( $_POST['fee_type'] ),
            'amount'         => $amount,
            'discount'       => $discount,
            'net_payable'    => $net_payable,
            'paid_amount'    => $paid_amount,
            'due_amount'     => $due_amount,
            'payment_status' => $payment_status,
            'payment_method' => sanitize_text_field( $_POST['payment_method'] ),
            'payment_date'   => current_time( 'mysql' ),
            'collected_by'   => get_current_user_id()
        );

        $wpdb->insert( $table_fees, $data );
        
        if ( function_exists('educore_log_activity') ) {
            educore_log_activity("Collected fee (Invoice: {$invoice_id}) Amount: {$paid_amount}");
        }

        // Redirect to Print Page
        $print_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=print&invoice=' . $invoice_id );
        echo '<script>window.location.href="' . esc_url( $print_url ) . '";</script>';
        exit;
    }

    $students = $wpdb->get_results( "SELECT id, full_name, student_id, class_name FROM $table_students WHERE status = 'Active' ORDER BY class_name ASC, full_name ASC" );
    $back_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=list' );
    
    // Arrays for dropdowns
    $months = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
    $current_month = date('F');
    $current_year  = date('Y');
    ?>

    <div class="mb-3">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; Back to List</a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border" style="max-width: 800px; margin: 0 auto;">
        <h3 class="border-bottom pb-2 mb-4 text-primary">Collect New Fee</h3>
        
        <form method="POST" action="">
            <?php wp_nonce_field( 'collect_fee_action', 'educore_fee_nonce' ); ?>
            
            <div class="mb-4">
                <label class="form-label fw-bold">Select Student</label>
                <select name="student_id" class="form-control" required style="max-height: 200px;">
                    <option value="">-- Search & Select Student --</option>
                    <?php foreach ( $students as $s ) : ?>
                        <option value="<?php echo esc_attr( $s->id ); ?>">
                            <?php echo esc_html( $s->full_name . ' (ID: ' . $s->student_id . ') - ' . $s->class_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Fee Type</label>
                    <select name="fee_type" class="form-control" required>
                        <option value="Tuition Fee">Tuition Fee</option>
                        <option value="Admission Fee">Admission Fee</option>
                        <option value="Exam Fee">Exam Fee</option>
                        <option value="Transport Fee">Transport Fee</option>
                        <option value="Other Charges">Other Charges</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">For Month</label>
                    <select name="fee_month" class="form-control" required>
                        <?php foreach ( $months as $m ) : ?>
                            <option value="<?php echo esc_attr($m); ?>" <?php selected($current_month, $m); ?>><?php echo esc_html($m); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">For Year</label>
                    <input type="number" name="fee_year" class="form-control" value="<?php echo esc_attr($current_year); ?>" required>
                </div>
            </div>

            <div class="row border p-3 bg-light rounded mb-4">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold text-dark">Total Amount (৳)</label>
                    <input type="number" step="0.01" name="amount" id="fee_amount" class="form-control" value="0" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold text-danger">Discount/Waiver (৳)</label>
                    <input type="number" step="0.01" name="discount" id="fee_discount" class="form-control" value="0">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold text-success">Net Payable (৳)</label>
                    <input type="number" id="fee_net" class="form-control" value="0" readonly style="background-color: #e2e8f0; font-weight: bold;">
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Payment Method</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="Cash">Cash</option>
                        <option value="bKash">bKash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-success">Actually Paid Amount (৳)</label>
                    <input type="number" step="0.01" name="paid_amount" id="fee_paid" class="form-control border-success" required>
                    <small class="text-muted">If paid less than Net Payable, it will be marked as Due.</small>
                </div>
            </div>

            <button type="submit" name="educore_collect_fee" class="btn btn-success w-100 py-2 fs-5" style="background-color: #10b981; border: none;">
                Receive Payment & Print Receipt
            </button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const amtInput = document.getElementById('fee_amount');
        const discInput = document.getElementById('fee_discount');
        const netInput = document.getElementById('fee_net');
        const paidInput = document.getElementById('fee_paid');

        function calc() {
            let a = parseFloat(amtInput.value) || 0;
            let d = parseFloat(discInput.value) || 0;
            let n = a - d;
            netInput.value = n > 0 ? n.toFixed(2) : 0;
            // Optionally auto-fill paid amount
            // paidInput.value = netInput.value; 
        }

        amtInput.addEventListener('input', calc);
        discInput.addEventListener('input', calc);
    });
    </script>
    <?php
}
?>