<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Simple Grading Logic Helper
function educore_calculate_grade( $obtained, $total ) {
    $percentage = ( $obtained / $total ) * 100;
    
    if ( $percentage >= 80 ) return array( 'A+', 5.00 );
    if ( $percentage >= 70 ) return array( 'A',  4.00 );
    if ( $percentage >= 60 ) return array( 'A-', 3.50 );
    if ( $percentage >= 50 ) return array( 'B',  3.00 );
    if ( $percentage >= 40 ) return array( 'C',  2.00 );
    if ( $percentage >= 33 ) return array( 'D',  1.00 );
    return array( 'F', 0.00 );
}

function educore_exams_marks_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';

    // Handle Form Submission
    if ( isset( $_POST['save_marks'] ) && wp_verify_nonce( $_POST['educore_marks_nonce'], 'save_marks_action' ) ) {
        $exam_id      = intval( $_POST['exam_id'] );
        $subject_name = sanitize_text_field( $_POST['subject_name'] );
        $total_marks  = floatval( $_POST['total_marks'] );
        $marks_data   = isset( $_POST['marks'] ) ? $_POST['marks'] : array();
        
        $user_id = get_current_user_id();
        $saved = 0;

        foreach ( $marks_data as $student_id => $obtained ) {
            if ( $obtained === '' ) continue; // Skip empty inputs
            
            $obtained = floatval( $obtained );
            list( $grade, $gpa ) = educore_calculate_grade( $obtained, $total_marks );

            // Check if exist
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table_results WHERE exam_id = %d AND student_id = %d AND subject_name = %s",
                $exam_id, $student_id, $subject_name
            ));

            $data = array(
                'exam_id'        => $exam_id,
                'student_id'     => $student_id,
                'subject_name'   => $subject_name,
                'total_marks'    => $total_marks,
                'obtained_marks' => $obtained,
                'grade'          => $grade,
                'gpa'            => $gpa,
                'evaluated_by'   => $user_id
            );

            if ( $existing ) {
                $wpdb->update( $table_results, $data, array( 'id' => $existing ) );
            } else {
                $wpdb->insert( $table_results, $data );
            }
            $saved++;
        }
        echo '<div class="alert alert-success">Marks updated successfully for ' . $saved . ' students.</div>';
    }

    // Filter Variables
    $filter_exam  = isset( $_GET['exam_id'] ) ? intval( $_GET['exam_id'] ) : 0;
    $filter_class = isset( $_GET['class_name'] ) ? sanitize_text_field( $_GET['class_name'] ) : '';
    $subject_name = isset( $_GET['subject_name'] ) ? sanitize_text_field( $_GET['subject_name'] ) : '';

    $exams = $wpdb->get_results( "SELECT id, exam_name FROM $table_exams ORDER BY id DESC" );
    $classes = get_option( 'educore_classes', array() );
    ?>
    <div class="mb-3">
        <a href="<?php echo admin_url( 'admin.php?page=school_management_system&tab=exams' ); ?>" class="btn btn-secondary btn-sm">&larr; Back to Exams</a>
    </div>

    <!-- Filter Block -->
    <div class="bg-white p-4 rounded shadow-sm border mb-4">
        <h4 class="mb-3 text-primary">Subject-wise Marks Entry</h4>
        <form method="GET" action="">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="exams">
            <input type="hidden" name="sub" value="marks">
            
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Select Exam</label>
                    <select name="exam_id" class="form-control" required>
                        <option value="">-- Choose Exam --</option>
                        <?php foreach ( $exams as $ex ) : ?>
                            <option value="<?php echo $ex->id; ?>" <?php selected( $filter_exam, $ex->id ); ?>><?php echo esc_html( $ex->exam_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Select Class</label>
                    <select name="class_name" class="form-control" required>
                        <option value="">-- Choose Class --</option>
                        <?php foreach ( $classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Subject Name</label>
                    <input type="text" name="subject_name" class="form-control" value="<?php echo esc_attr( $subject_name ); ?>" placeholder="e.g. English, Mathematics" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100" style="background-color: #3b82f6; border: none;">Load Students</button>
                </div>
            </div>
        </form>
    </div>

    <?php
    if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $subject_name ) ) {
        $students = $wpdb->get_results( $wpdb->prepare( "SELECT id, student_id, full_name, roll_no FROM $table_students WHERE status = 'Active' AND class_name = %s ORDER BY roll_no ASC", $filter_class ) );
        
        if ( $students ) {
            ?>
            <div class="bg-white p-4 rounded shadow-sm border">
                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_marks_action', 'educore_marks_nonce' ); ?>
                    <input type="hidden" name="exam_id" value="<?php echo $filter_exam; ?>">
                    <input type="hidden" name="subject_name" value="<?php echo esc_attr( $subject_name ); ?>">
                    
                    <div class="mb-3" style="width: 250px;">
                        <label class="form-label fw-bold text-danger">Total Marks for this Subject</label>
                        <input type="number" step="0.01" name="total_marks" class="form-control border-danger" value="100" required>
                    </div>

                    <table class="table table-bordered table-hover mt-3">
                        <thead style="background-color: #f8fafc;">
                            <tr>
                                <th style="width: 10%;">Roll No</th>
                                <th style="width: 15%;">Student ID</th>
                                <th>Student Name</th>
                                <th style="width: 20%;" class="text-center">Obtained Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $students as $s ) : 
                                // Fetch existing marks if already entered
                                $existing_marks = $wpdb->get_var( $wpdb->prepare(
                                    "SELECT obtained_marks FROM $table_results WHERE exam_id = %d AND student_id = %d AND subject_name = %s",
                                    $filter_exam, $s->id, $subject_name
                                ));
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $s->roll_no ); ?></strong></td>
                                <td><?php echo esc_html( $s->student_id ); ?></td>
                                <td><?php echo esc_html( $s->full_name ); ?></td>
                                <td>
                                    <input type="number" step="0.01" name="marks[<?php echo $s->id; ?>]" class="form-control text-center" value="<?php echo esc_attr( $existing_marks ); ?>" placeholder="0">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="mt-4 text-end">
                        <button type="submit" name="save_marks" class="btn btn-success px-5 py-2" style="background-color: #10b981; border: none; font-weight: bold;">Submit Marks</button>
                    </div>
                </form>
            </div>
            <?php
        } else {
            echo '<div class="alert alert-warning">No students found in this class.</div>';
        }
    }
}
?>