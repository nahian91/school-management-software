<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

/**
 * Enterprise Academic ID Card Engine & Precision Print Compiler
 * File: student-id-card-view.php
 */
function educore_student_id_card_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access this page.', 'ifsedu-sms' ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Fetch all academic units
    $raw_academic_units = $wpdb->get_results( "SELECT class_name, section_name FROM {$table_units} WHERE class_name IS NOT NULL AND class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" );

    // Group sections under their respective classes
    $class_sections_map = array();
    if ( ! empty( $raw_academic_units ) ) {
        foreach ( $raw_academic_units as $unit ) {
            $c_name = trim( $unit->class_name );
            $s_name = trim( $unit->section_name );

            if ( ! isset( $class_sections_map[ $c_name ] ) ) {
                $class_sections_map[ $c_name ] = array();
            }

            if ( ! empty( $s_name ) && ! in_array( $s_name, $class_sections_map[ $c_name ], true ) ) {
                $class_sections_map[ $c_name ][] = $s_name;
            }
        }
    }

    uksort( $class_sections_map, 'strnatcasecmp' );

    // Filter values
    $selected_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $selected_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $selected_student = isset( $_GET['student_id'] ) ? absint( $_GET['student_id'] ) : 0;
    $code_type        = isset( $_GET['code_type'] ) ? sanitize_text_field( wp_unslash( $_GET['code_type'] ) ) : 'barcode';

    $students           = array();
    $available_students = array();

    if ( ! empty( $selected_class ) ) {
        // Fetch Students for Dropdown
        $st_sql = "SELECT id, full_name, student_id, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $st_params = array( $selected_class );
        if ( ! empty( $selected_section ) ) {
            $st_sql .= " AND section_name = %s";
            $st_params[] = $selected_section;
        }
        $st_sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $available_students = $wpdb->get_results( $wpdb->prepare( $st_sql, ...$st_params ) );

        // Fetch Main Cards Query
        $where_clauses = array( "status = 'Active'", "class_name = %s" );
        $query_params  = array( $selected_class );

        if ( ! empty( $selected_section ) ) {
            $where_clauses[] = "section_name = %s";
            $query_params[]  = $selected_section;
        }

        if ( $selected_student > 0 ) {
            $where_clauses[] = "id = %d";
            $query_params[]  = $selected_student;
        }

        $where_sql = implode( ' AND ', $where_clauses );
        $query     = "SELECT * FROM {$table_students} WHERE {$where_sql} ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";

        $students = $wpdb->get_results( $wpdb->prepare( $query, ...$query_params ) );
    }
    ?>

    <!-- External Barcode & QR Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    <style>
        .dpt-id-engine-root {
            margin: 20px 20px 0 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
        }

        .afdp-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            margin-bottom: 28px;
        }

        .afdp-bento-card h4 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 20px 0;
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
            gap: 6px;
        }

        .dpt-input-block label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
        }

        .dpt-input-block select {
            width: 100%;
            height: 40px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 0 12px;
            font-size: 13.5px;
            color: #0f172a;
            transition: all 0.2s ease;
        }

        .dpt-input-block select:focus {
            border-color: #006a4e;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
        }

        .dpt-action-block { display: flex; gap: 10px; }

        .dpt-btn {
            height: 40px;
            padding: 0 16px;
            border-radius: 6px;
            font-size: 13.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .dpt-btn-primary { background: #006a4e; color: #ffffff; }
        .dpt-btn-primary:hover { background: #00523c; }

        .dpt-btn-secondary { background: #0284c7; color: #ffffff; }
        .dpt-btn-secondary:hover { background: #0369a1; }

        /* Card Layout Matrix */
        .dpt-id-cards-container {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            justify-content: flex-start;
        }

        .id-card-wrapper {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center;
        }

        .id-card-single-action {
            width: 100%;
            display: flex;
            justify-content: flex-end;
        }

        .btn-single-print {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
        }

        .btn-single-print:hover {
            background: #006a4e;
            color: #ffffff;
            border-color: #006a4e;
        }

        /* ISO CR80 Dimensions: 3.375in x 2.125in (~85.6mm x 53.98mm) */
        .id-card-box {
            width: 325px;
            height: 204px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
            display: flex;
            flex-direction: column;
        }

        .id-card-header {
            background: linear-gradient(135deg, #006a4e 0%, #004d38 100%);
            color: #ffffff;
            padding: 5px 8px;
            text-align: center;
            border-bottom: 2px solid #f59e0b;
            flex-shrink: 0;
        }

        .id-card-header h6 {
            margin: 0;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.1;
        }

        .id-card-header small {
            font-size: 0.52rem;
            letter-spacing: 0.8px;
            opacity: 0.92;
            display: block;
            margin-top: 1px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .id-card-body {
            padding: 6px 8px 0 8px;
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }

        .id-photo-frame {
            width: 58px;
            height: 68px;
            border: 1px solid #94a3b8;
            border-radius: 4px;
            overflow: hidden;
            background: #f1f5f9;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .id-photo-frame img { width: 100%; height: 100%; object-fit: cover; }

        .id-card-table {
            font-size: 0.62rem;
            width: 100%;
            border-collapse: collapse;
            line-height: 1.2;
        }

        .id-card-table td { padding: 0.5px 0; vertical-align: top; border: none !important; }
        .id-card-table td.lbl { color: #475569; font-weight: 600; width: 30%; }
        .id-card-table td.val { color: #0f172a; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .id-code-area {
            margin: auto 6px 20px 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fafafa;
            border: 1px dashed #cbd5e1;
            padding: 1px 4px;
            border-radius: 4px;
            height: 26px;
            box-sizing: border-box;
        }

        .id-barcode-svg { max-height: 22px; width: auto; max-width: 100%; display: block; margin: 0 auto; }

        .id-qrcode-box {
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .id-qrcode-box img { width: 22px !important; height: 22px !important; }

        .id-card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 18px;
            background: #f8fafc;
            padding: 2px 8px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.54rem;
            box-sizing: border-box;
        }

        .id-card-footer span.blood-badge {
            font-weight: 800;
            color: #dc2626;
            background: #fee2e2;
            padding: 0px 4px;
            border-radius: 2px;
        }

        .id-card-footer span.sig-title {
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
        }

        /* Print Media Overrides */
        @media print {
            @page { size: A4 portrait; margin: 8mm 6mm 8mm 6mm; }

            html, body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            #wpadminbar, #adminmenumain, #wpfooter, .no-print, .id-card-single-action, .afdp-bento-card {
                display: none !important;
            }

            #educore-printable-id-area {
                width: 100% !important;
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
            }

            .dpt-id-cards-container {
                display: grid !important;
                grid-template-columns: repeat(2, 85.6mm) !important;
                gap: 5mm 6mm !important;
                justify-content: center !important;
            }

            body.single-print-active .id-card-wrapper { display: none !important; }
            body.single-print-active .id-card-wrapper.target-single-print { display: flex !important; justify-content: center !important; }
            body.single-print-active .dpt-id-cards-container { display: flex !important; justify-content: center !important; }

            .id-card-box {
                width: 85.6mm !important;
                height: 53.98mm !important;
                border: 1px solid #000000 !important;
                box-shadow: none !important;
                border-radius: 3mm !important;
                page-break-inside: avoid !important;
                background: #ffffff !important;
            }

            .id-card-header {
                background: #006a4e !important;
                color: #ffffff !important;
            }
        }
    </style>

    <div class="dpt-id-engine-root">
        
        <!-- Filter Form Controls -->
        <div class="afdp-bento-card no-print">
            <h4>
                <span class="dashicons dashicons-id-alt" style="color:#006a4e;"></span>
                <?php esc_html_e( 'Student ID Card Generator', 'ifsedu-sms' ); ?>
            </h4>

            <form method="GET" action="" class="dpt-form-grid-wrapper">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="students">
                <input type="hidden" name="sub" value="id_card">

                <!-- Class -->
                <div class="dpt-input-block">
                    <label><?php esc_html_e( 'Select Class', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="class_name" id="dpt_class_select" required>
                        <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( array_keys( $class_sections_map ) as $c_name ) : ?>
                            <option value="<?php echo esc_attr( $c_name ); ?>" <?php selected( $selected_class, $c_name ); ?>>
                                <?php printf( esc_html__( 'Class %s', 'ifsedu-sms' ), esc_html( $c_name ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Section -->
                <div class="dpt-input-block">
                    <label><?php esc_html_e( 'Select Section', 'ifsedu-sms' ); ?></label>
                    <select name="section_name" id="dpt_section_select">
                        <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>

                <!-- Single Student Selector -->
                <div class="dpt-input-block">
                    <label><?php esc_html_e( 'Single Student (Optional)', 'ifsedu-sms' ); ?></label>
                    <select name="student_id" id="dpt_student_select">
                        <option value="0"><?php esc_html_e( '-- All Students --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $available_students as $st_item ) : ?>
                            <option value="<?php echo intval( $st_item->id ); ?>" <?php selected( $selected_student, $st_item->id ); ?>>
                                <?php echo esc_html( 'Roll ' . $st_item->roll_no . ': ' . $st_item->full_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Verification Code Type -->
                <div class="dpt-input-block">
                    <label><?php esc_html_e( 'Code Type', 'ifsedu-sms' ); ?></label>
                    <select name="code_type">
                        <option value="barcode" <?php selected( $code_type, 'barcode' ); ?>><?php esc_html_e( 'Barcode Only (Code128)', 'ifsedu-sms' ); ?></option>
                        <option value="qrcode" <?php selected( $code_type, 'qrcode' ); ?>><?php esc_html_e( 'QR Code Only (Profile URL)', 'ifsedu-sms' ); ?></option>
                        <option value="both" <?php selected( $code_type, 'both' ); ?>><?php esc_html_e( 'Both (Barcode + QR)', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>

                <!-- Actions -->
                <div class="dpt-action-block">
                    <button type="submit" class="dpt-btn dpt-btn-primary">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e( 'Fetch Cards', 'ifsedu-sms' ); ?>
                    </button>
                    <?php if ( ! empty( $students ) ) : ?>
                        <button type="button" onclick="educorePrintAllCards();" class="dpt-btn dpt-btn-secondary">
                            <span class="dashicons dashicons-printer"></span>
                            <?php esc_html_e( 'Print All Cards', 'ifsedu-sms' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Output Cards Grid -->
        <?php if ( ! empty( $selected_class ) ) : ?>
            <div id="educore-printable-id-area">
                <?php if ( ! empty( $students ) ) : ?>
                    <div class="dpt-id-cards-container">
                        <?php foreach ( $students as $student ) : 
                            $profile_url = site_url( '/student-verify/?uid=' . esc_attr( $student->student_id ) );
                            $wrapper_id  = 'student-card-' . esc_attr( $student->student_id );
                        ?>
                            <div class="id-card-wrapper" id="<?php echo esc_attr( $wrapper_id ); ?>">
                                
                                <div class="id-card-single-action no-print">
                                    <button type="button" class="btn-single-print" onclick="educorePrintSingleCard('<?php echo esc_js( $wrapper_id ); ?>');">
                                        <span class="dashicons dashicons-printer" style="font-size:13px; width:13px; height:13px;"></span>
                                        <?php esc_html_e( 'Print Single', 'ifsedu-sms' ); ?>
                                    </button>
                                </div>

                                <div class="id-card-box">
                                    <div class="id-card-header">
                                        <h6><?php echo esc_html( get_bloginfo('name') ); ?></h6>
                                        <small><?php esc_html_e( 'Student Identity Card', 'ifsedu-sms' ); ?></small>
                                    </div>
                                    
                                    <div class="id-card-body">
                                        <div class="id-photo-frame">
                                            <?php if ( ! empty( $student->photo_url ) ) : ?>
                                                <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="Student Photo">
                                            <?php else : ?>
                                                <div style="font-size:0.55rem; color:#94a3b8; text-align:center; font-weight:700;">NO PHOTO</div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <table class="id-card-table">
                                            <tr>
                                                <td class="lbl"><?php esc_html_e( 'ID No:', 'ifsedu-sms' ); ?></td>
                                                <td class="val"><?php echo esc_html( $student->student_id ); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="lbl"><?php esc_html_e( 'Name:', 'ifsedu-sms' ); ?></td>
                                                <td class="val" style="text-transform: uppercase;"><?php echo esc_html( $student->full_name ); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="lbl"><?php esc_html_e( 'Class:', 'ifsedu-sms' ); ?></td>
                                                <td class="val"><?php echo esc_html( $student->class_name ); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="lbl"><?php esc_html_e( 'Section:', 'ifsedu-sms' ); ?></td>
                                                <td class="val"><?php echo esc_html( $student->section_name ? $student->section_name : 'N/A' ); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="lbl"><?php esc_html_e( 'Roll No:', 'ifsedu-sms' ); ?></td>
                                                <td class="val"><?php echo esc_html( $student->roll_no ); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="lbl"><?php esc_html_e( 'Phone:', 'ifsedu-sms' ); ?></td>
                                                <td class="val"><?php echo esc_html( $student->guardian_phone ); ?></td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div class="id-code-area">
                                        <?php if ( $code_type === 'barcode' || $code_type === 'both' ) : ?>
                                            <div style="flex: 1; text-align: center;">
                                                <svg class="id-barcode-svg" data-barcode="<?php echo esc_attr( $student->student_id ); ?>"></svg>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ( $code_type === 'qrcode' || $code_type === 'both' ) : ?>
                                            <div class="id-qrcode-box" data-qrcode="<?php echo esc_url( $profile_url ); ?>"></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="id-card-footer">
                                        <span>Blood: <span class="blood-badge"><?php echo esc_html( $student->blood_group ? $student->blood_group : 'N/A' ); ?></span></span>
                                        <span class="sig-title"><?php esc_html_e( 'Authorized Signature', 'ifsedu-sms' ); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div style="text-align:center; padding:50px; background:#fff; border:1px dashed #cbd5e1; border-radius:12px;" class="no-print">
                        <span class="dashicons dashicons-warning" style="font-size:36px; color:#94a3b8;"></span>
                        <p style="margin:8px 0 0 0; font-weight:700; color:#64748b;"><?php esc_html_e( 'No active student records found.', 'ifsedu-sms' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Client Scripts Engine -->
    <script type="text/javascript">
    function educorePrintAllCards() {
        document.body.classList.remove('single-print-active');
        document.querySelectorAll('.target-single-print').forEach(el => el.classList.remove('target-single-print'));
        window.print();
    }

    function educorePrintSingleCard(cardWrapperId) {
        document.body.classList.add('single-print-active');
        document.querySelectorAll('.id-card-wrapper').forEach(el => el.classList.remove('target-single-print'));

        const targetWrapper = document.getElementById(cardWrapperId);
        if (targetWrapper) {
            targetWrapper.classList.add('target-single-print');
            window.print();
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        const classSectionsMap = <?php echo wp_json_encode( $class_sections_map ); ?>;
        const classSelect      = document.getElementById('dpt_class_select');
        const sectionSelect    = document.getElementById('dpt_section_select');
        const selectedSection  = <?php echo wp_json_encode( $selected_section ); ?>;

        function updateSections() {
            const selectedClass = classSelect.value;
            sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>';

            if (selectedClass && classSectionsMap[selectedClass]) {
                classSectionsMap[selectedClass].forEach(function(sec) {
                    const option = document.createElement('option');
                    option.value = sec;
                    option.textContent = sec;
                    if (sec === selectedSection) option.selected = true;
                    sectionSelect.appendChild(option);
                });
            }
        }

        if (classSelect) {
            classSelect.addEventListener('change', updateSections);
            updateSections();
        }

        // Render Barcodes
        document.querySelectorAll('.id-barcode-svg').forEach(function(el) {
            const val = el.getAttribute('data-barcode');
            if (val && typeof JsBarcode === 'function') {
                JsBarcode(el, val, {
                    format: "CODE128",
                    lineColor: "#0f172a",
                    width: 1.0,
                    height: 20,
                    displayValue: false,
                    margin: 0
                });
            }
        });

        // Render QR Codes
        document.querySelectorAll('.id-qrcode-box').forEach(function(el) {
            const url = el.getAttribute('data-qrcode');
            if (url && typeof QRCode === 'function') {
                new QRCode(el, {
                    text: url,
                    width: 22,
                    height: 22,
                    colorDark: "#0f172a",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.L
                });
            }
        });
    });
    </script>
    <?php
}