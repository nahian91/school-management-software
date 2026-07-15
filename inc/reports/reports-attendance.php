<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_reports_attendance_view() {
    global $wpdb;
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';

    $classes = get_option( 'educore_classes', array() );
    
    $filter_class = isset( $_GET['class_name'] ) ? sanitize_text_field( $_GET['class_name'] ) : '';
    $filter_month = isset( $_GET['month'] ) ? sanitize_text_field( $_GET['month'] ) : date('Y-m');

    ?>
    <div class="bg-white p-4 rounded shadow-sm border mb-4 no-print">
        <form method="GET" action="">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="reports">
            <input type="hidden" name="sub" value="attendance">
            
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Select Class</label>
                    <select name="class_name" class="form-control" required>
                        <option value="">-- Choose Class --</option>
                        <?php foreach ( $classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Select Month</label>
                    <input type="month" name="month" class="form-control" value="<?php echo esc_attr( $filter_month ); ?>" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-success w-100" style="background-color: #10b981; border: none;">View Report</button>
                </div>
            </div>
        </form>
    </div>

    <?php
    if ( ! empty( $filter_class ) && ! empty( $filter_month ) ) {
        // Find total working days in that month for that class
        $total_working_days = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(DISTINCT attendance_date) 
            FROM $table_attendance a
            JOIN $table_students s ON a.student_id = s.id
            WHERE s.class_name = %s AND a.attendance_date LIKE %s
        ", $filter_class, $filter_month . '%' ) );

        $total_working_days = $total_working_days ? intval( $total_working_days ) : 0;

        $students = $wpdb->get_results( $wpdb->prepare( "
            SELECT id, student_id, full_name, roll_no 
            FROM $table_students 
            WHERE status = 'Active' AND class_name = %s 
            ORDER BY roll_no ASC
        ", $filter_class ) );
        ?>

        <div class="bg-white p-4 rounded shadow-sm border">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 text-primary">Monthly Attendance Overview</h5>
                <span class="badge bg-secondary">Total Working Days: <?php echo $total_working_days; ?></span>
            </div>

            <table class="table table-bordered table-striped text-center align-middle">
                <thead style="background-color: #f8fafc;">
                    <tr>
                        <th style="width: 10%;">Roll No</th>
                        <th class="text-start">Student Name</th>
                        <th>Present (Days)</th>
                        <th>Absent/Late (Days)</th>
                        <th>Attendance %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $students ) : foreach ( $students as $student ) : 
                        // Count Present
                        $present_count = $wpdb->get_var( $wpdb->prepare( "
                            SELECT COUNT(id) FROM $table_attendance 
                            WHERE student_id = %d AND status = 'Present' AND attendance_date LIKE %s
                        ", $student->id, $filter_month . '%' ) );
                        
                        // Count Absent + Late
                        $absent_count = $wpdb->get_var( $wpdb->prepare( "
                            SELECT COUNT(id) FROM $table_attendance 
                            WHERE student_id = %d AND status IN ('Absent', 'Late') AND attendance_date LIKE %s
                        ", $student->id, $filter_month . '%' ) );

                        $percentage = ($total_working_days > 0) ? round( ($present_count / $total_working_days) * 100, 2 ) : 0;
                        $perf_class = $percentage >= 80 ? 'text-success' : ($percentage >= 50 ? 'text-warning' : 'text-danger');
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $student->roll_no ); ?></strong></td>
                        <td class="text-start"><?php echo esc_html( $student->full_name ); ?> <br><small class="text-muted">ID: <?php echo esc_html( $student->student_id ); ?></small></td>
                        <td class="text-success fw-bold"><?php echo intval( $present_count ); ?></td>
                        <td class="text-danger fw-bold"><?php echo intval( $absent_count ); ?></td>
                        <td class="fw-bold <?php echo $perf_class; ?>"><?php echo $percentage; ?>%</td>
                    </tr>
                    <?php endforeach; else : ?>
                    <tr><td colspan="5" class="text-muted">No students found in this class.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
?>