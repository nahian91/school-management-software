<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

/**
 * Enterprise Academic Admit Card Engine & Precision Print Compiler
 * File: student-admit-card-view.php
 */

// --------------------------------------------------------------------------
// 0. AJAX HANDLERS FOR DYNAMIC SELECTORS
// --------------------------------------------------------------------------
add_action( 'wp_ajax_educore_get_sections_by_class_admit', 'educore_get_sections_by_class_admit_handler' );
function educore_get_sections_by_class_admit_handler() {
    check_ajax_referer( 'educore_admit_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ifsedu-sms' ) ) );
    }

    global $wpdb;
    $table_units = $wpdb->prefix . 'sms_academic_units';
    $class_name  = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $sections = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT section_name FROM {$table_units} WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
        $class_name
    ) );

    wp_send_json_success( $sections );
}

add_action( 'wp_ajax_educore_get_students_by_class_admit', 'educore_get_students_by_class_admit_handler' );
function educore_get_students_by_class_admit_handler() {
    check_ajax_referer( 'educore_admit_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ifsedu-sms' ) ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name   = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $sql    = "SELECT id, full_name, student_id, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
    $params = array( $class_name );

    if ( ! empty( $section_name ) ) {
        $sql .= " AND section_name = %s";
        $params[] = $section_name;
    }

    $sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
    $students = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

    wp_send_json_success( $students );
}

// --------------------------------------------------------------------------
// 1. MAIN ADMIT CARD COMPILER VIEW
// --------------------------------------------------------------------------
function educore_student_admit_card_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access this page.', 'ifsedu-sms' ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_exams    = $wpdb->prefix . 'sms_exams';

    // Fetch Exams
    $exams = $wpdb->get_results( "SELECT id, exam_name FROM {$table_exams} ORDER BY id DESC" );

    // Fetch Unique Classes (Numeric Sort)
    $raw_classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    $classes     = array();
    if ( ! empty( $raw_classes ) ) {
        usort( $raw_classes, function( $a, $b ) {
            return strnatcasecmp( $a->class_name, $b->class_name );
        });
        foreach ( $raw_classes as $cls_obj ) {
            $classes[] = $cls_obj->class_name;
        }
    }

    // Capture Filter Requests
    $selected_exam_id  = isset( $_GET['exam_id'] ) ? absint( $_GET['exam_id'] ) : 0;
    $selected_class    = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $selected_section  = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $selected_student  = isset( $_GET['student_id'] ) ? absint( $_GET['student_id'] ) : 0;
    $exam_year         = isset( $_GET['exam_year'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_year'] ) ) : current_time( 'Y' );

    // Pre-populate sections & students if class filter is present
    $available_sections = array();
    $available_students = array();
    if ( ! empty( $selected_class ) ) {
        $available_sections = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT section_name FROM {$table_units} WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
            $selected_class
        ) );

        $st_sql = "SELECT id, full_name, student_id, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $st_params = array( $selected_class );
        if ( ! empty( $selected_section ) ) {
            $st_sql .= " AND section_name = %s";
            $st_params[] = $selected_section;
        }
        $st_sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $available_students = $wpdb->get_results( $wpdb->prepare( $st_sql, ...$st_params ) );
    }

    $students   = array();
    $exam_title = '';

    // Resolve Exam Name
    if ( $selected_exam_id > 0 ) {
        $exam_row = $wpdb->get_row( $wpdb->prepare( "SELECT exam_name FROM {$table_exams} WHERE id = %d", $selected_exam_id ) );
        if ( $exam_row ) {
            $exam_title = $exam_row->exam_name;
        }
    }

    // Fetch Target Students Dataset
    if ( ! empty( $selected_class ) && $selected_exam_id > 0 ) {
        $query  = "SELECT * FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $params = array( $selected_class );

        if ( ! empty( $selected_section ) ) {
            $query .= " AND section_name = %s";
            $params[] = $selected_section;
        }

        if ( $selected_student > 0 ) {
            $query .= " AND id = %d";
            $params[] = $selected_student;
        }

        $query .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $students = $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );
    }

    $school_name = get_bloginfo( 'name' );
    if ( empty( $school_name ) || $school_name === 'WordPress' ) {
        $school_name = 'Green Gems International School & College';
    }
    ?>

    <style>
        .dpt-admit-engine-root {
            margin: 24px 20px 0 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .afdp-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.03);
            margin-bottom: 32px;
        }

        .afdp-bento-card h2 {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 24px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dpt-form-grid-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 16px;
            align-items: flex-end;
        }

        .dpt-input-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .dpt-input-block label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
        }

        .dpt-input-block select, 
        .dpt-input-block input[type="text"] {
            width: 100%;
            height: 42px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13.5px;
            color: #0f172a;
            transition: all 0.2s ease;
        }

        .dpt-input-block select:focus, 
        .dpt-input-block input[type="text"]:focus {
            border-color: #006a4e;
            background: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
        }

        .dpt-action-block { display: flex; gap: 10px; }

        .dpt-btn {
            height: 42px;
            padding: 0 18px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .dpt-btn-primary { background: #006a4e; color: #ffffff; }
        .dpt-btn-primary:hover { background: #00523c; }

        .dpt-btn-secondary { background: #0f172a; color: #ffffff; }
        .dpt-btn-secondary:hover { background: #1e293b; }

        /* Admit Card Outer Architecture */
        .dpt-admit-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(490px, 1fr));
            gap: 24px;
        }

        .admit-card-wrapper {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
            position: relative;
        }

        .admit-card-top-tools {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            padding: 8px 14px;
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid #e2e8f0;
            margin: -20px -20px 16px -20px;
        }

        .single-print-btn {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #006a4e;
            font-size: 11.5px;
            font-weight: 800;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
        }

        .single-print-btn:hover {
            background: #006a4e;
            color: #ffffff;
            border-color: #006a4e;
        }

        /* Professional Admit Design */
        .admit-card-box {
            border: 2px solid #006a4e;
            border-radius: 10px;
            padding: 16px 20px;
            background: #ffffff;
            position: relative;
        }

        .admit-header {
            text-align: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }

        .admit-school-title {
            font-size: 18px;
            font-weight: 900;
            color: #006a4e;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.2;
        }

        .admit-school-sub {
            font-size: 11px;
            color: #64748b;
            margin: 3px 0 10px 0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .admit-title-badge {
            background: linear-gradient(135deg, #006a4e 0%, #004d38 100%);
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 18px;
            border-radius: 20px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            box-shadow: 0 2px 6px rgba(0, 106, 78, 0.25);
        }

        .admit-body-layout {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .admit-details-column { flex: 1; }

        .admit-table { 
            width: 100%; 
            border-collapse: collapse; 
        }

        .admit-table td { 
            padding: 4px 0; 
            font-size: 12.5px; 
            color: #334155; 
            border: none !important; 
        }

        .admit-table td.label-col { 
            font-weight: 700; 
            color: #64748b; 
            width: 32%; 
            text-transform: uppercase;
            font-size: 11px;
        }

        .admit-table td.value-col { 
            font-weight: 800; 
            color: #0f172a; 
        }

        .student-photo-frame {
            width: 90px;
            height: 110px;
            border: 1.5px solid #006a4e;
            border-radius: 6px;
            background: #f8fafc;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.06);
        }

        .student-photo-frame img { width: 100%; height: 100%; object-fit: cover; }
        .student-photo-frame span { font-size: 9px; font-weight: 800; color: #94a3b8; text-align: center; }

        .admit-instructions {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #006a4e;
            border-radius: 6px;
            padding: 8px 12px;
            margin-top: 8px;
            font-size: 10.5px;
            color: #475569;
        }

        .signature-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 26px;
            padding: 0 10px;
        }

        .signature-line {
            width: 140px;
            border-top: 1px dashed #334155;
            padding-top: 4px;
            font-size: 10px;
            font-weight: 800;
            color: #334155;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ==========================================================================
           PRINT STYLES
           ========================================================================== */
        @media print {
            @page { size: A4 portrait; margin: 10mm 8mm; }

            .no-print, #adminmenumain, #wpadminbar, #wpfooter, .admit-card-top-tools { display: none !important; }
            body { background: #ffffff !important; margin: 0 !important; padding: 0 !important; }

            #educore-printable-admit-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .dpt-admit-cards-container { 
                display: block !important; 
            }

            .admit-card-wrapper {
                page-break-inside: avoid;
                break-inside: avoid;
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin-bottom: 24px !important;
            }

            .admit-card-box {
                border: 2px solid #000000 !important;
                background: #ffffff !important;
            }

            .admit-school-title { color: #000000 !important; }

            .admit-title-badge {
                background: #000000 !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body.printing-single-card .admit-card-wrapper {
                display: none !important;
            }

            body.printing-single-card .admit-card-wrapper.target-single-print {
                display: block !important;
                margin: 0 !important;
            }
        }
    </style>

    <div class="dpt-admit-engine-root">
        
        <!-- Filter Form -->
        <div class="afdp-bento-card no-print">
            <h2>
                <span class="dashicons dashicons-tickets-alt" style="color:#006a4e;"></span>
                <?php esc_html_e( 'Academic Admit Card Compiler', 'ifsedu-sms' ); ?>
            </h2>

            <form method="GET" action="" class="dpt-form-grid-wrapper">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="students">
                <input type="hidden" name="sub" value="admit_card">

                <div class="dpt-input-block">
                    <label><?php esc_html_e( 'Select Examination', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="exam_id" required>
                        <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $exams as $ex ) : ?>
                            <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $selected_exam_id, $ex->id ); ?>>
                                <?php echo esc_html( $ex->exam_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-input-block">
                    <label><?php esc_html_e( 'Select Class', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="class_name" id="educore_admit_class_select" required>
                        <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $classes as $cls_name ) : ?>
                            <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $selected_class, $cls_name ); ?>>
                                <?php echo esc_html( $cls_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-input-block">
                    <label><?php esc_html_e( 'Select Section', 'ifsedu-sms' ); ?></label>
                    <select name="section_name" id="educore_admit_section_select">
                        <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $available_sections as $sec_name ) : ?>
                            <option value="<?php echo esc_attr( $sec_name ); ?>" <?php selected( $selected_section, $sec_name ); ?>>
                                <?php echo esc_html( $sec_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Individual Student Selector -->
                <div class="dpt-input-block">
                    <label><?php esc_html_e( 'Single Student (Optional)', 'ifsedu-sms' ); ?></label>
                    <select name="student_id" id="educore_admit_student_select">
                        <option value="0"><?php esc_html_e( '-- All Students in Section --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $available_students as $st_item ) : ?>
                            <option value="<?php echo intval( $st_item->id ); ?>" <?php selected( $selected_student, $st_item->id ); ?>>
                                <?php echo esc_html( '[Roll ' . $st_item->roll_no . '] ' . $st_item->full_name . ' (' . strtoupper( $st_item->student_id ) . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-input-block">
                    <label><?php esc_html_e( 'Session Year', 'ifsedu-sms' ); ?></label>
                    <input type="text" name="exam_year" value="<?php echo esc_attr( $exam_year ); ?>" required>
                </div>

                <div class="dpt-action-block">
                    <button type="submit" class="dpt-btn dpt-btn-primary">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e( 'Compile Cards', 'ifsedu-sms' ); ?>
                    </button>
                    <?php if ( ! empty( $students ) ) : ?>
                        <button type="button" onclick="window.print();" class="dpt-btn dpt-btn-secondary">
                            <span class="dashicons dashicons-printer"></span>
                            <?php esc_html_e( 'Print All Cards', 'ifsedu-sms' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Compiled Grid Output Area -->
        <?php if ( ! empty( $selected_class ) && $selected_exam_id > 0 ) : ?>
            <div id="educore-printable-admit-area">
                <?php if ( ! empty( $students ) ) : ?>
                    <div class="dpt-admit-cards-container">
                        <?php foreach ( $students as $student ) : 
                            $card_id = 'admit_card_' . $student->id;
                        ?>
                            <div class="admit-card-wrapper" id="<?php echo esc_attr( $card_id ); ?>">
                                
                                <!-- Individual Print Action -->
                                <div class="admit-card-top-tools no-print">
                                    <span style="font-size:11.5px; font-weight:800; color:#0f172a;">
                                        Roll: #<?php echo esc_html( $student->roll_no ); ?> &mdash; <?php echo esc_html( $student->full_name ); ?>
                                    </span>
                                    <button type="button" onclick="educorePrintSingleCard('<?php echo esc_js( $card_id ); ?>');" class="single-print-btn">
                                        <span class="dashicons dashicons-printer" style="font-size:13px; width:13px; height:13px;"></span>
                                        <?php esc_html_e( 'Print This Card', 'ifsedu-sms' ); ?>
                                    </button>
                                </div>

                                <div class="admit-card-box">
                                    <!-- Header -->
                                    <div class="admit-header">
                                        <h3 class="admit-school-title"><?php echo esc_html( $school_name ); ?></h3>
                                        <div class="admit-school-sub"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></div>
                                        <div class="admit-title-badge">
                                            <?php printf( esc_html__( 'ADMIT CARD : %1$s — %2$s', 'ifsedu-sms' ), esc_html( $exam_title ), esc_html( $exam_year ) ); ?>
                                        </div>
                                    </div>

                                    <!-- Body Layout -->
                                    <div class="admit-body-layout">
                                        <div class="admit-details-column">
                                            <table class="admit-table">
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Student ID:', 'ifsedu-sms' ); ?></td>
                                                    <td class="value-col" style="color: #006a4e;"><?php echo esc_html( strtoupper( $student->student_id ) ); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Candidate Name:', 'ifsedu-sms' ); ?></td>
                                                    <td class="value-col" style="text-transform: uppercase;"><?php echo esc_html( $student->full_name ); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Class & Section:', 'ifsedu-sms' ); ?></td>
                                                    <td class="value-col">
                                                        <?php echo esc_html( $student->class_name ); ?>
                                                        <?php echo ! empty( $student->section_name ) ? ' (Sec: ' . esc_html( $student->section_name ) . ')' : ''; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Roll Number:', 'ifsedu-sms' ); ?></td>
                                                    <td class="value-col">
                                                        <span style="background: #0f172a; color:#ffffff; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 800;">
                                                            #<?php echo esc_html( $student->roll_no ); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Guardian Phone:', 'ifsedu-sms' ); ?></td>
                                                    <td class="value-col" style="color: #334155;">
                                                        <?php echo esc_html( ! empty( $student->guardian_phone ) ? $student->guardian_phone : ( ! empty( $student->father_phone ) ? $student->father_phone : 'N/A' ) ); ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>

                                        <div class="admit-photo-column">
                                            <div class="student-photo-frame">
                                                <?php if ( ! empty( $student->photo_url ) ) : ?>
                                                    <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="Student Photo">
                                                <?php else : ?>
                                                    <span>AFFIX<br>PHOTO<br>HERE</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Candidate Instructions -->
                                    <div class="admit-instructions">
                                        <strong><?php esc_html_e( 'Important Instructions:', 'ifsedu-sms' ); ?></strong>
                                        <ol style="margin: 2px 0 0 14px; padding: 0;">
                                            <li><?php esc_html_e( 'Candidates must carry this admit card to the examination hall daily.', 'ifsedu-sms' ); ?></li>
                                            <li><?php esc_html_e( 'Any unauthorized materials or mobile phones are strictly prohibited.', 'ifsedu-sms' ); ?></li>
                                        </ol>
                                    </div>

                                    <!-- Signatures -->
                                    <div class="signature-container">
                                        <div class="signature-line"><?php esc_html_e( 'Controller of Exams', 'ifsedu-sms' ); ?></div>
                                        <div class="signature-line"><?php esc_html_e( 'Principal / Headmaster', 'ifsedu-sms' ); ?></div>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div style="text-align:center; padding:50px; background:#fff; border:1px dashed #cbd5e1; border-radius:12px;" class="no-print">
                        <span class="dashicons dashicons-warning" style="font-size:36px; color:#94a3b8;"></span>
                        <p style="margin:8px 0 0 0; font-weight:700; color:#64748b;"><?php esc_html_e( 'No active student records matched this query.', 'ifsedu-sms' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Client-Side Script -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var nonce = '<?php echo esc_js( wp_create_nonce( "educore_admit_nonce" ) ); ?>';

        // Dynamic Class -> Section & Student Loader
        $('#educore_admit_class_select').on('change', function() {
            var selectedClass   = $(this).val();
            var $sectionSelect = $('#educore_admit_section_select');
            var $studentSelect = $('#educore_admit_student_select');

            $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Sections... --', 'ifsedu-sms' ) ); ?></option>');
            $studentSelect.html('<option value="0"><?php echo esc_js( __( '-- All Students in Section --', 'ifsedu-sms' ) ); ?></option>');

            if (!selectedClass) {
                $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>');
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'educore_get_sections_by_class_admit',
                    security: nonce,
                    class_name: selectedClass
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var options = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>';
                        $.each(response.data, function(i, sec) {
                            options += '<option value="' + sec + '">' + sec + '</option>';
                        });
                        $sectionSelect.html(options);
                    } else {
                        $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>');
                    }
                    reloadStudentsDropdown();
                }
            });
        });

        $('#educore_admit_section_select').on('change', function() {
            reloadStudentsDropdown();
        });

        function reloadStudentsDropdown() {
            var selectedClass   = $('#educore_admit_class_select').val();
            var selectedSection = $('#educore_admit_section_select').val();
            var $studentSelect  = $('#educore_admit_student_select');

            if (!selectedClass) return;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'educore_get_students_by_class_admit',
                    security: nonce,
                    class_name: selectedClass,
                    section_name: selectedSection
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var options = '<option value="0"><?php echo esc_js( __( '-- All Students in Section --', 'ifsedu-sms' ) ); ?></option>';
                        $.each(response.data, function(i, st) {
                            var uid = (st.student_id || '').toUpperCase();
                            options += '<option value="' + st.id + '">[Roll ' + st.roll_no + '] ' + st.full_name + ' (' + uid + ')</option>';
                        });
                        $studentSelect.html(options);
                    } else {
                        $studentSelect.html('<option value="0"><?php echo esc_js( __( '-- All Students in Section --', 'ifsedu-sms' ) ); ?></option>');
                    }
                }
            });
        }
    });

    // Individual Single Card Print Isolation Trigger
    function educorePrintSingleCard(cardId) {
        const targetCard = document.getElementById(cardId);
        if (!targetCard) return;

        document.body.classList.add('printing-single-card');
        targetCard.classList.add('target-single-print');

        window.print();

        document.body.classList.remove('printing-single-card');
        targetCard.classList.remove('target-single-print');
    }
    </script>
    <?php
}