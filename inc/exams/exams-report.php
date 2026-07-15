<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_exams_report_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';

    $exams   = $wpdb->get_results( "SELECT id, exam_name FROM $table_exams ORDER BY id DESC" );
    $classes = get_option( 'educore_classes', array() );

    // GET filters
    $filter_exam    = isset( $_GET['exam_id'] ) ? intval( $_GET['exam_id'] ) : 0;
    $filter_student = isset( $_GET['student_id'] ) ? intval( $_GET['student_id'] ) : 0;
    ?>
    <div class="mb-3 no-print">
        <a href="<?php echo admin_url( 'admin.php?page=school_management_system&tab=exams' ); ?>" class="btn btn-secondary btn-sm">&larr; Back to Exams</a>
    </div>

    <!-- Generator Block -->
    <div class="bg-white p-4 rounded shadow-sm border mb-4 no-print">
        <h4 class="mb-3 text-primary">Generate Report Card</h4>
        <form method="GET" action="">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="exams">
            <input type="hidden" name="sub" value="report">
            
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Select Exam</label>
                    <select name="exam_id" class="form-control" required>
                        <option value="">-- Choose Exam --</option>
                        <?php foreach ( $exams as $ex ) : ?>
                            <option value="<?php echo $ex->id; ?>" <?php selected( $filter_exam, $ex->id ); ?>><?php echo esc_html( $ex->exam_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">Select Student</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">-- Select Student --</option>
                        <?php 
                        $students = $wpdb->get_results( "SELECT id, full_name, student_id, class_name FROM $table_students WHERE status = 'Active' ORDER BY class_name ASC" );
                        foreach ( $students as $s ) : ?>
                            <option value="<?php echo $s->id; ?>" <?php selected( $filter_student, $s->id ); ?>>
                                <?php echo esc_html( $s->full_name . ' (ID: ' . $s->student_id . ') - ' . $s->class_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-success w-100" style="background-color: #10b981; border: none;">Generate Report</button>
                </div>
            </div>
        </form>
    </div>

    <?php
    if ( $filter_exam > 0 && $filter_student > 0 ) {
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_students WHERE id = %d", $filter_student ) );
        $exam    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_exams WHERE id = %d", $filter_exam ) );
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_results WHERE exam_id = %d AND student_id = %d", $filter_exam, $filter_student ) );

        if ( ! $results ) {
            echo '<div class="alert alert-danger no-print">No marks entry found for this student in this exam.</div>';
            return;
        }

        // Calculate Aggregate
        $total_sub = count( $results );
        $sum_gpa = 0;
        $total_marks_all = 0;
        $obtained_marks_all = 0;
        $has_failed = false;

        foreach( $results as $r ) {
            $sum_gpa += $r->gpa;
            $total_marks_all += $r->total_marks;
            $obtained_marks_all += $r->obtained_marks;
            if ( $r->grade === 'F' ) $has_failed = true;
        }

        $avg_gpa = ( $total_sub > 0 ) ? ( $sum_gpa / $total_sub ) : 0;
        $final_gpa = $has_failed ? 0.00 : number_format( $avg_gpa, 2 );
        $final_grade = $has_failed ? 'F' : educore_calculate_grade( $obtained_marks_all, $total_marks_all )[0];
        
        $school_name = get_bloginfo('name');
        ?>
        
        <!-- Print styling -->
        <style>
            .report-card-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border: 1px solid #ccc; font-family: Arial, sans-serif; }
            .report-header { text-align: center; border-bottom: 2px solid #10b981; padding-bottom: 20px; margin-bottom: 20px; }
            .student-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; }
            .marks-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
            .marks-table th, .marks-table td { border: 1px solid #ddd; padding: 10px; text-align: center; }
            .marks-table th { background-color: #f8fafc; }
            .text-left { text-align: left !important; }
            .gpa-box { border: 2px solid #10b981; padding: 15px; text-align: center; border-radius: 8px; margin-bottom: 40px; }
            .signature-area { display: flex; justify-content: space-between; margin-top: 50px; }
            .sign-line { border-top: 1px solid #000; padding-top: 5px; width: 200px; text-align: center; }
            
            @media print {
                body * { visibility: hidden; }
                .report-card-container, .report-card-container * { visibility: visible; }
                .report-card-container { position: absolute; left: 0; top: 0; width: 100%; border: none; padding: 0; }
                .no-print { display: none !important; }
            }
        </style>

        <div class="text-center mb-3 no-print">
            <button onclick="window.print();" class="btn btn-primary btn-lg"><span class="dashicons dashicons-printer" style="margin-top:4px;"></span> Print Report Card</button>
        </div>

        <div class="report-card-container">
            <div class="report-header">
                <h1 style="margin: 0; color: #10b981; text-transform: uppercase;"><?php echo esc_html( $school_name ); ?></h1>
                <h3 style="margin: 10px 0 5px;"><?php echo esc_html( $exam->exam_name ); ?></h3>
                <p style="margin: 0; font-size: 14px; color: #555;">Academic Report Card</p>
            </div>

            <div class="student-info-grid">
                <div>
                    <strong>Student Name:</strong> <?php echo esc_html( $student->full_name ); ?><br><br>
                    <strong>Student ID:</strong> <?php echo esc_html( $student->student_id ); ?><br><br>
                    <strong>Guardian:</strong> <?php echo esc_html( $student->guardian_name ); ?>
                </div>
                <div style="text-align: right;">
                    <strong>Class:</strong> <?php echo esc_html( $student->class_name ); ?><br><br>
                    <strong>Section:</strong> <?php echo esc_html( $student->section_name ); ?><br><br>
                    <strong>Roll No:</strong> <?php echo esc_html( $student->roll_no ); ?>
                </div>
            </div>

            <table class="marks-table">
                <thead>
                    <tr>
                        <th class="text-left">Subject Name</th>
                        <th>Total Marks</th>
                        <th>Obtained Marks</th>
                        <th>Letter Grade</th>
                        <th>Grade Point (GP)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach( $results as $r ) : ?>
                    <tr>
                        <td class="text-left fw-bold"><?php echo esc_html( $r->subject_name ); ?></td>
                        <td><?php echo floatval( $r->total_marks ); ?></td>
                        <td><?php echo floatval( $r->obtained_marks ); ?></td>
                        <td style="font-weight: bold; color: <?php echo $r->grade === 'F' ? 'red' : 'inherit'; ?>"><?php echo esc_html( $r->grade ); ?></td>
                        <td><?php echo number_format( $r->gpa, 2 ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="gpa-box">
                <h3 style="margin: 0;">FINAL RESULT</h3>
                <p style="font-size: 20px; margin: 10px 0 0;">
                    Overall Grade: <strong><?php echo $final_grade; ?></strong> &nbsp;|&nbsp; 
                    Cumulative GPA: <strong><?php echo $final_gpa; ?></strong>
                </p>
            </div>

            <div class="signature-area">
                <div class="sign-line">Class Teacher</div>
                <div class="sign-line">Headmaster / Principal</div>
            </div>
        </div>
        <?php
    }
}
?>