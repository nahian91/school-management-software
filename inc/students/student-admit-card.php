<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

/**
 * High-End Academic Admit Card Engine & Layout Compiler
 * File: student-admit-card-view.php
 * Custom Prefixes Applied: dpt-, afdp-
 * Integration Matrix: Dynamic Exam Name & Separated Class/Section Filtering
 */

// 0. AJAX Handler: Dynamically load Sections based on Class selection
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

function educore_student_admit_card_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_exams    = $wpdb->prefix . 'sms_exams';

    // Fetch Exams list safely
    $exams = $wpdb->get_results( "SELECT id, exam_name FROM {$table_exams} ORDER BY id DESC" );

    // Fetch Unique Classes with Natural Numeric Sorting
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

    // Dynamic Filter Request Capture & Sanitization
    $selected_exam_id  = isset( $_GET['exam_id'] ) ? intval( $_GET['exam_id'] ) : 0;
    $selected_class    = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $selected_section  = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $exam_year         = isset( $_GET['exam_year'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_year'] ) ) : current_time('Y');

    // Pre-populate available sections if class filter is present
    $available_sections = array();
    if ( ! empty( $selected_class ) ) {
        $available_sections = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT section_name FROM {$table_units} WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
            $selected_class
        ) );
    }

    // Execution Core Matrix Data Pool
    $students   = array();
    $exam_title = '';

    // Step 1: Resolve Dynamic Exam Name from DB
    if ( $selected_exam_id > 0 ) {
        $exam_row = $wpdb->get_row( $wpdb->prepare( "SELECT exam_name FROM {$table_exams} WHERE id = %d", $selected_exam_id ) );
        if ( $exam_row ) {
            $exam_title = $exam_row->exam_name;
        }
    }

    // Step 2: Extract Target Student Dataset by Class & Section
    if ( ! empty( $selected_class ) && $selected_exam_id > 0 ) {
        $query = "SELECT * FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $params = array( $selected_class );

        if ( ! empty( $selected_section ) ) {
            $query .= " AND section_name = %s";
            $params[] = $selected_section;
        }

        $query .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $students = $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );
    }
    ?>

    <style>
        /* ==========================================================================
           1. MODERN ADMIT ENGINE CONTAINER & BENTO LAYOUT (SCREEN VIEW)
           ========================================================================== */
        .dpt-admit-engine-root {
            margin: 24px 20px 0 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
        }

        /* Bento-Style Form Card Styling */
        .afdp-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.03), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
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
            letter-spacing: -0.5px;
        }
        .afdp-bento-card h2 .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
            color: #006a4e;
        }

        /* CSS Grid Flex Matrix Engine */
        .dpt-form-grid-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            align-items: flex-end;
        }
        .dpt-input-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .dpt-input-block label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }
        .dpt-input-block select, 
        .dpt-input-block input[type="text"] {
            width: 100%;
            height: 42px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 14px;
            font-size: 14px;
            color: #0f172a;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .dpt-input-block select:focus, 
        .dpt-input-block input[type="text"]:focus {
            border-color: #006a4e;
            background: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
        }

        /* Action Buttons Matrix */
        .dpt-action-block {
            display: flex;
            gap: 12px;
        }
        .dpt-btn {
            height: 42px;
            padding: 0 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .dpt-btn-primary {
            background: #006a4e;
            color: #ffffff;
        }
        .dpt-btn-primary:hover {
            background: #00523c;
            transform: translateY(-1px);
        }
        .dpt-btn-secondary {
            background: #10b981;
            color: #ffffff;
        }
        .dpt-btn-secondary:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        /* ==========================================================================
           2. ADMIT CARD LAYOUT GRID MAPPING
           ========================================================================== */
        .dpt-admit-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(520px, 1fr));
            gap: 28px;
        }
        .admit-card-wrapper {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            position: relative;
            overflow: hidden;
        }
        
        .admit-card-wrapper::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0.03;
            background-image: radial-gradient(#006a4e 1.5px, transparent 1.5px), radial-gradient(#006a4e 1.5px, #ffffff 1.5px);
            background-size: 24px 24px;
            background-position: 0 0, 12px 12px;
        }

        .admit-card-box {
            border: 2px solid #0f172a;
            border-radius: 6px;
            padding: 20px;
            position: relative;
            background: #ffffff;
            z-index: 1;
        }

        .admit-header {
            text-align: center;
            border-bottom: 3px double #006a4e;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .admit-header h3 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 4px 0;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }
        .admit-header p {
            font-size: 12px;
            color: #64748b;
            margin: 0 0 10px 0;
            font-weight: 500;
        }
        .admit-title-badge {
            background: #006a4e;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 6px 16px;
            border-radius: 30px;
            display: inline-block;
            letter-spacing: 0.75px;
            text-transform: uppercase;
        }

        .admit-body-layout {
            display: flex;
            gap: 24px;
            align-items: flex-start;
        }
        .admit-details-column {
            flex: 1;
        }
        .admit-table {
            width: 100%;
            border-collapse: collapse;
        }
        .admit-table tr {
            border-bottom: 1px solid #f1f5f9;
        }
        .admit-table tr:last-child {
            border-bottom: none;
        }
        .admit-table td {
            padding: 8px 4px;
            font-size: 13.5px;
            line-height: 1.4;
            color: #334155;
            border: none !important;
        }
        .admit-table td.label-col {
            font-weight: 700;
            color: #64748b;
            width: 32%;
        }
        .admit-table td.value-col {
            font-weight: 600;
            color: #0f172a;
        }

        .student-photo-frame {
            width: 110px;
            height: 130px;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .student-photo-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .student-photo-frame span {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-align: center;
            letter-spacing: 0.5px;
        }

        .signature-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 36px;
        }
        .signature-line {
            width: 150px;
            border-top: 1px dashed #0f172a;
            padding-top: 6px;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.25px;
        }

        .afdp-empty-state {
            text-align: center;
            padding: 64px 24px;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            background: #ffffff;
        }
        .afdp-empty-state .dashicons {
            font-size: 40px;
            width: 40px;
            height: 40px;
            color: #94a3b8;
            margin-bottom: 12px;
        }
        .afdp-empty-state h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #64748b;
        }

        /* ==========================================================================
           3. HARDWARE PRINT METRICS SYSTEM DIRECTIVES
           ========================================================================== */
        @media print {
            body * {
                visibility: hidden;
            }
            #educore-printable-admit-area, 
            #educore-printable-admit-area * {
                visibility: visible;
            }
            #educore-printable-admit-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
            .dpt-admit-cards-container {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 16px !important;
                width: 100% !important;
            }
            .admit-card-wrapper {
                page-break-inside: avoid;
                break-inside: avoid;
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                background: transparent !important;
            }
            .admit-card-wrapper::before {
                display: none !important;
            }
            .admit-card-box {
                border: 2px solid #000000 !important;
                box-shadow: none !important;
                background: #ffffff !important;
            }
            .admit-title-badge {
                background-color: #006a4e !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .student-photo-frame {
                border: 1px dashed #000000 !important;
                background: #ffffff !important;
            }
        }
    </style>

    <div class="dpt-admit-engine-root">
        
        <!-- Bento Control Panel Form Block -->
        <div class="afdp-bento-card no-print">
            <h2>
                <span class="dashicons dashicons-tickets-alt"></span> Admit Card Bulk Generator
            </h2>
            <form method="GET" action="" class="dpt-form-grid-wrapper">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="students">
                <input type="hidden" name="sub" value="admit_card">

                <!-- Select Examination -->
                <div class="dpt-input-block">
                    <label>Select Examination <span style="color:#ef4444;">*</span></label>
                    <select name="exam_id" required>
                        <option value="">-- Choose Exam Scheme --</option>
                        <?php foreach ( $exams as $ex ) : ?>
                            <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $selected_exam_id, $ex->id ); ?>>
                                <?php echo esc_html( $ex->exam_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Select Class -->
                <div class="dpt-input-block">
                    <label>Select Class <span style="color:#ef4444;">*</span></label>
                    <select name="class_name" id="educore_admit_class_select" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ( $classes as $cls_name ) : ?>
                            <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $selected_class, $cls_name ); ?>>
                                <?php echo esc_html( $cls_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Select Section -->
                <div class="dpt-input-block">
                    <label>Select Section</label>
                    <select name="section_name" id="educore_admit_section_select">
                        <option value="">-- All Sections --</option>
                        <?php foreach ( $available_sections as $sec_name ) : ?>
                            <option value="<?php echo esc_attr( $sec_name ); ?>" <?php selected( $selected_section, $sec_name ); ?>>
                                <?php echo esc_html( $sec_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Academic Session -->
                <div class="dpt-input-block">
                    <label>Academic Session</label>
                    <input type="text" name="exam_year" value="<?php echo esc_attr( $exam_year ); ?>" required>
                </div>

                <div class="dpt-action-block">
                    <button type="submit" class="dpt-btn dpt-btn-primary">
                        <span class="dashicons dashicons-filter"></span> Compile Cards
                    </button>
                    <?php if ( ! empty( $students ) ) : ?>
                        <button type="button" onclick="window.print();" class="dpt-btn dpt-btn-secondary">
                            <span class="dashicons dashicons-printer"></span> Batch Print
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Dynamic AJAX Class-to-Section Loader Script -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( wp_create_nonce( "educore_admit_nonce" ) ); ?>';

            $('#educore_admit_class_select').on('change', function() {
                var selectedClass   = $(this).val();
                var $sectionSelect = $('#educore_admit_section_select');

                $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Sections... --', 'ifsedu-sms' ) ); ?></option>');

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
                    },
                    error: function() {
                        $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>');
                    }
                });
            });
        });
        </script>

        <!-- Compiled Grid Render Target Output Area -->
        <?php if ( ! empty( $selected_class ) && $selected_exam_id > 0 ) : ?>
            <div id="educore-printable-admit-area">
                <?php if ( ! empty( $students ) ) : ?>
                    <div class="dpt-admit-cards-container">
                        <?php foreach ( $students as $student ) : ?>
                            <div class="admit-card-wrapper">
                                <div class="admit-card-box">
                                    
                                    <!-- Header Module Component -->
                                    <div class="admit-header">
                                        <h3><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h3>
                                        <p><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
                                        <div class="admit-title-badge">
                                            ADMIT CARD : <?php echo esc_html( $exam_title ); ?> &mdash; <?php echo esc_html( $exam_year ); ?>
                                        </div>
                                    </div>

                                    <!-- Split Row Content Structure Matrix -->
                                    <div class="admit-body-layout">
                                        
                                        <!-- Student Informational Data Grid -->
                                        <div class="admit-details-column">
                                            <table class="admit-table">
                                                <tr>
                                                    <td class="label-col">Student ID:</td>
                                                    <td class="value-col"><code><?php echo esc_html( $student->student_id ); ?></code></td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col">Name:</td>
                                                    <td class="value-col" style="text-transform: uppercase;"><?php echo esc_html( $student->full_name ); ?></td>
                                                </tr>
                                                <?php if ( ! empty( $student->name_bn ) ) : ?>
                                                <tr>
                                                    <td class="label-col">নাম (বাংলা):</td>
                                                    <td class="value-col"><?php echo esc_html( $student->name_bn ); ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <tr>
                                                    <td class="label-col">Class & Sec:</td>
                                                    <td class="value-col">
                                                        <?php echo esc_html( $student->class_name ); ?>
                                                        <?php echo ! empty( $student->section_name ) ? ' &mdash; Sec: ' . esc_html( $student->section_name ) : ''; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col">Roll No:</td>
                                                    <td class="value-col">
                                                        <span style="background: #0f172a; color:#ffffff; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 800; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                                                            #<?php echo esc_html( $student->roll_no ); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col">Guardian:</td>
                                                    <td class="value-col" style="color: #475569; font-size: 13px;">
                                                        <?php echo esc_html( $student->guardian_name ? $student->guardian_name : $student->father_name ); ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>

                                        <!-- Passport Frame Render Engine Block -->
                                        <div class="admit-photo-column">
                                            <div class="student-photo-frame">
                                                <?php if ( ! empty( $student->photo_url ) ) : ?>
                                                    <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="Passport Student Image">
                                                <?php else : ?>
                                                    <span>AFFIX<br>PHOTO<br>HERE</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                    </div>

                                    <!-- Authorized Signatures Footer -->
                                    <div class="signature-container">
                                        <div class="signature-line">Controller of Exams</div>
                                        <div class="signature-line">Headmaster / Principal</div>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="afdp-empty-state no-print">
                        <span class="dashicons dashicons-warning"></span>
                        <h5>No active student records matched this target class/section configuration.</h5>
                    </div>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="afdp-empty-state no-print" style="border-style: dashed; background: transparent;">
                <span class="dashicons dashicons-info"></span>
                <h5>Select both Examination and Class parameters above to compile admit cards.</h5>
            </div>
        <?php endif; ?>
    </div>
    <?php
}