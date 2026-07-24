<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * High-End Academic Progress Marksheet & Tabulation Engine
 * File: exams-report-view.php
 * Custom Prefixes Applied: dpt-, afdp-
 * Architecture: Neo-Bento Interface with Print-Ready Layouts & Security Controls
 */

// 1. AJAX Handler for Dynamic Section Loading based on Class
add_action( 'wp_ajax_educore_get_sections_by_class', 'educore_get_sections_by_class_report_handler' );
function educore_get_sections_by_class_report_handler() {
    check_ajax_referer( 'educore_report_nonce', 'security' );

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

// 2. AJAX Handler for Dynamic Student Fetching based on Class & Section
add_action( 'wp_ajax_educore_get_students_by_class', 'educore_get_students_by_class_handler' );
function educore_get_students_by_class_handler() {
    check_ajax_referer( 'educore_report_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ifsedu-sms' ) ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name   = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $sql = "SELECT id, full_name, student_id, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
    $params = array( $class_name );

    if ( ! empty( $section_name ) ) {
        $sql .= " AND section_name = %s";
        $params[] = $section_name;
    }

    $sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";

    $students = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

    $data = array();
    if ( ! empty( $students ) ) {
        foreach ( $students as $s ) {
            $data[] = array(
                'id'         => intval( $s->id ),
                'full_name'  => esc_html( $s->full_name ),
                'student_id' => esc_html( $s->student_id ),
                'roll_no'    => esc_html( $s->roll_no ),
            );
        }
    }

    wp_send_json_success( $data );
}

function educore_exams_report_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Strict Security Control: Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to generate academic reports.', 'ifsedu-sms' ) );
    }

    // Dynamic Base URL preservation
    $current_uri = remove_query_arg( array( 'status' ), $_SERVER['REQUEST_URI'] );
    $base_url    = esc_url_raw( $current_uri );

    // Fetch Dynamic Dropdown Data
    $exams = $wpdb->get_results( "SELECT id, exam_name FROM {$table_exams} ORDER BY id DESC" );

    // Fetch Unique Classes with Natural Numeric Sorting
    $raw_classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    if ( ! empty( $raw_classes ) ) {
        usort( $raw_classes, function( $a, $b ) {
            return strnatcasecmp( $a->class_name, $b->class_name );
        });
    }

    // GET Filter Parameters Sanitization
    $filter_exam    = isset( $_GET['exam_id'] ) ? intval( $_GET['exam_id'] ) : 0;
    $report_type    = isset( $_GET['report_type'] ) ? sanitize_text_field( wp_unslash( $_GET['report_type'] ) ) : 'individual';
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $filter_student = isset( $_GET['student_id'] ) ? intval( $_GET['student_id'] ) : 0;
    
    // Fetch available sections for selected class if present
    $available_sections = array();
    if ( ! empty( $filter_class ) ) {
        $available_sections = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT section_name FROM {$table_units} WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
            $filter_class
        ) );
    }

    $back_url = add_query_arg( array( 'sub' => 'list' ), $base_url );
    ?>

    <style>
        /* ==========================================================================
           1. CORE BENTO UI SYSTEM & PRINT ENGINE
           ========================================================================== */
        .dpt-report-root {
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

        .dpt-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            margin-bottom: 24px;
        }

        /* Filter Console Layout */
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

        /* Print Documents Framing */
        .afdp-report-card-container {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px;
            border: 2px solid #0f172a;
            border-radius: 8px;
            color: #0f172a;
        }
        .afdp-report-header {
            text-align: center;
            border-bottom: 2px double #006a4e;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .afdp-marks-table, .afdp-tabulation-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .afdp-marks-table th, .afdp-marks-table td,
        .afdp-tabulation-table th, .afdp-tabulation-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            text-align: center;
            font-size: 13px;
        }
        .afdp-marks-table th, .afdp-tabulation-table th {
            background-color: #f8fafc;
            font-weight: 700;
            color: #334155;
        }

        .afdp-gpa-box {
            border: 2px solid #006a4e;
            padding: 16px;
            text-align: center;
            border-radius: 8px;
            margin-top: 24px;
            background: #f0fdf4;
        }

        .afdp-sign-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 48px;
            padding-top: 16px;
        }
        .afdp-sign-line {
            border-top: 1px solid #64748b;
            width: 180px;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            padding-top: 4px;
            color: #475569;
        }

        .afdp-tabulation-container {
            background: #ffffff;
            padding: 25px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
        }

        .afdp-status-banner {
            background: #fffbe0;
            border: 1px solid #fef3c7;
            border-radius: 10px;
            padding: 16px;
            color: #b45309;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            margin-bottom: 24px;
        }

        /* Printable Dynamic CSS Rules */
        @media print {
            body * { visibility: hidden; }
            .afdp-report-card-container, .afdp-report-card-container *,
            .afdp-tabulation-container, .afdp-tabulation-container * {
                visibility: visible;
            }
            .afdp-report-card-container, .afdp-tabulation-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                border: none;
                padding: 0;
                box-shadow: none;
            }
            .no-print { display: none !important; }
        }
    </style>

    <div class="dpt-report-root">
        
        <!-- Header Block -->
        <div class="afdp-header-block no-print">
            <h2>
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e( 'Academic Progress & Tabulation Generator', 'ifsedu-sms' ); ?>
            </h2>
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-secondary">
                &larr; <?php esc_html_e( 'Back to Exams List', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <!-- Generator Control Bento Card -->
        <div class="dpt-bento-card no-print">
            <form method="GET" action="<?php echo esc_url( $base_url ); ?>">
                <?php 
                // Preserve URL Query parameters dynamically
                $parsed_url = wp_parse_url( $base_url );
                if ( isset( $parsed_url['query'] ) ) {
                    parse_str( $parsed_url['query'], $query_params );
                    foreach ( $query_params as $param_key => $param_val ) {
                        if ( ! in_array( $param_key, array( 'exam_id', 'report_type', 'class_name', 'section_name', 'student_id' ) ) ) {
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
                            <option value=""><?php esc_html_e( '-- Choose Exam Scheme --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Report Type -->
                    <div class="dpt-form-group dpt-col-2">
                        <label class="dpt-form-label"><?php esc_html_e( 'Report Type', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="report_type" id="educore_report_type" class="dpt-select-field" required>
                            <option value="individual" <?php selected( $report_type, 'individual' ); ?>><?php esc_html_e( 'Student Marksheet', 'ifsedu-sms' ); ?></option>
                            <option value="tabulation" <?php selected( $report_type, 'tabulation' ); ?>><?php esc_html_e( 'Class Tabulation Sheet', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- Class Selection -->
                    <div class="dpt-form-group dpt-col-2">
                        <label class="dpt-form-label"><?php esc_html_e( 'Select Class', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="educore_class_filter" class="dpt-select-field" required>
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
                        <select name="section_name" id="educore_section_filter" class="dpt-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $available_sections as $sec_val ) : ?>
                                <option value="<?php echo esc_attr( $sec_val ); ?>" <?php selected( $filter_section, $sec_val ); ?>>
                                    <?php echo esc_html( $sec_val ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Student Selection -->
                    <div class="dpt-form-group dpt-col-3" id="student_select_box" style="<?php echo ( 'tabulation' === $report_type ) ? 'display:none;' : ''; ?>">
                        <label class="dpt-form-label"><?php esc_html_e( 'Select Student', 'ifsedu-sms' ); ?></label>
                        <select name="student_id" id="educore_student_filter" class="dpt-select-field">
                            <option value=""><?php esc_html_e( '-- Choose Student --', 'ifsedu-sms' ); ?></option>
                            <?php 
                            if ( ! empty( $filter_class ) ) {
                                $sql = "SELECT id, full_name, student_id, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
                                $params = array( $filter_class );

                                if ( ! empty( $filter_section ) ) {
                                    $sql .= " AND section_name = %s";
                                    $params[] = $filter_section;
                                }

                                $sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
                                $student_list = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

                                foreach ( $student_list as $s ) : ?>
                                    <option value="<?php echo intval( $s->id ); ?>" <?php selected( $filter_student, $s->id ); ?>>
                                        <?php 
                                            /* translators: 1: Roll Number, 2: Full Name, 3: Student ID */
                                            printf( esc_html__( 'Roll %1$s: %2$s (%3$s)', 'ifsedu-sms' ), esc_html( $s->roll_no ), esc_html( $s->full_name ), esc_html( $s->student_id ) ); 
                                        ?>
                                    </option>
                                <?php endforeach;
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Submit Button -->
                    <div class="dpt-col-2">
                        <button type="submit" class="dpt-btn-submit-trigger">
                            <?php esc_html_e( 'Generate', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Dynamic Dropdown AJAX Controller Script -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( wp_create_nonce( "educore_report_nonce" ) ); ?>';

            function toggleStudentBox() {
                if ($('#educore_report_type').val() === 'tabulation') {
                    $('#student_select_box').hide();
                } else {
                    $('#student_select_box').show();
                }
            }

            $('#educore_report_type').on('change', function() {
                toggleStudentBox();
            });

            // Fetch Sections & Reload Students when Class changes
            $('#educore_class_filter').on('change', function() {
                var selectedClass   = $(this).val();
                var $sectionSelect = $('#educore_section_filter');

                $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>');

                if (!selectedClass) {
                    reloadStudents();
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'educore_get_sections_by_class',
                        security: nonce,
                        class_name: selectedClass
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var secOptions = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>';
                            $.each(response.data, function(i, sec) {
                                secOptions += '<option value="' + sec + '">' + sec + '</option>';
                            });
                            $sectionSelect.html(secOptions);
                        }
                        reloadStudents();
                    }
                });
            });

            // Reload Students when Section changes
            $('#educore_section_filter').on('change', function() {
                reloadStudents();
            });

            function reloadStudents() {
                var selectedClass   = $('#educore_class_filter').val();
                var selectedSection = $('#educore_section_filter').val();
                var $studentSelect  = $('#educore_student_filter');

                $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Students... --', 'ifsedu-sms' ) ); ?></option>');

                if (!selectedClass) {
                    $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-sms' ) ); ?></option>');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'educore_get_students_by_class',
                        security: nonce,
                        class_name: selectedClass,
                        section_name: selectedSection
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-sms' ) ); ?></option>';
                            $.each(response.data, function(index, student) {
                                options += '<option value="' + student.id + '">Roll ' + student.roll_no + ': ' + student.full_name + ' (' + student.student_id + ')</option>';
                            });
                            $studentSelect.html(options);
                        } else {
                            $studentSelect.html('<option value=""><?php echo esc_js( __( 'No Active Students Found', 'ifsedu-sms' ) ); ?></option>');
                        }
                    },
                    error: function() {
                        $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-sms' ) ); ?></option>');
                    }
                });
            }
        });
        </script>

        <?php
        // ==========================================================================
        // CASE A: INDIVIDUAL STUDENT MARKSHEET REPORT
        // ==========================================================================
        if ( $filter_exam > 0 && 'individual' === $report_type && $filter_student > 0 ) {
            $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_students} WHERE id = %d", $filter_student ) );
            $exam    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_exams} WHERE id = %d", $filter_exam ) );
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_results} WHERE exam_id = %d AND student_id = %d ORDER BY subject_name ASC", $filter_exam, $filter_student ) );

            if ( ! $results ) {
                echo '<div class="afdp-status-banner no-print">' . esc_html__( 'No published marks found for this student in the selected examination.', 'ifsedu-sms' ) . '</div>';
                echo '</div>'; // Close root
                return;
            }

            // Aggregate Statistics
            $total_sub          = count( $results );
            $sum_gpa            = 0;
            $total_marks_all    = 0;
            $obtained_marks_all = 0;
            $has_failed         = false;

            foreach ( $results as $r ) {
                $sum_gpa            += $r->gpa;
                $total_marks_all    += $r->total_marks;
                $obtained_marks_all += $r->obtained_marks;
                if ( 'F' === $r->grade ) {
                    $has_failed = true;
                }
            }

            $avg_gpa     = ( $total_sub > 0 ) ? ( $sum_gpa / $total_sub ) : 0;
            $final_gpa   = $has_failed ? '0.00' : number_format( $avg_gpa, 2 );
            $final_grade = $has_failed ? 'F' : educore_calculate_grade( $obtained_marks_all, $total_marks_all )[0];
            $school_name = get_bloginfo( 'name' );
            ?>

            <div style="text-align: center; margin-bottom: 20px;" class="no-print">
                <button onclick="window.print();" class="dpt-btn-submit-trigger" style="width: auto; padding: 0 32px; font-size: 14px;">
                    <span class="dashicons dashicons-printer"></span>
                    <?php esc_html_e( 'Print Academic Report Card', 'ifsedu-sms' ); ?>
                </button>
            </div>

            <div class="afdp-report-card-container">
                <div class="afdp-report-header">
                    <h2 style="margin: 0; font-weight: 800; color: #006a4e; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo esc_html( $school_name ); ?></h2>
                    <h4 style="margin: 8px 0 4px 0; font-weight: 700; color: #1e293b;"><?php echo esc_html( $exam->exam_name ); ?></h4>
                    <span style="background: #006a4e; color: #ffffff; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                        <?php esc_html_e( 'Academic Progress Report Card', 'ifsedu-sms' ); ?>
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 24px;">
                    <div>
                        <p style="margin: 4px 0;"><strong><?php esc_html_e( 'Student Name:', 'ifsedu-sms' ); ?></strong> <span style="text-transform: uppercase; font-weight: 700;"><?php echo esc_html( $student->full_name ); ?></span></p>
                        <p style="margin: 4px 0;"><strong><?php esc_html_e( 'Student ID:', 'ifsedu-sms' ); ?></strong> <code><?php echo esc_html( $student->student_id ); ?></code></p>
                        <p style="margin: 4px 0;"><strong><?php esc_html_e( 'Guardian:', 'ifsedu-sms' ); ?></strong> <?php echo esc_html( $student->guardian_name ? $student->guardian_name : $student->father_name ); ?></p>
                    </div>
                    <div style="text-align: right;">
                        <p style="margin: 4px 0;"><strong><?php esc_html_e( 'Class:', 'ifsedu-sms' ); ?></strong> <?php echo esc_html( $student->class_name ); ?></p>
                        <p style="margin: 4px 0;"><strong><?php esc_html_e( 'Section:', 'ifsedu-sms' ); ?></strong> <?php echo esc_html( $student->section_name ? $student->section_name : __( 'N/A', 'ifsedu-sms' ) ); ?></p>
                        <p style="margin: 4px 0;"><strong><?php esc_html_e( 'Class Roll No:', 'ifsedu-sms' ); ?></strong> <span style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 2px 8px; border-radius: 4px; font-weight: 700;"><?php echo esc_html( $student->roll_no ); ?></span></p>
                    </div>
                </div>

                <table class="afdp-marks-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;"><?php esc_html_e( 'Subject Name', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Total Marks', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Obtained Marks', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Letter Grade', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Grade Point (GP)', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $results as $r ) : ?>
                        <tr>
                            <td style="text-align: left; font-weight: 700;"><?php echo esc_html( $r->subject_name ); ?></td>
                            <td><?php echo floatval( $r->total_marks ); ?></td>
                            <td><strong><?php echo floatval( $r->obtained_marks ); ?></strong></td>
                            <td style="font-weight: 700; color: <?php echo 'F' === $r->grade ? '#dc2626' : '#16a34a'; ?>;"><?php echo esc_html( $r->grade ); ?></td>
                            <td><?php echo number_format( $r->gpa, 2 ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="afdp-gpa-box">
                    <h4 style="margin: 0; font-weight: 800; color: #006a4e; text-transform: uppercase;"><?php esc_html_e( 'Final Evaluation Result', 'ifsedu-sms' ); ?></h4>
                    <p style="font-size: 16px; margin: 8px 0 0 0;">
                        <?php esc_html_e( 'Overall Status:', 'ifsedu-sms' ); ?> 
                        <strong style="color: <?php echo $has_failed ? '#dc2626' : '#16a34a'; ?>;">
                            <?php 
                                if ( $has_failed ) {
                                    esc_html_e( 'FAILED (F)', 'ifsedu-sms' );
                                } else {
                                    /* translators: %s: Final Letter Grade */
                                    printf( esc_html__( 'PASSED (%s)', 'ifsedu-sms' ), esc_html( $final_grade ) );
                                }
                            ?>
                        </strong> &nbsp;|&nbsp; 
                        <?php esc_html_e( 'Cumulative GPA:', 'ifsedu-sms' ); ?> <strong style="font-size: 18px;"><?php echo esc_html( $final_gpa ); ?></strong>
                    </p>
                </div>

                <div class="afdp-sign-row">
                    <div class="afdp-sign-line"><?php esc_html_e( 'Class Teacher Signature', 'ifsedu-sms' ); ?></div>
                    <div class="afdp-sign-line"><?php esc_html_e( 'Exam Controller', 'ifsedu-sms' ); ?></div>
                    <div class="afdp-sign-line"><?php esc_html_e( 'Principal / Headmaster', 'ifsedu-sms' ); ?></div>
                </div>
            </div>
            <?php
        }

        // ==========================================================================
        // CASE B: CLASS TABULATION SHEET REPORT
        // ==========================================================================
        elseif ( $filter_exam > 0 && 'tabulation' === $report_type && ! empty( $filter_class ) ) {
            $exam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_exams} WHERE id = %d", $filter_exam ) );
            
            $sql = "SELECT * FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
            $params = array( $filter_class );

            if ( ! empty( $filter_section ) ) {
                $sql .= " AND section_name = %s";
                $params[] = $filter_section;
            }

            $sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
            $students = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

            $subjects = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT subject_name FROM {$table_results} WHERE exam_id = %d ORDER BY subject_name ASC", $filter_exam ) );

            if ( ! $students || ! $subjects ) {
                $sec_label = ! empty( $filter_section ) ? ' (' . esc_html( $filter_section ) . ')' : '';
                $empty_tab_notice = sprintf(
                    esc_html__( 'No evaluated results or subjects found for Class %s%s in this exam.', 'ifsedu-sms' ),
                    '<strong>' . esc_html( $filter_class ) . '</strong>',
                    '<strong>' . esc_html( $sec_label ) . '</strong>'
                );
                echo '<div class="afdp-status-banner no-print">' . wp_kses_post( $empty_tab_notice ) . '</div>';
                echo '</div>'; // Close root
                return;
            }
            ?>

            <div style="text-align: center; margin-bottom: 20px;" class="no-print">
                <button onclick="window.print();" class="dpt-btn-submit-trigger" style="width: auto; padding: 0 32px; font-size: 14px;">
                    <span class="dashicons dashicons-printer"></span>
                    <?php esc_html_e( 'Print Class Tabulation Sheet', 'ifsedu-sms' ); ?>
                </button>
            </div>

            <div class="afdp-tabulation-container">
                <div style="text-align: center; margin-bottom: 24px; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px;">
                    <h3 style="margin: 0; font-weight: 800; color: #006a4e; text-transform: uppercase;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h3>
                    <h5 style="margin: 6px 0 0 0; font-weight: 700; color: #1e293b;"><?php echo esc_html( $exam->exam_name ); ?> - <?php esc_html_e( 'Tabulation Sheet', 'ifsedu-sms' ); ?></h5>
                    <span style="display: inline-block; background: #f1f5f9; color: #475569; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 700; margin-top: 6px;">
                        <?php esc_html_e( 'Class:', 'ifsedu-sms' ); ?> <?php echo esc_html( $filter_class ); ?>
                        <?php if ( ! empty( $filter_section ) ) : ?>
                            (<?php esc_html_e( 'Section:', 'ifsedu-sms' ); ?> <?php echo esc_html( $filter_section ); ?>)
                        <?php endif; ?>
                    </span>
                </div>

                <div class="dpt-table-responsive">
                    <table class="afdp-tabulation-table">
                        <thead>
                            <tr>
                                <th style="width: 5%;"><?php esc_html_e( 'Roll', 'ifsedu-sms' ); ?></th>
                                <th style="width: 10%;"><?php esc_html_e( 'ID', 'ifsedu-sms' ); ?></th>
                                <th style="width: 18%; text-align: left;"><?php esc_html_e( 'Student Name', 'ifsedu-sms' ); ?></th>
                                <?php foreach ( $subjects as $sub ) : ?>
                                    <th><?php echo esc_html( $sub ); ?></th>
                                <?php endforeach; ?>
                                <th style="width: 10%;"><?php esc_html_e( 'Total Score', 'ifsedu-sms' ); ?></th>
                                <th style="width: 8%;"><?php esc_html_e( 'GPA', 'ifsedu-sms' ); ?></th>
                                <th style="width: 8%;"><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $students as $s ) : 
                                $student_results = $wpdb->get_results( $wpdb->prepare(
                                    "SELECT subject_name, obtained_marks, grade, gpa FROM {$table_results} WHERE exam_id = %d AND student_id = %d",
                                    $filter_exam, $s->id
                                ), OBJECT_K );

                                $total_obtained = 0;
                                $sum_gpa        = 0;
                                $sub_count      = 0;
                                $has_failed     = false;
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $s->roll_no ); ?></strong></td>
                                <td><code><?php echo esc_html( $s->student_id ); ?></code></td>
                                <td style="text-align: left; font-weight: 600;"><?php echo esc_html( $s->full_name ); ?></td>
                                
                                <?php foreach ( $subjects as $sub ) : 
                                    if ( isset( $student_results[ $sub ] ) ) {
                                        $res = $student_results[ $sub ];
                                        $total_obtained += $res->obtained_marks;
                                        $sum_gpa        += $res->gpa;
                                        $sub_count++;
                                        if ( 'F' === $res->grade ) {
                                            $has_failed = true;
                                        }
                                        ?>
                                        <td>
                                            <strong><?php echo floatval( $res->obtained_marks ); ?></strong><br>
                                            <small style="color: <?php echo 'F' === $res->grade ? '#dc2626' : '#64748b'; ?>;">(<?php echo esc_html( $res->grade ); ?>)</small>
                                        </td>
                                    <?php } else { ?>
                                        <td style="color: #94a3b8;">-</td>
                                    <?php }
                                endforeach; 

                                $avg_gpa   = ( $sub_count > 0 ) ? ( $sum_gpa / $sub_count ) : 0;
                                $final_gpa = $has_failed ? '0.00' : number_format( $avg_gpa, 2 );
                                ?>

                                <td style="font-weight: 700;"><?php echo floatval( $total_obtained ); ?></td>
                                <td style="font-weight: 700; color: <?php echo $has_failed ? '#dc2626' : '#16a34a'; ?>;"><?php echo esc_html( $final_gpa ); ?></td>
                                <td>
                                    <span style="padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 11px; background: <?php echo $has_failed ? '#fef2f2' : '#f0fdf4'; ?>; color: <?php echo $has_failed ? '#dc2626' : '#16a34a'; ?>; border: 1px solid <?php echo $has_failed ? '#fecaca' : '#bbf7d0'; ?>;">
                                        <?php echo $has_failed ? esc_html__( 'FAIL', 'ifsedu-sms' ) : esc_html__( 'PASS', 'ifsedu-sms' ); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="afdp-sign-row">
                    <div class="afdp-sign-line"><?php esc_html_e( 'Prepared By', 'ifsedu-sms' ); ?></div>
                    <div class="afdp-sign-line"><?php esc_html_e( 'Checked By', 'ifsedu-sms' ); ?></div>
                    <div class="afdp-sign-line"><?php esc_html_e( 'Headmaster / Principal', 'ifsedu-sms' ); ?></div>
                </div>
            </div>
            <?php
        }
        ?>

    </div>
    <?php
}