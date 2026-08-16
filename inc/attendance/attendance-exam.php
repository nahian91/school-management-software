<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Examination Hall Attendance Roster & Hall Invigilator Log View
 * File: inc/attendance/attendance-exam.php
 * Custom Prefixes Applied: dpt-, afdp-
 */

function educore_exam_attendance_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_subjects = $wpdb->prefix . 'sms_subjects';
    $table_exam_att = $wpdb->prefix . 'sms_exam_attendance';

    // --------------------------------------------------------------------------
    // 0. AUTO-SCHEMA CHECK (Ensures exam attendance table exists)
    // --------------------------------------------------------------------------
    $check_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_exam_att}'" );
    if ( empty( $check_table ) ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $sql_exam_att = "CREATE TABLE {$table_exam_att} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            exam_id bigint(20) NOT NULL,
            student_id bigint(20) NOT NULL,
            class_name varchar(50) NOT NULL,
            section_name varchar(50) DEFAULT '' NOT NULL,
            subject_name varchar(150) NOT NULL,
            attendance_date date DEFAULT '1970-01-01' NOT NULL,
            status varchar(20) DEFAULT 'Present' NOT NULL,
            invigilator_remarks varchar(255) DEFAULT '' NOT NULL,
            recorded_by bigint(20) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY exam_student_subject_date (exam_id, student_id, subject_name, attendance_date),
            KEY exam_student_idx (exam_id, student_id),
            KEY status_idx (status)
        ) $charset_collate;";
        dbDelta( $sql_exam_att );
    }

    $saved_notice = '';

    // --------------------------------------------------------------------------
    // 1. SAVE EXAM ATTENDANCE FORM SUBMISSION
    // --------------------------------------------------------------------------
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['educore_save_exam_attendance'] ) ) {
        if ( isset( $_POST['educore_exam_att_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_exam_att_nonce_field'] ) ), 'save_exam_attendance_action' ) ) {
            $exam_id         = isset( $_POST['exam_id'] ) ? absint( $_POST['exam_id'] ) : 0;
            $class_name      = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
            $section_name    = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';
            $subject_name    = isset( $_POST['subject_name'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_name'] ) ) : '';
            $attendance_date = isset( $_POST['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_POST['attendance_date'] ) ) : current_time( 'Y-m-d' );
            $att_statuses    = isset( $_POST['att_status'] ) && is_array( $_POST['att_status'] ) ? $_POST['att_status'] : array();
            $invig_remarks   = isset( $_POST['invigilator_remarks'] ) && is_array( $_POST['invigilator_remarks'] ) ? $_POST['invigilator_remarks'] : array();

            $saved_count = 0;
            if ( $exam_id > 0 && ! empty( $class_name ) && ! empty( $subject_name ) && ! empty( $att_statuses ) ) {
                foreach ( $att_statuses as $student_id => $status_val ) {
                    $st_id   = absint( $student_id );
                    $status  = sanitize_text_field( $status_val );
                    $remarks = isset( $invig_remarks[ $student_id ] ) ? sanitize_text_field( wp_unslash( $invig_remarks[ $student_id ] ) ) : '';

                    $existing_id = $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$table_exam_att} WHERE exam_id = %d AND student_id = %d AND subject_name = %s AND attendance_date = %s",
                        $exam_id, $st_id, $subject_name, $attendance_date
                    ) );

                    $data = array(
                        'exam_id'             => $exam_id,
                        'student_id'          => $st_id,
                        'class_name'          => $class_name,
                        'section_name'        => $section_name,
                        'subject_name'        => $subject_name,
                        'attendance_date'     => $attendance_date,
                        'status'              => $status,
                        'invigilator_remarks' => $remarks,
                        'recorded_by'         => get_current_user_id()
                    );

                    if ( $existing_id ) {
                        $wpdb->update( $table_exam_att, $data, array( 'id' => $existing_id ) );
                    } else {
                        $wpdb->insert( $table_exam_att, $data );
                    }
                    $saved_count++;
                }

                $saved_notice = sprintf( esc_html__( 'Successfully recorded examination hall attendance for %d candidates.', 'ifsedu-sms' ), $saved_count );
            }
        }
    }

    // Capture GET Request Parameters
    $filter_exam    = isset( $_GET['exam_id'] ) ? absint( $_GET['exam_id'] ) : 0;
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $filter_subject = isset( $_GET['subject_name'] ) ? sanitize_text_field( wp_unslash( $_GET['subject_name'] ) ) : '';
    $filter_date    = isset( $_GET['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_GET['attendance_date'] ) ) : current_time( 'Y-m-d' );

    $exams = $wpdb->get_results( "SELECT id, exam_name FROM {$table_exams} ORDER BY id DESC" );

    // Fetch Unique Classes and build section maps with Natural Numeric Sorting
    $raw_units = $wpdb->get_results( "SELECT id, class_name, section_name, dept_name FROM {$table_units} WHERE class_name != ''" );
    $academic_classes    = array();
    $class_section_map   = array();
    $class_subject_map   = array();

    if ( ! empty( $raw_units ) ) {
        foreach ( $raw_units as $unit ) {
            $c_name = trim( $unit->class_name );
            if ( ! isset( $class_section_map[ $c_name ] ) ) {
                $class_section_map[ $c_name ] = array();
                $class_subject_map[ $c_name ] = array();
                $academic_classes[] = $c_name;
            }
            if ( ! empty( $unit->section_name ) ) {
                $class_section_map[ $c_name ][] = trim( $unit->section_name );
            }
            if ( ! empty( $unit->dept_name ) ) {
                $class_section_map[ $c_name ][] = trim( $unit->dept_name );
            }

            // Fetch subjects mapped to this specific unit id or class name
            $subs = $wpdb->get_results( $wpdb->prepare( 
                "SELECT subject_name, subject_code FROM {$table_subjects} WHERE class_id = %d OR class_id = %s OR class_name = %s", 
                $unit->id, $c_name, $c_name 
            ) );
            if ( ! empty( $subs ) ) {
                foreach ( $subs as $sub ) {
                    $class_subject_map[ $c_name ][] = array(
                        'name' => $sub->subject_name,
                        'code' => $sub->subject_code ? ' (' . $sub->subject_code . ')' : ''
                    );
                }
            }
        }

        foreach ( $class_section_map as $c_name => $secs ) {
            $class_section_map[ $c_name ] = array_values( array_unique( array_filter( $secs ) ) );
            usort( $class_section_map[ $c_name ], 'strnatcasecmp' );
        }

        foreach ( $class_subject_map as $c_name => $subs ) {
            // Unique mapping based on subject name
            $unique_subs = array();
            foreach ( $subs as $s ) {
                $unique_subs[ $s['name'] ] = $s;
            }
            $class_subject_map[ $c_name ] = array_values( $unique_subs );
        }

        // Fallback: if no subjects mapped via units, fetch all global subjects for every class
        $all_global_subs = $wpdb->get_results( "SELECT subject_name, subject_code FROM {$table_subjects} ORDER BY subject_name ASC" );
        foreach ( $academic_classes as $c_name ) {
            if ( empty( $class_subject_map[ $c_name ] ) && ! empty( $all_global_subs ) ) {
                foreach ( $all_global_subs as $gs ) {
                    $class_subject_map[ $c_name ][] = array(
                        'name' => $gs->subject_name,
                        'code' => $gs->subject_code ? ' (' . $gs->subject_code . ')' : ''
                    );
                }
            }
        }

        // Also ensure clean class name variants (e.g. "Class 1" vs "1") are mapped
        $normalized_map_sec = array();
        $normalized_map_sub = array();
        foreach ( $academic_classes as $c_name ) {
            $clean_key = trim( str_ireplace( 'Class ', '', $c_name ) );
            if ( isset( $class_section_map[ $c_name ] ) ) {
                empty( $class_section_map[ 'Class ' . $clean_key ] ) ? $class_section_map[ 'Class ' . $clean_key ] = $class_section_map[ $c_name ] : null;
                empty( $class_section_map[ $clean_key ] ) ? $class_section_map[ $clean_key ] = $class_section_map[ $c_name ] : null;
            }
            if ( isset( $class_subject_map[ $c_name ] ) ) {
                empty( $class_subject_map[ 'Class ' . $clean_key ] ) ? $class_subject_map[ 'Class ' . $clean_key ] = $class_subject_map[ $c_name ] : null;
                empty( $class_subject_map[ $clean_key ] ) ? $class_subject_map[ $clean_key ] = $class_subject_map[ $c_name ] : null;
            }
        }

        $academic_classes = array_values( array_unique( $academic_classes ) );
        usort( $academic_classes, 'strnatcasecmp' );
    }

    // Pre-populate Available Subjects for Selected Class on Server Load
    $available_sections = array();
    $available_subjects = array();
    if ( ! empty( $filter_class ) ) {
        if ( isset( $class_section_map[ $filter_class ] ) ) {
            $available_sections = $class_section_map[ $filter_class ];
        }
        if ( isset( $class_subject_map[ $filter_class ] ) ) {
            $available_subjects = $class_subject_map[ $filter_class ];
        }
    }

    // Fetch Active Students Roster & Saved Exam Attendance
    $students_list = array();
    $saved_logs    = array();

    if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) {
        $clean_filter_class = trim( str_ireplace( 'Class ', '', $filter_class ) );
        $st_sql = "SELECT id, full_name, student_id, roll_no, class_name, section_name, photo_url FROM {$table_students} WHERE status = 'Active' AND (class_name = %s OR class_name = %s)";
        $st_params = array( $filter_class, $clean_filter_class );

        if ( ! empty( $filter_section ) ) {
            $st_sql .= " AND section_name = %s";
            $st_params[] = $filter_section;
        }

        $st_sql .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $students_list = $wpdb->get_results( $wpdb->prepare( $st_sql, ...$st_params ) );

        $existing_logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT student_id, status, invigilator_remarks 
             FROM {$table_exam_att} 
             WHERE exam_id = %d AND (class_name = %s OR class_name = %s) AND subject_name = %s AND attendance_date = %s",
            $filter_exam, $filter_class, $clean_filter_class, $filter_subject, $filter_date
        ), OBJECT_K );

        if ( ! empty( $existing_logs ) ) {
            $saved_logs = $existing_logs;
        }
    }

    $admin_page_url = admin_url( 'admin.php' );
    ?>

    <style>
        .dpt-exam-att-root {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .dpt-filter-grid-5 {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1.5fr 2fr 1.5fr 1.2fr;
            gap: 14px;
            align-items: flex-end;
        }

        @media (max-width: 1200px) {
            .dpt-filter-grid-5 { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .dpt-filter-grid-5 { grid-template-columns: 1fr; }
        }

        .dpt-avatar-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dpt-avatar-mini {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 1.5px solid #006a4e;
            background: #e2e8f0;
            flex-shrink: 0;
        }

        .dpt-avatar-fallback-mini {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ecfdf5;
            color: #006a4e;
            font-weight: 800;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #a7f3d0;
            flex-shrink: 0;
        }

        .dpt-exam-card-badge {
            background: #f1f5f9;
            color: #0f172a;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 700;
            border: 1px solid #cbd5e1;
            font-family: monospace;
            font-size: 11.5px;
        }

        .dpt-remarks-input {
            width: 100%;
            height: 34px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 0 8px;
            font-size: 12.5px;
            background: #f8fafc;
            box-sizing: border-box;
        }

        .dpt-remarks-input:focus {
            border-color: #006a4e;
            background: #ffffff;
            outline: none;
        }
    </style>

    <div class="dpt-exam-att-root">

        <?php if ( ! empty( $saved_notice ) ) : ?>
            <div class="afdp-success-banner">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html( $saved_notice ); ?>
            </div>
        <?php endif; ?>

        <!-- Exam Attendance Filter Console -->
        <div class="dpt-bento-card no-print">
            <form method="GET" action="<?php echo esc_url( $admin_page_url ); ?>">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="attendance">
                <input type="hidden" name="sub" value="exam">

                <div class="dpt-filter-grid-5">
                    <!-- 1. Select Exam -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '1. Select Exam', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" id="educore_exam_att_exam_select" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Class Selection -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '2. Class Name', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="educore_exam_att_class_select" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $academic_classes as $cls_name ) : ?>
                                <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                                    <?php printf( esc_html__( '%s', 'ifsedu-sms' ), esc_html( $cls_name ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 3. Section Selection -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '3. Section (Optional)', 'ifsedu-sms' ); ?></label>
                        <select name="section_name" id="educore_exam_att_section_select" class="dpt-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                            <?php foreach ( $available_sections as $sec_name ) : ?>
                                <option value="<?php echo esc_attr( $sec_name ); ?>" <?php selected( $filter_section, $sec_name ); ?>>
                                    <?php echo esc_html( $sec_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 4. Subject Selection -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '4. Exam Subject', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="subject_name" id="educore_exam_att_subject_select" class="dpt-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-sms' ); ?></option>
                            <?php if ( ! empty( $available_subjects ) ) : ?>
                                <?php foreach ( $available_subjects as $sub_item ) : ?>
                                    <option value="<?php echo esc_attr( $sub_item['name'] ); ?>" <?php selected( $filter_subject, $sub_item['name'] ); ?>>
                                        <?php echo esc_html( $sub_item['name'] . $sub_item['code'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- 5. Exam Date -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( '5. Exam Date', 'ifsedu-sms' ); ?></label>
                        <input type="date" name="attendance_date" class="dpt-input-field" value="<?php echo esc_attr( $filter_date ); ?>">
                    </div>

                    <!-- Submit Trigger -->
                    <div>
                        <button type="submit" class="dpt-btn-submit-trigger" style="width: 100%;">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e( 'Load Roster', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Local Instant Cascade Script (No AJAX failures) -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var classSectionMap = <?php echo wp_json_encode( $class_section_map ); ?>;
            var classSubjectMap = <?php echo wp_json_encode( $class_subject_map ); ?>;
            var currentSelectedSection = "<?php echo esc_js( $filter_section ); ?>";
            var currentSelectedSubject = "<?php echo esc_js( $filter_subject ); ?>";

            function updateDropdowns(className) {
                var $secSelect     = $('#educore_exam_att_section_select');
                var $subjectSelect = $('#educore_exam_att_subject_select');

                $secSelect.empty().append('<option value=""><?php echo esc_js( __( "-- All Sections --", "ifsedu-sms" ) ); ?></option>');
                $subjectSelect.empty().append('<option value=""><?php echo esc_js( __( "-- Choose Subject --", "ifsedu-sms" ) ); ?></option>');

                if (!className) return;

                // Populate Sections
                if (classSectionMap[className] && classSectionMap[className].length > 0) {
                    $.each(classSectionMap[className], function(i, sec) {
                        var isSelected = (sec === currentSelectedSection) ? 'selected' : '';
                        $secSelect.append('<option value="' + sec + '" ' + isSelected + '>' + sec + '</option>');
                    });
                }

                // Populate Subjects
                if (classSubjectMap[className] && classSubjectMap[className].length > 0) {
                    $.each(classSubjectMap[className], function(i, sub) {
                        var isSelected = (sub.name === currentSelectedSubject) ? 'selected' : '';
                        $subjectSelect.append('<option value="' + sub.name + '" ' + isSelected + '>' + sub.name + sub.code + '</option>');
                    });
                } else {
                    $subjectSelect.html('<option value=""><?php echo esc_js( __( "No Subjects Configured for this Class", "ifsedu-sms" ) ); ?></option>');
                }
            }

            // On Class change
            $('#educore_exam_att_class_select').on('change', function() {
                var selectedClass = $(this).val();
                currentSelectedSection = '';
                currentSelectedSubject = '';
                updateDropdowns(selectedClass);
            });
        });
        </script>

        <!-- Exam Hall Attendance Roster Form -->
        <?php if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) : ?>
            <div class="dpt-bento-card">
                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_exam_attendance_action', 'educore_exam_att_nonce_field' ); ?>
                    <input type="hidden" name="exam_id" value="<?php echo esc_attr( $filter_exam ); ?>">
                    <input type="hidden" name="class_name" value="<?php echo esc_attr( $filter_class ); ?>">
                    <input type="hidden" name="section_name" value="<?php echo esc_attr( $filter_section ); ?>">
                    <input type="hidden" name="subject_name" value="<?php echo esc_attr( $filter_subject ); ?>">
                    <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                    <!-- Meta Summary Bar -->
                    <div class="afdp-roster-meta-bar">
                        <div>
                            <strong style="font-size:16px; color:#006a4e;"><?php echo esc_html( $filter_subject ); ?></strong>
                            <span style="font-size:13px; color:#475569; margin-left:8px;">
                                &mdash; Class <?php echo esc_html( $filter_class ); ?> 
                                <?php echo ! empty( $filter_section ) ? '(' . esc_html( $filter_section ) . ')' : ''; ?> 
                                | Date: <?php echo esc_html( date_i18n( 'd M, Y', strtotime( $filter_date ) ) ); ?>
                            </span>
                        </div>
                        <div class="dpt-counter-cluster">
                            <span class="dpt-badge-pill dpt-badge-total" id="examAttTotalCount">Total: <?php echo count( $students_list ); ?></span>
                            <span class="dpt-badge-pill dpt-badge-present" id="examAttPresentCount">Present: 0</span>
                            <span class="dpt-badge-pill dpt-badge-absent" id="examAttAbsentCount">Absent: 0</span>
                            <span class="dpt-badge-pill dpt-badge-late" id="examAttLateCount">Late/Expelled: 0</span>
                        </div>
                    </div>

                    <!-- Bulk Automation Buttons -->
                    <div class="afdp-bulk-automation-row no-print">
                        <div style="font-size: 13px; font-weight: 700; color: #475569;">
                            <span class="dashicons dashicons-admin-tools" style="vertical-align:middle;"></span>
                            <?php esc_html_e( 'Quick Automation Tools:', 'ifsedu-sms' ); ?>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button type="button" class="dpt-bulk-btn exam-bulk-btn" data-target-status="Present">
                                <span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Mark All Present', 'ifsedu-sms' ); ?>
                            </button>
                            <button type="button" class="dpt-bulk-btn exam-bulk-btn" data-target-status="Absent">
                                <span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Mark All Absent', 'ifsedu-sms' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Roster Table -->
                    <div class="dpt-table-responsive">
                        <table class="dpt-attendance-matrix-table">
                            <thead>
                                <tr>
                                    <th style="width: 8%;"><?php esc_html_e( 'Roll', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 14%;"><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 25%;"><?php esc_html_e( 'Candidate Name', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 30%; text-align: center;"><?php esc_html_e( 'Exam Hall Status', 'ifsedu-sms' ); ?></th>
                                    <th style="width: 23%;"><?php esc_html_e( 'Invigilator Notes / Expel Remarks', 'ifsedu-sms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $students_list ) ) : foreach ( $students_list as $s ) : 
                                    $saved_status  = isset( $saved_logs[ $s->id ] ) ? $saved_logs[ $s->id ]->status : 'Present';
                                    $saved_remarks = isset( $saved_logs[ $s->id ] ) ? $saved_logs[ $s->id ]->invigilator_remarks : '';
                                    $first_letter  = mb_substr( $s->full_name, 0, 1 );
                                ?>
                                    <tr>
                                        <td><strong>#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                        <td><span class="dpt-exam-card-badge"><?php echo esc_html( strtoupper( $s->student_id ) ); ?></span></td>
                                        <td>
                                            <div class="dpt-avatar-cell">
                                                <?php if ( ! empty( $s->photo_url ) ) : ?>
                                                    <img src="<?php echo esc_url( $s->photo_url ); ?>" class="dpt-avatar-mini" alt="Avatar">
                                                <?php else : ?>
                                                    <div class="dpt-avatar-fallback-mini"><?php echo esc_html( strtoupper( $first_letter ) ); ?></div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong style="color:#0f172a;"><?php echo esc_html( $s->full_name ); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <div class="afdp-checkbox-group">
                                                <input type="radio" class="afdp-checkbox-item exam-att-radio" name="att_status[<?php echo esc_attr( $s->id ); ?>]" id="att_present_<?php echo esc_attr( $s->id ); ?>" value="Present" <?php checked( $saved_status, 'Present' ); ?>>
                                                <label class="afdp-checkbox-label" for="att_present_<?php echo esc_attr( $s->id ); ?>">
                                                    <span class="dashicons dashicons-yes" style="font-size:13px; width:13px; height:13px;"></span>
                                                    <?php esc_html_e( 'Present', 'ifsedu-sms' ); ?>
                                                </label>

                                                <input type="radio" class="afdp-checkbox-item exam-att-radio" name="att_status[<?php echo esc_attr( $s->id ); ?>]" id="att_absent_<?php echo esc_attr( $s->id ); ?>" value="Absent" <?php checked( $saved_status, 'Absent' ); ?>>
                                                <label class="afdp-checkbox-label" for="att_absent_<?php echo esc_attr( $s->id ); ?>">
                                                    <span class="dashicons dashicons-no" style="font-size:13px; width:13px; height:13px;"></span>
                                                    <?php esc_html_e( 'Absent', 'ifsedu-sms' ); ?>
                                                </label>

                                                <input type="radio" class="afdp-checkbox-item exam-att-radio" name="att_status[<?php echo esc_attr( $s->id ); ?>]" id="att_late_<?php echo esc_attr( $s->id ); ?>" value="Late" <?php checked( $saved_status, 'Late' ); ?>>
                                                <label class="afdp-checkbox-label" for="att_late_<?php echo esc_attr( $s->id ); ?>">
                                                    <span class="dashicons dashicons-warning" style="font-size:13px; width:13px; height:13px;"></span>
                                                    <?php esc_html_e( 'Late / Expelled', 'ifsedu-sms' ); ?>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" name="invigilator_remarks[<?php echo esc_attr( $s->id ); ?>]" class="dpt-remarks-input" placeholder="e.g. Expelled, 15m Late, Seat No. 4" value="<?php echo esc_attr( $saved_remarks ); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">
                                            <?php esc_html_e( 'No active students found in the selected class/section.', 'ifsedu-sms' ); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ( ! empty( $students_list ) ) : ?>
                        <div style="text-align: right; margin-top: 24px;">
                            <button type="submit" name="educore_save_exam_attendance" class="dpt-btn-submit-trigger" style="height: 44px; padding: 0 32px; font-size: 15px;">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e( 'Save Exam Hall Attendance', 'ifsedu-sms' ); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Client-Side Summary Counters & Quick Automation Script -->
            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                function updateCounters() {
                    const present = document.querySelectorAll('.exam-att-radio[value="Present"]:checked').length;
                    const absent  = document.querySelectorAll('.exam-att-radio[value="Absent"]:checked').length;
                    const late    = document.querySelectorAll('.exam-att-radio[value="Late"]:checked').length;

                    document.getElementById('examAttPresentCount').textContent = 'Present: ' + present;
                    document.getElementById('examAttAbsentCount').textContent  = 'Absent: ' + absent;
                    document.getElementById('examAttLateCount').textContent    = 'Late/Expelled: ' + late;
                }

                document.querySelectorAll('.exam-att-radio').forEach(radio => {
                    radio.addEventListener('change', updateCounters);
                });

                document.querySelectorAll('.exam-bulk-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const targetStatus = this.getAttribute('data-target-status');
                        document.querySelectorAll('.exam-att-radio[value="' + targetStatus + '"]').forEach(radio => {
                            radio.checked = true;
                        });
                        updateCounters();
                    });
                });

                updateCounters();
            });
            </script>
        <?php endif; ?>

    </div>
    <?php
}