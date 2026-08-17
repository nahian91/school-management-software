<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Examination Hall Attendance Roster & Hall Invigilator Log View
 * File: inc/attendance/attendance-exam.php
 * Custom Prefixes Applied: dpt-, afdp-
 * Teacher Scope: Restricts Class/Section/Subject dropdowns to `sms_teacher_subjects` for logged-in Teachers.
 */

function educore_exam_attendance_view() {
    global $wpdb;
    $current_user = wp_get_current_user();

    $table_students         = $wpdb->prefix . 'sms_students';
    $table_exams            = $wpdb->prefix . 'sms_exams';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_subjects         = $wpdb->prefix . 'sms_subjects';
    $table_exam_att         = $wpdb->prefix . 'sms_exam_attendance';
    $table_staff            = $wpdb->prefix . 'sms_staff';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';

    // 1. Procedural Role & Capability Validation
    $is_admin = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    
    $is_staff = false;
    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author', 'contributor', 'subscriber' ) );
    }

    if ( ! $is_staff && ! $is_admin ) {
        $staff_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table_staff} WHERE wp_user_id = %d OR email = %s LIMIT 1",
            $current_user->ID,
            $current_user->user_email
        ) );
        if ( $staff_exists ) {
            $is_staff = true;
        }
    }

    if ( ! $is_admin && ! $is_staff ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view examination hall attendance.', 'ifsedu-sms' ) );
    }

    // --------------------------------------------------------------------------
    // 0. AUTO-SCHEMA CHECK (Ensures exam attendance table exists)
    // --------------------------------------------------------------------------
    $check_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_exam_att}'" );
    if ( empty( $check_table ) ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $sql_exam_att = "CREATE TABLE {$table_exam_att} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            exam_id bigint(20) NOT NULL,
            student_id bigint(20) NOT NULL,
            class_name varchar(50) NOT NULL,
            section_name varchar(50) DEFAULT '' NOT NULL,
            subject_name varchar(150) NOT NULL,
            attendance_date date DEFAULT '1970-01-01' NOT NULL,
            status varchar(20) DEFAULT 'Present' NOT NULL,
            invigilator_remarks varchar(255) DEFAULT '' NOT NULL,
            recorded_by bigint(20) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY exam_student_subject_date (exam_id, student_id, subject_name, attendance_date),
            KEY exam_student_idx (exam_id, student_id),
            KEY status_idx (status)
        ) $charset_collate;";
        dbDelta( $sql_exam_att );
    }

    $saved_notice = '';

    // Capture GET Request Parameters FIRST so they are available for boundary checks
    $filter_exam    = isset( $_GET['exam_id'] ) ? absint( $_GET['exam_id'] ) : 0;
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $filter_subject = isset( $_GET['subject_name'] ) ? sanitize_text_field( wp_unslash( $_GET['subject_name'] ) ) : '';
    $filter_date    = isset( $_GET['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_GET['attendance_date'] ) ) : current_time( 'Y-m-d' );

    // --------------------------------------------------------------------------
    // RESOLVE TEACHER SUBJECT & CLASS ALLOCATIONS
    // --------------------------------------------------------------------------
    $teacher_assigned_classes = array();
    $teacher_assigned_subs    = array();

    if ( ! $is_admin ) {
        $teacher_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table_staff} WHERE wp_user_id = %d OR email = %s OR full_name = %s LIMIT 1",
            $current_user->ID,
            $current_user->user_email,
            $current_user->display_name
        ) );

        if ( $teacher_id ) {
            $allocations = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT u.class_name, u.section_name, s.subject_name 
                 FROM {$table_teacher_subjects} ts
                 INNER JOIN {$table_units} u ON ts.class_id = u.id
                 INNER JOIN {$table_subjects} s ON ts.subject_id = s.id
                 WHERE ts.teacher_id = %d",
                $teacher_id
            ) );

            foreach ( $allocations as $al ) {
                if ( ! in_array( $al->class_name, $teacher_assigned_classes, true ) ) {
                    $teacher_assigned_classes[] = $al->class_name;
                }
                $teacher_assigned_subs[ $al->class_name ][] = $al->subject_name;
            }
        }
    }

    // --------------------------------------------------------------------------
    // 1. SAVE EXAM ATTENDANCE FORM SUBMISSION
    // --------------------------------------------------------------------------
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['educore_save_exam_attendance'] ) ) {
        if ( isset( $_POST['educore_exam_att_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_exam_att_nonce_field'] ) ), 'save_exam_attendance_action' ) ) {
            
            // Boundary check: Non-admin teacher can only submit for their assigned class and subject if allocations exist
            if ( ! $is_admin && ! empty( $teacher_assigned_classes ) && ( ! in_array( $filter_class, $teacher_assigned_classes, true ) || ! in_array( $filter_subject, (array) ( $teacher_assigned_subs[ $filter_class ] ?? array() ), true ) ) ) {
                wp_die( esc_html__( 'Security Check: You are not authorized to submit examination attendance for this allocation.', 'ifsedu-sms' ) );
            }

            $exam_id         = isset( $_POST['exam_id'] ) ? absint( $_POST['exam_id'] ) : 0;
            $class_name      = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
            $section_name    = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';
            $subject_name    = isset( $_POST['subject_name'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_name'] ) ) : '';
            $attendance_date = isset( $_POST['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_POST['attendance_date'] ) ) : current_time( 'Y-m-d' );
            $att_statuses    = isset( $_POST['att_status'] ) && is_array( $_POST['att_status'] ) ? $_POST['att_status'] : array();
            $invig_remarks   = isset( $_POST['invigilator_remarks'] ) && is_array( $_POST['invigilator_remarks'] ) ? $_POST['invigilator_remarks'] : array();

            $saved_count = 0;
            if ( $exam_id > 0 && ! empty( $class_name ) && ! empty( $subject_name ) && ! empty( $att_statuses ) ) {
                foreach ( $att_statuses as $student_id => $status_val ) {
                    $st_id   = absint( $student_id );
                    $status  = sanitize_text_field( $status_val );
                    $remarks = isset( $invig_remarks[ $student_id ] ) ? sanitize_text_field( wp_unslash( $invig_remarks[ $student_id ] ) ) : '';

                    $existing_id = $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$table_exam_att} WHERE exam_id = %d AND student_id = %d AND subject_name = %s AND attendance_date = %s",
                        $exam_id, $st_id, $subject_name, $attendance_date
                    ) );

                    $data = array(
                        'exam_id'             => $exam_id,
                        'student_id'          => $st_id,
                        'class_name'          => $class_name,
                        'section_name'        => $section_name,
                        'subject_name'        => $subject_name,
                        'attendance_date'     => $attendance_date,
                        'status'              => $status,
                        'invigilator_remarks' => $remarks,
                        'recorded_by'         => get_current_user_id()
                    );

                    if ( $existing_id ) {
                        $wpdb->update( $table_exam_att, $data, array( 'id' => $existing_id ) );
                    } else {
                        $wpdb->insert( $table_exam_att, $data );
                    }
                    $saved_count++;
                }

                $saved_notice = sprintf( esc_html__( 'Successfully recorded examination hall attendance for %d candidates.', 'ifsedu-sms' ), $saved_count );
            }
        }
    }

    $exams = $wpdb->get_results( "SELECT id, exam_name FROM {$table_exams} ORDER BY id DESC" );

    // Fetch Unique Classes and build section maps with Natural Numeric Sorting & Complete Subject Info (Scoped if Teacher)
    $raw_units = $wpdb->get_results( "SELECT id, class_name, section_name, dept_name FROM {$table_units} WHERE class_name != ''" );
    $academic_classes   = array();
    $class_section_map  = array();
    $class_subject_map  = array();

    if ( ! empty( $raw_units ) ) {
        foreach ( $raw_units as $unit ) {
            $c_name = trim( $unit->class_name );

            // If teacher mode and has assigned classes, filter dropdowns accordingly
            if ( ! $is_admin && ! empty( $teacher_assigned_classes ) && ! in_array( $c_name, $teacher_assigned_classes, true ) ) {
                continue;
            }

            if ( ! isset( $class_section_map[ $c_name ] ) ) {
                $class_section_map[ $c_name ] = array();
                $class_subject_map[ $c_name ] = array();
                $academic_classes[] = $c_name;
            }
            if ( ! empty( $unit->section_name ) ) {
                $class_section_map[ $c_name ][] = trim( $unit->section_name );
            }
            if ( ! empty( $unit->dept_name ) ) {
                $class_section_map[ $c_name ][] = trim( $unit->dept_name );
            }

            // Fetch subjects mapped strictly class-wise
            $clean_c = trim( str_ireplace( 'Class ', '', $c_name ) );
            if ( ! $is_admin && isset( $teacher_id ) && ! empty( $teacher_id ) ) {
                $subs = $wpdb->get_results( $wpdb->prepare( 
                    "SELECT DISTINCT s.subject_name, s.subject_code, s.total_marks, s.pass_marks, s.cq_marks, s.cq_pass, s.mcq_marks, s.mcq_pass, s.practical_marks, s.practical_pass 
                     FROM {$table_teacher_subjects} ts
                     INNER JOIN {$table_subjects} s ON ts.subject_id = s.id
                     INNER JOIN {$table_units} u ON ts.class_id = u.id
                     WHERE ts.teacher_id = %d AND (u.id = %d OR u.class_name = %s OR u.class_name = %s)", 
                    $teacher_id, $unit->id, $c_name, $clean_c
                ) );
            } else {
                $subs = $wpdb->get_results( $wpdb->prepare( 
                    "SELECT subject_name, subject_code, total_marks, pass_marks, cq_marks, cq_pass, mcq_marks, mcq_pass, practical_marks, practical_pass 
                     FROM {$table_subjects} 
                     WHERE class_id = %d OR class_id = %s OR class_name = %s OR class_name = %s", 
                    $unit->id, $c_name, $c_name, $clean_c
                ) );
            }

            if ( ! empty( $subs ) ) {
                foreach ( $subs as $sub ) {
                    $class_subject_map[ $c_name ][] = array(
                        'name'            => $sub->subject_name,
                        'code'            => $sub->subject_code ? ' (' . $sub->subject_code . ')' : '',
                        'total_marks'     => $sub->total_marks,
                        'pass_marks'      => $sub->pass_marks,
                        'cq_marks'        => $sub->cq_marks,
                        'cq_pass'         => $sub->cq_pass,
                        'mcq_marks'       => $sub->mcq_marks,
                        'mcq_pass'        => $sub->mcq_pass,
                        'practical_marks' => $sub->practical_marks,
                        'practical_pass'  => $sub->practical_pass,
                    );
                }
            }
        }

        foreach ( $class_section_map as $c_name => $secs ) {
            $class_section_map[ $c_name ] = array_values( array_unique( array_filter( $secs ) ) );
            usort( $class_section_map[ $c_name ], 'strnatcasecmp' );
        }

        foreach ( $class_subject_map as $c_name => $subs ) {
            $unique_subs = array();
            foreach ( $subs as $s ) {
                $unique_subs[ $s['name'] ] = $s;
            }
            $class_subject_map[ $c_name ] = array_values( $unique_subs );
        }

        // Global fallback if specific mapping is empty
        if ( $is_admin || empty( $teacher_assigned_classes ) ) {
            $all_global_subs = $wpdb->get_results( "SELECT subject_name, subject_code, total_marks, pass_marks, cq_marks, cq_pass, mcq_marks, mcq_pass, practical_marks, practical_pass FROM {$table_subjects} ORDER BY subject_name ASC" );
            foreach ( $academic_classes as $c_name ) {
                if ( empty( $class_subject_map[ $c_name ] ) && ! empty( $all_global_subs ) ) {
                    foreach ( $all_global_subs as $gs ) {
                        $class_subject_map[ $c_name ][] = array(
                            'name'            => $gs->subject_name,
                            'code'            => $gs->subject_code ? ' (' . $gs->subject_code . ')' : '',
                            'total_marks'     => $gs->total_marks,
                            'pass_marks'      => $gs->pass_marks,
                            'cq_marks'        => $gs->cq_marks,
                            'cq_pass'         => $gs->cq_pass,
                            'mcq_marks'       => $gs->mcq_marks,
                            'mcq_pass'        => $gs->mcq_pass,
                            'practical_marks' => $gs->practical_marks,
                            'practical_pass'  => $gs->practical_pass,
                        );
                    }
                }
            }
        }

        // Map clean class name variants
        foreach ( $academic_classes as $c_name ) {
            $clean_key = trim( str_ireplace( 'Class ', '', $c_name ) );
            if ( isset( $class_section_map[ $c_name ] ) ) {
                empty( $class_section_map[ 'Class ' . $clean_key ] ) ? $class_section_map[ 'Class ' . $clean_key ] = $class_section_map[ $c_name ] : null;
                empty( $class_section_map[ $clean_key ] ) ? $class_section_map[ $clean_key ] = $class_section_map[ $c_name ] : null;
            }
            if ( isset( $class_subject_map[ $c_name ] ) ) {
                empty( $class_subject_map[ 'Class ' . $clean_key ] ) ? $class_subject_map[ 'Class ' . $clean_key ] = $class_subject_map[ $c_name ] : null;
                empty( $class_subject_map[ $clean_key ] ) ? $class_subject_map[ $clean_key ] = $class_subject_map[ $c_name ] : null;
            }
        }

        $academic_classes = array_values( array_unique( $academic_classes ) );
        usort( $academic_classes, 'strnatcasecmp' );
    }

    // Pre-populate Available Sections & Class-wise Subjects on Server Load
    $available_sections = array();
    $available_subjects = array();
    if ( ! empty( $filter_class ) ) {
        if ( isset( $class_section_map[ $filter_class ] ) ) {
            $available_sections = $class_section_map[ $filter_class ];
        }
        if ( isset( $class_subject_map[ $filter_class ] ) ) {
            $available_subjects = $class_subject_map[ $filter_class ];
        }
    }

    // Fetch Active Students Roster & Saved Exam Attendance
    $students_list = array();
    $saved_logs    = array();

    if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) {
        $clean_filter_class = trim( str_ireplace( 'Class ', '', $filter_class ) );
        $st_sql = "SELECT id, full_name, student_id, roll_no, class_name, section_name, photo_url FROM {$table_students} WHERE status = 'Active' AND (class_name = %s OR class_name = %s)";
        $st_params = array( $filter_class, $clean_filter_class );

        if ( ! empty( $filter_section ) ) {
            $st_sql .= " AND section_name = %s";
            $st_params[] = $filter_section;
        }

        $st_sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $students_list = $wpdb->get_results( $wpdb->prepare( $st_sql, ...$st_params ) );

        $existing_logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT student_id, status, invigilator_remarks 
             FROM {$table_exam_att} 
             WHERE exam_id = %d AND (class_name = %s OR class_name = %s) AND subject_name = %s AND attendance_date = %s",
            $filter_exam, $filter_class, $clean_filter_class, $filter_subject, $filter_date
        ), OBJECT_K );

        if ( ! empty( $existing_logs ) ) {
            $saved_logs = $existing_logs;
        }
    }

    $admin_page_url = admin_url( 'admin.php' );
    ?>

    <style>
        .dpt-exam-att-root {
            display: flex;
            flex-direction: column;
            gap: 20px;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .dpt-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px 28px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .dpt-card-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dpt-card-title {
            font-size: 18px;
            font-weight: 800;
            color: #006a4e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dpt-filter-grid-5 {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1.5fr 2fr 1.5fr 1.2fr;
            gap: 14px;
            align-items: flex-end;
        }

        @media (max-width: 1200px) {
            .dpt-filter-grid-5 { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .dpt-filter-grid-5 { grid-template-columns: 1fr; }
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

        .dpt-select-field, .dpt-input-field {
            height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            width: 100%;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .dpt-select-field:focus, .dpt-input-field:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
        }

        .dpt-btn-submit-trigger {
            height: 42px;
            background: #006a4e;
            color: #ffffff;
            font-weight: 800;
            font-size: 13.5px;
            border-radius: 8px;
            padding: 0 20px;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
            transition: background 0.2s ease;
            width: 100%;
        }

        .dpt-btn-submit-trigger:hover { background: #00523c; }

        .afdp-success-banner {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .afdp-roster-meta-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            padding: 16px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 12px 12px 0 0;
        }

        .dpt-counter-cluster {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .dpt-badge-pill {
            font-size: 11.5px;
            font-weight: 800;
            padding: 5px 12px;
            border-radius: 20px;
            border: 1px solid transparent;
        }

        .dpt-badge-total   { background: #f1f5f9; border-color: #cbd5e1; color: #334155; }
        .dpt-badge-present { background: #ecfdf5; border-color: #a7f3d0; color: #059669; }
        .dpt-badge-absent  { background: #fef2f2; border-color: #fecaca; color: #dc2626; }
        .dpt-badge-late    { background: #fff7ed; border-color: #fed7aa; color: #c2410c; }

        .afdp-bulk-automation-row {
            background: #ffffff;
            padding: 12px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 16px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .dpt-bulk-btn {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .dpt-bulk-btn:hover { background: #f8fafc; border-color: #006a4e; color: #006a4e; }

        .dpt-table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .dpt-attendance-matrix-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .dpt-attendance-matrix-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 800;
            font-size: 11.5px;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .dpt-attendance-matrix-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: #334155;
            background: #ffffff;
            vertical-align: middle;
        }

        .dpt-avatar-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dpt-avatar-mini {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 1.5px solid #006a4e;
            background: #e2e8f0;
            flex-shrink: 0;
        }

        .dpt-avatar-fallback-mini {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ecfdf5;
            color: #006a4e;
            font-weight: 800;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #a7f3d0;
            flex-shrink: 0;
        }

        .dpt-exam-card-badge {
            background: #f1f5f9;
            color: #0f172a;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 700;
            border: 1px solid #cbd5e1;
            font-family: monospace;
            font-size: 11.5px;
        }

        .afdp-checkbox-group {
            display: inline-flex;
            background: #f1f5f9;
            padding: 3px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            gap: 3px;
        }

        .afdp-checkbox-item {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
            pointer-events: none;
        }

        .afdp-checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #64748b;
            line-height: 1;
        }

        .afdp-checkbox-item[value="Present"]:checked + .afdp-checkbox-label { background: #059669; color: #ffffff; box-shadow: 0 2px 6px rgba(5, 150, 105, 0.3); }
        .afdp-checkbox-item[value="Absent"]:checked + .afdp-checkbox-label { background: #dc2626; color: #ffffff; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3); }
        .afdp-checkbox-item[value="Late"]:checked + .afdp-checkbox-label { background: #d97706; color: #ffffff; box-shadow: 0 2px 6px rgba(217, 119, 6, 0.3); }

        .dpt-remarks-input {
            width: 100%;
            height: 36px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 0 10px;
            font-size: 13px;
            background: #f8fafc;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .dpt-remarks-input:focus {
            border-color: #006a4e;
            background: #ffffff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 106, 78, 0.12);
        }

        @media print {
            .no-print { display: none !important; }
            .dpt-bento-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
        }
    </style>

    <div class="dpt-exam-att-root">

        <?php if ( ! empty( $saved_notice ) ) : ?>
            <div class="afdp-success-banner">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html( $saved_notice ); ?>
            </div>
        <?php endif; ?>

        <!-- Exam Attendance Filter Console -->
        <div class="dpt-bento-card no-print">
            <div class="dpt-card-header">
                <h4 class="dpt-card-title">
                    <span class="dashicons dashicons-welcome-write-blog"></span>
                    <?php esc_html_e( 'Examination Hall Attendance Roster', 'ifsedu-sms' ); ?>
                </h4>
                <?php if ( ! $is_admin ) : ?>
                    <span style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700;">
                        <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                        <?php esc_html_e( 'Teacher Mode: Assigned Allocations Only', 'ifsedu-sms' ); ?>
                    </span>
                <?php endif; ?>
            </div>

            <form method="GET" action="<?php echo esc_url( $admin_page_url ); ?>">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="attendance">
                <input type="hidden" name="sub" value="exam">

                <div class="dpt-filter-grid-5">
                    <!-- 1. Select Exam -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '1. Select Exam', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" id="educore_exam_att_exam_select" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Class Selection -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '2. Class Name', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="educore_exam_att_class_select" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $academic_classes as $cls_name ) : ?>
                                <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                                    <?php echo esc_html( $cls_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 3. Section Selection -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '3. Section (Optional)', 'ifsedu-sms' ); ?></label>
                        <select name="section_name" id="educore_exam_att_section_select" class="dpt-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $available_sections as $sec_name ) : ?>
                                <option value="<?php echo esc_attr( $sec_name ); ?>" <?php selected( $filter_section, $sec_name ); ?>>
                                    <?php echo esc_html( $sec_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 4. Subject Selection -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '4. Exam Subject', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="subject_name" id="educore_exam_att_subject_select" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-sms' ); ?></option>
                            <?php if ( ! empty( $available_subjects ) ) : ?>
                                <?php foreach ( $available_subjects as $sub_item ) : ?>
                                    <option value="<?php echo esc_attr( $sub_item['name'] ); ?>" <?php selected( $filter_subject, $sub_item['name'] ); ?>>
                                        <?php echo esc_html( $sub_item['name'] . $sub_item['code'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- 5. Exam Date -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '5. Exam Date', 'ifsedu-sms' ); ?></label>
                        <input type="date" name="attendance_date" class="dpt-input-field" value="<?php echo esc_attr( $filter_date ); ?>">
                    </div>

                    <!-- Submit Trigger -->
                    <div>
                        <button type="submit" class="dpt-btn-submit-trigger" style="width: 100%;">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e( 'Load Roster', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Local Instant Cascade Script for Class-wise Subjects -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var classSectionMap = <?php echo wp_json_encode( $class_section_map ); ?>;
            var classSubjectMap = <?php echo wp_json_encode( $class_subject_map ); ?>;
            var currentSelectedSection = "<?php echo esc_js( $filter_section ); ?>";
            var currentSelectedSubject = "<?php echo esc_js( $filter_subject ); ?>";

            function updateDropdowns(className) {
                var $secSelect     = $('#educore_exam_att_section_select');
                var $subjectSelect = $('#educore_exam_att_subject_select');

                $secSelect.empty().append('<option value=""><?php echo esc_js( __( "-- All Sections --", "ifsedu-sms" ) ); ?></option>');
                $subjectSelect.empty().append('<option value=""><?php echo esc_js( __( "-- Choose Subject --", "ifsedu-sms" ) ); ?></option>');

                if (!className) return;

                // Populate Sections
                if (classSectionMap[className] && classSectionMap[className].length > 0) {
                    $.each(classSectionMap[className], function(i, sec) {
                        var isSelected = (sec === currentSelectedSection) ? 'selected' : '';
                        $secSelect.append('<option value="' + sec + '" ' + isSelected + '>' + sec + '</option>');
                    });
                }

                // Populate Class-Related Subjects
                if (classSubjectMap[className] && classSubjectMap[className].length > 0) {
                    $.each(classSubjectMap[className], function(i, sub) {
                        var isSelected = (sub.name === currentSelectedSubject) ? 'selected' : '';
                        $subjectSelect.append('<option value="' + sub.name + '" ' + isSelected + '>' + sub.name + sub.code + '</option>');
                    });
                } else {
                    $subjectSelect.html('<option value=""><?php echo esc_js( __( "No Subjects Configured for this Class", "ifsedu-sms" ) ); ?></option>');
                }
            }

            // On Class change
            $('#educore_exam_att_class_select').on('change', function() {
                var selectedClass = $(this).val();
                currentSelectedSection = '';
                currentSelectedSubject = '';
                updateDropdowns(selectedClass);
            });
        });
        </script>

        <!-- Exam Hall Attendance Roster Form -->
        <?php if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) : ?>
            <div class="dpt-bento-card" style="padding: 0; overflow: hidden;">
                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_exam_attendance_action', 'educore_exam_att_nonce_field' ); ?>
                    <input type="hidden" name="exam_id" value="<?php echo esc_attr( $filter_exam ); ?>">
                    <input type="hidden" name="class_name" value="<?php echo esc_attr( $filter_class ); ?>">
                    <input type="hidden" name="section_name" value="<?php echo esc_attr( $filter_section ); ?>">
                    <input type="hidden" name="subject_name" value="<?php echo esc_attr( $filter_subject ); ?>">
                    <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                    <!-- Meta Summary Bar -->
                    <div class="afdp-roster-meta-bar">
                        <div>
                            <strong style="font-size:16px; color:#006a4e;"><?php echo esc_html( $filter_subject ); ?></strong>
                            <span style="font-size:13px; color:#475569; margin-left:8px;">
                                &mdash; Class <?php echo esc_html( $filter_class ); ?> 
                                <?php echo ! empty( $filter_section ) ? '(' . esc_html( $filter_section ) . ')' : ''; ?> 
                                | Date: <?php echo esc_html( date_i18n( 'd M, Y', strtotime( $filter_date ) ) ); ?>
                            </span>
                        </div>
                        <div class="dpt-counter-cluster">
                            <span class="dpt-badge-pill dpt-badge-total" id="examAttTotalCount">Total: <?php echo count( $students_list ); ?></span>
                            <span class="dpt-badge-pill dpt-badge-present" id="examAttPresentCount">Present: 0</span>
                            <span class="dpt-badge-pill dpt-badge-absent" id="examAttAbsentCount">Absent: 0</span>
                            <span class="dpt-badge-pill dpt-badge-late" id="examAttLateCount">Late/Expelled: 0</span>
                        </div>
                    </div>

                    <!-- Bulk Automation Buttons -->
                    <div class="afdp-bulk-automation-row no-print">
                        <div style="font-size: 13px; font-weight: 700; color: #475569;">
                            <span class="dashicons dashicons-admin-tools" style="vertical-align:middle;"></span>
                            <?php esc_html_e( 'Quick Automation Tools:', 'ifsedu-sms' ); ?>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button type="button" class="dpt-bulk-btn exam-bulk-btn" data-target-status="Present">
                                <span class="dashicons dashicons-yes" style="font-size:14px; width:14px; height:14px;"></span> <?php esc_html_e( 'Mark All Present', 'ifsedu-sms' ); ?>
                            </button>
                            <button type="button" class="dpt-bulk-btn exam-bulk-btn" data-target-status="Absent">
                                <span class="dashicons dashicons-no" style="font-size:14px; width:14px; height:14px;"></span> <?php esc_html_e( 'Mark All Absent', 'ifsedu-sms' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Roster Table -->
                    <div class="dpt-table-responsive">
                        <table class="dpt-attendance-matrix-table">
                            <thead>
                                <tr>
                                    <th style="width: 8%;"><?php esc_html_e( 'Roll', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 14%;"><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 25%;"><?php esc_html_e( 'Candidate Name', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 30%; text-align: center;"><?php esc_html_e( 'Exam Hall Status', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 23%;"><?php esc_html_e( 'Invigilator Notes / Expel Remarks', 'ifsedu-sms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $students_list ) ) : foreach ( $students_list as $s ) : 
                                    $saved_status  = isset( $saved_logs[ $s->id ] ) ? $saved_logs[ $s->id ]->status : 'Present';
                                    $saved_remarks = isset( $saved_logs[ $s->id ] ) ? $saved_logs[ $s->id ]->invigilator_remarks : '';
                                    $first_letter  = mb_substr( $s->full_name, 0, 1 );
                                ?>
                                    <tr>
                                        <td><strong>#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                        <td><span class="dpt-exam-card-badge"><?php echo esc_html( strtoupper( $s->student_id ) ); ?></span></td>
                                        <td>
                                            <div class="dpt-avatar-cell">
                                                <?php if ( ! empty( $s->photo_url ) ) : ?>
                                                    <img src="<?php echo esc_url( $s->photo_url ); ?>" class="dpt-avatar-mini" alt="Avatar">
                                                <?php else : ?>
                                                    <div class="dpt-avatar-fallback-mini"><?php echo esc_html( strtoupper( $first_letter ) ); ?></div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong style="color:#0f172a;"><?php echo esc_html( $s->full_name ); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <div class="afdp-checkbox-group">
                                                <input type="radio" class="afdp-checkbox-item exam-att-radio" name="att_status[<?php echo esc_attr( $s->id ); ?>]" id="att_present_<?php echo esc_attr( $s->id ); ?>" value="Present" <?php checked( $saved_status, 'Present' ); ?>>
                                                <label class="afdp-checkbox-label" for="att_present_<?php echo esc_attr( $s->id ); ?>">
                                                    <span class="dashicons dashicons-yes" style="font-size:13px; width:13px; height:13px;"></span>
                                                    <?php esc_html_e( 'Present', 'ifsedu-sms' ); ?>
                                                </label>

                                                <input type="radio" class="afdp-checkbox-item exam-att-radio" name="att_status[<?php echo esc_attr( $s->id ); ?>]" id="att_absent_<?php echo esc_attr( $s->id ); ?>" value="Absent" <?php checked( $saved_status, 'Absent' ); ?>>
                                                <label class="afdp-checkbox-label" for="att_absent_<?php echo esc_attr( $s->id ); ?>">
                                                    <span class="dashicons dashicons-no" style="font-size:13px; width:13px; height:13px;"></span>
                                                    <?php esc_html_e( 'Absent', 'ifsedu-sms' ); ?>
                                                </label>

                                                <input type="radio" class="afdp-checkbox-item exam-att-radio" name="att_status[<?php echo esc_attr( $s->id ); ?>]" id="att_late_<?php echo esc_attr( $s->id ); ?>" value="Late" <?php checked( $saved_status, 'Late' ); ?>>
                                                <label class="afdp-checkbox-label" for="att_late_<?php echo esc_attr( $s->id ); ?>">
                                                    <span class="dashicons dashicons-warning" style="font-size:13px; width:13px; height:13px;"></span>
                                                    <?php esc_html_e( 'Late / Expelled', 'ifsedu-sms' ); ?>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" name="invigilator_remarks[<?php echo esc_attr( $s->id ); ?>]" class="dpt-remarks-input" placeholder="e.g. Expelled, 15m Late, Seat No. 4" value="<?php echo esc_attr( $saved_remarks ); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">
                                            <?php esc_html_e( 'No active students found in the selected class/section.', 'ifsedu-sms' ); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ( ! empty( $students_list ) ) : ?>
                        <div style="text-align: right; margin: 20px; padding: 0;">
                            <button type="submit" name="educore_save_exam_attendance" class="dpt-btn-submit-trigger" style="height: 44px; padding: 0 32px; font-size: 15px; width: auto;">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e( 'Save Exam Hall Attendance', 'ifsedu-sms' ); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Client-Side Summary Counters & Quick Automation Script -->
            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                function updateCounters() {
                    const present = document.querySelectorAll('.exam-att-radio[value="Present"]:checked').length;
                    const absent  = document.querySelectorAll('.exam-att-radio[value="Absent"]:checked').length;
                    const late    = document.querySelectorAll('.exam-att-radio[value="Late"]:checked').length;

                    document.getElementById('examAttPresentCount').textContent = 'Present: ' + present;
                    document.getElementById('examAttAbsentCount').textContent  = 'Absent: ' + absent;
                    document.getElementById('examAttLateCount').textContent    = 'Late/Expelled: ' + late;
                }

                document.querySelectorAll('.exam-att-radio').forEach(radio => {
                    radio.addEventListener('change', updateCounters);
                });

                document.querySelectorAll('.exam-bulk-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const targetStatus = this.getAttribute('data-target-status');
                        document.querySelectorAll('.exam-att-radio[value="' + targetStatus + '"]').forEach(radio => {
                            radio.checked = true;
                        });
                        updateCounters();
                    });
                });

                updateCounters();
            });
            </script>
        <?php endif; ?>

    </div>
    <?php
}