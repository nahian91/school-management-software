<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Bangladesh Education Board Grading System Scale Helper
if ( ! function_exists( 'educore_calculate_grade' ) ) {
    function educore_calculate_grade( $obtained, $total ) {
        if ( empty( $total ) || $total <= 0 ) {
            return array( 'F', 0.00 );
        }

        $percentage = ( $obtained / $total ) * 100;
        
        if ( $percentage >= 80 ) return array( 'A+', 5.00 );
        if ( $percentage >= 70 ) return array( 'A',  4.00 );
        if ( $percentage >= 60 ) return array( 'A-', 3.50 );
        if ( $percentage >= 50 ) return array( 'B',  3.00 );
        if ( $percentage >= 40 ) return array( 'C',  2.00 );
        if ( $percentage >= 33 ) return array( 'D',  1.00 );
        return array( 'F', 0.00 );
    }
}

function educore_exams_marks_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Handle Marks Submission
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

            // Check if record exists
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table_results} WHERE exam_id = %d AND student_id = %d AND subject_name = %s",
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
            $format = array( '%d', '%d', '%s', '%f', '%f', '%s', '%f', '%d' );

            if ( $existing ) {
                $wpdb->update( $table_results, $data, array( 'id' => $existing ), $format, array( '%d' ) );
            } else {
                $wpdb->insert( $table_results, $data, $format );
            }
            $saved++;
        }

        if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
            IFSEdu_School_Management_System::log_activity( "Updated marks for subject {$subject_name} across {$saved} students." );
        }

        echo '<div class="alert alert-success border-0 shadow-sm mb-4">Marks updated successfully for ' . esc_html( $saved ) . ' students.</div>';
    }

    // Filter Variables
    $filter_exam  = isset( $_GET['exam_id'] ) ? intval( $_GET['exam_id'] ) : 0;
    $filter_class = isset( $_GET['class_name'] ) ? sanitize_text_field( $_GET['class_name'] ) : '';
    $subject_name = isset( $_GET['subject_name'] ) ? sanitize_text_field( $_GET['subject_name'] ) : '';

    // Fetch dynamic options
    $exams   = $wpdb->get_results( "SELECT id, exam_name FROM {$table_exams} ORDER BY id DESC" );
    $classes = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} ORDER BY class_name ASC" );
    
    $back_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=list' );
    ?>

    <div class="mb-3">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; Back to Exams</a>
    </div>

    <!-- Filter Block -->
    <div class="bg-white p-4 rounded shadow-sm border mb-4">
        <h4 class="mb-3 text-success fw-bold">
            <span class="dashicons dashicons-edit me-1"></span> Subject-wise Marks Entry Matrix
        </h4>
        <form method="GET" action="">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="exams">
            <input type="hidden" name="sub" value="marks">
            
            <div class="row align-items-end g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Select Examination <span class="text-danger">*</span></label>
                    <select name="exam_id" class="form-select" required>
                        <option value="">-- Choose Exam Scheme --</option>
                        <?php foreach ( $exams as $ex ) : ?>
                            <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                <?php echo esc_html( $ex->exam_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Select Class / Tier <span class="text-danger">*</span></label>
                    <select name="class_name" class="form-select" required>
                        <option value="">-- Choose Class --</option>
                        <?php foreach ( $classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>>
                                <?php echo esc_html( $cls ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Subject Name <span class="text-danger">*</span></label>
                    <input type="text" name="subject_name" class="form-control" value="<?php echo esc_attr( $subject_name ); ?>" placeholder="e.g. Mathematics, English, Physics" required>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold" style="background-color: #006a4e; border: none;">
                        Load Roster
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Marks Entry Table Area -->
    <?php
    if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $subject_name ) ) {
        $students = $wpdb->get_results( $wpdb->prepare( 
            "SELECT id, student_id, full_name, roll_no, section_name FROM {$table_students} WHERE status = 'Active' AND class_name = %s ORDER BY roll_no ASC", 
            $filter_class 
        ) );
        
        if ( $students ) {
            ?>
            <div class="bg-white p-4 rounded shadow-sm border">
                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_marks_action', 'educore_marks_nonce' ); ?>
                    <input type="hidden" name="exam_id" value="<?php echo intval( $filter_exam ); ?>">
                    <input type="hidden" name="subject_name" value="<?php echo esc_attr( $subject_name ); ?>">
                    
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                        <div>
                            <h5 class="fw-bold m-0 text-slate-800">
                                Target Class: <span class="text-success"><?php echo esc_html( $filter_class ); ?></span> | 
                                Subject: <span class="text-primary"><?php echo esc_html( $subject_name ); ?></span>
                            </h5>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="form-label fw-bold m-0">Full Subject Marks:</label>
                            <input type="number" step="0.01" name="total_marks" id="educore_total_marks" class="form-control text-center fw-bold border-success" style="width: 100px;" value="100" required>
                        </div>
                    </div>

                    <table class="table table-bordered table-hover align-middle mt-3">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 8%;">Roll</th>
                                <th style="width: 15%;">Student ID</th>
                                <th>Student Name</th>
                                <th style="width: 10%;">Section</th>
                                <th style="width: 18%;" class="text-center">Obtained Marks</th>
                                <th style="width: 12%;" class="text-center">Calculated Grade</th>
                                <th style="width: 10%;" class="text-center">GPA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $students as $s ) : 
                                // Fetch existing marks if previously evaluated
                                $existing_marks = $wpdb->get_var( $wpdb->prepare(
                                    "SELECT obtained_marks FROM {$table_results} WHERE exam_id = %d AND student_id = %d AND subject_name = %s",
                                    $filter_exam, $s->id, $subject_name
                                ));

                                list($initial_grade, $initial_gpa) = ( $existing_marks !== null ) ? educore_calculate_grade( floatval($existing_marks), 100 ) : array('-', '-');
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $s->roll_no ); ?></strong></td>
                                <td><code><?php echo esc_html( $s->student_id ); ?></code></td>
                                <td class="fw-semibold"><?php echo esc_html( $s->full_name ); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo esc_html( $s->section_name ? $s->section_name : 'N/A' ); ?></span></td>
                                <td>
                                    <input type="number" step="0.01" name="marks[<?php echo intval( $s->id ); ?>]" class="form-control text-center mark-input fw-bold" value="<?php echo esc_attr( $existing_marks ); ?>" placeholder="0.00" data-student="<?php echo intval( $s->id ); ?>">
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary grade-badge" id="grade_<?php echo intval( $s->id ); ?>"><?php echo esc_html( $initial_grade ); ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold gpa-text" id="gpa_<?php echo intval( $s->id ); ?>"><?php echo esc_html( $initial_gpa ); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="mt-4 text-end">
                        <button type="submit" name="save_marks" class="btn btn-success px-5 py-2 fw-bold" style="background-color: #006a4e; border: none;">
                            Submit Marks Matrix
                        </button>
                    </div>
                </form>
            </div>

            <!-- Dynamic Auto-Grading Script -->
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                function calcGrade(obtained, total) {
                    if (isNaN(obtained) || obtained === '' || total <= 0) return { grade: '-', gpa: '-' };
                    var pct = (obtained / total) * 100;
                    if (pct >= 80) return { grade: 'A+', gpa: '5.00' };
                    if (pct >= 70) return { grade: 'A',  gpa: '4.00' };
                    if (pct >= 60) return { grade: 'A-', gpa: '3.50' };
                    if (pct >= 50) return { grade: 'B',  gpa: '3.00' };
                    if (pct >= 40) return { grade: 'C',  gpa: '2.00' };
                    if (pct >= 33) return { grade: 'D',  gpa: '1.00' };
                    return { grade: 'F', gpa: '0.00' };
                }

                $('.mark-input').on('input', function() {
                    var studentId = $(this).data('student');
                    var obtained  = parseFloat($(this).val());
                    var total     = parseFloat($('#educore_total_marks').val()) || 100;
                    var res       = calcGrade(obtained, total);

                    var $badge = $('#grade_' + studentId);
                    $badge.text(res.grade);

                    if (res.grade === 'F') {
                        $badge.removeClass().addClass('badge bg-danger grade-badge');
                    } else if (res.grade === 'A+') {
                        $badge.removeClass().addClass('badge bg-success grade-badge');
                    } else {
                        $badge.removeClass().addClass('badge bg-primary grade-badge');
                    }

                    $('#gpa_' + studentId).text(res.gpa);
                });
            });
            </script>
            <?php
        } else {
            echo '<div class="alert alert-warning text-center py-4">No active students found in Class <strong>' . esc_html( $filter_class ) . '</strong>.</div>';
        }
    }
}