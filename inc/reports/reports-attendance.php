<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Premium Student & Employee Attendance Analytics & Audit Module
 * File: reports-attendance-view.php
 */

function educore_reports_attendance_view() {
    global $wpdb;
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_staff      = $wpdb->prefix . 'sms_staff';
    $table_stu_att    = $wpdb->prefix . 'sms_attendance';
    $table_staff_att  = $wpdb->prefix . 'sms_staff_attendance';
    $table_units      = $wpdb->prefix . 'sms_academic_units';

    // Audit Target Mode Switcher (Student vs Employee)
    $report_target = isset( $_GET['report_target'] ) ? sanitize_text_field( wp_unslash( $_GET['report_target'] ) ) : 'student';

    // Student Filters
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';

    // Employee Filters
    $filter_staff_type = isset( $_GET['staff_type'] ) ? sanitize_text_field( wp_unslash( $_GET['staff_type'] ) ) : '';

    // Date Filters
    $filter_selected_month = isset( $_GET['report_month'] ) ? sanitize_text_field( wp_unslash( $_GET['report_month'] ) ) : current_time('m');
    $filter_year           = isset( $_GET['report_year'] ) ? sanitize_text_field( wp_unslash( $_GET['report_year'] ) ) : current_time('Y');
    $filter_month          = $filter_year . '-' . sprintf( '%02d', intval( $filter_selected_month ) );

    // Fetch classes and units
    $class_rows = $wpdb->get_results( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' AND class_name IS NOT NULL ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    $classes    = ! empty( $class_rows ) ? wp_list_pluck( $class_rows, 'class_name' ) : array();

    if ( ! empty( $classes ) ) {
        usort( $classes, function( $a, $b ) {
            return strnatcasecmp( $a, $b );
        });
    }

    $all_units = $wpdb->get_results( "SELECT id, class_name, section_name FROM {$table_units} WHERE section_name != '' AND section_name IS NOT NULL ORDER BY section_name ASC" );

    // Fetch unique Staff Types for the Employee mode filter
    $db_staff_types  = $wpdb->get_col( "SELECT DISTINCT staff_type FROM {$table_staff} WHERE status = 'Active' AND staff_type != '' ORDER BY staff_type ASC" );
    $default_types   = array( 'Teacher (School)', 'Teacher (College)', 'Officer', 'Staff' );
    $all_staff_types = array_unique( array_merge( $default_types, $db_staff_types ) );

    $months = array(
        '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
        '05' => 'May',     '06' => 'June',     '07' => 'July',  '08' => 'August',
        '09' => 'September','10' => 'October', '11' => 'November','12' => 'December',
    );

    $current_yr_int = intval( current_time('Y') );
    $years          = array( strval( $current_yr_int - 1 ), strval( $current_yr_int ), strval( $current_yr_int + 1 ) );
    ?>
    <style>
        /* ==========================================================================
           ATTENDANCE REPORTING SYSTEM - NEO-BENTO ARCHITECTURE
           ========================================================================== */
        .dpt-attendance-root {
            margin: 20px 20px 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #0f172a;
        }

        .afdp-header-frame {
            background: linear-gradient(135deg, #006a4e 0%, #004d39 100%);
            padding: 24px 28px;
            border-radius: 16px;
            margin-bottom: 24px;
            color: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(0, 106, 78, 0.25);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .afdp-header-content h2 {
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.5px;
        }

        .afdp-header-content h2 .dashicons {
            font-size: 26px;
            width: 26px;
            height: 26px;
            color: #a7f3d0;
        }

        .afdp-header-content p {
            margin: 0;
            font-size: 13px;
            color: #d1fae5;
            font-weight: 500;
        }

        /* Segmented Target Switcher */
        .report-mode-segmented {
            display: inline-flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            gap: 4px;
        }

        .report-mode-input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
            pointer-events: none;
        }

        .report-mode-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 7px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            color: #64748b;
            user-select: none;
            line-height: 1;
            border: 1px solid transparent;
        }

        .report-mode-pill .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
            line-height: 1;
            opacity: 0.7;
        }

        .report-mode-pill:hover {
            color: #0f172a;
            background: rgba(255, 255, 255, 0.6);
        }

        .report-mode-input:checked + .report-mode-pill {
            background: #006a4e;
            color: #ffffff;
            border-color: #00523c;
            box-shadow: 0 2px 8px rgba(0, 106, 78, 0.25);
        }

        .report-mode-input:checked + .report-mode-pill .dashicons {
            opacity: 1;
        }

        .dpt-filter-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
        }

        .dpt-field-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dpt-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
        }

        .dpt-select {
            width: 100%;
            height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0 14px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            box-sizing: border-box;
            transition: all 0.2s;
        }

        .dpt-select:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
            outline: none;
        }

        .dpt-btn-generate {
            height: 42px;
            background: #006a4e;
            border: none;
            color: #ffffff;
            font-weight: 700;
            font-size: 13.5px;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .dpt-btn-generate:hover {
            background: #00523c;
            transform: translateY(-1px);
        }

        .dpt-summary-bento {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
            flex-wrap: wrap;
            gap: 12px;
        }

        .dpt-summary-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .dpt-badge-days {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .dpt-table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
        }

        .dpt-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .dpt-data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13.5px;
        }

        .dpt-data-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
            white-space: nowrap;
        }

        .dpt-data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            text-align: center;
            vertical-align: middle;
        }

        .dpt-data-table tbody tr:hover td {
            background-color: #f8fafc;
        }

        .dpt-progress-container {
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: center;
        }

        .dpt-progress-bar-bg {
            width: 90px;
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        .dpt-progress-bar-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .dpt-fill-success { background: #006a4e; }
        .dpt-fill-warning { background: #f59e0b; }
        .dpt-fill-danger  { background: #ef4444; }

        .dpt-text-success { color: #047857; font-weight: 800; }
        .dpt-text-warning { color: #b45309; font-weight: 800; }
        .dpt-text-danger  { color: #b91c1c; font-weight: 800; }

        .afdp-fallback-card {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            margin-top: 20px;
        }

        .afdp-fallback-card .dashicons {
            font-size: 36px;
            width: 36px;
            height: 36px;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        .afdp-fallback-card p {
            margin: 0;
            font-size: 14px;
            color: #64748b;
            font-weight: 600;
        }

        .dpt-print-header-area {
            display: none;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
            }

            body * {
                visibility: hidden !important;
            }

            #adminmenumain, #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter,
            #screen-meta, #screen-meta-links, .afdp-header-frame, .dpt-filter-card,
            .dpt-summary-bento, .notice, .updated, .error, .no-print {
                display: none !important;
            }

            html, body, #wpcontent, #wpbody, #wpbody-content, .dpt-attendance-root {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .dpt-print-header-area, .dpt-print-header-area *,
            .dpt-table-card, .dpt-table-card * {
                visibility: visible !important;
            }

            .dpt-print-header-area {
                display: block !important;
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                border-bottom: 2px solid #000000;
                padding-bottom: 8px;
                margin-bottom: 15px;
                text-align: center;
            }

            .dpt-print-header-area h1 {
                font-size: 18pt !important;
                font-weight: bold !important;
                text-transform: uppercase;
                margin: 0 0 4px 0 !important;
                color: #000000 !important;
            }

            .dpt-print-header-area h3 {
                font-size: 12pt !important;
                font-weight: bold !important;
                margin: 0 0 10px 0 !important;
                color: #333333 !important;
            }

            .dpt-print-meta-grid {
                display: flex !important;
                justify-content: space-between;
                font-size: 9.5pt !important;
                color: #000000 !important;
                padding: 0 4px;
            }

            .dpt-table-card {
                position: absolute !important;
                left: 0 !important;
                top: 95px !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
            }

            .dpt-data-table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 9.5pt !important;
            }

            .dpt-data-table th, .dpt-data-table td {
                border: 1px solid #000000 !important;
                padding: 7px 8px !important;
                color: #000000 !important;
                background: #ffffff !important;
                text-align: center !important;
            }

            .dpt-data-table th {
                background-color: #f1f5f9 !important;
                font-weight: bold !important;
                text-transform: uppercase;
                font-size: 8.5pt !important;
            }

            .dpt-progress-bar-bg {
                display: none !important;
            }

            .dpt-text-success, .dpt-text-warning, .dpt-text-danger {
                color: #000000 !important;
                font-weight: bold !important;
            }
        }
    </style>

    <div class="dpt-attendance-root">
        
        <!-- Header Banner -->
        <div class="afdp-header-frame no-print">
            <div class="afdp-header-content">
                <h2>
                    <span class="dashicons dashicons-calendar-alt"></span> Monthly Attendance Audit Statement
                </h2>
                <p>Select target scope, month, and year to generate comprehensive monthly attendance audit reports.</p>
            </div>
        </div>

        <!-- Filter Control Matrix Card -->
        <div class="dpt-filter-card no-print">
            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="reports">
                <input type="hidden" name="sub" value="attendance">
                
                <!-- Target Scope Switcher -->
                <div style="margin-bottom:20px; display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                    <span style="font-size:13px; font-weight:700; color:#475569;"><?php esc_html_e( 'Audit Target Scope:', 'ifsedu-sms' ); ?></span>
                    
                    <div class="report-mode-segmented">
                        <input type="radio" class="report-mode-input" id="target_student" name="report_target" value="student" <?php checked( $report_target, 'student' ); ?>>
                        <label class="report-mode-pill" for="target_student">
                            <span class="dashicons dashicons-welcome-learn-more"></span>
                            <?php esc_html_e( 'Students Audit', 'ifsedu-sms' ); ?>
                        </label>

                        <input type="radio" class="report-mode-input" id="target_staff" name="report_target" value="staff" <?php checked( $report_target, 'staff' ); ?>>
                        <label class="report-mode-pill" for="target_staff">
                            <span class="dashicons dashicons-businessman"></span>
                            <?php esc_html_e( 'Employees (Staff / Faculty) Audit', 'ifsedu-sms' ); ?>
                        </label>
                    </div>
                </div>

                <div style="display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap;">
                    
                    <!-- STUDENT FILTERS -->
                    <div id="wrapper_student_filters" style="display: <?php echo ( $report_target === 'student' ) ? 'flex' : 'none'; ?>; gap:14px; flex:2; flex-wrap:wrap;">
                        <div class="dpt-field-group" style="flex:1; min-width:160px;">
                            <label class="dpt-label"><?php esc_html_e( 'Select Class', 'ifsedu-sms' ); ?> *</label>
                            <select name="class_name" id="afdp_class_select" class="dpt-select">
                                <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-sms' ); ?></option>
                                <?php foreach ( $classes as $cls ) : ?>
                                    <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="dpt-field-group" style="flex:1; min-width:160px;">
                            <label class="dpt-label"><?php esc_html_e( 'Select Section', 'ifsedu-sms' ); ?></label>
                            <select name="section_name" id="afdp_section_select" class="dpt-select">
                                <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- EMPLOYEE FILTERS -->
                    <div id="wrapper_staff_filters" style="display: <?php echo ( $report_target === 'staff' ) ? 'flex' : 'none'; ?>; gap:14px; flex:2; flex-wrap:wrap;">
                        <div class="dpt-field-group" style="flex:1; min-width:220px;">
                            <label class="dpt-label"><?php esc_html_e( 'Filter by Employment Type', 'ifsedu-sms' ); ?></label>
                            <select name="staff_type" id="afdp_staff_type_select" class="dpt-select">
                                <option value=""><?php esc_html_e( '-- All Employment Types --', 'ifsedu-sms' ); ?></option>
                                <?php foreach ( $all_staff_types as $st_type ) : ?>
                                    <option value="<?php echo esc_attr( $st_type ); ?>" <?php selected( $filter_staff_type, $st_type ); ?>><?php echo esc_html( $st_type ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- MONTH & YEAR FILTERS -->
                    <div class="dpt-field-group" style="flex:1; min-width:140px;">
                        <label class="dpt-label"><?php esc_html_e( 'Select Month', 'ifsedu-sms' ); ?> *</label>
                        <select name="report_month" class="dpt-select" required>
                            <?php foreach ( $months as $m_num => $m_name ) : ?>
                                <option value="<?php echo esc_attr( $m_num ); ?>" <?php selected( $filter_selected_month, $m_num ); ?>>
                                    <?php echo esc_html( $m_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dpt-field-group" style="flex:1; min-width:120px;">
                        <label class="dpt-label"><?php esc_html_e( 'Select Year', 'ifsedu-sms' ); ?> *</label>
                        <select name="report_year" class="dpt-select" required>
                            <?php foreach ( $years as $yr ) : ?>
                                <option value="<?php echo esc_attr( $yr ); ?>" <?php selected( $filter_year, $yr ); ?>>
                                    <?php echo esc_html( $yr ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dpt-field-group" style="min-width:140px;">
                        <button type="submit" class="dpt-btn-generate" style="width:100%;">
                            <span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'View Report', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- REPORT STATEMENT ENGINE -->
        <?php
        $like_pattern = $wpdb->esc_like( $filter_month ) . '%';
        $month_name   = isset( $months[$filter_selected_month] ) ? $months[$filter_selected_month] : '';

        if ( $report_target === 'student' && ! empty( $filter_class ) ) {
            
            $section_sql = "";
            $params_days = array( $filter_class );
            if ( ! empty( $filter_section ) ) {
                $section_sql   = " AND s.section_name = %s ";
                $params_days[] = $filter_section;
            }
            $params_days[] = $like_pattern;

            $total_working_days = $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(DISTINCT a.attendance_date) 
                FROM {$table_attendance} a
                INNER JOIN {$table_students} s ON a.student_id = s.id
                WHERE s.class_name = %s {$section_sql} AND a.attendance_date LIKE %s
            ", $params_days ) );

            $total_working_days = $total_working_days ? intval( $total_working_days ) : 0;

            $params_students = array( $like_pattern, $filter_class );
            if ( ! empty( $filter_section ) ) {
                $params_students[] = $filter_section;
            }

            $students = $wpdb->get_results( $wpdb->prepare( "
                SELECT 
                    s.id, s.student_id, s.full_name, s.roll_no, s.section_name,
                    SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count
                FROM {$table_students} s
                LEFT JOIN {$table_attendance} a 
                    ON s.id = a.student_id AND a.attendance_date LIKE %s
                WHERE s.status = 'Active' AND s.class_name = %s {$section_sql}
                GROUP BY s.id
            ", $params_students ) );

            if ( ! empty( $students ) ) {
                usort( $students, function( $a, $b ) {
                    return strnatcasecmp( $a->roll_no, $b->roll_no );
                });
            }

            $section_label = ! empty( $filter_section ) ? 'Section: ' . esc_html( $filter_section ) : 'All Sections';
            ?>

            <!-- Print Header -->
            <div class="dpt-print-header-area">
                <h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
                <h3>Student Monthly Attendance Audit Report</h3>
                <div class="dpt-print-meta-grid">
                    <div>
                        <strong>Class:</strong> <?php echo esc_html( $filter_class ); ?> (<?php echo esc_html( $section_label ); ?>)<br>
                        <strong>Academic Session:</strong> <?php echo esc_html( $filter_year ); ?>
                    </div>
                    <div style="text-align: right;">
                        <strong>Report Month:</strong> <?php echo esc_html( $month_name . ' ' . $filter_year ); ?><br>
                        <strong>Generated:</strong> <?php echo esc_html( current_time('M j, Y, g:i a') ); ?>
                    </div>
                </div>
            </div>

            <!-- Screen Summary Bento -->
            <div class="dpt-summary-bento no-print">
                <h3 class="dpt-summary-title">
                    <span class="dashicons dashicons-groups" style="color:#006a4e;"></span> 
                    Class: <?php echo esc_html( $filter_class . ' - ' . $section_label ); ?> | Month: <?php echo esc_html( $month_name . ' ' . $filter_year ); ?>
                </h3>
                <div style="display:flex; gap:12px; align-items:center;">
                    <span class="dpt-badge-days">
                        <span class="dashicons dashicons-clock"></span> Total Working Days: <?php echo esc_html( $total_working_days ); ?>
                    </span>
                    <button onclick="window.print()" class="dpt-btn-generate no-print" style="height:34px; padding:0 14px; font-size:12.5px; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; box-shadow:none;">
                        <span class="dashicons dashicons-printer"></span> Print Report
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="dpt-table-card">
                <div class="dpt-table-wrapper">
                    <table class="dpt-data-table">
                        <thead>
                            <tr>
                                <th style="width: 10%;">Roll No</th>
                                <th style="text-align: left;">Student Name</th>
                                <th style="width: 12%;">Student ID</th>
                                <th style="width: 10%;">Section</th>
                                <th style="width: 12%;">Present</th>
                                <th style="width: 12%;">Absent</th>
                                <th style="width: 12%;">Late</th>
                                <th style="width: 14%;">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $students ) ) : foreach ( $students as $student ) : 
                                $present_count = intval( $student->present_count );
                                $absent_count  = intval( $student->absent_count );
                                $late_count    = intval( $student->late_count );

                                $total_attended = $present_count + $late_count;
                                $percentage     = ($total_working_days > 0) ? round( ($total_attended / $total_working_days) * 100, 1 ) : 0;
                                
                                $fill_class = 'dpt-fill-danger';
                                $text_class = 'dpt-text-danger';
                                if ( $percentage >= 80 ) {
                                    $fill_class = 'dpt-fill-success';
                                    $text_class = 'dpt-text-success';
                                } elseif ( $percentage >= 50 ) {
                                    $fill_class = 'dpt-fill-warning';
                                    $text_class = 'dpt-text-warning';
                                }
                            ?>
                            <tr>
                                <td><strong>#<?php echo esc_html( $student->roll_no ); ?></strong></td>
                                <td style="text-align: left;"><div style="font-weight: 700;"><?php echo esc_html( $student->full_name ); ?></div></td>
                                <td><code><?php echo esc_html( $student->student_id ); ?></code></td>
                                <td><span><?php echo esc_html( ! empty( $student->section_name ) ? $student->section_name : 'N/A' ); ?></span></td>
                                <td style="color:#047857; font-weight:800;"><?php echo esc_html( $present_count ); ?> Days</td>
                                <td style="color:#b91c1c; font-weight:800;"><?php echo esc_html( $absent_count ); ?> Days</td>
                                <td style="color:#b45309; font-weight:800;"><?php echo esc_html( $late_count ); ?> Days</td>
                                <td>
                                    <div class="dpt-progress-container">
                                        <div class="dpt-progress-bar-bg no-print">
                                            <div class="dpt-progress-bar-fill <?php echo esc_attr( $fill_class ); ?>" style="width: <?php echo esc_attr( min(100, $percentage) ); ?>%;"></div>
                                        </div>
                                        <span class="<?php echo esc_attr( $text_class ); ?>"><?php echo esc_html( $percentage ); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else : ?>
                            <tr><td colspan="8" style="padding: 30px; color: #94a3b8;">No active students found assigned to this class/section filter.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php } elseif ( $report_target === 'staff' ) {
            
            $staff_type_sql = "";
            $params_days    = array();

            if ( ! empty( $filter_staff_type ) ) {
                $staff_type_sql = " AND st.staff_type = %s ";
                $params_days[]  = $filter_staff_type;
            }
            $params_days[] = $like_pattern;

            $total_working_days = $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(DISTINCT a.attendance_date) 
                FROM {$table_staff_att} a
                INNER JOIN {$table_staff} st ON a.staff_id = st.id
                WHERE st.status = 'Active' {$staff_type_sql} AND a.attendance_date LIKE %s
            ", $params_days ) );

            $total_working_days = $total_working_days ? intval( $total_working_days ) : 0;

            $params_staff = array( $like_pattern );
            if ( ! empty( $filter_staff_type ) ) {
                 $params_staff[] = $filter_staff_type;
            }

            $staff_members = $wpdb->get_results( $wpdb->prepare( "
                SELECT 
                    st.id, st.staff_id, st.full_name, st.name_bn, st.designation, st.staff_type,
                    SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count
                FROM {$table_staff} st
                LEFT JOIN {$table_staff_att} a 
                    ON st.id = a.staff_id AND a.attendance_date LIKE %s
                WHERE st.status = 'Active' {$staff_type_sql}
                GROUP BY st.id
                ORDER BY st.full_name ASC
            ", $params_staff ) );

            $type_label = ! empty( $filter_staff_type ) ? $filter_staff_type : 'All Employment Types';
            ?>

            <!-- Print Header -->
            <div class="dpt-print-header-area">
                <h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
                <h3>Employee Monthly Attendance Audit Statement</h3>
                <div class="dpt-print-meta-grid">
                    <div>
                        <strong>Employment Scope:</strong> <?php echo esc_html( $type_label ); ?><br>
                        <strong>Academic Session:</strong> <?php echo esc_html( $filter_year ); ?>
                    </div>
                    <div style="text-align: right;">
                        <strong>Report Month:</strong> <?php echo esc_html( $month_name . ' ' . $filter_year ); ?><br>
                        <strong>Generated:</strong> <?php echo esc_html( current_time('M j, Y, g:i a') ); ?>
                    </div>
                </div>
            </div>

            <!-- Screen Summary Bento -->
            <div class="dpt-summary-bento no-print">
                <h3 class="dpt-summary-title">
                    <span class="dashicons dashicons-businessman" style="color:#006a4e;"></span> 
                    Scope: <?php echo esc_html( $type_label ); ?> | Month: <?php echo esc_html( $month_name . ' ' . $filter_year ); ?>
                </h3>
                <div style="display:flex; gap:12px; align-items:center;">
                    <span class="dpt-badge-days">
                        <span class="dashicons dashicons-clock"></span> Total Working Days: <?php echo esc_html( $total_working_days ); ?>
                    </span>
                    <button onclick="window.print()" class="dpt-btn-generate no-print" style="height:34px; padding:0 14px; font-size:12.5px; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; box-shadow:none;">
                        <span class="dashicons dashicons-printer"></span> Print Report
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="dpt-table-card">
                <div class="dpt-table-wrapper">
                    <table class="dpt-data-table">
                        <thead>
                            <tr>
                                <th style="width: 12%;">Staff ID</th>
                                <th style="text-align: left;">Employee Name</th>
                                <th style="width: 18%;">Designation</th>
                                <th style="width: 16%;">Employment Type</th>
                                <th style="width: 10%;">Present</th>
                                <th style="width: 10%;">Absent</th>
                                <th style="width: 10%;">Late</th>
                                <th style="width: 14%;">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $staff_members ) ) : foreach ( $staff_members as $st ) : 
                                $present_count = intval( $st->present_count );
                                $absent_count  = intval( $st->absent_count );
                                $late_count    = intval( $st->late_count );

                                $total_attended = $present_count + $late_count;
                                $percentage     = ($total_working_days > 0) ? round( ($total_attended / $total_working_days) * 100, 1 ) : 0;
                                
                                $fill_class = 'dpt-fill-danger';
                                $text_class = 'dpt-text-danger';
                                if ( $percentage >= 80 ) {
                                    $fill_class = 'dpt-fill-success';
                                    $text_class = 'dpt-text-success';
                                } elseif ( $percentage >= 50 ) {
                                    $fill_class = 'dpt-fill-warning';
                                    $text_class = 'dpt-text-warning';
                                }

                                $name_display = ! empty( $st->name_bn ) ? $st->name_bn : $st->full_name;
                            ?>
                            <tr>
                                <td><code><?php echo esc_html( ! empty( $st->staff_id ) ? $st->staff_id : '#' . $st->id ); ?></code></td>
                                <td style="text-align: left;"><div style="font-weight: 700;"><?php echo esc_html( $name_display ); ?></div></td>
                                <td><span><?php echo esc_html( ! empty( $st->designation ) ? $st->designation : 'Faculty' ); ?></span></td>
                                <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-weight:600; font-size:12px;"><?php echo esc_html( $st->staff_type ); ?></span></td>
                                <td style="color:#047857; font-weight:800;"><?php echo esc_html( $present_count ); ?> Days</td>
                                <td style="color:#b91c1c; font-weight:800;"><?php echo esc_html( $absent_count ); ?> Days</td>
                                <td style="color:#b45309; font-weight:800;"><?php echo esc_html( $late_count ); ?> Days</td>
                                <td>
                                    <div class="dpt-progress-container">
                                        <div class="dpt-progress-bar-bg no-print">
                                            <div class="dpt-progress-bar-fill <?php echo esc_attr( $fill_class ); ?>" style="width: <?php echo esc_attr( min(100, $percentage) ); ?>%;"></div>
                                        </div>
                                        <span class="<?php echo esc_attr( $text_class ); ?>"><?php echo esc_html( $percentage ); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else : ?>
                            <tr><td colspan="8" style="padding: 30px; color: #94a3b8;">No active employees found for the selected scope.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php } else {
            echo '<div class="afdp-fallback-card no-print"><span class="dashicons dashicons-info"></span><p>' . esc_html__( 'Please select your Target Scope, Class/Type, Month, and Year above to generate the monthly attendance report.', 'ifsedu-sms' ) . '</p></div>';
        }
        ?>

    </div>

    <!-- Client-Side Target Switcher & Section Chaining Script -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const unitsMap        = <?php echo wp_json_encode( ! empty( $all_units ) ? $all_units : array() ); ?>;
        const currentSection  = "<?php echo esc_js( $filter_section ); ?>";
        const classSelect     = document.getElementById('afdp_class_select');
        const sectionSelect   = document.getElementById('afdp_section_select');

        const targetRadios    = document.querySelectorAll('input[name="report_target"]');
        const wrapperStudents = document.getElementById('wrapper_student_filters');
        const wrapperStaff    = document.getElementById('wrapper_staff_filters');

        // Target Switcher Listener
        targetRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'student') {
                    wrapperStudents.style.display = 'flex';
                    wrapperStaff.style.display    = 'none';
                } else {
                    wrapperStudents.style.display = 'none';
                    wrapperStaff.style.display    = 'flex';
                }
            });
        });

        // Section Chaining
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

        if (classSelect && sectionSelect) {
            populateSections(classSelect.value, currentSection);

            classSelect.addEventListener('change', function() {
                populateSections(this.value);
            });
        }
    });
    </script>
    <?php
}