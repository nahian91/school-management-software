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
 * Custom Prefixes Applied: dpt-, afdp-
 * Architecture: Neo-Bento Interface with Kinetic Data Input & Secure Batch Execution
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
    $current_uri = remove_query_arg( array( 'status' ), $_SERVER['REQUEST_URI'] );
    $base_url    = esc_url_raw( $current_uri );

    $notice_message = '';

    // 1. Handle Marks Submission (Single-Hit Processing Matrix)
    if ( isset( $_POST['save_marks'] ) && isset( $_POST['educore_marks_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_marks_nonce'] ) ), 'save_marks_action' ) ) {
        $exam_id      = isset( $_POST['exam_id'] ) ? intval( $_POST['exam_id'] ) : 0;
        $subject_name = isset( $_POST['subject_name'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_name'] ) ) : '';
        $total_marks  = isset( $_POST['total_marks'] ) ? max( 0, floatval( $_POST['total_marks'] ) ) : 0;
        $marks_data   = isset( $_POST['marks'] ) ? (array) wp_unslash( $_POST['marks'] ) : array();
        
        $user_id = get_current_user_id();
        $saved   = 0;

        if ( ! empty( $marks_data ) && $total_marks > 0 ) {
            // Bulk pre-fetching existing results for this batch transaction
            $target_student_ids = array_map( 'intval', array_keys( $marks_data ) );
            $ids_placeholder    = implode( ',', array_fill( 0, count( $target_student_ids ), '%d' ) );
            
            $prep_query = $wpdb->prepare(
                "SELECT student_id, id FROM {$table_results} WHERE exam_id = %d AND subject_name = %s AND student_id IN ($ids_placeholder)",
                array_merge( array( $exam_id, $subject_name ), $target_student_ids )
            );
            $existing_records = $wpdb->get_results( $prep_query, OBJECT_K );

            foreach ( $marks_data as $student_id => $obtained ) {
                if ( $obtained === '' ) continue; // Skip empty inputs
                
                $obtained = floatval( $obtained );
                
                // Server-Side Constraint: Cap at full marks
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
            IFSEdu_School_Management_System::log_activity( "Updated marks for subject {$subject_name} across {$saved} students." );
        }

        $notice_message = sprintf(
            esc_html__( 'Marks configuration parsed and saved successfully for %d students.', 'ifsedu-sms' ),
            $saved
        );
    }

    // 2. Filter Variables Evaluation
    $filter_exam    = isset( $_GET['exam_id'] ) ? intval( $_GET['exam_id'] ) : 0;
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $subject_name   = isset( $_GET['subject_name'] ) ? sanitize_text_field( wp_unslash( $_GET['subject_name'] ) ) : '';

    // Fetch Dynamic Dropdowns Structure
    $exams = $wpdb->get_results( "SELECT id, exam_name FROM {$table_exams} ORDER BY id DESC" );
    
    // Fetch Unique Classes with Natural Numeric Sorting (1, 2, 3... 11)
    $raw_classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    if ( ! empty( $raw_classes ) ) {
        usort( $raw_classes, function( $a, $b ) {
            return strnatcasecmp( $a->class_name, $b->class_name );
        });
    }

    // Fetch Unique Sections
    $raw_sections = $wpdb->get_results( "SELECT DISTINCT section_name FROM {$table_units} WHERE section_name != '' ORDER BY section_name ASC" );

    // Subjects Dropdown List
    $subjects = $wpdb->get_results( "SELECT DISTINCT subject_name FROM {$table_subjects} WHERE subject_name != '' ORDER BY subject_name ASC" );
    
    $back_url = add_query_arg( array( 'sub' => 'list' ), $base_url );
    ?>

    <style>
        /* ==========================================================================
           1. CORE BENTO UI MATRIX LAYER
           ========================================================================== */
        .dpt-marks-root {
            margin: 20px 20px 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        .afdp-header-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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
            letter-spacing: -0.5px;
        }
        .afdp-header-block h2 .dashicons {
            font-size: 26px;
            width: 26px;
            height: 26px;
            color: #006a4e;
        }

        .afdp-status-banner {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            padding: 14px 18px;
            color: #065f46;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dpt-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            margin-bottom: 24px;
        }

        .afdp-card-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Filter Console Grid Layout for 5 Columns */
        .dpt-filter-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 14px;
            align-items: end;
        }
        .dpt-col-3 { grid-column: span 3; }
        .dpt-col-2 { grid-column: span 2; }

        @media (max-width: 992px) {
            .dpt-col-3, .dpt-col-2 { grid-column: span 12; }
        }

        /* Form Controls Framework */
        .dpt-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .dpt-form-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            margin: 0;
        }
        .dpt-input-field, .dpt-select-field {
            height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            width: 100%;
            box-shadow: none;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .dpt-input-field:focus, .dpt-select-field:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
            outline: none;
        }

        .dpt-btn-submit-trigger {
            height: 40px;
            background: #006a4e;
            border: 1px solid transparent;
            color: #ffffff;
            font-weight: 700;
            font-size: 13.5px;
            border-radius: 8px;
            padding: 0 20px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.15);
            width: 100%;
        }
        .dpt-btn-submit-trigger:hover {
            background: #00523c;
            color: #ffffff;
        }

        .dpt-btn-secondary {
            height: 36px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-weight: 700;
            font-size: 13px;
            border-radius: 8px;
            padding: 0 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .dpt-btn-secondary:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* Matrix Table UI System */
        .dpt-table-responsive {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
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
            font-size: 12.5px;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .dpt-marks-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13.5px;
            color: #334155;
            background: #ffffff;
        }
        .dpt-marks-table tr:last-child td {
            border-bottom: none;
        }
        .dpt-marks-table tr:hover td {
            background: #f8fafc;
        }

        /* Badges & Dynamic State Styling */
        .afdp-badge {
            font-size: 11.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
        }
        .afdp-badge-neutral { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .afdp-badge-success { background: #e6f4ea; color: #137333; border: 1px solid #ceead6; }
        .afdp-badge-danger  { background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf; }
        .afdp-badge-primary { background: #e8f0fe; color: #1a73e8; border: 1px solid #d2e3fc; }

        .afdp-mark-input {
            width: 110px;
            text-align: center;
            font-weight: 800;
            font-size: 14px;
            margin: 0 auto;
        }
        .afdp-mark-input.error {
            border-color: #ef4444 !important;
            color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }
    </style>

    <div class="dpt-marks-root">
        
        <!-- Navigation Header Block -->
        <div class="afdp-header-block">
            <h2>
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e( 'Subject-wise Marks Entry Matrix', 'ifsedu-sms' ); ?>
            </h2>
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-secondary">
                &larr; <?php esc_html_e( 'Back to Exams List', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <!-- System Notification Banner -->
        <?php if ( ! empty( $notice_message ) ) : ?>
            <div class="afdp-status-banner">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html( $notice_message ); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Console Bento Card -->
        <div class="dpt-bento-card">
            <form method="GET" action="<?php echo esc_url( $base_url ); ?>">
                <?php 
                // Preserve URL Query parameters dynamically
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
                    <!-- Exam Selection -->
                    <div class="dpt-form-group dpt-col-3">
                        <label class="dpt-form-label"><?php esc_html_e( 'Select Examination', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Class Selection -->
                    <div class="dpt-form-group dpt-col-2">
                        <label class="dpt-form-label"><?php esc_html_e( 'Select Class', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Class --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $raw_classes as $cls_obj ) : ?>
                                <option value="<?php echo esc_attr( $cls_obj->class_name ); ?>" <?php selected( $filter_class, $cls_obj->class_name ); ?>>
                                    <?php echo esc_html( $cls_obj->class_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Section Selection -->
                    <div class="dpt-form-group dpt-col-2">
                        <label class="dpt-form-label"><?php esc_html_e( 'Select Section', 'ifsedu-sms' ); ?></label>
                        <select name="section_name" class="dpt-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $raw_sections as $sec_obj ) : ?>
                                <option value="<?php echo esc_attr( $sec_obj->section_name ); ?>" <?php selected( $filter_section, $sec_obj->section_name ); ?>>
                                    <?php echo esc_html( $sec_obj->section_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Subject Selection -->
                    <div class="dpt-form-group dpt-col-3">
                        <label class="dpt-form-label"><?php esc_html_e( 'Subject Name', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="subject_name" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Select Subject --', 'ifsedu-sms' ); ?></option>
                            <?php if ( ! empty( $subjects ) ) : foreach ( $subjects as $sub_obj ) : ?>
                                <option value="<?php echo esc_attr( $sub_obj->subject_name ); ?>" <?php selected( $subject_name, $sub_obj->subject_name ); ?>>
                                    <?php echo esc_html( $sub_obj->subject_name ); ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <!-- Trigger Button -->
                    <div class="dpt-col-2">
                        <button type="submit" class="dpt-btn-submit-trigger">
                            <?php esc_html_e( 'Load Roster', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Marks Entry Table Module Area -->
        <?php
        if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $subject_name ) ) {
            
            // Build dynamic query depending on whether a specific section is selected
            $sql = "SELECT id, student_id, full_name, roll_no, section_name FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
            $params = array( $filter_class );

            if ( ! empty( $filter_section ) ) {
                $sql .= " AND section_name = %s";
                $params[] = $filter_section;
            }

            $sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";

            $students = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
            
            if ( $students ) {
                // Pre-fetch stored results for this roster
                $student_ids  = wp_list_pluck( $students, 'id' );
                $placeholders = implode( ',', array_fill( 0, count( $student_ids ), '%d' ) );
                
                $cached_results_query = $wpdb->prepare(
                    "SELECT student_id, total_marks, obtained_marks FROM {$table_results} WHERE exam_id = %d AND subject_name = %s AND student_id IN ($placeholders)",
                    array_merge( array( $filter_exam, $subject_name ), $student_ids )
                );
                $loaded_results_states = $wpdb->get_results( $cached_results_query, OBJECT_K );
                ?>
                <div class="dpt-bento-card">
                    <form method="POST" action="">
                        <?php wp_nonce_field( 'save_marks_action', 'educore_marks_nonce' ); ?>
                        <input type="hidden" name="exam_id" value="<?php echo intval( $filter_exam ); ?>">
                        <input type="hidden" name="subject_name" value="<?php echo esc_attr( $subject_name ); ?>">
                        
                        <div class="afdp-card-title">
                            <div>
                                <?php esc_html_e( 'Target Class:', 'ifsedu-sms' ); ?> <span style="color:#006a4e;"><?php echo esc_html( $filter_class ); ?></span> 
                                <?php if ( ! empty( $filter_section ) ) : ?>
                                    (<?php esc_html_e( 'Section:', 'ifsedu-sms' ); ?> <span style="color:#006a4e;"><?php echo esc_html( $filter_section ); ?></span>)
                                <?php endif; ?>
                                | <?php esc_html_e( 'Subject:', 'ifsedu-sms' ); ?> <span style="color:#2563eb;"><?php echo esc_html( $subject_name ); ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <label class="dpt-form-label"><?php esc_html_e( 'Full Subject Marks:', 'ifsedu-sms' ); ?></label>
                                <input type="number" step="0.01" name="total_marks" id="educore_total_marks" class="dpt-input-field" style="width: 90px; text-align: center; font-weight: 800; border-color: #006a4e;" value="100" min="1" required>
                            </div>
                        </div>

                        <div class="dpt-table-responsive">
                            <table class="dpt-marks-table">
                                <thead>
                                    <tr>
                                        <th style="width: 8%;"><?php esc_html_e( 'Roll', 'ifsedu-sms' ); ?></th>
                                        <th style="width: 15%;"><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                                        <th><?php esc_html_e( 'Student Name', 'ifsedu-sms' ); ?></th>
                                        <th style="width: 12%;"><?php esc_html_e( 'Section', 'ifsedu-sms' ); ?></th>
                                        <th style="width: 18%; text-align: center;"><?php esc_html_e( 'Obtained Marks', 'ifsedu-sms' ); ?></th>
                                        <th style="width: 14%; text-align: center;"><?php esc_html_e( 'Calculated Grade', 'ifsedu-sms' ); ?></th>
                                        <th style="width: 10%; text-align: center;"><?php esc_html_e( 'GPA', 'ifsedu-sms' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ( $students as $s ) : 
                                        $student_internal_id = intval( $s->id );
                                        $existing_marks      = isset( $loaded_results_states[ $student_internal_id ] ) ? $loaded_results_states[ $student_internal_id ]->obtained_marks : '';
                                        $full_subject_marks  = isset( $loaded_results_states[ $student_internal_id ] ) ? floatval( $loaded_results_states[ $student_internal_id ]->total_marks ) : 100;

                                        list( $initial_grade, $initial_gpa ) = ( $existing_marks !== '' && $existing_marks !== null ) ? educore_calculate_grade( floatval( $existing_marks ), $full_subject_marks ) : array( '-', '-' );
                                        
                                        $badge_class = 'afdp-badge-neutral';
                                        if ( $initial_grade === 'A+' ) {
                                            $badge_class = 'afdp-badge-success';
                                        } elseif ( $initial_grade === 'F' ) {
                                            $badge_class = 'afdp-badge-danger';
                                        } elseif ( $initial_grade !== '-' ) {
                                            $badge_class = 'afdp-badge-primary';
                                        }
                                    ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $s->roll_no ); ?></strong></td>
                                        <td><code><?php echo esc_html( $s->student_id ); ?></code></td>
                                        <td><strong><?php echo esc_html( $s->full_name ); ?></strong></td>
                                        <td><span class="afdp-badge afdp-badge-neutral"><?php echo esc_html( $s->section_name ? $s->section_name : __( 'N/A', 'ifsedu-sms' ) ); ?></span></td>
                                        <td style="text-align: center;">
                                            <input type="number" step="0.01" name="marks[<?php echo $student_internal_id; ?>]" class="dpt-input-field afdp-mark-input mark-input" value="<?php echo esc_attr( $existing_marks ); ?>" placeholder="0.00" min="0" data-student="<?php echo $student_internal_id; ?>">
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="afdp-badge grade-badge <?php echo esc_attr( $badge_class ); ?>" id="grade_<?php echo $student_internal_id; ?>"><?php echo esc_html( $initial_grade ); ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <strong class="gpa-text" id="gpa_<?php echo $student_internal_id; ?>"><?php echo esc_html( $initial_gpa ); ?></strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div style="margin-top: 24px; text-align: right;">
                            <button type="submit" name="save_marks" class="dpt-btn-submit-trigger" style="width: auto; padding: 0 32px;">
                                <?php esc_html_e( 'Submit Marks Matrix', 'ifsedu-sms' ); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Auto-Grading Script -->
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    function calcGrade(obtained, total) {
                        if (isNaN(obtained) || obtained === '' || obtained < 0 || total <= 0) return { grade: '-', gpa: '-' };
                        
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

                    $('.mark-input').on('input', function() {
                        var studentId = $(this).data('student');
                        var obtained  = parseFloat($(this).val());
                        var total     = parseFloat($('#educore_total_marks').val()) || 100;
                        
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
                        } else if (res.grade === '-') {
                            $badge.addClass('afdp-badge-neutral');
                        } else {
                            $badge.addClass('afdp-badge-primary');
                        }

                        $('#gpa_' + studentId).text(res.gpa);
                    });

                    $('#educore_total_marks').on('input', function() {
                        $('.mark-input').trigger('input');
                    });
                });
                </script>
                <?php
            } else {
                $section_label = ! empty( $filter_section ) ? ' (' . esc_html( $filter_section ) . ')' : '';
                $empty_notice  = sprintf(
                    esc_html__( 'No active students found in Class %s%s.', 'ifsedu-sms' ),
                    '<strong>' . esc_html( $filter_class ) . '</strong>',
                    '<strong>' . esc_html( $section_label ) . '</strong>'
                );
                ?>
                <div class="afdp-status-banner" style="background: #fffbe0; border-color: #fef3c7; color: #b45309; justify-content: center;">
                    <?php echo wp_kses_post( $empty_notice ); ?>
                </div>
                <?php
            }
        }
        ?>
    </div>
    <?php
}