<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_fees_invoice_print_view() {
    global $wpdb;
    $invoice_id = isset( $_GET['invoice'] ) ? sanitize_text_field( $_GET['invoice'] ) : '';
    
    if ( empty( $invoice_id ) ) return;

    $table_fees     = $wpdb->prefix . 'sms_fees';
    $table_students = $wpdb->prefix . 'sms_students';

    $query = $wpdb->prepare( "
        SELECT f.*, s.full_name, s.student_id as s_id, s.class_name, s.section_name, s.roll_no 
        FROM $table_fees f 
        LEFT JOIN $table_students s ON f.student_id = s.id 
        WHERE f.invoice_id = %s
    ", $invoice_id );
    
    $receipt = $wpdb->get_row( $query );

    if ( ! $receipt ) {
        echo 'Receipt not found.';
        return;
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=fees' );
    $school_name = get_bloginfo('name');
    ?>

    <style>
        .receipt-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border: 1px solid #ccc; font-family: Arial, sans-serif; }
        .receipt-header { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 20px; margin-bottom: 20px; }
        .receipt-title { font-size: 24px; font-weight: bold; margin: 0; text-transform: uppercase; }
        .student-info-box { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .info-col { width: 48%; }
        .table-receipt { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .table-receipt th, .table-receipt td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .table-receipt th { background-color: #f8f9fa; }
        .text-right { text-align: right !important; }
        .signature-box { display: flex; justify-content: space-between; margin-top: 60px; }
        .sign-line { border-top: 1px solid #000; padding-top: 5px; width: 200px; text-align: center; }
        
        @media print {
            body * { visibility: hidden; }
            .receipt-container, .receipt-container * { visibility: visible; }
            .receipt-container { position: absolute; left: 0; top: 0; width: 100%; border: none; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>

    <div class="mb-3 no-print text-center">
        <button onclick="window.print();" class="btn btn-primary btn-lg" style="background: #3b82f6; border: none; margin-right: 10px;">
            <span class="dashicons dashicons-printer" style="margin-top: 4px;"></span> Print Receipt
        </button>
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-lg">Back to Fees</a>
    </div>

    <div class="receipt-container">
        <div class="receipt-header">
            <h1 class="receipt-title"><?php echo esc_html( $school_name ); ?></h1>
            <p style="margin: 5px 0;">Official Money Receipt</p>
        </div>

        <div class="student-info-box">
            <div class="info-col">
                <strong>Receipt No:</strong> <?php echo esc_html( $receipt->invoice_id ); ?><br>
                <strong>Date:</strong> <?php echo date('d F Y h:i A', strtotime($receipt->payment_date)); ?><br>
                <strong>Method:</strong> <?php echo esc_html( $receipt->payment_method ); ?>
            </div>
            <div class="info-col text-right">
                <strong>Student Name:</strong> <?php echo esc_html( $receipt->full_name ); ?><br>
                <strong>Student ID:</strong> <?php echo esc_html( $receipt->s_id ); ?><br>
                <strong>Class:</strong> <?php echo esc_html( $receipt->class_name ); ?> (Sec: <?php echo esc_html( $receipt->section_name ); ?>, Roll: <?php echo esc_html( $receipt->roll_no ); ?>)
            </div>
        </div>

        <table class="table-receipt">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount (BDT)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?php echo esc_html( $receipt->fee_type ); ?></strong><br>
                        <small>For the month of <?php echo esc_html( $receipt->fee_month . ' ' . $receipt->fee_year ); ?></small>
                    </td>
                    <td class="text-right"><?php echo number_format( $receipt->amount, 2 ); ?></td>
                </tr>
                <?php if ( $receipt->discount > 0 ) : ?>
                <tr>
                    <td class="text-right">Discount/Waiver (-)</td>
                    <td class="text-right"><?php echo number_format( $receipt->discount, 2 ); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="text-right"><strong>Net Payable Amount</strong></td>
                    <td class="text-right"><strong><?php echo number_format( $receipt->net_payable, 2 ); ?></strong></td>
                </tr>
                <tr>
                    <td class="text-right text-success"><strong>Paid Amount</strong></td>
                    <td class="text-right text-success"><strong><?php echo number_format( $receipt->paid_amount, 2 ); ?></strong></td>
                </tr>
                <?php if ( $receipt->due_amount > 0 ) : ?>
                <tr>
                    <td class="text-right text-danger"><strong>Due Balance</strong></td>
                    <td class="text-right text-danger"><strong><?php echo number_format( $receipt->due_amount, 2 ); ?></strong></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p><strong>Status:</strong> 
            <span style="border: 2px solid #000; padding: 2px 10px; font-weight: bold; text-transform: uppercase;">
                <?php echo esc_html( $receipt->payment_status ); ?>
            </span>
        </p>

        <div class="signature-box">
            <div class="sign-line">Student / Guardian Sign</div>
            <div class="sign-line">Authorized Signatory</div>
        </div>
        
        <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #666;">
            System Generated Receipt. Thank you.
        </div>
    </div>
    <?php
}
?>