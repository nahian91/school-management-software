<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Bangladesh Education Board Grading System Scale Helper
if ( ! function_exists( 'educore_calculate_grade' ) ) {
    function educore_calculate_grade( $obtained, $total ) {
        if ( empty( $total ) || $total <= 0 ) {
            return array( 'F', 0.00 );
        }

        $percentage = ( $obtained / $total ) * 100;
        
        if ( $percentage >= 80 ) return array( 'A+', 5.00 );
        if ( $percentage >= 70 ) return array( 'A',  4.00 );
        if ( $percentage >= 60 ) return array( 'A-', 3.50 );
        if ( $percentage >= 50 ) return array( 'B',  3.00 );
        if ( $percentage >= 40 ) return array( 'C',  2.00 );
        if ( $percentage >= 33 ) return array( 'D',  1.00 );
        return array( 'F', 0.00 );
    }
}

/**
 * High-End Subject-wise Examination Marks Evaluation Matrix
 * File: exams-marks-view.php
 */
function educore_exams_marks_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_subjects = $wpdb->prefix . 'sms_subjects';

    // Strict Security Control: Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to evaluate exam marks.', 'ifsedu-sms' ) );
    }

    // Dynamic Base URL preservation
    $current_uri = remove_query_arg( array( 'status', 'msg' ), $_SERVER['REQUEST_URI'] );
    $base_url    = esc_url_raw( $current_uri );

    $notice_message = '';

    // 1. Handle Marks Submission (Single-Hit Batch Processing Matrix)
    if ( isset( $_POST['save_marks'] ) && isset( $_POST['educore_marks_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_marks_nonce'] ) ), 'save_marks_action' ) ) {
        $exam_id      = isset( $_POST['exam_id'] ) ? intval( $_POST['exam_id'] ) : 0;
        $subject_name = isset( $_POST['subject_name'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_name'] ) ) : '';
        $total_marks  = isset( $_POST['total_marks'] ) ? max( 0, floatval( $_POST['total_marks'] ) ) : 100;
        $marks_data   = isset( $_POST['marks'] ) ? (array) wp_unslash( $_POST['marks'] ) : array();
        
        $user_id = get_current_user_id();
        $saved   = 0;

        if ( ! empty( $marks_data ) && $total_marks > 0 ) {
            $target_student_ids = array_map( 'intval', array_keys( $marks_data ) );
            $ids_placeholder    = implode( ',', array_fill( 0, count( $target_student_ids ), '%d' ) );
            
            $prep_query = $wpdb->prepare(
                "SELECT student_id, id FROM {$table_results} WHERE exam_id = %d AND subject_name = %s AND student_id IN ($ids_placeholder)",
                array_merge( array( $exam_id, $subject_name ), $target_student_ids )
            );
            $existing_records = $wpdb->get_results( $prep_query, OBJECT_K );

            foreach ( $marks_data as $student_id => $obtained ) {
                if ( $obtained === '' || $obtained === null ) continue; // Skip empty fields
                
                $obtained = floatval( $obtained );
                
                // Cap at full marks
                if ( $obtained > $total_marks ) {
                    $obtained = $total_marks;
                }

                list( $grade, $gpa ) = educore_calculate_grade( $obtained, $total_marks );

                $data = array(
                    'exam_id'        => $exam_id,
                    'student_id'     => intval( $student_id ),
                    'subject_name'   => $subject_name,
                    'total_marks'    => $total_marks,
                    'obtained_marks' => $obtained,
                    'grade'          => $grade,
                    'gpa'            => $gpa,
                    'evaluated_by'   => $user_id
                );
                
                $format = array( '%d', '%d', '%s', '%f', '%f', '%s', '%f', '%d' );

                if ( isset( $existing_records[ $student_id ] ) ) {
                    $wpdb->update( 
                        $table_results, 
                        $data, 
                        array( 'id' => intval( $existing_records[ $student_id ]->id ) ), 
                        $format, 
                        array( '%d' ) 
                    );
                } else {
                    $wpdb->insert( $table_results, $data, $format );
                }
                $saved++;
            }
        }

        if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
            IFSEdu_School_Management_System::log_activity( sprintf( "Saved marks evaluation for subject '%s' across %d students.", $subject_name, $saved ) );
        }

        $notice_message = sprintf(
            esc_html__( 'Marks configuration parsed and updated successfully for %d student(s).', 'ifsedu-sms' ),
            $saved
        );
    }

    // 2. Filter Variables Evaluation
    $filter_exam    = isset( $_GET['exam_id'] ) ? intval( $_GET['exam_id'] ) : 0;
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $subject_name   = isset( $_GET['subject_name'] ) ? sanitize_text_field( wp_unslash( $_GET['subject_name'] ) ) : '';

    // Fetch Exams with their associated Class Details
    $exams = $wpdb->get_results( "SELECT id, exam_name, class_name FROM {$table_exams} ORDER BY id DESC" );

    // Build Exam-to-Classes Mapping
    $exam_class_map = array();
    foreach ( $exams as $ex_item ) {
        $exam_class_map[ $ex_item->id ] = array();
        if ( ! empty( $ex_item->class_name ) ) {
            // Support comma-separated or single class names stored in exams
            $classes_array = array_map( 'trim', explode( ',', $ex_item->class_name ) );
            $exam_class_map[ $ex_item->id ] = array_filter( $classes_array );
        }
    }

    // Fallback: If an exam has no specific class set, all classes will be available
    $all_classes_raw = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    if ( ! empty( $all_classes_raw ) ) {
        usort( $all_classes_raw, 'strnatcasecmp' );
    }

    // All academic units for dynamic section filtering
    $all_units = $wpdb->get_results( "SELECT id, class_name, section_name FROM {$table_units} WHERE section_name != '' ORDER BY section_name ASC" );

    // Fetch subjects mapping
    $all_subjects = $wpdb->get_results( "
        SELECT s.id, s.subject_name, s.class_id, u.class_name, u.section_name 
        FROM {$table_subjects} s 
        LEFT JOIN {$table_units} u ON s.class_id = u.id 
        ORDER BY s.subject_name ASC
    " );
    
    $back_url = add_query_arg( array( 'sub' => 'list' ), $base_url );
    ?>

    <style>
        .dpt-marks-root {
            margin: 20px 20px 30px 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .afdp-header-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .afdp-header-block h2 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dpt-btn-secondary {
            height: 38px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-weight: 700;
            font-size: 13px;
            border-radius: 8px;
            padding: 0 16px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .dpt-btn-secondary:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .afdp-status-banner {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            padding: 14px 18px;
            color: #065f46;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .dpt-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px 28px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }

        .dpt-filter-grid {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1.5fr 2fr 1.2fr;
            gap: 16px;
            align-items: flex-end;
        }

        @media (max-width: 992px) {
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

        .dpt-select-field, .dpt-input-field {
            height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 14px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            width: 100%;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .dpt-select-field:focus, .dpt-input-field:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
            outline: none;
        }

        .dpt-btn-submit-trigger {
            height: 42px;
            background: #006a4e;
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
            border-radius: 8px;
            padding: 0 20px;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
            width: 100%;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .dpt-btn-submit-trigger:hover {
            background: #00523c;
        }

        /* Grade Scale Helper Guide */
        .dpt-grade-legend-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 10px 16px;
            margin-bottom: 20px;
            font-size: 12px;
        }

        .dpt-legend-pill {
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 11px;
            display: inline-flex;
            gap: 4px;
            align-items: center;
        }

        /* Analytics Quick Header */
        .dpt-analytics-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .dpt-strip-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            flex-direction: column;
        }

        .dpt-strip-title {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        .dpt-strip-val {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 2px;
        }

        /* Matrix Table Styling */
        .dpt-table-responsive {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .dpt-marks-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
        }

        .dpt-marks-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .dpt-marks-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: #334155;
            vertical-align: middle;
        }

        .dpt-marks-table tr:hover td {
            background: #f8fafc;
        }

        .afdp-mark-input {
            width: 110px;
            height: 38px;
            text-align: center;
            font-weight: 800;
            font-size: 15px;
            color: #0f172a;
            border-radius: 6px;
            margin: 0 auto;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            transition: all 0.2s ease;
        }

        .afdp-mark-input:focus {
            border-color: #006a4e !important;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.15) !important;
            outline: none;
            background: #ffffff;
        }

        .afdp-mark-input.error {
            border-color: #ef4444 !important;
            color: #dc2626 !important;
            background-color: #fef2f2 !important;
        }

        .afdp-badge {
            font-size: 12px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            text-align: center;
            min-width: 32px;
        }

        .afdp-badge-success { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .afdp-badge-primary { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .afdp-badge-danger  { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .afdp-badge-neutral { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
    </style>

    <div class="dpt-marks-root">
        
        <!-- Navigation Header -->
        <div class="afdp-header-block">
            <h2>
                <span class="dashicons dashicons-awards" style="color:#006a4e;"></span>
                <?php esc_html_e( 'Examination Marks Evaluation & Entry Matrix', 'ifsedu-sms' ); ?>
            </h2>
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size:14px; width:14px; height:14px;"></span>
                <?php esc_html_e( 'Back to Exams Directory', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <?php if ( ! empty( $notice_message ) ) : ?>
            <div class="afdp-status-banner">
                <span class="dashicons dashicons-yes-alt" style="font-size:20px; width:20px; height:20px; color:#006a4e;"></span>
                <span><?php echo esc_html( $notice_message ); ?></span>
            </div>
        <?php endif; ?>

        <!-- Selection Console Bento Box -->
        <div class="dpt-bento-card">
            <form method="GET" action="<?php echo esc_url( $base_url ); ?>" id="educoreMarksFilterForm">
                <?php 
                $parsed_url = wp_parse_url( $base_url );
                if ( isset( $parsed_url['query'] ) ) {
                    parse_str( $parsed_url['query'], $query_params );
                    foreach ( $query_params as $param_key => $param_val ) {
                        if ( ! in_array( $param_key, array( 'exam_id', 'class_name', 'section_name', 'subject_name' ) ) ) {
                            echo '<input type="hidden" name="' . esc_attr( $param_key ) . '" value="' . esc_attr( $param_val ) . '">';
                        }
                    }
                }
                ?>
                
                <div class="dpt-filter-grid">
                    <!-- 1. Exam Selection -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '1. Select Exam', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" id="educore_marks_exam_select" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Class Selection (Filtered strictly by chosen Exam) -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '2. Exam Class', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="educore_marks_class_select" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Select Exam First --', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- 3. Section Selection -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '3. Section', 'ifsedu-sms' ); ?></label>
                        <select name="section_name" id="educore_marks_section_select" class="dpt-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- 4. Subject Selection -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '4. Subject', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="subject_name" id="educore_marks_subject_select" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Select Subject --', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- 5. Submit Button -->
                    <div>
                        <button type="submit" class="dpt-btn-submit-trigger">
                            <span class="dashicons dashicons-filter"></span>
                            <?php esc_html_e( 'Load Matrix', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Marks Matrix Evaluation Roster Area -->
        <?php
        if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $subject_name ) ) {
            
            $sql = "SELECT id, student_id, full_name, roll_no, section_name FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
            $params = array( $filter_class );

            if ( ! empty( $filter_section ) ) {
                $sql .= " AND section_name = %s";
                $params[] = $filter_section;
            }

            $sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
            $students = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
            
            if ( ! empty( $students ) ) {
                $student_ids  = wp_list_pluck( $students, 'id' );
                $placeholders = implode( ',', array_fill( 0, count( $student_ids ), '%d' ) );
                
                $cached_results_query = $wpdb->prepare(
                    "SELECT student_id, total_marks, obtained_marks FROM {$table_results} WHERE exam_id = %d AND subject_name = %s AND student_id IN ($placeholders)",
                    array_merge( array( $filter_exam, $subject_name ), $student_ids )
                );
                $loaded_results_states = $wpdb->get_results( $cached_results_query, OBJECT_K );
                
                // Get default total marks
                $default_total = 100;
                if ( ! empty( $loaded_results_states ) ) {
                    $first_row = reset( $loaded_results_states );
                    if ( ! empty( $first_row->total_marks ) ) {
                        $default_total = floatval( $first_row->total_marks );
                    }
                }
                ?>

                <!-- Grading Scale Reference Bar -->
                <div class="dpt-grade-legend-bar">
                    <strong style="color:#0f172a; margin-right:4px;"><?php esc_html_e( 'Grading Scale:', 'ifsedu-sms' ); ?></strong>
                    <span class="dpt-legend-pill" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0;">80%+ [A+ | 5.0]</span>
                    <span class="dpt-legend-pill" style="background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe;">70-79% [A | 4.0]</span>
                    <span class="dpt-legend-pill" style="background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe;">60-69% [A- | 3.5]</span>
                    <span class="dpt-legend-pill" style="background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe;">50-59% [B | 3.0]</span>
                    <span class="dpt-legend-pill" style="background:#fffbeb; color:#b45309; border:1px solid #fde68a;">40-49% [C | 2.0]</span>
                    <span class="dpt-legend-pill" style="background:#fffbeb; color:#b45309; border:1px solid #fde68a;">33-39% [D | 1.0]</span>
                    <span class="dpt-legend-pill" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca;">&lt;33% [F | 0.0]</span>
                </div>

                <!-- Marks Table Bento Card -->
                <div class="dpt-bento-card">
                    <form method="POST" action="" id="educoreMarksMatrixForm">
                        <?php wp_nonce_field( 'save_marks_action', 'educore_marks_nonce' ); ?>
                        <input type="hidden" name="exam_id" value="<?php echo intval( $filter_exam ); ?>">
                        <input type="hidden" name="subject_name" value="<?php echo esc_attr( $subject_name ); ?>">
                        
                        <!-- Roster Action Top Bar -->
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:20px;">
                            <div>
                                <h3 style="margin:0; font-size:17px; font-weight:800; color:#0f172a;">
                                    <?php echo esc_html( $filter_class ); ?> 
                                    <?php echo ! empty( $filter_section ) ? '(' . esc_html( $filter_section ) . ')' : ''; ?>
                                    &mdash; <span style="color:#006a4e;"><?php echo esc_html( $subject_name ); ?></span>
                                </h3>
                                <span style="font-size:12px; color:#64748b; font-weight:600;">
                                    <?php printf( esc_html__( '%d Students in Evaluation Batch', 'ifsedu-sms' ), count( $students ) ); ?>
                                </span>
                            </div>

                            <div style="display:flex; align-items:center; gap:16px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <label class="dpt-form-label" style="margin:0;"><?php esc_html_e( 'Total Marks:', 'ifsedu-sms' ); ?></label>
                                    <input type="number" step="0.01" name="total_marks" id="educore_total_marks" class="dpt-input-field" style="width:85px; height:38px; text-align:center; font-weight:800; border-color:#006a4e; background:#ffffff;" value="<?php echo esc_attr( $default_total ); ?>" min="1" required>
                                </div>

                                <button type="submit" name="save_marks" class="dpt-btn-submit-trigger" style="width:auto; height:38px; padding:0 22px;">
                                    <span class="dashicons dashicons-saved"></span>
                                    <?php esc_html_e( 'Save All Marks', 'ifsedu-sms' ); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Live Analytics Summary -->
                        <div class="dpt-analytics-strip">
                            <div class="dpt-strip-card">
                                <span class="dpt-strip-title"><?php esc_html_e( 'Evaluated Count', 'ifsedu-sms' ); ?></span>
                                <span class="dpt-strip-val" id="stat_evaluated">0 / <?php echo count( $students ); ?></span>
                            </div>
                            <div class="dpt-strip-card">
                                <span class="dpt-strip-title"><?php esc_html_e( 'Class Average', 'ifsedu-sms' ); ?></span>
                                <span class="dpt-strip-val" id="stat_avg" style="color:#2563eb;">0.00</span>
                            </div>
                            <div class="dpt-strip-card">
                                <span class="dpt-strip-title"><?php esc_html_e( 'Passed Students', 'ifsedu-sms' ); ?></span>
                                <span class="dpt-strip-val" id="stat_pass" style="color:#059669;">0</span>
                            </div>
                            <div class="dpt-strip-card">
                                <span class="dpt-strip-title"><?php esc_html_e( 'Failed (F)', 'ifsedu-sms' ); ?></span>
                                <span class="dpt-strip-val" id="stat_fail" style="color:#dc2626;">0</span>
                            </div>
                        </div>

                        <!-- Evaluation Table -->
                        <div class="dpt-table-responsive">
                            <table class="dpt-marks-table">
                                <thead>
                                    <tr>
                                        <th style="width: 8%; text-align:center;"><?php esc_html_e( 'Roll', 'ifsedu-sms' ); ?></th>
                                        <th style="width: 16%;"><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                                        <th><?php esc_html_e( 'Student Full Name', 'ifsedu-sms' ); ?></th>
                                        <th style="width: 12%;"><?php esc_html_e( 'Section', 'ifsedu-sms' ); ?></th>
                                        <th style="width: 20%; text-align: center;"><?php esc_html_e( 'Obtained Marks', 'ifsedu-sms' ); ?></th>
                                        <th style="width: 14%; text-align: center;"><?php esc_html_e( 'Grade', 'ifsedu-sms' ); ?></th>
                                        <th style="width: 12%; text-align: center;"><?php esc_html_e( 'GPA', 'ifsedu-sms' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ( $students as $s ) : 
                                        $student_internal_id = intval( $s->id );
                                        $existing_marks      = isset( $loaded_results_states[ $student_internal_id ] ) ? $loaded_results_states[ $student_internal_id ]->obtained_marks : '';
                                        
                                        list( $initial_grade, $initial_gpa ) = ( $existing_marks !== '' && $existing_marks !== null ) ? educore_calculate_grade( floatval( $existing_marks ), $default_total ) : array( '—', '—' );
                                        
                                        $badge_class = 'afdp-badge-neutral';
                                        if ( $initial_grade === 'A+' ) $badge_class = 'afdp-badge-success';
                                        elseif ( $initial_grade === 'F' ) $badge_class = 'afdp-badge-danger';
                                        elseif ( $initial_grade !== '—' ) $badge_class = 'afdp-badge-primary';
                                    ?>
                                    <tr>
                                        <td style="text-align:center;">
                                            <span style="font-weight:800; color:#0f172a;">#<?php echo esc_html( $s->roll_no ); ?></span>
                                        </td>
                                        <td><code class="dpt-ref-code"><?php echo esc_html( $s->student_id ); ?></code></td>
                                        <td><strong style="color:#0f172a;"><?php echo esc_html( $s->full_name ); ?></strong></td>
                                        <td><span class="afdp-badge afdp-badge-neutral"><?php echo esc_html( $s->section_name ? $s->section_name : 'N/A' ); ?></span></td>
                                        <td style="text-align: center;">
                                            <input type="number" step="0.01" name="marks[<?php echo $student_internal_id; ?>]" class="afdp-mark-input mark-input" value="<?php echo esc_attr( $existing_marks ); ?>" placeholder="0.00" min="0" data-student="<?php echo $student_internal_id; ?>">
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="afdp-badge grade-badge <?php echo esc_attr( $badge_class ); ?>" id="grade_<?php echo $student_internal_id; ?>">
                                                <?php echo esc_html( $initial_grade ); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <strong class="gpa-text" id="gpa_<?php echo $student_internal_id; ?>" style="color:#0f172a; font-size:14px;">
                                                <?php echo esc_html( $initial_gpa ); ?>
                                            </strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div style="margin-top: 24px; display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:12px; color:#64748b; font-weight:600;">
                                <?php esc_html_e( 'Tip: Use Up/Down Arrow or Enter keys to quickly move between mark inputs.', 'ifsedu-sms' ); ?>
                            </span>
                            <button type="submit" name="save_marks" class="dpt-btn-submit-trigger" style="width:auto; padding:0 36px; height:44px;">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e( 'Save All Exam Marks', 'ifsedu-sms' ); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Real-time Grading & Matrix Keyboard Navigation Engine -->
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    function calcGrade(obtained, total) {
                        if (isNaN(obtained) || obtained === '' || obtained < 0 || total <= 0) return { grade: '—', gpa: '—' };
                        
                        var pct = (obtained / total) * 100;
                        if (pct > 100) pct = 100;

                        if (pct >= 80) return { grade: 'A+', gpa: '5.00' };
                        if (pct >= 70) return { grade: 'A',  gpa: '4.00' };
                        if (pct >= 60) return { grade: 'A-', gpa: '3.50' };
                        if (pct >= 50) return { grade: 'B',  gpa: '3.00' };
                        if (pct >= 40) return { grade: 'C',  gpa: '2.00' };
                        if (pct >= 33) return { grade: 'D',  gpa: '1.00' };
                        return { grade: 'F', gpa: '0.00' };
                    }

                    function updateLiveStats() {
                        var totalMarks = parseFloat($('#educore_total_marks').val()) || 100;
                        var totalEvaluated = 0;
                        var sumMarks = 0;
                        var passed = 0;
                        var failed = 0;

                        $('.mark-input').each(function() {
                            var val = $(this).val();
                            if (val !== '') {
                                var num = parseFloat(val);
                                totalEvaluated++;
                                sumMarks += num;
                                var res = calcGrade(num, totalMarks);
                                if (res.grade === 'F') {
                                    failed++;
                                } else if (res.grade !== '—') {
                                    passed++;
                                }
                            }
                        });

                        $('#stat_evaluated').text(totalEvaluated + ' / ' + $('.mark-input').length);
                        var avg = totalEvaluated > 0 ? (sumMarks / totalEvaluated).toFixed(2) : '0.00';
                        $('#stat_avg').text(avg);
                        $('#stat_pass').text(passed);
                        $('#stat_fail').text(failed);
                    }

                    $('.mark-input').on('input', function() {
                        var studentId = $(this).data('student');
                        var val       = $(this).val();
                        var total     = parseFloat($('#educore_total_marks').val()) || 100;
                        
                        if (val === '') {
                            $('#grade_' + studentId).text('—').removeClass('afdp-badge-success afdp-badge-danger afdp-badge-primary').addClass('afdp-badge-neutral');
                            $('#gpa_' + studentId).text('—');
                            $(this).removeClass('error');
                            updateLiveStats();
                            return;
                        }

                        var obtained = parseFloat(val);
                        if (obtained > total) {
                            $(this).addClass('error');
                        } else {
                            $(this).removeClass('error');
                        }

                        var res = calcGrade(obtained, total);
                        var $badge = $('#grade_' + studentId);
                        $badge.text(res.grade);

                        $badge.removeClass('afdp-badge-neutral afdp-badge-success afdp-badge-danger afdp-badge-primary');
                        if (res.grade === 'F') {
                            $badge.addClass('afdp-badge-danger');
                        } else if (res.grade === 'A+') {
                            $badge.addClass('afdp-badge-success');
                        } else if (res.grade === '—') {
                            $badge.addClass('afdp-badge-neutral');
                        } else {
                            $badge.addClass('afdp-badge-primary');
                        }

                        $('#gpa_' + studentId).text(res.gpa);
                        updateLiveStats();
                    });

                    // Keyboard navigation between cells
                    $('.mark-input').on('keydown', function(e) {
                        var inputs = $('.mark-input');
                        var idx = inputs.index(this);

                        if (e.which === 13 || e.which === 40) { // Enter or Down Arrow
                            e.preventDefault();
                            if (idx < inputs.length - 1) {
                                inputs.eq(idx + 1).focus().select();
                            }
                        } else if (e.which === 38) { // Up Arrow
                            e.preventDefault();
                            if (idx > 0) {
                                inputs.eq(idx - 1).focus().select();
                            }
                        }
                    });

                    $('#educore_total_marks').on('input', function() {
                        $('.mark-input').trigger('input');
                    });

                    // Run on initial load
                    updateLiveStats();
                });
                </script>
                <?php
            } else {
                $section_label = ! empty( $filter_section ) ? ' (' . esc_html( $filter_section ) . ')' : '';
                ?>
                <div class="afdp-status-banner" style="background:#fffbe0; border-color:#fde68a; color:#b45309; justify-content:center;">
                    <?php printf( esc_html__( 'No active students found in Class %1$s%2$s.', 'ifsedu-sms' ), '<strong>' . esc_html( $filter_class ) . '</strong>', '<strong>' . esc_html( $section_label ) . '</strong>' ); ?>
                </div>
                <?php
            }
        }
        ?>
    </div>

    <!-- DYNAMIC SELECTOR CHAINING ENGINE: Exam -> Related Classes -> Sections -> Subjects -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const examClassMap = <?php echo wp_json_encode( ! empty( $exam_class_map ) ? $exam_class_map : array() ); ?>;
        const allGlobalClasses = <?php echo wp_json_encode( ! empty( $all_classes_raw ) ? $all_classes_raw : array() ); ?>;
        const unitsMap     = <?php echo wp_json_encode( ! empty( $all_units ) ? $all_units : array() ); ?>;
        const subjectsMap  = <?php echo wp_json_encode( ! empty( $all_subjects ) ? $all_subjects : array() ); ?>;

        const currentExam    = "<?php echo esc_js( $filter_exam ); ?>";
        const currentClass   = "<?php echo esc_js( $filter_class ); ?>";
        const currentSection = "<?php echo esc_js( $filter_section ); ?>";
        const currentSubject = "<?php echo esc_js( $subject_name ); ?>";

        const examSelect    = document.getElementById('educore_marks_exam_select');
        const classSelect   = document.getElementById('educore_marks_class_select');
        const sectionSelect = document.getElementById('educore_marks_section_select');
        const subjectSelect = document.getElementById('educore_marks_subject_select');

        // 1. Populate Classes that are strictly related to the selected Exam
        function populateExamClasses(selectedExamId, selectedClassName = '') {
            if (!classSelect) return;
            classSelect.innerHTML = '<option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-sms' ); ?></option>';

            if (!selectedExamId) {
                classSelect.innerHTML = '<option value=""><?php esc_html_e( '-- Select Exam First --', 'ifsedu-sms' ); ?></option>';
                populateSections('');
                populateSubjects('');
                return;
            }

            let classesToLoad = [];
            if (examClassMap[selectedExamId] && examClassMap[selectedExamId].length > 0) {
                classesToLoad = examClassMap[selectedExamId];
            } else {
                // If exam was created globally for all classes, load all classes
                classesToLoad = allGlobalClasses;
            }

            classesToLoad.forEach(cls => {
                const opt = document.createElement('option');
                opt.value = cls;
                opt.textContent = cls;
                if (cls == selectedClassName) {
                    opt.selected = true;
                }
                classSelect.appendChild(opt);
            });

            populateSections(classSelect.value, currentSection);
            populateSubjects(classSelect.value, sectionSelect.value, currentSubject);
        }

        // 2. Populate Sections based on selected Class
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

        // 3. Populate Subjects based on selected Class and Section
        function populateSubjects(selectedClass, selectedSecName = '', selectedSubName = '') {
            if (!subjectSelect) return;
            subjectSelect.innerHTML = '<option value=""><?php esc_html_e( '-- Select Subject --', 'ifsedu-sms' ); ?></option>';
            if (!selectedClass) return;

            let filteredSubjects = subjectsMap.filter(item => item.class_name == selectedClass);

            if (selectedSecName) {
                const sectionSpecific = filteredSubjects.filter(item => item.section_name == selectedSecName);
                if (sectionSpecific.length > 0) {
                    filteredSubjects = sectionSpecific;
                }
            }

            const uniqueSubjects = [...new Set(filteredSubjects.map(item => item.subject_name).filter(Boolean))];

            uniqueSubjects.forEach(subName => {
                const opt = document.createElement('option');
                opt.value = subName;
                opt.textContent = subName;
                if (subName == selectedSubName) {
                    opt.selected = true;
                }
                subjectSelect.appendChild(opt);
            });
        }

        // Initial execution on load (preserves existing selected state)
        if (examSelect && classSelect) {
            populateExamClasses(examSelect.value, currentClass);

            examSelect.addEventListener('change', function() {
                populateExamClasses(this.value, '');
            });

            classSelect.addEventListener('change', function() {
                populateSections(this.value);
                populateSubjects(this.value, sectionSelect.value);
            });

            sectionSelect.addEventListener('change', function() {
                populateSubjects(classSelect.value, this.value);
            });
        }
    });
    </script>
    <?php
}