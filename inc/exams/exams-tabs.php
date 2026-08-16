<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

/**
 * High-End Academic Examinations Sub-Navigation Engine & Router Matrix
 * File: inc/exams.php
 * Subtabs: All Examinations, Add Examination, Exam Routine
 */
function educore_exams_tab() {
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( wp_unslash( $_GET['sub'] ) ) : 'list';

    // Construct URLs for top submenu links
    $all_exams_url    = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=list' );
    $add_exam_url     = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=add' );
    $exam_routine_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=routine' );
    ?>

    <style>
        .dpt-exams-nav-root {
            margin: 20px 20px 24px 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .afdp-top-nav-wrapper {
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
            border-color: #e2e8f0;
            color: #475569;
        }
        .dpt-nav-link-inactive:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .dpt-module-viewport-container {
            width: 100%;
        }

        @media print {
            .no-print, .afdp-top-nav-wrapper {
                display: none !important;
            }
        }
    </style>

    <div class="dpt-exams-nav-root">
        
        <!-- Top Sub-Navigation Menu Bar -->
        <div class="afdp-top-nav-wrapper no-print">
            <div class="dpt-nav-button-group">
                <!-- 1. All Examinations -->
                <a href="<?php echo esc_url( $all_exams_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'list' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-welcome-write-blog"></span>
                    <?php esc_html_e( 'All Examinations', 'ifsedu-sms' ); ?>
                </a>
                
                <!-- 2. Add Examination -->
                <a href="<?php echo esc_url( $add_exam_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'add' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Add Examination', 'ifsedu-sms' ); ?>
                </a>
                
                <!-- 3. Exam Routine -->
                <a href="<?php echo esc_url( $exam_routine_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'routine' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e( 'Exam Routine', 'ifsedu-sms' ); ?>
                </a>
            </div>

            <?php if ( in_array( $sub_tab, array( 'edit' ), true ) ) : ?>
                <span style="background:#eff6ff; color:#1d4ed8; font-size:12px; font-weight:700; padding:5px 12px; border-radius:20px; border:1px solid #bfdbfe;">
                    <?php esc_html_e( 'Editing Exam Scheme', 'ifsedu-sms' ); ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Viewport Loader -->
        <div class="dpt-module-viewport-container">
            <?php
            switch ( $sub_tab ) {
                case 'add':
                case 'edit':
                    $add_edit_files = array(
                        EDUCORE_PATH . 'inc/exams/exam-add-edit.php',
                        EDUCORE_PATH . 'inc/exams/exams-add-edit.php'
                    );
                    foreach ( $add_edit_files as $file ) {
                        if ( file_exists( $file ) ) {
                            require_once $file;
                            break;
                        }
                    }
                    if ( function_exists( 'educore_exam_add_edit_view' ) ) {
                        educore_exam_add_edit_view();
                    } elseif ( function_exists( 'educore_exams_add_view' ) ) {
                        educore_exams_add_view();
                    }
                    break;

                case 'routine':
                    $routine_files = array(
                        EDUCORE_PATH . 'inc/exams/exam-routine.php',
                        EDUCORE_PATH . 'inc/exams/exams-routine.php'
                    );
                    foreach ( $routine_files as $file ) {
                        if ( file_exists( $file ) ) {
                            require_once $file;
                            break;
                        }
                    }
                    if ( function_exists( 'educore_exam_routine_view' ) ) {
                        educore_exam_routine_view();
                    } elseif ( function_exists( 'educore_exams_routine_view' ) ) {
                        educore_exams_routine_view();
                    }
                    break;

                case 'list':
                default:
                    $list_files = array(
                        EDUCORE_PATH . 'inc/exams/exam-list.php',
                        EDUCORE_PATH . 'inc/exams/exams-list.php',
                        EDUCORE_PATH . 'inc/exams/exams-list-view.php'
                    );
                    foreach ( $list_files as $file ) {
                        if ( file_exists( $file ) ) {
                            require_once $file;
                            break;
                        }
                    }
                    if ( function_exists( 'educore_exam_list_view' ) ) {
                        educore_exam_list_view();
                    } elseif ( function_exists( 'educore_exams_list_view' ) ) {
                        educore_exams_list_view();
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}