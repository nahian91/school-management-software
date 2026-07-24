<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Daily Student Attendance Entry Workspace
 * File: inc/attendance/attendance-daily.php
 */
function educore_daily_attendance_view( $classes, $sections, $filter_class, $filter_section, $filter_date ) {
    global $wpdb;

    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';

    // Handle Attendance Form Commit
    if ( isset( $_POST['educore_save_attendance'] ) && isset( $_POST['educore_attendance_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_attendance_nonce'] ) ), 'save_attendance_action' ) ) {
        $attendance_date = isset( $_POST['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_POST['attendance_date'] ) ) : current_time( 'Y-m-d' );
        $attendance_data = isset( $_POST['attendance'] ) ? (array) $_POST['attendance'] : array();
        $current_user_id = get_current_user_id();

        $saved_count = 0;

        if ( ! empty( $attendance_data ) ) {
            $target_student_ids = array_map( 'intval', array_keys( $attendance_data ) );
            $ids_placeholder    = implode( ',', array_fill( 0, count( $target_student_ids ), '%d' ) );

            $prep_query = $wpdb->prepare(
                "SELECT student_id, id FROM {$table_attendance} WHERE attendance_date = %s AND student_id IN ($ids_placeholder)",
                array_merge( array( $attendance_date ), $target_student_ids )
            );

            $existing_records = $wpdb->get_results( $prep_query, OBJECT_K );

            foreach ( $attendance_data as $student_id => $status ) {
                $student_id = intval( $student_id );
                $status     = sanitize_text_field( wp_unslash( $status ) );

                if ( isset( $existing_records[ $student_id ] ) ) {
                    $wpdb->update(
                        $table_attendance,
                        array(
                            'status'      => $status,
                            'recorded_by' => $current_user_id
                        ),
                        array( 'id' => intval( $existing_records[ $student_id ]->id ) ),
                        array( '%s', '%d' ),
                        array( '%d' )
                    );
                } else {
                    $wpdb->insert(
                        $table_attendance,
                        array(
                            'student_id'      => $student_id,
                            'attendance_date' => $attendance_date,
                            'status'          => $status,
                            'remarks'         => '',
                            'recorded_by'     => $current_user_id
                        ),
                        array( '%d', '%s', '%s', '%s', '%d' )
                    );
                }
                $saved_count++;
            }
        }

        echo '<div class="afdp-success-banner"><span class="dashicons dashicons-yes-alt"></span> ' . sprintf( esc_html__( 'Attendance records successfully updated for %d students.', 'ifsedu-sms' ), intval( $saved_count ) ) . '</div>';
    }
    ?>

    <!-- Daily Filter Controls Bento Card -->
    <div class="dpt-bento-card no-print">
        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            <input type="hidden" name="sub" value="daily">
            
            <div class="dpt-form-row">
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Select Target Date', 'ifsedu-sms' ); ?> *</label>
                    <input type="date" name="attendance_date" class="dpt-input-field" value="<?php echo esc_attr( $filter_date ); ?>" required>
                </div>
                
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Academic Class', 'ifsedu-sms' ); ?> *</label>
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
                    <button type="submit" class="dpt-btn-submit-trigger" style="width: 100%;"><?php esc_html_e( 'Load Students', 'ifsedu-sms' ); ?></button>
                </div>
            </div>
        </form>
    </div>

    <?php
    if ( ! empty( $filter_class ) ) {
        $query = "SELECT id, student_id, full_name, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $sql_args = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $query .= " AND section_name = %s";
            $sql_args[] = $filter_section;
        }

        $query .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $students = $wpdb->get_results( $wpdb->prepare( $query, ...$sql_args ) );

        if ( $students ) {
            $student_ids  = wp_list_pluck( $students, 'id' );
            $placeholders = implode( ',', array_fill( 0, count( $student_ids ), '%d' ) );
            
            $cached_attendance_query = $wpdb->prepare(
                "SELECT student_id, status FROM {$table_attendance} WHERE attendance_date = %s AND student_id IN ($placeholders)",
                array_merge( array( $filter_date ), $student_ids )
            );
            $loaded_attendance_states = $wpdb->get_results( $cached_attendance_query, OBJECT_K );
            ?>
            <div class="dpt-bento-card">
                
                <div class="afdp-roster-meta-bar">
                    <div class="afdp-roster-title">
                        <h4 style="margin:0; font-weight:800;"><?php esc_html_e( 'Mark Attendance:', 'ifsedu-sms' ); ?> <span style="color: #006a4e;"><?php echo esc_html( $filter_class . ( $filter_section ? ' (' . $filter_section . ')' : '' ) ); ?></span></h4>
                        <small style="color:#64748b; font-weight:600;"><?php esc_html_e( 'Target Date:', 'ifsedu-sms' ); ?> <?php echo esc_html( date_i18n( 'd F, Y', strtotime( $filter_date ) ) ); ?></small>
                    </div>
                    
                    <div class="dpt-counter-cluster">
                        <span class="dpt-badge-pill dpt-badge-total"><?php esc_html_e( 'Total:', 'ifsedu-sms' ); ?> <span id="cnt-total"><?php echo count( $students ); ?></span></span>
                        <span class="dpt-badge-pill dpt-badge-present"><?php esc_html_e( 'Present:', 'ifsedu-sms' ); ?> <span id="cnt-present">0</span></span>
                        <span class="dpt-badge-pill dpt-badge-absent"><?php esc_html_e( 'Absent:', 'ifsedu-sms' ); ?> <span id="cnt-absent">0</span></span>
                        <span class="dpt-badge-pill dpt-badge-late"><?php esc_html_e( 'Late:', 'ifsedu-sms' ); ?> <span id="cnt-late">0</span></span>
                    </div>
                </div>

                <div class="afdp-bulk-automation-row no-print">
                    <div style="font-size:13px; font-weight:700; color:#475569; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Bulk Operations:', 'ifsedu-sms' ); ?>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="dpt-bulk-btn" data-target-status="Present"><?php esc_html_e( 'Set All Present', 'ifsedu-sms' ); ?></button>
                        <button type="button" class="dpt-bulk-btn" data-target-status="Absent"><?php esc_html_e( 'Set All Absent', 'ifsedu-sms' ); ?></button>
                        <button type="button" class="dpt-bulk-btn" data-target-status="Late"><?php esc_html_e( 'Set All Late', 'ifsedu-sms' ); ?></button>
                    </div>
                </div>
                
                <form method="POST" action="" id="educoreAttendanceSubmitEngine">
                    <?php wp_nonce_field( 'save_attendance_action', 'educore_attendance_nonce' ); ?>
                    <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                    <div class="dpt-table-responsive">
                        <table class="dpt-attendance-matrix-table">
                            <thead>
                                <tr>
                                    <th style="width: 12%;"><?php esc_html_e( 'Roll No', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 18%;"><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 35%;"><?php esc_html_e( 'Student Name', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 35%; text-align: center;"><?php esc_html_e( 'Attendance Status', 'ifsedu-sms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ( $students as $student ) : 
                                    $student_internal_id = intval( $student->id );
                                    $current_status = isset( $loaded_attendance_states[ $student_internal_id ] ) ? $loaded_attendance_states[ $student_internal_id ]->status : 'Present';
                                ?>
                                <tr class="student-attendance-row">
                                    <td><strong># <?php echo esc_html( $student->roll_no ); ?></strong></td>
                                    <td><code style="color: #0f172a; font-weight: 700; background: #f1f5f9; padding: 3px 8px; border-radius: 6px; border: 1px solid #e2e8f0;"><?php echo esc_html( $student->student_id ); ?></code></td>
                                    <td><span style="font-weight: 700; color: #0f172a;"><?php echo esc_html( $student->full_name ); ?></span></td>
                                    <td style="text-align: center;">
                                        <div class="afdp-checkbox-group">
                                            <input type="radio" class="afdp-checkbox-item status-radio-node" name="attendance[<?php echo $student_internal_id; ?>]" id="present_<?php echo $student_internal_id; ?>" value="Present" <?php checked( $current_status, 'Present' ); ?>>
                                            <label class="afdp-checkbox-label" for="present_<?php echo $student_internal_id; ?>">
                                                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                                <?php esc_html_e( 'Present', 'ifsedu-sms' ); ?>
                                            </label>

                                            <input type="radio" class="afdp-checkbox-item status-radio-node" name="attendance[<?php echo $student_internal_id; ?>]" id="absent_<?php echo $student_internal_id; ?>" value="Absent" <?php checked( $current_status, 'Absent' ); ?>>
                                            <label class="afdp-checkbox-label" for="absent_<?php echo $student_internal_id; ?>">
                                                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                                                <?php esc_html_e( 'Absent', 'ifsedu-sms' ); ?>
                                            </label>

                                            <input type="radio" class="afdp-checkbox-item status-radio-node" name="attendance[<?php echo $student_internal_id; ?>]" id="late_<?php echo $student_internal_id; ?>" value="Late" <?php checked( $current_status, 'Late' ); ?>>
                                            <label class="afdp-checkbox-label" for="late_<?php echo $student_internal_id; ?>">
                                                <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                                                <?php esc_html_e( 'Late', 'ifsedu-sms' ); ?>
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 16px; text-align: right;">
                        <button type="submit" name="educore_save_attendance" class="dpt-btn-submit-trigger" style="padding: 0 32px; height: 44px; font-size: 14px;">
                            <?php esc_html_e( 'Save Attendance', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </form>
            </div>
            <?php
        } else {
            echo '<div class="afdp-fallback-card"><span class="dashicons dashicons-warning"></span><p>' . esc_html__( 'No active students found matching current Class/Section requirements.', 'ifsedu-sms' ) . '</p></div>';
        }
    } else {
        echo '<div class="afdp-fallback-card"><span class="dashicons dashicons-info"></span><p>' . esc_html__( 'Select a target Date and Class above to load the attendance workspace.', 'ifsedu-sms' ) . '</p></div>';
    }
}