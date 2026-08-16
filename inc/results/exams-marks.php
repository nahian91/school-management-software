<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * High-End Marks Entry Matrix & Grading Evaluation Engine
 * File: inc/results/exams-marks.php
 * Custom Prefixes Applied: dpt-, afdp-
 * Architecture: Neo-Bento Interface with Real-time Auto Grading & BD NCTB Pass-Fail Checks
 */

// --------------------------------------------------------------------------
// 0. AUTO-SCHEMA CHECK (Ensures sms_results table exists with component breakdown)
// --------------------------------------------------------------------------
global $wpdb;
$table_results = $wpdb->prefix . 'sms_results';
$check_table   = $wpdb->get_var( "SHOW TABLES LIKE '{$table_results}'" );

if ( empty( $check_table ) ) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();
    $sql_results = "CREATE TABLE {$table_results} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        exam_id bigint(20) NOT NULL,
        student_id bigint(20) NOT NULL,
        class_name varchar(50) NOT NULL,
        section_name varchar(50) DEFAULT '' NOT NULL,
        subject_name varchar(150) NOT NULL,
        total_marks decimal(5,2) DEFAULT '100.00' NOT NULL,
        obtained_marks decimal(5,2) DEFAULT '0.00' NOT NULL,
        cq_marks decimal(5,2) DEFAULT '0.00' NOT NULL,
        mcq_marks decimal(5,2) DEFAULT '0.00' NOT NULL,
        practical_marks decimal(5,2) DEFAULT '0.00' NOT NULL,
        grade varchar(10) DEFAULT 'F' NOT NULL,
        gpa decimal(4,2) DEFAULT '0.00' NOT NULL,
        remarks varchar(255) DEFAULT '' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY exam_student_subject (exam_id, student_id, subject_name),
        KEY exam_student_idx (exam_id, student_id),
        KEY class_section_idx (class_name, section_name)
    ) $charset_collate;";
    dbDelta( $sql_results );
}

// --------------------------------------------------------------------------
// 1. AJAX HANDLERS
// --------------------------------------------------------------------------

// Dynamic Section Loader
add_action( 'wp_ajax_educore_get_sections_by_class_marks', 'educore_get_sections_by_class_marks_handler' );
function educore_get_sections_by_class_marks_handler() {
    check_ajax_referer( 'educore_marks_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) ) {
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

// Dynamic Subject Loader with Pass Mark Criteria
add_action( 'wp_ajax_educore_get_subjects_by_class_marks', 'educore_get_subjects_by_class_marks_handler' );
function educore_get_subjects_by_class_marks_handler() {
    check_ajax_referer( 'educore_marks_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ifsedu-sms' ) ) );
    }

    global $wpdb;
    $table_subjects = $wpdb->prefix . 'sms_subjects';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $subjects = $wpdb->get_results( $wpdb->prepare(
        "SELECT s.id, s.subject_name, s.subject_code, s.total_marks, s.pass_marks, s.cq_marks, s.cq_pass, s.mcq_marks, s.mcq_pass, s.practical_marks, s.practical_pass 
         FROM {$table_subjects} s 
         INNER JOIN {$table_units} u ON s.class_id = u.id 
         WHERE u.class_name = %s 
         ORDER BY s.subject_name ASC",
        $class_name
    ) );

    wp_send_json_success( $subjects );
}

// --------------------------------------------------------------------------
// 2. STANDARD BD NCTB GRADING FUNCTION
// --------------------------------------------------------------------------
if ( ! function_exists( 'educore_calculate_grade' ) ) {
    function educore_calculate_grade( $obtained, $total = 100 ) {
        $total = floatval( $total ) > 0 ? floatval( $total ) : 100;
        $pct   = ( floatval( $obtained ) / $total ) * 100;

        if ( $pct >= 80 ) {
            return array( 'A+', 5.00 );
        } elseif ( $pct >= 70 ) {
            return array( 'A', 4.00 );
        } elseif ( $pct >= 60 ) {
            return array( 'A-', 3.50 );
        } elseif ( $pct >= 50 ) {
            return array( 'B', 3.00 );
        } elseif ( $pct >= 40 ) {
            return array( 'C', 2.00 );
        } elseif ( $pct >= 33 ) {
            return array( 'D', 1.00 );
        } else {
            return array( 'F', 0.00 );
        }
    }
}

// --------------------------------------------------------------------------
// 3. MAIN MARKS ENTRY MATRIX VIEW
// --------------------------------------------------------------------------
function educore_exams_marks_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_subjects = $wpdb->prefix . 'sms_subjects';

    // Strict Security Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to enter examination marks.', 'ifsedu-sms' ) );
    }

    $current_uri = remove_query_arg( array( 'status', 'msg' ), $_SERVER['REQUEST_URI'] );
    $base_url    = esc_url_raw( $current_uri );
    $notice_msg  = '';

    // --------------------------------------------------------------------------
    // FORM SUBMISSION (SAVE/UPDATE BULK MARKS MATRIX)
    // --------------------------------------------------------------------------
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['educore_save_marks_matrix'] ) ) {
        if ( isset( $_POST['educore_marks_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_marks_nonce'] ) ), 'save_marks_action' ) ) {
            $exam_id      = isset( $_POST['exam_id'] ) ? absint( $_POST['exam_id'] ) : 0;
            $class_name   = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
            $section_name = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';
            $subject_name = isset( $_POST['subject_name'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_name'] ) ) : '';
            $total_marks  = isset( $_POST['total_marks_limit'] ) ? floatval( $_POST['total_marks_limit'] ) : 100.00;
            $pass_marks   = isset( $_POST['pass_marks_limit'] ) ? floatval( $_POST['pass_marks_limit'] ) : 33.00;
            $cq_pass      = isset( $_POST['cq_pass_limit'] ) ? floatval( $_POST['cq_pass_limit'] ) : 0.00;
            $mcq_pass     = isset( $_POST['mcq_pass_limit'] ) ? floatval( $_POST['mcq_pass_limit'] ) : 0.00;
            $pr_pass      = isset( $_POST['pr_pass_limit'] ) ? floatval( $_POST['pr_pass_limit'] ) : 0.00;

            $students_cq  = isset( $_POST['cq_marks'] ) && is_array( $_POST['cq_marks'] ) ? $_POST['cq_marks'] : array();
            $students_mcq = isset( $_POST['mcq_marks'] ) && is_array( $_POST['mcq_marks'] ) ? $_POST['mcq_marks'] : array();
            $students_pr  = isset( $_POST['practical_marks'] ) && is_array( $_POST['practical_marks'] ) ? $_POST['practical_marks'] : array();

            $saved_count = 0;
            if ( $exam_id > 0 && ! empty( $class_name ) && ! empty( $subject_name ) ) {
                foreach ( $students_cq as $s_id => $val_cq ) {
                    $s_id_int = absint( $s_id );
                    $cq_val   = floatval( $val_cq );
                    $mcq_val  = isset( $students_mcq[ $s_id ] ) ? floatval( $students_mcq[ $s_id ] ) : 0.00;
                    $pr_val   = isset( $students_pr[ $s_id ] ) ? floatval( $students_pr[ $s_id ] ) : 0.00;

                    $obtained = $cq_val + $mcq_val + $pr_val;

                    // Evaluate Pass/Fail status based on total and individual component pass marks
                    $has_failed = false;
                    if ( $cq_pass > 0 && $cq_val < $cq_pass ) {
                        $has_failed = true;
                    }
                    if ( $mcq_pass > 0 && $mcq_val < $mcq_pass ) {
                        $has_failed = true;
                    }
                    if ( $pr_pass > 0 && $pr_val < $pr_pass ) {
                        $has_failed = true;
                    }
                    if ( $obtained < $pass_marks ) {
                        $has_failed = true;
                    }

                    if ( $has_failed ) {
                        $grade = 'F';
                        $gpa   = 0.00;
                    } else {
                        $grade_eval = educore_calculate_grade( $obtained, $total_marks );
                        $grade      = $grade_eval[0];
                        $gpa        = $grade_eval[1];
                    }

                    // Check if a mark entry already exists
                    $existing_id = $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$table_results} WHERE exam_id = %d AND student_id = %d AND subject_name = %s",
                        $exam_id, $s_id_int, $subject_name
                    ) );

                    $data = array(
                        'exam_id'         => $exam_id,
                        'student_id'      => $s_id_int,
                        'class_name'      => $class_name,
                        'section_name'    => $section_name,
                        'subject_name'    => $subject_name,
                        'total_marks'     => $total_marks,
                        'obtained_marks'  => $obtained,
                        'cq_marks'        => $cq_val,
                        'mcq_marks'       => $mcq_val,
                        'practical_marks' => $pr_val,
                        'grade'           => $grade,
                        'gpa'             => $gpa,
                    );

                    if ( $existing_id ) {
                        $wpdb->update( $table_results, $data, array( 'id' => $existing_id ) );
                    } else {
                        $wpdb->insert( $table_results, $data );
                    }
                    $saved_count++;
                }

                $notice_msg = sprintf( esc_html__( 'Successfully evaluated and saved marks for %d students.', 'ifsedu-sms' ), $saved_count );
            }
        }
    }

    // Capture GET Filter Parameters
    $filter_exam    = isset( $_GET['exam_id'] ) ? absint( $_GET['exam_id'] ) : 0;
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $filter_subject = isset( $_GET['subject_name'] ) ? sanitize_text_field( wp_unslash( $_GET['subject_name'] ) ) : '';

    // Fetch Examinations
    $exams = $wpdb->get_results( "SELECT id, exam_name, class_name FROM {$table_exams} ORDER BY id DESC" );

    // Fetch All Unique Classes with Natural Numeric Sorting
    $raw_classes = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    $academic_classes = array();
    if ( ! empty( $raw_classes ) ) {
        $academic_classes = array_values( array_unique( $raw_classes ) );
        usort( $academic_classes, 'strnatcasecmp' );
    }

    // Pre-populate Available Sections for Selected Class
    $available_sections = array();
    if ( ! empty( $filter_class ) ) {
        $available_sections = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT section_name FROM {$table_units} WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
            $filter_class
        ) );
    }

    // Fetch Mapped Subjects with Component Pass Mark Limits for Selected Class
    $available_subjects = array();
    $active_subject_obj = null;

    if ( ! empty( $filter_class ) ) {
        $available_subjects = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.* FROM {$table_subjects} s 
             INNER JOIN {$table_units} u ON s.class_id = u.id 
             WHERE u.class_name = %s 
             ORDER BY s.subject_name ASC",
            $filter_class
        ) );

        if ( ! empty( $filter_subject ) && ! empty( $available_subjects ) ) {
            foreach ( $available_subjects as $sub_item ) {
                if ( $sub_item->subject_name === $filter_subject ) {
                    $active_subject_obj = $sub_item;
                    break;
                }
            }
        }
    }

    // Fetch Active Students Dataset & Pre-existing Marks
    $students_list = array();
    $saved_marks   = array();

    if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) {
        $st_sql = "SELECT id, full_name, student_id, roll_no, class_name, section_name FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $st_params = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $st_sql .= " AND section_name = %s";
            $st_params[] = $filter_section;
        }

        $st_sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $students_list = $wpdb->get_results( $wpdb->prepare( $st_sql, ...$st_params ) );

        // Fetch Saved Marks Map
        $existing_results = $wpdb->get_results( $wpdb->prepare(
            "SELECT student_id, cq_marks, mcq_marks, practical_marks, obtained_marks, grade, gpa 
             FROM {$table_results} 
             WHERE exam_id = %d AND class_name = %s AND subject_name = %s",
            $filter_exam, $filter_class, $filter_subject
        ), OBJECT_K );

        if ( ! empty( $existing_results ) ) {
            $saved_marks = $existing_results;
        }
    }
    ?>

    <style>
        .dpt-marks-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            gap: 20px;
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

        .dpt-filter-grid {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1.5fr 2fr 1.2fr;
            gap: 14px;
            align-items: flex-end;
        }

        @media (max-width: 1024px) {
            .dpt-filter-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .dpt-filter-grid { grid-template-columns: 1fr; }
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

        .dpt-input, .dpt-select {
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

        .dpt-input:focus, .dpt-select:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
        }

        .dpt-btn-primary {
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

        .dpt-btn-primary:hover { background: #00523c; }

        .dpt-btn-submit {
            height: 46px;
            background: #006a4e;
            color: #ffffff;
            font-weight: 800;
            font-size: 15px;
            border-radius: 10px;
            padding: 0 32px;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(0, 106, 78, 0.25);
            transition: all 0.2s ease;
        }

        .dpt-btn-submit:hover { background: #00523c; transform: translateY(-1px); }

        /* Component Marks Matrix Table */
        .dpt-matrix-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .dpt-matrix-table th, .dpt-matrix-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: center;
            vertical-align: middle;
        }

        .dpt-matrix-table th {
            background-color: #f8fafc;
            color: #334155;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.3px;
        }

        .dpt-mark-cell-input {
            width: 100%;
            max-width: 85px;
            height: 36px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            text-align: center;
            font-weight: 800;
            font-size: 13.5px;
            color: #0f172a;
            background: #ffffff;
            margin: 0 auto;
            display: block;
            box-sizing: border-box;
        }

        .dpt-mark-cell-input:focus {
            border-color: #006a4e;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 106, 78, 0.2);
        }

        .dpt-criteria-pill {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            margin-top: 2px;
            border: 1px solid #cbd5e1;
        }

        .dpt-badge-grade {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 800;
            font-size: 12px;
        }

        .grade-pass { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .grade-fail { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    </style>

    <div class="dpt-marks-root">

        <?php if ( ! empty( $notice_msg ) ) : ?>
            <div class="notice notice-success is-dismissible" style="padding:12px; margin:0; font-weight:700; border-left:4px solid #006a4e; background:#ecfdf5; color:#065f46; border-radius:8px;">
                <span class="dashicons dashicons-yes-alt" style="vertical-align:middle; margin-right:4px;"></span>
                <?php echo esc_html( $notice_msg ); ?>
            </div>
        <?php endif; ?>

        <!-- Search & Selection Bento Filter Card -->
        <div class="dpt-bento-card">
            <div class="dpt-card-header">
                <h4 class="dpt-card-title">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e( 'Academic Evaluation & Marks Entry Matrix', 'ifsedu-sms' ); ?>
                </h4>
            </div>

            <form method="GET" action="<?php echo esc_url( $base_url ); ?>" id="educoreMarksFilterForm">
                <?php 
                $parsed_url = wp_parse_url( $base_url );
                if ( isset( $parsed_url['query'] ) ) {
                    parse_str( $parsed_url['query'], $query_params );
                    foreach ( $query_params as $param_key => $param_val ) {
                        if ( ! in_array( $param_key, array( 'exam_id', 'class_name', 'section_name', 'subject_name' ), true ) ) {
                            echo '<input type="hidden" name="' . esc_attr( $param_key ) . '" value="' . esc_attr( $param_val ) . '">';
                        }
                    }
                }
                ?>
                <input type="hidden" name="sub" value="marks">

                <div class="dpt-filter-grid">
                    <!-- 1. Select Exam -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '1. Select Exam', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" id="educore_marks_exam_select" class="dpt-select" required>
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
                        <select name="class_name" id="educore_marks_class_select" class="dpt-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $academic_classes as $cls_name ) : ?>
                                <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                                    <?php printf( esc_html__( 'Class %s', 'ifsedu-sms' ), esc_html( $cls_name ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 3. Section Selection -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '3. Section (Optional)', 'ifsedu-sms' ); ?></label>
                        <select name="section_name" id="educore_marks_section_select" class="dpt-select">
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
                        <label class="dpt-form-label"><?php esc_html_e( '4. Target Subject', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="subject_name" id="educore_marks_subject_select" class="dpt-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $available_subjects as $sub_item ) : ?>
                                <option value="<?php echo esc_attr( $sub_item->subject_name ); ?>" <?php selected( $filter_subject, $sub_item->subject_name ); ?>>
                                    <?php echo esc_html( $sub_item->subject_name . ( $sub_item->subject_code ? ' (' . $sub_item->subject_code . ')' : '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 5. Submit Filter -->
                    <div>
                        <button type="submit" class="dpt-btn-primary">
                            <span class="dashicons dashicons-filter"></span>
                            <?php esc_html_e( 'Load Matrix', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Dynamic Cascading Scripts for Class -> Sections & Subjects -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( wp_create_nonce( "educore_marks_nonce" ) ); ?>';

            $('#educore_marks_class_select').on('change', function() {
                var selectedClass  = $(this).val();
                var $secSelect     = $('#educore_marks_section_select');
                var $subjectSelect = $('#educore_marks_subject_select');

                $secSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Sections... --', 'ifsedu-sms' ) ); ?></option>');
                $subjectSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Subjects... --', 'ifsedu-sms' ) ); ?></option>');

                if (!selectedClass) {
                    $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>');
                    $subjectSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-sms' ) ); ?></option>');
                    return;
                }

                // Load Sections
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'educore_get_sections_by_class_marks',
                        security: nonce,
                        class_name: selectedClass
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>';
                            $.each(response.data, function(i, sec) {
                                options += '<option value="' + sec + '">' + sec + '</option>';
                            });
                            $secSelect.html(options);
                        } else {
                            $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>');
                        }
                    }
                });

                // Load Subjects
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'educore_get_subjects_by_class_marks',
                        security: nonce,
                        class_name: selectedClass
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var subOptions = '<option value=""><?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-sms' ) ); ?></option>';
                            $.each(response.data, function(i, sub) {
                                var codeStr = sub.subject_code ? ' (' + sub.subject_code + ')' : '';
                                subOptions += '<option value="' + sub.subject_name + '">' + sub.subject_name + codeStr + '</option>';
                            });
                            $subjectSelect.html(subOptions);
                        } else {
                            $subjectSelect.html('<option value=""><?php echo esc_js( __( 'No Mapped Subjects Found', 'ifsedu-sms' ) ); ?></option>');
                        }
                    }
                });
            });
        });
        </script>

        <!-- Marks Entry Matrix Table -->
        <?php if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) : 
            $tot_limit = $active_subject_obj ? floatval( $active_subject_obj->total_marks ) : 100.00;
            $pass_lim  = $active_subject_obj ? floatval( $active_subject_obj->pass_marks ) : 33.00;
            $cq_lim    = $active_subject_obj ? floatval( $active_subject_obj->cq_marks ) : 70.00;
            $cq_p_lim  = $active_subject_obj ? floatval( $active_subject_obj->cq_pass ) : 23.00;
            $mcq_lim   = $active_subject_obj ? floatval( $active_subject_obj->mcq_marks ) : 30.00;
            $mcq_p_lim = $active_subject_obj ? floatval( $active_subject_obj->mcq_pass ) : 10.00;
            $pr_lim    = $active_subject_obj ? floatval( $active_subject_obj->practical_marks ) : 0.00;
            $pr_p_lim  = $active_subject_obj ? floatval( $active_subject_obj->practical_pass ) : 0.00;
        ?>
            <div class="dpt-bento-card">
                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_marks_action', 'educore_marks_nonce' ); ?>
                    <input type="hidden" name="exam_id" value="<?php echo esc_attr( $filter_exam ); ?>">
                    <input type="hidden" name="class_name" value="<?php echo esc_attr( $filter_class ); ?>">
                    <input type="hidden" name="section_name" value="<?php echo esc_attr( $filter_section ); ?>">
                    <input type="hidden" name="subject_name" value="<?php echo esc_attr( $filter_subject ); ?>">

                    <input type="hidden" name="total_marks_limit" id="total_marks_limit" value="<?php echo esc_attr( $tot_limit ); ?>">
                    <input type="hidden" name="pass_marks_limit" id="pass_marks_limit" value="<?php echo esc_attr( $pass_lim ); ?>">
                    <input type="hidden" name="cq_pass_limit" id="cq_pass_limit" value="<?php echo esc_attr( $cq_p_lim ); ?>">
                    <input type="hidden" name="mcq_pass_limit" id="mcq_pass_limit" value="<?php echo esc_attr( $mcq_p_lim ); ?>">
                    <input type="hidden" name="pr_pass_limit" id="pr_pass_limit" value="<?php echo esc_attr( $pr_p_lim ); ?>">

                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid #e2e8f0;">
                        <div>
                            <strong style="font-size:16px; color:#0f172a;"><?php echo esc_html( $filter_subject ); ?></strong>
                            <span style="font-size:12px; color:#64748b; margin-left:8px;">(Total: <?php echo $tot_limit; ?> | Pass: <?php echo $pass_lim; ?>)</span>
                        </div>
                        <div>
                            <button type="submit" name="educore_save_marks_matrix" class="dpt-btn-submit">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e( 'Save All Marks', 'ifsedu-sms' ); ?>
                            </button>
                        </div>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="dpt-matrix-table" id="dptMarksEntryTable">
                            <thead>
                                <tr>
                                    <th style="width: 6%;"><?php esc_html_e( 'Roll', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 12%;"><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                                    <th style="text-align: left; width: 22%;"><?php esc_html_e( 'Student Full Name', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 14%;">
                                        <?php esc_html_e( 'CQ Theory', 'ifsedu-sms' ); ?><br>
                                        <span class="dpt-criteria-pill">Max: <?php echo $cq_lim; ?> | ≥ <?php echo $cq_p_lim; ?></span>
                                    </th>
                                    <th style="width: 14%;">
                                        <?php esc_html_e( 'MCQ', 'ifsedu-sms' ); ?><br>
                                        <span class="dpt-criteria-pill">Max: <?php echo $mcq_lim; ?> | ≥ <?php echo $mcq_p_lim; ?></span>
                                    </th>
                                    <?php if ( $pr_lim > 0 ) : ?>
                                        <th style="width: 14%;">
                                            <?php esc_html_e( 'Practical', 'ifsedu-sms' ); ?><br>
                                            <span class="dpt-criteria-pill">Max: <?php echo $pr_lim; ?> | ≥ <?php echo $pr_p_lim; ?></span>
                                        </th>
                                    <?php endif; ?>
                                    <th style="width: 10%;"><?php esc_html_e( 'Total', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 8%;"><?php esc_html_e( 'Grade', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 8%;"><?php esc_html_e( 'GPA', 'ifsedu-sms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $students_list ) ) : foreach ( $students_list as $s ) : 
                                    $curr_res = isset( $saved_marks[ $s->id ] ) ? $saved_marks[ $s->id ] : null;
                                    $curr_cq  = $curr_res ? floatval( $curr_res->cq_marks ) : '';
                                    $curr_mcq = $curr_res ? floatval( $curr_res->mcq_marks ) : '';
                                    $curr_pr  = $curr_res ? floatval( $curr_res->practical_marks ) : '';
                                    $curr_tot = $curr_res ? floatval( $curr_res->obtained_marks ) : '0.00';
                                    $curr_grd = $curr_res ? esc_html( $curr_res->grade ) : '—';
                                    $curr_gpa = $curr_res ? number_format( floatval( $curr_res->gpa ), 2 ) : '0.00';
                                    $is_fail  = ( $curr_grd === 'F' );
                                ?>
                                    <tr data-student-id="<?php echo esc_attr( $s->id ); ?>">
                                        <td><strong>#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                        <td><code><?php echo esc_html( strtoupper( $s->student_id ) ); ?></code></td>
                                        <td style="text-align: left; font-weight: 700; color: #0f172a;"><?php echo esc_html( $s->full_name ); ?></td>
                                        
                                        <!-- CQ Input -->
                                        <td>
                                            <input type="number" step="0.5" min="0" max="<?php echo esc_attr( $cq_lim ); ?>" 
                                                   name="cq_marks[<?php echo esc_attr( $s->id ); ?>]" 
                                                   class="dpt-mark-cell-input inp-cq" 
                                                   value="<?php echo esc_attr( $curr_cq ); ?>" placeholder="0">
                                        </td>

                                        <!-- MCQ Input -->
                                        <td>
                                            <input type="number" step="0.5" min="0" max="<?php echo esc_attr( $mcq_lim ); ?>" 
                                                   name="mcq_marks[<?php echo esc_attr( $s->id ); ?>]" 
                                                   class="dpt-mark-cell-input inp-mcq" 
                                                   value="<?php echo esc_attr( $curr_mcq ); ?>" placeholder="0">
                                        </td>

                                        <!-- Practical Input -->
                                        <?php if ( $pr_lim > 0 ) : ?>
                                            <td>
                                                <input type="number" step="0.5" min="0" max="<?php echo esc_attr( $pr_lim ); ?>" 
                                                       name="practical_marks[<?php echo esc_attr( $s->id ); ?>]" 
                                                       class="dpt-mark-cell-input inp-pr" 
                                                       value="<?php echo esc_attr( $curr_pr ); ?>" placeholder="0">
                                            </td>
                                        <?php else : ?>
                                            <input type="hidden" name="practical_marks[<?php echo esc_attr( $s->id ); ?>]" class="inp-pr" value="0">
                                        <?php endif; ?>

                                        <!-- Calculated Total -->
                                        <td><strong class="cell-total-obt" style="font-size: 14px; color: #0f172a;"><?php echo esc_html( $curr_tot ); ?></strong></td>

                                        <!-- Evaluated Grade -->
                                        <td>
                                            <span class="dpt-badge-grade cell-grade <?php echo $is_fail ? 'grade-fail' : ( $curr_grd !== '—' ? 'grade-pass' : '' ); ?>">
                                                <?php echo esc_html( $curr_grd ); ?>
                                            </span>
                                        </td>

                                        <!-- Evaluated GPA -->
                                        <td><strong class="cell-gpa" style="color: <?php echo $is_fail ? '#dc2626' : '#006a4e'; ?>;"><?php echo esc_html( $curr_gpa ); ?></strong></td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr>
                                        <td colspan="<?php echo ( $pr_lim > 0 ) ? 9 : 8; ?>" style="padding: 40px; color: #94a3b8;">
                                            <?php esc_html_e( 'No active students found matching the selected academic parameters.', 'ifsedu-sms' ); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ( ! empty( $students_list ) ) : ?>
                        <div style="text-align: right; margin-top: 24px;">
                            <button type="submit" name="educore_save_marks_matrix" class="dpt-btn-submit">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e( 'Save All Marks', 'ifsedu-sms' ); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Client-Side Real-time Grading & Threshold Calculation Script -->
            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                const totalLimit = parseFloat(document.getElementById('total_marks_limit').value) || 100;
                const passLimit  = parseFloat(document.getElementById('pass_marks_limit').value) || 33;
                const cqPass     = parseFloat(document.getElementById('cq_pass_limit').value) || 0;
                const mcqPass    = parseFloat(document.getElementById('mcq_pass_limit').value) || 0;
                const prPass     = parseFloat(document.getElementById('pr_pass_limit').value) || 0;

                function computeGradeAndGpa(obtained, total) {
                    const pct = (obtained / total) * 100;
                    if (pct >= 80) return { grade: 'A+', gpa: '5.00' };
                    if (pct >= 70) return { grade: 'A',  gpa: '4.00' };
                    if (pct >= 60) return { grade: 'A-', gpa: '3.50' };
                    if (pct >= 50) return { grade: 'B',  gpa: '3.00' };
                    if (pct >= 40) return { grade: 'C',  gpa: '2.00' };
                    if (pct >= 33) return { grade: 'D',  gpa: '1.00' };
                    return { grade: 'F', gpa: '0.00' };
                }

                function evaluateRow(row) {
                    const inpCq  = row.querySelector('.inp-cq');
                    const inpMcq = row.querySelector('.inp-mcq');
                    const inpPr  = row.querySelector('.inp-pr');

                    const valCq  = parseFloat(inpCq ? inpCq.value : 0) || 0;
                    const valMcq = parseFloat(inpMcq ? inpMcq.value : 0) || 0;
                    const valPr  = parseFloat(inpPr ? inpPr.value : 0) || 0;

                    const obtained = valCq + valMcq + valPr;
                    row.querySelector('.cell-total-obt').textContent = obtained.toFixed(2);

                    let failed = false;
                    if (cqPass > 0 && valCq < cqPass) failed = true;
                    if (mcqPass > 0 && valMcq < mcqPass) failed = true;
                    if (prPass > 0 && valPr < prPass) failed = true;
                    if (obtained < passLimit) failed = true;

                    const gradeBadge = row.querySelector('.cell-grade');
                    const gpaCell    = row.querySelector('.cell-gpa');

                    if (failed) {
                        gradeBadge.textContent = 'F';
                        gradeBadge.className   = 'dpt-badge-grade cell-grade grade-fail';
                        gpaCell.textContent   = '0.00';
                        gpaCell.style.color    = '#dc2626';
                    } else {
                        const res = computeGradeAndGpa(obtained, totalLimit);
                        gradeBadge.textContent = res.grade;
                        gradeBadge.className   = 'dpt-badge-grade cell-grade grade-pass';
                        gpaCell.textContent   = res.gpa;
                        gpaCell.style.color    = '#006a4e';
                    }
                }

                const table = document.getElementById('dptMarksEntryTable');
                if (table) {
                    table.addEventListener('input', function(e) {
                        if (e.target.classList.contains('dpt-mark-cell-input')) {
                            const row = e.target.closest('tr');
                            if (row) evaluateRow(row);
                        }
                    });
                }
            });
            </script>
        <?php endif; ?>

    </div>
    <?php
}