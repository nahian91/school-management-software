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
$results_dir = plugin_dir_path( __FILE__ ) . 'results/';
if ( file_exists( $results_dir . 'exams-marks.php' ) ) {
    require_once $results_dir . 'exams-marks.php';
}
if ( file_exists( $results_dir . 'exams-report.php' ) ) {
    require_once $results_dir . 'exams-report.php';
}

function educore_results_tab() {
    // Capability Security Check
    if ( ! current_user_can( 'manage_options' ) && ! ( class_exists( 'IFSEdu_School_Management_System' ) && IFSEdu_School_Management_System::has_access( array( 'teacher' ) ) ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access examination marks & results.', 'ifsedu-sms' ) );
    }

    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( wp_unslash( $_GET['sub'] ) ) : 'marks';

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
                <!-- 1. Marks Entry Matrix -->
                <a href="<?php echo esc_url( $marks_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'marks' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e( 'Marks Entry Matrix', 'ifsedu-sms' ); ?>
                </a>
                
                <!-- 2. Progress & Tabulation Sheet -->
                <a href="<?php echo esc_url( $report_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'report' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Progress & Tabulation Sheet', 'ifsedu-sms' ); ?>
                </a>

                <!-- 3. Merit List & Positions -->
                <a href="<?php echo esc_url( $merit_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'merit' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-awards"></span>
                    <?php esc_html_e( 'Merit List & Positions', 'ifsedu-sms' ); ?>
                </a>
            </div>

            <div>
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
                    educore_merit_list_view();
                    break;

                case 'marks':
                default:
                    if ( function_exists( 'educore_exams_marks_view' ) ) {
                        educore_exams_marks_view();
                    } else {
                        echo '<div class="afdp-notice-card">' . esc_html__( 'Marks Entry Matrix module is initializing.', 'ifsedu-sms' );
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}

// --------------------------------------------------------------------------
// MERIT LIST & POSITION ROSTER MODULE VIEW
// --------------------------------------------------------------------------
function educore_merit_list_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Filter Parameters
    $filter_exam    = isset( $_GET['exam_id'] ) ? intval( $_GET['exam_id'] ) : 0;
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';

    $exams = $wpdb->get_results( "SELECT id, exam_name, class_name FROM {$table_exams} ORDER BY id DESC" );
    $all_classes_raw = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );

    $school_name = get_bloginfo( 'name' );
    if ( empty( $school_name ) || $school_name === 'WordPress' ) {
        $school_name = 'Green Gems International School & College';
    }
    
    $current_uri = remove_query_arg( array( 'status' ), $_SERVER['REQUEST_URI'] );
    $base_url    = esc_url_raw( $current_uri );
    ?>

    <!-- Merit List Filter Console -->
    <div class="dpt-bento-card no-print">
        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="results">
            <input type="hidden" name="sub" value="merit">

            <div style="display: grid; grid-template-columns: 2fr 2fr 2fr 1fr; gap: 16px; align-items: flex-end;">
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Select Exam', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
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
                    <label class="dpt-form-label"><?php esc_html_e( 'Class Name', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="class_name" class="dpt-select-field" required>
                        <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $all_classes_raw as $cls_name ) : ?>
                            <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                                <?php echo esc_html( $cls_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Section (Optional)', 'ifsedu-sms' ); ?></label>
                    <input type="text" name="section_name" class="dpt-input-field" placeholder="e.g. Science / A" value="<?php echo esc_attr( $filter_section ); ?>">
                </div>

                <div>
                    <button type="submit" class="dpt-btn-submit-trigger" style="height: 42px;">
                        <span class="dashicons dashicons-awards"></span>
                        <?php esc_html_e( 'Generate Merit List', 'ifsedu-sms' ); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php
    if ( $filter_exam > 0 && ! empty( $filter_class ) ) {
        $exam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_exams} WHERE id = %d", $filter_exam ) );

        $sql = "SELECT * FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $params = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $sql .= " AND section_name = %s";
            $params[] = $filter_section;
        }

        $students = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

        if ( ! empty( $students ) ) {
            $ranked_students = array();

            foreach ( $students as $s ) {
                $results = $wpdb->get_results( $wpdb->prepare(
                    "SELECT obtained_marks, grade, gpa FROM {$table_results} WHERE exam_id = %d AND student_id = %d",
                    $filter_exam, $s->id
                ) );

                if ( empty( $results ) ) continue;

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

                $avg_gpa = ( $sub_count > 0 ) ? ( $sum_gpa / $sub_count ) : 0;
                $final_gpa = $has_fail ? 0.00 : round( $avg_gpa, 2 );

                $ranked_students[] = array(
                    'student' => $s,
                    'total'   => $total_obt,
                    'gpa'     => $final_gpa,
                    'failed'  => $has_fail
                );
            }

            // Sort by Pass/Fail (Pass first), then GPA (DESC), then Total Marks (DESC)
            usort( $ranked_students, function( $a, $b ) {
                if ( $a['failed'] !== $b['failed'] ) {
                    return $a['failed'] ? 1 : -1;
                }
                if ( $b['gpa'] != $a['gpa'] ) {
                    return ( $b['gpa'] < $a['gpa'] ) ? -1 : 1;
                }
                return ( $b['total'] < $a['total'] ) ? -1 : 1;
            });
            ?>

            <div style="text-align: center; margin-bottom: 24px;" class="no-print">
                <button onclick="window.print();" class="dpt-btn-submit-trigger" style="width: auto; padding: 0 32px; font-size: 14px;">
                    <span class="dashicons dashicons-printer"></span>
                    <?php esc_html_e( 'Print Merit List Roster', 'ifsedu-sms' ); ?>
                </button>
            </div>

            <div class="afdp-tabulation-container">
                <div style="text-align: center; margin-bottom: 24px; border-bottom: 2px solid #006a4e; padding-bottom: 16px;">
                    <h3 style="margin: 0; font-weight: 800; color: #006a4e; text-transform: uppercase; font-size: 20px;"><?php echo esc_html( $school_name ); ?></h3>
                    <h5 style="margin: 6px 0 0 0; font-weight: 700; color: #1e293b; font-size: 14px;"><?php echo esc_html( $exam->exam_name ); ?> &mdash; <?php esc_html_e( 'Official Merit Position Ranking', 'ifsedu-sms' ); ?></h5>
                    <span style="display: inline-block; background: #f1f5f9; color: #475569; padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-top: 6px; border: 1px solid #cbd5e1;">
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
                                <th style="width: 10%;"><?php esc_html_e( 'Position', 'ifsedu-sms' ); ?></th>
                                <th style="width: 10%;"><?php esc_html_e( 'Roll', 'ifsedu-sms' ); ?></th>
                                <th style="width: 15%;"><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                                <th style="text-align: left;"><?php esc_html_e( 'Student Full Name', 'ifsedu-sms' ); ?></th>
                                <th style="width: 15%;"><?php esc_html_e( 'Total Score', 'ifsedu-sms' ); ?></th>
                                <th style="width: 12%;"><?php esc_html_e( 'GPA', 'ifsedu-sms' ); ?></th>
                                <th style="width: 12%;"><?php esc_html_e( 'Result Status', 'ifsedu-sms' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $position = 1;
                            foreach ( $ranked_students as $item ) : 
                                $s = $item['student'];
                            ?>
                            <tr>
                                <td>
                                    <span style="font-weight: 800; font-size: 14px; color: <?php echo $position === 1 ? '#059669' : '#0f172a'; ?>;">
                                        <?php 
                                            if ( $item['failed'] ) {
                                                echo '—';
                                            } else {
                                                echo '#' . $position++;
                                            }
                                        ?>
                                    </span>
                                </td>
                                <td><strong>#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                <td><code><?php echo esc_html( $s->student_id ); ?></code></td>
                                <td style="text-align: left; font-weight: 700; color: #0f172a;"><?php echo esc_html( $s->full_name ); ?></td>
                                <td><strong><?php echo floatval( $item['total'] ); ?></strong></td>
                                <td style="font-weight: 800; color: #006a4e;"><?php echo number_format( floatval( $item['gpa'] ), 2 ); ?></td>
                                <td>
                                    <span style="padding: 3px 12px; border-radius: 20px; font-weight: 800; font-size: 11px; background: <?php echo $item['failed'] ? '#fef2f2' : '#ecfdf5'; ?>; color: <?php echo $item['failed'] ? '#dc2626' : '#059669'; ?>; border: 1px solid <?php echo $item['failed'] ? '#fecaca' : '#a7f3d0'; ?>;">
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
                    <div class="afdp-sign-line"><?php esc_html_e( 'Principal / Headmaster', 'ifsedu-sms' ); ?></div>
                </div>
            </div>
            <?php
        } else {
            echo '<div class="afdp-status-banner">' . esc_html__( 'No active students found matching the selected criteria.', 'ifsedu-sms' ) . '</div>';
        }
    }
}