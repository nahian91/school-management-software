<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Merit List & Position Roster Module View
 * File: inc/results/exams-merit.php
 * Custom Prefixes Applied: dpt-, afdp-
 */
function educore_merit_list_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Filter Parameters Sanitization
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