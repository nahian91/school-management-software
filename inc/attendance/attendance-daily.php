<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Daily Student Attendance Entry Workspace
 * File: inc/attendance/attendance-daily.php
 * Strictly Filtered by Assigned Teacher Subjects & Units from Academic Setup
 */
function educore_daily_attendance_view( $classes, $sections, $filter_class, $filter_section, $filter_date ) {
    global $wpdb;

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' );

    $table_students         = $wpdb->prefix . 'sms_students';
    $table_attendance       = $wpdb->prefix . 'sms_attendance';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_staff            = $wpdb->prefix . 'sms_staff';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';

    // 1. Resolve Exact Assigned Classes & Sections for Non-Admin Teachers from sms_teacher_subjects
    $teacher_assigned_classes  = array();
    $teacher_assigned_sections = array();
    $assigned_unit_ids         = array();

    if ( ! $is_admin ) {
        $teacher_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table_staff} WHERE email = %s OR full_name = %s LIMIT 1",
            $current_user->user_email,
            $current_user->display_name
        ) );

        if ( $teacher_id ) {
            $allocations = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT u.id AS unit_id, u.class_name, u.section_name 
                 FROM {$table_teacher_subjects} ts
                 INNER JOIN {$table_units} u ON ts.class_id = u.id
                 WHERE ts.teacher_id = %d AND u.class_name != ''",
                $teacher_id
            ) );

            foreach ( $allocations as $al ) {
                $assigned_unit_ids[] = intval( $al->unit_id );
                if ( ! in_array( $al->class_name, $teacher_assigned_classes, true ) ) {
                    $teacher_assigned_classes[] = $al->class_name;
                }
                if ( ! empty( $al->section_name ) && ! in_array( $al->section_name, $teacher_assigned_sections, true ) ) {
                    $teacher_assigned_sections[] = $al->section_name;
                }
            }
        }
        // Override global classes parameter with teacher's assigned scope
        $classes = $teacher_assigned_classes;
    }

    // 2. Fetch Academic Units scoped to teacher's assignments or global
    if ( ! $is_admin && ! empty( $assigned_unit_ids ) ) {
        $unit_placeholders = implode( ',', array_fill( 0, count( $assigned_unit_ids ), '%d' ) );
        $all_units = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, class_name, section_name FROM {$table_units} WHERE id IN ($unit_placeholders) AND section_name != '' ORDER BY section_name ASC",
            ...$assigned_unit_ids
        ) );
    } else {
        $all_units = $wpdb->get_results( "SELECT id, class_name, section_name FROM {$table_units} WHERE section_name != '' ORDER BY section_name ASC" );
    }

    // Auto-select class & section for teachers if not explicitly chosen
    if ( ! $is_admin && empty( $filter_class ) && ! empty( $classes[0] ) ) {
        $filter_class = $classes[0];
    }
    if ( ! $is_admin && empty( $filter_section ) && ! empty( $all_units ) ) {
        foreach ( $all_units as $unit_row ) {
            if ( $unit_row->class_name === $filter_class && ! empty( $unit_row->section_name ) ) {
                $filter_section = $unit_row->section_name;
                break;
            }
        }
    }

    // 3. Fetch Active Students scoped to Assigned Classes & Sections
    if ( ! $is_admin && ! empty( $classes ) ) {
        $class_placeholders = implode( ',', array_fill( 0, count( $classes ), '%s' ) );
        $st_query = "SELECT id, class_name, section_name, full_name, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name IN ($class_placeholders)";
        $st_args  = $classes;

        if ( ! empty( $teacher_assigned_sections ) ) {
            $sec_placeholders = implode( ',', array_fill( 0, count( $teacher_assigned_sections ), '%s' ) );
            $st_query .= " AND section_name IN ($sec_placeholders)";
            $st_args = array_merge( $st_args, $teacher_assigned_sections );
        }

        $st_query .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $all_active_students = $wpdb->get_results( $wpdb->prepare( $st_query, ...$st_args ) );
    } else {
        $all_active_students = $wpdb->get_results( "SELECT id, class_name, section_name, full_name, roll_no FROM {$table_students} WHERE status = 'Active' ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC" );
    }

    // Additional Filter for specific student
    $filter_student = isset( $_GET['filter_student'] ) ? intval( $_GET['filter_student'] ) : 0;

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

        echo '<div class="afdp-success-banner" style="background:#ecfdf5; border:1px solid #a7f3d0; color:#047857; padding:12px 18px; border-radius:10px; margin-bottom:20px; font-weight:700; display:flex; align-items:center; gap:8px;"><span class="dashicons dashicons-yes-alt"></span> ' . sprintf( esc_html__( 'Attendance records successfully updated for %d students.', 'ifsedu-sms' ), intval( $saved_count ) ) . '</div>';
    }
    ?>

    <style>
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

        .att-radio-input[value="Present"]:checked + .att-status-pill {
            background: #059669;
            color: #ffffff;
            border-color: #047857;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }
        .att-radio-input[value="Present"]:checked + .att-status-pill .dashicons {
            opacity: 1;
        }

        .att-radio-input[value="Absent"]:checked + .att-status-pill {
            background: #dc2626;
            color: #ffffff;
            border-color: #b91c1c;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
        }
        .att-radio-input[value="Absent"]:checked + .att-status-pill .dashicons {
            opacity: 1;
        }

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

    <!-- Daily Filter Controls Bento Card -->
    <div class="dpt-bento-card no-print" style="background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:12px; margin-bottom:24px;">
        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            <input type="hidden" name="sub" value="daily">
            
            <div class="dpt-form-group" style="flex:1; min-width:180px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Target Date', 'ifsedu-sms' ); ?> *</label>
                <input type="date" name="attendance_date" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" value="<?php echo esc_attr( $filter_date ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
            </div>
            
            <div class="dpt-form-group" style="flex:1; min-width:180px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">
                    <?php esc_html_e( 'Academic Class', 'ifsedu-sms' ); ?> *
                    <?php if ( ! $is_admin ) : ?>
                        <span style="color:#059669; font-size:11px; font-weight:700;">(<?php esc_html_e( 'Assigned Only', 'ifsedu-sms' ); ?>)</span>
                    <?php endif; ?>
                </label>
                <select name="class_name" id="educore_attendance_class_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" required>
                    <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-sms' ); ?></option>
                    <?php foreach ( $classes as $cls ) : ?>
                        <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="dpt-form-group" style="flex:1; min-width:180px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">
                    <?php esc_html_e( 'Section', 'ifsedu-sms' ); ?>
                    <?php if ( ! $is_admin ) : ?>
                        <span style="color:#059669; font-size:11px; font-weight:700;">(<?php esc_html_e( 'Assigned', 'ifsedu-sms' ); ?>)</span>
                    <?php endif; ?>
                </label>
                <select name="section_name" id="educore_attendance_section_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;">
                    <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                </select>
            </div>

            <div class="dpt-form-group" style="flex:1; min-width:220px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Student (Optional)', 'ifsedu-sms' ); ?></label>
                <select name="filter_student" id="educore_attendance_student_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;">
                    <option value=""><?php esc_html_e( '-- All Students --', 'ifsedu-sms' ); ?></option>
                </select>
            </div>
            
            <div class="dpt-form-group">
                <button type="submit" style="height:40px; padding:0 24px; background:#006a4e; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Load Students', 'ifsedu-sms' ); ?></button>
            </div>
        </form>
    </div>

    <?php
    if ( ! empty( $filter_class ) ) {
        // Enforce boundary check for non-admin teachers
        if ( ! $is_admin && ! in_array( $filter_class, $classes, true ) ) {
            echo '<div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:20px; border-radius:12px; text-align:center; font-weight:600;"><p style="margin:0;">' . esc_html__( 'You are not authorized to mark attendance for this class.', 'ifsedu-sms' ) . '</p></div>';
            return;
        }

        $query = "SELECT id, student_id, full_name, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $sql_args = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $query .= " AND section_name = %s";
            $sql_args[] = $filter_section;
        } elseif ( ! $is_admin && ! empty( $teacher_assigned_sections ) ) {
            $sec_placeholders = implode( ',', array_fill( 0, count( $teacher_assigned_sections ), '%s' ) );
            $query .= " AND section_name IN ($sec_placeholders)";
            $sql_args = array_merge( $sql_args, $teacher_assigned_sections );
        }

        if ( $filter_student > 0 ) {
            $query .= " AND id = %d";
            $sql_args[] = $filter_student;
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
            <div class="dpt-bento-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
                
                <div class="afdp-roster-meta-bar" style="padding:20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                    <div class="afdp-roster-title">
                        <h4 style="margin:0; font-weight:800; font-size:18px;"><?php esc_html_e( 'Mark Attendance:', 'ifsedu-sms' ); ?> <span style="color: #006a4e;"><?php echo esc_html( $filter_class . ( $filter_section ? ' (' . $filter_section . ')' : '' ) ); ?></span></h4>
                        <small style="color:#64748b; font-weight:600; font-size:13px;"><?php esc_html_e( 'Target Date:', 'ifsedu-sms' ); ?> <?php echo esc_html( date_i18n( 'd F, Y', strtotime( $filter_date ) ) ); ?></small>
                    </div>
                    
                    <div class="dpt-counter-cluster" style="display:flex; gap:10px;">
                        <span style="background:#e2e8f0; color:#475569; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Total:', 'ifsedu-sms' ); ?> <span id="cnt-total"><?php echo count( $students ); ?></span></span>
                        <span style="background:#ecfdf5; color:#059669; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Present:', 'ifsedu-sms' ); ?> <span id="cnt-present">0</span></span>
                        <span style="background:#fef2f2; color:#dc2626; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Absent:', 'ifsedu-sms' ); ?> <span id="cnt-absent">0</span></span>
                        <span style="background:#fff7ed; color:#ea580c; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Late:', 'ifsedu-sms' ); ?> <span id="cnt-late">0</span></span>
                    </div>
                </div>

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
                
                <form method="POST" action="" id="educoreAttendanceSubmitEngine">
                    <?php wp_nonce_field( 'save_attendance_action', 'educore_attendance_nonce' ); ?>
                    <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
                            <thead>
                                <tr style="background:#f8fafc;">
                                    <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:12%;"><?php esc_html_e( 'Roll No', 'ifsedu-sms' ); ?></th>
                                    <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:18%;"><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                                    <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%;"><?php esc_html_e( 'Student Name', 'ifsedu-sms' ); ?></th>
                                    <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%; text-align:center;"><?php esc_html_e( 'Attendance Status', 'ifsedu-sms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ( $students as $student ) : 
                                    $student_internal_id = intval( $student->id );
                                    $current_status = isset( $loaded_attendance_states[ $student_internal_id ] ) ? $loaded_attendance_states[ $student_internal_id ]->status : 'Present';
                                ?>
                                <tr class="student-attendance-row" style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:12px 20px;"><strong># <?php echo esc_html( $student->roll_no ); ?></strong></td>
                                    <td style="padding:12px 20px;"><code style="color:#0f172a; font-weight:700; background:#f1f5f9; padding:4px 8px; border-radius:6px; border:1px solid #e2e8f0;"><?php echo esc_html( $student->student_id ); ?></code></td>
                                    <td style="padding:12px 20px;"><span style="font-weight:700; color:#0f172a;"><?php echo esc_html( $student->full_name ); ?></span></td>
                                    <td style="padding:12px 20px; text-align:center;">
                                        
                                        <!-- Segmented Pill Control -->
                                        <div class="att-segmented-group">
                                            <input type="radio" class="att-radio-input status-radio-node" name="attendance[<?php echo $student_internal_id; ?>]" id="stu_pres_<?php echo $student_internal_id; ?>" value="Present" <?php checked( $current_status, 'Present' ); ?>>
                                            <label class="att-status-pill" for="stu_pres_<?php echo $student_internal_id; ?>">
                                                <span class="dashicons dashicons-yes-alt"></span>
                                                <?php esc_html_e( 'Present', 'ifsedu-sms' ); ?>
                                            </label>

                                            <input type="radio" class="att-radio-input status-radio-node" name="attendance[<?php echo $student_internal_id; ?>]" id="stu_abs_<?php echo $student_internal_id; ?>" value="Absent" <?php checked( $current_status, 'Absent' ); ?>>
                                            <label class="att-status-pill" for="stu_abs_<?php echo $student_internal_id; ?>">
                                                <span class="dashicons dashicons-dismiss"></span>
                                                <?php esc_html_e( 'Absent', 'ifsedu-sms' ); ?>
                                            </label>

                                            <input type="radio" class="att-radio-input status-radio-node" name="attendance[<?php echo $student_internal_id; ?>]" id="stu_late_<?php echo $student_internal_id; ?>" value="Late" <?php checked( $current_status, 'Late' ); ?>>
                                            <label class="att-status-pill" for="stu_late_<?php echo $student_internal_id; ?>">
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
                        <button type="submit" name="educore_save_attendance" style="padding:0 32px; height:44px; font-size:14px; font-weight:700; background:#006a4e; color:#fff; border:none; border-radius:8px; cursor:pointer; box-shadow:0 4px 12px rgba(0, 106, 78, 0.2);">
                            <span class="dashicons dashicons-saved" style="margin-top:5px;"></span> <?php esc_html_e( 'Save Attendance Data', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </form>
            </div>
            <?php
        } else {
            echo '<div style="background:#fffbeb; border:1px solid #fed7aa; color:#9a3412; padding:20px; border-radius:12px; text-align:center; font-weight:600;"><span class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px; margin-bottom:10px; display:block; margin:0 auto;"></span><p style="margin:0;">' . esc_html__( 'No active students found matching current filters.', 'ifsedu-sms' ) . '</p></div>';
        }
    } else {
        echo '<div style="background:#f0f9ff; border:1px solid #bae6fd; color:#0369a1; padding:20px; border-radius:12px; text-align:center; font-weight:600;"><span class="dashicons dashicons-info" style="font-size:24px; width:24px; height:24px; margin-bottom:10px; display:block; margin:0 auto;"></span><p style="margin:0;">' . esc_html__( 'Select a Target Date and Academic Class above to load the attendance workspace.', 'ifsedu-sms' ) . '</p></div>';
    }
    ?>
    
    <!-- Dynamic JS Engine: Safe Class->Section->Student Chaining & Bulk Logic -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        
        const rawUnits = <?php echo wp_json_encode( !empty($all_units) ? $all_units : array() ); ?>;
        const rawStudents = <?php echo wp_json_encode( !empty($all_active_students) ? $all_active_students : array() ); ?>;
        
        const unitsMap = Array.isArray(rawUnits) ? rawUnits : [];
        const studentsMap = Array.isArray(rawStudents) ? rawStudents : [];
        
        const currentFilterSection = "<?php echo esc_js( $filter_section ); ?>";
        const currentFilterStudent = "<?php echo esc_js( $filter_student ); ?>";
        
        const classSelect = document.getElementById('educore_attendance_class_select');
        const sectionSelect = document.getElementById('educore_attendance_section_select');
        const studentSelect = document.getElementById('educore_attendance_student_select');

        // Populate Sections based on Class
        function populateSections(selectedClass, selectedSecName = '') {
            if (!sectionSelect) return;
            sectionSelect.innerHTML = '<option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>';
            if (!selectedClass) return;

            const filtered = unitsMap.filter(item => item.class_name == selectedClass);
            const uniqueSections = [...new Set(filtered.map(item => item.section_name).filter(Boolean))];

            uniqueSections.forEach(secName => {
                const opt = document.createElement('option');
                opt.value = secName;
                opt.textContent = secName;
                if (secName == selectedSecName) {
                    opt.selected = true;
                }
                sectionSelect.appendChild(opt);
            });
        }

        // Populate Students based on Class and Section
        function populateStudents(selectedClass, selectedSecName, selectedStudentId = '') {
            if (!studentSelect) return;
            studentSelect.innerHTML = '<option value=""><?php esc_html_e( '-- All Students --', 'ifsedu-sms' ); ?></option>';
            if (!selectedClass) return;

            let filteredStudents = studentsMap.filter(item => item.class_name == selectedClass);
            
            if (selectedSecName) {
                filteredStudents = filteredStudents.filter(item => item.section_name == selectedSecName);
            }

            filteredStudents.forEach(stu => {
                const opt = document.createElement('option');
                opt.value = stu.id;
                opt.textContent = stu.roll_no ? `[Roll: ${stu.roll_no}] ${stu.full_name}` : stu.full_name;
                
                if (String(stu.id) === String(selectedStudentId)) {
                    opt.selected = true;
                }
                studentSelect.appendChild(opt);
            });
        }

        if (classSelect && sectionSelect && studentSelect) {
            populateSections(classSelect.value, currentFilterSection);
            populateStudents(classSelect.value, currentFilterSection, currentFilterStudent);

            classSelect.addEventListener('change', function() {
                populateSections(classSelect.value);
                populateStudents(classSelect.value, sectionSelect.value);
            });

            sectionSelect.addEventListener('change', function() {
                populateStudents(classSelect.value, sectionSelect.value);
            });
        }
        
        // Bulk Action & Live Counter Engine
        function updateLiveCounters() {
            const total = document.querySelectorAll('.student-attendance-row').length;
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

        const allRadios = document.querySelectorAll('.status-radio-node');
        allRadios.forEach(radio => {
            radio.addEventListener('change', updateLiveCounters);
        });
        
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

        updateLiveCounters();
        
    });
    </script>
<?php
}