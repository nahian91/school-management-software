<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_fees_collect_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_fees     = $wpdb->prefix . 'sms_fees';

    // Secure Mutation Engine Form Submission Handler
    if ( isset( $_POST['educore_collect_fee'] ) && wp_verify_nonce( $_POST['educore_fee_nonce'], 'collect_fee_action' ) ) {
        
        $amount      = floatval( $_POST['amount'] );
        $discount    = floatval( $_POST['discount'] );
        $paid_amount = floatval( $_POST['paid_amount'] );
        
        $net_payable = $amount - $discount;
        $due_amount  = $net_payable - $paid_amount;
        
        // Strict Business Status Logic Check Loop
        $payment_status = 'Unpaid';
        if ( $paid_amount >= $net_payable ) {
            $payment_status = 'Paid';
            $due_amount = 0; // Negative boundary condition recovery
        } elseif ( $paid_amount > 0 && $paid_amount < $net_payable ) {
            $payment_status = 'Partial';
        }

        // Generate Unique Invoice Alpha ID Structure
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
            educore_log_activity("Collected fee invoice matrix node: (Invoice: {$invoice_id}) Vol: {$paid_amount}");
        }

        // Direct Execution to Print Routing Page
        $print_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=print&invoice=' . $invoice_id );
        echo '<script>window.location.href="' . esc_url( $print_url ) . '";</script>';
        exit;
    }

    $students = $wpdb->get_results( "SELECT id, full_name, student_id, class_name FROM $table_students WHERE status = 'Active' ORDER BY class_name ASC, full_name ASC" );
    $back_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=list' );
    
    // Core calendar loop arrays mapping
    $months = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
    $current_month = date('F');
    $current_year  = date('Y');
    ?>

    <!-- Navigation Area Component -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
            <svg style="width:16px; height:16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Fee Directory
        </a>
    </div>

    <!-- Main Workspace Form Processing Card Container -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden mx-auto" style="max-width: 850px;">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h4 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <svg viewBox="0 0 24 24" style="width:22px; height:22px; fill:none; stroke:#006a4e; stroke-width:2.5;"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                Collect New Fee Entry
            </h4>
        </div>
        
        <div class="card-body p-4 bg-white">
            <form method="POST" action="" class="needs-validation">
                <?php wp_nonce_field( 'collect_fee_action', 'educore_fee_nonce' ); ?>
                
                <!-- Student Selection Node -->
                <div class="mb-4">
                    <label class="form-label fw-semibold text-secondary">Select Target Student *</label>
                    <select name="student_id" class="form-select" required>
                        <option value="">-- Search & Select Student Registry --</option>
                        <?php foreach ( $students as $s ) : ?>
                            <option value="<?php echo esc_attr( $s->id ); ?>">
                                <?php echo esc_html( $s->full_name . ' (ID: ' . $s->student_id . ') — ' . $s->class_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Parameters Meta Data Grid -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-secondary">Fee Category Type</label>
                        <select name="fee_type" class="form-select" required>
                            <option value="Tuition Fee">Tuition Fee</option>
                            <option value="Admission Fee">Admission Fee</option>
                            <option value="Exam Fee">Exam Fee</option>
                            <option value="Transport Fee">Transport Fee</option>
                            <option value="Other Charges">Other Charges</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-secondary">Billing Month</label>
                        <select name="fee_month" class="form-select" required>
                            <?php foreach ( $months as $m ) : ?>
                                <option value="<?php echo esc_attr($m); ?>" <?php selected($current_month, $m); ?>><?php echo esc_html($m); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-secondary">Billing Year</label>
                        <input type="number" name="fee_year" class="form-control" value="<?php echo esc_attr($current_year); ?>" required>
                    </div>
                </div>

                <!-- Mathematical Ledger Computations Panel Grid -->
                <div class="row border-0 p-4 rounded-3 mb-4 g-3" style="background-color: #f8fafc; border: 1px dashed #cbd5e1 !important;">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-dark">Total Amount (৳) *</label>
                        <input type="number" step="0.01" name="amount" id="fee_amount" class="form-control bg-white fw-semibold" value="0.00" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-danger">Discount / Waiver (৳)</label>
                        <input type="number" step="0.01" name="discount" id="fee_discount" class="form-control bg-white fw-semibold text-danger" value="0.00" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-success">Net Payable Value (৳)</label>
                        <input type="number" id="fee_net" class="form-control border-0 fw-bold text-success fs-5" value="0.00" readonly style="background-color: #e2e8f0;">
                    </div>
                </div>

                <!-- Execution Gateway & Payment Input Modules -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-secondary">Payment Gateway Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="Cash">Cash Clearing</option>
                            <option value="bKash">bKash Merchant</option>
                            <option value="Bank Transfer">Direct Bank Wire</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-primary">Actually Paid Amount (৳) *</label>
                        <input type="number" step="0.01" name="paid_amount" id="fee_paid" class="form-control border-primary fw-bold text-primary" value="0.00" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-warning">Outstanding Due Balance (৳)</label>
                        <input type="number" id="fee_due" class="form-control border-0 fw-bold text-warning fs-5" value="0.00" readonly style="background-color: #fef3c7;">
                    </div>
                </div>

                <!-- Submit Module Frame -->
                <div class="mt-4 pt-2">
                    <button type="submit" name="educore_collect_fee" class="btn btn-success w-100 py-2.5 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" style="background-color: #006a4e; border: none; font-size: 1.1rem;">
                        <svg viewBox="0 0 24 24" style="width:20px; height:20px; fill:none; stroke:currentColor; stroke-width:2.5;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                        Receive Payment Ledger & Print Invoice Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Core Realtime Computation Data Matrix Processor Engines -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const amtInput  = document.getElementById('fee_amount');
        const discInput = document.getElementById('fee_discount');
        const netInput  = document.getElementById('fee_net');
        const paidInput = document.getElementById('fee_paid');
        const dueInput  = document.getElementById('fee_due');

        function calculateLedgerMetrics() {
            let totalAmount = parseFloat(amtInput.value) || 0;
            let discount    = parseFloat(discInput.value) || 0;
            let paidValue   = parseFloat(paidInput.value) || 0;

            // Compute Net Total Values
            let netPayable = totalAmount - discount;
            netPayable = netPayable > 0 ? netPayable : 0;
            netInput.value = netPayable.toFixed(2);

            // Compute Due Metric Configurations
            let outstandingDue = netPayable - paidValue;
            outstandingDue = outstandingDue > 0 ? outstandingDue : 0;
            dueInput.value = outstandingDue.toFixed(2);
        }

        // Event hooks monitoring bindings
        amtInput.addEventListener('input', calculateLedgerMetrics);
        discInput.addEventListener('input', calculateLedgerMetrics);
        paidInput.addEventListener('input', calculateLedgerMetrics);
    });
    </script>
    <?php
}