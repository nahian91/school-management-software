<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Individual Student Attendance History Log Audit
 * File: inc/attendance/attendance-reports.php
 */
function educore_student_attendance_log_view( $classes ) {
    global $wpdb;

    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';

    $filter_student_id = isset( $_GET['student_id'] ) ? absint( $_GET['student_id'] ) : 0;
    $start_date        = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : date( 'Y-m-01' );
    $end_date          = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : current_time( 'Y-m-d' );

    $student = null;
    $logs    = array();

    if ( $filter_student_id > 0 ) {
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_students} WHERE id = %d", $filter_student_id ) );
        if ( $student ) {
            $logs = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table_attendance} WHERE student_id = %d AND attendance_date BETWEEN %s AND %s ORDER BY attendance_date DESC",
                $filter_student_id, $start_date, $end_date
            ) );
        }
    }

    $all_students = $wpdb->get_results( "SELECT id, full_name, student_id, class_name FROM {$table_students} WHERE status = 'Active' ORDER BY full_name ASC" );
    ?>

    <div class="dpt-bento-card no-print">
        <form method="GET" action="">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            <input type="hidden" name="sub" value="reports">

            <div class="dpt-form-row">
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Select Student', 'ifsedu-sms' ); ?> *</label>
                    <select name="student_id" class="dpt-select-field" required>
                        <option value=""><?php esc_html_e( '-- Choose Student --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $all_students as $st ) : ?>
                            <option value="<?php echo intval( $st->id ); ?>" <?php selected( $filter_student_id, $st->id ); ?>>
                                <?php echo esc_html( $st->full_name . ' (' . $st->class_name . ' - ' . $st->student_id . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'From Date', 'ifsedu-sms' ); ?></label>
                    <input type="date" name="start_date" class="dpt-input-field" value="<?php echo esc_attr( $start_date ); ?>">
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'To Date', 'ifsedu-sms' ); ?></label>
                    <input type="date" name="end_date" class="dpt-input-field" value="<?php echo esc_attr( $end_date ); ?>">
                </div>

                <div class="dpt-form-group">
                    <button type="submit" class="dpt-btn-submit-trigger" style="width: 100%;"><?php esc_html_e( 'Fetch Log', 'ifsedu-sms' ); ?></button>
                </div>
            </div>
        </form>
    </div>

    <?php if ( $student ) : ?>
        <div class="dpt-bento-card">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:20px;">
                <div>
                    <h3 style="margin:0; font-weight:800;"><?php echo esc_html( $student->full_name ); ?> <small>(ID: <?php echo esc_html( $student->student_id ); ?>)</small></h3>
                    <span style="color:#64748b; font-size:13px; font-weight:600;"><?php printf( esc_html__( 'Class: %1$s | Log Period: %2$s to %3$s', 'ifsedu-sms' ), esc_html( $student->class_name ), esc_html( $start_date ), esc_html( $end_date ) ); ?></span>
                </div>
                <button type="button" onclick="window.print();" class="dpt-btn-submit-trigger no-print" style="width:auto; padding:0 20px; background:#0f172a;">
                    <span class="dashicons dashicons-printer" style="vertical-align:middle;"></span>
                    <?php esc_html_e( 'Print Log', 'ifsedu-sms' ); ?>
                </button>
            </div>

            <div class="dpt-table-responsive">
                <table class="dpt-attendance-matrix-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Day', 'ifsedu-sms' ); ?></th>
                            <th style="text-align:right;"><?php esc_html_e( 'Recorded Status', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $logs ) ) : foreach ( $logs as $l ) : 
                            $time         = strtotime( $l->attendance_date );
                            $status_color = $l->status === 'Present' ? '#059669' : ( $l->status === 'Absent' ? '#dc2626' : '#d97706' );
                        ?>
                            <tr>
                                <td><strong><?php echo date_i18n( 'd M, Y', $time ); ?></strong></td>
                                <td><?php echo date_i18n( 'l', $time ); ?></td>
                                <td style="text-align:right;"><strong style="color:<?php echo $status_color; ?>;"><?php echo esc_html( $l->status ); ?></strong></td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="3" style="text-align:center; color:#94a3b8; padding:30px;"><?php esc_html_e( 'No attendance logs recorded for this date range.', 'ifsedu-sms' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif;
}