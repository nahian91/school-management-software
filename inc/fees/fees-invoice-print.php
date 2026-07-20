<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helper: Convert BDT Numeric Amount into Words
 */
if ( ! function_exists( 'educore_number_to_words' ) ) {
    function educore_number_to_words( $amount ) {
        $amount = floatval( $amount );
        if ( $amount <= 0 ) return 'Zero Taka Only';

        $words = array(
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
            30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
            80 => 'Eighty', 90 => 'Ninety'
        );

        $number = floor( $amount );
        $paisa  = round( ( $amount - $number ) * 100 );
        $str    = array();

        if ( $number >= 10000000 ) { // Crore
            $crore  = floor( $number / 10000000 );
            $number %= 10000000;
            $str[]  = educore_number_to_words( $crore ) . ' Crore';
        }
        if ( $number >= 100000 ) { // Lakh
            $lakh   = floor( $number / 100000 );
            $number %= 100000;
            $str[]  = educore_number_to_words( $lakh ) . ' Lakh';
        }
        if ( $number >= 1000 ) { // Thousand
            $thousand = floor( $number / 1000 );
            $number   %= 1000;
            $str[]    = educore_number_to_words( $thousand ) . ' Thousand';
        }
        if ( $number >= 100 ) { // Hundred
            $hundred = floor( $number / 100 );
            $number  %= 100;
            $str[]   = $words[ $hundred ] . ' Hundred';
        }
        if ( $number > 0 ) {
            if ( $number < 20 ) {
                $str[] = $words[ $number ];
            } else {
                $ten   = floor( $number / 10 ) * 10;
                $unit  = $number % 10;
                $str[] = $words[ $ten ] . ( $unit ? ' ' . $words[ $unit ] : '' );
            }
        }

        $result = implode( ' ', $str ) . ' Taka';
        if ( $paisa > 0 ) {
            $result .= ' and ' . $paisa . ' Paisa';
        }
        return $result . ' Only';
    }
}

function educore_fees_invoice_print_view() {
    global $wpdb;

    // Security Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view or print payment receipts.', 'educore' ) );
    }

    $invoice_id = isset( $_GET['invoice'] ) ? sanitize_text_field( $_GET['invoice'] ) : '';
    if ( empty( $invoice_id ) ) {
        echo '<div class="alert alert-danger">No invoice identifier provided.</div>';
        return;
    }

    $table_fees     = $wpdb->prefix . 'sms_fees';
    $table_students = $wpdb->prefix . 'sms_students';

    $query = $wpdb->prepare( "
        SELECT f.*, s.full_name, s.student_id as s_id, s.class_name, s.section_name, s.roll_no, s.guardian_phone 
        FROM {$table_fees} f 
        LEFT JOIN {$table_students} s ON f.student_id = s.id 
        WHERE f.invoice_id = %s
    ", $invoice_id );
    
    $receipt = $wpdb->get_row( $query );

    if ( ! $receipt ) {
        echo '<div class="alert alert-danger">Invoice receipt record not found in system schema.</div>';
        return;
    }

    $back_url    = admin_url( 'admin.php?page=school_management_system&tab=fees' );
    $school_name = get_bloginfo( 'name' );
    $copies      = array( 'Student Copy', 'Office Copy', 'Bank / Audit Copy' );
    ?>

    <style>
        .invoice-print-wrapper {
            background: #ffffff;
            padding: 20px;
            font-family: Arial, sans-serif;
            color: #0f172a;
        }
        .receipt-card {
            border: 2px solid #000000;
            border-radius: 6px;
            padding: 15px;
            background: #ffffff;
            position: relative;
            height: 100%;
        }
        .receipt-card-header {
            border-bottom: 2px double #000000;
            padding-bottom: 8px;
            margin-bottom: 12px;
            text-align: center;
        }
        .copy-type-badge {
            background-color: #000000;
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table-receipt-data td, .table-receipt-data th {
            padding: 4px 6px;
            font-size: 0.82rem;
            vertical-align: middle;
        }
        .table-receipt-data th {
            background-color: #f1f5f9;
            border-bottom: 1px solid #000;
        }
        .signature-line-box {
            border-top: 1px solid #000000;
            width: 120px;
            text-align: center;
            font-size: 0.75rem;
            font-weight: 700;
            padding-top: 3px;
        }

        /* Printable Cut Rules */
        @media print {
            body * { visibility: hidden; }
            #educore-printable-receipt-area, #educore-printable-receipt-area * { visibility: visible; }
            #educore-printable-receipt-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print { display: none !important; }
            .col-md-4 { width: 33.3333% !important; float: left !important; }
            .receipt-card { border: 1px solid #000 !important; }
        }
    </style>

    <!-- Top Action Toolbar -->
    <div class="mb-4 no-print text-center">
        <button onclick="window.print();" class="btn btn-success btn-lg px-5 fw-bold" style="background-color: #006a4e; border: none;">
            <span class="dashicons dashicons-printer align-middle me-1"></span> Print 3-Part Receipt Coupon
        </button>
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-outline-secondary btn-lg ms-2">Back to Fees</a>
    </div>

    <!-- Printable Receipt Container (Renders 3 Copies for Triplicate Invoice) -->
    <div class="invoice-print-wrapper" id="educore-printable-receipt-area">
        <div class="row g-3">
            <?php foreach ( $copies as $copy_label ) : ?>
                <div class="col-md-4">
                    <div class="receipt-card">
                        
                        <!-- Header -->
                        <div class="receipt-card-header">
                            <h5 class="fw-bold m-0 text-uppercase" style="letter-spacing: 0.5px; font-size: 1.05rem;">
                                <?php echo esc_html( $school_name ); ?>
                            </h5>
                            <div class="my-1">
                                <span class="copy-type-badge"><?php echo esc_html( $copy_label ); ?></span>
                            </div>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">Official Money Receipt</small>
                        </div>

                        <!-- Meta Info -->
                        <table class="w-100 table-receipt-data mb-2">
                            <tr>
                                <td><strong>Invoice:</strong> #<?php echo esc_html( $receipt->invoice_id ); ?></td>
                                <td class="text-end"><strong>Date:</strong> <?php echo date( 'd-M-Y', strtotime( $receipt->payment_date ) ); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Student ID:</strong> <?php echo esc_html( $receipt->s_id ? $receipt->s_id : 'N/A' ); ?></td>
                                <td class="text-end"><strong>Roll:</strong> <?php echo esc_html( $receipt->roll_no ? $receipt->roll_no : 'N/A' ); ?></td>
                            </tr>
                            <tr>
                                <td colspan="2"><strong>Name:</strong> <span class="text-uppercase"><?php echo esc_html( $receipt->full_name ? $receipt->full_name : 'Unknown' ); ?></span></td>
                            </tr>
                            <tr>
                                <td colspan="2"><strong>Class:</strong> <?php echo esc_html( $receipt->class_name ); ?> <?php echo ! empty( $receipt->section_name ) ? '(Sec: ' . esc_html( $receipt->section_name ) . ')' : ''; ?></td>
                            </tr>
                        </table>

                        <!-- Breakdown Table -->
                        <table class="table table-bordered table-receipt-data mb-2">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-end">Amount (BDT)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $receipt->fee_type ); ?></strong><br>
                                        <small class="text-muted">(<?php echo esc_html( ucfirst($receipt->fee_month) . ' ' . $receipt->fee_year ); ?>)</small>
                                    </td>
                                    <td class="text-end"><?php echo number_format( $receipt->amount, 2 ); ?></td>
                                </tr>
                                <?php if ( $receipt->discount > 0 ) : ?>
                                <tr>
                                    <td class="text-end text-muted">Discount / Waiver (-)</td>
                                    <td class="text-end text-muted"><?php echo number_format( $receipt->discount, 2 ); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="text-end fw-bold">Net Payable</td>
                                    <td class="text-end fw-bold">৳<?php echo number_format( $receipt->net_payable, 2 ); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-end fw-bold text-success">Paid Amount</td>
                                    <td class="text-end fw-bold text-success">৳<?php echo number_format( $receipt->paid_amount, 2 ); ?></td>
                                </tr>
                                <?php if ( $receipt->due_amount > 0 ) : ?>
                                <tr>
                                    <td class="text-end fw-bold text-danger">Due Balance</td>
                                    <td class="text-end fw-bold text-danger">৳<?php echo number_format( $receipt->due_amount, 2 ); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Amount in Words -->
                        <div class="p-2 bg-light border rounded mb-3" style="font-size: 0.75rem;">
                            <strong>In Words:</strong> <em><?php echo esc_html( educore_number_to_words( $receipt->paid_amount ) ); ?></em>
                        </div>

                        <!-- Footer Signatures -->
                        <div class="d-flex justify-content-between align-items-end mt-4 pt-2">
                            <div class="signature-line-box">
                                Student / Guardian
                            </div>
                            <div class="signature-line-box">
                                Cashier / Officer
                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
?>