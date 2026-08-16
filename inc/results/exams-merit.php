<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Enterprise Merit List & Position Roster Module
 * File: inc/results/exams-merit.php
 * Theme Aesthetic: Elite Neo-Bento UI
 * Custom Prefixes Applied: dpt-, afdp-
 */

// 1. AJAX Handler for Dynamic Section Loading based on Class
add_action( 'wp_ajax_educore_get_sections_by_class_merit', 'educore_get_sections_by_class_merit_handler' );
function educore_get_sections_by_class_merit_handler() {
    check_ajax_referer( 'educore_merit_nonce', 'security' );

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

function educore_merit_list_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Strict Security Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view academic merit rankings.', 'ifsedu-sms' ) );
    }

    // GET Filter Parameters Sanitization
    $filter_exam    = isset( $_GET['exam_id'] ) ? absint( $_GET['exam_id'] ) : 0;
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';

    $exams           = $wpdb->get_results( "SELECT id, exam_name, class_name FROM {$table_exams} ORDER BY id DESC" );
    $all_classes_raw = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );

    if ( ! empty( $all_classes_raw ) ) {
        usort( $all_classes_raw, 'strnatcasecmp' );
    }

    // Available sections for chosen class
    $available_sections = array();
    if ( ! empty( $filter_class ) ) {
        $available_sections = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT section_name FROM {$table_units} WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
            $filter_class
        ) );
    }

    $school_name = get_bloginfo( 'name' );
    if ( empty( $school_name ) || $school_name === 'WordPress' ) {
        $school_name = 'Green Gems International School & College';
    }
    
    $current_uri = remove_query_arg( array( 'status' ), $_SERVER['REQUEST_URI'] );
    $base_url    = esc_url_raw( $current_uri );
    $back_url    = add_query_arg( array( 'sub' => 'list' ), $base_url );
    ?>

    <style>
        .dpt-merit-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            margin: 20px 20px 30px 0;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Top Header Bar */
        .afdp-header-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        /* Bento Card Container */
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

        /* Filter Grid */
        .dpt-filter-grid {
            display: grid;
            grid-template-columns: 2fr 2fr 2fr 1.2fr;
            gap: 16px;
            align-items: flex-end;
        }

        @media (max-width: 900px) {
            .dpt-filter-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px) {
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

        /* Metric Bento Grid Matrix */
        .dpt-bento-grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .dpt-stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease;
        }

        .dpt-stat-card:hover { transform: translateY(-2px); }

        .dpt-stat-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }

        .dpt-stat-card.stat-top::before   { background: #eab308; }
        .dpt-stat-card.stat-avg::before   { background: #006a4e; }
        .dpt-stat-card.stat-pass::before  { background: #0284c7; }
        .dpt-stat-card.stat-rate::before  { background: #8b5cf6; }

        .dpt-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .dpt-stat-meta {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .dpt-stat-label {
            font-size: 11px;
            color: #64748b;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dpt-stat-value {
            font-size: 20px;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.3px;
        }

        /* Table & Roster Card */
        .afdp-tabulation-container {
            background: #ffffff;
            padding: 32px;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .afdp-tabulation-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .afdp-tabulation-table th, .afdp-tabulation-table td {
            border: 1px solid #cbd5e1;
            padding: 10px 12px;
            text-align: center;
            font-size: 13px;
            vertical-align: middle;
        }

        .afdp-tabulation-table th {
            background-color: #f8fafc;
            font-weight: 800;
            color: #334155;
            text-transform: uppercase;
            font-size: 11.5px;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .afdp-tabulation-table tbody tr:hover td {
            background-color: #f8fafc;
        }

        /* Rank Badges */
        .afdp-rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            letter-spacing: 0.2px;
        }

        .rank-gold {
            background: #fefce8;
            color: #854d0e;
            border: 1px solid #fef08a;
            box-shadow: 0 2px 6px rgba(234, 179, 8, 0.15);
        }

        .rank-silver {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        .rank-bronze {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #ffedd5;
        }

        .rank-norm {
            background: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .dpt-badge-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 11px;
            letter-spacing: 0.3px;
        }

        .status-pass {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }

        .status-fail {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
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

        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body * { visibility: hidden; }
            .afdp-tabulation-container, .afdp-tabulation-container * {
                visibility: visible;
            }
            .afdp-tabulation-container {
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

    <div class="dpt-merit-root">

        <!-- Top Navigation Header -->
        <div class="afdp-header-block no-print">
            <h2>
                <span class="dashicons dashicons-awards" style="color:#006a4e;"></span>
                <?php esc_html_e( 'Merit List & Position Ranking Roster', 'ifsedu-sms' ); ?>
            </h2>
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size:14px; width:14px; height:14px;"></span>
                <?php esc_html_e( 'Back to Exams Directory', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <!-- Merit List Filter Console Bento Card -->
        <div class="dpt-bento-card no-print">
            <div class="dpt-card-header">
                <h4 class="dpt-card-title">
                    <span class="dashicons dashicons-filter"></span>
                    <?php esc_html_e( 'Filter Examination & Academic Cohort', 'ifsedu-sms' ); ?>
                </h4>
            </div>

            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="educoreMeritFilterForm">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="results">
                <input type="hidden" name="sub" value="merit">

                <div class="dpt-filter-grid">
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '1. Select Exam', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '2. Class Name', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="educore_merit_class_select" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $all_classes_raw as $cls_name ) : ?>
                                <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                                    <?php echo esc_html( $cls_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '3. Section Filter', 'ifsedu-sms' ); ?></label>
                        <select name="section_name" id="educore_merit_section_select" class="dpt-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections (Entire Class) --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $available_sections as $sec_val ) : ?>
                                <option value="<?php echo esc_attr( $sec_val ); ?>" <?php selected( $filter_section, $sec_val ); ?>>
                                    <?php echo esc_html( $sec_val ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="dpt-btn-submit-trigger">
                            <span class="dashicons dashicons-analytics"></span>
                            <?php esc_html_e( 'Generate Roster', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( wp_create_nonce( "educore_merit_nonce" ) ); ?>';

            $('#educore_merit_class_select').on('change', function() {
                var selectedClass = $(this).val();
                var $secSelect = $('#educore_merit_section_select');

                $secSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Sections... --', 'ifsedu-sms' ) ); ?></option>');

                if (!selectedClass) {
                    $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections (Entire Class) --', 'ifsedu-sms' ) ); ?></option>');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'educore_get_sections_by_class_merit',
                        security: nonce,
                        class_name: selectedClass
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value=""><?php echo esc_js( __( '-- All Sections (Entire Class) --', 'ifsedu-sms' ) ); ?></option>';
                            $.each(response.data, function(i, sec) {
                                options += '<option value="' + sec + '">' + sec + '</option>';
                            });
                            $secSelect.html(options);
                        } else {
                            $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections (Entire Class) --', 'ifsedu-sms' ) ); ?></option>');
                        }
                    }
                });
            });
        });
        </script>

        <?php
        if ( $filter_exam > 0 && ! empty( $filter_class ) ) {
            $exam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_exams} WHERE id = %d", $filter_exam ) );

            // 1. Fetch ALL students of the class to determine Global Class Rank
            $all_class_students = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, full_name, student_id, roll_no, class_name, section_name 
                 FROM {$table_students} 
                 WHERE status = 'Active' AND class_name = %s 
                 ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                $filter_class
            ) );

            if ( ! empty( $all_class_students ) ) {
                $all_ranked_pool = array();

                foreach ( $all_class_students as $s ) {
                    $results = $wpdb->get_results( $wpdb->prepare(
                        "SELECT obtained_marks, grade, gpa FROM {$table_results} WHERE exam_id = %d AND student_id = %d",
                        $filter_exam, $s->id
                    ) );

                    if ( empty( $results ) ) {
                        continue;
                    }

                    $total_obt = 0;
                    $sum_gpa   = 0;
                    $sub_count = count( $results );
                    $has_fail  = false;

                    foreach ( $results as $res ) {
                        $total_obt += floatval( $res->obtained_marks );
                        $sum_gpa   += floatval( $res->gpa );
                        if ( strtoupper( trim( $res->grade ) ) === 'F' || floatval( $res->gpa ) <= 0 ) {
                            $has_fail = true;
                        }
                    }

                    $avg_gpa   = ( $sub_count > 0 ) ? ( $sum_gpa / $sub_count ) : 0;
                    $final_gpa = $has_fail ? 0.00 : round( $avg_gpa, 2 );

                    $all_ranked_pool[] = array(
                        'student'  => $s,
                        'total'    => $total_obt,
                        'gpa'      => $final_gpa,
                        'failed'   => $has_fail,
                        'section'  => $s->section_name ? trim( $s->section_name ) : ''
                    );
                }

                // Global Sort: Passed students first -> GPA (DESC) -> Total Score (DESC)
                usort( $all_ranked_pool, function( $a, $b ) {
                    if ( $a['failed'] !== $b['failed'] ) {
                        return $a['failed'] ? 1 : -1;
                    }
                    if ( $b['gpa'] != $a['gpa'] ) {
                        return ( $b['gpa'] < $a['gpa'] ) ? -1 : 1;
                    }
                    return ( $b['total'] < $a['total'] ) ? -1 : 1;
                });

                // Assign Global Class Positions & Section Positions
                $class_pos_counter = 1;
                $section_counters  = array();
                $display_roster    = array();

                $total_passed_count = 0;
                $sum_passed_gpa     = 0;
                $top_performer_name = '—';

                foreach ( $all_ranked_pool as $item ) {
                    $sec = $item['section'];
                    if ( ! isset( $section_counters[ $sec ] ) ) {
                        $section_counters[ $sec ] = 1;
                    }

                    if ( ! $item['failed'] ) {
                        $item['class_position']   = $class_pos_counter++;
                        $item['section_position'] = $section_counters[ $sec ]++;
                        
                        if ( empty( $filter_section ) || $sec === $filter_section ) {
                            $total_passed_count++;
                            $sum_passed_gpa += $item['gpa'];
                            if ( $top_performer_name === '—' ) {
                                $top_performer_name = $item['student']->full_name;
                            }
                        }
                    } else {
                        $item['class_position']   = 0;
                        $item['section_position'] = 0;
                    }

                    if ( empty( $filter_section ) || $sec === $filter_section ) {
                        $display_roster[] = $item;
                    }
                }

                $total_students_count = count( $display_roster );
                $pass_rate_pct = ( $total_students_count > 0 ) ? round( ( $total_passed_count / $total_students_count ) * 100, 1 ) : 0;
                $cohort_avg_gpa = ( $total_passed_count > 0 ) ? number_format( $sum_passed_gpa / $total_passed_count, 2 ) : '0.00';

                if ( ! empty( $display_roster ) ) :
                ?>

                <!-- Summary Metrics Bento Grid -->
                <div class="dpt-bento-grid-stats no-print">
                    <div class="dpt-stat-card stat-top">
                        <div class="dpt-stat-icon" style="background: #fefce8; color: #eab308;">
                            <span class="dashicons dashicons-star-filled" style="font-size:24px; width:24px; height:24px;"></span>
                        </div>
                        <div class="dpt-stat-meta">
                            <span class="dpt-stat-label"><?php esc_html_e( 'Top Performer', 'ifsedu-sms' ); ?></span>
                            <span class="dpt-stat-value" style="color: #854d0e; font-size:16px;"><?php echo esc_html( $top_performer_name ); ?></span>
                        </div>
                    </div>

                    <div class="dpt-stat-card stat-avg">
                        <div class="dpt-stat-icon" style="background: #ecfdf5; color: #006a4e;">
                            <span class="dashicons dashicons-chart-bar" style="font-size:24px; width:24px; height:24px;"></span>
                        </div>
                        <div class="dpt-stat-meta">
                            <span class="dpt-stat-label"><?php esc_html_e( 'Class Avg. GPA', 'ifsedu-sms' ); ?></span>
                            <span class="dpt-stat-value" style="color: #006a4e;"><?php echo esc_html( $cohort_avg_gpa ); ?></span>
                        </div>
                    </div>

                    <div class="dpt-stat-card stat-pass">
                        <div class="dpt-stat-icon" style="background: #f0f9ff; color: #0284c7;">
                            <span class="dashicons dashicons-groups" style="font-size:24px; width:24px; height:24px;"></span>
                        </div>
                        <div class="dpt-stat-meta">
                            <span class="dpt-stat-label"><?php esc_html_e( 'Passed Students', 'ifsedu-sms' ); ?></span>
                            <span class="dpt-stat-value" style="color: #0284c7;"><?php echo esc_html( $total_passed_count . ' / ' . $total_students_count ); ?></span>
                        </div>
                    </div>

                    <div class="dpt-stat-card stat-rate">
                        <div class="dpt-stat-icon" style="background: #f5f3ff; color: #8b5cf6;">
                            <span class="dashicons dashicons-saved" style="font-size:24px; width:24px; height:24px;"></span>
                        </div>
                        <div class="dpt-stat-meta">
                            <span class="dpt-stat-label"><?php esc_html_e( 'Pass Percentage', 'ifsedu-sms' ); ?></span>
                            <span class="dpt-stat-value" style="color: #6d28d9;"><?php echo esc_html( $pass_rate_pct ); ?>%</span>
                        </div>
                    </div>
                </div>

                <div style="text-align: center; margin-bottom: 20px;" class="no-print">
                    <button onclick="window.print();" class="dpt-btn-submit-trigger" style="width: auto; padding: 0 32px; font-size: 14px;">
                        <span class="dashicons dashicons-printer"></span>
                        <?php esc_html_e( 'Print Official Merit Position Roster', 'ifsedu-sms' ); ?>
                    </button>
                </div>

                <!-- Printable Official Roster Card -->
                <div class="afdp-tabulation-container">
                    <div style="text-align: center; margin-bottom: 24px; border-bottom: 2px solid #006a4e; padding-bottom: 16px;">
                        <h3 style="margin: 0; font-weight: 800; color: #006a4e; text-transform: uppercase; font-size: 20px;"><?php echo esc_html( $school_name ); ?></h3>
                        <h5 style="margin: 6px 0 0 0; font-weight: 700; color: #1e293b; font-size: 14px;"><?php echo esc_html( $exam->exam_name ); ?> &mdash; <?php esc_html_e( 'Official Merit Ranking & Position Roster', 'ifsedu-sms' ); ?></h5>
                        <span style="display: inline-block; background: #f1f5f9; color: #475569; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-top: 8px; border: 1px solid #cbd5e1;">
                            <?php esc_html_e( 'Class:', 'ifsedu-sms' ); ?> <?php echo esc_html( $filter_class ); ?>
                            <?php if ( ! empty( $filter_section ) ) : ?>
                                &nbsp;|&nbsp; <?php esc_html_e( 'Section:', 'ifsedu-sms' ); ?> <?php echo esc_html( $filter_section ); ?>
                            <?php else : ?>
                                &nbsp;|&nbsp; <?php esc_html_e( 'All Sections Combined', 'ifsedu-sms' ); ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="afdp-tabulation-table">
                            <thead>
                                <tr>
                                    <th style="width: 10%;"><?php esc_html_e( 'Class Rank', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 10%;"><?php esc_html_e( 'Sec. Rank', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 8%;"><?php esc_html_e( 'Roll', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 13%;"><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                                    <th style="text-align: left;"><?php esc_html_e( 'Student Full Name', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 10%;"><?php esc_html_e( 'Section', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 12%;"><?php esc_html_e( 'Total Score', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 10%;"><?php esc_html_e( 'GPA', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 11%;"><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ( $display_roster as $item ) : 
                                    $s = $item['student'];
                                    $c_pos = $item['class_position'];
                                    $s_pos = $item['section_position'];

                                    // Rank Badge Styling Logic
                                    $rank_class = 'rank-norm';
                                    if ( $c_pos === 1 ) $rank_class = 'rank-gold';
                                    elseif ( $c_pos === 2 ) $rank_class = 'rank-silver';
                                    elseif ( $c_pos === 3 ) $rank_class = 'rank-bronze';
                                ?>
                                <tr>
                                    <!-- Class Position -->
                                    <td>
                                        <?php if ( ! $item['failed'] && $c_pos > 0 ) : ?>
                                            <span class="afdp-rank-badge <?php echo esc_attr( $rank_class ); ?>">
                                                <?php if ( $c_pos === 1 ) : ?>🏆<?php endif; ?>
                                                #<?php echo esc_html( $c_pos ); ?>
                                            </span>
                                        <?php else : ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Section Position -->
                                    <td>
                                        <?php if ( ! $item['failed'] && $s_pos > 0 ) : ?>
                                            <span class="afdp-rank-badge <?php echo ( $s_pos === 1 ) ? 'rank-gold' : 'rank-norm'; ?>">
                                                #<?php echo esc_html( $s_pos ); ?>
                                            </span>
                                        <?php else : ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><strong>#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                    <td><code><?php echo esc_html( $s->student_id ); ?></code></td>
                                    <td style="text-align: left; font-weight: 700; color: #0f172a;"><?php echo esc_html( $s->full_name ); ?></td>
                                    <td><span style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 12px;"><?php echo esc_html( ! empty( $s->section_name ) ? $s->section_name : 'N/A' ); ?></span></td>
                                    <td><strong><?php echo floatval( $item['total'] ); ?></strong></td>
                                    <td style="font-weight: 800; color: <?php echo $item['failed'] ? '#dc2626' : '#006a4e'; ?>;"><?php echo number_format( floatval( $item['gpa'] ), 2 ); ?></td>
                                    <td>
                                        <span class="dpt-badge-status <?php echo $item['failed'] ? 'status-fail' : 'status-pass'; ?>">
                                            <?php echo $item['failed'] ? esc_html__( 'FAIL', 'ifsedu-sms' ) : esc_html__( 'PASS', 'ifsedu-sms' ); ?>
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
                else :
                    echo '<div class="afdp-status-banner">' . esc_html__( 'No published exam results found for students in the selected section.', 'ifsedu-sms' ) . '</div>';
                endif;

            } else {
                echo '<div class="afdp-status-banner">' . esc_html__( 'No active students found in this class.', 'ifsedu-sms' ) . '</div>';
            }
        }
        ?>

    </div>
    <?php
}