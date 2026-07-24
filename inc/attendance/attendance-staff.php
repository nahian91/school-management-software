<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Faculty & Staff Attendance Roster Entry Workspace
 * File: inc/attendance/attendance-staff.php
 */
function educore_staff_attendance_view() {
    global $wpdb;

    $table_staff      = $wpdb->prefix . 'sms_staff';
    $table_attendance = $wpdb->prefix . 'sms_staff_attendance';

    $filter_date = isset( $_REQUEST['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['attendance_date'] ) ) : current_time( 'Y-m-d' );

    // Save Staff Attendance Form Action
    if ( isset( $_POST['educore_save_staff_attendance'] ) && isset( $_POST['educore_staff_att_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_staff_att_nonce'] ) ), 'save_staff_attendance_action' ) ) {
        $attendance_data = isset( $_POST['staff_attendance'] ) ? (array) $_POST['staff_attendance'] : array();
        $saved_count     = 0;

        foreach ( $attendance_data as $staff_id => $status ) {
            $staff_id = intval( $staff_id );
            $status   = sanitize_text_field( wp_unslash( $status ) );

            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table_attendance} WHERE staff_id = %d AND attendance_date = %s",
                $staff_id, $filter_date
            ) );

            if ( $existing_id ) {
                $wpdb->update(
                    $table_attendance,
                    array( 'status' => $status, 'recorded_by' => get_current_user_id() ),
                    array( 'id' => $existing_id ),
                    array( '%s', '%d' ),
                    array( '%d' )
                );
            } else {
                $wpdb->insert(
                    $table_attendance,
                    array(
                        'staff_id'        => $staff_id,
                        'attendance_date' => $filter_date,
                        'status'          => $status,
                        'recorded_by'     => get_current_user_id()
                    ),
                    array( '%d', '%s', '%s', '%d' )
                );
            }
            $saved_count++;
        }

        echo '<div class="afdp-success-banner"><span class="dashicons dashicons-yes-alt"></span> ' . sprintf( esc_html__( 'Staff attendance updated for %d employees.', 'ifsedu-sms' ), $saved_count ) . '</div>';
    }

    // Fetch Active Staff Members
    $staff_members = $wpdb->get_results( "SELECT id, staff_id, full_name, designation FROM {$table_staff} WHERE status = 'Active' ORDER BY full_name ASC" );

    // Fetch Existing Attendance Records for Date
    $attendance_states = array();
    if ( ! empty( $staff_members ) ) {
        $staff_ids    = wp_list_pluck( $staff_members, 'id' );
        $placeholders = implode( ',', array_fill( 0, count( $staff_ids ), '%d' ) );
        
        $raw_states = $wpdb->get_results( $wpdb->prepare(
            "SELECT staff_id, status FROM {$table_attendance} WHERE attendance_date = %s AND staff_id IN ($placeholders)",
            array_merge( array( $filter_date ), $staff_ids )
        ), OBJECT_K );

        foreach ( $raw_states as $sid => $obj ) {
            $attendance_states[ $sid ] = $obj->status;
        }
    }
    ?>

    <div class="dpt-bento-card">
        <form method="GET" action="" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            <input type="hidden" name="sub" value="staff">

            <div style="display:flex; gap:16px; align-items:flex-end;">
                <div class="dpt-form-group" style="flex:1;">
                    <label class="dpt-form-label"><?php esc_html_e( 'Target Date', 'ifsedu-sms' ); ?> *</label>
                    <input type="date" name="attendance_date" class="dpt-input-field" value="<?php echo esc_attr( $filter_date ); ?>" required>
                </div>
                <button type="submit" class="dpt-btn-submit-trigger"><?php esc_html_e( 'Load Staff List', 'ifsedu-sms' ); ?></button>
            </div>
        </form>

        <?php if ( ! empty( $staff_members ) ) : ?>
            <form method="POST" action="">
                <?php wp_nonce_field( 'save_staff_attendance_action', 'educore_staff_att_nonce' ); ?>
                <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                <div class="dpt-table-responsive">
                    <table class="dpt-attendance-matrix-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Staff ID', 'ifsedu-sms' ); ?></th>
                                <th><?php esc_html_e( 'Full Name', 'ifsedu-sms' ); ?></th>
                                <th><?php esc_html_e( 'Designation', 'ifsedu-sms' ); ?></th>
                                <th style="text-align:center;"><?php esc_html_e( 'Attendance Status', 'ifsedu-sms' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $staff_members as $st ) : 
                                $st_id  = (int) $st->id;
                                $status = isset( $attendance_states[ $st_id ] ) ? $attendance_states[ $st_id ] : 'Present';
                            ?>
                                <tr>
                                    <td><code><?php echo esc_html( $st->staff_id ); ?></code></td>
                                    <td><strong style="color:#0f172a;"><?php echo esc_html( $st->full_name ); ?></strong></td>
                                    <td><?php echo esc_html( $st->designation ? $st->designation : 'Faculty' ); ?></td>
                                    <td style="text-align:center;">
                                        <div class="afdp-checkbox-group">
                                            <input type="radio" class="afdp-checkbox-item" name="staff_attendance[<?php echo $st_id; ?>]" id="st_pres_<?php echo $st_id; ?>" value="Present" <?php checked( $status, 'Present' ); ?>>
                                            <label class="afdp-checkbox-label" for="st_pres_<?php echo $st_id; ?>"><?php esc_html_e( 'Present', 'ifsedu-sms' ); ?></label>

                                            <input type="radio" class="afdp-checkbox-item" name="staff_attendance[<?php echo $st_id; ?>]" id="st_abs_<?php echo $st_id; ?>" value="Absent" <?php checked( $status, 'Absent' ); ?>>
                                            <label class="afdp-checkbox-label" for="st_abs_<?php echo $st_id; ?>"><?php esc_html_e( 'Absent', 'ifsedu-sms' ); ?></label>

                                            <input type="radio" class="afdp-checkbox-item" name="staff_attendance[<?php echo $st_id; ?>]" id="st_late_<?php echo $st_id; ?>" value="Late" <?php checked( $status, 'Late' ); ?>>
                                            <label class="afdp-checkbox-label" for="st_late_<?php echo $st_id; ?>"><?php esc_html_e( 'Late', 'ifsedu-sms' ); ?></label>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:20px; text-align:right;">
                    <button type="submit" name="educore_save_staff_attendance" class="dpt-btn-submit-trigger" style="padding: 0 32px; height: 44px;">
                        <?php esc_html_e( 'Save Staff Attendance', 'ifsedu-sms' ); ?>
                    </button>
                </div>
            </form>
        <?php else : ?>
            <div class="afdp-fallback-card"><span class="dashicons dashicons-warning"></span><p><?php esc_html_e( 'No active staff records found.', 'ifsedu-sms' ); ?></p></div>
        <?php endif; ?>
    </div>
    <?php
}