<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Monthly Attendance Summary Audit & Reports
 * File: inc/attendance/attendance-monthly.php
 */
function educore_monthly_attendance_summary_view( $classes, $sections, $filter_class, $filter_section ) {
    global $wpdb;

    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';

    $selected_month = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : current_time( 'Y-m' );
    $start_date     = $selected_month . '-01';
    $end_date       = date( 'Y-m-t', strtotime( $start_date ) );

    $students     = array();
    $summary_data = array();

    if ( ! empty( $filter_class ) ) {
        $query = "SELECT id, student_id, full_name, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $params = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $query .= " AND section_name = %s";
            $params[] = $filter_section;
        }

        $query .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $students = $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );

        if ( ! empty( $students ) ) {
            $student_ids  = wp_list_pluck( $students, 'id' );
            $placeholders = implode( ',', array_fill( 0, count( $student_ids ), '%d' ) );

            $raw_summary = $wpdb->get_results( $wpdb->prepare(
                "SELECT student_id, status, COUNT(*) as count_total
                 FROM {$table_attendance}
                 WHERE attendance_date BETWEEN %s AND %s AND student_id IN ($placeholders)
                 GROUP BY student_id, status",
                array_merge( array( $start_date, $end_date ), $student_ids )
            ) );

            foreach ( $raw_summary as $row ) {
                $summary_data[ $row->student_id ][ $row->status ] = (int) $row->count_total;
            }
        }
    }
    ?>

    <!-- Monthly Filter Control Bento Card -->
    <div class="dpt-bento-card no-print">
        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            <input type="hidden" name="sub" value="monthly">

            <div class="dpt-form-row">
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Target Month', 'ifsedu-sms' ); ?> *</label>
                    <input type="month" name="month" class="dpt-input-field" value="<?php echo esc_attr( $selected_month ); ?>" required>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Class', 'ifsedu-sms' ); ?> *</label>
                    <select name="class_name" id="educore_attendance_class_select" class="dpt-select-field" required>
                        <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Section', 'ifsedu-sms' ); ?></label>
                    <select name="section_name" id="educore_attendance_section_select" class="dpt-select-field">
                        <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $sections as $sec ) : ?>
                            <option value="<?php echo esc_attr( $sec ); ?>" <?php selected( $filter_section, $sec ); ?>><?php echo esc_html( $sec ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-form-group">
                    <button type="submit" class="dpt-btn-submit-trigger" style="width: 100%;"><?php esc_html_e( 'Generate Monthly Audit', 'ifsedu-sms' ); ?></button>
                </div>
            </div>
        </form>
    </div>

    <?php if ( ! empty( $filter_class ) && ! empty( $students ) ) : ?>
        <div class="dpt-bento-card">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:20px;">
                <div>
                    <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;"><?php printf( esc_html__( 'Monthly Attendance Audit Statement: %s', 'ifsedu-sms' ), esc_html( date_i18n( 'F Y', strtotime( $start_date ) ) ) ); ?></h3>
                    <span style="color:#64748b; font-size:13px; font-weight:600;"><?php printf( esc_html__( 'Class: %1$s %2$s', 'ifsedu-sms' ), esc_html( $filter_class ), esc_html( $filter_section ? '(' . $filter_section . ')' : '' ) ); ?></span>
                </div>
                <button type="button" onclick="window.print();" class="dpt-btn-submit-trigger no-print" style="width:auto; padding:0 20px; background:#0f172a;">
                    <span class="dashicons dashicons-printer" style="vertical-align:middle;"></span>
                    <?php esc_html_e( 'Print Summary', 'ifsedu-sms' ); ?>
                </button>
            </div>

            <div class="dpt-table-responsive">
                <table class="dpt-attendance-matrix-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Roll', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Student Name', 'ifsedu-sms' ); ?></th>
                            <th style="text-align:center; color:#059669;"><?php esc_html_e( 'Presents', 'ifsedu-sms' ); ?></th>
                            <th style="text-align:center; color:#dc2626;"><?php esc_html_e( 'Absents', 'ifsedu-sms' ); ?></th>
                            <th style="text-align:center; color:#d97706;"><?php esc_html_e( 'Lates', 'ifsedu-sms' ); ?></th>
                            <th style="text-align:right;"><?php esc_html_e( 'Attendance Ratio', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $students as $st ) : 
                            $st_id          = (int) $st->id;
                            $p_cnt          = isset( $summary_data[ $st_id ]['Present'] ) ? $summary_data[ $st_id ]['Present'] : 0;
                            $a_cnt          = isset( $summary_data[ $st_id ]['Absent'] ) ? $summary_data[ $st_id ]['Absent'] : 0;
                            $l_cnt          = isset( $summary_data[ $st_id ]['Late'] ) ? $summary_data[ $st_id ]['Late'] : 0;
                            $total_recorded = $p_cnt + $a_cnt + $l_cnt;
                            $pct            = $total_recorded > 0 ? round( ( $p_cnt / $total_recorded ) * 100, 1 ) : 0;
                            $pct_color      = $pct >= 80 ? '#059669' : ( $pct >= 60 ? '#d97706' : '#dc2626' );
                        ?>
                            <tr>
                                <td><strong>#<?php echo esc_html( $st->roll_no ); ?></strong></td>
                                <td><code><?php echo esc_html( $st->student_id ); ?></code></td>
                                <td><strong style="color:#0f172a;"><?php echo esc_html( $st->full_name ); ?></strong></td>
                                <td style="text-align:center; font-weight:800; color:#059669;"><?php echo $p_cnt; ?></td>
                                <td style="text-align:center; font-weight:800; color:#dc2626;"><?php echo $a_cnt; ?></td>
                                <td style="text-align:center; font-weight:800; color:#d97706;"><?php echo $l_cnt; ?></td>
                                <td style="text-align:right;">
                                    <strong style="color:<?php echo $pct_color; ?>;"><?php echo $pct; ?>%</strong>
                                    <div style="height:4px; background:#e2e8f0; border-radius:10px; overflow:hidden; margin-top:4px;">
                                        <div style="width:<?php echo $pct; ?>%; height:100%; background:<?php echo $pct_color; ?>;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ( ! empty( $filter_class ) ) : ?>
        <div class="afdp-fallback-card"><span class="dashicons dashicons-warning"></span><p><?php esc_html_e( 'No active student records found for the selected Class/Section.', 'ifsedu-sms' ); ?></p></div>
    <?php else : ?>
        <div class="afdp-fallback-card"><span class="dashicons dashicons-info"></span><p><?php esc_html_e( 'Select a Month and Class above to generate the attendance audit statement.', 'ifsedu-sms' ); ?></p></div>
    <?php endif;
}