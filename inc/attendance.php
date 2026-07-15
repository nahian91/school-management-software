<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function educore_attendance_tab() {
    global $wpdb;
    
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';

    // 1. Handle Form Submission (Save Attendance)
    if ( isset( $_POST['educore_save_attendance'] ) && wp_verify_nonce( $_POST['educore_attendance_nonce'], 'save_attendance_action' ) ) {
        $attendance_date = sanitize_text_field( $_POST['attendance_date'] );
        $attendance_data = isset( $_POST['attendance'] ) ? $_POST['attendance'] : array();
        $current_user_id = get_current_user_id();

        $saved_count = 0;

        foreach ( $attendance_data as $student_id => $status ) {
            $student_id = intval( $student_id );
            $status     = sanitize_text_field( $status );

            // Check if attendance already exists for this student on this date
            $existing = $wpdb->get_var( $wpdb->prepare( 
                "SELECT id FROM $table_attendance WHERE student_id = %d AND attendance_date = %s", 
                $student_id, $attendance_date 
            ) );

            if ( $existing ) {
                // Update existing record
                $wpdb->update( 
                    $table_attendance, 
                    array( 'status' => $status, 'recorded_by' => $current_user_id ), 
                    array( 'id' => $existing ) 
                );
            } else {
                // Insert new record
                $wpdb->insert( 
                    $table_attendance, 
                    array(
                        'student_id'      => $student_id,
                        'attendance_date' => $attendance_date,
                        'status'          => $status,
                        'recorded_by'     => $current_user_id
                    )
                );
            }
            $saved_count++;
        }

        echo '<div class="alert alert-success">Attendance saved successfully for ' . $saved_count . ' students.</div>';
        if ( function_exists('educore_log_activity') ) {
            educore_log_activity("Recorded daily attendance for Date: " . $attendance_date);
        }
    }

    // 2. Filter Variables
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( $_GET['class_name'] ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( $_GET['section_name'] ) : '';
    $filter_date    = isset( $_GET['attendance_date'] ) ? sanitize_text_field( $_GET['attendance_date'] ) : current_time('Y-m-d');

    // 3. Get Unique Classes and Sections for the Dropdown Filters
    $classes  = $wpdb->get_col( "SELECT DISTINCT class_name FROM $table_students WHERE status = 'Active' AND class_name != '' ORDER BY class_name ASC" );
    $sections = $wpdb->get_col( "SELECT DISTINCT section_name FROM $table_students WHERE status = 'Active' AND section_name != '' ORDER BY section_name ASC" );
    ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><span class="dashicons dashicons-clipboard"></span> Daily Attendance</h2>
    </div>

    <!-- Filter Form -->
    <div class="bg-white p-4 rounded shadow-sm border mb-4">
        <form method="GET" action="">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Select Date</label>
                    <input type="date" name="attendance_date" class="form-control" value="<?php echo esc_attr( $filter_date ); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Class</label>
                    <select name="class_name" class="form-control" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ( $classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Section (Optional)</label>
                    <select name="section_name" class="form-control">
                        <option value="">-- All Sections --</option>
                        <?php foreach ( $sections as $sec ) : ?>
                            <option value="<?php echo esc_attr( $sec ); ?>" <?php selected( $filter_section, $sec ); ?>><?php echo esc_html( $sec ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100" style="background-color: #3b82f6; border: none;">Filter Students</button>
                </div>
            </div>
        </form>
    </div>

    <?php
    // 4. Display Students for Attendance Entry if Class is Selected
    if ( ! empty( $filter_class ) ) {
        
        $query = "SELECT id, student_id, full_name, roll_no FROM $table_students WHERE status = 'Active' AND class_name = %s";
        $args = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $query .= " AND section_name = %s";
            $args[] = $filter_section;
        }

        $query .= " ORDER BY roll_no ASC";
        $students = $wpdb->get_results( $wpdb->prepare( $query, ...$args ) );

        if ( $students ) {
            ?>
            <div class="bg-white p-4 rounded shadow-sm border">
                <h4 class="mb-3 border-bottom pb-2 text-primary">
                    Mark Attendance for: <?php echo esc_html( $filter_class . ' ' . $filter_section ); ?> 
                    <span class="float-end text-muted fs-6">Date: <?php echo date('d F Y', strtotime($filter_date)); ?></span>
                </h4>
                
                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_attendance_action', 'educore_attendance_nonce' ); ?>
                    <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                    <table class="table table-striped table-bordered align-middle">
                        <thead style="background-color: #f8fafc;">
                            <tr>
                                <th style="width: 10%;">Roll No</th>
                                <th style="width: 15%;">Student ID</th>
                                <th style="width: 40%;">Student Name</th>
                                <th style="width: 35%;">Attendance Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ( $students as $student ) : 
                                // Check if attendance already exists for this student on the selected date
                                $existing_status = $wpdb->get_var( $wpdb->prepare( 
                                    "SELECT status FROM $table_attendance WHERE student_id = %d AND attendance_date = %s", 
                                    $student->id, $filter_date 
                                ) );
                                
                                // Default to 'Present' if no record exists
                                $current_status = $existing_status ? $existing_status : 'Present';
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $student->roll_no ); ?></strong></td>
                                <td><?php echo esc_html( $student->student_id ); ?></td>
                                <td><?php echo esc_html( $student->full_name ); ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <input type="radio" class="btn-check" name="attendance[<?php echo $student->id; ?>]" id="present_<?php echo $student->id; ?>" value="Present" <?php checked( $current_status, 'Present' ); ?>>
                                        <label class="btn btn-outline-success btn-sm" for="present_<?php echo $student->id; ?>">Present</label>

                                        <input type="radio" class="btn-check" name="attendance[<?php echo $student->id; ?>]" id="absent_<?php echo $student->id; ?>" value="Absent" <?php checked( $current_status, 'Absent' ); ?>>
                                        <label class="btn btn-outline-danger btn-sm" for="absent_<?php echo $student->id; ?>">Absent</label>

                                        <input type="radio" class="btn-check" name="attendance[<?php echo $student->id; ?>]" id="late_<?php echo $student->id; ?>" value="Late" <?php checked( $current_status, 'Late' ); ?>>
                                        <label class="btn btn-outline-warning btn-sm" for="late_<?php echo $student->id; ?>">Late</label>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="mt-4 text-end">
                        <button type="submit" name="educore_save_attendance" class="btn btn-success px-5 py-2" style="background-color: #10b981; border: none; font-weight: bold;">
                            Save Attendance
                        </button>
                    </div>
                </form>
            </div>
            <?php
        } else {
            echo '<div class="alert alert-warning">No active students found in this Class/Section.</div>';
        }
    } else {
        echo '<div class="alert alert-info bg-white border"><span class="dashicons dashicons-info" style="color: #3b82f6;"></span> Please select a Date and Class from above to take attendance.</div>';
    }
}
?>