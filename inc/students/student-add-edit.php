<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access buffer safety row
}

/**
 * Enterprise Multi-Step Student Admission & Profile Engine
 * File: student-add-edit.php
 * Target Tables: sms_students, sms_academic_units, sms_staff
 */
function educore_student_add_edit_view() {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to manage student records.', 'ifsedu-sms' ) );
    }

    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_staff    = $wpdb->prefix . 'sms_staff';

    // --------------------------------------------------------------------------
    // 0. AUTO-SCHEMA CHECK
    // --------------------------------------------------------------------------
    $check_shift = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_students}` LIKE 'shift'" );
    if ( empty( $check_shift ) ) {
        $wpdb->query( "ALTER TABLE `{$table_students}` ADD `shift` varchar(50) DEFAULT 'No Shift' NOT NULL AFTER `section_name`" );
    }

    $check_fee_date = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_students}` LIKE 'fee_start_date'" );
    if ( empty( $check_fee_date ) ) {
        $wpdb->query( "ALTER TABLE `{$table_students}` ADD `fee_start_date` date DEFAULT NULL AFTER `admission_date`" );
    }

    $check_waiver_staff = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_students}` LIKE 'waiver_staff_id'" );
    if ( empty( $check_waiver_staff ) ) {
        $wpdb->query( "ALTER TABLE `{$table_students}` ADD `waiver_staff_id` bigint(20) DEFAULT 0 NOT NULL AFTER `quota`" );
    }

    $check_waiver_pct = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_students}` LIKE 'waiver_percentage'" );
    if ( empty( $check_waiver_pct ) ) {
        $wpdb->query( "ALTER TABLE `{$table_students}` ADD `waiver_percentage` decimal(5,2) DEFAULT 0.00 NOT NULL AFTER `waiver_staff_id`" );
    }

    $is_edit    = isset( $_GET['sub'] ) && $_GET['sub'] === 'edit';
    $student_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

    $student = null;
    if ( $is_edit && $student_id > 0 ) {
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_students} WHERE id = %d", $student_id ) );
    }

    // 1. Calculate Next Auto-Sequential Student ID (e.g. GIDS-0001)
    $generated_student_id = 'GIDS-0001';
    if ( ! $is_edit ) {
        $all_existing_ids = $wpdb->get_col( "SELECT student_id FROM {$table_students} WHERE student_id LIKE 'gids-%'" );
        $max_num = 0;
        if ( ! empty( $all_existing_ids ) ) {
            foreach ( $all_existing_ids as $sid ) {
                if ( preg_match( '/^gids-(\d+)$/i', trim( $sid ), $matches ) ) {
                    $num = intval( $matches[1] );
                    if ( $num > $max_num ) {
                        $max_num = $num;
                    }
                }
            }
        }
        $next_num = $max_num + 1;
        $generated_student_id = 'GIDS-' . str_pad( $next_num, 4, '0', STR_PAD_LEFT );
    }

    // Natural Serial Sorting for Classes (1, 2, 3 ... 10) & Class-to-Section Mapping
    $raw_units = $wpdb->get_results( "SELECT class_name, section_name, dept_name FROM {$table_units} WHERE class_name != ''" );
    $class_section_map = array();
    $academic_classes  = array();

    if ( ! empty( $raw_units ) ) {
        foreach ( $raw_units as $unit ) {
            $c_name = trim( $unit->class_name );
            if ( ! isset( $class_section_map[ $c_name ] ) ) {
                $class_section_map[ $c_name ] = array();
                $academic_classes[] = $c_name;
            }
            if ( ! empty( $unit->section_name ) ) {
                $class_section_map[ $c_name ][] = trim( $unit->section_name );
            }
            if ( ! empty( $unit->dept_name ) ) {
                $class_section_map[ $c_name ][] = trim( $unit->dept_name );
            }
        }

        foreach ( $class_section_map as $c_name => $secs ) {
            $class_section_map[ $c_name ] = array_values( array_unique( array_filter( $secs ) ) );
            usort( $class_section_map[ $c_name ], 'strnatcasecmp' );
        }

        $academic_classes = array_values( array_unique( $academic_classes ) );
        usort( $academic_classes, 'strnatcasecmp' );
    }

    // Fetch all active Staff/Teachers/Officers for Waiver Reference
    $staff_members = $wpdb->get_results( "SELECT id, full_name, designation, staff_type FROM {$table_staff} WHERE status = 'Active' ORDER BY full_name ASC" );

    // --------------------------------------------------------------------------
    // FORM SUBMISSION PROCESSING
    // --------------------------------------------------------------------------
    if ( isset( $_POST['educore_save_student'] ) && isset( $_POST['educore_student_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_student_nonce'] ) ), 'save_student_action' ) ) {

        $photo_url = $student ? $student->photo_url : '';

        // Handle Photo Upload
        if ( ! empty( $_FILES['student_photo']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $uploaded_file = wp_handle_upload( $_FILES['student_photo'], array( 'test_form' => false ) );
            if ( ! isset( $uploaded_file['error'] ) && isset( $uploaded_file['url'] ) ) {
                $photo_url = esc_url_raw( $uploaded_file['url'] );
            }
        }

        $data = array(
            'student_id'         => isset( $_POST['student_id'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['student_id'] ) ) ) : '',
            'full_name'          => isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '',
            'name_bn'            => '',
            'class_name'         => isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '',
            'section_name'       => isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '',
            'shift'              => isset( $_POST['shift'] ) ? sanitize_text_field( wp_unslash( $_POST['shift'] ) ) : 'No Shift',
            'roll_no'            => isset( $_POST['roll_no'] ) ? intval( $_POST['roll_no'] ) : 0,
            'admission_date'     => isset( $_POST['admission_date'] ) ? sanitize_text_field( wp_unslash( $_POST['admission_date'] ) ) : '',
            'fee_start_date'     => ! empty( $_POST['fee_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_start_date'] ) ) : null,
            'birth_reg_no'       => isset( $_POST['birth_reg_no'] ) ? sanitize_text_field( wp_unslash( $_POST['birth_reg_no'] ) ) : '',
            'dob'                => isset( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : '',
            'birth_place'        => isset( $_POST['birth_place'] ) ? sanitize_text_field( wp_unslash( $_POST['birth_place'] ) ) : '',
            'gender'             => isset( $_POST['gender'] ) ? sanitize_text_field( wp_unslash( $_POST['gender'] ) ) : 'Male',
            'blood_group'        => isset( $_POST['blood_group'] ) ? sanitize_text_field( wp_unslash( $_POST['blood_group'] ) ) : '',
            'religion'           => isset( $_POST['religion'] ) ? sanitize_text_field( wp_unslash( $_POST['religion'] ) ) : 'Islam',
            'nationality'        => isset( $_POST['nationality'] ) ? sanitize_text_field( wp_unslash( $_POST['nationality'] ) ) : 'Bangladeshi',
            'student_email'      => isset( $_POST['student_email'] ) ? sanitize_email( wp_unslash( $_POST['student_email'] ) ) : '',
            'student_phone'      => isset( $_POST['student_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['student_phone'] ) ) : '',
            'quota'              => isset( $_POST['quota'] ) ? sanitize_text_field( wp_unslash( $_POST['quota'] ) ) : 'General',

            // Financial Waiver
            'waiver_staff_id'    => isset( $_POST['waiver_staff_id'] ) ? absint( $_POST['waiver_staff_id'] ) : 0,
            'waiver_percentage'  => isset( $_POST['waiver_percentage'] ) ? min( 100, max( 0, floatval( $_POST['waiver_percentage'] ) ) ) : 0.00,

            'father_name'        => isset( $_POST['father_name'] ) ? sanitize_text_field( wp_unslash( $_POST['father_name'] ) ) : '',
            'father_name_bn'     => '',
            'father_nid'         => isset( $_POST['father_nid'] ) ? sanitize_text_field( wp_unslash( $_POST['father_nid'] ) ) : '',
            'father_phone'       => isset( $_POST['father_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['father_phone'] ) ) : '',
            'father_profession'  => isset( $_POST['father_profession'] ) ? sanitize_text_field( wp_unslash( $_POST['father_profession'] ) ) : '',

            'mother_name'        => isset( $_POST['mother_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mother_name'] ) ) : '',
            'mother_name_bn'     => '',
            'mother_nid'         => isset( $_POST['mother_nid'] ) ? sanitize_text_field( wp_unslash( $_POST['mother_nid'] ) ) : '',
            'mother_phone'       => isset( $_POST['mother_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['mother_phone'] ) ) : '',
            'mother_profession'  => isset( $_POST['mother_profession'] ) ? sanitize_text_field( wp_unslash( $_POST['mother_profession'] ) ) : '',

            'guardian_name'      => isset( $_POST['guardian_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guardian_name'] ) ) : '',
            'guardian_phone'     => isset( $_POST['guardian_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['guardian_phone'] ) ) : '',
            'guardian_relation'  => isset( $_POST['guardian_relation'] ) ? sanitize_text_field( wp_unslash( $_POST['guardian_relation'] ) ) : '',
            'guardian_nid'       => isset( $_POST['guardian_nid'] ) ? sanitize_text_field( wp_unslash( $_POST['guardian_nid'] ) ) : '',
            'guardian_income'    => isset( $_POST['guardian_income'] ) ? sanitize_text_field( wp_unslash( $_POST['guardian_income'] ) ) : '',

            'prev_school_name'   => isset( $_POST['prev_school_name'] ) ? sanitize_text_field( wp_unslash( $_POST['prev_school_name'] ) ) : '',
            'prev_eiin'          => isset( $_POST['prev_eiin'] ) ? sanitize_text_field( wp_unslash( $_POST['prev_eiin'] ) ) : '',
            'prev_class'         => isset( $_POST['prev_class'] ) ? sanitize_text_field( wp_unslash( $_POST['prev_class'] ) ) : '',
            'prev_gpa'           => isset( $_POST['prev_gpa'] ) ? sanitize_text_field( wp_unslash( $_POST['prev_gpa'] ) ) : '',

            'address'            => isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
            'permanent_address'  => isset( $_POST['permanent_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['permanent_address'] ) ) : '',
            'residential_status' => isset( $_POST['residential_status'] ) ? sanitize_text_field( wp_unslash( $_POST['residential_status'] ) ) : 'Non-Residential',
            'co_curricular'      => isset( $_POST['co_curricular'] ) ? implode( ', ', array_map( 'sanitize_text_field', wp_unslash( $_POST['co_curricular'] ) ) ) : '',

            'photo_url'          => $photo_url,
            'status'             => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Active'
        );

        if ( $is_edit ) {
            $wpdb->update( $table_students, $data, array( 'id' => $student_id ) );
            $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list&msg=updated' );
        } else {
            $wpdb->insert( $table_students, $data );
            $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list&msg=success' );
        }

        echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_url ) . '";</script>';
        exit;
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );
    $today_date = current_time( 'Y-m-d' );
    ?>

    <style>
        .dpt-admission-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            margin: 20px 20px 30px 0;
        }

        .dpt-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .dpt-back-btn {
            height: 38px;
            padding: 0 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .dpt-back-btn:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .dpt-form-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .dpt-form-title {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 24px 0;
            padding-bottom: 16px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Stepper Navigation */
        .dpt-stepper-bar {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 32px;
            background: #f8fafc;
            padding: 8px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        @media (max-width: 992px) {
            .dpt-stepper-bar { grid-template-columns: repeat(2, 1fr); }
        }

        .dpt-step-node {
            background: transparent;
            border: none;
            padding: 10px 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            text-align: left;
            transition: all 0.2s ease;
        }

        .dpt-step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #cbd5e1;
            color: #64748b;
            font-weight: 800;
            font-size: 12.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .dpt-step-info {
            display: flex;
            flex-direction: column;
        }

        .dpt-step-title {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            line-height: 1.2;
        }

        .dpt-step-sub {
            font-size: 10.5px;
            color: #94a3b8;
            font-weight: 500;
        }

        .dpt-step-node.active {
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .dpt-step-node.active .dpt-step-circle {
            border-color: #006a4e;
            background: #006a4e;
            color: #ffffff;
        }

        .dpt-step-node.active .dpt-step-title {
            color: #0f172a;
        }

        .dpt-step-node.completed .dpt-step-circle {
            border-color: #006a4e;
            background: #ecfdf5;
            color: #006a4e;
        }

        /* Step Panels */
        .educore-step-panel {
            display: none;
            opacity: 0;
            transform: translateY(6px);
            transition: all 0.3s ease;
        }

        .educore-step-panel.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        /* Grids & Form Controls */
        .dpt-grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 22px; }
        .dpt-grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 22px; }
        .dpt-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 22px; }

        @media (max-width: 768px) {
            .dpt-grid-2 { grid-template-columns: 1fr; }
        }

        .dpt-field-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dpt-field-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            margin: 0;
        }

        .dpt-input, .dpt-select, .dpt-textarea {
            height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0 14px;
            font-size: 13.5px;
            background: #f8fafc;
            color: #0f172a;
            width: 100%;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .dpt-textarea {
            height: auto;
            padding: 10px 14px;
        }

        .dpt-input:focus, .dpt-select:focus, .dpt-textarea:focus {
            border-color: #006a4e;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
            outline: none;
        }

        .dpt-hint-text {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            margin-top: 2px;
        }

        .dpt-section-heading {
            font-size: 15px;
            font-weight: 800;
            color: #006a4e;
            margin: 24px 0 16px 0;
            padding-bottom: 8px;
            border-bottom: 1px dashed #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dpt-photo-uploader-box {
            display: flex;
            align-items: center;
            gap: 20px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .dpt-avatar-preview {
            width: 72px;
            height: 72px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid #006a4e;
            background: #e2e8f0;
            flex-shrink: 0;
        }

        .dpt-actions-footer {
            border-top: 1px solid #f1f5f9;
            padding-top: 24px;
            margin-top: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dpt-btn {
            height: 42px;
            padding: 0 24px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-prev { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .btn-prev:hover { background: #e2e8f0; color: #0f172a; }

        .btn-next { background: #006a4e; color: #ffffff; box-shadow: 0 4px 12px rgba(0, 106, 78, 0.15); }
        .btn-next:hover { background: #00523c; box-shadow: 0 6px 16px rgba(0, 106, 78, 0.25); }

        .btn-submit { background: #006a4e; color: #ffffff; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
        .btn-submit:hover { background: #059669; }
    </style>

    <div class="dpt-admission-root">

        <!-- Top Header Navigation -->
        <div class="dpt-header-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-back-btn">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Student Directory', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <div class="dpt-form-card">
            <h3 class="dpt-form-title">
                <span class="dashicons dashicons-id" style="color:#006a4e;"></span>
                <?php echo $is_edit ? esc_html__( 'Edit Student Profile Record', 'ifsedu-sms' ) : esc_html__( 'Admit Comprehensive New Student', 'ifsedu-sms' ); ?>
            </h3>

            <!-- Dynamic 5-Step Progress Bar -->
            <div class="dpt-stepper-bar" id="educoreStudentStepper">
                <button type="button" class="dpt-step-node active" data-step="1">
                    <div class="dpt-step-circle">1</div>
                    <div class="dpt-step-info">
                        <span class="dpt-step-title"><?php esc_html_e( 'Basic & Academic', 'ifsedu-sms' ); ?></span>
                        <span class="dpt-step-sub"><?php esc_html_e( 'Identity & Class', 'ifsedu-sms' ); ?></span>
                    </div>
                </button>

                <button type="button" class="dpt-step-node" data-step="2">
                    <div class="dpt-step-circle">2</div>
                    <div class="dpt-step-info">
                        <span class="dpt-step-title"><?php esc_html_e( 'Parents Info', 'ifsedu-sms' ); ?></span>
                        <span class="dpt-step-sub"><?php esc_html_e( 'Father & Mother', 'ifsedu-sms' ); ?></span>
                    </div>
                </button>

                <button type="button" class="dpt-step-node" data-step="3">
                    <div class="dpt-step-circle">3</div>
                    <div class="dpt-step-info">
                        <span class="dpt-step-title"><?php esc_html_e( 'Guardian & History', 'ifsedu-sms' ); ?></span>
                        <span class="dpt-step-sub"><?php esc_html_e( 'Emergency & Previous', 'ifsedu-sms' ); ?></span>
                    </div>
                </button>

                <button type="button" class="dpt-step-node" data-step="4">
                    <div class="dpt-step-circle">4</div>
                    <div class="dpt-step-info">
                        <span class="dpt-step-title"><?php esc_html_e( 'Financial Waiver', 'ifsedu-sms' ); ?></span>
                        <span class="dpt-step-sub"><?php esc_html_e( 'Staff Ref. & Discount %', 'ifsedu-sms' ); ?></span>
                    </div>
                </button>

                <button type="button" class="dpt-step-node" data-step="5">
                    <div class="dpt-step-circle">5</div>
                    <div class="dpt-step-info">
                        <span class="dpt-step-title"><?php esc_html_e( 'Address & Photo', 'ifsedu-sms' ); ?></span>
                        <span class="dpt-step-sub"><?php esc_html_e( 'Logistics & Submit', 'ifsedu-sms' ); ?></span>
                    </div>
                </button>
            </div>

            <!-- Form Workspace -->
            <form method="POST" action="" enctype="multipart/form-data" id="educoreStudentForm" novalidate>
                <?php wp_nonce_field( 'save_student_action', 'educore_student_nonce' ); ?>

                <!-- STEP 1: Academic & Basic Personal Profile -->
                <div class="educore-step-panel active" id="educore-step-1">

                    <div class="dpt-grid-3">
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Student Unique ID / UID', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="student_id" id="educore_student_id" class="dpt-input" style="font-weight:800; color:#006a4e; text-transform:uppercase;" value="<?php echo $student ? esc_attr( $student->student_id ) : esc_attr( $generated_student_id ); ?>" required readonly>
                            <span class="dpt-hint-text">শিক্ষার্থীর ইউনিক আইডি (স্বয়ংক্রিয়)</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Full Name (English)', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="full_name" class="dpt-input" value="<?php echo $student ? esc_attr( $student->full_name ) : ''; ?>" placeholder="e.g. Tanvir Ahmed" required>
                            <span class="dpt-hint-text">শিক্ষার্থীর পুরো নাম (ইংরেজি বড় অক্ষরে)</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Class / Grade', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select name="class_name" id="educore_class_select" class="dpt-select" required>
                                <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-sms' ); ?></option>
                                <?php foreach ( $academic_classes as $ac ) : ?>
                                    <option value="<?php echo esc_attr( $ac ); ?>" <?php selected( $student ? $student->class_name : '', $ac ); ?>><?php echo esc_html( $ac ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="dpt-hint-text">ভর্তিচ্ছু শ্রেণি নির্বাচন করুন</span>
                        </div>
                    </div>

                    <div class="dpt-grid-4">
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Section / Group', 'ifsedu-sms' ); ?></label>
                            <select name="section_name" id="educore_section_select" class="dpt-select">
                                <option value=""><?php esc_html_e( '-- Select Section --', 'ifsedu-sms' ); ?></option>
                            </select>
                            <span class="dpt-hint-text">শাখা বা বিভাগ নির্বাচন করুন</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Roll Number', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" name="roll_no" class="dpt-input" value="<?php echo $student ? esc_attr( $student->roll_no ) : ''; ?>" placeholder="e.g. 101" required>
                            <span class="dpt-hint-text">শ্রেণি রোল নম্বর প্রদান করুন</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Admission Date', 'ifsedu-sms' ); ?></label>
                            <input type="date" name="admission_date" id="admission_date_input" class="dpt-input" value="<?php echo $student ? esc_attr( $student->admission_date ) : $today_date; ?>" max="<?php echo esc_attr( $today_date ); ?>">
                            <span class="dpt-hint-text">ভর্তির তারিখ (আজকের বা পূর্বের দিন)</span>
                        </div>

                        <!-- Shift Dropdown -->
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Academic Shift', 'ifsedu-sms' ); ?></label>
                            <select name="shift" class="dpt-select">
                                <option value="No Shift" <?php selected( $student ? $student->shift : '', 'No Shift' ); ?>><?php esc_html_e( 'No Shift', 'ifsedu-sms' ); ?></option>
                                <option value="Morning Shift" <?php selected( $student ? $student->shift : '', 'Morning Shift' ); ?>><?php esc_html_e( 'Morning Shift', 'ifsedu-sms' ); ?></option>
                                <option value="Day Shift" <?php selected( $student ? $student->shift : '', 'Day Shift' ); ?>><?php esc_html_e( 'Day Shift', 'ifsedu-sms' ); ?></option>
                            </select>
                            <span class="dpt-hint-text">শিফট নির্বাচন করুন (প্রযোজ্য ক্ষেত্রে)</span>
                        </div>
                    </div>

                    <div class="dpt-grid-4">
                        <!-- Start Date for Monthly Fees -->
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Start Date for Monthly Fees', 'ifsedu-sms' ); ?></label>
                            <input type="date" name="fee_start_date" id="fee_start_date_input" class="dpt-input" value="<?php echo ( $student && ! empty( $student->fee_start_date ) ) ? esc_attr( $student->fee_start_date ) : current_time('Y-m-01'); ?>" max="<?php echo esc_attr( $today_date ); ?>">
                            <span class="dpt-hint-text">মাসিক বেতন শুরুর তারিখ (ভবিষ্যতের তারিখ নয়)</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Birth Reg. Number (17 Digits)', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="birth_reg_no" maxlength="17" class="dpt-input" value="<?php echo $student ? esc_attr( $student->birth_reg_no ) : ''; ?>" placeholder="17 ডিজিটের জন্ম নিবন্ধন নম্বর">
                            <span class="dpt-hint-text">অনলাইন জন্ম নিবন্ধন নম্বর (১৭ ডিজিট)</span>
                        </div>

                        <!-- Date of Birth -->
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Date of Birth', 'ifsedu-sms' ); ?></label>
                            <input type="date" name="dob" id="dob_input" class="dpt-input" value="<?php echo $student ? esc_attr( $student->dob ) : ''; ?>" max="<?php echo esc_attr( $today_date ); ?>">
                            <span class="dpt-hint-text">জন্ম তারিখ (আজ বা পূর্বের দিন)</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Birth District', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="birth_place" class="dpt-input" value="<?php echo $student ? esc_attr( $student->birth_place ) : ''; ?>" placeholder="e.g. Sylhet">
                            <span class="dpt-hint-text">জন্মস্থান / নিজ জেলা</span>
                        </div>
                    </div>

                    <div class="dpt-grid-4">
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Gender', 'ifsedu-sms' ); ?></label>
                            <select name="gender" class="dpt-select">
                                <option value="Male" <?php selected( $student ? $student->gender : '', 'Male' ); ?>><?php esc_html_e( 'Male', 'ifsedu-sms' ); ?></option>
                                <option value="Female" <?php selected( $student ? $student->gender : '', 'Female' ); ?>><?php esc_html_e( 'Female', 'ifsedu-sms' ); ?></option>
                                <option value="Other" <?php selected( $student ? $student->gender : '', 'Other' ); ?>><?php esc_html_e( 'Other', 'ifsedu-sms' ); ?></option>
                            </select>
                            <span class="dpt-hint-text">লিঙ্গ নির্বাচন করুন</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Blood Group', 'ifsedu-sms' ); ?></label>
                            <select name="blood_group" class="dpt-select">
                                <option value=""><?php esc_html_e( '-- Select Group --', 'ifsedu-sms' ); ?></option>
                                <?php foreach ( array('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') as $bg ) : ?>
                                    <option value="<?php echo $bg; ?>" <?php selected( $student ? $student->blood_group : '', $bg ); ?>><?php echo $bg; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="dpt-hint-text">রক্তের গ্রুপ নির্বাচন করুন</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Religion', 'ifsedu-sms' ); ?></label>
                            <select name="religion" class="dpt-select">
                                <option value="Islam" <?php selected( $student ? $student->religion : '', 'Islam' ); ?>>Islam</option>
                                <option value="Hinduism" <?php selected( $student ? $student->religion : '', 'Hinduism' ); ?>>Hinduism</option>
                                <option value="Christianity" <?php selected( $student ? $student->religion : '', 'Christianity' ); ?>>Christianity</option>
                                <option value="Buddhism" <?php selected( $student ? $student->religion : '', 'Buddhism' ); ?>>Buddhism</option>
                            </select>
                            <span class="dpt-hint-text">ধর্ম নির্বাচন করুন</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Nationality', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="nationality" class="dpt-input" value="<?php echo $student ? esc_attr( $student->nationality ) : 'Bangladeshi'; ?>">
                            <span class="dpt-hint-text">জাতীয়তা (যেমন: Bangladeshi)</span>
                        </div>
                    </div>

                    <div class="dpt-grid-3">
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Quota Category', 'ifsedu-sms' ); ?></label>
                            <select name="quota" class="dpt-select">
                                <option value="General" <?php selected( $student ? $student->quota : '', 'General' ); ?>>General</option>
                                <option value="Freedom Fighter" <?php selected( $student ? $student->quota : '', 'Freedom Fighter' ); ?>>Freedom Fighter (মুক্তিযোদ্ধা)</option>
                                <option value="Tribal" <?php selected( $student ? $student->quota : '', 'Tribal' ); ?>>Tribal (ক্ষুদ্র নৃ-গোষ্ঠী)</option>
                                <option value="Physically Challenged" <?php selected( $student ? $student->quota : '', 'Physically Challenged' ); ?>>Physically Challenged (প্রতিবন্ধী)</option>
                            </select>
                            <span class="dpt-hint-text">কোটা নির্বাচন করুন</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Student Mobile Number', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="student_phone" class="dpt-input" value="<?php echo $student ? esc_attr( $student->student_phone ) : ''; ?>" placeholder="01700000000">
                            <span class="dpt-hint-text">শিক্ষার্থীর ব্যক্তিগত মোবাইল নম্বর</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Student Email Address', 'ifsedu-sms' ); ?></label>
                            <input type="email" name="student_email" class="dpt-input" value="<?php echo $student ? esc_attr( $student->student_email ) : ''; ?>" placeholder="student@example.com">
                            <span class="dpt-hint-text">শিক্ষার্থীর ইমেইল ঠিকানা</span>
                        </div>
                    </div>

                </div>

                <!-- STEP 2: Parents Information -->
                <div class="educore-step-panel" id="educore-step-2">

                    <div class="dpt-grid-2">
                        <!-- Father's Card -->
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
                            <h4 style="margin:0 0 16px 0; font-size:15px; font-weight:800; color:#0f172a; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                                <?php esc_html_e( 'Father Information', 'ifsedu-sms' ); ?>
                            </h4>

                            <div class="dpt-field-group" style="margin-bottom:14px;">
                                <label class="dpt-field-label"><?php esc_html_e( 'Father Name (English)', 'ifsedu-sms' ); ?></label>
                                <input type="text" name="father_name" class="dpt-input" value="<?php echo $student ? esc_attr( $student->father_name ) : ''; ?>" placeholder="e.g. MD Rafiqul Islam">
                                <span class="dpt-hint-text">পিতার নাম (ইংরেজি অক্ষরে)</span>
                            </div>

                            <div class="dpt-field-group" style="margin-bottom:14px;">
                                <label class="dpt-field-label"><?php esc_html_e( 'Father NID', 'ifsedu-sms' ); ?></label>
                                <input type="text" name="father_nid" class="dpt-input" value="<?php echo $student ? esc_attr( $student->father_nid ) : ''; ?>" placeholder="জাতীয় পরিচয়পত্র নম্বর">
                                <span class="dpt-hint-text">পিতার জাতীয় পরিচয়পত্র নম্বর (১০/১৩/১৭ ডিজিট)</span>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                                <div class="dpt-field-group">
                                    <label class="dpt-field-label"><?php esc_html_e( 'Father Phone', 'ifsedu-sms' ); ?></label>
                                    <input type="text" name="father_phone" class="dpt-input" value="<?php echo $student ? esc_attr( $student->father_phone ) : ''; ?>" placeholder="01700000000">
                                    <span class="dpt-hint-text">পিতার সচল মোবাইল নম্বর</span>
                                </div>
                                <div class="dpt-field-group">
                                    <label class="dpt-field-label"><?php esc_html_e( 'Profession', 'ifsedu-sms' ); ?></label>
                                    <input type="text" name="father_profession" class="dpt-input" value="<?php echo $student ? esc_attr( $student->father_profession ) : ''; ?>" placeholder="e.g. Businessman / Teacher">
                                    <span class="dpt-hint-text">পিতার পেশা</span>
                                </div>
                            </div>
                        </div>

                        <!-- Mother's Card -->
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
                            <h4 style="margin:0 0 16px 0; font-size:15px; font-weight:800; color:#0f172a; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                                <?php esc_html_e( 'Mother Information', 'ifsedu-sms' ); ?>
                            </h4>

                            <div class="dpt-field-group" style="margin-bottom:14px;">
                                <label class="dpt-field-label"><?php esc_html_e( 'Mother Name (English)', 'ifsedu-sms' ); ?></label>
                                <input type="text" name="mother_name" class="dpt-input" value="<?php echo $student ? esc_attr( $student->mother_name ) : ''; ?>" placeholder="e.g. Nasima Begum">
                                <span class="dpt-hint-text">মাতার নাম (ইংরেজি অক্ষরে)</span>
                            </div>

                            <div class="dpt-field-group" style="margin-bottom:14px;">
                                <label class="dpt-field-label"><?php esc_html_e( 'Mother NID', 'ifsedu-sms' ); ?></label>
                                <input type="text" name="mother_nid" class="dpt-input" value="<?php echo $student ? esc_attr( $student->mother_nid ) : ''; ?>" placeholder="জাতীয় পরিচয়পত্র নম্বর">
                                <span class="dpt-hint-text">মাতার জাতীয় পরিচয়পত্র নম্বর (১০/১৩/১৭ ডিজিট)</span>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                                <div class="dpt-field-group">
                                    <label class="dpt-field-label"><?php esc_html_e( 'Mother Phone', 'ifsedu-sms' ); ?></label>
                                    <input type="text" name="mother_phone" class="dpt-input" value="<?php echo $student ? esc_attr( $student->mother_phone ) : ''; ?>" placeholder="01700000000">
                                    <span class="dpt-hint-text">মাতার সচল মোবাইল নম্বর</span>
                                </div>
                                <div class="dpt-field-group">
                                    <label class="dpt-field-label"><?php esc_html_e( 'Profession', 'ifsedu-sms' ); ?></label>
                                    <input type="text" name="mother_profession" class="dpt-input" value="<?php echo $student ? esc_attr( $student->mother_profession ) : ''; ?>" placeholder="e.g. Homemaker / Doctor">
                                    <span class="dpt-hint-text">মাতার পেশা</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- STEP 3: Guardian & Academic History -->
                <div class="educore-step-panel" id="educore-step-3">

                    <div class="dpt-section-heading">
                        <span><?php esc_html_e( 'Legal Guardian Details (SMS Notifications Target)', 'ifsedu-sms' ); ?></span>
                    </div>

                    <div class="dpt-grid-4">
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Guardian Name', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="guardian_name" class="dpt-input" value="<?php echo $student ? esc_attr( $student->guardian_name ) : ''; ?>" required>
                            <span class="dpt-hint-text">আইনসম্মত অভিভাবকের নাম</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Guardian Phone (SMS Alert Number)', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="guardian_phone" class="dpt-input" value="<?php echo $student ? esc_attr( $student->guardian_phone ) : ''; ?>" placeholder="01700000000" required>
                            <span class="dpt-hint-text">এসএমএস নোটিফিকেশনের মূল নম্বর</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Relation with Guardian', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="guardian_relation" class="dpt-input" value="<?php echo $student ? esc_attr( $student->guardian_relation ) : 'Father'; ?>">
                            <span class="dpt-hint-text">অভিভাবকের সাথে সম্পর্ক (যেমন: Father/Uncle)</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Guardian NID / Annual Income', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="guardian_nid" class="dpt-input" value="<?php echo $student ? esc_attr( $student->guardian_nid ) : ''; ?>" placeholder="NID or Annual Income">
                            <span class="dpt-hint-text">অভিভাবকের এনআইডি অথবা বার্ষিক আয়</span>
                        </div>
                    </div>

                    <div class="dpt-section-heading">
                        <span><?php esc_html_e( 'Previous Academic Background', 'ifsedu-sms' ); ?></span>
                    </div>

                    <div class="dpt-grid-4">
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Previous School Name', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="prev_school_name" class="dpt-input" value="<?php echo $student ? esc_attr( $student->prev_school_name ) : ''; ?>">
                            <span class="dpt-hint-text">পূর্ববর্তী শিক্ষাপ্রতিষ্ঠানের নাম</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Previous Institute EIIN', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="prev_eiin" class="dpt-input" value="<?php echo $student ? esc_attr( $student->prev_eiin ) : ''; ?>">
                            <span class="dpt-hint-text">পূর্ববর্তী প্রতিষ্ঠানের EIIN নম্বর</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Last Passed Class', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="prev_class" class="dpt-input" value="<?php echo $student ? esc_attr( $student->prev_class ) : ''; ?>" placeholder="e.g. Class 5">
                            <span class="dpt-hint-text">সর্বশেষ উত্তীর্ণ শ্রেণি</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Obtained GPA / Marks', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="prev_gpa" class="dpt-input" value="<?php echo $student ? esc_attr( $student->prev_gpa ) : ''; ?>" placeholder="e.g. 5.00">
                            <span class="dpt-hint-text">পূর্ববর্তী পরীক্ষায় প্রাপ্ত জিপিএ/নম্বর</span>
                        </div>
                    </div>

                </div>

                <!-- STEP 4: Financial Waiver -->
                <div class="educore-step-panel" id="educore-step-4">
                    <div class="dpt-section-heading">
                        <span><?php esc_html_e( 'Permanent Financial Waiver & Reference Scheme', 'ifsedu-sms' ); ?></span>
                    </div>

                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:20px; margin-bottom:24px;">
                        <p style="margin:0 0 16px 0; color:#166534; font-size:13.5px; font-weight:600;">
                            <?php esc_html_e( 'Select any institutional faculty member (Teacher, Staff, or Officer) to link this student and assign an ongoing percentage waiver for monthly tuition fees.', 'ifsedu-sms' ); ?>
                        </p>

                        <div class="dpt-grid-2">
                            <!-- Staff Reference Dropdown -->
                            <div class="dpt-field-group">
                                <label class="dpt-field-label"><?php esc_html_e( 'Referred Teacher / Staff / Officer', 'ifsedu-sms' ); ?></label>
                                <select name="waiver_staff_id" class="dpt-select">
                                    <option value="0"><?php esc_html_e( '-- No Staff Reference / General Student --', 'ifsedu-sms' ); ?></option>
                                    <?php foreach ( $staff_members as $sm ) : ?>
                                        <option value="<?php echo intval( $sm->id ); ?>" <?php selected( $student ? $student->waiver_staff_id : 0, $sm->id ); ?>>
                                            <?php echo esc_html( $sm->full_name . ' (' . $sm->designation . ' - ' . $sm->staff_type . ')' ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="dpt-hint-text">সুপারিশকারী শিক্ষক বা কর্মকর্তা নির্বাচন করুন</span>
                            </div>

                            <!-- Discount Percentage Input -->
                            <div class="dpt-field-group">
                                <label class="dpt-field-label"><?php esc_html_e( 'All-Time Waiver / Discount Percentage (%)', 'ifsedu-sms' ); ?></label>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <input type="number" step="0.01" min="0" max="100" name="waiver_percentage" class="dpt-input" style="font-weight:800; font-size:15px; color:#006a4e;" value="<?php echo $student ? esc_attr( $student->waiver_percentage ) : '0.00'; ?>" placeholder="0.00">
                                    <span style="font-size:18px; font-weight:800; color:#006a4e;">%</span>
                                </div>
                                <span class="dpt-hint-text">মাসিক বেতনের ওপর স্থায়ী ছাড়ের শতকরা হার (যেমন: 50)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 5: Address, Photo & Logistics -->
                <div class="educore-step-panel" id="educore-step-5">

                    <div class="dpt-section-heading">
                        <span><?php esc_html_e( 'Address Details', 'ifsedu-sms' ); ?></span>
                        <button type="button" id="btnCopyAddress" style="background:none; border:none; color:#006a4e; font-weight:700; font-size:12px; cursor:pointer;">
                            <span class="dashicons dashicons-admin-page" style="vertical-align:middle;"></span> <?php esc_html_e( 'Same as Present Address', 'ifsedu-sms' ); ?>
                        </button>
                    </div>

                    <div class="dpt-grid-2">
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Present Address (বর্তমান ঠিকানা)', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                            <textarea name="address" id="educore_present_address" rows="3" class="dpt-textarea" required><?php echo $student ? esc_textarea( $student->address ) : ''; ?></textarea>
                            <span class="dpt-hint-text">বর্তমান বসবাসের পূর্ণাঙ্গ ঠিকানা লিখুন</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Permanent Address (স্থায়ী ঠিকানা)', 'ifsedu-sms' ); ?></label>
                            <textarea name="permanent_address" id="educore_permanent_address" rows="3" class="dpt-textarea"><?php echo $student ? esc_textarea( $student->permanent_address ) : ''; ?></textarea>
                            <span class="dpt-hint-text">স্থায়ী ঠিকানা (গ্রাম, ডাকঘর, উপজেলা, জেলা)</span>
                        </div>
                    </div>

                    <div class="dpt-section-heading">
                        <span><?php esc_html_e( 'Logistics & Account Setup', 'ifsedu-sms' ); ?></span>
                    </div>

                    <!-- Photo Upload Box -->
                    <div class="dpt-photo-uploader-box">
                        <img src="<?php echo ( $student && ! empty( $student->photo_url ) ) ? esc_url( $student->photo_url ) : 'https://via.placeholder.com/150?text=No+Photo'; ?>" id="studentPhotoPreview" class="dpt-avatar-preview" alt="Student Preview">

                        <div class="dpt-field-group" style="flex:1;">
                            <label class="dpt-field-label"><?php esc_html_e( 'Upload Student Portrait Photo', 'ifsedu-sms' ); ?></label>
                            <input type="file" name="student_photo" id="studentPhotoInput" accept="image/*" class="dpt-input" style="padding-top:8px;">
                            <span class="dpt-hint-text">শিক্ষার্থীর পাসপোর্ট সাইজের ছবি আপলোড করুন (JPG, PNG)</span>
                        </div>
                    </div>

                    <div class="dpt-grid-3">
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Residential Status', 'ifsedu-sms' ); ?></label>
                            <select name="residential_status" class="dpt-select">
                                <option value="Non-Residential" <?php selected( $student ? $student->residential_status : '', 'Non-Residential' ); ?>>Non-Residential (অনাবাসিক)</option>
                                <option value="Residential (School Hostel)" <?php selected( $student ? $student->residential_status : '', 'Residential (School Hostel)' ); ?>>Residential (School Hostel)</option>
                                <option value="Mess / Private Care" <?php selected( $student ? $student->residential_status : '', 'Mess / Private Care' ); ?>>Mess / Private Care</option>
                            </select>
                            <span class="dpt-hint-text">আবাসিক অবস্থা নির্বাচন করুন</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Co-Curricular Activities', 'ifsedu-sms' ); ?></label>
                            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:8px;">
                                <?php
                                $activities = array('Scout', 'BNCC', 'Red Crescent', 'Sports Club', 'Cultural Club');
                                $current_acts = $student ? explode(', ', $student->co_curricular) : array();
                                foreach ( $activities as $act ) : $chk = in_array($act, $current_acts) ? 'checked' : ''; ?>
                                    <label style="font-size:12px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:4px;">
                                        <input type="checkbox" name="co_curricular[]" value="<?php echo esc_attr( $act ); ?>" <?php echo $chk; ?>> <?php echo esc_html( $act ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <span class="dpt-hint-text">সহশিক্ষা কার্যক্রমসমূহ নির্বাচন করুন</span>
                        </div>

                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Account Status', 'ifsedu-sms' ); ?></label>
                            <select name="status" class="dpt-select">
                                <option value="Active" <?php selected( $student ? $student->status : '', 'Active' ); ?>>Active</option>
                                <option value="Inactive" <?php selected( $student ? $student->status : '', 'Inactive' ); ?>>Inactive</option>
                            </select>
                            <span class="dpt-hint-text">শিক্ষার্থীর অ্যাকাউন্টের বর্তমান স্ট্যাটাস</span>
                        </div>
                    </div>

                </div>

                <!-- Actions Footer Bar -->
                <div class="dpt-actions-footer">
                    <button type="button" class="dpt-btn btn-prev" id="btnPrevStep" style="visibility:hidden;">
                        <span class="dashicons dashicons-arrow-left-alt2"></span> <?php esc_html_e( 'Previous', 'ifsedu-sms' ); ?>
                    </button>

                    <div>
                        <button type="button" class="dpt-btn btn-next" id="btnNextStep">
                            <?php esc_html_e( 'Next Step', 'ifsedu-sms' ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>

                        <button type="submit" name="educore_save_student" id="btnSubmitForm" class="dpt-btn btn-submit" style="display:none;">
                            <span class="dashicons dashicons-saved"></span> <?php echo $is_edit ? esc_html__( 'Update Record', 'ifsedu-sms' ) : esc_html__( 'Finalize Admission', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <!-- Client-Side Navigation, Copy & Live Image Preview Engine -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        let currentStep = 1;
        const totalSteps = 5;

        const form = document.getElementById('educoreStudentForm');
        const btnNext = document.getElementById('btnNextStep');
        const btnPrev = document.getElementById('btnPrevStep');
        const btnSubmit = document.getElementById('btnSubmitForm');
        const stepNodes = document.querySelectorAll('#educoreStudentStepper .dpt-step-node');

        // Dynamic Class-to-Section Auto-Chaining Engine
        const classSectionMap = <?php echo wp_json_encode( $class_section_map ); ?>;
        const classSelect = document.getElementById('educore_class_select');
        const sectionSelect = document.getElementById('educore_section_select');
        const currentSavedSection = "<?php echo $student ? esc_js( $student->section_name ) : ''; ?>";

        function updateSections(className, selectedSec = '') {
            if (!sectionSelect) return;
            sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Select Section --', 'ifsedu-sms' ) ); ?></option>';

            if (className && classSectionMap[className] && classSectionMap[className].length > 0) {
                classSectionMap[className].forEach(function(sec) {
                    const opt = document.createElement('option');
                    opt.value = sec;
                    opt.textContent = sec;
                    if (sec === selectedSec) {
                        opt.selected = true;
                    }
                    sectionSelect.appendChild(opt);
                });
            }
        }

        if (classSelect) {
            classSelect.addEventListener('change', function() {
                updateSections(this.value, '');
            });

            if (classSelect.value) {
                updateSections(classSelect.value, currentSavedSection);
            }
        }

        // Strict Date Constraint Validation (No Next Day / Future Date)
        const todayStr = "<?php echo esc_js( $today_date ); ?>";
        const dateFieldsToLock = ['dob_input', 'fee_start_date_input', 'admission_date_input'];
        
        dateFieldsToLock.forEach(function(fieldId) {
            const el = document.getElementById(fieldId);
            if (el) {
                el.setAttribute('max', todayStr);
                el.addEventListener('change', function() {
                    if (this.value && this.value > todayStr) {
                        alert('ভবিষ্যতের কোনো তারিখ নির্বাচন করা যাবে না। আজকের বা পূর্বের তারিখ নির্বাচন করুন।');
                        this.value = todayStr;
                    }
                });
            }
        });

        // 1. Photo Live Preview
        const photoInput = document.getElementById('studentPhotoInput');
        const photoPreview = document.getElementById('studentPhotoPreview');
        if (photoInput && photoPreview) {
            photoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        photoPreview.src = evt.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });
        }

        // 2. Same as Present Address Cloner
        const btnCopy = document.getElementById('btnCopyAddress');
        if (btnCopy) {
            btnCopy.addEventListener('click', function() {
                const present = document.getElementById('educore_present_address').value;
                document.getElementById('educore_permanent_address').value = present;
            });
        }

        // 3. View Switcher Logic
        function renderStep() {
            document.querySelectorAll('.educore-step-panel').forEach(panel => panel.classList.remove('active'));
            document.getElementById('educore-step-' + currentStep).classList.add('active');

            stepNodes.forEach(node => {
                const stepNum = parseInt(node.getAttribute('data-step'));
                node.classList.remove('active', 'completed');
                if (stepNum === currentStep) {
                    node.classList.add('active');
                } else if (stepNum < currentStep) {
                    node.classList.add('completed');
                }
            });

            btnPrev.style.visibility = (currentStep === 1) ? 'hidden' : 'visible';

            if (currentStep === totalSteps) {
                btnNext.style.display = 'none';
                btnSubmit.style.display = 'inline-flex';
            } else {
                btnNext.style.display = 'inline-flex';
                btnSubmit.style.display = 'none';
            }
        }

        // 4. Validation Check
        function validateStep(stepNumber) {
            const activePanel = document.getElementById('educore-step-' + stepNumber);
            const requiredFields = activePanel.querySelectorAll('[required]');
            let valid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.style.borderColor = '#ef4444';
                    field.addEventListener('input', function tmp() {
                        if (field.value.trim()) {
                            field.style.borderColor = '#cbd5e1';
                            field.removeEventListener('input', tmp);
                        }
                    });
                }
            });

            // Re-check date constraint on validation
            const dobEl = document.getElementById('dob_input');
            if (dobEl && dobEl.value && dobEl.value > todayStr) {
                valid = false;
                dobEl.style.borderColor = '#ef4444';
                alert('জন্ম তারিখ আজকের দিনের চেয়ে বেশি (ভবিষ্যত) হতে পারবে না।');
            }

            if (!valid) {
                alert('<?php echo esc_js( __( 'Please complete all required (*) fields in this step before proceeding.', 'ifsedu-sms' ) ); ?>');
            }
            return valid;
        }

        btnNext.addEventListener('click', function() {
            if (validateStep(currentStep) && currentStep < totalSteps) {
                currentStep++;
                renderStep();
            }
        });

        btnPrev.addEventListener('click', function() {
            if (currentStep > 1) {
                currentStep--;
                renderStep();
            }
        });

        stepNodes.forEach(node => {
            node.addEventListener('click', function() {
                const target = parseInt(this.getAttribute('data-step'));
                if (target < currentStep || validateStep(currentStep)) {
                    currentStep = target;
                    renderStep();
                }
            });
        });

        // 5. Form Submission Validation
        form.addEventListener('submit', function(e) {
            for (let i = 1; i <= totalSteps; i++) {
                if (!validateStep(i)) {
                    e.preventDefault();
                    currentStep = i;
                    renderStep();
                    return false;
                }
            }
        });
    });
    </script>
    <?php
}