<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Attendance Reports & Audit Log Workspace (Students, Staff & Exam Attendance)
 * File: inc/attendance/attendance-reports.php
 * Custom Prefixes Applied: dpt-, afdp-
 */

// 1. AJAX Handler for Dynamic Subject Loading in Reports
add_action( 'wp_ajax_educore_get_subjects_by_class_report', 'educore_get_subjects_by_class_report_handler' );
function educore_get_subjects_by_class_report_handler() {
    check_ajax_referer( 'educore_report_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ifsedu-sms' ) ) );
    }

    global $wpdb;
    $table_subjects = $wpdb->prefix . 'sms_subjects';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name   = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $clean_class = trim( str_ireplace( 'Class ', '', $class_name ) );

    $unit_sql    = "SELECT id FROM {$table_units} WHERE (class_name = %s OR class_name = %s)";
    $unit_params = array( $class_name, $clean_class );

    if ( ! empty( $section_name ) ) {
        $unit_sql    .= " AND (section_name = %s OR dept_name = %s)";
        $unit_params[] = $section_name;
        $unit_params[] = $section_name;
    }

    $unit_ids = $wpdb->get_col( $wpdb->prepare( $unit_sql, ...$unit_params ) );

    $subjects = array();
    if ( ! empty( $unit_ids ) ) {
        $in_placeholders = implode( ',', array_fill( 0, count( $unit_ids ), '%d' ) );
        $subjects = $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT id, subject_name, subject_code FROM {$table_subjects} WHERE class_id IN ($in_placeholders) ORDER BY subject_name ASC",
            ...$unit_ids
        ) );
    }

    if ( empty( $subjects ) ) {
        $has_class_name = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_subjects}` LIKE 'class_name'" );
        if ( ! empty( $has_class_name ) ) {
            $subjects = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT id, subject_name, subject_code FROM {$table_subjects} WHERE class_name = %s OR class_name = %s ORDER BY subject_name ASC",
                $class_name, $clean_class
            ) );
        } else {
            $subjects = $wpdb->get_results( "SELECT DISTINCT id, subject_name, subject_code FROM {$table_subjects} ORDER BY subject_name ASC" );
        }
    }

    wp_send_json_success( ! empty( $subjects ) ? $subjects : array() );
}

function educore_student_attendance_log_view( $classes ) {
    global $wpdb;

    $table_students   = $wpdb->prefix . 'sms_students';
    $table_staff      = $wpdb->prefix . 'sms_staff';
    $table_exams      = $wpdb->prefix . 'sms_exams';
    $table_stu_att    = $wpdb->prefix . 'sms_attendance';
    $table_staff_att  = $wpdb->prefix . 'sms_staff_attendance';
    $table_exam_att   = $wpdb->prefix . 'sms_exam_attendance';
    $table_units      = $wpdb->prefix . 'sms_academic_units';
    $table_subjects   = $wpdb->prefix . 'sms_subjects';

    $report_mode       = isset( $_GET['report_mode'] ) ? sanitize_text_field( wp_unslash( $_GET['report_mode'] ) ) : 'student';
    $filter_class      = isset( $_GET['filter_class'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_class'] ) ) : '';
    $filter_section    = isset( $_GET['filter_section'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_section'] ) ) : '';
    $filter_student_id = isset( $_GET['student_id'] ) ? absint( $_GET['student_id'] ) : 0;
    $filter_subject    = isset( $_GET['subject_name'] ) ? sanitize_text_field( wp_unslash( $_GET['subject_name'] ) ) : '';
    
    // Exam Attendance filters
    $filter_exam_id    = isset( $_GET['exam_id'] ) ? absint( $_GET['exam_id'] ) : 0;
    $exam_class        = isset( $_GET['exam_class'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_class'] ) ) : '';
    $exam_subject      = isset( $_GET['exam_subject'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_subject'] ) ) : '';

    $filter_staff_type = isset( $_GET['staff_type'] ) ? sanitize_text_field( wp_unslash( $_GET['staff_type'] ) ) : '';
    $filter_staff_id   = isset( $_GET['staff_id'] ) ? absint( $_GET['staff_id'] ) : 0;

    $start_date        = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : date( 'Y-m-01' );
    $end_date          = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : current_time( 'Y-m-d' );

    $subject_person = null;
    $exam_meta      = null;
    $logs           = array();

    // 1. Fetch Records Based on Mode
    if ( $report_mode === 'student' && $filter_student_id > 0 ) {
        $subject_person = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_students} WHERE id = %d", $filter_student_id ) );
        if ( $subject_person ) {
            $log_sql    = "SELECT attendance_date, status FROM {$table_stu_att} WHERE student_id = %d AND attendance_date BETWEEN %s AND %s";
            $log_params = array( $filter_student_id, $start_date, $end_date );

            $has_subject_col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_stu_att}` LIKE 'subject_name'" );
            if ( ! empty( $has_subject_col ) && ! empty( $filter_subject ) ) {
                $log_sql    .= " AND subject_name = %s";
                $log_params[] = $filter_subject;
            }

            $log_sql .= " ORDER BY attendance_date DESC";
            $logs = $wpdb->get_results( $wpdb->prepare( $log_sql, ...$log_params ) );
        }
    } elseif ( $report_mode === 'exam' && $filter_exam_id > 0 && ! empty( $exam_class ) && ! empty( $exam_subject ) ) {
        $exam_meta = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_exams} WHERE id = %d", $filter_exam_id ) );
        if ( $exam_meta ) {
            $clean_ex_class = trim( str_ireplace( 'Class ', '', $exam_class ) );
            $logs = $wpdb->get_results( $wpdb->prepare(
                "SELECT ea.*, s.full_name, s.student_id as reg_id, s.roll_no 
                 FROM {$table_exam_att} ea 
                 INNER JOIN {$table_students} s ON ea.student_id = s.id 
                 WHERE ea.exam_id = %d AND (ea.class_name = %s OR ea.class_name = %s) AND ea.subject_name = %s 
                 ORDER BY CAST(s.roll_no AS UNSIGNED) ASC, s.roll_no ASC",
                $filter_exam_id, $exam_class, $clean_ex_class, $exam_subject
            ) );
        }
    } elseif ( $report_mode === 'staff' && $filter_staff_id > 0 ) {
        $subject_person = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_staff} WHERE id = %d", $filter_staff_id ) );
        if ( $subject_person ) {
            $logs = $wpdb->get_results( $wpdb->prepare(
                "SELECT attendance_date, status FROM {$table_staff_att} WHERE staff_id = %d AND attendance_date BETWEEN %s AND %s ORDER BY attendance_date DESC",
                $filter_staff_id, $start_date, $end_date
            ) );
        }
    }

    // Pre-load lookup datasets
    $all_units       = $wpdb->get_results( "SELECT id, class_name, section_name FROM {$table_units} WHERE section_name != '' ORDER BY section_name ASC" );
    $all_students    = $wpdb->get_results( "SELECT id, full_name, student_id, roll_no, class_name, section_name FROM {$table_students} WHERE status = 'Active' ORDER BY CAST(roll_no AS UNSIGNED) ASC, full_name ASC" );
    $all_staff       = $wpdb->get_results( "SELECT id, full_name, name_bn, staff_id, designation, staff_type FROM {$table_staff} WHERE status = 'Active' ORDER BY full_name ASC" );
    $all_exams       = $wpdb->get_results( "SELECT id, exam_name FROM {$table_exams} ORDER BY id DESC" );
    
    $db_staff_types  = $wpdb->get_col( "SELECT DISTINCT staff_type FROM {$table_staff} WHERE status = 'Active' AND staff_type != '' ORDER BY staff_type ASC" );
    $default_types   = array( 'Teacher (School)', 'Teacher (College)', 'Officer', 'Staff' );
    $all_staff_types = array_unique( array_merge( $default_types, $db_staff_types ) );

    $available_subjects = array();
    if ( ! empty( $filter_class ) ) {
        $clean_c = trim( str_ireplace( 'Class ', '', $filter_class ) );
        $u_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table_units} WHERE (class_name = %s OR class_name = %s)", $filter_class, $clean_c ) );
        if ( ! empty( $u_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $u_ids ), '%d' ) );
            $available_subjects = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT subject_name, subject_code FROM {$table_subjects} WHERE class_id IN ($placeholders) ORDER BY subject_name ASC", ...$u_ids ) );
        }
    }
    ?>

    <style>
        .report-mode-segmented { display: inline-flex; background: #f1f5f9; padding: 4px; border-radius: 10px; border: 1px solid #cbd5e1; gap: 4px; flex-wrap: wrap; }
        .report-mode-input { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
        .report-mode-pill { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 13px; font-weight: 700; border-radius: 7px; cursor: pointer; transition: all 0.2s ease; color: #64748b; border: 1px solid transparent; line-height: 1; }
        .report-mode-pill .dashicons { font-size: 16px; width: 16px; height: 16px; opacity: 0.7; }
        .report-mode-pill:hover { color: #0f172a; background: rgba(255, 255, 255, 0.6); }
        .report-mode-input:checked + .report-mode-pill { background: #006a4e; color: #ffffff; border-color: #00523c; box-shadow: 0 2px 8px rgba(0, 106, 78, 0.25); }
        .report-mode-input:checked + .report-mode-pill .dashicons { opacity: 1; }

        .att-status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .att-badge-present { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .att-badge-absent  { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .att-badge-late    { background: #fff7ed; color: #d97706; border: 1px solid #fed7aa; }
    </style>

    <!-- Filter Controls Bento Card -->
    <div class="dpt-bento-card no-print" style="background:#fff; border:1px solid #e2e8f0; padding:24px; border-radius:12px; margin-bottom:24px;">
        <form method="GET" action="" id="educore_reports_form">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            <input type="hidden" name="sub" value="reports">

            <!-- Report Mode Switcher -->
            <div style="margin-bottom:20px; display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                <span style="font-size:13px; font-weight:700; color:#475569;"><?php esc_html_e( 'Audit Scope Target:', 'ifsedu-sms' ); ?></span>
                
                <div class="report-mode-segmented">
                    <input type="radio" class="report-mode-input" id="mode_student" name="report_mode" value="student" <?php checked( $report_mode, 'student' ); ?>>
                    <label class="report-mode-pill" for="mode_student">
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                        <?php esc_html_e( 'Individual Student Log', 'ifsedu-sms' ); ?>
                    </label>

                    <input type="radio" class="report-mode-input" id="mode_exam" name="report_mode" value="exam" <?php checked( $report_mode, 'exam' ); ?>>
                    <label class="report-mode-pill" for="mode_exam">
                        <span class="dashicons dashicons-awards"></span>
                        <?php esc_html_e( 'Exam Attendance Audit', 'ifsedu-sms' ); ?>
                    </label>

                    <input type="radio" class="report-mode-input" id="mode_staff" name="report_mode" value="staff" <?php checked( $report_mode, 'staff' ); ?>>
                    <label class="report-mode-pill" for="mode_staff">
                        <span class="dashicons dashicons-businessman"></span>
                        <?php esc_html_e( 'Employment Type (Faculty / Staff)', 'ifsedu-sms' ); ?>
                    </label>
                </div>
            </div>

            <!-- 1. STUDENT MODE CONTROLS -->
            <div id="wrapper_student_controls" style="display: <?php echo ( $report_mode === 'student' ) ? 'grid' : 'none'; ?>; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:16px; align-items:flex-end;">
                <div class="dpt-form-group">
                    <label class="dpt-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Academic Class', 'ifsedu-sms' ); ?></label>
                    <select name="filter_class" id="rpt_class_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Section', 'ifsedu-sms' ); ?></label>
                    <select name="filter_section" id="rpt_section_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Subject (Optional)', 'ifsedu-sms' ); ?></label>
                    <select name="subject_name" id="rpt_subject_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- All Subjects --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $available_subjects as $sub ) : ?>
                            <option value="<?php echo esc_attr( $sub->subject_name ); ?>" <?php selected( $filter_subject, $sub->subject_name ); ?>><?php echo esc_html( $sub->subject_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-form-group" style="grid-column: span 2;">
                    <label class="dpt-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Student', 'ifsedu-sms' ); ?> *</label>
                    <select name="student_id" id="report_student_id" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- Choose Student --', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>
            </div>

            <!-- 2. EXAM ATTENDANCE MODE CONTROLS -->
            <div id="wrapper_exam_controls" style="display: <?php echo ( $report_mode === 'exam' ) ? 'grid' : 'none'; ?>; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; align-items:flex-end;">
                <div class="dpt-form-group">
                    <label class="dpt-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Exam', 'ifsedu-sms' ); ?> *</label>
                    <select name="exam_id" id="rpt_exam_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $all_exams as $ex ) : ?>
                            <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam_id, $ex->id ); ?>><?php echo esc_html( $ex->exam_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Class Name', 'ifsedu-sms' ); ?> *</label>
                    <select name="exam_class" id="rpt_exam_class" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $exam_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Exam Subject', 'ifsedu-sms' ); ?> *</label>
                    <select name="exam_subject" id="rpt_exam_subject" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>
            </div>

            <!-- 3. STAFF MODE CONTROLS -->
            <div id="wrapper_staff_controls" style="display: <?php echo ( $report_mode === 'staff' ) ? 'flex' : 'none'; ?>; gap:16px; flex-wrap:wrap; align-items:flex-end;">
                <div class="dpt-form-group" style="flex:1; min-width:200px;">
                    <label class="dpt-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Employment Type', 'ifsedu-sms' ); ?> *</label>
                    <select name="staff_type" id="report_staff_type" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- All Employment Types --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $all_staff_types as $st_type ) : ?>
                            <option value="<?php echo esc_attr( $st_type ); ?>" <?php selected( $filter_staff_type, $st_type ); ?>><?php echo esc_html( $st_type ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-form-group" style="flex:2; min-width:220px;">
                    <label class="dpt-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Employee', 'ifsedu-sms' ); ?> *</label>
                    <select name="staff_id" id="report_staff_id" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- Choose Employee --', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>
            </div>

            <!-- DATE RANGE & SUBMIT ROW (Hidden during Exam Audit mode) -->
            <div id="wrapper_date_range" style="display: <?php echo ( $report_mode === 'exam' ) ? 'none' : 'flex'; ?>; gap:16px; margin-top:16px; align-items:flex-end; flex-wrap:wrap;">
                <div class="dpt-form-group" style="flex:1; min-width:160px;">
                    <label class="dpt-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'From Date', 'ifsedu-sms' ); ?></label>
                    <input type="date" name="start_date" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" value="<?php echo esc_attr( $start_date ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                </div>

                <div class="dpt-form-group" style="flex:1; min-width:160px;">
                    <label class="dpt-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'To Date', 'ifsedu-sms' ); ?></label>
                    <input type="date" name="end_date" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" value="<?php echo esc_attr( $end_date ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                </div>

                <div class="dpt-form-group" style="flex:1; min-width:160px;">
                    <button type="submit" style="width:100%; height:40px; background:#006a4e; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Fetch Attendance Log', 'ifsedu-sms' ); ?></button>
                </div>
            </div>

            <!-- Submit trigger specifically for Exam mode -->
            <div id="wrapper_exam_submit" style="display: <?php echo ( $report_mode === 'exam' ) ? 'block' : 'none'; ?>; margin-top:16px;">
                <button type="submit" style="width:100%; max-width:240px; height:40px; background:#006a4e; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Load Exam Roster Audit', 'ifsedu-sms' ); ?></button>
            </div>
        </form>
    </div>

    <!-- STATEMENT RESULT -->
    <?php if ( $subject_person || $exam_meta ) : 
        if ( $report_mode === 'exam' && $exam_meta ) {
            $display_title = $exam_meta->exam_name . ' — Class ' . $exam_class;
            $display_code  = 'Subject: ' . $exam_subject;
            $meta_line     = sprintf( __( 'Total Candidates Audited: %d Candidates', 'ifsedu-sms' ), count( $logs ) );
        } else {
            $display_title = ( $report_mode === 'student' ) ? $subject_person->full_name : ( ! empty( $subject_person->name_bn ) ? $subject_person->name_bn : $subject_person->full_name );
            $display_code  = ( $report_mode === 'student' ) ? $subject_person->student_id : ( ! empty( $subject_person->staff_id ) ? $subject_person->staff_id : '#' . $subject_person->id );
            $meta_line     = ( $report_mode === 'student' ) 
                           ? sprintf( __( 'Class: %1$s %2$s %3$s | Log Period: %4$s to %5$s', 'ifsedu-sms' ), esc_html( $subject_person->class_name ), esc_html( $subject_person->section_name ? '(' . $subject_person->section_name . ')' : '' ), esc_html( $filter_subject ? ' | Subject: ' . $filter_subject : '' ), esc_html( $start_date ), esc_html( $end_date ) )
                           : sprintf( __( 'Designation: %1$s (%2$s) | Log Period: %3$s to %4$s', 'ifsedu-sms' ), esc_html( $subject_person->designation ? $subject_person->designation : 'Faculty' ), esc_html( $subject_person->staff_type ), esc_html( $start_date ), esc_html( $end_date ) );
        }
    ?>
        <div class="dpt-bento-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:20px;">
                <div>
                    <h3 style="margin:0; font-weight:800; font-size:18px; color:#0f172a;"><?php echo esc_html( $display_title ); ?> <small style="color:#64748b; font-size:14px;">(<?php echo esc_html( $display_code ); ?>)</small></h3>
                    <span style="color:#64748b; font-size:13px; font-weight:600;"><?php echo $meta_line; ?></span>
                </div>
                <button type="button" onclick="window.print();" class="no-print" style="height:36px; padding:0 16px; background:#0f172a; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer;">
                    <span class="dashicons dashicons-printer" style="vertical-align:middle; font-size:16px; width:16px; height:16px;"></span>
                    <?php esc_html_e( 'Print Log Statement', 'ifsedu-sms' ); ?>
                </button>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <?php if ( $report_mode === 'exam' ) : ?>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:15%;"><?php esc_html_e( 'Roll', 'ifsedu-sms' ); ?></th>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:25%;"><?php esc_html_e( 'Candidate Name', 'ifsedu-sms' ); ?></th>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:30%;"><?php esc_html_e( 'Invigilator Notes', 'ifsedu-sms' ); ?></th>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:30%; text-align:right;"><?php esc_html_e( 'Exam Hall Status', 'ifsedu-sms' ); ?></th>
                            <?php else : ?>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:30%;"><?php esc_html_e( 'Date', 'ifsedu-sms' ); ?></th>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%;"><?php esc_html_e( 'Day', 'ifsedu-sms' ); ?></th>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%; text-align:right;"><?php esc_html_e( 'Attendance Status', 'ifsedu-sms' ); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $logs ) ) : foreach ( $logs as $l ) : 
                            if ( $report_mode === 'exam' ) {
                                $status = $l->status;
                            } else {
                                $time   = strtotime( $l->attendance_date );
                                $status = $l->status;
                            }
                        ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <?php if ( $report_mode === 'exam' ) : ?>
                                    <td style="padding:12px 16px;"><strong>#<?php echo esc_html( $l->roll_no ); ?></strong></td>
                                    <td style="padding:12px 16px; font-weight:700; color:#0f172a;"><?php echo esc_html( $l->full_name ); ?> <small style="color:#64748b;">(<?php echo esc_html( strtoupper( $l->reg_id ) ); ?>)</small></td>
                                    <td style="padding:12px 16px; color:#475569;"><?php echo esc_html( $l->invigilator_remarks ? $l->invigilator_remarks : '—' ); ?></td>
                                    <td style="padding:12px 16px; text-align:right;">
                                        <?php if ( $status === 'Present' ) : ?>
                                            <span class="att-status-badge att-badge-present"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Present', 'ifsedu-sms' ); ?></span>
                                        <?php elseif ( $status === 'Absent' ) : ?>
                                            <span class="att-status-badge att-badge-absent"><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Absent', 'ifsedu-sms' ); ?></span>
                                        <?php else : ?>
                                            <span class="att-status-badge att-badge-late"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Late / Expelled', 'ifsedu-sms' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php else : ?>
                                    <td style="padding:12px 16px;"><strong><?php echo date_i18n( 'd F, Y', $time ); ?></strong></td>
                                    <td style="padding:12px 16px; color:#475569; font-weight:600;"><?php echo date_i18n( 'l', $time ); ?></td>
                                    <td style="padding:12px 16px; text-align:right;">
                                        <?php if ( $status === 'Present' ) : ?>
                                            <span class="att-status-badge att-badge-present"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Present', 'ifsedu-sms' ); ?></span>
                                        <?php elseif ( $status === 'Absent' ) : ?>
                                            <span class="att-status-badge att-badge-absent"><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Absent', 'ifsedu-sms' ); ?></span>
                                        <?php else : ?>
                                            <span class="att-status-badge att-badge-late"><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Late', 'ifsedu-sms' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="4" style="text-align:center; color:#94a3b8; padding:40px; font-weight:600;"><?php esc_html_e( 'No attendance logs recorded matching the selected criteria.', 'ifsedu-sms' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- DYNAMIC JS ENGINE: Robust Cascading Sections, Subjects & Students -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const ajaxUrl     = '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>';
        const reportNonce = '<?php echo esc_js( wp_create_nonce( "educore_report_nonce" ) ); ?>';

        const unitsMap    = <?php echo wp_json_encode( ! empty( $all_units ) ? $all_units : array() ); ?>;
        const studentsMap = <?php echo wp_json_encode( ! empty( $all_students ) ? $all_students : array() ); ?>;
        const staffMap    = <?php echo wp_json_encode( ! empty( $all_staff ) ? $all_staff : array() ); ?>;

        const currentClass     = "<?php echo esc_js( $filter_class ); ?>";
        const currentSection   = "<?php echo esc_js( $filter_section ); ?>";
        const currentSubject   = "<?php echo esc_js( $filter_subject ); ?>";
        const currentStudentId = "<?php echo esc_js( $filter_student_id ); ?>";

        const currentExamClass = "<?php echo esc_js( $exam_class ); ?>";
        const currentExamSub   = "<?php echo esc_js( $exam_subject ); ?>";

        const currentStaffType = "<?php echo esc_js( $filter_staff_type ); ?>";
        const currentStaffId   = "<?php echo esc_js( $filter_staff_id ); ?>";

        const modeRadios     = document.querySelectorAll('input[name="report_mode"]');
        const wrapperStudent = document.getElementById('wrapper_student_controls');
        const wrapperExam    = document.getElementById('wrapper_exam_controls');
        const wrapperStaff   = document.getElementById('wrapper_staff_controls');
        const wrapperDate    = document.getElementById('wrapper_date_range');
        const wrapperExSub   = document.getElementById('wrapper_exam_submit');

        const classSelect    = document.getElementById('rpt_class_select');
        const sectionSelect  = document.getElementById('rpt_section_select');
        const subjectSelect  = document.getElementById('rpt_subject_select');
        const studentSelect  = document.getElementById('report_student_id');

        const examClassSelect = document.getElementById('rpt_exam_class');
        const examSubSelect   = document.getElementById('rpt_exam_subject');

        const staffTypeSelect = document.getElementById('report_staff_type');
        const staffSelect     = document.getElementById('report_staff_id');

        // 1. Toggle Mode Visibility
        modeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'student') {
                    wrapperStudent.style.display = 'grid';
                    wrapperExam.style.display    = 'none';
                    wrapperStaff.style.display   = 'none';
                    wrapperDate.style.display    = 'flex';
                    wrapperExSub.style.display   = 'none';
                } else if (this.value === 'exam') {
                    wrapperStudent.style.display = 'none';
                    wrapperExam.style.display    = 'grid';
                    wrapperStaff.style.display   = 'none';
                    wrapperDate.style.display    = 'none';
                    wrapperExSub.style.display   = 'block';
                    if (examClassSelect.value) {
                        loadExamSubjects(examClassSelect.value, currentExamSub);
                    }
                } else {
                    wrapperStudent.style.display = 'none';
                    wrapperExam.style.display    = 'none';
                    wrapperStaff.style.display   = 'flex';
                    wrapperDate.style.display    = 'flex';
                    wrapperExSub.style.display   = 'none';
                    populateStaffByType(staffTypeSelect.value, currentStaffId);
                }
            });
        });

        // 2. Populate Sections based on Class
        function populateSections(selectedClass, selectedSecName = '') {
            if (!sectionSelect) return;
            sectionSelect.innerHTML = '<option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>';
            if (!selectedClass) return;

            const filtered = unitsMap.filter(item => item.class_name == selectedClass);
            const uniqueSections = [...new Set(filtered.map(item => item.section_name).filter(Boolean))];

            uniqueSections.forEach(secName => {
                const opt = document.createElement('option');
                opt.value = secName;
                opt.textContent = secName;
                if (secName == selectedSecName) {
                    opt.selected = true;
                }
                sectionSelect.appendChild(opt);
            });
        }

        // 3. Fetch & Populate Subjects dynamically via AJAX (Student Mode)
        function loadSubjects(selectedClass, selectedSection, targetSubject = '') {
            if (!subjectSelect) return;
            subjectSelect.innerHTML = '<option value=""><?php esc_html_e( '-- Loading Subjects... --', 'ifsedu-sms' ); ?></option>';

            if (!selectedClass) {
                subjectSelect.innerHTML = '<option value=""><?php esc_html_e( '-- All Subjects --', 'ifsedu-sms' ); ?></option>';
                return;
            }

            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'educore_get_subjects_by_class_report',
                    security: reportNonce,
                    class_name: selectedClass,
                    section_name: selectedSection
                },
                success: function(response) {
                    if (response.success && response.data && response.data.length > 0) {
                        let opts = '<option value=""><?php esc_html_e( '-- All Subjects --', 'ifsedu-sms' ); ?></option>';
                        response.data.forEach(sub => {
                            let codeStr = sub.subject_code ? ` (${sub.subject_code})` : '';
                            let isSel = (sub.subject_name === targetSubject) ? 'selected' : '';
                            opts += `<option value="${sub.subject_name}" ${isSel}>${sub.subject_name}${codeStr}</option>`;
                        });
                        subjectSelect.innerHTML = opts;
                    } else {
                        subjectSelect.innerHTML = '<option value=""><?php esc_html_e( 'No Subjects Configured', 'ifsedu-sms' ); ?></option>';
                    }
                },
                error: function() {
                    subjectSelect.innerHTML = '<option value=""><?php esc_html_e( '-- Error Loading Subjects --', 'ifsedu-sms' ); ?></option>';
                }
            });
        }

        // 4. Fetch & Populate Subjects dynamically via AJAX (Exam Mode)
        function loadExamSubjects(selectedClass, targetSubject = '') {
            if (!examSubSelect) return;
            examSubSelect.innerHTML = '<option value=""><?php esc_html_e( '-- Loading Subjects... --', 'ifsedu-sms' ); ?></option>';

            if (!selectedClass) {
                examSubSelect.innerHTML = '<option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-sms' ); ?></option>';
                return;
            }

            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'educore_get_subjects_by_class_report',
                    security: reportNonce,
                    class_name: selectedClass,
                    section_name: ''
                },
                success: function(response) {
                    if (response.success && response.data && response.data.length > 0) {
                        let opts = '<option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-sms' ); ?></option>';
                        response.data.forEach(sub => {
                            let codeStr = sub.subject_code ? ` (${sub.subject_code})` : '';
                            let isSel = (sub.subject_name === targetSubject) ? 'selected' : '';
                            opts += `<option value="${sub.subject_name}" ${isSel}>${sub.subject_name}${codeStr}</option>`;
                        });
                        examSubSelect.innerHTML = opts;
                    } else {
                        examSubSelect.innerHTML = '<option value=""><?php esc_html_e( 'No Subjects Found', 'ifsedu-sms' ); ?></option>';
                    }
                },
                error: function() {
                    examSubSelect.innerHTML = '<option value=""><?php esc_html_e( '-- Error --', 'ifsedu-sms' ); ?></option>';
                }
            });
        }

        // 5. Populate Students based on Class and Section
        function populateStudents(selectedClass, selectedSecName, selectedStudentId = '') {
            if (!studentSelect) return;
            studentSelect.innerHTML = '<option value=""><?php esc_html_e( '-- Choose Student --', 'ifsedu-sms' ); ?></option>';

            let filteredStudents = studentsMap;

            if (selectedClass) {
                filteredStudents = filteredStudents.filter(item => item.class_name == selectedClass);
            }

            if (selectedSecName) {
                filteredStudents = filteredStudents.filter(item => item.section_name == selectedSecName);
            }

            filteredStudents.forEach(stu => {
                const opt = document.createElement('option');
                opt.value = stu.id;
                opt.textContent = stu.roll_no ? `[Roll: ${stu.roll_no}] ${stu.full_name} (${stu.class_name})` : `${stu.full_name} (${stu.class_name})`;
                
                if (String(stu.id) === String(selectedStudentId)) {
                    opt.selected = true;
                }
                studentSelect.appendChild(opt);
            });
        }

        if (classSelect && sectionSelect && subjectSelect && studentSelect) {
            populateSections(classSelect.value, currentSection);
            populateStudents(classSelect.value, currentSection, currentStudentId);

            classSelect.addEventListener('change', function() {
                populateSections(this.value, '');
                loadSubjects(this.value, '', '');
                populateStudents(this.value, '', '');
            });

            sectionSelect.addEventListener('change', function() {
                loadSubjects(classSelect.value, this.value, '');
                populateStudents(classSelect.value, this.value, '');
            });
        }

        if (examClassSelect) {
            if (examClassSelect.value) {
                loadExamSubjects(examClassSelect.value, currentExamSub);
            }
            examClassSelect.addEventListener('change', function() {
                loadExamSubjects(this.value, '');
            });
        }

        // 6. Robust Staff Auto-populate
        function populateStaffByType(selectedType, selectedStaffId = '') {
            if (!staffSelect) return;
            staffSelect.innerHTML = '<option value=""><?php esc_html_e( '-- Choose Employee --', 'ifsedu-sms' ); ?></option>';

            let filteredStaff = staffMap;

            if (selectedType && selectedType.trim() !== '') {
                const targetLower = selectedType.trim().toLowerCase();
                filteredStaff = staffMap.filter(item => {
                    const itemTypeLower = (item.staff_type || '').trim().toLowerCase();
                    return itemTypeLower === targetLower || itemTypeLower.includes(targetLower) || targetLower.includes(itemTypeLower);
                });

                if (filteredStaff.length === 0) {
                    filteredStaff = staffMap;
                }
            }

            filteredStaff.forEach(st => {
                const opt = document.createElement('option');
                opt.value = st.id;
                const name = st.name_bn ? st.name_bn : st.full_name;
                opt.textContent = st.designation ? `${name} (${st.designation})` : name;
                
                if (String(st.id) === String(selectedStaffId)) {
                    opt.selected = true;
                }
                staffSelect.appendChild(opt);
            });
        }

        if (staffTypeSelect && staffSelect) {
            populateStaffByType(staffTypeSelect.value, currentStaffId);

            staffTypeSelect.addEventListener('change', function() {
                populateStaffByType(this.value);
            });
        }
    });
    </script>
    <?php
}