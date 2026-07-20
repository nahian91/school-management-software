<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function educore_attendance_tab() {
    global $wpdb;
    
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $table_units      = $wpdb->prefix . 'sms_academic_units';

    // 1. Handle Form Submission (Save Attendance Data)
    if ( isset( $_POST['educore_save_attendance'] ) && wp_verify_nonce( $_POST['educore_attendance_nonce'], 'save_attendance_action' ) ) {
        $attendance_date = sanitize_text_field( $_POST['attendance_date'] );
        $attendance_data = isset( $_POST['attendance'] ) ? $_POST['attendance'] : array();
        $current_user_id = get_current_user_id();

        $saved_count = 0;

        foreach ( $attendance_data as $student_id => $status ) {
            $student_id = intval( $student_id );
            $status     = sanitize_text_field( $status );

            // Check if attendance already exists for this student on this specific date
            $existing = $wpdb->get_var( $wpdb->prepare( 
                "SELECT id FROM {$table_attendance} WHERE student_id = %d AND attendance_date = %s", 
                $student_id, $attendance_date 
            ) );

            if ( $existing ) {
                // Update existing record
                $wpdb->update( 
                    $table_attendance, 
                    array( 'status' => $status, 'recorded_by' => $current_user_id ), 
                    array( 'id' => $existing ),
                    array( '%s', '%d' ),
                    array( '%d' )
                );
            } else {
                // Insert new record
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

        echo '<div class="alert alert-success border-0 shadow-sm mb-4">Attendance saved successfully for ' . intval( $saved_count ) . ' students.</div>';
        
        if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
            IFSEdu_School_Management_System::log_activity( "Recorded daily attendance grid for Date: " . $attendance_date );
        }
    }

    // 2. Filter Inputs
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( $_GET['class_name'] ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( $_GET['section_name'] ) : '';
    $filter_date    = isset( $_GET['attendance_date'] ) ? sanitize_text_field( $_GET['attendance_date'] ) : current_time('Y-m-d');

    // 3. Fetch Classes & Sections Dynamically from Academic Units Table
    $classes = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} ORDER BY class_name ASC" );
    
    // Build Sections and Department Groups list
    $raw_sections = $wpdb->get_results( "SELECT DISTINCT section_name, dept_name FROM {$table_units} WHERE section_name != '' OR dept_name != ''" );
    $sections     = array();
    if ( ! empty( $raw_sections ) ) {
        foreach ( $raw_sections as $sec_obj ) {
            if ( ! empty( $sec_obj->section_name ) ) $sections[] = $sec_obj->section_name;
            if ( ! empty( $sec_obj->dept_name ) )    $sections[] = $sec_obj->dept_name;
        }
        $sections = array_unique( $sections );
    }
    ?>

    <style>
        .attendance-card { border-radius: 8px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); }
        .attendance-btn-group .btn-check:checked + .btn-outline-success { background-color: #006a4e !important; color: #fff !important; }
        .attendance-btn-group .btn-check:checked + .btn-outline-danger { background-color: #ef4444 !important; color: #fff !important; }
        .attendance-btn-group .btn-check:checked + .btn-outline-warning { background-color: #f59e0b !important; color: #fff !important; }
        .counter-badge { font-weight: 700; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; }
        .bulk-controller { padding: 8px 16px; font-weight: 600; cursor: pointer; transition: all 0.2s ease-in-out; }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><span class="dashicons dashicons-clipboard text-success fs-3 pt-1"></span> Daily Attendance Matrix</h2>
    </div>

    <!-- Filter Console Block -->
    <div class="bg-white p-4 rounded shadow-sm border mb-4 attendance-card">
        <form method="GET" action="">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            
            <div class="row align-items-end g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold text-muted">Select Target Date <span class="text-danger">*</span></label>
                    <input type="date" name="attendance_date" class="form-control" value="<?php echo esc_attr( $filter_date ); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-muted">Academic Class / Year <span class="text-danger">*</span></label>
                    <select name="class_name" class="form-select" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ( $classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-muted">Section / Group</label>
                    <select name="section_name" class="form-select">
                        <option value="">-- All Sections / Groups --</option>
                        <?php foreach ( $sections as $sec ) : ?>
                            <option value="<?php echo esc_attr( $sec ); ?>" <?php selected( $filter_section, $sec ); ?>><?php echo esc_html( $sec ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold" style="background-color: #006a4e; border: none;">Load Roster Structure</button>
                </div>
            </div>
        </form>
    </div>

    <?php
    // 4. Render Interface If Criteria Formulated
    if ( ! empty( $filter_class ) ) {
        
        $query = "SELECT id, student_id, full_name, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $args = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $query .= " AND section_name = %s";
            $args[] = $filter_section;
        }

        $query .= " ORDER BY roll_no ASC";
        $students = $wpdb->get_results( $wpdb->prepare( $query, ...$args ) );

        if ( $students ) {
            ?>
            <div class="bg-white p-4 rounded shadow-sm border attendance-card">
                
                <!-- Dynamic Metrics Real-time Analytics Dashboard Header -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-3 mb-4 gap-3">
                    <div>
                        <h4 class="mb-1 text-dark fw-bold">
                            Mark Attendance: <span class="text-success"><?php echo esc_html( $filter_class . ( $filter_section ? ' (' . $filter_section . ')' : '' ) ); ?></span>
                        </h4>
                        <small class="text-muted fw-semibold">Target Date: <?php echo date('d F, Y', strtotime($filter_date)); ?></small>
                    </div>
                    <!-- Live Stats Dynamic Counters -->
                    <div class="d-flex gap-2">
                        <span class="bg-light text-dark border counter-badge">Total: <span id="cnt-total"><?php echo count($students); ?></span></span>
                        <span class="bg-success-subtle text-success counter-badge" style="background-color: #d1fae5; color: #065f46 !important;">Present: <span id="cnt-present">0</span></span>
                        <span class="bg-danger-subtle text-danger counter-badge" style="background-color: #fee2e2; color: #991b1b !important;">Absent: <span id="cnt-absent">0</span></span>
                        <span class="bg-warning-subtle text-warning counter-badge" style="background-color: #fef3c7; color: #92400e !important;">Late: <span id="cnt-late">0</span></span>
                    </div>
                </div>

                <!-- Global Action Automation Controllers Bar -->
                <div class="bg-light p-3 rounded mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3 border">
                    <div class="fw-bold text-secondary"><span class="dashicons dashicons-admin-tools align-middle me-1"></span> Bulk Operations:</div>
                    <div class="d-inline-flex gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm bulk-controller fw-bold" data-target-status="Present">Set All Present</button>
                        <button type="button" class="btn btn-outline-danger btn-sm bulk-controller fw-bold" data-target-status="Absent">Set All Absent</button>
                        <button type="button" class="btn btn-outline-warning btn-sm bulk-controller fw-bold" data-target-status="Late">Set All Late</button>
                    </div>
                </div>
                
                <form method="POST" action="" id="educoreAttendanceSubmitEngine">
                    <?php wp_nonce_field( 'save_attendance_action', 'educore_attendance_nonce' ); ?>
                    <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle border mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 10%;" class="ps-3">Roll No</th>
                                    <th style="width: 15%;">Student ID</th>
                                    <th style="width: 40%;">Student Name</th>
                                    <th style="width: 35%; text-align: center;">Attendance Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ( $students as $student ) : 
                                    // Fetch existing status if already recorded
                                    $existing_status = $wpdb->get_var( $wpdb->prepare( 
                                        "SELECT status FROM {$table_attendance} WHERE student_id = %d AND attendance_date = %s", 
                                        $student->id, $filter_date 
                                    ) );
                                    
                                    $current_status = $existing_status ? $existing_status : 'Present';
                                ?>
                                <tr class="student-attendance-row">
                                    <td class="ps-3"><strong># <?php echo esc_html( $student->roll_no ); ?></strong></td>
                                    <td><code class="text-dark fw-bold"><?php echo esc_html( $student->student_id ); ?></code></td>
                                    <td><span class="fw-semibold text-slate-700"><?php echo esc_html( $student->full_name ); ?></span></td>
                                    <td style="text-align: center;">
                                        <div class="btn-group attendance-btn-group" role="group" aria-label="Status Controls">
                                            <input type="radio" class="btn-check status-radio-node" name="attendance[<?php echo intval( $student->id ); ?>]" id="present_<?php echo intval( $student->id ); ?>" value="Present" <?php checked( $current_status, 'Present' ); ?>>
                                            <label class="btn btn-outline-success btn-sm px-3 fw-semibold" for="present_<?php echo intval( $student->id ); ?>">Present</label>

                                            <input type="radio" class="btn-check status-radio-node" name="attendance[<?php echo intval( $student->id ); ?>]" id="absent_<?php echo intval( $student->id ); ?>" value="Absent" <?php checked( $current_status, 'Absent' ); ?>>
                                            <label class="btn btn-outline-danger btn-sm px-3 fw-semibold" for="absent_<?php echo intval( $student->id ); ?>">Absent</label>

                                            <input type="radio" class="btn-check status-radio-node" name="attendance[<?php echo intval( $student->id ); ?>]" id="late_<?php echo intval( $student->id ); ?>" value="Late" <?php checked( $current_status, 'Late' ); ?>>
                                            <label class="btn btn-outline-warning btn-sm px-3 fw-semibold" for="late_<?php echo intval( $student->id ); ?>">Late</label>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-end border-top pt-3">
                        <button type="submit" name="educore_save_attendance" class="btn btn-success px-5 py-2 fw-bold" style="background-color: #006a4e; border: none; font-size: 1rem; box-shadow: 0 4px 6px -1px rgba(0, 106, 78, 0.3);">
                            Commit & Save Roster Stack
                        </button>
                    </div>
                </form>
            </div>

            <!-- Dynamic Interactivity Layer -->
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                function recountLiveStatisticsDashboard() {
                    var total   = $('.student-attendance-row').length;
                    var present = $('.status-radio-node[value="Present"]:checked').length;
                    var absent  = $('.status-radio-node[value="Absent"]:checked').length;
                    var late    = $('.status-radio-node[value="Late"]:checked').length;

                    $('#cnt-total').text(total);
                    $('#cnt-present').text(present);
                    $('#cnt-absent').text(absent);
                    $('#cnt-late').text(late);
                }

                recountLiveStatisticsDashboard();

                $('.status-radio-node').on('change', function() {
                    recountLiveStatisticsDashboard();
                });

                $('.bulk-controller').on('click', function() {
                    var targetedStatusType = $(this).data('target-status');
                    $('.student-attendance-row').each(function() {
                        $(this).find('.status-radio-node[value="' + targetedStatusType + '"]').prop('checked', true).trigger('change');
                    });
                });
            });
            </script>
            <?php
        } else {
            echo '<div class="alert alert-warning border-0 shadow-sm">No active students found matching current Class/Section requirements.</div>';
        }
    } else {
        echo '<div class="alert alert-light border shadow-sm text-center py-5"><span class="dashicons dashicons-info text-primary fs-1 d-block mb-2"></span> Select a target Date and Class above to load the attendance workspace.</div>';
    }
}