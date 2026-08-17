<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Academic Results & Evaluation Matrix Module Router
 * File: inc/results.php
 * Subtabs: Marks Entry Matrix, Progress & Tabulation Sheet, Merit List & Positions
 * Custom Prefixes Applied: dpt-, afdp-
 */

// Load Modular Dependency Sub-Files
$results_dir = defined( 'EDUCORE_PATH' ) ? EDUCORE_PATH . 'inc/results/' : plugin_dir_path( __FILE__ ) . 'results/';

if ( file_exists( $results_dir . 'exams-marks.php' ) ) {
    require_once $results_dir . 'exams-marks.php';
}
if ( file_exists( $results_dir . 'exams-report.php' ) ) {
    require_once $results_dir . 'exams-report.php';
}
if ( file_exists( $results_dir . 'exams-merit.php' ) ) {
    require_once $results_dir . 'exams-merit.php';
}

function educore_results_tab() {
    global $wpdb;

    $current_user = wp_get_current_user();
    $table_staff  = $wpdb->prefix . 'sms_staff';

    // 1. Procedural Role Capability Validations
    $is_admin = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    
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
        wp_die( esc_html__( 'You do not have sufficient permissions to access examination marks & results.', 'ifsedu-sms' ) );
    }

    $sub_tab = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'marks';

    // 2. Role Boundary: Allow Teachers/Staff access to 'marks' and 'report' (Tabulation & Marksheet)
    $allowed_teacher_tabs = array( 'marks', 'report' );
    if ( ! $is_admin && ! in_array( $sub_tab, $allowed_teacher_tabs, true ) ) {
        $sub_tab = 'marks';
    }

    // 3. Query Assigned Classes & Subjects for Logged-In Teacher
    $assigned_teacher_info = array();
    if ( ! $is_admin ) {
        $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';
        $table_subjects         = $wpdb->prefix . 'sms_subjects';
        $table_units            = $wpdb->prefix . 'sms_academic_units';

        $teacher_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table_staff} WHERE wp_user_id = %d OR email = %s OR full_name = %s LIMIT 1",
            $current_user->ID,
            $current_user->user_email,
            $current_user->display_name
        ) );

        if ( $teacher_id ) {
            $assigned_teacher_info = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT u.class_name, u.section_name, s.subject_name, s.subject_code 
                 FROM {$table_teacher_subjects} ts
                 INNER JOIN {$table_units} u ON ts.class_id = u.id
                 INNER JOIN {$table_subjects} s ON ts.subject_id = s.id
                 WHERE ts.teacher_id = %d AND u.class_name != ''
                 ORDER BY CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, s.subject_name ASC",
                $teacher_id
            ) );
        }
    }

    // Construct URLs for Submenu Tabs
    $marks_url  = admin_url( 'admin.php?page=school_management_system&tab=results&sub=marks' );
    $report_url = admin_url( 'admin.php?page=school_management_system&tab=results&sub=report' );
    $merit_url  = admin_url( 'admin.php?page=school_management_system&tab=results&sub=merit' );
    ?>

    <style>
        .dpt-results-nav-root {
            margin: 20px 20px 24px 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        /* Bento Navigation Header Card */
        .afdp-results-nav-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 12px 18px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
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
            gap: 10px;
            flex-wrap: wrap;
        }

        .dpt-nav-link {
            height: 38px;
            padding: 0 16px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
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
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.15);
        }
        .dpt-nav-link-active:hover {
            background: #00523c;
        }

        .dpt-nav-link-inactive {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #475569;
        }
        .dpt-nav-link-inactive:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .dpt-assigned-context-pill {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .dpt-module-viewport-container {
            width: 100%;
        }

        .afdp-notice-card {
            background: #f0fdf4;
            border-left: 4px solid #006a4e;
            padding: 16px 20px;
            border-radius: 0 8px 8px 0;
            color: #15803d;
            font-size: 14px;
            font-weight: 600;
            margin-top: 10px;
        }

        @media print {
            .no-print, .afdp-results-nav-bar {
                display: none !important;
            }
            .dpt-results-nav-root {
                margin: 0 !important;
            }
        }
    </style>

    <div class="dpt-results-nav-root">
        
        <!-- Top Sub-Navigation Menu Bar -->
        <div class="afdp-results-nav-bar no-print">
            <div class="dpt-nav-button-group">
                <!-- 1. Marks Entry Matrix (Accessible by Teachers and Admins) -->
                <a href="<?php echo esc_url( $marks_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'marks' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e( 'Marks Entry Matrix', 'ifsedu-sms' ); ?>
                </a>
                
                <!-- 2. Progress & Tabulation Sheet (Accessible by Teachers and Admins) -->
                <a href="<?php echo esc_url( $report_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'report' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Progress & Tabulation Sheet', 'ifsedu-sms' ); ?>
                </a>

                <!-- 3. Merit List & Positions (Admin Only) -->
                <?php if ( $is_admin ) : ?>
                    <a href="<?php echo esc_url( $merit_url ); ?>" 
                       class="dpt-nav-link <?php echo ( $sub_tab === 'merit' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                        <span class="dashicons dashicons-awards"></span>
                        <?php esc_html_e( 'Merit List & Positions', 'ifsedu-sms' ); ?>
                    </a>
                <?php endif; ?>
            </div>

            <div>
                <?php if ( ! $is_admin && ! empty( $assigned_teacher_info ) ) : ?>
                    <span class="dpt-assigned-context-pill">
                        <span class="dashicons dashicons-welcome-learn-more" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php printf( esc_html__( 'Assigned Subjects: %d Allocations', 'ifsedu-sms' ), count( $assigned_teacher_info ) ); ?>
                    </span>
                <?php else : ?>
                    <span style="background:#f1f5f9; border:1px solid #cbd5e1; color:#475569; font-size:12px; font-weight:700; padding:5px 12px; border-radius:20px;">
                        <span class="dashicons dashicons-analytics" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                        <?php 
                            if ( $sub_tab === 'report' ) {
                                esc_html_e( 'Academic Tabulation View', 'ifsedu-sms' );
                            } elseif ( $sub_tab === 'merit' ) {
                                esc_html_e( 'Position Ranking & Merit List', 'ifsedu-sms' );
                            } else {
                                esc_html_e( 'Subject Marks Evaluation', 'ifsedu-sms' );
                            }
                        ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Sub-View Execution Core -->
        <div class="dpt-module-viewport-container">
            <?php
            switch ( $sub_tab ) {
                case 'report':
                    if ( function_exists( 'educore_exams_report_view' ) ) {
                        educore_exams_report_view();
                    } else {
                        echo '<div class="afdp-notice-card">' . esc_html__( 'Progress & Tabulation Sheet module is initializing.', 'ifsedu-sms' ) . '</div>';
                    }
                    break;

                case 'merit':
                    if ( $is_admin ) {
                        if ( function_exists( 'educore_merit_list_view' ) ) {
                            educore_merit_list_view();
                        } else {
                            echo '<div class="afdp-notice-card">' . esc_html__( 'Merit List module is initializing.', 'ifsedu-sms' ) . '</div>';
                        }
                    }
                    break;

                case 'marks':
                default:
                    if ( function_exists( 'educore_exams_marks_view' ) ) {
                        educore_exams_marks_view();
                    } else {
                        echo '<div class="afdp-notice-card">' . esc_html__( 'Marks Entry Matrix module is initializing.', 'ifsedu-sms' ) . '</div>';
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}