<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_student_admit_card_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Fetch configured academic units dynamically
    $academic_units = $wpdb->get_results( "SELECT * FROM {$table_units} ORDER BY unit_type DESC, class_name ASC, dept_name ASC" );

    // Filter handling
    $selected_unit_id = isset( $_GET['academic_unit_id'] ) ? intval( $_GET['academic_unit_id'] ) : 0;
    $exam_title       = isset( $_GET['exam_title'] ) ? sanitize_text_field( $_GET['exam_title'] ) : 'Annual Examination - ' . date('Y');
    $exam_year        = isset( $_GET['exam_year'] ) ? sanitize_text_field( $_GET['exam_year'] ) : date('Y');

    // Fetch students based on selected unit
    $students      = array();
    $selected_unit = null;

    if ( $selected_unit_id > 0 ) {
        $selected_unit = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_units} WHERE id = %d", $selected_unit_id ) );

        if ( $selected_unit ) {
            if ( $selected_unit->unit_type === 'College' ) {
                $query = $wpdb->prepare(
                    "SELECT * FROM {$table_students} WHERE status = 'Active' AND class_name = %s ORDER BY roll_no ASC",
                    $selected_unit->class_name
                );
            } else {
                $query = $wpdb->prepare(
                    "SELECT * FROM {$table_students} WHERE status = 'Active' AND class_name = %s AND section_name = %s ORDER BY roll_no ASC",
                    $selected_unit->class_name,
                    $selected_unit->section_name
                );
            }
            $students = $wpdb->get_results( $query );
        }
    }
    ?>

    <style>
        /* Print Styles - Formats 2 Admit Cards per A4 Page */
        @media print {
            body * { visibility: hidden; }
            #educore-printable-admit-area, #educore-printable-admit-area * { visibility: visible; }
            #educore-printable-admit-area { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
            .admit-card-wrapper {
                page-break-inside: avoid;
                break-inside: avoid;
                margin-bottom: 25px !important;
            }
        }

        /* Admit Card UI Design */
        .admit-card-box {
            border: 2px solid #0f172a;
            border-radius: 8px;
            padding: 18px;
            background: #ffffff;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .admit-header {
            border-bottom: 2px double #006a4e;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .admit-title-badge {
            background-color: #006a4e;
            color: #ffffff;
            font-weight: 700;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .admit-table td {
            padding: 4px 8px;
            font-size: 0.9rem;
            vertical-align: middle;
        }
        .admit-table td.label-col {
            font-weight: 600;
            color: #475569;
            width: 28%;
        }
        .student-photo-frame {
            width: 100px;
            height: 115px;
            border: 1px dashed #94a3b8;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            overflow: hidden;
        }
        .student-photo-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .signature-line {
            border-top: 1px solid #64748b;
            width: 130px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            text-align: center;
            padding-top: 4px;
        }
    </style>

    <!-- Filter Controls Card -->
    <div class="bg-white p-4 rounded shadow-sm border mb-4 no-print">
        <h4 class="fw-bold text-success mb-3">
            <span class="dashicons dashicons-tickets-alt me-1"></span> Admit Card Generator Engine
        </h4>
        <form method="GET" action="" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="students">
            <input type="hidden" name="sub" value="admit_card">

            <div class="col-md-4">
                <label class="form-label fw-bold">Select Academic Unit <span class="text-danger">*</span></label>
                <select name="academic_unit_id" class="form-select" required>
                    <option value="">-- Choose Academic Unit --</option>
                    <?php if ( ! empty( $academic_units ) ) : foreach ( $academic_units as $unit ) : ?>
                        <option value="<?php echo intval( $unit->id ); ?>" <?php selected( $selected_unit_id, $unit->id ); ?>>
                            [<?php echo esc_html( $unit->unit_type ); ?>] 
                            <?php echo $unit->unit_type === 'College' ? 'Dept: ' . esc_html( $unit->dept_name ) . ' (' . esc_html( $unit->class_name ) . ')' : 'Class: ' . esc_html( $unit->class_name ) . ' - Sec: ' . esc_html( $unit->section_name ); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold">Exam Title</label>
                <input type="text" name="exam_title" class="form-control" value="<?php echo esc_attr( $exam_title ); ?>" required>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-bold">Academic Year</label>
                <input type="text" name="exam_year" class="form-control" value="<?php echo esc_attr( $exam_year ); ?>" required>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold">
                    <span class="dashicons dashicons-filter align-middle"></span> Generate
                </button>
                <?php if ( ! empty( $students ) ) : ?>
                    <button type="button" onclick="window.print();" class="btn btn-success w-100 fw-bold">
                        <span class="dashicons dashicons-printer align-middle"></span> Print
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Generated Admit Cards Display Area -->
    <?php if ( $selected_unit_id > 0 ) : ?>
        <div id="educore-printable-admit-area">
            <?php if ( ! empty( $students ) ) : ?>
                <div class="row">
                    <?php foreach ( $students as $student ) : ?>
                        <div class="col-md-6 mb-4 admit-card-wrapper">
                            <div class="admit-card-box">
                                
                                <!-- Header Section -->
                                <div class="admit-header text-center">
                                    <h4 class="fw-bold mb-1 text-uppercase text-slate-800" style="letter-spacing: 0.5px;">
                                        <?php echo esc_html( get_bloginfo( 'name' ) ); ?>
                                    </h4>
                                    <p class="small text-muted mb-2"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
                                    <span class="admit-title-badge">
                                        ADMIT CARD - <?php echo esc_html( $exam_title ); ?> (<?php echo esc_html( $exam_year ); ?>)
                                    </span>
                                </div>

                                <!-- Student Details Grid -->
                                <div class="d-flex align-items-start gap-3 mt-3">
                                    <div class="flex-grow-1">
                                        <table class="table table-sm table-borderless admit-table mb-0">
                                            <tr>
                                                <td class="label-col">Student ID:</td>
                                                <td><strong><?php echo esc_html( $student->student_id ); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td class="label-col">Name:</td>
                                                <td><strong class="text-uppercase"><?php echo esc_html( $student->full_name ); ?></strong></td>
                                            </tr>
                                            <?php if ( ! empty( $student->name_bn ) ) : ?>
                                            <tr>
                                                <td class="label-col">নাম (বাংলা):</td>
                                                <td><?php echo esc_html( $student->name_bn ); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td class="label-col"><?php echo ($selected_unit && $selected_unit->unit_type === 'College') ? 'Dept / Year:' : 'Class & Sec:'; ?></td>
                                                <td>
                                                    <strong><?php echo esc_html( $student->class_name ); ?></strong> 
                                                    <?php echo ! empty( $student->section_name ) ? ' (' . esc_html( $student->section_name ) . ')' : ''; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="label-col">Roll No:</td>
                                                <td><span class="badge bg-secondary fs-6"><?php echo esc_html( $student->roll_no ); ?></span></td>
                                            </tr>
                                            <tr>
                                                <td class="label-col">Guardian:</td>
                                                <td><?php echo esc_html( $student->guardian_name ? $student->guardian_name : $student->father_name ); ?></td>
                                            </tr>
                                        </table>
                                    </div>

                                    <!-- Student Profile Photo -->
                                    <div class="text-center">
                                        <div class="student-photo-frame mb-1">
                                            <?php if ( ! empty( $student->photo_url ) ) : ?>
                                                <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="Student">
                                            <?php else : ?>
                                                <span class="text-muted small">NO PHOTO</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Signatures -->
                                <div class="d-flex justify-content-between align-items-end mt-4 pt-3">
                                    <div class="signature-line">
                                        Exam Controller
                                    </div>
                                    <div class="signature-line">
                                        Headmaster / Principal
                                    </div>
                                </div>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="alert alert-warning text-center py-4 no-print">
                    No active student records found for the selected Academic Unit.
                </div>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="alert alert-light border text-center py-5 no-print">
            <span class="dashicons dashicons-info fs-1 text-muted d-block mb-2"></span>
            <h5>Please select an Academic Unit above to generate Examination Admit Cards.</h5>
        </div>
    <?php endif; ?>

    <?php
}
?>