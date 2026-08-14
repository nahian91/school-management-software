<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

/**
 * Enterprise Academic Certificate, Testimonial & TC Compiler Engine
 * File: student-certificate-view.php
 */
function educore_student_certificate_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access this page.', 'ifsedu-sms' ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    
    // Request routing
    $action     = isset( $_GET['cert_action'] ) ? sanitize_text_field( wp_unslash( $_GET['cert_action'] ) ) : '';
    $student_id = isset( $_GET['student_id'] ) ? absint( $_GET['student_id'] ) : 0;
    $doc_type   = isset( $_GET['doc_type'] ) ? sanitize_text_field( wp_unslash( $_GET['doc_type'] ) ) : 'certificate';

    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    if ( empty( $school_name ) || $school_name === 'WordPress' ) {
        $school_name = 'Green Gems International School & College';
    }
    $school_address = get_option( 'educore_school_address', get_bloginfo( 'description' ) );

    // =========================================================================
    // 1. PRINT & PREVIEW VIEW
    // =========================================================================
    if ( $action === 'print' && $student_id > 0 ) {
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_students} WHERE id = %d", $student_id ) );
        
        if ( ! $student ) {
            echo '<div class="notice notice-error" style="padding:15px; margin:20px 20px 20px 0; font-weight:700;">' . esc_html__( 'Student record not found in database.', 'ifsedu-sms' ) . '</div>';
            return;
        }

        $back_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=certificate' );
        
        // Define Document Titles
        $doc_title = 'CERTIFICATE OF ACHIEVEMENT';
        if ( $doc_type === 'testimonial' ) {
            $doc_title = 'ACADEMIC TESTIMONIAL';
        } elseif ( $doc_type === 'transfer_certificate' ) {
            $doc_title = 'TRANSFER CERTIFICATE';
        }

        $guardian_display = ! empty( $student->father_name ) ? $student->father_name : ( ! empty( $student->guardian_name ) ? $student->guardian_name : '—' );
        $mother_display   = ! empty( $student->mother_name ) ? $student->mother_name : '—';
        ?>

        <style>
            .cert-print-container {
                margin: 20px auto;
                max-width: 1100px;
                font-family: 'Georgia', 'Times New Roman', serif;
            }

            .cert-action-bar {
                display: flex;
                justify-content: center;
                gap: 12px;
                margin-bottom: 24px;
            }

            .cert-btn {
                padding: 10px 24px;
                font-size: 14px;
                font-weight: 700;
                border-radius: 8px;
                cursor: pointer;
                border: 1px solid transparent;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                transition: all 0.2s ease;
            }

            .cert-btn-primary { background: #006a4e; color: #ffffff; }
            .cert-btn-primary:hover { background: #00523c; color: #ffffff; }

            .cert-btn-secondary { background: #ffffff; border-color: #cbd5e1; color: #475569; }
            .cert-btn-secondary:hover { background: #f8fafc; color: #0f172a; }

            /* Premium Certificate Architecture */
            .cert-print-wrapper {
                width: 100%;
                min-height: 680px;
                background: #ffffff;
                padding: 50px 60px;
                border: 14px solid #006a4e;
                outline: 4px double #d97706;
                outline-offset: -20px;
                position: relative;
                color: #0f172a;
                text-align: center;
                box-sizing: border-box;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                box-shadow: 0 10px 30px rgba(0,0,0,0.06);
                border-radius: 4px;
                overflow: hidden;
            }

            /* Watermark Emblem Layer */
            .cert-print-wrapper::before {
                content: "<?php echo esc_js( mb_strtoupper( $school_name, 'UTF-8' ) ); ?>";
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-25deg);
                font-size: 64px;
                font-weight: 900;
                color: rgba(0, 106, 78, 0.035);
                white-space: nowrap;
                z-index: 0;
                pointer-events: none;
                user-select: none;
            }

            .cert-header { 
                position: relative; 
                z-index: 1; 
                margin-bottom: 20px; 
            }
            
            .cert-school-name { 
                font-size: 34px; 
                font-weight: 900; 
                color: #006a4e; 
                text-transform: uppercase; 
                margin: 0; 
                letter-spacing: 1.5px;
                line-height: 1.2;
            }
            
            .cert-school-address { 
                font-size: 14px; 
                margin: 6px 0 18px; 
                color: #64748b; 
                font-style: italic;
                font-family: Arial, sans-serif;
            }
            
            .cert-title-badge {
                display: inline-block;
                background: #0f172a;
                color: #fef08a;
                font-size: 18px;
                font-weight: 800;
                padding: 6px 36px;
                border-radius: 4px;
                letter-spacing: 2px;
                text-transform: uppercase;
                border: 1.5px solid #d97706;
            }
            
            .cert-body { 
                font-size: 19px; 
                line-height: 2.3; 
                text-align: justify; 
                text-align-last: center;
                padding: 10px 30px;
                flex-grow: 1;
                display: flex;
                align-items: center;
                position: relative;
                z-index: 1;
            }
            
            .cert-body .highlight { 
                border-bottom: 1.5px dotted #006a4e; 
                padding: 0 8px; 
                font-weight: 800;
                color: #006a4e;
                font-style: italic;
            }
            
            .cert-seal-box {
                width: 90px;
                height: 90px;
                border: 2.5px dashed #d97706;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #d97706;
                font-weight: 800;
                font-size: 12px;
                text-transform: uppercase;
                text-align: center;
                margin: 0 auto;
                opacity: 0.85;
                font-family: Arial, sans-serif;
            }

            .cert-footer { 
                display: flex; 
                justify-content: space-between; 
                align-items: flex-end;
                padding: 0 20px;
                margin-top: 20px;
                position: relative;
                z-index: 1;
            }
            
            .cert-sign-col { 
                text-align: center; 
                border-top: 1.5px solid #0f172a; 
                width: 200px; 
                padding-top: 6px; 
                font-size: 14px; 
                font-weight: 800; 
                color: #0f172a;
                text-transform: uppercase;
                font-family: Arial, sans-serif;
                letter-spacing: 0.5px;
            }

            .cert-date {
                font-size: 15px;
                font-style: italic;
                color: #475569;
            }
            
            /* ========================================================
               PRINT MEDIA STYLES (A4 Landscape 1-Page Precision)
               ======================================================== */
            @media print {
                @page { 
                    size: A4 landscape; 
                    margin: 8mm 6mm; 
                }
                
                html, body {
                    width: 100%;
                    height: 100%;
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #ffffff !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                #wpadminbar, #adminmenumain, #wpfooter, .no-print { 
                    display: none !important; 
                }
                
                .cert-print-container {
                    margin: 0 !important;
                    max-width: none !important;
                    width: 100% !important;
                }

                .cert-print-wrapper { 
                    position: absolute !important; 
                    left: 0 !important; 
                    top: 0 !important; 
                    width: 100% !important; 
                    height: 98vh !important;
                    margin: 0 !important; 
                    box-shadow: none !important; 
                    border-width: 10px !important;
                    outline-offset: -16px !important;
                    padding: 30px 40px !important;
                    page-break-after: avoid !important;
                    page-break-before: avoid !important;
                }

                .cert-school-name { font-size: 32px !important; }
                .cert-body { font-size: 18px !important; line-height: 2.1 !important; padding: 0 10px !important; }
            }
        </style>

        <div class="cert-print-container">
            <div class="cert-action-bar no-print">
                <button onclick="window.print();" class="cert-btn cert-btn-primary">
                    <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Document', 'ifsedu-sms' ); ?>
                </button>
                <a href="<?php echo esc_url( $back_url ); ?>" class="cert-btn cert-btn-secondary">
                    <span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'Back to Generator', 'ifsedu-sms' ); ?>
                </a>
            </div>

            <div class="cert-print-wrapper">
                <div class="cert-header">
                    <h1 class="cert-school-name"><?php echo esc_html( $school_name ); ?></h1>
                    <p class="cert-school-address"><?php echo esc_html( $school_address ); ?></p>
                    <div class="cert-title-badge"><?php echo esc_html( $doc_title ); ?></div>
                </div>

                <div class="cert-body">
                    <div>
                        <?php if ( $doc_type === 'testimonial' ) : ?>
                            <!-- TESTIMONIAL CONTENT -->
                            This is to certify that <span class="highlight"><?php echo esc_html( $student->full_name ); ?></span>, 
                            son/daughter of <span class="highlight"><?php echo esc_html( $guardian_display ); ?></span> 
                            and <span class="highlight"><?php echo esc_html( $mother_display ); ?></span>, 
                            bearing Student ID <span class="highlight"><?php echo esc_html( $student->student_id ); ?></span>, 
                            is/was a bonafide student of this institution in Class <span class="highlight"><?php echo esc_html( $student->class_name ); ?></span> 
                            (Section: <span class="highlight"><?php echo esc_html( ! empty( $student->section_name ) ? $student->section_name : 'N/A' ); ?></span>, 
                            Roll No: <span class="highlight">#<?php echo esc_html( $student->roll_no ); ?></span>). 
                            To the best of my knowledge, he/she bears a good moral character and took an active interest in co-curricular activities. 
                            I wish him/her every success and a bright future in all academic and personal pursuits.

                        <?php elseif ( $doc_type === 'transfer_certificate' ) : ?>
                            <!-- TRANSFER CERTIFICATE CONTENT -->
                            This is to certify that <span class="highlight"><?php echo esc_html( $student->full_name ); ?></span>, 
                            son/daughter of <span class="highlight"><?php echo esc_html( $guardian_display ); ?></span>, 
                            was a registered regular student of this institution in Class <span class="highlight"><?php echo esc_html( $student->class_name ); ?></span> 
                            under Student ID <span class="highlight"><?php echo esc_html( $student->student_id ); ?></span>. 
                            He/She has paid all institutional dues up to the month of <span class="highlight"><?php echo esc_html( date_i18n( 'F Y' ) ); ?></span>. 
                            He/She is granted this Transfer Certificate on personal grounds/guardian's formal request. His/Her conduct and character during the academic stay were satisfactory.

                        <?php else : ?>
                            <!-- GENERAL ACHIEVEMENT CERTIFICATE -->
                            This Certificate of Academic Excellence is proudly presented to <span class="highlight"><?php echo esc_html( $student->full_name ); ?></span> 
                            (Student ID: <span class="highlight"><?php echo esc_html( $student->student_id ); ?></span>, Roll No: <span class="highlight">#<?php echo esc_html( $student->roll_no ); ?></span>) 
                            in recognition of exemplary discipline, dedication, and commendable performance in 
                            Class <span class="highlight"><?php echo esc_html( $student->class_name ); ?></span> during the academic session. 
                            We appreciate the outstanding efforts and wish for continued academic distinction.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cert-seal-box">Official<br>Seal</div>

                <div class="cert-footer">
                    <div class="cert-sign-col"><?php esc_html_e( 'Class Teacher', 'ifsedu-sms' ); ?></div>
                    <div class="cert-date"><?php echo esc_html( sprintf( __( 'Date of Issue: %s', 'ifsedu-sms' ), date_i18n( 'd F, Y' ) ) ); ?></div>
                    <div class="cert-sign-col"><?php esc_html_e( 'Principal / Headmaster', 'ifsedu-sms' ); ?></div>
                </div>
            </div>
        </div>

        <?php
        return; // End print view
    }

    // =========================================================================
    // 2. FORM FILTER VIEW (DEFAULT)
    // =========================================================================
    $academic_units = $wpdb->get_results( "SELECT class_name, section_name FROM {$table_units} ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" );
    
    $unique_classes    = array();
    $unique_sections   = array();
    $class_section_map = array();
    
    if ( ! empty( $academic_units ) ) {
        foreach ( $academic_units as $unit ) {
            $c = trim( $unit->class_name );
            $s = trim( $unit->section_name );
            
            if ( ! empty( $c ) && ! in_array( $c, $unique_classes, true ) ) {
                $unique_classes[] = $c;
            }
            if ( ! empty( $s ) && ! in_array( $s, $unique_sections, true ) ) {
                $unique_sections[] = $s;
            }
            if ( ! isset( $class_section_map[ $c ] ) ) {
                $class_section_map[ $c ] = array();
            }
            if ( ! empty( $s ) && ! in_array( $s, $class_section_map[ $c ], true ) ) {
                $class_section_map[ $c ][] = $s;
            }
        }
        usort( $unique_classes, 'strnatcasecmp' );
        usort( $unique_sections, 'strnatcasecmp' );
    }

    $students = $wpdb->get_results( "SELECT id, full_name, student_id, class_name, section_name, roll_no FROM {$table_students} WHERE status='Active' ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC" );
    ?>

    <style>
        .cert-form-box {
            background: #ffffff;
            border-radius: 16px;
            padding: 36px 40px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            border-top: 5px solid #006a4e;
            max-width: 820px;
            margin: 20px auto;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .cert-form-header {
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 20px;
            margin-bottom: 28px;
        }

        .cert-form-header h2 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 10px 0 4px 0;
        }

        .cert-form-header p {
            color: #64748b;
            font-size: 14px;
            margin: 0;
        }

        .cert-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 22px;
        }

        @media (max-width: 768px) {
            .cert-grid-2 { grid-template-columns: 1fr; }
        }

        .cert-field-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .cert-field-label {
            color: #475569;
            font-size: 12.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .cert-select-input {
            width: 100%;
            height: 42px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 0 14px;
            font-size: 14px;
            background: #f8fafc;
            color: #0f172a;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .cert-select-input:focus {
            border-color: #006a4e;
            background: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
        }

        .cert-submit-btn {
            width: 100%;
            height: 46px;
            background: #006a4e;
            color: #ffffff;
            border: none;
            font-size: 15px;
            font-weight: 800;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s ease;
            box-shadow: 0 4px 14px rgba(0, 106, 78, 0.2);
            margin-top: 10px;
        }

        .cert-submit-btn:hover {
            background: #00523c;
        }
    </style>

    <div class="cert-form-box">
        <div class="cert-form-header">
            <span class="dashicons dashicons-awards" style="font-size: 44px; width: 44px; height: 44px; color: #006a4e;"></span>
            <h2><?php esc_html_e( 'Academic Document & Certificate Compiler', 'ifsedu-sms' ); ?></h2>
            <p><?php esc_html_e( 'Select the document type and student credentials to compile an official certificate.', 'ifsedu-sms' ); ?></p>
        </div>
        
        <form method="GET" action="">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="students">
            <input type="hidden" name="sub" value="certificate">
            <input type="hidden" name="cert_action" value="print">
            
            <div class="cert-grid-2">
                <!-- 1. Document Type -->
                <div class="cert-field-group">
                    <label class="cert-field-label"><?php esc_html_e( '1. Select Document Type', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="doc_type" class="cert-select-input" required>
                        <option value="certificate">🎓 <?php esc_html_e( 'Certificate of Achievement', 'ifsedu-sms' ); ?></option>
                        <option value="testimonial">📜 <?php esc_html_e( 'Academic Testimonial / Character Certificate', 'ifsedu-sms' ); ?></option>
                        <option value="transfer_certificate">📄 <?php esc_html_e( 'Transfer Certificate (TC)', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>

                <!-- 2. Class -->
                <div class="cert-field-group">
                    <label class="cert-field-label"><?php esc_html_e( '2. Filter By Class', 'ifsedu-sms' ); ?></label>
                    <select id="cert_class" class="cert-select-input">
                        <option value=""><?php esc_html_e( '-- All Classes --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $unique_classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( 'Class ' . $cls ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Section -->
                <div class="cert-field-group">
                    <label class="cert-field-label"><?php esc_html_e( '3. Filter By Section', 'ifsedu-sms' ); ?></label>
                    <select id="cert_section" class="cert-select-input">
                        <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $unique_sections as $sec ) : ?>
                            <option value="<?php echo esc_attr( $sec ); ?>"><?php echo esc_html( $sec ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 4. Target Student Selector -->
                <div class="cert-field-group">
                    <label class="cert-field-label"><?php esc_html_e( '4. Select Target Student', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select id="cert_student" name="student_id" class="cert-select-input" required>
                        <option value=""><?php esc_html_e( '-- Choose Student --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $students as $s ) : ?>
                            <option value="<?php echo esc_attr( $s->id ); ?>" 
                                    data-class="<?php echo esc_attr( $s->class_name ); ?>" 
                                    data-section="<?php echo esc_attr( $s->section_name ); ?>">
                                <?php echo esc_html( '[Roll: ' . $s->roll_no . '] ' . $s->full_name . ' (' . $s->class_name . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="cert-submit-btn">
                <span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Compile & Preview Document', 'ifsedu-sms' ); ?>
            </button>
        </form>
    </div>

    <!-- Client-Side Filter Chaining Engine -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const classSelect       = document.getElementById('cert_class');
        const sectionSelect     = document.getElementById('cert_section');
        const studentSelect     = document.getElementById('cert_student');
        
        const allStudents       = Array.from(studentSelect.options).slice(1);
        const classSectionMap   = <?php echo wp_json_encode( $class_section_map ); ?>;
        const allUniqueSections = <?php echo wp_json_encode( $unique_sections ); ?>;

        function updateSections() {
            const selectedClass = classSelect.value;
            sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>';
            
            let sectionsToLoad = [];
            if (selectedClass && classSectionMap[selectedClass]) {
                sectionsToLoad = classSectionMap[selectedClass];
            } else if (!selectedClass) {
                sectionsToLoad = allUniqueSections;
            }
            
            sectionsToLoad.forEach(sec => {
                let opt = document.createElement('option');
                opt.value = sec;
                opt.textContent = sec;
                sectionSelect.appendChild(opt);
            });
            
            filterStudents();
        }

        function filterStudents() {
            const selectedClass   = classSelect.value;
            const selectedSection = sectionSelect.value;

            studentSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-sms' ) ); ?></option>';

            allStudents.forEach(option => {
                const sClass   = option.getAttribute('data-class');
                const sSection = option.getAttribute('data-section');

                let matchClass   = (selectedClass === "" || sClass === selectedClass);
                let matchSection = (selectedSection === "" || sSection === selectedSection);

                if (matchClass && matchSection) {
                    studentSelect.appendChild(option.cloneNode(true));
                }
            });
        }

        classSelect.addEventListener('change', updateSections);
        sectionSelect.addEventListener('change', filterStudents);
    });
    </script>
    <?php
}