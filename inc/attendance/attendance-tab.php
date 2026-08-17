<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Teacher & Staff Attendance View
 * File: inc/attendance.php (or inc/attendance/attendance-tab.php)
 */
function educore_attendance_tab() {
    global $wpdb;

    $current_user = wp_get_current_user();
    $table_units  = $wpdb->prefix . 'sms_academic_units';
    $table_staff  = $wpdb->prefix . 'sms_staff';
    $table_assign = $wpdb->prefix . 'sms_teacher_subjects';

    // 1. Role & Capability Checks (Checks Admin, Procedural Helper, and SMS Staff Table)
    $is_admin = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    
    // Check if user has teacher/staff role or exists in staff table
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
        wp_die( esc_html__( 'You do not have sufficient permissions to access the attendance module.', 'ifsedu-sms' ) );
    }

    $sub_tab = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'daily';

    // Allowed sub-tabs for teachers & staff
    $teacher_allowed_tabs = array( 'daily', 'roster', 'exam', 'monthly' );
    if ( ! $is_admin && ! in_array( $sub_tab, $teacher_allowed_tabs, true ) ) {
        $sub_tab = 'daily';
    }

    $filter_class    = isset( $_REQUEST['class_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['class_name'] ) ) : '';
    $filter_section  = isset( $_REQUEST['section_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['section_name'] ) ) : '';
    $filter_date     = isset( $_REQUEST['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['attendance_date'] ) ) : current_time( 'Y-m-d' );
    $filter_exam_id  = isset( $_REQUEST['exam_id'] ) ? absint( $_REQUEST['exam_id'] ) : 0;

    // 2. Fetch Assigned Classes & Sections
    $classes  = array();
    $sections = array();

    if ( ! $is_admin ) {
        $teacher = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$table_staff} WHERE wp_user_id = %d OR email = %s LIMIT 1",
            $current_user->ID,
            $current_user->user_email
        ) );

        if ( $teacher ) {
            $assigned_units = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT u.class_name, u.section_name 
                 FROM {$table_assign} ts
                 INNER JOIN {$table_units} u ON ts.class_id = u.id
                 WHERE ts.teacher_id = %d
                 ORDER BY CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC",
                $teacher->id
            ) );

            if ( ! empty( $assigned_units ) ) {
                foreach ( $assigned_units as $unit ) {
                    if ( ! in_array( $unit->class_name, $classes, true ) ) {
                        $classes[] = $unit->class_name;
                    }
                    if ( ! empty( $unit->section_name ) && ! in_array( $unit->section_name, $sections, true ) ) {
                        $sections[] = $unit->section_name;
                    }
                }
            }
        }

        // Fallback: If no specific units assigned, allow all active classes
        if ( empty( $classes ) ) {
            $raw_classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
            if ( ! empty( $raw_classes ) ) {
                foreach ( $raw_classes as $cls_obj ) {
                    $classes[] = $cls_obj->class_name;
                }
            }
        }

        if ( empty( $filter_class ) && ! empty( $classes[0] ) ) {
            $filter_class = $classes[0];
        }
        if ( empty( $filter_section ) && ! empty( $sections[0] ) ) {
            $filter_section = $sections[0];
        }
    } else {
        $raw_classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
        if ( ! empty( $raw_classes ) ) {
            foreach ( $raw_classes as $cls_obj ) {
                $classes[] = $cls_obj->class_name;
            }
        }
    }

    if ( ! empty( $filter_class ) && empty( $sections ) ) {
        $raw_sections = $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT section_name FROM {$table_units} WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
            $filter_class
        ) );
        if ( ! empty( $raw_sections ) ) {
            foreach ( $raw_sections as $sec_obj ) {
                $sections[] = $sec_obj->section_name;
            }
        }
    }

    // Dynamic Navigation URLs
    $daily_url   = admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=daily' );
    $exam_url    = admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=exam' );
    $monthly_url = admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=monthly' );
    $staff_url   = admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=staff' );
    $reports_url = admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=reports' );
    ?>

    <style>
        .dpt-attendance-root {
            margin: 20px 20px 24px 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }
        
        .afdp-top-nav-wrapper {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 12px 18px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 24px;
        }

        .dpt-nav-button-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .dpt-nav-link {
            height: 38px;
            padding: 0 16px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        
        .dpt-nav-link-active {
            background: #006a4e;
            color: #ffffff !important;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }
        .dpt-nav-link-active:hover {
            background: #00523c;
        }
        
        .dpt-nav-link-inactive {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #475569;
        }
        .dpt-nav-link-inactive:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .dpt-assigned-pill {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        @media print {
            .no-print, .afdp-top-nav-wrapper { display: none !important; }
            .dpt-attendance-root { margin: 0 !important; }
        }
    </style>

    <div class="dpt-attendance-root">
        
        <!-- Sub-Navigation Header Bar -->
        <div class="afdp-top-nav-wrapper no-print">
            <div class="dpt-nav-button-group">
                <a href="<?php echo esc_url( $daily_url ); ?>" class="dpt-nav-link <?php echo ( $sub_tab === 'daily' || $sub_tab === 'roster' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Daily Attendance', 'ifsedu-sms' ); ?>
                </a>

                <a href="<?php echo esc_url( $exam_url ); ?>" class="dpt-nav-link <?php echo ( $sub_tab === 'exam' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-welcome-write-blog"></span>
                    <?php esc_html_e( 'Exam Attendance', 'ifsedu-sms' ); ?>
                </a>

                <a href="<?php echo esc_url( $monthly_url ); ?>" class="dpt-nav-link <?php echo ( $sub_tab === 'monthly' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php esc_html_e( 'Monthly Summary', 'ifsedu-sms' ); ?>
                </a>

                <?php if ( $is_admin ) : ?>
                    <a href="<?php echo esc_url( $staff_url ); ?>" class="dpt-nav-link <?php echo ( $sub_tab === 'staff' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                        <span class="dashicons dashicons-businessman"></span>
                        <?php esc_html_e( 'Staff Attendance', 'ifsedu-sms' ); ?>
                    </a>

                    <a href="<?php echo esc_url( $reports_url ); ?>" class="dpt-nav-link <?php echo ( $sub_tab === 'reports' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e( 'Attendance Reports', 'ifsedu-sms' ); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ( ! $is_admin && ! empty( $classes ) ) : ?>
                <div class="dpt-assigned-pill">
                    <span class="dashicons dashicons-id-alt" style="font-size:14px; width:14px; height:14px;"></span>
                    <?php 
                        $sec_label = ! empty( $sections ) ? ' (' . implode( ', ', $sections ) . ')' : '';
                        printf( esc_html__( 'Assigned: Class %s%s', 'ifsedu-sms' ), esc_html( implode( ', ', $classes ) ), esc_html( $sec_label ) ); 
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="dpt-module-viewport-container">
            <?php
            $attendance_dir = EDUCORE_PATH . 'inc/attendance/';

            switch ( $sub_tab ) {
                case 'exam':
                    $exam_file = $attendance_dir . 'attendance-exam.php';
                    if ( file_exists( $exam_file ) ) {
                        require_once $exam_file;
                    }
                    if ( function_exists( 'educore_exam_attendance_view' ) ) {
                        educore_exam_attendance_view( $classes, $sections, $filter_class, $filter_section, $filter_date, $filter_exam_id );
                    }
                    break;

                case 'monthly':
                    $monthly_file = $attendance_dir . 'attendance-monthly.php';
                    if ( file_exists( $monthly_file ) ) {
                        require_once $monthly_file;
                    }
                    if ( function_exists( 'educore_monthly_attendance_summary_view' ) ) {
                        educore_monthly_attendance_summary_view( $classes, $sections, $filter_class, $filter_section );
                    }
                    break;

                case 'staff':
                    if ( $is_admin ) {
                        $staff_file = $attendance_dir . 'attendance-staff.php';
                        if ( file_exists( $staff_file ) ) {
                            require_once $staff_file;
                        }
                        if ( function_exists( 'educore_staff_attendance_view' ) ) {
                            educore_staff_attendance_view();
                        }
                    }
                    break;

                case 'reports':
                    if ( $is_admin ) {
                        $reports_file = $attendance_dir . 'attendance-reports.php';
                        if ( file_exists( $reports_file ) ) {
                            require_once $reports_file;
                        }
                        if ( function_exists( 'educore_student_attendance_log_view' ) ) {
                            educore_student_attendance_log_view( $classes );
                        }
                    }
                    break;

                case 'daily':
                case 'roster':
                default:
                    $daily_file = $attendance_dir . 'attendance-daily.php';
                    if ( file_exists( $daily_file ) ) {
                        require_once $daily_file;
                    }
                    if ( function_exists( 'educore_daily_attendance_view' ) ) {
                        educore_daily_attendance_view( $classes, $sections, $filter_class, $filter_section, $filter_date );
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}