<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

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

    // Strict Security Control: Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to evaluate exam marks.', 'educore' ) );
    }

    // 1. Handle Marks Submission (Single-Hit Processing Matrix)
    if ( isset( $_POST['save_marks'] ) && wp_verify_nonce( $_POST['educore_marks_nonce'], 'save_marks_action' ) ) {
        $exam_id      = intval( $_POST['exam_id'] );
        $subject_name = sanitize_text_field( $_POST['subject_name'] );
        $total_marks  = max( 0, floatval( $_POST['total_marks'] ) );
        $marks_data   = isset( $_POST['marks'] ) ? (array) $_POST['marks'] : array();
        
        $user_id = get_current_user_id();
        $saved = 0;

        if ( ! empty( $marks_data ) && $total_marks > 0 ) {
            // Bulk pre-fetching existing results for this batch transaction to avoid loop query amplification
            $target_student_ids = array_map( 'intval', array_keys( $marks_data ) );
            $ids_placeholder    = implode( ',', array_fill( 0, count( $target_student_ids ), '%d' ) );
            
            $prep_query = $wpdb->prepare(
                "SELECT student_id, id FROM {$table_results} WHERE exam_id = %d AND subject_name = %s AND student_id IN ($ids_placeholder)",
                array_merge( array( $exam_id, $subject_name ), $target_student_ids )
            );
            $existing_records = $wpdb->get_results( $prep_query, OBJECT_K );

            foreach ( $marks_data as $student_id => $obtained ) {
                if ( $obtained === '' ) continue; // Skip empty inputs
                
                $obtained = floatval( $obtained );
                
                // Server-Side Data Integrity Constraint: Prevent marks from exceeding full capacity
                if ( $obtained > $total_marks ) {
                    $obtained = $total_marks;
                }

                list( $grade, $gpa ) = educore_calculate_grade( $obtained, $total_marks );

                $data = array(
                    'exam_id'        => $exam_id,
                    'student_id'     => intval( $student_id ),
                    'subject_name'   => $subject_name,
                    'total_marks'    => $total_marks,
                    'obtained_marks' => $obtained,
                    'grade'          => $grade,
                    'gpa'            => $gpa,
                    'evaluated_by'   => $user_id
                );
                
                $format = array( '%d', '%d', '%s', '%f', '%f', '%s', '%f', '%d' );

                if ( isset( $existing_records[ $student_id ] ) ) {
                    $wpdb->update( 
                        $table_results, 
                        $data, 
                        array( 'id' => intval( $existing_records[ $student_id ]->id ) ), 
                        $format, 
                        array( '%d' ) 
                    );
                } else {
                    $wpdb->insert( $table_results, $data, $format );
                }
                $saved++;
            }
        }

        if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
            IFSEdu_School_Management_System::log_activity( "Updated marks for subject {$subject_name} across {$saved} students." );
        }

        echo '<div class="alert alert-success border-0 shadow-sm mb-4">Marks configuration parsed and saved successfully for ' . esc_html( $saved ) . ' students.</div>';
    }

    // 2. Filter Variables Evaluation
    $filter_exam  = isset( $_GET['exam_id'] ) ? intval( $_GET['exam_id'] ) : 0;
    $filter_class = isset( $_GET['class_name'] ) ? sanitize_text_field( $_GET['class_name'] ) : '';
    $subject_name = isset( $_GET['subject_name'] ) ? sanitize_text_field( $_GET['subject_name'] ) : '';

    // Fetch Dynamic Dropdowns Structure
    $exams   = $wpdb->get_results( "SELECT id, exam_name FROM {$table_exams} ORDER BY id DESC" );
    $classes = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} ORDER BY class_name ASC" );
    
    $back_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=list' );
    ?>

    <div class="mb-3">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm shadow-none">&larr; Back to Exams</a>
    </div>

    <!-- Filter Console Block -->
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
                    <label class="form-label fw-bold text-muted">Select Examination <span class="text-danger">*</span></label>
                    <select name="exam_id" class="form-select shadow-none" required>
                        <option value="">-- Choose Exam Scheme --</option>
                        <?php foreach ( $exams as $ex ) : ?>
                            <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                <?php echo esc_html( $ex->exam_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold text-muted">Select Class / Tier <span class="text-danger">*</span></label>
                    <select name="class_name" class="form-select shadow-none" required>
                        <option value="">-- Choose Class --</option>
                        <?php foreach ( $classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>>
                                <?php echo esc_html( $cls ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold text-muted">Subject Name <span class="text-danger">*</span></label>
                    <input type="text" name="subject_name" class="form-control shadow-none" value="<?php echo esc_attr( $subject_name ); ?>" placeholder="e.g. Mathematics, English, Physics" required>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold shadow-none" style="background-color: #006a4e; border: none;">
                        Load Roster
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Marks Entry Table Module Area -->
    <?php
    if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $subject_name ) ) {
        $students = $wpdb->get_results( $wpdb->prepare( 
            "SELECT id, student_id, full_name, roll_no, section_name FROM {$table_students} WHERE status = 'Active' AND class_name = %s ORDER BY roll_no ASC", 
            $filter_class 
        ) );
        
        if ( $students ) {
            // Memory caching optimization layer for target sheet grid render
            $student_ids  = wp_list_pluck( $students, 'id' );
            $placeholders = implode( ',', array_fill( 0, count( $student_ids ), '%d' ) );
            
            $cached_results_query = $wpdb->prepare(
                "SELECT student_id, total_marks, obtained_marks FROM {$table_results} WHERE exam_id = %d AND subject_name = %s AND student_id IN ($placeholders)",
                array_merge( array( $filter_exam, $subject_name ), $student_ids )
            );
            $loaded_results_states = $wpdb->get_results( $cached_results_query, OBJECT_K );
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
                            <input type="number" step="0.01" name="total_marks" id="educore_total_marks" class="form-control text-center fw-bold border-success shadow-none" style="width: 100px;" value="100" min="1" required>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mt-3 mb-0">
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
                            </tbody>
                            <tbody>
                                <?php 
                                foreach ( $students as $s ) : 
                                    $student_internal_id = intval( $s->id );
                                    $existing_marks      = isset( $loaded_results_states[ $student_internal_id ] ) ? $loaded_results_states[ $student_internal_id ]->obtained_marks : '';
                                    $full_subject_marks  = isset( $loaded_results_states[ $student_internal_id ] ) ? floatval( $loaded_results_states[ $student_internal_id ]->total_marks ) : 100;

                                    list( $initial_grade, $initial_gpa ) = ( $existing_marks !== '' && $existing_marks !== null ) ? educore_calculate_grade( floatval( $existing_marks ), $full_subject_marks ) : array( '-', '-' );
                                    
                                    // CSS badge class management matrix based on evaluation state
                                    $badge_class = 'bg-secondary';
                                    if ( $initial_grade === 'A+' ) {
                                        $badge_class = 'bg-success';
                                    } elseif ( $initial_grade === 'F' ) {
                                        $badge_class = 'bg-danger';
                                    } elseif ( $initial_grade !== '-' ) {
                                        $badge_class = 'bg-primary';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $s->roll_no ); ?></strong></td>
                                    <td><code><?php echo esc_html( $s->student_id ); ?></code></td>
                                    <td class="fw-semibold text-dark"><?php echo esc_html( $s->full_name ); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo esc_html( $s->section_name ? $s->section_name : 'N/A' ); ?></span></td>
                                    <td>
                                        <input type="number" step="0.01" name="marks[<?php echo $student_internal_id; ?>]" class="form-control text-center mark-input fw-bold shadow-none" value="<?php echo esc_attr( $existing_marks ); ?>" placeholder="0.00" min="0" data-student="<?php echo $student_internal_id; ?>">
                                    </td>
                                    <td class="text-center">
                                        <span class="badge grade-badge <?php echo esc_attr( $badge_class ); ?>" id="grade_<?php echo $student_internal_id; ?>"><?php echo esc_html( $initial_grade ); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold gpa-text" id="gpa_<?php echo $student_internal_id; ?>"><?php echo esc_html( $initial_gpa ); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" name="save_marks" class="btn btn-success px-5 py-2 fw-bold shadow-none" style="background-color: #006a4e; border: none;">
                            Submit Marks Matrix
                        </button>
                    </div>
                </form>
            </div>

            <!-- Dynamic Auto-Grading Calculation Engine Script -->
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                function calcGrade(obtained, total) {
                    if (isNaN(obtained) || obtained === '' || obtained < 0 || total <= 0) return { grade: '-', gpa: '-' };
                    
                    // Cap input values if they exceed max score parameter limits
                    var pct = (obtained / total) * 100;
                    if (pct > 100) pct = 100;

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
                    
                    // Visual warning feedback check
                    if (obtained > total) {
                        $(this).addClass('border-danger text-danger');
                    } else {
                        $(this).removeClass('border-danger text-danger');
                    }

                    var res = calcGrade(obtained, total);
                    var $badge = $('#grade_' + studentId);
                    $badge.text(res.grade);

                    $badge.removeClass('bg-secondary bg-success bg-danger bg-primary');
                    if (res.grade === 'F') {
                        $badge.addClass('bg-danger');
                    } else if (res.grade === 'A+') {
                        $badge.addClass('bg-success');
                    } else if (res.grade === '-') {
                        $badge.addClass('bg-secondary');
                    } else {
                        $badge.addClass('bg-primary');
                    }

                    $('#gpa_' + studentId).text(res.gpa);
                });

                // Trigger adjustments if full marks change live
                $('#educore_total_marks').on('input', function() {
                    $('.mark-input').trigger('input');
                });
            });
            </script>
            <?php
        } else {
            echo '<div class="alert alert-warning text-center py-4 border-0 shadow-sm">No active students found in Class <strong>' . esc_html( $filter_class ) . '</strong>.</div>';
        }
    }
}