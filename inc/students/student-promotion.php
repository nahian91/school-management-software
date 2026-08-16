<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Academic Student Promotion & Roll Re-assignment Engine
 * File: inc/students/students-promotion.php
 * Custom Prefixes Applied: dpt-, afdp-
 */

// 1. AJAX Handler to dynamically fetch Target Sections
add_action( 'wp_ajax_educore_get_target_sections_promotion', 'educore_get_target_sections_promotion_handler' );
function educore_get_target_sections_promotion_handler() {
    check_ajax_referer( 'educore_promotion_nonce', 'security' );

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

function educore_student_promotion_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to promote students.', 'ifsedu-sms' ) );
    }

    $current_uri = remove_query_arg( array( 'status', 'msg' ), $_SERVER['REQUEST_URI'] );
    $base_url    = esc_url_raw( $current_uri );
    $notice_msg  = '';

    // --------------------------------------------------------------------------
    // 1. BULK PROMOTION EXECUTION ENGINE
    // --------------------------------------------------------------------------
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['educore_execute_promotion'] ) ) {
        if ( isset( $_POST['educore_promotion_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_promotion_nonce'] ) ), 'execute_promotion_action' ) ) {
            $target_class   = isset( $_POST['target_class'] ) ? sanitize_text_field( wp_unslash( $_POST['target_class'] ) ) : '';
            $selected_stids = isset( $_POST['promote_student'] ) && is_array( $_POST['promote_student'] ) ? array_map( 'absint', $_POST['promote_student'] ) : array();
            $new_rolls      = isset( $_POST['new_roll'] ) && is_array( $_POST['new_roll'] ) ? $_POST['new_roll'] : array();
            $new_sections   = isset( $_POST['new_section'] ) && is_array( $_POST['new_section'] ) ? $_POST['new_section'] : array();

            if ( ! empty( $target_class ) && ! empty( $selected_stids ) ) {
                $promoted_count = 0;

                foreach ( $selected_stids as $st_id ) {
                    $roll_val    = isset( $new_rolls[ $st_id ] ) ? sanitize_text_field( $new_rolls[ $st_id ] ) : '';
                    $section_val = isset( $new_sections[ $st_id ] ) ? sanitize_text_field( $new_sections[ $st_id ] ) : '';

                    $updated = $wpdb->update(
                        $table_students,
                        array(
                            'class_name'   => $target_class,
                            'section_name' => $section_val,
                            'roll_no'      => $roll_val,
                        ),
                        array( 'id' => $st_id ),
                        array( '%s', '%s', '%s' ),
                        array( '%d' )
                    );

                    if ( false !== $updated ) {
                        $promoted_count++;
                    }
                }

                if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                    IFSEdu_School_Management_System::log_activity( sprintf( 'Promoted %d students to Class %s', $promoted_count, $target_class ) );
                }

                $notice_msg = sprintf( esc_html__( 'Successfully promoted %d student(s) to Class %s.', 'ifsedu-sms' ), $promoted_count, esc_html( $target_class ) );
            }
        }
    }

    // --------------------------------------------------------------------------
    // 2. QUERY CONTEXT & FILTERS
    // --------------------------------------------------------------------------
    $filter_exam    = isset( $_GET['exam_id'] ) ? absint( $_GET['exam_id'] ) : 0;
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';

    $exams = $wpdb->get_results( "SELECT id, exam_name FROM {$table_exams} ORDER BY id DESC" );

    $raw_classes = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    $academic_classes = array();
    if ( ! empty( $raw_classes ) ) {
        $academic_classes = array_values( array_unique( $raw_classes ) );
        usort( $academic_classes, 'strnatcasecmp' );
    }

    $available_sections = array();
    if ( ! empty( $filter_class ) ) {
        $available_sections = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT section_name FROM {$table_units} WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
            $filter_class
        ) );
    }

    // --------------------------------------------------------------------------
    // 3. COMPUTE MERIT POSITIONS & PASS/FAIL FOR CANDIDATES
    // --------------------------------------------------------------------------
    $display_candidates = array();

    if ( $filter_exam > 0 && ! empty( $filter_class ) ) {
        $st_sql = "SELECT id, full_name, student_id, roll_no, class_name, section_name FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $st_params = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $st_sql .= " AND section_name = %s";
            $st_params[] = $filter_section;
        }

        $st_sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $class_students = $wpdb->get_results( $wpdb->prepare( $st_sql, ...$st_params ) );

        if ( ! empty( $class_students ) ) {
            $candidate_pool = array();

            foreach ( $class_students as $s ) {
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

                $candidate_pool[] = array(
                    'student' => $s,
                    'total'   => $total_obt,
                    'gpa'     => $final_gpa,
                    'failed'  => $has_fail,
                    'section' => $s->section_name ? trim( $s->section_name ) : ''
                );
            }

            // Merit sort: Passed first -> GPA (DESC) -> Total Marks (DESC)
            usort( $candidate_pool, function( $a, $b ) {
                if ( $a['failed'] !== $b['failed'] ) {
                    return $a['failed'] ? 1 : -1;
                }
                if ( $b['gpa'] != $a['gpa'] ) {
                    return ( $b['gpa'] < $a['gpa'] ) ? -1 : 1;
                }
                return ( $b['total'] < $a['total'] ) ? -1 : 1;
            });

            $class_pos_counter = 1;
            $section_counters  = array();

            foreach ( $candidate_pool as $item ) {
                $sec = $item['section'];
                if ( ! isset( $section_counters[ $sec ] ) ) {
                    $section_counters[ $sec ] = 1;
                }

                if ( ! $item['failed'] ) {
                    $item['class_position']   = $class_pos_counter++;
                    $item['section_position'] = $section_counters[ $sec ]++;
                } else {
                    $item['class_position']   = 0;
                    $item['section_position'] = 0;
                }

                $display_candidates[] = $item;
            }
        }
    }
    ?>

    <style>
        .dpt-promotion-root {
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
            grid-template-columns: 2fr 1.5fr 1.5fr 1.2fr;
            gap: 16px;
            align-items: flex-end;
        }

        @media (max-width: 900px) {
            .dpt-filter-grid { grid-template-columns: 1fr 1fr; }
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

        .dpt-select, .dpt-input {
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

        .dpt-select:focus, .dpt-input:focus {
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

        /* Target Promotion Settings Bar */
        .dpt-promotion-target-bar {
            background: #f0fdf4;
            border: 1.5px solid #a7f3d0;
            border-radius: 12px;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }

        .dpt-promotion-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .dpt-promotion-table th, .dpt-promotion-table td {
            border: 1px solid #cbd5e1;
            padding: 10px 12px;
            text-align: center;
            vertical-align: middle;
        }

        .dpt-promotion-table th {
            background-color: #f8fafc;
            color: #334155;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.3px;
        }

        .dpt-rank-badge {
            display: inline-block;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11.5px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .dpt-rank-badge.top {
            background: #fefce8;
            color: #854d0e;
            border-color: #fef08a;
        }

        .dpt-status-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 11px;
        }

        .status-pass { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .status-fail { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .dpt-cell-input-sm {
            height: 36px;
            width: 80px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            text-align: center;
            font-weight: 800;
            font-size: 13px;
            padding: 0 4px;
        }
    </style>

    <div class="dpt-promotion-root">

        <?php if ( ! empty( $notice_msg ) ) : ?>
            <div class="notice notice-success is-dismissible" style="padding:14px; margin:0; font-weight:700; border-left:4px solid #006a4e; background:#ecfdf5; color:#065f46; border-radius:8px;">
                <span class="dashicons dashicons-yes-alt" style="vertical-align:middle; margin-right:4px;"></span>
                <?php echo esc_html( $notice_msg ); ?>
            </div>
        <?php endif; ?>

        <!-- Step 1: Exam & Source Cohort Selection -->
        <div class="dpt-bento-card">
            <div class="dpt-card-header">
                <h4 class="dpt-card-title">
                    <span class="dashicons dashicons-randomize"></span>
                    <?php esc_html_e( '1. Select Annual Exam & Current Academic Cohort', 'ifsedu-sms' ); ?>
                </h4>
            </div>

            <form method="GET" action="<?php echo esc_url( $base_url ); ?>" id="educorePromotionFilterForm">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="students">
                <input type="hidden" name="sub" value="promotion">

                <div class="dpt-filter-grid">
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Select Final / Annual Exam', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" class="dpt-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Source Class', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="source_class_select" class="dpt-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $academic_classes as $cls_name ) : ?>
                                <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                                    <?php printf( esc_html__( 'Class %s', 'ifsedu-sms' ), esc_html( $cls_name ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Source Section (Optional)', 'ifsedu-sms' ); ?></label>
                        <select name="section_name" id="source_section_select" class="dpt-select">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $available_sections as $sec_val ) : ?>
                                <option value="<?php echo esc_attr( $sec_val ); ?>" <?php selected( $filter_section, $sec_val ); ?>>
                                    <?php echo esc_html( $sec_val ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="dpt-btn-primary">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e( 'Fetch Results', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( wp_create_nonce( "educore_promotion_nonce" ) ); ?>';

            $('#source_class_select').on('change', function() {
                var selectedClass = $(this).val();
                var $secSelect = $('#source_section_select');
                $secSelect.html('<option value=""><?php echo esc_js( __( '-- Loading... --', 'ifsedu-sms' ) ); ?></option>');

                if (!selectedClass) {
                    $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'educore_get_target_sections_promotion',
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
            });
        });
        </script>

        <!-- Step 2: Promotion Processing Matrix Table -->
        <?php if ( $filter_exam > 0 && ! empty( $filter_class ) ) : ?>
            <div class="dpt-bento-card">
                <form method="POST" action="">
                    <?php wp_nonce_field( 'execute_promotion_action', 'educore_promotion_nonce' ); ?>

                    <!-- Target Class & Section Configuration Strip -->
                    <div class="dpt-promotion-target-bar">
                        <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                            <div>
                                <label class="dpt-form-label" style="color:#065f46;"><?php esc_html_e( 'Promote To Next Class', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                                <select name="target_class" id="target_class_dropdown" class="dpt-select" style="min-width:200px; background:#ffffff;" required>
                                    <option value=""><?php esc_html_e( '-- Choose Target Class --', 'ifsedu-sms' ); ?></option>
                                    <?php foreach ( $academic_classes as $cls_name ) : ?>
                                        <option value="<?php echo esc_attr( $cls_name ); ?>">
                                            <?php printf( esc_html__( 'Class %s', 'ifsedu-sms' ), esc_html( $cls_name ) ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="dpt-form-label" style="color:#065f46;"><?php esc_html_e( 'Assign Section (Optional)', 'ifsedu-sms' ); ?></label>
                                <select name="bulk_target_section" id="bulk_target_section" class="dpt-select" style="min-width:180px; background:#ffffff;">
                                    <option value=""><?php esc_html_e( '-- Select Class First --', 'ifsedu-sms' ); ?></option>
                                </select>
                            </div>
                        </div>

                        <div style="display:flex; gap:10px; align-items:center;">
                            <button type="button" id="btn-autofill-rolls" class="dpt-select" style="width:auto; height:42px; cursor:pointer; background:#ffffff; font-weight:700;">
                                <span class="dashicons dashicons-sort" style="vertical-align:middle;"></span> <?php esc_html_e( 'Auto-Assign Rolls by Merit', 'ifsedu-sms' ); ?>
                            </button>

                            <button type="submit" name="educore_execute_promotion" class="dpt-btn-primary" style="width:auto; height:42px; padding:0 24px;">
                                <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Execute Promotion', 'ifsedu-sms' ); ?>
                            </button>
                        </div>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="dpt-promotion-table" id="dptPromotionTable">
                            <thead>
                                <tr>
                                    <th style="width: 4%;"><input type="checkbox" id="check_all_promoted"></th>
                                    <th style="width: 7%;"><?php esc_html_e( 'Class Rank', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 7%;"><?php esc_html_e( 'Sec Rank', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 7%;"><?php esc_html_e( 'Curr Roll', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 11%;"><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                                    <th style="text-align:left;"><?php esc_html_e( 'Student Name', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 10%;"><?php esc_html_e( 'Total Marks', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 8%;"><?php esc_html_e( 'GPA', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 9%;"><?php esc_html_e( 'Exam Status', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 11%;"><?php esc_html_e( 'New Roll No.', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></th>
                                    <th style="width: 11%;"><?php esc_html_e( 'New Section', 'ifsedu-sms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $display_candidates ) ) : 
                                    $merit_index = 1;
                                    foreach ( $display_candidates as $item ) : 
                                        $s      = $item['student'];
                                        $failed = $item['failed'];
                                        $c_pos  = $item['class_position'];
                                        $s_pos  = $item['section_position'];
                                ?>
                                    <tr class="<?php echo $failed ? 'row-failed' : 'row-passed'; ?>">
                                        <td>
                                            <input type="checkbox" name="promote_student[]" value="<?php echo esc_attr( $s->id ); ?>" class="st-promote-check" <?php echo ! $failed ? 'checked' : ''; ?>>
                                        </td>
                                        <td>
                                            <?php if ( ! $failed && $c_pos > 0 ) : ?>
                                                <span class="dpt-rank-badge <?php echo $c_pos <= 3 ? 'top' : ''; ?>">#<?php echo esc_html( $c_pos ); ?></span>
                                            <?php else : ?>
                                                <span style="color:#94a3b8;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ( ! $failed && $s_pos > 0 ) : ?>
                                                <span class="dpt-rank-badge">#<?php echo esc_html( $s_pos ); ?></span>
                                            <?php else : ?>
                                                <span style="color:#94a3b8;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                        <td><code><?php echo esc_html( strtoupper( $s->student_id ) ); ?></code></td>
                                        <td style="text-align:left; font-weight:700; color:#0f172a;"><?php echo esc_html( $s->full_name ); ?></td>
                                        <td><strong><?php echo floatval( $item['total'] ); ?></strong></td>
                                        <td style="font-weight:800; color:<?php echo $failed ? '#dc2626' : '#006a4e'; ?>;"><?php echo number_format( floatval( $item['gpa'] ), 2 ); ?></td>
                                        <td>
                                            <span class="dpt-status-pill <?php echo $failed ? 'status-fail' : 'status-pass'; ?>">
                                                <?php echo $failed ? esc_html__( 'FAIL', 'ifsedu-sms' ) : esc_html__( 'PASS', 'ifsedu-sms' ); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <input type="text" name="new_roll[<?php echo esc_attr( $s->id ); ?>]" 
                                                   class="dpt-cell-input-sm st-new-roll" 
                                                   value="<?php echo ! $failed ? esc_attr( $c_pos ) : esc_attr( $s->roll_no ); ?>" 
                                                   data-merit-pos="<?php echo esc_attr( $c_pos > 0 ? $c_pos : 999 ); ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="new_section[<?php echo esc_attr( $s->id ); ?>]" 
                                                   class="dpt-cell-input-sm st-new-section" 
                                                   value="<?php echo esc_attr( $s->section_name ); ?>" 
                                                   style="width:90px;" placeholder="Section">
                                        </td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr>
                                        <td colspan="11" style="padding:40px; color:#94a3b8; text-align:center;">
                                            <?php esc_html_e( 'No student examination results found matching the selected class and exam.', 'ifsedu-sms' ); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>

            <!-- Client-side Bulk Roll & Target Section Helpers -->
            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                const checkAll = document.getElementById('check_all_promoted');
                if (checkAll) {
                    checkAll.addEventListener('change', function() {
                        document.querySelectorAll('.st-promote-check').forEach(cb => cb.checked = checkAll.checked);
                    });
                }

                // Auto-fill rolls sequentially based on Merit Position
                const autoRollBtn = document.getElementById('btn-autofill-rolls');
                if (autoRollBtn) {
                    autoRollBtn.addEventListener('click', function() {
                        let rankCounter = 1;
                        document.querySelectorAll('#dptPromotionTable tbody tr.row-passed').forEach(row => {
                            const rollInp = row.querySelector('.st-new-roll');
                            const cb = row.querySelector('.st-promote-check');
                            if (rollInp && cb && cb.checked) {
                                rollInp.value = rankCounter++;
                            }
                        });
                    });
                }

                // Dynamic Section Loader for Target Class
                const targetClassDropdown = document.getElementById('target_class_dropdown');
                const targetSectionDropdown = document.getElementById('bulk_target_section');

                if (targetClassDropdown && targetSectionDropdown) {
                    targetClassDropdown.addEventListener('change', function() {
                        const targetCls = this.value;
                        targetSectionDropdown.innerHTML = '<option value="">-- Loading... --</option>';

                        if (!targetCls) {
                            targetSectionDropdown.innerHTML = '<option value="">-- Select Class First --</option>';
                            return;
                        }

                        jQuery.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'educore_get_target_sections_promotion',
                                security: '<?php echo esc_js( wp_create_nonce( "educore_promotion_nonce" ) ); ?>',
                                class_name: targetCls
                            },
                            success: function(response) {
                                if (response.success && response.data.length > 0) {
                                    let opts = '<option value="">-- Apply Bulk Section --</option>';
                                    response.data.forEach(sec => {
                                        opts += '<option value="' + sec + '">' + sec + '</option>';
                                    });
                                    targetSectionDropdown.innerHTML = opts;
                                } else {
                                    targetSectionDropdown.innerHTML = '<option value="">-- No Sections Found --</option>';
                                }
                            }
                        });
                    });

                    // Sync bulk section selector across individual inputs
                    targetSectionDropdown.addEventListener('change', function() {
                        const chosenSec = this.value;
                        if (chosenSec) {
                            document.querySelectorAll('.st-new-section').forEach(inp => inp.value = chosenSec);
                        }
                    });
                }
            });
            </script>
        <?php endif; ?>

    </div>
    <?php
}