<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * High-End Academic Progress Marksheet & Tabulation Engine
 * File: inc/results/exams-report.php
 * Custom Prefixes Applied: dpt-, afdp-
 * Architecture: Neo-Bento Interface with Print-Ready Layouts & Security Controls
 */

// 1. AJAX Handler for Dynamic Section Loading based on Class
add_action( 'wp_ajax_educore_get_sections_by_class', 'educore_get_sections_by_class_report_handler' );
function educore_get_sections_by_class_report_handler() {
    check_ajax_referer( 'educore_report_nonce', 'security' );

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff     = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author' ) );
    }

    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    if ( ! $is_admin && ! $is_staff ) {
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
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ifsedu-sms' ) ) );
    }

    $table_units = $wpdb->prefix . 'sms_academic_units';
    $class_name  = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $clean_class = trim( str_ireplace( 'Class ', '', $class_name ) );

    $sections = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT section_name FROM {$table_units} WHERE (class_name = %s OR class_name = %s) AND section_name != '' ORDER BY section_name ASC",
        $class_name,
        $clean_class
    ) );

    wp_send_json_success( $sections );
}

// 2. AJAX Handler for Dynamic Student Fetching based on Class & Section
add_action( 'wp_ajax_educore_get_students_by_class', 'educore_get_students_by_class_handler' );
function educore_get_students_by_class_handler() {
    check_ajax_referer( 'educore_report_nonce', 'security' );

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff     = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author' ) );
    }

    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    if ( ! $is_admin && ! $is_staff ) {
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
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ifsedu-sms' ) ) );
    }

    $table_students = $wpdb->prefix . 'sms_students';
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name   = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $clean_class = trim( str_ireplace( 'Class ', '', $class_name ) );

    $sql = "SELECT id, full_name, student_id, roll_no FROM {$table_students} WHERE status = 'Active' AND (class_name = %s OR class_name = %s)";
    $params = array( $class_name, $clean_class );

    if ( ! empty( $section_name ) ) {
        $sql .= " AND section_name = %s";
        $params[] = $section_name;
    }

    $sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";

    $students = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

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
    $current_user = wp_get_current_user();

    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_staff    = $wpdb->prefix . 'sms_staff';

    // 1. Procedural Security Validation
    $is_admin = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author' ) );
    }

    if ( ! $is_admin && ! $is_staff ) {
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
        wp_die( esc_html__( 'You do not have sufficient permissions to generate academic reports.', 'ifsedu-sms' ) );
    }

    // Dynamic Base URL preservation
    $current_uri = remove_query_arg( array( 'status' ), $_SERVER['REQUEST_URI'] );
    $base_url    = esc_url_raw( $current_uri );

    // Fetch Exams along with their associated class details
    $exams = $wpdb->get_results( "SELECT id, exam_name, class_name FROM {$table_exams} ORDER BY id DESC" );

    // Build Exam-to-Classes Map
    $exam_class_map = array();
    foreach ( $exams as $ex_item ) {
        $exam_class_map[ $ex_item->id ] = array();
        if ( ! empty( $ex_item->class_name ) ) {
            $classes_array = array_map( 'trim', explode( ',', $ex_item->class_name ) );
            $exam_class_map[ $ex_item->id ] = array_filter( $classes_array );
        }
    }

    // Global classes fallback
    $all_classes_raw = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    if ( ! empty( $all_classes_raw ) ) {
        $all_classes_raw = array_values( array_unique( $all_classes_raw ) );
        usort( $all_classes_raw, 'strnatcasecmp' );
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
        $clean_class = trim( str_ireplace( 'Class ', '', $filter_class ) );
        $available_sections = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT section_name FROM {$table_units} WHERE (class_name = %s OR class_name = %s) AND section_name != '' ORDER BY section_name ASC",
            $filter_class,
            $clean_class
        ) );
    }

    $school_name = get_bloginfo( 'name' );
    if ( empty( $school_name ) || $school_name === 'WordPress' ) {
        $school_name = 'Green Gems International School & College';
    }

    $back_url = add_query_arg( array( 'sub' => 'marks' ), $base_url );
    ?>

    <style>
        .dpt-report-root {
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
            grid-template-columns: 2fr 1.5fr 1.5fr 1.5fr 2fr 1.2fr;
            gap: 16px;
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
            text-decoration: none;
        }

        .dpt-btn-submit-trigger:hover {
            background: #00523c;
            color: #ffffff;
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

        /* Printable Report Card Design */
        .afdp-report-card-container {
            max-width: 860px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            color: #0f172a;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .afdp-report-header {
            text-align: center;
            border-bottom: 3px solid #006a4e;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .afdp-grading-legend-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        .afdp-grading-legend-table th, .afdp-grading-legend-table td {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            text-align: center;
        }
        .afdp-grading-legend-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
        }

        .afdp-marks-table, .afdp-tabulation-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .afdp-marks-table th, .afdp-marks-table td,
        .afdp-tabulation-table th, .afdp-tabulation-table td {
            border: 1px solid #cbd5e1;
            padding: 9px 10px;
            text-align: center;
            font-size: 13px;
        }

        .afdp-marks-table th, .afdp-tabulation-table th {
            background-color: #f8fafc;
            font-weight: 800;
            color: #334155;
            text-transform: uppercase;
            font-size: 11.5px;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .afdp-gpa-box {
            border: 2px solid #006a4e;
            padding: 16px;
            text-align: center;
            border-radius: 12px;
            margin-top: 20px;
            background: #f0fdf4;
        }

        .afdp-sign-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 50px;
            padding-top: 16px;
        }

        .afdp-sign-line {
            border-top: 1.5px dashed #64748b;
            width: 180px;
            text-align: center;
            font-size: 11.5px;
            font-weight: 700;
            padding-top: 8px;
            color: #334155;
            text-transform: uppercase;
        }

        .afdp-tabulation-container {
            background: #ffffff;
            padding: 32px;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .afdp-status-banner {
            background: #fffbe0;
            border: 1px solid #fef3c7;
            border-radius: 10px;
            padding: 16px;
            color: #b45309;
            font-weight: 700;
            font-size: 14px;
            text-align: center;
            margin-bottom: 24px;
        }

        .dpt-tabulation-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 20px;
            background: #ffffff;
            -webkit-overflow-scrolling: touch;
        }

        .dpt-tabulation-scroll-wrapper::-webkit-scrollbar {
            height: 8px;
        }

        .dpt-tabulation-scroll-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .dpt-tabulation-scroll-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .dpt-tabulation-scroll-wrapper::-webkit-scrollbar-thumb:hover {
            background: #006a4e;
        }

        .dpt-tabulation-scroll-wrapper .afdp-tabulation-table {
            margin-bottom: 0;
            border: none;
            min-width: 950px;
        }

        .dpt-tabulation-scroll-wrapper .afdp-tabulation-table th:first-child,
        .dpt-tabulation-scroll-wrapper .afdp-tabulation-table td:first-child {
            border-left: none;
        }

        .dpt-tabulation-scroll-wrapper .afdp-tabulation-table th:last-child,
        .dpt-tabulation-scroll-wrapper .afdp-tabulation-table td:last-child {
            border-right: none;
        }

        @media print {
            @page { size: A4 landscape; margin: 10mm; }
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
            .dpt-tabulation-scroll-wrapper {
                overflow: visible !important;
                border: none !important;
            }
            .dpt-tabulation-scroll-wrapper .afdp-tabulation-table {
                min-width: 100% !important;
            }
            .no-print { display: none !important; }
        }
    </style>

    <div class="dpt-report-root">
        
        <!-- Header Block -->
        <div class="afdp-header-block no-print">
            <h2>
                <span class="dashicons dashicons-clipboard" style="color:#006a4e;"></span>
                <?php esc_html_e( 'Academic Progress Marksheet & Tabulation Engine', 'ifsedu-sms' ); ?>
            </h2>
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size:14px; width:14px; height:14px;"></span>
                <?php esc_html_e( 'Back to Marks Entry', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <!-- Generator Control Bento Card -->
        <div class="dpt-bento-card no-print">
            <form method="GET" action="<?php echo esc_url( $base_url ); ?>" id="educoreReportFilterForm">
                <?php 
                $parsed_url = wp_parse_url( $base_url );
                if ( isset( $parsed_url['query'] ) ) {
                    parse_str( $parsed_url['query'], $query_params );
                    foreach ( $query_params as $param_key => $param_val ) {
                        if ( ! in_array( $param_key, array( 'exam_id', 'report_type', 'class_name', 'section_name', 'student_id' ), true ) ) {
                            echo '<input type="hidden" name="' . esc_attr( $param_key ) . '" value="' . esc_attr( $param_val ) . '">';
                        }
                    }
                }
                ?>
                
                <div class="dpt-filter-grid">
                    <!-- 1. Exam Selection -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '1. Select Exam', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" id="educore_report_exam_select" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Report Type -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '2. Report Type', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="report_type" id="educore_report_type" class="dpt-select-field" required>
                            <option value="individual" <?php selected( $report_type, 'individual' ); ?>><?php esc_html_e( 'Student Marksheet', 'ifsedu-sms' ); ?></option>
                            <option value="tabulation" <?php selected( $report_type, 'tabulation' ); ?>><?php esc_html_e( 'Class Tabulation Sheet', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- 3. Class Selection -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '3. Exam Class', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="educore_class_filter" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Select Exam First --', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- 4. Section Selection -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '4. Section', 'ifsedu-sms' ); ?></label>
                        <select name="section_name" id="educore_section_filter" class="dpt-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $available_sections as $sec_val ) : ?>
                                <option value="<?php echo esc_attr( $sec_val ); ?>" <?php selected( $filter_section, $sec_val ); ?>>
                                    <?php echo esc_html( $sec_val ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 5. Student Selection -->
                    <div class="dpt-form-group" id="student_select_box" style="<?php echo ( 'tabulation' === $report_type ) ? 'display:none;' : ''; ?>">
                        <label class="dpt-form-label"><?php esc_html_e( '5. Target Student', 'ifsedu-sms' ); ?></label>
                        <select name="student_id" id="educore_student_filter" class="dpt-select-field">
                            <option value=""><?php esc_html_e( '-- Choose Student --', 'ifsedu-sms' ); ?></option>
                            <?php 
                            if ( ! empty( $filter_class ) ) {
                                $clean_class = trim( str_ireplace( 'Class ', '', $filter_class ) );
                                $sql = "SELECT id, full_name, student_id, roll_no FROM {$table_students} WHERE status = 'Active' AND (class_name = %s OR class_name = %s)";
                                $params = array( $filter_class, $clean_class );

                                if ( ! empty( $filter_section ) ) {
                                    $sql .= " AND section_name = %s";
                                    $params[] = $filter_section;
                                }

                                $sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
                                $student_list = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

                                if ( ! empty( $student_list ) ) {
                                    foreach ( $student_list as $s ) : ?>
                                        <option value="<?php echo intval( $s->id ); ?>" <?php selected( $filter_student, $s->id ); ?>>
                                            <?php printf( esc_html__( 'Roll %1$s: %2$s (%3$s)', 'ifsedu-sms' ), esc_html( $s->roll_no ), esc_html( $s->full_name ), esc_html( $s->student_id ) ); ?>
                                        </option>
                                    <?php endforeach;
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <!-- 6. Submit Button -->
                    <div>
                        <button type="submit" class="dpt-btn-submit-trigger">
                            <span class="dashicons dashicons-analytics"></span>
                            <?php esc_html_e( 'Generate', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Dynamic Dropdown AJAX Controller Script -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce          = '<?php echo esc_js( wp_create_nonce( "educore_report_nonce" ) ); ?>';
            var examClassMap   = <?php echo wp_json_encode( ! empty( $exam_class_map ) ? $exam_class_map : array() ); ?>;
            var allClasses     = <?php echo wp_json_encode( ! empty( $all_classes_raw ) ? $all_classes_raw : array() ); ?>;
            var currentClass   = "<?php echo esc_js( $filter_class ); ?>";
            var currentSection = "<?php echo esc_js( $filter_section ); ?>";

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

            function populateExamClasses(examId, selectedClass) {
                var $classSelect = $('#educore_class_filter');
                $classSelect.html('<option value=""><?php echo esc_js( __( '-- Select Class --', 'ifsedu-sms' ) ); ?></option>');

                if (!examId) {
                    $classSelect.html('<option value=""><?php echo esc_js( __( '-- Select Exam First --', 'ifsedu-sms' ) ); ?></option>');
                    $('#educore_section_filter').html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>');
                    $('#educore_student_filter').html('<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-sms' ) ); ?></option>');
                    return;
                }

                var classesToLoad = (examClassMap[examId] && examClassMap[examId].length > 0) ? examClassMap[examId] : allClasses;

                $.each(classesToLoad, function(i, cls) {
                    var sel = (cls === selectedClass) ? 'selected' : '';
                    var displayCls = (/^class\s+/i.test(cls)) ? cls : 'Class ' + cls;
                    $classSelect.append('<option value="' + cls + '" ' + sel + '>' + displayCls + '</option>');
                });
            }

            $('#educore_report_exam_select').on('change', function() {
                populateExamClasses($(this).val(), '');
                $('#educore_class_filter').trigger('change');
            });

            $('#educore_class_filter').on('change', function() {
                var selectedClass  = $(this).val();
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
                                var sel = (sec === currentSection) ? 'selected' : '';
                                secOptions += '<option value="' + sec + '" ' + sel + '>' + sec + '</option>';
                            });
                            $sectionSelect.html(secOptions);
                        }
                        reloadStudents();
                    }
                });
            });

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
                    }
                });
            }

            if ($('#educore_report_exam_select').val()) {
                populateExamClasses($('#educore_report_exam_select').val(), currentClass);
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
                echo '</div>';
                return;
            }

            $total_sub          = count( $results );
            $sum_gpa            = 0;
            $total_marks_all    = 0;
            $obtained_marks_all = 0;
            $has_failed         = false;

            foreach ( $results as $r ) {
                $sum_gpa            += floatval( $r->gpa );
                $total_marks_all    += floatval( $r->total_marks );
                $obtained_marks_all += floatval( $r->obtained_marks );
                if ( strtoupper( trim( $r->grade ) ) === 'F' || floatval( $r->gpa ) <= 0 ) {
                    $has_failed = true;
                }
            }

            $avg_gpa     = ( $total_sub > 0 ) ? ( $sum_gpa / $total_sub ) : 0;
            $final_gpa   = $has_failed ? '0.00' : number_format( $avg_gpa, 2 );
            
            // Derive Final Grade
            $final_grade = 'F';
            if ( ! $has_failed ) {
                if ( $avg_gpa >= 5.0 ) $final_grade = 'A+';
                elseif ( $avg_gpa >= 4.0 ) $final_grade = 'A';
                elseif ( $avg_gpa >= 3.5 ) $final_grade = 'A-';
                elseif ( $avg_gpa >= 3.0 ) $final_grade = 'B';
                elseif ( $avg_gpa >= 2.0 ) $final_grade = 'C';
                elseif ( $avg_gpa >= 1.0 ) $final_grade = 'D';
            }
            ?>

            <div style="text-align: center; margin-bottom: 24px;" class="no-print">
                <button onclick="window.print();" class="dpt-btn-submit-trigger" style="width: auto; padding: 0 32px; font-size: 14px;">
                    <span class="dashicons dashicons-printer"></span>
                    <?php esc_html_e( 'Print Professional Marksheet', 'ifsedu-sms' ); ?>
                </button>
            </div>

            <div class="afdp-report-card-container">
                <div class="afdp-report-header">
                    <div style="font-size: 11px; font-weight: 800; color: #64748b; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 4px;"><?php esc_html_e( 'Official Academic Transcript', 'ifsedu-sms' ); ?></div>
                    <h2 style="margin: 0; font-weight: 800; color: #006a4e; text-transform: uppercase; font-size: 22px; letter-spacing: 0.5px;"><?php echo esc_html( $school_name ); ?></h2>
                    <h4 style="margin: 6px 0 4px 0; font-weight: 700; color: #1e293b; font-size: 15px;"><?php echo esc_html( $exam->exam_name ); ?></h4>
                </div>

                <!-- Grading Scale Reference -->
                <table class="afdp-grading-legend-table">
                    <thead>
                        <tr>
                            <th>Marks</th>
                            <th>80-100%</th>
                            <th>70-79%</th>
                            <th>60-69%</th>
                            <th>50-59%</th>
                            <th>40-49%</th>
                            <th>33-39%</th>
                            <th>0-32%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Grade / GP</strong></td>
                            <td>A+ (5.00)</td>
                            <td>A (4.00)</td>
                            <td>A- (3.50)</td>
                            <td>B (3.00)</td>
                            <td>C (2.00)</td>
                            <td>D (1.00)</td>
                            <td>F (0.00)</td>
                        </tr>
                    </tbody>
                </table>

                <div style="display: flex; justify-content: space-between; background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px 20px; border-radius: 10px; margin-bottom: 24px; font-size: 13px; line-height: 1.6;">
                    <div>
                        <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Student Name:', 'ifsedu-sms' ); ?></strong> <span style="text-transform: uppercase; font-weight: 800; color:#0f172a;"><?php echo esc_html( $student->full_name ); ?></span></p>
                        <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Student ID:', 'ifsedu-sms' ); ?></strong> <code><?php echo esc_html( $student->student_id ); ?></code></p>
                        <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Guardian:', 'ifsedu-sms' ); ?></strong> <?php echo esc_html( ! empty( $student->guardian_name ) ? $student->guardian_name : $student->father_name ); ?></p>
                    </div>
                    <div style="text-align: right;">
                        <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Class:', 'ifsedu-sms' ); ?></strong> <?php echo esc_html( $student->class_name ); ?></p>
                        <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Section:', 'ifsedu-sms' ); ?></strong> <?php echo esc_html( ! empty( $student->section_name ) ? $student->section_name : __( 'N/A', 'ifsedu-sms' ) ); ?></p>
                        <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Roll Number:', 'ifsedu-sms' ); ?></strong> <span style="background: #ffffff; border: 1px solid #cbd5e1; padding: 2px 8px; border-radius: 4px; font-weight: 800;">#<?php echo esc_html( $student->roll_no ); ?></span></p>
                    </div>
                </div>

                <table class="afdp-marks-table">
                    <thead>
                        <tr>
                            <th style="text-align: left; width: 32%;"><?php esc_html_e( 'Subject Name', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Full Marks', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'CQ', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'MCQ', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'PR', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Obtained', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Grade', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'GP', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $results as $r ) : 
                            $row_failed = ( 'F' === strtoupper( trim( $r->grade ) ) || floatval( $r->gpa ) <= 0 );
                        ?>
                        <tr>
                            <td style="text-align: left; font-weight: 700; color: #0f172a;"><?php echo esc_html( $r->subject_name ); ?></td>
                            <td><?php echo floatval( $r->total_marks ); ?></td>
                            <td><?php echo isset( $r->cq_marks ) ? floatval( $r->cq_marks ) : '—'; ?></td>
                            <td><?php echo isset( $r->mcq_marks ) ? floatval( $r->mcq_marks ) : '—'; ?></td>
                            <td><?php echo isset( $r->practical_marks ) ? floatval( $r->practical_marks ) : '—'; ?></td>
                            <td><strong><?php echo floatval( $r->obtained_marks ); ?></strong></td>
                            <td style="font-weight: 800; color: <?php echo $row_failed ? '#dc2626' : '#059669'; ?>;"><?php echo esc_html( $r->grade ); ?></td>
                            <td><strong style="color: <?php echo $row_failed ? '#dc2626' : '#2563eb'; ?>;"><?php echo number_format( floatval( $r->gpa ), 2 ); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="afdp-gpa-box">
                    <h4 style="margin: 0; font-weight: 800; color: #006a4e; text-transform: uppercase; font-size: 14px; letter-spacing: 0.5px;"><?php esc_html_e( 'Final Result Summary', 'ifsedu-sms' ); ?></h4>
                    <p style="font-size: 14.5px; margin: 6px 0 0 0; color: #1e293b;">
                        <?php esc_html_e( 'Status:', 'ifsedu-sms' ); ?> 
                        <strong style="color: <?php echo $has_failed ? '#dc2626' : '#059669'; ?>;">
                            <?php echo $has_failed ? esc_html__( 'FAILED (F)', 'ifsedu-sms' ) : sprintf( esc_html__( 'PASSED (%s)', 'ifsedu-sms' ), esc_html( $final_grade ) ); ?>
                        </strong> &nbsp;|&nbsp; 
                        <?php esc_html_e( 'Total Score:', 'ifsedu-sms' ); ?> <strong><?php echo floatval( $obtained_marks_all ); ?> / <?php echo floatval( $total_marks_all ); ?></strong> &nbsp;|&nbsp;
                        <?php esc_html_e( 'GPA:', 'ifsedu-sms' ); ?> <strong style="font-size: 16px; color: #006a4e;"><?php echo esc_html( $final_gpa ); ?></strong>
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
            $clean_class = trim( str_ireplace( 'Class ', '', $filter_class ) );
            
            $sql = "SELECT * FROM {$table_students} WHERE status = 'Active' AND (class_name = %s OR class_name = %s)";
            $params = array( $filter_class, $clean_class );

            if ( ! empty( $filter_section ) ) {
                $sql .= " AND section_name = %s";
                $params[] = $filter_section;
            }

            $sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
            $students = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

            $subjects = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT subject_name FROM {$table_results} WHERE exam_id = %d AND (class_name = %s OR class_name = %s) ORDER BY subject_name ASC", $filter_exam, $filter_class, $clean_class ) );

            if ( ! $students || ! $subjects ) {
                $sec_label = ! empty( $filter_section ) ? ' (' . esc_html( $filter_section ) . ')' : '';
                $empty_tab_notice = sprintf(
                    esc_html__( 'No evaluated marks or subject entries found for %1$s%2$s in this exam.', 'ifsedu-sms' ),
                    '<strong>' . esc_html( $filter_class ) . '</strong>',
                    '<strong>' . esc_html( $sec_label ) . '</strong>'
                );
                echo '<div class="afdp-status-banner no-print">' . wp_kses_post( $empty_tab_notice ) . '</div>';
                echo '</div>';
                return;
            }
            ?>

            <div style="text-align: center; margin-bottom: 24px;" class="no-print">
                <button onclick="window.print();" class="dpt-btn-submit-trigger" style="width: auto; padding: 0 32px; font-size: 14px;">
                    <span class="dashicons dashicons-printer"></span>
                    <?php esc_html_e( 'Print Class Tabulation Sheet', 'ifsedu-sms' ); ?>
                </button>
            </div>

            <div class="afdp-tabulation-container">
                <div style="text-align: center; margin-bottom: 24px; border-bottom: 2px solid #006a4e; padding-bottom: 16px;">
                    <h3 style="margin: 0; font-weight: 800; color: #006a4e; text-transform: uppercase; font-size: 20px;"><?php echo esc_html( $school_name ); ?></h3>
                    <h5 style="margin: 6px 0 0 0; font-weight: 700; color: #1e293b; font-size: 14px;"><?php echo esc_html( $exam->exam_name ); ?> &mdash; <?php esc_html_e( 'Academic Tabulation Sheet', 'ifsedu-sms' ); ?></h5>
                    <span style="display: inline-block; background: #f1f5f9; color: #475569; padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-top: 6px; border: 1px solid #cbd5e1;">
                        <?php echo esc_html( preg_match('/^class\s+/i', $filter_class) ? $filter_class : 'Class ' . $filter_class ); ?>
                        <?php if ( ! empty( $filter_section ) ) : ?>
                            (<?php esc_html_e( 'Section:', 'ifsedu-sms' ); ?> <?php echo esc_html( $filter_section ); ?>)
                        <?php endif; ?>
                    </span>
                </div>

                <!-- Horizontal Scrollbar Wrapper -->
                <div class="dpt-tabulation-scroll-wrapper">
                    <table class="afdp-tabulation-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><?php esc_html_e( 'Roll', 'ifsedu-sms' ); ?></th>
                                <th style="width: 90px;"><?php esc_html_e( 'ID', 'ifsedu-sms' ); ?></th>
                                <th style="min-width: 160px; text-align: left;"><?php esc_html_e( 'Student Name', 'ifsedu-sms' ); ?></th>
                                <?php foreach ( $subjects as $sub ) : ?>
                                    <th style="min-width: 110px;"><?php echo esc_html( $sub ); ?></th>
                                <?php endforeach; ?>
                                <th style="min-width: 85px;"><?php esc_html_e( 'Total Score', 'ifsedu-sms' ); ?></th>
                                <th style="min-width: 70px;"><?php esc_html_e( 'GPA', 'ifsedu-sms' ); ?></th>
                                <th style="min-width: 75px;"><?php esc_html_e( 'Result', 'ifsedu-sms' ); ?></th>
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
                                <td><strong style="color: #0f172a;">#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                <td><code><?php echo esc_html( $s->student_id ); ?></code></td>
                                <td style="text-align: left; font-weight: 700; color: #0f172a; white-space: nowrap;"><?php echo esc_html( $s->full_name ); ?></td>
                                
                                <?php foreach ( $subjects as $sub ) : 
                                    if ( isset( $student_results[ $sub ] ) ) {
                                        $res = $student_results[ $sub ];
                                        $total_obtained += floatval( $res->obtained_marks );
                                        $sum_gpa        += floatval( $res->gpa );
                                        $sub_count++;
                                        if ( 'F' === strtoupper( trim( $res->grade ) ) || floatval( $res->gpa ) <= 0 ) {
                                            $has_failed = true;
                                        }
                                        $sub_failed = ( 'F' === strtoupper( trim( $res->grade ) ) || floatval( $res->gpa ) <= 0 );
                                        ?>
                                        <td>
                                            <strong><?php echo floatval( $res->obtained_marks ); ?></strong><br>
                                            <small style="font-weight: 700; color: <?php echo $sub_failed ? '#dc2626' : '#059669'; ?>;">(<?php echo esc_html( $res->grade ); ?>)</small>
                                        </td>
                                    <?php } else { ?>
                                        <td style="color: #94a3b8;">—</td>
                                    <?php }
                                endforeach; 

                                $avg_gpa   = ( $sub_count > 0 ) ? ( $sum_gpa / $sub_count ) : 0;
                                $final_gpa = $has_failed ? '0.00' : number_format( $avg_gpa, 2 );
                                ?>

                                <td style="font-weight: 800; color:#0f172a;"><?php echo floatval( $total_obtained ); ?></td>
                                <td style="font-weight: 800; color: <?php echo $has_failed ? '#dc2626' : '#006a4e'; ?>;"><?php echo esc_html( $final_gpa ); ?></td>
                                <td>
                                    <span style="padding: 3px 10px; border-radius: 20px; font-weight: 800; font-size: 11px; background: <?php echo $has_failed ? '#fef2f2' : '#ecfdf5'; ?>; color: <?php echo $has_failed ? '#dc2626' : '#059669'; ?>; border: 1px solid <?php echo $has_failed ? '#fecaca' : '#a7f3d0'; ?>;">
                                        <?php echo $has_failed ? esc_html__( 'FAIL', 'ifsedu-sms' ) : esc_html__( 'PASS', 'ifsedu-sms' ); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="afdp-sign-row">
                    <div class="afdp-sign-line"><?php esc_html_e( 'Tabulator Signature', 'ifsedu-sms' ); ?></div>
                    <div class="afdp-sign-line"><?php esc_html_e( 'Exam Controller', 'ifsedu-sms' ); ?></div>
                    <div class="afdp-sign-line"><?php esc_html_e( 'Headmaster / Principal', 'ifsedu-sms' ); ?></div>
                </div>
            </div>
            <?php
        }
        ?>

    </div>
    <?php
}