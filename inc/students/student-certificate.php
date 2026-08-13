<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

function educore_student_certificate_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units'; // Academic units table
    
    // Get parameters
    $action     = isset( $_GET['cert_action'] ) ? sanitize_text_field( $_GET['cert_action'] ) : '';
    $student_id = isset( $_GET['student_id'] ) ? intval( $_GET['student_id'] ) : 0;
    $doc_type   = isset( $_GET['doc_type'] ) ? sanitize_text_field( $_GET['doc_type'] ) : 'certificate';

    $school_name    = get_option( 'educore_school_name', get_bloginfo('name') );
    $school_address = get_option( 'educore_school_address', 'Address not set' );

    // =========================================================================
    // 1. PRINT VIEW (WHEN FORM IS SUBMITTED)
    // =========================================================================
    if ( $action === 'print' && $student_id > 0 ) {
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_students WHERE id = %d", $student_id ) );
        
        if ( ! $student ) {
            echo '<div class="alert alert-danger">Student not found.</div>';
            return;
        }

        $back_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=certificate' );
        
        // Define Document Titles
        $doc_title = '';
        if ( $doc_type === 'testimonial' ) $doc_title = 'TESTIMONIAL';
        elseif ( $doc_type === 'transfer_certificate' ) $doc_title = 'TRANSFER CERTIFICATE';
        else $doc_title = 'CERTIFICATE OF ACHIEVEMENT';
        ?>

        <style>
            /* Premium Certificate CSS for Web View */
            .cert-print-wrapper {
                max-width: 1050px;
                min-height: 700px;
                margin: 0 auto;
                background: #ffffff;
                padding: 60px;
                border: 15px solid #006a4e; /* Green Outer Border */
                outline: 6px double #d4af37; /* Gold Inner Double Border */
                outline-offset: -25px;
                position: relative;
                font-family: 'Georgia', 'Times New Roman', serif;
                color: #0f172a;
                text-align: center;
                z-index: 1;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                box-sizing: border-box;
            }

            /* The Watermark Layer */
            .cert-print-wrapper::before {
                content: "<?php echo esc_js( mb_strtoupper( $school_name, 'UTF-8' ) ); ?>";
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-30deg);
                font-size: 80px;
                font-weight: 900;
                color: rgba(0, 106, 78, 0.04); /* Very light green watermark */
                white-space: nowrap;
                z-index: -1;
                pointer-events: none;
                user-select: none;
            }

            .cert-header { margin-bottom: 20px; }
            
            .cert-school-name { 
                font-size: 42px; 
                font-weight: 900; 
                color: #006a4e; 
                text-transform: uppercase; 
                margin: 0; 
                letter-spacing: 2px;
                text-shadow: 1px 1px 0px rgba(0,0,0,0.1);
            }
            
            .cert-school-address { 
                font-size: 16px; 
                margin: 8px 0 20px; 
                color: #475569; 
                font-style: italic;
            }
            
            .cert-title-badge {
                display: inline-block;
                background: #0f172a;
                color: #d4af37; /* Gold text */
                font-size: 24px;
                font-weight: bold;
                padding: 10px 40px;
                border-radius: 5px;
                letter-spacing: 3px;
                margin-bottom: 20px;
                border: 2px solid #d4af37;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            
            .cert-body { 
                font-size: 22px; 
                line-height: 2.2; 
                text-align: justify; 
                text-align-last: center; /* Centers the justified text */
                padding: 0 40px;
                flex-grow: 1;
                display: flex;
                align-items: center; /* Vertically center the content text */
            }
            
            .cert-body strong { 
                border-bottom: 1px dashed #0f172a; 
                padding: 0 10px; 
                font-size: 24px;
                font-style: italic;
                color: #006a4e;
            }
            
            .cert-seal {
                width: 100px;
                height: 100px;
                border: 3px dashed #d4af37;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #d4af37;
                font-weight: bold;
                font-size: 14px;
                text-transform: uppercase;
                text-align: center;
                margin: 0 auto;
                opacity: 0.8;
            }

            .cert-footer { 
                display: flex; 
                justify-content: space-between; 
                align-items: flex-end;
                padding: 0 40px;
                margin-top: 20px;
            }
            
            .cert-sign { 
                text-align: center; 
                border-top: 2px solid #000; 
                width: 220px; 
                padding-top: 8px; 
                font-size: 18px; 
                font-weight: bold; 
                color: #0f172a;
            }

            .cert-date {
                font-size: 18px;
                font-style: italic;
                color: #475569;
            }
            
            /* ========================================================
               PRINT MEDIA STYLES (Force 1 Page Landscape)
               ======================================================== */
            @media print {
                /* Forces Landscape Mode automatically in the print dialog */
                @page { 
                    size: A4 landscape; 
                    margin: 10mm; /* Small physical margin to prevent clipping */
                }
                
                html, body {
                    width: 100%;
                    height: 100%;
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #fff;
                }

                body * { visibility: hidden; }
                
                .cert-print-wrapper, .cert-print-wrapper * { visibility: visible; }
                
                .cert-print-wrapper { 
                    position: absolute; 
                    left: 0; 
                    top: 0; 
                    width: 100%; 
                    height: 98vh; /* Force exactly 1 viewport height to prevent page breaks */
                    max-width: none;
                    margin: 0; 
                    box-shadow: none; 
                    border-width: 12px; /* Slightly thinner for print to save space */
                    outline-offset: -20px;
                    padding: 40px;
                    page-break-after: avoid;
                    page-break-before: avoid;
                }

                /* Adjust font sizes slightly for perfect landscape fit if needed */
                .cert-school-name { font-size: 38px; }
                .cert-body { font-size: 20px; line-height: 2; padding: 0 20px; }
                .cert-body strong { font-size: 22px; }
                
                .no-print { display: none !important; }
            }
        </style>

        <div class="mb-4 no-print text-center">
            <button onclick="window.print();" class="btn btn-primary btn-lg" style="background-color: #006a4e; border: none; padding: 10px 30px;">
                <span class="dashicons dashicons-printer" style="margin-top: 4px;"></span> Print Document
            </button>
            <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-lg" style="padding: 10px 30px;">Back</a>
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
                        This is to certify that <strong><?php echo esc_html( $student->full_name ); ?></strong>, 
                        son/daughter of <strong><?php echo esc_html( $student->guardian_name ); ?></strong>, 
                        has been a bonafide student of this institution. 
                        He/She passed/studied in Class <strong><?php echo esc_html( $student->class_name ); ?></strong> 
                        (Section: <strong><?php echo esc_html( $student->section_name ? $student->section_name : 'N/A' ); ?></strong>, 
                        Roll No: <strong><?php echo esc_html( $student->roll_no ); ?></strong>). 
                        During his/her tenure in this school, his/her character and conduct have been highly satisfactory. 
                        We wish him/her all success in life.

                    <?php elseif ( $doc_type === 'transfer_certificate' ) : ?>
                        <!-- TRANSFER CERTIFICATE CONTENT -->
                        This is to certify that <strong><?php echo esc_html( $student->full_name ); ?></strong>, 
                        son/daughter of <strong><?php echo esc_html( $student->guardian_name ); ?></strong>, 
                        was a registered student of Class <strong><?php echo esc_html( $student->class_name ); ?></strong> 
                        under Student ID <strong><?php echo esc_html( $student->student_id ); ?></strong>. 
                        He/She is leaving the school on his/her own accord/guardian's request. 
                        All outstanding dues of the school have been cleared up to <strong><?php echo date('F Y'); ?></strong>. 
                        His/Her academic progress was satisfactory and character is good.

                    <?php else : ?>
                        <!-- GENERAL CERTIFICATE CONTENT -->
                        This Certificate of Achievement is proudly presented to <strong><?php echo esc_html( $student->full_name ); ?></strong> 
                        (Student ID: <strong><?php echo esc_html( $student->student_id ); ?></strong>) 
                        for outstanding performance and successful completion of academic requirements in 
                        Class <strong><?php echo esc_html( $student->class_name ); ?></strong>. 
                        Your dedication, hard work, and discipline are highly appreciated. 
                        Keep up the excellent work!
                    <?php endif; ?>
                </div>
            </div>

            <div class="cert-seal">Official<br>Seal</div>

            <div class="cert-footer">
                <div class="cert-sign">Class Teacher</div>
                <div class="cert-date">Issue Date: <?php echo date('d F, Y'); ?></div>
                <div class="cert-sign">Headmaster / Principal</div>
            </div>
        </div>

        <?php
        return; // End print view
    }

    // =========================================================================
    // 2. FORM VIEW (DEFAULT)
    // =========================================================================
    
    // Fetch unique classes and sections mapping from the academic units table
    $academic_units = $wpdb->get_results( "SELECT class_name, section_name FROM $table_units ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" );
    
    $unique_classes    = array();
    $unique_sections   = array();
    $class_section_map = array(); // For mapping classes to their sections
    
    if ( ! empty( $academic_units ) ) {
        foreach ( $academic_units as $unit ) {
            $c = $unit->class_name;
            $s = $unit->section_name;
            
            // Collect Unique Classes
            if ( ! in_array( $c, $unique_classes, true ) ) {
                $unique_classes[] = $c;
            }
            // Collect Unique Sections globally
            if ( ! empty( $s ) && ! in_array( $s, $unique_sections, true ) ) {
                $unique_sections[] = $s;
            }
            // Map Sections to specific Class
            if ( ! isset( $class_section_map[$c] ) ) {
                $class_section_map[$c] = array();
            }
            if ( ! empty( $s ) && ! in_array( $s, $class_section_map[$c], true ) ) {
                $class_section_map[$c][] = $s;
            }
        }
        // Ensure natural sorting
        usort( $unique_classes, 'strnatcasecmp' );
        usort( $unique_sections, 'strnatcasecmp' );
    }

    // Fetch active students
    $students = $wpdb->get_results( "SELECT id, full_name, student_id, class_name, section_name, roll_no FROM $table_students WHERE status='Active' ORDER BY roll_no ASC" );
    ?>

    <style>
        .cert-form-box {
            background: #ffffff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            border-top: 5px solid #006a4e;
            max-width: 850px;
            margin: 0 auto;
        }
        .form-label { color: #334155; font-size: 14px; font-weight: 700; margin-bottom: 8px; display: block; }
        .form-control-lg { border-radius: 8px; border: 1px solid #cbd5e1; padding: 12px 16px; font-size: 15px; }
        .form-control-lg:focus { border-color: #006a4e; box-shadow: 0 0 0 3px rgba(0,106,78,0.1); }
    </style>

    <div class="cert-form-box">
        <div class="text-center border-bottom pb-4 mb-4">
            <span class="dashicons dashicons-awards" style="font-size: 48px; width: 48px; height: 48px; color: #006a4e;"></span>
            <h2 class="mt-3" style="color: #0f172a; font-weight: 800;">Generate Official Documents</h2>
            <p style="color: #64748b; font-size: 15px;">Select document type and filter student to generate print-ready certificates.</p>
        </div>
        
        <form method="GET" action="">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="students">
            <input type="hidden" name="sub" value="certificate">
            <input type="hidden" name="cert_action" value="print">
            
            <div class="row">
                <!-- 1. Document Type -->
                <div class="col-md-6 mb-4">
                    <label class="form-label">1. Select Document Type</label>
                    <select name="doc_type" class="form-control form-control-lg" required>
                        <option value="certificate">🎓 Achievement Certificate</option>
                        <option value="testimonial">📜 Testimonial (Character Certificate)</option>
                        <option value="transfer_certificate">📄 Transfer Certificate (TC)</option>
                    </select>
                </div>

                <!-- 2. Class -->
                <div class="col-md-6 mb-4">
                    <label class="form-label">2. Select Class</label>
                    <select id="cert_class" class="form-control form-control-lg">
                        <option value="">-- All Classes --</option>
                        <?php foreach ( $unique_classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $cls ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Section -->
                <div class="col-md-6 mb-4">
                    <label class="form-label">3. Select Section (Optional)</label>
                    <select id="cert_section" class="form-control form-control-lg">
                        <option value="">-- All Sections --</option>
                        <?php foreach ( $unique_sections as $sec ) : ?>
                            <option value="<?php echo esc_attr( $sec ); ?>"><?php echo esc_html( $sec ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 4. Student -->
                <div class="col-md-6 mb-4">
                    <label class="form-label">4. Select Student</label>
                    <select id="cert_student" name="student_id" class="form-control form-control-lg" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ( $students as $s ) : ?>
                            <option value="<?php echo esc_attr( $s->id ); ?>" 
                                    data-class="<?php echo esc_attr( $s->class_name ); ?>" 
                                    data-section="<?php echo esc_attr( $s->section_name ); ?>">
                                <?php echo esc_html( $s->full_name . ' (Roll: ' . $s->roll_no . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-success w-100 py-3 mt-2" style="background-color: #006a4e; border: none; font-size: 16px; font-weight: 700; border-radius: 8px;">
                <span class="dashicons dashicons-visibility" style="margin-top: 2px;"></span> Generate & Preview Document
            </button>
        </form>
    </div>

    <!-- JavaScript for Smart Dropdown Filtering -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const classSelect = document.getElementById('cert_class');
        const sectionSelect = document.getElementById('cert_section');
        const studentSelect = document.getElementById('cert_student');
        
        // Save original options
        const allStudents = Array.from(studentSelect.options).slice(1); // excluding first placeholder
        
        // Mapping of classes to their respective sections
        const classSectionMap = <?php echo wp_json_encode( $class_section_map ); ?>;
        const allUniqueSections = <?php echo wp_json_encode( $unique_sections ); ?>;

        // Function to update sections based on selected class
        function updateSections() {
            const selectedClass = classSelect.value;
            
            // Clear current sections
            sectionSelect.innerHTML = '<option value="">-- All Sections --</option>';
            
            let sectionsToLoad = [];
            
            // If a class is selected and it has mapped sections
            if (selectedClass && classSectionMap[selectedClass]) {
                sectionsToLoad = classSectionMap[selectedClass];
            } else if (!selectedClass) {
                // If "All Classes" is selected, show all unique sections globally
                sectionsToLoad = allUniqueSections;
            }
            
            // Populate section dropdown
            sectionsToLoad.forEach(sec => {
                let opt = document.createElement('option');
                opt.value = sec;
                opt.textContent = sec;
                sectionSelect.appendChild(opt);
            });
            
            // Trigger student filter after updating sections
            filterStudents();
        }

        // Function to update students based on class and section
        function filterStudents() {
            const selectedClass = classSelect.value;
            const selectedSection = sectionSelect.value;

            // Reset student dropdown
            studentSelect.innerHTML = '<option value="">-- Select Student --</option>';

            allStudents.forEach(option => {
                const sClass = option.getAttribute('data-class');
                const sSection = option.getAttribute('data-section');

                let matchClass = (selectedClass === "" || sClass === selectedClass);
                let matchSection = (selectedSection === "" || sSection === selectedSection);

                if (matchClass && matchSection) {
                    studentSelect.appendChild(option.cloneNode(true));
                }
            });
        }

        // Event Listeners
        classSelect.addEventListener('change', updateSections);
        sectionSelect.addEventListener('change', filterStudents);
    });
    </script>

    <?php
}
?>