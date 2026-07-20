<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access buffer safety row
}

/**
 * Multi-Step Student Admission & Modification View Engine
 * Database Target: sms_students, sms_academic_units
 */
function educore_student_add_edit_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    $is_edit    = isset( $_GET['sub'] ) && $_GET['sub'] === 'edit';
    $student_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    $student = null;
    if ( $is_edit && $student_id > 0 ) {
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_students} WHERE id = %d", $student_id ) );
    }

    // Fetch configured Class and Section options dynamically
    $academic_classes  = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} ORDER BY class_name ASC" );
    $academic_sections = $wpdb->get_results( "SELECT DISTINCT section_name, dept_name FROM {$table_units} WHERE section_name != '' OR dept_name != ''" );

    // Build unified array for Sections / Groups
    $sections_list = array();
    if ( ! empty( $academic_sections ) ) {
        foreach ( $academic_sections as $sec ) {
            if ( ! empty( $sec->section_name ) ) {
                $sections_list[] = $sec->section_name;
            }
            if ( ! empty( $sec->dept_name ) ) {
                $sections_list[] = $sec->dept_name;
            }
        }
        $sections_list = array_unique( $sections_list );
    }

    // Handle Form Submission
    if ( isset( $_POST['educore_save_student'] ) && wp_verify_nonce( $_POST['educore_student_nonce'], 'save_student_action' ) ) {
        
        $photo_url = $student ? $student->photo_url : '';

        // File Upload Processing
        if ( ! empty( $_FILES['student_photo']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $uploaded_file = wp_handle_upload( $_FILES['student_photo'], array( 'test_form' => false ) );
            if ( isset( $uploaded_file['error'] ) ) {
                echo '<div class="notice notice-error"><p>Photo Upload Error: ' . esc_html( $uploaded_file['error'] ) . '</p></div>';
            } else {
                $photo_url = $uploaded_file['url'];
            }
        }

        $data = array(
            'student_id'          => sanitize_text_field( $_POST['student_id'] ),
            'full_name'           => sanitize_text_field( $_POST['full_name'] ),
            'name_bn'             => sanitize_text_field( $_POST['name_bn'] ),
            'class_name'          => sanitize_text_field( $_POST['class_name'] ),
            'section_name'        => sanitize_text_field( $_POST['section_name'] ),
            'roll_no'             => intval( $_POST['roll_no'] ),
            'admission_date'      => sanitize_text_field( $_POST['admission_date'] ),
            'birth_reg_no'        => sanitize_text_field( $_POST['birth_reg_no'] ),
            'dob'                 => sanitize_text_field( $_POST['dob'] ),
            'birth_place'         => sanitize_text_field( $_POST['birth_place'] ),
            'gender'              => sanitize_text_field( $_POST['gender'] ),
            'blood_group'         => sanitize_text_field( $_POST['blood_group'] ),
            'religion'            => sanitize_text_field( $_POST['religion'] ),
            'nationality'         => sanitize_text_field( $_POST['nationality'] ),
            'student_email'       => sanitize_email( $_POST['student_email'] ),
            'student_phone'       => sanitize_text_field( $_POST['student_phone'] ),
            'quota'               => sanitize_text_field( $_POST['quota'] ),
            
            'father_name'         => sanitize_text_field( $_POST['father_name'] ),
            'father_name_bn'      => sanitize_text_field( $_POST['father_name_bn'] ),
            'father_nid'          => sanitize_text_field( $_POST['father_nid'] ),
            'father_phone'        => sanitize_text_field( $_POST['father_phone'] ),
            'father_profession'   => sanitize_text_field( $_POST['father_profession'] ),
            
            'mother_name'         => sanitize_text_field( $_POST['mother_name'] ),
            'mother_name_bn'      => sanitize_text_field( $_POST['mother_name_bn'] ),
            'mother_nid'          => sanitize_text_field( $_POST['mother_nid'] ),
            'mother_phone'        => sanitize_text_field( $_POST['mother_phone'] ),
            'mother_profession'   => sanitize_text_field( $_POST['mother_profession'] ),
            
            'guardian_name'       => sanitize_text_field( $_POST['guardian_name'] ),
            'guardian_phone'      => sanitize_text_field( $_POST['guardian_phone'] ),
            'guardian_relation'   => sanitize_text_field( $_POST['guardian_relation'] ),
            'guardian_nid'        => sanitize_text_field( $_POST['guardian_nid'] ),
            'guardian_income'     => sanitize_text_field( $_POST['guardian_income'] ),
            
            'prev_school_name'    => sanitize_text_field( $_POST['prev_school_name'] ),
            'prev_eiin'           => sanitize_text_field( $_POST['prev_eiin'] ),
            'prev_class'          => sanitize_text_field( $_POST['prev_class'] ),
            'prev_gpa'            => sanitize_text_field( $_POST['prev_gpa'] ),
            
            'address'             => sanitize_textarea_field( $_POST['address'] ),
            'permanent_address'   => sanitize_textarea_field( $_POST['permanent_address'] ),
            'residential_status'  => sanitize_text_field( $_POST['residential_status'] ),
            'co_curricular'       => isset( $_POST['co_curricular'] ) ? implode( ', ', array_map( 'sanitize_text_field', $_POST['co_curricular'] ) ) : '',
            
            'photo_url'           => $photo_url,
            'status'              => sanitize_text_field( $_POST['status'] )
        );

        if ( $is_edit ) {
            $wpdb->update( $table_students, $data, array( 'id' => $student_id ) );
            if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                IFSEdu_School_Management_System::log_activity( "Updated student record: " . $data['full_name'] );
            }
            $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list&status=updated' );
            echo '<script type="text/javascript">window.location.href="' . esc_url( $redirect_url ) . '";</script>';
            exit;
        } else {
            $wpdb->insert( $table_students, $data );
            if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                IFSEdu_School_Management_System::log_activity( "Created new student record: " . $data['full_name'] );
            }
            $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list&status=success' );
            echo '<script type="text/javascript">window.location.href="' . esc_url( $redirect_url ) . '";</script>';
            exit;
        }
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );
    ?>

    <style>
        .afdp-admission-container { margin-top: 20px; }
        .educore-step-content { display: none; }
        .educore-step-content.active { display: block; }
        .dnt-step-tabs { border-bottom: 1px solid #e2e8f0; margin-bottom: 24px; display: flex; gap: 8px; }
        .dnt-step-tabs .nav-link { 
            color: #64748b; 
            font-weight: 600; 
            border: none; 
            border-bottom: 2px solid transparent; 
            background: transparent; 
            padding: 12px 16px; 
            cursor: pointer; 
            font-size: 14px; 
            transition: all 0.2s ease;
        }
        .dnt-step-tabs .nav-link.active { color: #006a4e !important; border-bottom: 2px solid #006a4e !important; font-weight: 700; }
        .dnt-step-tabs .nav-link.completed { color: #16a34a; }
        .form-step-actions { border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 30px; display: flex; justify-content: space-between; }
        .afdp-form-card { background: #ffffff; padding: 28px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
    </style>

    <div class="afdp-admission-container">
        <div class="mb-3">
            <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-sm" style="border-radius: 6px; font-weight: 600; text-decoration: none; padding: 8px 14px; border: 1px solid #cbd5e1; color: #475569; background: #fff; display: inline-flex; align-items: center; gap: 6px;">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span> Back to Directory
            </a>
        </div>

        <div class="afdp-form-card">
            <h3 style="margin: 0 0 20px 0; font-size: 20px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
                <?php echo $is_edit ? 'Edit Student Profile Matrix' : 'Admit Comprehensive New Student'; ?>
            </h3>
            
            <!-- Tab Indicators -->
            <div class="dnt-step-tabs" id="educoreStudentTabs">
                <button type="button" class="nav-link active" id="step-1-tab" data-step="1">1. Basic & Academic</button>
                <button type="button" class="nav-link" id="step-2-tab" data-step="2">2. Parents Details</button>
                <button type="button" class="nav-link" id="step-3-tab" data-step="3">3. Guardian & History</button>
                <button type="button" class="nav-link" id="step-4-tab" data-step="4">4. Logistics & Address</button>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" id="educoreStudentForm">
                <?php wp_nonce_field( 'save_student_action', 'educore_student_nonce' ); ?>
                
                <?php if ( $is_edit && ! empty( $student->photo_url ) ) : ?>
                    <div class="mb-4" style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 14px;">Current Active Photo</label>
                        <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="Student Photo" style="width: 90px; height: 90px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0;">
                    </div>
                <?php endif; ?>

                <!-- STEP 1: Academic & Basic Info -->
                <div class="educore-step-content active" id="educore-step-1">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Student ID (Unique/UID) <span style="color:#dc2626;">*</span></label>
                            <div style="display: flex; gap: 4px;">
                                <input type="text" name="student_id" id="educore_student_id" class="regular-text" style="flex:1; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->student_id ) : ''; ?>" required>
                                <?php if ( ! $is_edit ) : ?>
                                    <button type="button" class="button" id="btnAutoGenerateID" style="padding: 0 12px; height:auto; border-radius:6px;">Auto</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Full Name (English) <span style="color:#dc2626;">*</span></label>
                            <input type="text" name="full_name" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->full_name ) : ''; ?>" required>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">শিক্ষার্থীর নাম (বাংলায়)</label>
                            <input type="text" name="name_bn" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->name_bn ) : ''; ?>">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Class / Academic Year <span style="color:#dc2626;">*</span></label>
                            <select name="class_name" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; height: 38px;" required>
                                <option value="">-- Select Class --</option>
                                <?php if ( ! empty( $academic_classes ) ) : foreach ( $academic_classes as $ac ) : ?>
                                    <option value="<?php echo esc_attr( $ac ); ?>" <?php selected( $student ? $student->class_name : '', $ac ); ?>>
                                        <?php echo esc_html( $ac ); ?>
                                    </option>
                                <?php endforeach; else : ?>
                                    <?php if ( $student && ! empty( $student->class_name ) ) : ?>
                                        <option value="<?php echo esc_attr( $student->class_name ); ?>" selected><?php echo esc_html( $student->class_name ); ?></option>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Section / Group</label>
                            <select name="section_name" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; height: 38px;">
                                <option value="">-- Select Section / Group --</option>
                                <?php if ( ! empty( $sections_list ) ) : foreach ( $sections_list as $sec ) : ?>
                                    <option value="<?php echo esc_attr( $sec ); ?>" <?php selected( $student ? $student->section_name : '', $sec ); ?>>
                                        <?php echo esc_html( $sec ); ?>
                                    </option>
                                <?php endforeach; else : ?>
                                    <?php if ( $student && ! empty( $student->section_name ) ) : ?>
                                        <option value="<?php echo esc_attr( $student->section_name ); ?>" selected><?php echo esc_html( $student->section_name ); ?></option>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Roll Number <span style="color:#dc2626;">*</span></label>
                            <input type="number" name="roll_no" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->roll_no ) : ''; ?>" required>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Admission Date</label>
                            <input type="date" name="admission_date" style="width:100%; padding:7px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->admission_date ) : date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Birth Registration No</label>
                            <input type="text" name="birth_reg_no" maxlength="17" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->birth_reg_no ) : ''; ?>">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Date of Birth</label>
                            <input type="date" name="dob" style="width:100%; padding:7px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->dob ) : ''; ?>">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Birth District</label>
                            <input type="text" name="birth_place" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" placeholder="e.g., Sylhet" value="<?php echo $student ? esc_attr( $student->birth_place ) : ''; ?>">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Gender</label>
                            <select name="gender" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; height: 38px;">
                                <option value="Male" <?php selected( $student ? $student->gender : '', 'Male' ); ?>>Male</option>
                                <option value="Female" <?php selected( $student ? $student->gender : '', 'Female' ); ?>>Female</option>
                                <option value="Other" <?php selected( $student ? $student->gender : '', 'Other' ); ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Blood Group</label>
                            <select name="blood_group" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; height: 38px;">
                                <option value="">Select Blood Group</option>
                                <?php
                                $blood_groups = array('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-');
                                foreach ($blood_groups as $bg) {
                                    echo '<option value="' . esc_attr($bg) . '" ' . selected($student ? $student->blood_group : '', $bg, false) . '>' . esc_html($bg) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Religion</label>
                            <select name="religion" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; height: 38px;">
                                <option value="Islam" <?php selected( $student ? $student->religion : '', 'Islam' ); ?>>Islam</option>
                                <option value="Hinduism" <?php selected( $student ? $student->religion : '', 'Hinduism' ); ?>>Hinduism</option>
                                <option value="Christianity" <?php selected( $student ? $student->religion : '', 'Christianity' ); ?>>Christianity</option>
                                <option value="Buddhism" <?php selected( $student ? $student->religion : '', 'Buddhism' ); ?>>Buddhism</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Nationality</label>
                            <input type="text" name="nationality" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->nationality ) : 'Bangladeshi'; ?>">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Quota</label>
                            <select name="quota" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; height: 38px;">
                                <option value="General" <?php selected( $student ? $student->quota : '', 'General' ); ?>>General</option>
                                <option value="Freedom Fighter" <?php selected( $student ? $student->quota : '', 'Freedom Fighter' ); ?>>Freedom Fighter (মুক্তিযোদ্ধা)</option>
                                <option value="Tribal" <?php selected( $student ? $student->quota : '', 'Tribal' ); ?>>Tribal (ক্ষুদ্র নৃ-গোষ্ঠী)</option>
                                <option value="Physically Challenged" <?php selected( $student ? $student->quota : '', 'Physically Challenged' ); ?>>Physically Challenged (প্রতিবন্ধী)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Student Personal Mobile</label>
                            <input type="text" name="student_phone" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->student_phone ) : ''; ?>">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155; font-size:14px;">Student Email</label>
                            <input type="email" name="student_email" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->student_email ) : ''; ?>">
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Parents Information -->
                <div class="educore-step-content" id="educore-step-2">
                    <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                        <!-- Father Details -->
                        <div style="flex: 1; min-width: 280px; border-right: 1px solid #e2e8f0; padding-right: 20px;">
                            <h4 style="margin: 0 0 16px 0; font-size: 15px; color: #475569; font-weight: 700; border-bottom: 2px solid #f1f5f9; padding-bottom: 6px;">Father's Details</h4>
                            <div style="margin-bottom: 12px;">
                                <label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">Father's Name (English)</label>
                                <input type="text" name="father_name" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->father_name ) : ''; ?>">
                            </div>
                            <div style="margin-bottom: 12px;">
                                <label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">পিতার নাম (বাংলায়)</label>
                                <input type="text" name="father_name_bn" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->father_name_bn ) : ''; ?>">
                            </div>
                            <div style="margin-bottom: 12px;">
                                <label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">Father's NID</label>
                                <input type="text" name="father_nid" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->father_nid ) : ''; ?>">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">Father's Phone</label>
                                    <input type="text" name="father_phone" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->father_phone ) : ''; ?>">
                                </div>
                                <div>
                                    <label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">Profession</label>
                                    <input type="text" name="father_profession" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->father_profession ) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Mother Details -->
                        <div style="flex: 1; min-width: 280px;">
                            <h4 style="margin: 0 0 16px 0; font-size: 15px; color: #475569; font-weight: 700; border-bottom: 2px solid #f1f5f9; padding-bottom: 6px;">Mother's Details</h4>
                            <div style="margin-bottom: 12px;">
                                <label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">Mother's Name (English)</label>
                                <input type="text" name="mother_name" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->mother_name ) : ''; ?>">
                            </div>
                            <div style="margin-bottom: 12px;">
                                <label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">মাতার নাম (বাংলায়)</label>
                                <input type="text" name="mother_name_bn" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->mother_name_bn ) : ''; ?>">
                            </div>
                            <div style="margin-bottom: 12px;">
                                <label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">Mother's NID</label>
                                <input type="text" name="mother_nid" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->mother_nid ) : ''; ?>">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">Mother's Phone</label>
                                    <input type="text" name="mother_phone" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->mother_phone ) : ''; ?>">
                                </div>
                                <div>
                                    <label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">Profession</label>
                                    <input type="text" name="mother_profession" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->mother_profession ) : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: Legal Guardian & Academic History -->
                <div class="educore-step-content" id="educore-step-3">
                    <h4 style="margin: 0 0 14px 0; font-size: 15px; color: #006a4e; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px;">Legal Guardian Configurations</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 16px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px;">Guardian Name <span style="color:#dc2626;">*</span></label>
                            <input type="text" name="guardian_name" id="educore_guardian_name" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->guardian_name ) : ''; ?>" required>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px;">Relation with Guardian</label>
                            <input type="text" name="guardian_relation" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->guardian_relation ) : ''; ?>">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px;">Guardian NID</label>
                            <input type="text" name="guardian_nid" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->guardian_nid ) : ''; ?>">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px;">Guardian Annual Income (BDT)</label>
                            <input type="number" name="guardian_income" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" placeholder="e.g., 180000" value="<?php echo $student ? esc_attr( $student->guardian_income ) : ''; ?>">
                        </div>
                    </div>
                    <div style="margin-bottom: 24px;">
                        <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px;">Guardian Contact Mobile (For SMS Alerts) <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="guardian_phone" id="educore_guardian_phone" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->guardian_phone ) : ''; ?>" required>
                    </div>

                    <h4 style="margin: 20px 0 14px 0; font-size: 15px; color: #006a4e; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px;">Previous Academic History</h4>
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; font-size:13px;">Previous School Name</label>
                            <input type="text" name="prev_school_name" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->prev_school_name ) : ''; ?>">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; font-size:13px;">Institute EIIN</label>
                            <input type="text" name="prev_eiin" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" value="<?php echo $student ? esc_attr( $student->prev_eiin ) : ''; ?>">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; font-size:13px;">Passed Class</label>
                            <input type="text" name="prev_class" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" placeholder="e.g., JSC, Class 5" value="<?php echo $student ? esc_attr( $student->prev_class ) : ''; ?>">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; font-size:13px;">Obtained GPA</label>
                            <input type="text" name="prev_gpa" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" placeholder="e.g., 5.00" value="<?php echo $student ? esc_attr( $student->prev_gpa ) : ''; ?>">
                        </div>
                    </div>
                </div>

                <!-- STEP 4: Logistics, Address & Settings -->
                <div class="educore-step-content" id="educore-step-4">
                    <h4 style="margin: 0 0 14px 0; font-size: 15px; color: #006a4e; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px;">Address Details</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px;">Present Address (বর্তমান ঠিকানা) <span style="color:#dc2626;">*</span></label>
                            <textarea name="address" id="educore_present_address" rows="3" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" placeholder="Vill/Road, Post Office, Upazila, District" required><?php echo $student ? esc_textarea( $student->address ) : ''; ?></textarea>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px;">Permanent Address (স্থায়ী ঠিকানা)</label>
                            <textarea name="permanent_address" rows="3" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" placeholder="Vill/Road, Post Office, Upazila, District"><?php echo $student ? esc_textarea( $student->permanent_address ) : ''; ?></textarea>
                        </div>
                    </div>

                    <h4 style="margin: 20px 0 14px 0; font-size: 15px; color: #006a4e; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px;">Logistics & Settings Matrix</h4>
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px;">Residential Status</label>
                            <select name="residential_status" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; height: 38px;">
                                <option value="Non-Residential" <?php selected( $student ? $student->residential_status : '', 'Non-Residential' ); ?>>Non-Residential (অনাবাসিক)</option>
                                <option value="Residential (School Hostel)" <?php selected( $student ? $student->residential_status : '', 'Residential (School Hostel)' ); ?>>Residential (School Hostel)</option>
                                <option value="Mess / Private Care" <?php selected( $student ? $student->residential_status : '', 'Mess / Private Care' ); ?>>Mess / Private Care</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:8px; font-size:14px;">Co-Curricular Activities (সহ-শিক্ষা কার্যক্রম)</label>
                            <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-top: 4px;">
                                <?php
                                $activities = array('Scout', 'BNCC', 'Red Crescent', 'Cultural Club', 'Sports Club', 'Girls Guide');
                                $current_activities = $student ? explode(', ', $student->co_curricular) : array();
                                foreach ($activities as $act) {
                                    $checked = in_array($act, $current_activities) ? 'checked' : '';
                                    echo '<label style="display: inline-flex; align-items: center; font-size: 13px; cursor: pointer;">';
                                    echo '<input type="checkbox" name="co_curricular[]" value="' . esc_attr($act) . '" ' . $checked . ' style="margin-right: 4px;"> ' . esc_html($act);
                                    echo '</label>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px;">Student Profile Photo</label>
                            <input type="file" name="student_photo" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:6px; background: #fff;" accept="image/*">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px;">Account Status</label>
                            <select name="status" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; height: 38px;">
                                <option value="Active" <?php selected( $student ? $student->status : '', 'Active' ); ?>>Active</option>
                                <option value="Inactive" <?php selected( $student ? $student->status : '', 'Inactive' ); ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step Control Actions Footer Bar -->
                <div class="form-step-actions">
                    <button type="button" class="button" id="btnPrevStep" style="display:none; padding: 6px 18px; height: auto; font-weight: 600;">Previous</button>
                    <div style="margin-left: auto; display: flex; gap: 8px;">
                        <button type="button" class="button button-primary" id="btnNextStep" style="background: #006a4e; border-color: #006a4e; padding: 6px 20px; height: auto; font-weight: 600;">Next Step</button>
                        <input type="submit" name="educore_save_student" id="btnSubmitForm" class="button button-primary" style="display:none; background: #16a34a; border-color: #16a34a; padding: 6px 24px; height: auto; font-weight: 700;" value="<?php echo $is_edit ? 'Update Core Record' : 'Finalize Admission'; ?>">
                    </div>
                </div>

            </form>
        </div>
    </div>

    <!-- Client Side Step Router and Data Validation Script -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var currentStep = 1;
        var totalSteps = 4;
        
        var form = document.getElementById('educoreStudentForm');
        var btnNext = document.getElementById('btnNextStep');
        var btnPrev = document.getElementById('btnPrevStep');
        var btnSubmit = document.getElementById('btnSubmitForm');
        var tabButtons = document.querySelectorAll('#educoreStudentTabs .nav-link');

        // Auto UID Generator Linker Logic
        var btnAuto = document.getElementById('btnAutoGenerateID');
        if (btnAuto) {
            btnAuto.addEventListener('click', function() {
                var prefix = 'EDU-';
                var randomDigits = Math.floor(100000 + Math.random() * 900000); // 6 Digit Token
                document.getElementById('educore_student_id').value = prefix + randomDigits;
            });
        }

        function updateStepView() {
            // Content layer switching
            document.querySelectorAll('.educore-step-content').forEach(function(el) {
                el.classList.remove('active');
            });
            document.getElementById('educore-step-' + currentStep).classList.add('active');

            // Tab interface state refresh
            tabButtons.forEach(function(btn) {
                var stepNum = parseInt(btn.getAttribute('data-step'));
                btn.classList.remove('active');
                if (stepNum === currentStep) {
                    btn.classList.add('active');
                }
                if (stepNum < currentStep) {
                    btn.classList.add('completed');
                } else {
                    btn.classList.remove('completed');
                }
            });

            // Action Button conditional triggers
            if (currentStep === 1) {
                btnPrev.style.display = 'none';
            } else {
                btnPrev.style.display = 'inline-block';
            }

            if (currentStep === totalSteps) {
                btnNext.style.display = 'none';
                btnSubmit.style.display = 'inline-block';
            } else {
                btnNext.style.display = 'inline-block';
                btnSubmit.style.display = 'none';
            }
        }

        function validateCurrentStepInputs() {
            var currentWorkspace = document.getElementById('educore-step-' + currentStep);
            var requiredInputs = currentWorkspace.querySelectorAll('[required]');
            var isValid = true;

            requiredInputs.forEach(function(input) {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = '#dc2626';
                    input.addEventListener('input', function tmp() {
                        if (input.value.trim()) {
                            input.style.borderColor = '#cbd5e1';
                            input.removeEventListener('input', tmp);
                        }
                    });
                }
            });

            if (!isValid) {
                alert('অনুগ্রহ করে এই ধাপের বাধ্যতামূলক (*) ফিল্ডগুলো পূরণ করুন।');
            }
            return isValid;
        }

        btnNext.addEventListener('click', function() {
            if (validateCurrentStepInputs()) {
                if (currentStep < totalSteps) {
                    currentStep++;
                    updateStepView();
                }
            }
        });

        btnPrev.addEventListener('click', function() {
            if (currentStep > 1) {
                currentStep--;
                updateStepView();
            }
        });

        // Clickable tabs restriction logic
        tabButtons.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var targetStep = parseInt(this.getAttribute('data-step'));
                if (targetStep < currentStep) {
                    currentStep = targetStep;
                    updateStepView();
                } else if (targetStep > currentStep) {
                    // Forward jumping requires passing current step's validation rules
                    if (validateCurrentStepInputs()) {
                        currentStep = targetStep;
                        updateStepView();
                    }
                }
            });
        });

        // Form Submission final security block
        form.addEventListener('submit', function(e) {
            if (!validateCurrentStepInputs()) {
                e.preventDefault();
            }
        });
    });
    </script>
    <?php
}