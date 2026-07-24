<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

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

        $result = implode( ' ', array_filter( $str ) ) . ' Taka';
        if ( $paisa > 0 ) {
            $result .= ' and ' . $paisa . ' Paisa';
        }
        return $result . ' Only';
    }
}

/**
 * Invoice Print Controller
 * File: fees-invoice-print.php
 * Custom Prefixes Applied: dpt-, afdp-
 */
function educore_fees_invoice_print_view() {
    global $wpdb;

    // Security Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view or print payment receipts.', 'ifsedu-sms' ) );
    }

    $invoice_id = isset( $_GET['invoice'] ) ? sanitize_text_field( $_GET['invoice'] ) : '';
    if ( empty( $invoice_id ) ) {
        echo '<div class="afdp-alert-danger">' . esc_html__( 'No invoice identifier provided.', 'ifsedu-sms' ) . '</div>';
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
        echo '<div class="afdp-alert-danger">' . esc_html__( 'Invoice receipt record not found in system schema.', 'ifsedu-sms' ) . '</div>';
        return;
    }

    $back_url    = admin_url( 'admin.php?page=school_management_system&tab=fees' );
    $school_name = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_logo = get_option( 'educore_school_logo', '' );
    $copies      = array( 'Student Copy', 'Office Copy', 'Bank / Audit Copy' );
    ?>

    <style>
        /* ==========================================================================
           FEES INVOICE PRINT - NEO-BENTO & PRINT ENGINE
           ========================================================================== */
        .afdp-print-toolbar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .dpt-btn-print {
            padding: 10px 24px;
            background: #006a4e;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
            transition: all 0.2s ease;
        }

        .dpt-btn-print:hover {
            background: #00523c;
            transform: translateY(-1px);
        }

        .dpt-btn-back {
            padding: 10px 20px;
            background: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .dpt-btn-back:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .invoice-print-wrapper {
            background: #ffffff;
            padding: 10px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: #0f172a;
        }

        .dpt-triplicate-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .receipt-card {
            border: 1.5px solid #0f172a;
            border-radius: 8px;
            padding: 14px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-sizing: border-box;
            position: relative;
        }

        .receipt-card-header {
            border-bottom: 2px double #0f172a;
            padding-bottom: 8px;
            margin-bottom: 10px;
            text-align: center;
        }

        .receipt-logo {
            max-height: 38px;
            width: auto;
            margin-bottom: 4px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .receipt-school-title {
            font-weight: 800;
            font-size: 13.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin: 0 0 4px 0;
            color: #006a4e;
        }

        .copy-type-badge {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .table-receipt-data {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 10px;
        }

        .table-receipt-data td, 
        .table-receipt-data th {
            padding: 4px 6px;
            vertical-align: middle;
        }

        .table-receipt-data th {
            background-color: #f1f5f9;
            border-top: 1px solid #0f172a;
            border-bottom: 1px solid #0f172a;
            font-weight: 800;
            text-align: left;
        }

        .dpt-bordered-table {
            border: 1px solid #e2e8f0;
        }

        .dpt-bordered-table td, 
        .dpt-bordered-table th {
            border: 1px solid #e2e8f0;
        }

        .words-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 6px;
            border-radius: 6px;
            font-size: 10px;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .signature-area {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 15px;
            padding-top: 5px;
        }

        .signature-line-box {
            border-top: 1px dashed #0f172a;
            width: 90px;
            text-align: center;
            font-size: 9px;
            font-weight: 700;
            padding-top: 2px;
            color: #334155;
        }

        .afdp-alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 14px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            margin: 20px auto;
            max-width: 600px;
            text-align: center;
        }

        /* Printable Rules */
        @media print {
            @page {
                size: A4 landscape;
                margin: 8mm;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
            }
            body * { 
                visibility: hidden; 
            }
            #educore-printable-receipt-area, 
            #educore-printable-receipt-area * { 
                visibility: visible; 
            }
            #educore-printable-receipt-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 0;
            }
            .no-print { 
                display: none !important; 
            }
            .dpt-triplicate-grid {
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 8mm !important;
            }
            .receipt-card { 
                border: 1px solid #000000 !important; 
                box-shadow: none !important;
            }
            .receipt-school-title {
                color: #000000 !important;
            }
            .copy-type-badge {
                background-color: #000000 !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .words-box {
                background: #ffffff !important;
                border: 1px solid #000000 !important;
            }
            .table-receipt-data th {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>

    <!-- Top Action Toolbar -->
    <div class="afdp-print-toolbar no-print">
        <button onclick="window.print();" class="dpt-btn-print">
            <span class="dashicons dashicons-printer" style="font-size:16px; width:16px; height:16px;"></span>
            <?php esc_html_e( 'Print 3-Part Receipt Coupon', 'ifsedu-sms' ); ?>
        </button>
        <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-back">
            <span class="dashicons dashicons-arrow-left-alt" style="font-size:14px; width:14px; height:14px;"></span>
            <?php esc_html_e( 'Back to Fee Directory', 'ifsedu-sms' ); ?>
        </a>
    </div>

    <!-- Printable Receipt Container -->
    <div class="invoice-print-wrapper" id="educore-printable-receipt-area">
        <div class="dpt-triplicate-grid">
            <?php foreach ( $copies as $copy_label ) : ?>
                <div class="receipt-card">
                    <div>
                        <!-- Header -->
                        <div class="receipt-card-header">
                            <?php if ( ! empty( $school_logo ) ) : ?>
                                <img src="<?php echo esc_url( $school_logo ); ?>" alt="Logo" class="receipt-logo">
                            <?php endif; ?>
                            <h5 class="receipt-school-title">
                                <?php echo esc_html( $school_name ); ?>
                            </h5>
                            <div style="margin: 3px 0;">
                                <span class="copy-type-badge"><?php echo esc_html( $copy_label ); ?></span>
                            </div>
                            <span style="font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; font-weight: 700;">
                                <?php esc_html_e( 'Money Receipt', 'ifsedu-sms' ); ?>
                            </span>
                        </div>

                        <!-- Meta Info Table -->
                        <table class="table-receipt-data">
                            <tr>
                                <td><strong>Invoice:</strong> #<?php echo esc_html( $receipt->invoice_id ); ?></td>
                                <td style="text-align: right;"><strong>Date:</strong> <?php echo esc_html( date( 'd-M-Y', strtotime( $receipt->payment_date ) ) ); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Student ID:</strong> <?php echo esc_html( $receipt->s_id ? $receipt->s_id : 'N/A' ); ?></td>
                                <td style="text-align: right;"><strong>Roll:</strong> <?php echo esc_html( $receipt->roll_no ? $receipt->roll_no : 'N/A' ); ?></td>
                            </tr>
                            <tr>
                                <td colspan="2"><strong>Name:</strong> <span style="text-transform: uppercase; font-weight: 700;"><?php echo esc_html( $receipt->full_name ? $receipt->full_name : 'N/A' ); ?></span></td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <strong>Class:</strong> <?php echo esc_html( $receipt->class_name ); ?> 
                                    <?php echo ! empty( $receipt->section_name ) ? ' | <strong>Section:</strong> ' . esc_html( $receipt->section_name ) : ''; ?>
                                </td>
                            </tr>
                        </table>

                        <!-- Breakdown Table -->
                        <table class="table-receipt-data dpt-bordered-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Description', 'ifsedu-sms' ); ?></th>
                                    <th style="text-align: right;"><?php esc_html_e( 'Amount (BDT)', 'ifsedu-sms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $receipt->fee_type ); ?></strong><br>
                                        <span style="font-size: 9.5px; color: #64748b;">(<?php echo esc_html( ucfirst( $receipt->fee_month ) . ' ' . $receipt->fee_year ); ?>)</span>
                                    </td>
                                    <td style="text-align: right; font-weight: 600;"><?php echo esc_html( number_format( $receipt->amount, 2 ) ); ?></td>
                                </tr>
                                <?php if ( floatval( $receipt->late_fine ) > 0 ) : ?>
                                <tr>
                                    <td style="color: #dc2626;">Late Fine (+)</td>
                                    <td style="text-align: right; color: #dc2626; font-weight: 600;"><?php echo esc_html( number_format( $receipt->late_fine, 2 ) ); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ( floatval( $receipt->discount ) > 0 ) : ?>
                                <tr>
                                    <td style="color: #2563eb;">Discount / Waiver (-)</td>
                                    <td style="text-align: right; color: #2563eb; font-weight: 600;"><?php echo esc_html( number_format( $receipt->discount, 2 ) ); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td style="font-weight: 700; background: #f8fafc;">Net Payable</td>
                                    <td style="text-align: right; font-weight: 800; background: #f8fafc;">৳<?php echo esc_html( number_format( $receipt->net_payable, 2 ) ); ?></td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 800; color: #006a4e;">Paid Amount</td>
                                    <td style="text-align: right; font-weight: 800; color: #006a4e;">৳<?php echo esc_html( number_format( $receipt->paid_amount, 2 ) ); ?></td>
                                </tr>
                                <?php if ( floatval( $receipt->due_amount ) > 0 ) : ?>
                                <tr>
                                    <td style="font-weight: 700; color: #dc2626;">Due Balance</td>
                                    <td style="text-align: right; font-weight: 800; color: #dc2626;">৳<?php echo esc_html( number_format( $receipt->due_amount, 2 ) ); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Amount in Words -->
                        <div class="words-box">
                            <strong>In Words:</strong> <em><?php echo esc_html( educore_number_to_words( $receipt->paid_amount ) ); ?></em>
                        </div>
                    </div>

                    <!-- Footer Signatures -->
                    <div class="signature-area">
                        <div class="signature-line-box">
                            <?php esc_html_e( 'Student / Guardian', 'ifsedu-sms' ); ?>
                        </div>
                        <div class="signature-line-box">
                            <?php esc_html_e( 'Cashier / Officer', 'ifsedu-sms' ); ?>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}