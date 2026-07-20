<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_exams_report_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Fetch Dynamic Dropdown Data
    $exams   = $wpdb->get_results( "SELECT id, exam_name FROM {$table_exams} ORDER BY id DESC" );
    $classes = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} ORDER BY class_name ASC" );

    // GET Filter Parameters
    $filter_exam    = isset( $_GET['exam_id'] ) ? intval( $_GET['exam_id'] ) : 0;
    $report_type    = isset( $_GET['report_type'] ) ? sanitize_text_field( $_GET['report_type'] ) : 'individual';
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( $_GET['class_name'] ) : '';
    $filter_student = isset( $_GET['student_id'] ) ? intval( $_GET['student_id'] ) : 0;
    
    $back_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=list' );
    ?>

    <div class="mb-3 no-print">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; Back to Exams</a>
    </div>

    <!-- Generator Control Card -->
    <div class="bg-white p-4 rounded shadow-sm border mb-4 no-print">
        <h4 class="mb-3 text-success fw-bold">
            <span class="dashicons dashicons-clipboard me-1"></span> Academic Progress & Tabulation Generator
        </h4>
        <form method="GET" action="">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="exams">
            <input type="hidden" name="sub" value="report">
            
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

                <div class="col-md-2">
                    <label class="form-label fw-bold">Report Type <span class="text-danger">*</span></label>
                    <select name="report_type" id="educore_report_type" class="form-select" required>
                        <option value="individual" <?php selected( $report_type, 'individual' ); ?>>Student Marksheet</option>
                        <option value="tabulation" <?php selected( $report_type, 'tabulation' ); ?>>Class Tabulation Sheet</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold">Class / Tier <span class="text-danger">*</span></label>
                    <select name="class_name" id="educore_class_filter" class="form-select" required>
                        <option value="">-- Choose Class --</option>
                        <?php foreach ( $classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>>
                                <?php echo esc_html( $cls ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3" id="student_select_box" style="<?php echo ($report_type === 'tabulation') ? 'display:none;' : ''; ?>">
                    <label class="form-label fw-bold">Select Student</label>
                    <select name="student_id" class="form-select">
                        <option value="">-- Choose Student --</option>
                        <?php 
                        if ( ! empty( $filter_class ) ) {
                            $student_list = $wpdb->get_results( $wpdb->prepare(
                                "SELECT id, full_name, student_id, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s ORDER BY roll_no ASC",
                                $filter_class
                            ));
                            foreach ( $student_list as $s ) : ?>
                                <option value="<?php echo intval( $s->id ); ?>" <?php selected( $filter_student, $s->id ); ?>>
                                    Roll <?php echo esc_html( $s->roll_no ); ?>: <?php echo esc_html( $s->full_name ); ?> (<?php echo esc_html( $s->student_id ); ?>)
                                </option>
                            <?php endforeach;
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold" style="background-color: #006a4e; border: none;">
                        Generate
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Toggle Student Selector Dropdown via JS -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#educore_report_type').on('change', function() {
            if ($(this).val() === 'tabulation') {
                $('#student_select_box').hide();
            } else {
                $('#student_select_box').show();
            }
        });
    });
    </script>

    <?php
    // ==========================================
    // CASE A: INDIVIDUAL STUDENT MARKSHEET REPORT
    // ==========================================
    if ( $filter_exam > 0 && $report_type === 'individual' && $filter_student > 0 ) {
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_students} WHERE id = %d", $filter_student ) );
        $exam    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_exams} WHERE id = %d", $filter_exam ) );
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_results} WHERE exam_id = %d AND student_id = %d", $filter_exam, $filter_student ) );

        if ( ! $results ) {
            echo '<div class="alert alert-warning text-center py-4 no-print">No published marks found for this student in the selected examination.</div>';
            return;
        }

        // Aggregate Statistics
        $total_sub          = count( $results );
        $sum_gpa            = 0;
        $total_marks_all    = 0;
        $obtained_marks_all = 0;
        $has_failed         = false;

        foreach ( $results as $r ) {
            $sum_gpa            += $r->gpa;
            $total_marks_all    += $r->total_marks;
            $obtained_marks_all += $r->obtained_marks;
            if ( $r->grade === 'F' ) $has_failed = true;
        }

        $avg_gpa     = ( $total_sub > 0 ) ? ( $sum_gpa / $total_sub ) : 0;
        $final_gpa   = $has_failed ? '0.00' : number_format( $avg_gpa, 2 );
        $final_grade = $has_failed ? 'F' : educore_calculate_grade( $obtained_marks_all, $total_marks_all )[0];
        $school_name = get_bloginfo('name');
        ?>

        <style>
            .report-card-container { max-width: 820px; margin: 0 auto; background: #fff; padding: 40px; border: 2px solid #0f172a; border-radius: 8px; font-family: sans-serif; }
            .report-header { text-align: center; border-bottom: 2px double #006a4e; padding-bottom: 15px; margin-bottom: 20px; }
            .marks-table th, .marks-table td { border: 1px solid #cbd5e1; padding: 8px 12px; text-align: center; font-size: 0.9rem; }
            .marks-table th { background-color: #f8fafc; font-weight: bold; }
            .gpa-box { border: 2px solid #006a4e; padding: 15px; text-align: center; border-radius: 8px; margin-top: 25px; background: #f0fdf4; }
            .sign-line { border-top: 1px solid #64748b; width: 180px; text-align: center; font-size: 0.85rem; font-weight: 600; padding-top: 4px; }
            
            @media print {
                body * { visibility: hidden; }
                .report-card-container, .report-card-container * { visibility: visible; }
                .report-card-container { position: absolute; left: 0; top: 0; width: 100%; border: none; padding: 0; }
                .no-print { display: none !important; }
            }
        </style>

        <div class="text-center mb-3 no-print">
            <button onclick="window.print();" class="btn btn-success btn-lg px-5 fw-bold">
                <span class="dashicons dashicons-printer align-middle me-1"></span> Print Academic Report Card
            </button>
        </div>

        <div class="report-card-container shadow-sm">
            <div class="report-header">
                <h2 class="m-0 fw-bold text-uppercase" style="color: #006a4e; letter-spacing: 0.5px;"><?php echo esc_html( $school_name ); ?></h2>
                <h4 class="mt-2 mb-1 fw-bold text-slate-800"><?php echo esc_html( $exam->exam_name ); ?></h4>
                <span class="badge bg-success px-3 py-2 fs-6 text-uppercase">Academic Progress Report Card</span>
            </div>

            <div class="row mb-4">
                <div class="col-6">
                    <p class="m-1"><strong>Student Name:</strong> <span class="text-uppercase fw-bold"><?php echo esc_html( $student->full_name ); ?></span></p>
                    <p class="m-1"><strong>Student ID:</strong> <code><?php echo esc_html( $student->student_id ); ?></code></p>
                    <p class="m-1"><strong>Guardian:</strong> <?php echo esc_html( $student->guardian_name ? $student->guardian_name : $student->father_name ); ?></p>
                </div>
                <div class="col-6 text-end">
                    <p class="m-1"><strong>Class / Tier:</strong> Class <?php echo esc_html( $student->class_name ); ?></p>
                    <p class="m-1"><strong>Section / Group:</strong> <?php echo esc_html( $student->section_name ? $student->section_name : 'N/A' ); ?></p>
                    <p class="m-1"><strong>Class Roll No:</strong> <span class="badge bg-secondary"><?php echo esc_html( $student->roll_no ); ?></span></p>
                </div>
            </div>

            <table class="table marks-table mb-4">
                <thead>
                    <tr>
                        <th class="text-start">Subject Name</th>
                        <th>Total Marks</th>
                        <th>Obtained Marks</th>
                        <th>Letter Grade</th>
                        <th>Grade Point (GP)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach( $results as $r ) : ?>
                    <tr>
                        <td class="text-start fw-bold"><?php echo esc_html( $r->subject_name ); ?></td>
                        <td><?php echo floatval( $r->total_marks ); ?></td>
                        <td><strong><?php echo floatval( $r->obtained_marks ); ?></strong></td>
                        <td class="fw-bold <?php echo $r->grade === 'F' ? 'text-danger' : 'text-success'; ?>"><?php echo esc_html( $r->grade ); ?></td>
                        <td><?php echo number_format( $r->gpa, 2 ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="gpa-box">
                <h4 class="m-0 fw-bold text-success text-uppercase">Final Evaluation Result</h4>
                <p class="fs-5 m-0 mt-2">
                    Overall Status: <strong class="<?php echo $has_failed ? 'text-danger' : 'text-success'; ?>"><?php echo $has_failed ? 'FAILED (F)' : 'PASSED (' . $final_grade . ')'; ?></strong> &nbsp;|&nbsp; 
                    Cumulative GPA: <strong class="fs-4"><?php echo esc_html( $final_gpa ); ?></strong>
                </p>
            </div>

            <div class="d-flex justify-content-between align-items-end mt-5 pt-4">
                <div class="sign-line">Class Teacher Signature</div>
                <div class="sign-line">Exam Controller</div>
                <div class="sign-line">Principal / Headmaster</div>
            </div>
        </div>
        <?php
    }

    // ==========================================
    // CASE B: CLASS TABULATION SHEET REPORT
    // ==========================================
    elseif ( $filter_exam > 0 && $report_type === 'tabulation' && ! empty( $filter_class ) ) {
        $exam     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_exams} WHERE id = %d", $filter_exam ) );
        $students = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_students} WHERE status = 'Active' AND class_name = %s ORDER BY roll_no ASC", $filter_class ) );
        $subjects = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT subject_name FROM {$table_results} WHERE exam_id = %d ORDER BY subject_name ASC", $filter_exam ) );

        if ( ! $students || ! $subjects ) {
            echo '<div class="alert alert-warning text-center py-4 no-print">No evaluated results or subjects found for Class <strong>' . esc_html( $filter_class ) . '</strong> in this exam.</div>';
            return;
        }
        ?>

        <style>
            .tabulation-container { background: #fff; padding: 25px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: sans-serif; }
            .tabulation-table th, .tabulation-table td { border: 1px solid #94a3b8; text-align: center; vertical-align: middle; padding: 6px; font-size: 0.85rem; }
            .tabulation-table th { background-color: #f1f5f9; font-weight: bold; }
            @media print {
                body * { visibility: hidden; }
                .tabulation-container, .tabulation-container * { visibility: visible; }
                .tabulation-container { position: absolute; left: 0; top: 0; width: 100%; border: none; padding: 0; }
                .no-print { display: none !important; }
            }
        </style>

        <div class="text-center mb-3 no-print">
            <button onclick="window.print();" class="btn btn-success btn-lg px-5 fw-bold">
                <span class="dashicons dashicons-printer align-middle me-1"></span> Print Class Tabulation Sheet
            </button>
        </div>

        <div class="tabulation-container shadow-sm">
            <div class="text-center mb-4 border-bottom pb-3">
                <h3 class="fw-bold m-0 text-uppercase" style="color: #006a4e;"><?php echo esc_html( get_bloginfo('name') ); ?></h3>
                <h5 class="fw-bold m-0 mt-1"><?php echo esc_html( $exam->exam_name ); ?> - Tabulation Sheet</h5>
                <span class="badge bg-secondary mt-1">Class: <?php echo esc_html( $filter_class ); ?></span>
            </div>

            <div class="table-responsive">
                <table class="table tabulation-table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 5%;">Roll</th>
                            <th style="width: 10%;">ID</th>
                            <th style="width: 18%;" class="text-start">Student Name</th>
                            <?php foreach ( $subjects as $sub ) : ?>
                                <th><?php echo esc_html( $sub ); ?></th>
                            <?php endforeach; ?>
                            <th style="width: 10%;">Total Score</th>
                            <th style="width: 8%;">GPA</th>
                            <th style="width: 8%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $students as $s ) : 
                            $student_results = $wpdb->get_results( $wpdb->prepare(
                                "SELECT subject_name, obtained_marks, grade, gpa FROM {$table_results} WHERE exam_id = %d AND student_id = %d",
                                $filter_exam, $s->id
                            ), OBJECT_K );

                            $total_obtained = 0;
                            $sum_gpa        = 0;
                            $sub_count      = 0;
                            $has_failed     = false;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $s->roll_no ); ?></strong></td>
                            <td><code><?php echo esc_html( $s->student_id ); ?></code></td>
                            <td class="text-start fw-semibold"><?php echo esc_html( $s->full_name ); ?></td>
                            
                            <?php foreach ( $subjects as $sub ) : 
                                if ( isset( $student_results[ $sub ] ) ) {
                                    $res = $student_results[ $sub ];
                                    $total_obtained += $res->obtained_marks;
                                    $sum_gpa        += $res->gpa;
                                    $sub_count++;
                                    if ( $res->grade === 'F' ) $has_failed = true;
                                    ?>
                                    <td>
                                        <strong><?php echo floatval( $res->obtained_marks ); ?></strong><br>
                                        <small class="<?php echo $res->grade === 'F' ? 'text-danger' : 'text-muted'; ?>">(<?php echo esc_html( $res->grade ); ?>)</small>
                                    </td>
                                <?php } else { ?>
                                    <td class="text-muted">-</td>
                                <?php }
                            endforeach; 

                            $avg_gpa   = ( $sub_count > 0 ) ? ( $sum_gpa / $sub_count ) : 0;
                            $final_gpa = $has_failed ? '0.00' : number_format( $avg_gpa, 2 );
                            ?>

                            <td class="fw-bold"><?php echo floatval( $total_obtained ); ?></td>
                            <td class="fw-bold <?php echo $has_failed ? 'text-danger' : 'text-success'; ?>"><?php echo esc_html( $final_gpa ); ?></td>
                            <td>
                                <span class="badge <?php echo $has_failed ? 'bg-danger' : 'bg-success'; ?>">
                                    <?php echo $has_failed ? 'FAIL' : 'PASS'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-end mt-5 pt-4">
                <div class="sign-line">Prepared By</div>
                <div class="sign-line">Checked By</div>
                <div class="sign-line">Headmaster / Principal</div>
            </div>
        </div>
        <?php
    }
}