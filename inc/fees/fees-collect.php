<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Fee Collection Module Engine
 * File: fees-collect.php
 * Theme Aesthetic: Elite Neo-Bento UI
 * Custom Prefixes Applied: dpt-, afdp-
 */

// AJAX Handler to dynamic filter student list by Class in Fee Collection
add_action( 'wp_ajax_educore_get_students_for_fee_collect', 'educore_get_students_for_fee_collect_handler' );
function educore_get_students_for_fee_collect_handler() {
    check_ajax_referer( 'educore_fee_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ifsedu-sms' ) ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $raw_class      = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $raw_class ) ) {
        $students = $wpdb->get_results(
            "SELECT id, full_name, student_id, roll_no, class_name, section_name 
             FROM {$table_students} WHERE status = 'Active' 
             ORDER BY class_name ASC, CAST(roll_no AS UNSIGNED) ASC, roll_no ASC"
        );
    } else {
        $parsed_class = $raw_class;
        $section_name = '';
        if ( preg_match( '/^(.*?)\s*\((.*?)\)$/', $raw_class, $matches ) ) {
            $parsed_class = trim( $matches[1] );
            $section_name = trim( $matches[2] );
        }

        if ( ! empty( $section_name ) ) {
            $students = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, full_name, student_id, roll_no, class_name, section_name 
                 FROM {$table_students} 
                 WHERE status = 'Active' AND class_name = %s AND section_name = %s 
                 ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                $parsed_class, $section_name
            ) );
        } else {
            $students = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, full_name, student_id, roll_no, class_name, section_name 
                 FROM {$table_students} 
                 WHERE status = 'Active' AND (class_name = %s OR class_name = %s) 
                 ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                $raw_class, $parsed_class
            ) );
        }
    }

    $data = array();
    if ( ! empty( $students ) ) {
        foreach ( $students as $s ) {
            $sec_str = ! empty( $s->section_name ) ? ' (' . $s->section_name . ')' : '';
            $data[]  = array(
                'id'         => intval( $s->id ),
                'full_name'  => esc_html( $s->full_name ),
                'student_id' => esc_html( $s->student_id ),
                'roll_no'    => esc_html( $s->roll_no ),
                'class_info' => esc_html( $s->class_name . $sec_str ),
            );
        }
    }

    wp_send_json_success( $data );
}

function educore_fees_collect_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_fees     = $wpdb->prefix . 'sms_fees';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // 1. Strict Security Control: Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to collect fees.', 'ifsedu-sms' ) );
    }

    $db_error = '';

    // Dynamic Base URL Preservation
    $current_uri = remove_query_arg( array( 'status' ), $_SERVER['REQUEST_URI'] );
    $base_url    = esc_url_raw( $current_uri );
    $back_url    = add_query_arg( array( 'sub' => 'list' ), $base_url );

    // 2. Secure Form Submission Handler
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['educore_collect_fee'] ) ) {
        if ( isset( $_POST['educore_fee_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_fee_nonce'] ) ), 'collect_fee_action' ) ) {
            
            $student_id  = isset( $_POST['student_id'] ) ? absint( $_POST['student_id'] ) : 0;
            $amount      = isset( $_POST['amount'] ) ? max( 0, floatval( $_POST['amount'] ) ) : 0;
            $late_fine   = isset( $_POST['late_fine'] ) ? max( 0, floatval( $_POST['late_fine'] ) ) : 0;
            $discount    = isset( $_POST['discount'] ) ? max( 0, floatval( $_POST['discount'] ) ) : 0;
            $paid_amount = isset( $_POST['paid_amount'] ) ? max( 0, floatval( $_POST['paid_amount'] ) ) : 0;
            
            // Gross & Mathematical Ledger Rules
            $gross_total = $amount + $late_fine;
            $net_payable = max( 0, $gross_total - $discount );
            $due_amount  = max( 0, $net_payable - $paid_amount );
            
            // Payment Status Logic
            $payment_status = 'Unpaid';
            if ( $paid_amount >= $net_payable && $net_payable > 0 ) {
                $payment_status = 'Paid';
                $due_amount     = 0;
            } elseif ( $paid_amount > 0 && $paid_amount < $net_payable ) {
                $payment_status = 'Partial';
            }

            // Generate Unique Invoice ID
            $invoice_id = 'INV-' . date( 'ym' ) . '-' . wp_rand( 10000, 99999 );

            $fee_month = isset( $_POST['fee_month'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_month'] ) ) : '';
            $fee_year  = isset( $_POST['fee_year'] ) ? absint( $_POST['fee_year'] ) : date( 'Y' );
            $fee_type  = isset( $_POST['fee_type'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_type'] ) ) : '';
            $p_method  = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'Cash';
            $trx_id    = isset( $_POST['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) ) : '';
            $remarks   = isset( $_POST['remarks'] ) ? sanitize_text_field( wp_unslash( $_POST['remarks'] ) ) : '';

            $data = array(
                'invoice_id'     => $invoice_id,
                'student_id'     => $student_id,
                'fee_month'      => $fee_month,
                'fee_year'       => $fee_year,
                'fee_type'       => $fee_type,
                'amount'         => $amount,
                'late_fine'      => $late_fine,
                'discount'       => $discount,
                'net_payable'    => $net_payable,
                'paid_amount'    => $paid_amount,
                'due_amount'     => $due_amount,
                'payment_status' => $payment_status,
                'payment_method' => $p_method,
                'transaction_id' => $trx_id,
                'remarks'        => $remarks,
                'payment_date'   => current_time( 'mysql' ),
                'collected_by'   => get_current_user_id()
            );

            $format = array(
                '%s', '%d', '%s', '%d', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d'
            );

            $inserted = $wpdb->insert( $table_fees, $data, $format );
            
            if ( $inserted ) {
                if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                    IFSEdu_School_Management_System::log_activity( sprintf( "Collected fee invoice: (%s) Amount: %.2f", $invoice_id, $paid_amount ) );
                }

                $page_slug = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
                
                $print_url = add_query_arg(
                    array(
                        'page'    => $page_slug,
                        'sub'     => 'print',
                        'invoice' => $invoice_id
                    ),
                    admin_url( 'admin.php' )
                );

                // JS Fallback with Absolute WP Admin URL
                echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $print_url ) . '";</script>';
                exit;
            } else {
                $db_error = $wpdb->last_error ? $wpdb->last_error : __( 'Failed to write record to database. Verify table schema.', 'ifsedu-sms' );
            }
        } else {
            $db_error = __( 'Security check failed. Nonce confirmation mismatch.', 'ifsedu-sms' );
        }
    }

    // Fetch Class/Section Units for Filter Console
    $raw_classes = $wpdb->get_results( "SELECT id, class_name, section_name FROM {$table_units} ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    if ( ! empty( $raw_classes ) ) {
        usort( $raw_classes, function( $a, $b ) {
            $res = strnatcasecmp( $a->class_name, $b->class_name );
            if ( $res === 0 && isset( $a->section_name ) && isset( $b->section_name ) ) {
                return strnatcasecmp( $a->section_name, $b->section_name );
            }
            return $res;
        });
    }

    // Fetch Initial Active Students List
    $students = $wpdb->get_results( 
        "SELECT id, full_name, student_id, roll_no, class_name, section_name 
         FROM {$table_students} WHERE status = 'Active' 
         ORDER BY class_name ASC, CAST(roll_no AS UNSIGNED) ASC, roll_no ASC"
    );

    $months        = array( "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December" );
    $current_month = date( 'F' );
    $current_year  = date( 'Y' );
    ?>

    <style>
        /* ==========================================================================
           FEES COLLECTION - NEO-BENTO ARCHITECTURE
           ========================================================================== */
        .dpt-fees-container {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #0f172a;
            max-width: 980px;
            margin: 20px auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .afdp-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dpt-btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #475569;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .dpt-btn-back:hover {
            border-color: #006a4e;
            color: #006a4e;
            background: #f8fafc;
        }

        .dpt-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-card-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 18px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .afdp-card-title {
            font-size: 20px;
            font-weight: 800;
            color: #006a4e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.3px;
        }

        .dpt-date-badge {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-weight: 700;
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 20px;
        }

        .dpt-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dpt-form-label {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .dpt-field-input,
        .dpt-field-select {
            width: 100%;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13.5px;
            color: #0f172a;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .dpt-field-input:focus,
        .dpt-field-select:focus {
            outline: none;
            border-color: #006a4e;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
        }

        .dpt-grid-filter {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 16px;
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .dpt-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .dpt-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        @media (max-width: 768px) {
            .dpt-grid-filter, .dpt-grid-3, .dpt-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        /* Ledger Panel Design */
        .afdp-ledger-panel {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin: 20px 0;
        }

        @media (max-width: 900px) {
            .afdp-ledger-panel {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .dpt-pct-group {
            display: flex;
            gap: 4px;
            margin-top: 6px;
        }

        .dpt-btn-pct {
            flex: 1;
            padding: 3px 0;
            font-size: 11px;
            font-weight: 700;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .dpt-btn-pct:hover {
            border-color: #006a4e;
            color: #006a4e;
            background: #f0fdf4;
        }

        .dpt-readonly-net {
            background: #ecfdf5 !important;
            border-color: #a7f3d0 !important;
            color: #047857 !important;
            font-weight: 800;
            font-size: 16px;
        }

        .dpt-readonly-due {
            background: #fffbeb !important;
            border-color: #fde68a !important;
            color: #b45309 !important;
            font-weight: 800;
            font-size: 16px;
        }

        .dpt-btn-submit {
            width: 100%;
            padding: 14px;
            background: #006a4e;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(0, 106, 78, 0.25);
            margin-top: 10px;
        }

        .dpt-btn-submit:hover {
            background: #00523c;
            transform: translateY(-1px);
        }

        .afdp-alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 14px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>

    <div class="dpt-fees-container">

        <!-- Navigation Bar -->
        <div class="afdp-top-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-back">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size:14px; width:14px; height:14px;"></span>
                <?php esc_html_e( 'Back to Fee Directory', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <?php if ( ! empty( $db_error ) ) : ?>
            <div class="afdp-alert-error">
                <span class="dashicons dashicons-warning" style="font-size:18px; width:18px; height:18px;"></span>
                <span><strong><?php esc_html_e( 'Database Error:', 'ifsedu-sms' ); ?></strong> <?php echo esc_html( $db_error ); ?></span>
            </div>
        <?php endif; ?>

        <!-- Main Entry Workspace Bento Box -->
        <div class="dpt-bento-card">
            <div class="afdp-card-header">
                <h4 class="afdp-card-title">
                    <span class="dashicons dashicons-money-alt" style="font-size:22px; width:22px; height:22px;"></span>
                    <?php esc_html_e( 'Collect Student Fee Entry', 'ifsedu-sms' ); ?>
                </h4>
                <span class="dpt-date-badge"><?php echo esc_html( date( 'd M, Y' ) ); ?></span>
            </div>

            <form method="POST" action="">
                <?php wp_nonce_field( 'collect_fee_action', 'educore_fee_nonce' ); ?>
                
                <!-- Easy Category Filter & Target Student Selector -->
                <div class="dpt-grid-filter">
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Filter By Class / Tier', 'ifsedu-sms' ); ?></label>
                        <select id="educore_fee_class_filter" class="dpt-field-select" style="font-weight:600;">
                            <option value=""><?php esc_html_e( '-- All Classes --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $raw_classes as $cls_obj ) : 
                                $c_val = ! empty( $cls_obj->section_name ) ? $cls_obj->class_name . ' (' . $cls_obj->section_name . ')' : $cls_obj->class_name;
                            ?>
                                <option value="<?php echo esc_attr( $c_val ); ?>"><?php echo esc_html( $c_val ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Select Target Student', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                        <select name="student_id" id="educore_fee_student_select" class="dpt-field-select" style="font-size: 14px; font-weight: 600;" required>
                            <option value=""><?php esc_html_e( '-- Search & Select Active Student --', 'ifsedu-sms' ); ?></option>
                            <?php if ( ! empty( $students ) ) : ?>
                                <?php foreach ( $students as $s ) : 
                                    $sec_info = ! empty( $s->section_name ) ? ' (' . $s->section_name . ')' : '';
                                ?>
                                    <option value="<?php echo esc_attr( $s->id ); ?>">
                                        <?php 
                                            /* translators: 1: Roll, 2: Name, 3: ID, 4: Class, 5: Section */
                                            printf( esc_html__( '[Roll: %1$s] - %2$s (ID: %3$s) | %4$s%5$s', 'ifsedu-sms' ), esc_html( $s->roll_no ), esc_html( $s->full_name ), esc_html( $s->student_id ), esc_html( $s->class_name ), esc_html( $sec_info ) ); 
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <!-- Parameters Grid -->
                <div class="dpt-grid-3" style="margin-bottom: 20px;">
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Fee Category Type', 'ifsedu-sms' ); ?></label>
                        <select name="fee_type" class="dpt-field-select" required>
                            <option value="Tuition Fee"><?php esc_html_e( 'Tuition Fee', 'ifsedu-sms' ); ?></option>
                            <option value="Admission Fee"><?php esc_html_e( 'Admission Fee', 'ifsedu-sms' ); ?></option>
                            <option value="Exam Fee"><?php esc_html_e( 'Exam Fee', 'ifsedu-sms' ); ?></option>
                            <option value="Transport Fee"><?php esc_html_e( 'Transport Fee', 'ifsedu-sms' ); ?></option>
                            <option value="Hostel Fee"><?php esc_html_e( 'Hostel Fee', 'ifsedu-sms' ); ?></option>
                            <option value="Other Charges"><?php esc_html_e( 'Other Charges', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Billing Month', 'ifsedu-sms' ); ?></label>
                        <select name="fee_month" class="dpt-field-select" required>
                            <?php foreach ( $months as $m ) : ?>
                                <option value="<?php echo esc_attr( $m ); ?>" <?php selected( $current_month, $m ); ?>>
                                    <?php echo esc_html( $m ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Billing Year', 'ifsedu-sms' ); ?></label>
                        <input type="number" name="fee_year" class="dpt-field-input" value="<?php echo esc_attr( $current_year ); ?>" required>
                    </div>
                </div>

                <!-- Mathematical Ledger & Quick Waiver Panel -->
                <div class="afdp-ledger-panel">
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Base Amount (৳)', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                        <input type="number" step="0.01" name="amount" id="fee_amount" class="dpt-field-input" value="0.00" min="0" required>
                    </div>
                    <div class="dpt-form-group">
                        <label class="dpt-form-label" style="color: #dc2626;"><?php esc_html_e( 'Late Fine (৳)', 'ifsedu-sms' ); ?></label>
                        <input type="number" step="0.01" name="late_fine" id="fee_fine" class="dpt-field-input" value="0.00" min="0">
                    </div>
                    <div class="dpt-form-group">
                        <label class="dpt-form-label" style="color: #2563eb;"><?php esc_html_e( 'Waiver / Discount (৳)', 'ifsedu-sms' ); ?></label>
                        <input type="number" step="0.01" name="discount" id="fee_discount" class="dpt-field-input" value="0.00" min="0">
                        <div class="dpt-pct-group">
                            <button type="button" class="dpt-btn-pct discount-btn" data-pct="5">5%</button>
                            <button type="button" class="dpt-btn-pct discount-btn" data-pct="10">10%</button>
                            <button type="button" class="dpt-btn-pct discount-btn" data-pct="100">100%</button>
                        </div>
                    </div>
                    <div class="dpt-form-group">
                        <label class="dpt-form-label" style="color: #047857;"><?php esc_html_e( 'Net Payable (৳)', 'ifsedu-sms' ); ?></label>
                        <input type="number" id="fee_net" class="dpt-field-input dpt-readonly-net" value="0.00" readonly>
                    </div>
                </div>

                <!-- Payment Details Module -->
                <div class="dpt-grid-3" style="margin-bottom: 20px;">
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Payment Method', 'ifsedu-sms' ); ?></label>
                        <select name="payment_method" id="payment_method" class="dpt-field-select" required>
                            <option value="Cash"><?php esc_html_e( 'Cash Clearing', 'ifsedu-sms' ); ?></option>
                            <option value="bKash"><?php esc_html_e( 'bKash Mobile Banking', 'ifsedu-sms' ); ?></option>
                            <option value="Nagad"><?php esc_html_e( 'Nagad Mobile Banking', 'ifsedu-sms' ); ?></option>
                            <option value="Bank Transfer"><?php esc_html_e( 'Direct Bank Wire', 'ifsedu-sms' ); ?></option>
                            <option value="Cheque"><?php esc_html_e( 'Cheque Payment', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>
                    <div class="dpt-form-group">
                        <label class="dpt-form-label" style="color: #006a4e;"><?php esc_html_e( 'Actually Paid (৳)', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                        <input type="number" step="0.01" name="paid_amount" id="fee_paid" class="dpt-field-input" style="border-color: #006a4e; font-weight: 800; color: #006a4e;" value="0.00" min="0" required>
                    </div>
                    <div class="dpt-form-group">
                        <label class="dpt-form-label" style="color: #b45309;"><?php esc_html_e( 'Outstanding Due (৳)', 'ifsedu-sms' ); ?></label>
                        <input type="number" id="fee_due" class="dpt-field-input dpt-readonly-due" value="0.00" readonly>
                    </div>
                </div>

                <!-- Audit Meta Info -->
                <div class="dpt-grid-2" style="margin-bottom: 24px;">
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Transaction / Reference ID', 'ifsedu-sms' ); ?></label>
                        <input type="text" name="transaction_id" class="dpt-field-input" placeholder="e.g. TRX98234723 or Cheque No.">
                    </div>
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Notes / Remarks', 'ifsedu-sms' ); ?></label>
                        <input type="text" name="remarks" class="dpt-field-input" placeholder="e.g. Special approval for partial payment">
                    </div>
                </div>

                <!-- Action Button -->
                <button type="submit" name="educore_collect_fee" class="dpt-btn-submit">
                    <span class="dashicons dashicons-saved" style="font-size:20px; width:20px; height:20px;"></span>
                    <?php esc_html_e( 'Receive Payment & Generate Receipt', 'ifsedu-sms' ); ?>
                </button>
            </form>
        </div>

    </div>

    <!-- Live Calculations & Dynamic Filtering Engine Script -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Dynamic AJAX fetch student list when class filter is changed
        $('#educore_fee_class_filter').on('change', function() {
            var selectedClass = $(this).val();
            var $studentSelect = $('#educore_fee_student_select');

            $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Active Students... --', 'ifsedu-sms' ) ); ?></option>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'educore_get_students_for_fee_collect',
                    security: '<?php echo esc_js( wp_create_nonce( "educore_fee_nonce" ) ); ?>',
                    class_name: selectedClass
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var options = '<option value=""><?php echo esc_js( __( '-- Search & Select Active Student --', 'ifsedu-sms' ) ); ?></option>';
                        $.each(response.data, function(index, student) {
                            options += '<option value="' + student.id + '">[Roll: ' + student.roll_no + '] - ' + student.full_name + ' (ID: ' + student.student_id + ') | ' + student.class_info + '</option>';
                        });
                        $studentSelect.html(options);
                    } else {
                        $studentSelect.html('<option value=""><?php echo esc_js( __( 'No Active Students Found in Class', 'ifsedu-sms' ) ); ?></option>');
                    }
                },
                error: function() {
                    $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Search & Select Active Student --', 'ifsedu-sms' ) ); ?></option>');
                }
            });
        });

        // Live Ledger Math Calculations Engine
        const amtInput   = document.getElementById('fee_amount');
        const fineInput  = document.getElementById('fee_fine');
        const discInput  = document.getElementById('fee_discount');
        const netInput   = document.getElementById('fee_net');
        const paidInput  = document.getElementById('fee_paid');
        const dueInput   = document.getElementById('fee_due');
        const discBtns   = document.querySelectorAll('.discount-btn');

        function calculateLedgerMetrics(updatePaidField) {
            if (typeof updatePaidField === 'undefined') {
                updatePaidField = false;
            }

            let baseAmount = parseFloat(amtInput.value) || 0;
            let lateFine   = parseFloat(fineInput.value) || 0;
            let discount   = parseFloat(discInput.value) || 0;
            
            let grossTotal = baseAmount + lateFine;
            let netPayable = Math.max(0, grossTotal - discount);
            netInput.value = netPayable.toFixed(2);

            if (updatePaidField) {
                paidInput.value = netPayable.toFixed(2);
            }

            let paidValue      = parseFloat(paidInput.value) || 0;
            let outstandingDue = Math.max(0, netPayable - paidValue);
            dueInput.value     = outstandingDue.toFixed(2);
        }

        discBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                let pct = parseFloat(this.getAttribute('data-pct')) || 0;
                let baseAmount = parseFloat(amtInput.value) || 0;
                let calculatedDiscount = (baseAmount * pct) / 100;
                discInput.value = calculatedDiscount.toFixed(2);
                calculateLedgerMetrics(true);
            });
        });

        if (amtInput && fineInput && discInput && paidInput) {
            amtInput.addEventListener('input', function() { calculateLedgerMetrics(true); });
            fineInput.addEventListener('input', function() { calculateLedgerMetrics(true); });
            discInput.addEventListener('input', function() { calculateLedgerMetrics(false); });
            paidInput.addEventListener('input', function() { calculateLedgerMetrics(false); });
        }
    });
    </script>
    <?php
}