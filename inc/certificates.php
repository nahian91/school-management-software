<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * High-End Academic Certificate & Testimonial Generator Engine
 * File: certificate-tab.php
 * Custom Prefixes Applied: dpt-, afdp-
 * Architecture: Neo-Bento Control Panel with A4 Landscape Print Compiler & Watermark
 */

// 1. AJAX Handler: Fetch Sections by Class
add_action( 'wp_ajax_educore_get_sections_by_class_cert', 'educore_get_sections_by_class_cert_handler' );
function educore_get_sections_by_class_cert_handler() {
    check_ajax_referer( 'educore_cert_nonce', 'security' );

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

// 2. AJAX Handler: Fetch Active Students by Class & Section
add_action( 'wp_ajax_educore_get_students_by_class_cert', 'educore_get_students_by_class_cert_handler' );
function educore_get_students_by_class_cert_handler() {
    check_ajax_referer( 'educore_cert_nonce', 'security' );

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

    $sql = "SELECT id, full_name, student_id, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
    $params = array( $class_name );

    if ( ! empty( $section_name ) ) {
        $sql .= " AND section_name = %s";
        $params[] = $section_name;
    }

    $sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
    $students = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

    $data = array();
    if ( ! empty( $students ) ) {
        foreach ( $students as $s ) {
            $data[] = array(
                'id'         => intval( $s->id ),
                'full_name'  => esc_html( $s->full_name ),
                'student_id' => esc_html( $s->student_id ),
                'roll_no'    => esc_html( $s->roll_no ),
            );
        }
    }

    wp_send_json_success( $data );
}

function educore_certificate_tab() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to generate certificates.', 'ifsedu-sms' ) );
    }

    // Dynamic Base URL
    $current_uri = remove_query_arg( array( 'status' ), $_SERVER['REQUEST_URI'] );
    $base_url    = esc_url_raw( $current_uri );

    // Fetch Unique Classes for Dropdown with Natural Numeric Sorting
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

    // Capture GET Filter Inputs
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $filter_student = isset( $_GET['student_id'] ) ? intval( $_GET['student_id'] ) : 0;
    $cert_type      = isset( $_GET['cert_type'] ) ? sanitize_text_field( wp_unslash( $_GET['cert_type'] ) ) : 'testimonial';
    $issue_date     = isset( $_GET['issue_date'] ) ? sanitize_text_field( wp_unslash( $_GET['issue_date'] ) ) : current_time('Y-m-d');

    // Pre-populate sections and students if filter is set
    $available_sections = array();
    if ( ! empty( $filter_class ) ) {
        $available_sections = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT section_name FROM {$table_units} WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
            $filter_class
        ) );
    }

    $student = null;
    if ( $filter_student > 0 ) {
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_students} WHERE id = %d", $filter_student ) );
    }
    ?>

    <style>
        /* ==========================================================================
           1. CORE BENTO UI SYSTEM & SCREEN STYLING
           ========================================================================== */
        .dpt-cert-root {
            margin: 20px 20px 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
        }

        .afdp-header-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .afdp-header-block h2 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.5px;
        }
        .afdp-header-block h2 .dashicons {
            font-size: 26px;
            width: 26px;
            height: 26px;
            color: #006a4e;
        }

        .dpt-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }

        .dpt-filter-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 14px;
            align-items: end;
        }
        .dpt-col-3 { grid-column: span 3; }
        .dpt-col-2 { grid-column: span 2; }

        @media (max-width: 992px) {
            .dpt-col-3, .dpt-col-2 { grid-column: span 12; }
        }

        .dpt-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .dpt-form-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            margin: 0;
        }
        .dpt-input-field, .dpt-select-field {
            height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            width: 100%;
            box-shadow: none;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .dpt-input-field:focus, .dpt-select-field:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
            outline: none;
        }

        .dpt-btn-submit-trigger {
            height: 40px;
            background: #006a4e;
            border: 1px solid transparent;
            color: #ffffff;
            font-weight: 700;
            font-size: 13.5px;
            border-radius: 8px;
            padding: 0 20px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.15);
            width: 100%;
        }
        .dpt-btn-submit-trigger:hover {
            background: #00523c;
            color: #ffffff;
        }

        /* ==========================================================================
           2. CERTIFICATE ORNATE DESIGN LAYOUT (PRINT READY WITH WATERMARK)
           ========================================================================== */
        .afdp-certificate-container {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px;
            border: 12px double #006a4e;
            border-radius: 4px;
            position: relative;
            color: #0f172a;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        /* Institutional Center Watermark */
        .cert-watermark-bg {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            font-size: 64px;
            font-weight: 900;
            color: #006a4e;
            opacity: 0.06;
            text-transform: uppercase;
            letter-spacing: 4px;
            white-space: nowrap;
            pointer-events: none;
            user-select: none;
            z-index: 1;
            text-align: center;
            width: 100%;
        }

        .cert-corner-decoration {
            position: absolute;
            width: 50px;
            height: 50px;
            border: 3px solid #006a4e;
            z-index: 2;
        }
        .cert-corner-tl { top: 10px; left: 10px; border-right: none; border-bottom: none; }
        .cert-corner-tr { top: 10px; right: 10px; border-left: none; border-bottom: none; }
        .cert-corner-bl { bottom: 10px; left: 10px; border-right: none; border-top: none; }
        .cert-corner-br { bottom: 10px; right: 10px; border-left: none; border-top: none; }

        .cert-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            position: relative;
            z-index: 2;
        }
        .cert-institution-name {
            font-size: 28px;
            font-weight: 900;
            color: #006a4e;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 0 6px 0;
        }
        .cert-institution-sub {
            font-size: 13px;
            color: #64748b;
            margin: 0 0 16px 0;
            font-weight: 600;
        }
        .cert-title-badge {
            display: inline-block;
            background: linear-gradient(135deg, #006a4e 0%, #004d38 100%);
            color: #ffffff;
            font-size: 16px;
            font-weight: 800;
            padding: 8px 28px;
            border-radius: 30px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            box-shadow: 0 4px 10px rgba(0, 106, 78, 0.2);
        }

        .cert-body {
            font-size: 16px;
            line-height: 2.2;
            text-align: justify;
            margin: 30px 10px;
            color: #1e293b;
            position: relative;
            z-index: 2;
        }
        .cert-fill-line {
            font-weight: 800;
            color: #0f172a;
            border-bottom: 2px dotted #006a4e;
            padding: 0 8px;
            display: inline-block;
        }

        .cert-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 60px;
            padding-top: 20px;
            position: relative;
            z-index: 2;
        }
        .cert-seal-box {
            width: 90px;
            height: 90px;
            border: 2px dashed #006a4e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #006a4e;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            text-align: center;
        }
        .cert-sign-line {
            border-top: 1.5px solid #0f172a;
            width: 180px;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            padding-top: 6px;
        }

        .afdp-fallback-card {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
        }
        .afdp-fallback-card .dashicons {
            font-size: 36px;
            width: 36px;
            height: 36px;
            color: #94a3b8;
            margin-bottom: 10px;
        }
        .afdp-fallback-card p {
            margin: 0;
            font-size: 14px;
            color: #64748b;
            font-weight: 600;
        }

        @media print {
            body * { visibility: hidden; }
            .afdp-certificate-container, .afdp-certificate-container * {
                visibility: visible;
            }
            .afdp-certificate-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none !important;
                border: 10px double #006a4e !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .cert-watermark-bg {
                opacity: 0.08 !important;
            }
            .no-print { display: none !important; }
        }
    </style>

    <div class="dpt-cert-root">

        <!-- Top Header Block -->
        <div class="afdp-header-block no-print">
            <h2>
                <span class="dashicons dashicons-welcome-learn-more"></span>
                <?php esc_html_e( 'Academic Certificate & Testimonial Generator', 'ifsedu-sms' ); ?>
            </h2>
        </div>

        <!-- Filter Console Bento Card -->
        <div class="dpt-bento-card no-print">
            <form method="GET" action="<?php echo esc_url( $base_url ); ?>">
                <?php 
                $parsed_url = wp_parse_url( $base_url );
                if ( isset( $parsed_url['query'] ) ) {
                    parse_str( $parsed_url['query'], $query_params );
                    foreach ( $query_params as $param_key => $param_val ) {
                        if ( ! in_array( $param_key, array( 'class_name', 'section_name', 'student_id', 'cert_type', 'issue_date' ) ) ) {
                            echo '<input type="hidden" name="' . esc_attr( $param_key ) . '" value="' . esc_attr( $param_val ) . '">';
                        }
                    }
                }
                ?>
                
                <div class="dpt-filter-grid">
                    <!-- Certificate Type -->
                    <div class="dpt-form-group dpt-col-3">
                        <label class="dpt-form-label"><?php esc_html_e( 'Certificate Type', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="cert_type" class="dpt-select-field" required>
                            <option value="testimonial" <?php selected( $cert_type, 'testimonial' ); ?>><?php esc_html_e( 'Testimonial / Character Cert.', 'ifsedu-sms' ); ?></option>
                            <option value="transfer" <?php selected( $cert_type, 'transfer' ); ?>><?php esc_html_e( 'Transfer Certificate (TC)', 'ifsedu-sms' ); ?></option>
                            <option value="completion" <?php selected( $cert_type, 'completion' ); ?>><?php esc_html_e( 'Course Completion Cert.', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- Class Selector -->
                    <div class="dpt-form-group dpt-col-2">
                        <label class="dpt-form-label"><?php esc_html_e( 'Select Class', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="educore_cert_class_select" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Class --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $classes as $cls_name ) : ?>
                                <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                                    <?php echo esc_html( $cls_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Section Selector -->
                    <div class="dpt-form-group dpt-col-2">
                        <label class="dpt-form-label"><?php esc_html_e( 'Select Section', 'ifsedu-sms' ); ?></label>
                        <select name="section_name" id="educore_cert_section_select" class="dpt-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $available_sections as $sec_val ) : ?>
                                <option value="<?php echo esc_attr( $sec_val ); ?>" <?php selected( $filter_section, $sec_val ); ?>>
                                    <?php echo esc_html( $sec_val ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Student Selector -->
                    <div class="dpt-form-group dpt-col-3">
                        <label class="dpt-form-label"><?php esc_html_e( 'Select Student', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="student_id" id="educore_cert_student_select" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Student --', 'ifsedu-sms' ); ?></option>
                            <?php 
                            if ( ! empty( $filter_class ) ) {
                                $sql = "SELECT id, full_name, student_id, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
                                $params = array( $filter_class );

                                if ( ! empty( $filter_section ) ) {
                                    $sql .= " AND section_name = %s";
                                    $params[] = $filter_section;
                                }

                                $sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
                                $student_list = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

                                foreach ( $student_list as $s ) : ?>
                                    <option value="<?php echo intval( $s->id ); ?>" <?php selected( $filter_student, $s->id ); ?>>
                                        <?php printf( esc_html__( 'Roll %1$s: %2$s (%3$s)', 'ifsedu-sms' ), esc_html( $s->roll_no ), esc_html( $s->full_name ), esc_html( $s->student_id ) ); ?>
                                    </option>
                                <?php endforeach;
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Issue Date & Submit -->
                    <div class="dpt-col-2">
                        <input type="hidden" name="issue_date" value="<?php echo esc_attr( $issue_date ); ?>">
                        <button type="submit" class="dpt-btn-submit-trigger">
                            <?php esc_html_e( 'Generate', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Certificate Output Render Canvas -->
        <?php if ( $student ) : 
            $school_name = get_bloginfo( 'name' );
            $school_desc = get_bloginfo( 'description' );
            $guardian    = ! empty( $student->guardian_name ) ? $student->guardian_name : ( ! empty( $student->father_name ) ? $student->father_name : 'N/A' );
            $mother      = ! empty( $student->mother_name ) ? $student->mother_name : 'N/A';
            $dob         = ( ! empty( $student->dob ) && $student->dob !== '0000-00-00' ) ? date_i18n( 'd F Y', strtotime( $student->dob ) ) : 'N/A';
            $formatted_issue = date_i18n( 'd F Y', strtotime( $issue_date ) );
        ?>
            <div style="text-align: center; margin-bottom: 24px;" class="no-print">
                <button onclick="window.print();" class="dpt-btn-submit-trigger" style="width: auto; padding: 0 32px; height: 44px;">
                    <span class="dashicons dashicons-printer" style="vertical-align:middle;"></span>
                    <?php esc_html_e( 'Print Official Certificate', 'ifsedu-sms' ); ?>
                </button>
            </div>

            <div class="afdp-certificate-container">
                <!-- Background Institutional Watermark -->
                <div class="cert-watermark-bg">
                    <?php echo esc_html( $school_name ); ?>
                </div>

                <!-- Ornate Corner Vectors -->
                <div class="cert-corner-decoration cert-corner-tl"></div>
                <div class="cert-corner-decoration cert-corner-tr"></div>
                <div class="cert-corner-decoration cert-corner-bl"></div>
                <div class="cert-corner-decoration cert-corner-br"></div>

                <!-- Header Block -->
                <div class="cert-header">
                    <h1 class="cert-institution-name"><?php echo esc_html( $school_name ); ?></h1>
                    <div class="cert-institution-sub"><?php echo esc_html( $school_desc ); ?></div>
                    <div class="cert-title-badge">
                        <?php 
                        if ( 'transfer' === $cert_type ) {
                            esc_html_e( 'Transfer Certificate (TC)', 'ifsedu-sms' );
                        } elseif ( 'completion' === $cert_type ) {
                            esc_html_e( 'Certificate of Completion', 'ifsedu-sms' );
                        } else {
                            esc_html_e( 'Academic Testimonial', 'ifsedu-sms' );
                        }
                        ?>
                    </div>
                </div>

                <!-- Dynamic Narrative Body -->
                <div class="cert-body">
                    This is to certify that <span class="cert-fill-line"><?php echo esc_html( $student->full_name ); ?></span>, 
                    Son/Daughter of <span class="cert-fill-line"><?php echo esc_html( $guardian ); ?></span> and 
                    <span class="cert-fill-line"><?php echo esc_html( $mother ); ?></span>, 
                    bearing Student ID <span class="cert-fill-line"><?php echo esc_html( $student->student_id ); ?></span> 
                    and Roll No. <span class="cert-fill-line">#<?php echo esc_html( $student->roll_no ); ?></span>, 
                    was a regular student of Class <span class="cert-fill-line"><?php echo esc_html( $student->class_name ); ?></span>
                    <?php if ( ! empty( $student->section_name ) ) : ?>
                        (Section: <span class="cert-fill-line"><?php echo esc_html( $student->section_name ); ?></span>)
                    <?php endif; ?> 
                    at our institution.

                    <br><br>
                    To the best of my knowledge and belief, he/she bears a good moral character and maintained commendable conduct throughout the academic term. Date of birth as per institutional registry is <span class="cert-fill-line"><?php echo esc_html( $dob ); ?></span>.

                    <?php if ( 'transfer' === $cert_type ) : ?>
                        <br><br>
                        All institutional dues have been cleared. He/She is permitted to leave the institution for higher academic pursuits.
                    <?php endif; ?>

                    <br><br>
                    I wish him/her every success in all future academic and professional endeavors.
                </div>

                <!-- Footer Signatures & Seal -->
                <div class="cert-footer">
                    <div>
                        <div style="font-size: 12px; font-weight: 700; color: #64748b;">Issue Date:</div>
                        <div style="font-size: 14px; font-weight: 800; color: #0f172a;"><?php echo esc_html( $formatted_issue ); ?></div>
                    </div>

                    <div class="cert-seal-box">
                        OFFICIAL<br>INSTITUTIONAL<br>SEAL
                    </div>

                    <div class="cert-sign-line">
                        Headmaster / Principal
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div class="afdp-fallback-card no-print">
                <span class="dashicons dashicons-info"></span>
                <p><?php esc_html_e( 'Select a target Class and Student above to compile and render the academic certificate.', 'ifsedu-sms' ); ?></p>
            </div>
        <?php endif; ?>

    </div>

    <!-- Script: AJAX Cascade Selectors -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var nonce = '<?php echo esc_js( wp_create_nonce( "educore_cert_nonce" ) ); ?>';

        // 1. Fetch Sections when Class changes
        $('#educore_cert_class_select').on('change', function() {
            var selectedClass   = $(this).val();
            var $sectionSelect = $('#educore_cert_section_select');

            $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>');

            if (!selectedClass) {
                reloadCertStudents();
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'educore_get_sections_by_class_cert',
                    security: nonce,
                    class_name: selectedClass
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var secOptions = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>';
                        $.each(response.data, function(i, sec) {
                            secOptions += '<option value="' + sec + '">' + sec + '</option>';
                        });
                        $sectionSelect.html(secOptions);
                    }
                    reloadCertStudents();
                }
            });
        });

        // 2. Reload Students when Section changes
        $('#educore_cert_section_select').on('change', function() {
            reloadCertStudents();
        });

        function reloadCertStudents() {
            var selectedClass   = $('#educore_cert_class_select').val();
            var selectedSection = $('#educore_cert_section_select').val();
            var $studentSelect  = $('#educore_cert_student_select');

            $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Students... --', 'ifsedu-sms' ) ); ?></option>');

            if (!selectedClass) {
                $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-sms' ) ); ?></option>');
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'educore_get_students_by_class_cert',
                    security: nonce,
                    class_name: selectedClass,
                    section_name: selectedSection
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var options = '<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-sms' ) ); ?></option>';
                        $.each(response.data, function(index, student) {
                            options += '<option value="' + student.id + '">Roll ' + student.roll_no + ': ' + student.full_name + ' (' + student.student_id + ')</option>';
                        });
                        $studentSelect.html(options);
                    } else {
                        $studentSelect.html('<option value=""><?php echo esc_js( __( 'No Active Students Found', 'ifsedu-sms' ) ); ?></option>');
                    }
                },
                error: function() {
                    $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-sms' ) ); ?></option>');
                }
            });
        }
    });
    </script>
    <?php
}