<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_student_id_card_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Fetch classes dynamically from academic units table
    $academic_units = $wpdb->get_results( "SELECT * FROM {$table_units} ORDER BY unit_type DESC, class_name ASC" );

    // Filter values
    $selected_unit_id = isset( $_GET['academic_unit_id'] ) ? intval( $_GET['academic_unit_id'] ) : 0;
    
    $students = array();
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
        @media print {
            body * { visibility: hidden; }
            #educore-printable-id-area, #educore-printable-id-area * { visibility: visible; }
            #educore-printable-id-area { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
            .id-card-wrapper { page-break-inside: avoid; break-inside: avoid; }
        }

        /* CR80 Standard ID Card Dimensions (3.375in x 2.125in) */
        .id-card-box {
            width: 330px;
            height: 215px;
            border: 2px solid #0f172a;
            border-radius: 10px;
            background: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            font-family: sans-serif;
        }
        .id-card-header {
            background-color: #006a4e;
            color: #ffffff;
            padding: 8px;
            text-align: center;
        }
        .id-card-body {
            padding: 10px;
            display: flex;
            gap: 10px;
        }
        .id-photo-frame {
            width: 75px;
            height: 90px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            background: #f8fafc;
            flex-shrink: 0;
        }
        .id-photo-frame img { width: 100%; height: 100%; object-fit: cover; }
        .id-card-table { font-size: 0.78rem; width: 100%; border-collapse: collapse; }
        .id-card-table td { padding: 2px 0; }
        .id-card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: #f1f5f9;
            padding: 4px 10px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.7rem;
        }
    </style>

    <!-- Filter Card -->
    <div class="bg-white p-4 rounded shadow-sm border mb-4 no-print">
        <h4 class="fw-bold text-success mb-3">
            <span class="dashicons dashicons-id-alt me-1"></span> Student ID Card Generator
        </h4>
        <form method="GET" action="" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="students">
            <input type="hidden" name="sub" value="id_card">

            <div class="col-md-8">
                <label class="form-label fw-bold">Select Configured Academic Unit <span class="text-danger">*</span></label>
                <select name="academic_unit_id" class="form-select" required>
                    <option value="">-- Choose Academic Class/Department --</option>
                    <?php foreach ( $academic_units as $unit ) : ?>
                        <option value="<?php echo intval( $unit->id ); ?>" <?php selected( $selected_unit_id, $unit->id ); ?>>
                            [<?php echo esc_html( $unit->unit_type ); ?>] 
                            <?php echo $unit->unit_type === 'College' ? 'Dept: ' . esc_html( $unit->dept_name ) . ' (' . esc_html( $unit->class_name ) . ')' : 'Class: ' . esc_html( $unit->class_name ) . ' - Sec: ' . esc_html( $unit->section_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold">Fetch Records</button>
                <?php if ( ! empty( $students ) ) : ?>
                    <button type="button" onclick="window.print();" class="btn btn-success w-100 fw-bold">Print ID Cards</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Cards Display Grid -->
    <?php if ( $selected_unit_id > 0 ) : ?>
        <div id="educore-printable-id-area">
            <?php if ( ! empty( $students ) ) : ?>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ( $students as $student ) : ?>
                        <div class="id-card-wrapper mb-3">
                            <div class="id-card-box">
                                <div class="id-card-header">
                                    <h6 class="m-0 fw-bold text-uppercase" style="font-size: 0.85rem;"><?php echo esc_html( get_bloginfo('name') ); ?></h6>
                                    <small style="font-size: 0.65rem;">STUDENT IDENTITY CARD</small>
                                </div>
                                <div class="id-card-body">
                                    <div class="id-photo-frame">
                                        <?php if ( ! empty( $student->photo_url ) ) : ?>
                                            <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="Student">
                                        <?php else : ?>
                                            <div class="d-flex h-100 align-items-center justify-content-center text-muted" style="font-size:0.6rem;">NO PHOTO</div>
                                        <?php endif; ?>
                                    </div>
                                    <table class="id-card-table">
                                        <tr><td style="color:#64748b; width:35%;">ID No:</td><td><strong><?php echo esc_html( $student->student_id ); ?></strong></td></tr>
                                        <tr><td style="color:#64748b;">Name:</td><td><strong><?php echo esc_html( $student->full_name ); ?></strong></td></tr>
                                        <tr><td style="color:#64748b;">Class/Year:</td><td><?php echo esc_html( $student->class_name ); ?></td></tr>
                                        <tr><td style="color:#64748b;">Sec/Dept:</td><td><?php echo esc_html( $student->section_name ? $student->section_name : 'N/A' ); ?></td></tr>
                                        <tr><td style="color:#64748b;">Roll No:</td><td><?php echo esc_html( $student->roll_no ); ?></td></tr>
                                        <tr><td style="color:#64748b;">Mobile:</td><td><?php echo esc_html( $student->guardian_phone ); ?></td></tr>
                                    </table>
                                </div>
                                <div class="id-card-footer">
                                    <span>Blood: <strong><?php echo esc_html( $student->blood_group ? $student->blood_group : 'N/A' ); ?></strong></span>
                                    <span>Principal Signature</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="alert alert-warning text-center py-4 no-print">No active students registered for this academic unit.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php
}