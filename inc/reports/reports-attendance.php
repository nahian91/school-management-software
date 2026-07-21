<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Premium Student Attendance Analytics & Audit Module
 * Theme Aesthetic: Elite Neo-Bento Grid & Visual Progress System
 * Custom Prefixes Applied: dpt-, afdp-
 * File: reports-attendance-view.php
 */
function educore_reports_attendance_view() {
    global $wpdb;
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $table_units      = $wpdb->prefix . 'sms_academic_units'; // Academic Units / Classes Table

    // Fetch dynamic classes array directly from database table with natural numeric order
    $class_rows = $wpdb->get_results( "SELECT class_name FROM {$table_units} ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    $classes    = ! empty( $class_rows ) ? wp_list_pluck( $class_rows, 'class_name' ) : array();
    
    // Filter State Management
    $filter_class          = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_selected_month = isset( $_GET['report_month'] ) ? sanitize_text_field( wp_unslash( $_GET['report_month'] ) ) : current_time('m');
    $filter_year           = isset( $_GET['report_year'] ) ? sanitize_text_field( wp_unslash( $_GET['report_year'] ) ) : current_time('Y');

    // Constructed Year-Month String (e.g. "2026-07") for DB Queries
    $filter_month = $filter_year . '-' . sprintf( '%02d', intval( $filter_selected_month ) );

    // Array of Months
    $months = array(
        '01' => 'January',
        '02' => 'February',
        '03' => 'March',
        '04' => 'April',
        '05' => 'May',
        '06' => 'June',
        '07' => 'July',
        '08' => 'August',
        '09' => 'September',
        '10' => 'October',
        '11' => 'November',
        '12' => 'December',
    );

    // Dynamic Year Range Matrix
    $current_yr_int = intval( current_time('Y') );
    $years          = array(
        strval( $current_yr_int - 1 ),
        strval( $current_yr_int ),
        strval( $current_yr_int + 1 )
    );
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

        /* Top Header Action Banner */
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

        /* Filter Control Matrix Card */
        .dpt-filter-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
        }

        .dpt-filter-grid {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1fr 180px;
            gap: 16px;
            align-items: flex-end;
        }

        @media (max-width: 991px) {
            .dpt-filter-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .dpt-filter-grid {
                grid-template-columns: 1fr;
            }
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
            letter-spacing: -0.1px;
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

        /* Summary Bento Banner & Stats Bar */
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

        /* Attendance Table Node */
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

        /* Progress Meter Matrix */
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

        .dpt-fill-success { background: #10b981; }
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

        /* Print Media Layout */
        @media print {
            body * { visibility: hidden; }
            .dpt-attendance-root, .dpt-attendance-root * { visibility: visible; }
            .dpt-attendance-root {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 0;
            }
            .no-print { display: none !important; }
            .dpt-table-card, .dpt-summary-bento {
                border: 1px solid #cbd5e1 !important;
                box-shadow: none !important;
            }
        }
    </style>

    <div class="dpt-attendance-root">
        
        <!-- Header Banner -->
        <div class="afdp-header-frame no-print">
            <div class="afdp-header-content">
                <h2>
                    <span class="dashicons dashicons-calendar-alt"></span> Monthly Student Attendance Audit
                </h2>
                <p>Select academic class, month, and year to generate class-wide attendance percentages and aggregate reports.</p>
            </div>
        </div>

        <!-- Filter Control Matrix Card -->
        <div class="dpt-filter-card no-print">
            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="reports">
                <input type="hidden" name="sub" value="attendance">
                
                <div class="dpt-filter-grid">
                    <!-- Class Dropdown -->
                    <div class="dpt-field-group">
                        <label class="dpt-label">Select Class</label>
                        <select name="class_name" class="dpt-select" required>
                            <option value="">-- Choose Class --</option>
                            <?php foreach ( $classes as $cls ) : ?>
                                <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Month Dropdown -->
                    <div class="dpt-field-group">
                        <label class="dpt-label">Select Month</label>
                        <select name="report_month" class="dpt-select" required>
                            <?php foreach ( $months as $m_num => $m_name ) : ?>
                                <option value="<?php echo esc_attr( $m_num ); ?>" <?php selected( $filter_selected_month, $m_num ); ?>>
                                    <?php echo esc_html( $m_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Year Dropdown -->
                    <div class="dpt-field-group">
                        <label class="dpt-label">Select Year</label>
                        <select name="report_year" class="dpt-select" required>
                            <?php foreach ( $years as $yr ) : ?>
                                <option value="<?php echo esc_attr( $yr ); ?>" <?php selected( $filter_year, $yr ); ?>>
                                    <?php echo esc_html( $yr ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="dpt-btn-generate">
                        <span class="dashicons dashicons-filter"></span> View Report
                    </button>
                </div>
            </form>
        </div>

        <?php
        if ( ! empty( $filter_class ) && ! empty( $filter_selected_month ) && ! empty( $filter_year ) ) {
            // Find total working days in that month for that class
            $total_working_days = $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(DISTINCT a.attendance_date) 
                FROM {$table_attendance} a
                INNER JOIN {$table_students} s ON a.student_id = s.id
                WHERE s.class_name = %s AND a.attendance_date LIKE %s
            ", $filter_class, $filter_month . '%' ) );

            $total_working_days = $total_working_days ? intval( $total_working_days ) : 0;

            // Single Optimized Bulk Query: Fetch Students & Pre-Calculated Monthly Counts with Numeric Roll Sorting
            $students = $wpdb->get_results( $wpdb->prepare( "
                SELECT 
                    s.id, 
                    s.student_id, 
                    s.full_name, 
                    s.roll_no,
                    SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count
                FROM {$table_students} s
                LEFT JOIN {$table_attendance} a 
                    ON s.id = a.student_id 
                    AND a.attendance_date LIKE %s
                WHERE s.status = 'Active' AND s.class_name = %s
                GROUP BY s.id
                ORDER BY CAST(s.roll_no AS UNSIGNED) ASC, s.roll_no ASC
            ", $filter_month . '%', $filter_class ) );
            ?>

            <!-- Summary Bento Header -->
            <div class="dpt-summary-bento">
                <h3 class="dpt-summary-title">
                    <span class="dashicons dashicons-groups" style="color:#006a4e;"></span> 
                    Attendance Breakdown for <?php echo esc_html( $filter_class ); ?> (<?php echo esc_html( $months[$filter_selected_month] . ' ' . $filter_year ); ?>)
                </h3>
                <div class="dpt-summary-right" style="display:flex; gap:12px; align-items:center;">
                    <span class="dpt-badge-days">
                        <span class="dashicons dashicons-clock"></span> Total Working Days: <?php echo $total_working_days; ?>
                    </span>
                    <button onclick="window.print()" class="dpt-btn-generate no-print" style="height:34px; padding:0 14px; font-size:12.5px; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; box-shadow:none;">
                        <span class="dashicons dashicons-printer"></span> Print Report
                    </button>
                </div>
            </div>

            <!-- Attendance Data Table -->
            <div class="dpt-table-card">
                <div class="dpt-table-wrapper">
                    <table class="dpt-data-table">
                        <thead>
                            <tr>
                                <th style="width: 10%;">Roll No</th>
                                <th style="text-align: left;">Student Info</th>
                                <th>Present (Days)</th>
                                <th>Absent (Days)</th>
                                <th>Late (Days)</th>
                                <th>Attendance %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $students ) ) : foreach ( $students as $student ) : 
                                $present_count = intval( $student->present_count );
                                $absent_count  = intval( $student->absent_count );
                                $late_count    = intval( $student->late_count );

                                // Percentage considers Present + Late as attended days or pure Present percentage based on institutional rule
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
                                <td style="text-align: left;">
                                    <div style="font-weight: 700; color: #0f172a;"><?php echo esc_html( $student->full_name ); ?></div>
                                    <small style="color: #64748b; font-size: 11.5px;">ID: <?php echo esc_html( $student->student_id ); ?></small>
                                </td>
                                <td style="color:#047857; font-weight:800;"><?php echo $present_count; ?></td>
                                <td style="color:#b91c1c; font-weight:800;"><?php echo $absent_count; ?></td>
                                <td style="color:#b45309; font-weight:800;"><?php echo $late_count; ?></td>
                                <td>
                                    <div class="dpt-progress-container">
                                        <div class="dpt-progress-bar-bg">
                                            <div class="dpt-progress-bar-fill <?php echo esc_attr( $fill_class ); ?>" style="width: <?php echo min(100, $percentage); ?>%;"></div>
                                        </div>
                                        <span class="<?php echo esc_attr( $text_class ); ?>"><?php echo $percentage; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else : ?>
                            <tr>
                                <td colspan="6" style="padding: 30px; color: #94a3b8;">No active students found assigned to this class.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
        } else {
            echo '<div class="afdp-fallback-card no-print"><span class="dashicons dashicons-info"></span><p>' . esc_html__( 'Please select a Class, Month, and Year above to generate the monthly attendance report.', 'ifsedu-sms' ) . '</p></div>';
        }
        ?>

    </div>
    <?php
}