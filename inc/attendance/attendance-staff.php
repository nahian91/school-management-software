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

    $filter_date       = isset( $_REQUEST['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['attendance_date'] ) ) : current_time( 'Y-m-d' );
    $filter_staff_type = isset( $_REQUEST['staff_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['staff_type'] ) ) : '';

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

        echo '<div class="afdp-success-banner" style="background:#ecfdf5; border:1px solid #a7f3d0; color:#047857; padding:12px 18px; border-radius:10px; margin-bottom:20px; font-weight:700; display:flex; align-items:center; gap:8px;"><span class="dashicons dashicons-yes-alt"></span> ' . sprintf( esc_html__( 'Staff attendance successfully updated for %d employees.', 'ifsedu-sms' ), $saved_count ) . '</div>';
    }

    // Fetch Unique Employment Types (staff_type) for the dropdown filter
    $all_staff_types = $wpdb->get_col( "SELECT DISTINCT staff_type FROM {$table_staff} WHERE status = 'Active' AND staff_type != '' ORDER BY staff_type ASC" );

    // Dynamic order column detection for proper staff ordering
    $db_columns = $wpdb->get_col( "DESCRIBE {$table_staff}", 0 );
    $order_col  = 'id';

    if ( in_array( 'order_number', $db_columns, true ) ) {
        $order_col = 'order_number';
    } elseif ( in_array( 'sort_order', $db_columns, true ) ) {
        $order_col = 'sort_order';
    } elseif ( in_array( 'serial_number', $db_columns, true ) ) {
        $order_col = 'serial_number';
    } elseif ( in_array( 'position', $db_columns, true ) ) {
        $order_col = 'position';
    } elseif ( in_array( 'order_no', $db_columns, true ) ) {
        $order_col = 'order_no';
    } elseif ( in_array( 'serial', $db_columns, true ) ) {
        $order_col = 'serial';
    }

    // Build Query for Active Staff Members with optional Employment Type filter
    $query = "SELECT *, {$order_col} AS db_order_number FROM {$table_staff} WHERE status = 'Active'";
    $query_args = array();

    if ( ! empty( $filter_staff_type ) ) {
        $query .= " AND staff_type = %s";
        $query_args[] = $filter_staff_type;
    }

    $query .= " ORDER BY CAST({$order_col} AS UNSIGNED) ASC, {$order_col} ASC, full_name ASC";
    
    if ( ! empty( $query_args ) ) {
        $staff_members = $wpdb->get_results( $wpdb->prepare( $query, ...$query_args ) );
    } else {
        $staff_members = $wpdb->get_results( $query );
    }

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

    <style>
        /* Modern Segmented Status Control Styling */
        .att-segmented-group {
            display: inline-flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            gap: 4px;
        }

        .att-radio-input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
            pointer-events: none;
        }

        .att-status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 12.5px;
            font-weight: 700;
            border-radius: 7px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            color: #64748b;
            user-select: none;
            line-height: 1;
            border: 1px solid transparent;
        }

        .att-status-pill .dashicons {
            font-size: 15px;
            width: 15px;
            height: 15px;
            line-height: 1;
            opacity: 0.7;
        }

        .att-status-pill:hover {
            color: #0f172a;
            background: rgba(255, 255, 255, 0.6);
        }

        /* Active State: Present */
        .att-radio-input[value="Present"]:checked + .att-status-pill {
            background: #059669;
            color: #ffffff;
            border-color: #047857;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }
        .att-radio-input[value="Present"]:checked + .att-status-pill .dashicons {
            opacity: 1;
        }

        /* Active State: Absent */
        .att-radio-input[value="Absent"]:checked + .att-status-pill {
            background: #dc2626;
            color: #ffffff;
            border-color: #b91c1c;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
        }
        .att-radio-input[value="Absent"]:checked + .att-status-pill .dashicons {
            opacity: 1;
        }

        /* Active State: Late */
        .att-radio-input[value="Late"]:checked + .att-status-pill {
            background: #d97706;
            color: #ffffff;
            border-color: #b45309;
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3);
        }
        .att-radio-input[value="Late"]:checked + .att-status-pill .dashicons {
            opacity: 1;
        }
    </style>

    <!-- Staff Filter Controls Bento Card -->
    <div class="dpt-bento-card no-print" style="background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:12px; margin-bottom:24px;">
        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
            <!-- Hidden Page Controllers to Fix Routing -->
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            <input type="hidden" name="sub" value="staff">

            <div class="dpt-form-group" style="flex:1; min-width:200px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Target Date', 'ifsedu-sms' ); ?> *</label>
                <input type="date" name="attendance_date" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" value="<?php echo esc_attr( $filter_date ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
            </div>

            <div class="dpt-form-group" style="flex:1; min-width:220px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Filter by Employment Type', 'ifsedu-sms' ); ?></label>
                <select name="staff_type" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                    <option value=""><?php esc_html_e( '-- All Employment Types --', 'ifsedu-sms' ); ?></option>
                    <?php 
                    $default_staff_types = array( 'Teacher (School)', 'Teacher (College)', 'Officer', 'Staff' );
                    $merged_staff_types  = array_unique( array_merge( $default_staff_types, $all_staff_types ) );

                    foreach ( $merged_staff_types as $st_type ) : ?>
                        <option value="<?php echo esc_attr( $st_type ); ?>" <?php selected( $filter_staff_type, $st_type ); ?>><?php echo esc_html( $st_type ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="dpt-form-group">
                <button type="submit" style="height:40px; padding:0 24px; background:#008f5d; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Load Staff Roster', 'ifsedu-sms' ); ?></button>
            </div>
        </form>
    </div>

    <?php if ( ! empty( $staff_members ) ) : ?>
        <div class="dpt-bento-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
            
            <!-- Meta Bar with Live Counters -->
            <div class="afdp-roster-meta-bar" style="padding:20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                <div class="afdp-roster-title">
                    <h4 style="margin:0; font-weight:800; font-size:18px; color:#0f172a;"><?php esc_html_e( 'Staff Attendance Roster', 'ifsedu-sms' ); ?></h4>
                    <small style="color:#64748b; font-weight:600; font-size:13px;"><?php esc_html_e( 'Target Date:', 'ifsedu-sms' ); ?> <?php echo esc_html( date_i18n( 'd F, Y', strtotime( $filter_date ) ) ); ?></small>
                </div>
                
                <div class="dpt-counter-cluster" style="display:flex; gap:10px;">
                    <span style="background:#e2e8f0; color:#475569; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Total:', 'ifsedu-sms' ); ?> <span id="cnt-total"><?php echo count( $staff_members ); ?></span></span>
                    <span style="background:#ecfdf5; color:#059669; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Present:', 'ifsedu-sms' ); ?> <span id="cnt-present">0</span></span>
                    <span style="background:#fef2f2; color:#dc2626; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Absent:', 'ifsedu-sms' ); ?> <span id="cnt-absent">0</span></span>
                    <span style="background:#fff7ed; color:#ea580c; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Late:', 'ifsedu-sms' ); ?> <span id="cnt-late">0</span></span>
                </div>
            </div>

            <!-- Bulk Operations Bar -->
            <div class="afdp-bulk-automation-row no-print" style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; gap:16px; align-items:center;">
                <div style="font-size:13px; font-weight:700; color:#475569; display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Bulk Operations:', 'ifsedu-sms' ); ?>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="dpt-bulk-btn" data-target-status="Present" style="cursor:pointer; background:#fff; border:1px solid #a7f3d0; color:#059669; font-weight:600; padding:6px 12px; border-radius:6px; font-size:12px; transition:0.2s;"><?php esc_html_e( 'Set All Present', 'ifsedu-sms' ); ?></button>
                    <button type="button" class="dpt-bulk-btn" data-target-status="Absent" style="cursor:pointer; background:#fff; border:1px solid #fecaca; color:#dc2626; font-weight:600; padding:6px 12px; border-radius:6px; font-size:12px; transition:0.2s;"><?php esc_html_e( 'Set All Absent', 'ifsedu-sms' ); ?></button>
                    <button type="button" class="dpt-bulk-btn" data-target-status="Late" style="cursor:pointer; background:#fff; border:1px solid #fed7aa; color:#ea580c; font-weight:600; padding:6px 12px; border-radius:6px; font-size:12px; transition:0.2s;"><?php esc_html_e( 'Set All Late', 'ifsedu-sms' ); ?></button>
                </div>
            </div>

            <form method="POST" action="">
                <?php wp_nonce_field( 'save_staff_attendance_action', 'educore_staff_att_nonce' ); ?>
                <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:15%;"><?php esc_html_e( 'Staff ID', 'ifsedu-sms' ); ?></th>
                                <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:30%;"><?php esc_html_e( 'Full Name', 'ifsedu-sms' ); ?></th>
                                <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:20%;"><?php esc_html_e( 'Designation', 'ifsedu-sms' ); ?></th>
                                <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%; text-align:center;"><?php esc_html_e( 'Attendance Status', 'ifsedu-sms' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $staff_members as $st ) : 
                                $st_id  = (int) $st->id;
                                $status = isset( $attendance_states[ $st_id ] ) ? $attendance_states[ $st_id ] : 'Present';
                                $full_name = ! empty( $st->name_bn ) ? $st->name_bn : $st->full_name;
                                $display_staff_id = ( property_exists( $st, 'staff_id' ) && ! empty( $st->staff_id ) ) ? $st->staff_id : '#' . $st_id;
                            ?>
                                <tr class="staff-attendance-row" style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:12px 20px;"><code style="color:#0f172a; font-weight:700; background:#f1f5f9; padding:4px 8px; border-radius:6px; border:1px solid #e2e8f0;"><?php echo esc_html( $display_staff_id ); ?></code></td>
                                    <td style="padding:12px 20px;"><span style="font-weight:700; color:#0f172a;"><?php echo esc_html( $full_name ); ?></span></td>
                                    <td style="padding:12px 20px; color:#475569;"><?php echo esc_html( ! empty( $st->designation ) ? $st->designation : 'Faculty' ); ?></td>
                                    <td style="padding:12px 20px; text-align:center;">
                                        
                                        <!-- Modern Segmented Pill Control -->
                                        <div class="att-segmented-group">
                                            
                                            <input type="radio" class="att-radio-input status-radio-node" name="staff_attendance[<?php echo $st_id; ?>]" id="st_pres_<?php echo $st_id; ?>" value="Present" <?php checked( $status, 'Present' ); ?>>
                                            <label class="att-status-pill" for="st_pres_<?php echo $st_id; ?>">
                                                <span class="dashicons dashicons-yes-alt"></span>
                                                <?php esc_html_e( 'Present', 'ifsedu-sms' ); ?>
                                            </label>

                                            <input type="radio" class="att-radio-input status-radio-node" name="staff_attendance[<?php echo $st_id; ?>]" id="st_abs_<?php echo $st_id; ?>" value="Absent" <?php checked( $status, 'Absent' ); ?>>
                                            <label class="att-status-pill" for="st_abs_<?php echo $st_id; ?>">
                                                <span class="dashicons dashicons-dismiss"></span>
                                                <?php esc_html_e( 'Absent', 'ifsedu-sms' ); ?>
                                            </label>

                                            <input type="radio" class="att-radio-input status-radio-node" name="staff_attendance[<?php echo $st_id; ?>]" id="st_late_<?php echo $st_id; ?>" value="Late" <?php checked( $status, 'Late' ); ?>>
                                            <label class="att-status-pill" for="st_late_<?php echo $st_id; ?>">
                                                <span class="dashicons dashicons-clock"></span>
                                                <?php esc_html_e( 'Late', 'ifsedu-sms' ); ?>
                                            </label>

                                        </div>

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="padding:20px; background:#f8fafc; text-align:right; border-top:1px solid #e2e8f0;">
                    <button type="submit" name="educore_save_staff_attendance" style="padding:0 32px; height:44px; font-size:14px; font-weight:700; background:#008f5d; color:#fff; border:none; border-radius:8px; cursor:pointer; box-shadow:0 4px 12px rgba(0, 143, 93, 0.2);">
                        <span class="dashicons dashicons-saved" style="margin-top:5px;"></span> <?php esc_html_e( 'Save Staff Attendance', 'ifsedu-sms' ); ?>
                    </button>
                </div>
            </form>
        </div>
    <?php else : ?>
        <div style="background:#fffbeb; border:1px solid #fed7aa; color:#9a3412; padding:20px; border-radius:12px; text-align:center; font-weight:600;"><span class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px; margin-bottom:10px; display:block; margin:0 auto;"></span><p style="margin:0;"><?php esc_html_e( 'No active staff records found matching the filter criteria.', 'ifsedu-sms' ); ?></p></div>
    <?php endif; ?>
    
    <!-- Dynamic JS Engine: Live Bulk Logic -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        // Bulk Action & Live Counter Engine
        function updateLiveCounters() {
            const total = document.querySelectorAll('.staff-attendance-row').length;
            const present = document.querySelectorAll('.status-radio-node[value="Present"]:checked').length;
            const absent = document.querySelectorAll('.status-radio-node[value="Absent"]:checked').length;
            const late = document.querySelectorAll('.status-radio-node[value="Late"]:checked').length;
            
            const elTotal = document.getElementById('cnt-total');
            const elPresent = document.getElementById('cnt-present');
            const elAbsent = document.getElementById('cnt-absent');
            const elLate = document.getElementById('cnt-late');
            
            if (elTotal) elTotal.textContent = total;
            if (elPresent) elPresent.textContent = present;
            if (elAbsent) elAbsent.textContent = absent;
            if (elLate) elLate.textContent = late;
        }

        // Attach listeners to all individual radio buttons
        const allRadios = document.querySelectorAll('.status-radio-node');
        allRadios.forEach(radio => {
            radio.addEventListener('change', updateLiveCounters);
        });
        
        // Attach listeners to Bulk action buttons
        const bulkBtns = document.querySelectorAll('.dpt-bulk-btn');
        bulkBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const targetStatus = this.getAttribute('data-target-status');
                const matchingRadios = document.querySelectorAll('.status-radio-node[value="' + targetStatus + '"]');
                
                matchingRadios.forEach(radio => {
                    radio.checked = true;
                });
                
                updateLiveCounters();
            });
        });

        // Initialize counters on page load
        updateLiveCounters();
    });
    </script>
    <?php
}