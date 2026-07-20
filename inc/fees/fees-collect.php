<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_fees_collect_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_fees     = $wpdb->prefix . 'sms_fees';

    $db_error = '';

    // Secure Mutation Engine Form Submission Handler
    if ( isset( $_POST['educore_collect_fee'] ) && wp_verify_nonce( $_POST['educore_fee_nonce'] ?? '', 'collect_fee_action' ) ) {
        
        $student_id  = absint( $_POST['student_id'] ?? 0 );
        $amount      = max( 0, floatval( $_POST['amount'] ?? 0 ) );
        $late_fine   = max( 0, floatval( $_POST['late_fine'] ?? 0 ) );
        $discount    = max( 0, floatval( $_POST['discount'] ?? 0 ) );
        $paid_amount = max( 0, floatval( $_POST['paid_amount'] ?? 0 ) );
        
        // Gross including Late Fine
        $gross_total = $amount + $late_fine;
        $net_payable = max( 0, $gross_total - $discount );
        $due_amount  = max( 0, $net_payable - $paid_amount );
        
        // Payment Status Business Logic
        $payment_status = 'Unpaid';
        if ( $paid_amount >= $net_payable && $net_payable > 0 ) {
            $payment_status = 'Paid';
            $due_amount     = 0;
        } elseif ( $paid_amount > 0 && $paid_amount < $net_payable ) {
            $payment_status = 'Partial';
        }

        // Generate Unique Invoice ID
        $invoice_id = 'INV-' . date( 'ym' ) . '-' . wp_rand( 10000, 99999 );

        $data = array(
            'invoice_id'     => $invoice_id,
            'student_id'     => $student_id,
            'fee_month'      => sanitize_text_field( $_POST['fee_month'] ?? '' ),
            'fee_year'       => sanitize_text_field( $_POST['fee_year'] ?? '' ),
            'fee_type'       => sanitize_text_field( $_POST['fee_type'] ?? '' ),
            'amount'         => $amount,
            'late_fine'      => $late_fine,
            'discount'       => $discount,
            'net_payable'    => $net_payable,
            'paid_amount'    => $paid_amount,
            'due_amount'     => $due_amount,
            'payment_status' => $payment_status,
            'payment_method' => sanitize_text_field( $_POST['payment_method'] ?? 'Cash' ),
            'transaction_id' => sanitize_text_field( $_POST['transaction_id'] ?? '' ),
            'remarks'        => sanitize_text_field( $_POST['remarks'] ?? '' ),
            'payment_date'   => current_time( 'mysql' ),
            'collected_by'   => get_current_user_id()
        );

        $format = array(
            '%s', // invoice_id
            '%d', // student_id
            '%s', // fee_month
            '%s', // fee_year
            '%s', // fee_type
            '%f', // amount
            '%f', // late_fine
            '%f', // discount
            '%f', // net_payable
            '%f', // paid_amount
            '%f', // due_amount
            '%s', // payment_status
            '%s', // payment_method
            '%s', // transaction_id
            '%s', // remarks
            '%s', // payment_date
            '%d'  // collected_by
        );

        $inserted = $wpdb->insert( $table_fees, $data, $format );
        
        if ( $inserted ) {
            if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                IFSEdu_School_Management_System::log_activity( sprintf( "Collected fee invoice: (%s) Amount: %.2f", $invoice_id, $paid_amount ) );
            }
            $print_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=print&invoice=' . urlencode( $invoice_id ) );
            echo '<script type="text/javascript">window.location.href="' . esc_url( $print_url ) . '";</script>';
            exit;
        } else {
            $db_error = $wpdb->last_error ? $wpdb->last_error : 'Failed to write record to database. Verify mysql columns: late_fine, transaction_id, remarks.';
        }
    }

    // Fetch Active Students securely
    $students = $wpdb->get_results( 
        $wpdb->prepare(
            "SELECT id, full_name, student_id, class_name FROM {$table_students} WHERE status = %s ORDER BY class_name ASC, full_name ASC",
            'Active'
        )
    );

    $back_url      = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=list' );
    $months        = array( "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December" );
    $current_month = date( 'F' );
    $current_year  = date( 'Y' );
    ?>

    <!-- Navigation Bar -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
            &larr; <?php esc_html_e( 'Back to Fee Directory', 'educore' ); ?>
        </a>
    </div>

    <?php if ( ! empty( $db_error ) ) : ?>
        <div class="alert alert-danger shadow-sm border-0 mb-4 mx-auto" style="max-width: 900px;">
            <strong>Database Error:</strong> <?php echo esc_html( $db_error ); ?>
        </div>
    <?php endif; ?>

    <!-- Main Entry Workspace -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden mx-auto" style="max-width: 900px;">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
            <h4 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <span class="dashicons dashicons-money-alt text-success fs-3"></span>
                <?php esc_html_e( 'Collect New Fee Entry', 'educore' ); ?>
            </h4>
            <span class="badge bg-light text-dark border"><?php echo esc_html( date( 'd M, Y' ) ); ?></span>
        </div>
        
        <div class="card-body p-4 bg-white">
            <form method="POST" action="" class="needs-validation">
                <?php wp_nonce_field( 'collect_fee_action', 'educore_fee_nonce' ); ?>
                
                <!-- Student Selector Component -->
                <div class="mb-4">
                    <label class="form-label fw-semibold text-secondary"><?php esc_html_e( 'Select Target Student *', 'educore' ); ?></label>
                    <select name="student_id" id="student_id" class="form-select form-select-lg" required>
                        <option value=""><?php esc_html_e( '-- Search & Select Student --', 'educore' ); ?></option>
                        <?php if ( ! empty( $students ) ) : ?>
                            <?php foreach ( $students as $s ) : ?>
                                <option value="<?php echo esc_attr( $s->id ); ?>">
                                    <?php echo esc_html( sprintf( '%s (ID: %s) — Class: %s', $s->full_name, $s->student_id, $s->class_name ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Parameters Grid -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-secondary"><?php esc_html_e( 'Fee Category Type', 'educore' ); ?></label>
                        <select name="fee_type" class="form-select" required>
                            <option value="Tuition Fee"><?php esc_html_e( 'Tuition Fee', 'educore' ); ?></option>
                            <option value="Admission Fee"><?php esc_html_e( 'Admission Fee', 'educore' ); ?></option>
                            <option value="Exam Fee"><?php esc_html_e( 'Exam Fee', 'educore' ); ?></option>
                            <option value="Transport Fee"><?php esc_html_e( 'Transport Fee', 'educore' ); ?></option>
                            <option value="Hostel Fee"><?php esc_html_e( 'Hostel Fee', 'educore' ); ?></option>
                            <option value="Other Charges"><?php esc_html_e( 'Other Charges', 'educore' ); ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-secondary"><?php esc_html_e( 'Billing Month', 'educore' ); ?></label>
                        <select name="fee_month" class="form-select" required>
                            <?php foreach ( $months as $m ) : ?>
                                <option value="<?php echo esc_attr( $m ); ?>" <?php selected( $current_month, $m ); ?>>
                                    <?php echo esc_html( $m ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-secondary"><?php esc_html_e( 'Billing Year', 'educore' ); ?></label>
                        <input type="number" name="fee_year" class="form-control" value="<?php echo esc_attr( $current_year ); ?>" required>
                    </div>
                </div>

                <!-- Mathematical Ledger & Quick Waiver Module -->
                <div class="row border-0 p-4 rounded-3 mb-4 g-3" style="background-color: #f8fafc; border: 1px dashed #cbd5e1 !important;">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-dark"><?php esc_html_e( 'Base Amount (৳) *', 'educore' ); ?></label>
                        <input type="number" step="0.01" name="amount" id="fee_amount" class="form-control bg-white fw-semibold" value="0.00" min="0" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-danger"><?php esc_html_e( 'Late Fine (৳)', 'educore' ); ?></label>
                        <input type="number" step="0.01" name="late_fine" id="fee_fine" class="form-control bg-white fw-semibold text-danger" value="0.00" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-primary"><?php esc_html_e( 'Discount / Waiver (৳)', 'educore' ); ?></label>
                        <input type="number" step="0.01" name="discount" id="fee_discount" class="form-control bg-white fw-semibold text-primary" value="0.00" min="0">
                        <div class="btn-group btn-group-sm mt-1 w-100" role="group">
                            <button type="button" class="btn btn-outline-secondary py-0 discount-btn" data-pct="5">5%</button>
                            <button type="button" class="btn btn-outline-secondary py-0 discount-btn" data-pct="10">10%</button>
                            <button type="button" class="btn btn-outline-secondary py-0 discount-btn" data-pct="100">100%</button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-success"><?php esc_html_e( 'Net Payable (৳)', 'educore' ); ?></label>
                        <input type="number" id="fee_net" class="form-control border-0 fw-bold text-success fs-5" value="0.00" readonly style="background-color: #e2e8f0;">
                    </div>
                </div>

                <!-- Payment Details Module -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-secondary"><?php esc_html_e( 'Payment Method', 'educore' ); ?></label>
                        <select name="payment_method" id="payment_method" class="form-select" required>
                            <option value="Cash"><?php esc_html_e( 'Cash Clearing', 'educore' ); ?></option>
                            <option value="bKash"><?php esc_html_e( 'bKash Mobile Banking', 'educore' ); ?></option>
                            <option value="Nagad"><?php esc_html_e( 'Nagad Mobile Banking', 'educore' ); ?></option>
                            <option value="Bank Transfer"><?php esc_html_e( 'Direct Bank Wire', 'educore' ); ?></option>
                            <option value="Cheque"><?php esc_html_e( 'Cheque Payment', 'educore' ); ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-primary"><?php esc_html_e( 'Actually Paid (৳) *', 'educore' ); ?></label>
                        <input type="number" step="0.01" name="paid_amount" id="fee_paid" class="form-control border-primary fw-bold text-primary" value="0.00" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-warning"><?php esc_html_e( 'Outstanding Due (৳)', 'educore' ); ?></label>
                        <input type="number" id="fee_due" class="form-control border-0 fw-bold text-warning fs-5" value="0.00" readonly style="background-color: #fef3c7;">
                    </div>
                </div>

                <!-- Audit Meta Info -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary"><?php esc_html_e( 'Transaction / Reference ID', 'educore' ); ?></label>
                        <input type="text" name="transaction_id" class="form-control" placeholder="e.g. TRX98234723 or Cheque No.">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary"><?php esc_html_e( 'Notes / Remarks', 'educore' ); ?></label>
                        <input type="text" name="remarks" class="form-control" placeholder="e.g. Special approval for partial payment">
                    </div>
                </div>

                <!-- Submit Action Area -->
                <div class="mt-4 pt-2">
                    <button type="submit" name="educore_collect_fee" class="btn btn-success w-100 py-2.5 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" style="background-color: #006a4e; border: none; font-size: 1.1rem;">
                        <span class="dashicons dashicons-disk align-middle"></span>
                        <?php esc_html_e( 'Receive Payment & Generate Receipt', 'educore' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Calculations Script -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const amtInput   = document.getElementById('fee_amount');
        const fineInput  = document.getElementById('fee_fine');
        const discInput  = document.getElementById('fee_discount');
        const netInput   = document.getElementById('fee_net');
        const paidInput  = document.getElementById('fee_paid');
        const dueInput   = document.getElementById('fee_due');
        const discBtns   = document.querySelectorAll('.discount-btn');

        function calculateLedgerMetrics() {
            let baseAmount = parseFloat(amtInput.value) || 0;
            let lateFine   = parseFloat(fineInput.value) || 0;
            let discount   = parseFloat(discInput.value) || 0;
            let paidValue  = parseFloat(paidInput.value) || 0;

            let grossTotal = baseAmount + lateFine;
            let netPayable = Math.max(0, grossTotal - discount);
            netInput.value = netPayable.toFixed(2);

            let outstandingDue = Math.max(0, netPayable - paidValue);
            dueInput.value = outstandingDue.toFixed(2);
        }

        discBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                let pct = parseFloat(this.getAttribute('data-pct')) || 0;
                let baseAmount = parseFloat(amtInput.value) || 0;
                let calculatedDiscount = (baseAmount * pct) / 100;
                discInput.value = calculatedDiscount.toFixed(2);
                calculateLedgerMetrics();
            });
        });

        if (amtInput && fineInput && discInput && paidInput) {
            amtInput.addEventListener('input', calculateLedgerMetrics);
            fineInput.addEventListener('input', calculateLedgerMetrics);
            discInput.addEventListener('input', calculateLedgerMetrics);
            paidInput.addEventListener('input', calculateLedgerMetrics);
        }
    });
    </script>
    <?php
}