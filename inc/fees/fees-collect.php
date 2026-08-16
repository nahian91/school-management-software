<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Fee Collection Module Engine with Automated Late Fine Rules (Role-Filtered for Accountant & Admin)
 * File: inc/fees/fees-collect.php
 * Theme Aesthetic: Elite Neo-Bento UI
 * Custom Prefixes Applied: dpt-, afdp-
 */

// 1. AJAX Handler to dynamically load Sections based on Class
add_action( 'wp_ajax_educore_get_sections_by_class_fee', 'educore_get_sections_by_class_fee_handler' );
function educore_get_sections_by_class_fee_handler() {
    check_ajax_referer( 'educore_fee_nonce', 'security' );

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ifsedu-sms' ) ) );
    }

    global $wpdb;
    $table_units = $wpdb->prefix . 'sms_academic_units';
    $class_name  = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $sections = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT section_name FROM {$table_units} WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
        $class_name
    ) );

    wp_send_json_success( $sections );
}

// 2. AJAX Handler to fetch configured Fee Categories & Amounts strictly from Fees Settings
add_action( 'wp_ajax_educore_get_fee_types_by_class', 'educore_get_fee_types_by_class_handler' );
function educore_get_fee_types_by_class_handler() {
    check_ajax_referer( 'educore_fee_nonce', 'security' );

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ifsedu-sms' ) ) );
    }

    global $wpdb;
    $table_fee_types = $wpdb->prefix . 'sms_fee_types';
    $table_late_cfg  = $wpdb->prefix . 'sms_late_fee_config';
    $class_name      = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $billing_month   = isset( $_POST['billing_month'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_month'] ) ) : date( 'F' );
    $billing_year    = isset( $_POST['billing_year'] ) ? absint( $_POST['billing_year'] ) : date( 'Y' );

    $fee_types = array();
    if ( ! empty( $class_name ) ) {
        $fee_types = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, fee_title, amount, period_type FROM {$table_fee_types} WHERE class_name = %s ORDER BY id ASC",
            $class_name
        ) );
    }

    // Calculate Late Fine based on Settings Table Rules
    $calculated_fine = 0.00;
    $late_cfg = $wpdb->get_row( "SELECT * FROM {$table_late_cfg} LIMIT 1" );

    if ( $late_cfg && strtolower( $late_cfg->status ) === 'active' ) {
        $today_timestamp = current_time( 'timestamp' );
        
        // Build target due date timestamp (e.g. 12th of the selected billing month & year)
        $month_num = date( 'm', strtotime( $billing_month . ' 1' ) );
        $due_date_str = sprintf( '%04d-%02d-%02d', $billing_year, $month_num, absint( $late_cfg->fine_start_date ) );
        $due_timestamp = strtotime( $due_date_str );

        if ( $today_timestamp > $due_timestamp ) {
            $overdue_days = floor( ( $today_timestamp - $due_timestamp ) / ( 60 * 60 * 24 ) );

            if ( $overdue_days > absint( $late_cfg->grace_days ) ) {
                $effective_days = $overdue_days - absint( $late_cfg->grace_days );
                $fine_type      = $late_cfg->fine_type;
                $rate_val       = floatval( $late_cfg->fine_amount );
                $max_cap        = floatval( $late_cfg->max_fine_cap );

                if ( $fine_type === 'Fixed' ) {
                    $calculated_fine = $rate_val;
                } elseif ( $fine_type === 'Daily' ) {
                    $calculated_fine = $rate_val * $effective_days;
                } elseif ( $fine_type === 'Percentage' ) {
                    // Applied against base amount dynamically in JS or base fee
                    $calculated_fine = $rate_val; // Base rate flag for client side computation
                }

                if ( $max_cap > 0 && $calculated_fine > $max_cap ) {
                    $calculated_fine = $max_cap;
                }
            }
        }
    }

    wp_send_json_success( array(
        'fee_types'     => $fee_types,
        'late_fine'     => $calculated_fine,
        'fine_type'     => $late_cfg ? $late_cfg->fine_type : 'Fixed',
        'fine_amount'   => $late_cfg ? floatval( $late_cfg->fine_amount ) : 0.00,
        'grace_days'    => $late_cfg ? absint( $late_cfg->grace_days ) : 0,
        'start_date'    => $late_cfg ? absint( $late_cfg->fine_start_date ) : 12,
    ) );
}

// 3. AJAX Handler to dynamically filter student list by Class & Section
add_action( 'wp_ajax_educore_get_students_for_fee_collect', 'educore_get_students_for_fee_collect_handler' );
function educore_get_students_for_fee_collect_handler() {
    check_ajax_referer( 'educore_fee_nonce', 'security' );

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ifsedu-sms' ) ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name   = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        $students = $wpdb->get_results(
            "SELECT id, full_name, student_id, roll_no, class_name, section_name, shift, fee_start_date, waiver_percentage, waiver_staff_id 
             FROM {$table_students} WHERE status = 'Active' 
             ORDER BY class_name ASC, CAST(roll_no AS UNSIGNED) ASC, roll_no ASC"
        );
    } else {
        $sql = "SELECT id, full_name, student_id, roll_no, class_name, section_name, shift, fee_start_date, waiver_percentage, waiver_staff_id 
                FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $params = array( $class_name );

        if ( ! empty( $section_name ) ) {
            $sql .= " AND section_name = %s";
            $params[] = $section_name;
        }

        $sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $students = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
    }

    $data = array();
    if ( ! empty( $students ) ) {
        foreach ( $students as $s ) {
            $sec_str   = ! empty( $s->section_name ) ? ' (' . $s->section_name . ')' : '';
            $shift_str = ( ! empty( $s->shift ) && $s->shift !== 'No Shift' ) ? ' [' . $s->shift . ']' : '';
            $data[] = array(
                'id'                => intval( $s->id ),
                'full_name'         => esc_html( $s->full_name ),
                'student_id'        => esc_html( strtoupper( $s->student_id ) ),
                'roll_no'           => esc_html( $s->roll_no ),
                'class_name'        => esc_html( $s->class_name ),
                'section_name'      => esc_html( $s->section_name ? $s->section_name : '' ),
                'class_info'        => esc_html( $s->class_name . $sec_str . $shift_str ),
                'fee_start_date'    => esc_html( $s->fee_start_date ? $s->fee_start_date : '' ),
                'waiver_percentage' => floatval( $s->waiver_percentage ?? 0 ),
                'waiver_staff_id'   => intval( $s->waiver_staff_id ?? 0 ),
            );
        }
    }

    wp_send_json_success( $data );
}

// 4. AJAX Handler to fetch details of a single student
add_action( 'wp_ajax_educore_get_single_student_waiver_info', 'educore_get_single_student_waiver_info_handler' );
function educore_get_single_student_waiver_info_handler() {
    check_ajax_referer( 'educore_fee_nonce', 'security' );

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ifsedu-sms' ) ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_staff    = $wpdb->prefix . 'sms_staff';
    
    $student_id_num = isset( $_POST['student_id'] ) ? absint( $_POST['student_id'] ) : 0;
    $search_uid     = isset( $_POST['search_uid'] ) ? sanitize_text_field( wp_unslash( $_POST['search_uid'] ) ) : '';

    if ( $student_id_num > 0 ) {
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT id, full_name, student_id, roll_no, class_name, section_name, shift, fee_start_date, waiver_percentage, waiver_staff_id FROM {$table_students} WHERE id = %d AND status = 'Active'", $student_id_num ) );
    } elseif ( ! empty( $search_uid ) ) {
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT id, full_name, student_id, roll_no, class_name, section_name, shift, fee_start_date, waiver_percentage, waiver_staff_id FROM {$table_students} WHERE (student_id = %s OR student_id LIKE %s) AND status = 'Active' LIMIT 1", $search_uid, '%' . $wpdb->esc_like( $search_uid ) ) );
    } else {
        wp_send_json_error( array( 'message' => __( 'Invalid parameters provided.', 'ifsedu-sms' ) ) );
    }

    if ( ! $student ) {
        wp_send_json_error( array( 'message' => __( 'Student not found.', 'ifsedu-sms' ) ) );
    }

    $staff_ref_name = '';
    if ( ! empty( $student->waiver_staff_id ) && $student->waiver_staff_id > 0 ) {
        $staff_row = $wpdb->get_row( $wpdb->prepare( "SELECT full_name, designation FROM {$table_staff} WHERE id = %d", $student->waiver_staff_id ) );
        if ( $staff_row ) {
            $staff_ref_name = $staff_row->full_name . ' (' . $staff_row->designation . ')';
        }
    }

    wp_send_json_success( array(
        'id'                => intval( $student->id ),
        'full_name'         => esc_html( $student->full_name ),
        'student_id'        => esc_html( strtoupper( $student->student_id ) ),
        'roll_no'           => esc_html( $student->roll_no ),
        'class_name'        => esc_html( $student->class_name ),
        'section_name'      => esc_html( $student->section_name ? $student->section_name : '' ),
        'shift'             => esc_html( $student->shift ? $student->shift : 'No Shift' ),
        'fee_start_date'    => esc_html( $student->fee_start_date ? $student->fee_start_date : 'From Admission' ),
        'waiver_percentage' => floatval( $student->waiver_percentage ?? 0 ),
        'staff_ref_name'    => esc_html( $staff_ref_name ),
    ) );
}

function educore_fees_collect_view() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $roles        = (array) $current_user->roles;

    // 1. Multi-Role Capability Security Matrix (Admins & Accountants)
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to collect fees.', 'ifsedu-sms' ) );
    }

    $table_students  = $wpdb->prefix . 'sms_students';
    $table_fees      = $wpdb->prefix . 'sms_fees';
    $table_units     = $wpdb->prefix . 'sms_academic_units';
    $table_fee_types = $wpdb->prefix . 'sms_fee_types';
    $table_late_cfg  = $wpdb->prefix . 'sms_late_fee_config';

    $db_error    = '';
    $current_uri = remove_query_arg( array( 'status', 'msg' ), $_SERVER['REQUEST_URI'] );
    $base_url    = esc_url_raw( $current_uri );
    $back_url    = add_query_arg( array( 'sub' => 'list' ), $base_url );

    // Handle Form Submission
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['educore_collect_fee'] ) ) {
        if ( isset( $_POST['educore_fee_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_fee_nonce'] ) ), 'collect_fee_action' ) ) {
            
            $student_id  = isset( $_POST['student_id'] ) ? absint( $_POST['student_id'] ) : 0;
            $amount      = isset( $_POST['amount'] ) ? max( 0, floatval( $_POST['amount'] ) ) : 0;
            $late_fine   = isset( $_POST['late_fine'] ) ? max( 0, floatval( $_POST['late_fine'] ) ) : 0;
            $discount    = isset( $_POST['discount'] ) ? max( 0, floatval( $_POST['discount'] ) ) : 0;
            $paid_amount = isset( $_POST['paid_amount'] ) ? max( 0, floatval( $_POST['paid_amount'] ) ) : 0;
            
            // Mathematical Ledger Rules
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

            // Unique Invoice Identifier
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

                $page_slug    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
                $redirect_url = add_query_arg(
                    array(
                        'page' => $page_slug,
                        'tab'  => 'fees',
                        'sub'  => 'list',
                        'msg'  => 'collected'
                    ),
                    admin_url( 'admin.php' )
                );

                echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_url ) . '";</script>';
                exit;
            } else {
                $db_error = $wpdb->last_error ? $wpdb->last_error : __( 'Failed to record fee entry in database.', 'ifsedu-sms' );
            }
        } else {
            $db_error = __( 'Security nonce mismatch. Please refresh and try again.', 'ifsedu-sms' );
        }
    }

    // Fetch Unique Classes for Filter Console with Natural Numeric Sorting
    $raw_classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    if ( ! empty( $raw_classes ) ) {
        usort( $raw_classes, function( $a, $b ) {
            return strnatcasecmp( $a->class_name, $b->class_name );
        });
    }

    // Fetch Initial Active Students List
    $students = $wpdb->get_results( 
        "SELECT id, full_name, student_id, roll_no, class_name, section_name, shift, fee_start_date, waiver_percentage, waiver_staff_id 
         FROM {$table_students} WHERE status = 'Active' 
         ORDER BY class_name ASC, CAST(roll_no AS UNSIGNED) ASC, roll_no ASC"
    );

    // Fetch default Late Fine Configuration for initial load
    $late_config = $wpdb->get_row( "SELECT * FROM {$table_late_cfg} LIMIT 1" );

    $months        = array( "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December" );
    $current_month = date( 'F' );
    $current_year  = date( 'Y' );
    ?>

    <style>
        .dpt-fees-container {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
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

        .dpt-field-input, .dpt-field-select {
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

        .dpt-field-input:focus, .dpt-field-select:focus {
            outline: none;
            border-color: #006a4e;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
        }

        /* Search & Filter Matrix */
        .dpt-grid-filter-live {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 2fr;
            gap: 14px;
            background: #f8fafc;
            padding: 18px;
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

        @media (max-width: 992px) {
            .dpt-grid-filter-live {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 768px) {
            .dpt-grid-filter-live, .dpt-grid-3, .dpt-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        /* Student Quick Info Strip */
        .dpt-student-quick-strip {
            display: none;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 13.5px;
            color: #065f46;
            animation: dptFadeIn 0.2s ease;
        }

        @keyframes dptFadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Ledger Panel */
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
                <span style="background:#f1f5f9; border:1px solid #e2e8f0; color:#475569; font-weight:700; font-size:12px; padding:4px 12px; border-radius:20px;">
                    <?php echo esc_html( date_i18n( 'd M, Y' ) ); ?>
                </span>
            </div>

            <form method="POST" action="" id="educoreFeeCollectForm">
                <?php wp_nonce_field( 'collect_fee_action', 'educore_fee_nonce' ); ?>
                
                <!-- Live Search + Cascade Category Filter -->
                <div class="dpt-grid-filter-live">
                    
                    <!-- 1. Live Instant ID Search -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label" style="color:#006a4e;">
                            <span class="dashicons dashicons-search" style="font-size:13px; width:13px; height:13px; vertical-align:middle;"></span>
                            <?php esc_html_e( 'Live ID Search', 'ifsedu-sms' ); ?>
                        </label>
                        <input type="text" id="educore_live_id_search" class="dpt-field-input" placeholder="Type ID e.g. GIDS-0001" style="font-weight:700; border-color:#006a4e; background:#ffffff; text-transform:uppercase;" autocomplete="off">
                    </div>

                    <!-- 2. Class Filter -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Class', 'ifsedu-sms' ); ?></label>
                        <select id="educore_fee_class_filter" class="dpt-field-select" style="font-weight:600;">
                            <option value=""><?php esc_html_e( '-- All Classes --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $raw_classes as $cls_obj ) : ?>
                                <option value="<?php echo esc_attr( $cls_obj->class_name ); ?>"><?php printf( esc_html__( '%s', 'ifsedu-sms' ), esc_html( $cls_obj->class_name ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 3. Section Filter (Dynamic) -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Section', 'ifsedu-sms' ); ?></label>
                        <select id="educore_fee_section_filter" class="dpt-field-select" style="font-weight:600;">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- 4. Student Dropdown -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Target Student', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                        <select name="student_id" id="educore_fee_student_select" class="dpt-field-select" style="font-size:13.5px; font-weight:700;" required>
                            <option value=""><?php esc_html_e( '-- Choose Active Student --', 'ifsedu-sms' ); ?></option>
                            <?php if ( ! empty( $students ) ) : ?>
                                <?php foreach ( $students as $s ) : 
                                    $sec_info = ! empty( $s->section_name ) ? ' (' . $s->section_name . ')' : '';
                                    $shift_info = ( ! empty( $s->shift ) && $s->shift !== 'No Shift' ) ? ' [' . $s->shift . ']' : '';
                                ?>
                                    <option value="<?php echo esc_attr( $s->id ); ?>" data-uid="<?php echo esc_attr( strtoupper($s->student_id) ); ?>">
                                        <?php printf( esc_html__( '[Roll: %1$s] - %2$s (ID: %3$s) | %4$s%5$s%6$s', 'ifsedu-sms' ), esc_html( $s->roll_no ), esc_html( $s->full_name ), esc_html( strtoupper($s->student_id) ), esc_html( $s->class_name ), esc_html( $sec_info ), esc_html( $shift_info ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <!-- Dynamic Student Waiver & Auto-Fill Information Strip -->
                <div id="educoreStudentInfoStrip" class="dpt-student-quick-strip">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div>
                            <strong id="stripStudentName" style="font-size:15px; color:#065f46;"></strong> 
                            <span id="stripStudentClass" style="font-weight:600; margin-left:6px;"></span>
                        </div>
                        <div id="stripWaiverBadge" style="display:none; background:#ffffff; border:1.5px solid #059669; padding:4px 14px; border-radius:20px; font-weight:800; color:#059669; font-size:12.5px;">
                            <span class="dashicons dashicons-tag" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                            <span id="stripWaiverText"></span>
                        </div>
                    </div>
                </div>

                <!-- Parameters Grid -->
                <div class="dpt-grid-3" style="margin-bottom: 20px;">
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Fee Category Type', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                        <select name="fee_type" id="fee_type_select" class="dpt-field-select" required>
                            <option value=""><?php esc_html_e( '-- Select Class or Student First --', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Billing Month', 'ifsedu-sms' ); ?></label>
                        <select name="fee_month" id="fee_month_select" class="dpt-field-select" required>
                            <?php foreach ( $months as $m ) : ?>
                                <option value="<?php echo esc_attr( $m ); ?>" <?php selected( $current_month, $m ); ?>>
                                    <?php echo esc_html( $m ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Billing Year', 'ifsedu-sms' ); ?></label>
                        <input type="number" name="fee_year" id="fee_year_input" class="dpt-field-input" value="<?php echo esc_attr( $current_year ); ?>" required>
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
                            <button type="button" class="dpt-btn-pct discount-btn" data-pct="50">50%</button>
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

    <!-- Live Calculations, Automated Late Fines & Auto-Waiver Script -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var nonce = '<?php echo esc_js( wp_create_nonce( "educore_fee_nonce" ) ); ?>';
        var activeStudentWaiverPct = 0;
        var searchDebounceTimer;

        // 1. Live Instant Student ID Search with Auto-Fill
        $('#educore_live_id_search').on('input', function() {
            var searchUid = $(this).val().trim();
            clearTimeout(searchDebounceTimer);

            if (searchUid.length < 2) return;

            searchDebounceTimer = setTimeout(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'educore_get_single_student_waiver_info',
                        security: nonce,
                        search_uid: searchUid
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            var d = response.data;
                            $('#educore_fee_class_filter').val(d.class_name);
                            loadFeeTypesAndFine(d.class_name, function() {
                                loadSectionsAndSelect(d.class_name, d.section_name, d.id, function() {
                                    applyStudentDetails(d);
                                });
                            });
                        }
                    }
                });
            }, 300);
        });

        // 2. Fetch Sections & Fee Types when Class Filter Changes
        $('#educore_fee_class_filter').on('change', function() {
            var selectedClass = $(this).val();
            loadSectionsAndSelect(selectedClass, '', 0);
            loadFeeTypesAndFine(selectedClass);
        });

        // Re-calculate fine if month or year changes
        $('#fee_month_select, #fee_year_input').on('change', function() {
            var selectedClass = $('#educore_fee_class_filter').val();
            if (selectedClass) {
                loadFeeTypesAndFine(selectedClass);
            }
        });

        // 3. Dynamic Fee Types & Automated Fine Loader
        function loadFeeTypesAndFine(className, callback) {
            var $feeTypeSelect = $('#fee_type_select');
            var billingMonth   = $('#fee_month_select').val();
            var billingYear    = $('#fee_year_input').val();
            
            if (!className) {
                $feeTypeSelect.html('<option value=""><?php echo esc_js( __( '-- Select Class or Student First --', 'ifsedu-sms' ) ); ?></option>');
                $('#fee_amount').val('0.00');
                $('#fee_fine').val('0.00');
                applyWaiverDiscount();
                if (typeof callback === 'function') callback();
                return;
            }

            $feeTypeSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Fee Categories... --', 'ifsedu-sms' ) ); ?></option>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'educore_get_fee_types_by_class',
                    security: nonce,
                    class_name: className,
                    billing_month: billingMonth,
                    billing_year: billingYear
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var resData = response.data;
                        
                        // Populate Fee Categories
                        if (resData.fee_types && resData.fee_types.length > 0) {
                            var options = '<option value=""><?php echo esc_js( __( '-- Select Fee Category --', 'ifsedu-sms' ) ); ?></option>';
                            $.each(resData.fee_types, function(i, item) {
                                options += '<option value="' + item.fee_title + '" data-amount="' + item.amount + '">' + item.fee_title + ' (৳' + parseFloat(item.amount).toFixed(2) + ' - ' + item.period_type + ')</option>';
                            });
                            $feeTypeSelect.html(options);

                            var firstAmount = parseFloat(resData.fee_types[0].amount) || 0;
                            $feeTypeSelect.prop('selectedIndex', 1);
                            $('#fee_amount').val(firstAmount.toFixed(2));
                        } else {
                            $feeTypeSelect.html('<option value=""><?php echo esc_js( __( 'No Fee Settings Configured for this Class', 'ifsedu-sms' ) ); ?></option>');
                            $('#fee_amount').val('0.00');
                        }

                        // Apply Automated Late Fine
                        var calculatedFine = parseFloat(resData.late_fine) || 0;
                        if (resData.fine_type === 'Percentage') {
                            var curBase = parseFloat($('#fee_amount').val()) || 0;
                            calculatedFine = (curBase * parseFloat(resData.fine_amount)) / 100;
                        }
                        $('#fee_fine').val(calculatedFine.toFixed(2));
                    }
                    applyWaiverDiscount();
                    if (typeof callback === 'function') callback();
                },
                error: function() {
                    $feeTypeSelect.html('<option value=""><?php echo esc_js( __( '-- Error Loading Fee Categories --', 'ifsedu-sms' ) ); ?></option>');
                    if (typeof callback === 'function') callback();
                }
            });
        }

        // When Fee Type Changes, auto-set Base Amount & Recalculate Percentage Fines
        $('#fee_type_select').on('change', function() {
            var selectedAmount = $(this).find(':selected').data('amount');
            if (typeof selectedAmount !== 'undefined' && selectedAmount !== '') {
                $('#fee_amount').val(parseFloat(selectedAmount).toFixed(2));
            } else {
                $('#fee_amount').val('0.00');
            }
            loadFeeTypesAndFine($('#educore_fee_class_filter').val());
        });

        // 4. Reload Students when Section Filter Changes
        $('#educore_fee_section_filter').on('change', function() {
            var selectedClass   = $('#educore_fee_class_filter').val();
            var selectedSection = $(this).val();
            reloadFeeStudents(selectedClass, selectedSection, 0);
        });

        function loadSectionsAndSelect(selectedClass, targetSection, selectStudentId, callback) {
            var $sectionSelect = $('#educore_fee_section_filter');
            $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>');

            if (!selectedClass) {
                reloadFeeStudents('', '', 0);
                if (typeof callback === 'function') callback();
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'educore_get_sections_by_class_fee',
                    security: nonce,
                    class_name: selectedClass
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var secOptions = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>';
                        $.each(response.data, function(i, sec) {
                            var isSelected = (sec === targetSection) ? 'selected' : '';
                            secOptions += '<option value="' + sec + '" ' + isSelected + '>' + sec + '</option>';
                        });
                        $sectionSelect.html(secOptions);
                    }
                    reloadFeeStudents(selectedClass, targetSection, selectStudentId, callback);
                }
            });
        }

        function reloadFeeStudents(selectedClass, selectedSection, selectStudentId, callback) {
            var $studentSelect = $('#educore_fee_student_select');
            $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Active Students... --', 'ifsedu-sms' ) ); ?></option>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'educore_get_students_for_fee_collect',
                    security: nonce,
                    class_name: selectedClass,
                    section_name: selectedSection
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var options = '<option value=""><?php echo esc_js( __( '-- Search & Select Active Student --', 'ifsedu-sms' ) ); ?></option>';
                        $.each(response.data, function(index, student) {
                            var isSelected = (selectStudentId && student.id === selectStudentId) ? 'selected' : '';
                            options += '<option value="' + student.id + '" data-uid="' + student.student_id + '" ' + isSelected + '>[Roll: ' + student.roll_no + '] - ' + student.full_name + ' (ID: ' + student.student_id + ') | ' + student.class_info + '</option>';
                        });
                        $studentSelect.html(options);

                        if (selectStudentId) {
                            $studentSelect.val(selectStudentId).trigger('change');
                        }
                    } else {
                        $studentSelect.html('<option value=""><?php echo esc_js( __( 'No Active Students Found in Class', 'ifsedu-sms' ) ); ?></option>');
                    }

                    if (typeof callback === 'function') {
                        callback();
                    }
                }
            });
        }

        // 5. Auto-Fetch Student Details & Apply Financial Waiver
        $('#educore_fee_student_select').on('change', function() {
            var stId = $(this).val();
            var $strip = $('#educoreStudentInfoStrip');

            if (!stId) {
                $strip.hide();
                activeStudentWaiverPct = 0;
                calculateLedgerMetrics(true);
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'educore_get_single_student_waiver_info',
                    security: nonce,
                    student_id: stId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var d = response.data;
                        applyStudentDetails(d);
                        if (!$('#educore_fee_class_filter').val() || $('#educore_fee_class_filter').val() !== d.class_name) {
                            $('#educore_fee_class_filter').val(d.class_name);
                            loadFeeTypesAndFine(d.class_name);
                        }
                    }
                }
            });
        });

        function applyStudentDetails(d) {
            var $strip = $('#educoreStudentInfoStrip');
            $('#stripStudentName').text(d.full_name + ' (ID: ' + d.student_id + ')');
            $('#stripStudentClass').text('Class ' + d.class_name + ' [' + d.shift + '] | Roll: #' + d.roll_no);
            
            activeStudentWaiverPct = parseFloat(d.waiver_percentage) || 0;

            if (activeStudentWaiverPct > 0) {
                var refTxt = d.staff_ref_name ? ' | Ref: ' + d.staff_ref_name : '';
                $('#stripWaiverText').text(activeStudentWaiverPct + '% Waiver Active' + refTxt);
                $('#stripWaiverBadge').show();
            } else {
                $('#stripWaiverBadge').hide();
            }

            $strip.slideDown(200);
            applyWaiverDiscount();
        }

        // 6. Live Ledger Math Calculations Engine
        const amtInput  = document.getElementById('fee_amount');
        const fineInput = document.getElementById('fee_fine');
        const discInput = document.getElementById('fee_discount');
        const netInput  = document.getElementById('fee_net');
        const paidInput = document.getElementById('fee_paid');
        const dueInput  = document.getElementById('fee_due');
        const discBtns  = document.querySelectorAll('.discount-btn');

        function applyWaiverDiscount() {
            let baseAmount = parseFloat(amtInput.value) || 0;
            if (activeStudentWaiverPct > 0 && baseAmount > 0) {
                let autoDiscount = (baseAmount * activeStudentWaiverPct) / 100;
                discInput.value = autoDiscount.toFixed(2);
            }
            calculateLedgerMetrics(true);
        }

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
            amtInput.addEventListener('input', function() {
                if (activeStudentWaiverPct > 0) {
                    applyWaiverDiscount();
                } else {
                    calculateLedgerMetrics(true);
                }
            });
            fineInput.addEventListener('input', function() { calculateLedgerMetrics(true); });
            discInput.addEventListener('input', function() { calculateLedgerMetrics(false); });
            paidInput.addEventListener('input', function() { calculateLedgerMetrics(false); });
        }
    });
    </script>
    <?php
}