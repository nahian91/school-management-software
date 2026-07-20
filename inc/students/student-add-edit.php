<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
                echo '<div class="alert alert-danger shadow-sm border-0 mb-3">Photo Upload Error: ' . esc_html( $uploaded_file['error'] ) . '</div>';
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
        .educore-step-content { display: none; }
        .educore-step-content.active { display: block; }
        .nav-tabs .nav-link { color: #495057; font-weight: 600; border: 1px solid #dee2e6; margin-right: 5px; border-radius: 5px 5px 0 0; background-color: #f8f9fa; }
        .nav-tabs .nav-link.active { color: #fff !important; background-color: #10b981 !important; border-color: #10b981 !important; }
        .nav-tabs .nav-link.completed { background-color: #e2fbf0; color: #047857; border-color: #a7f3d0; }
        .form-step-actions { border-top: 1px solid #dee2e6; padding-top: 20px; margin-top: 30px; }
    </style>

    <div class="mb-3">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; Back to List</a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border">
        <h3 class="pb-2 mb-4 text-success fw-bold border-bottom"><?php echo $is_edit ? 'Edit Student Details' : 'Admit New Student'; ?></h3>
        
        <!-- Tab Indicators -->
        <ul class="nav nav-tabs mb-4 flex-column flex-sm-row" id="educoreStudentTabs" role="tablist">
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link active" id="step-1-tab" data-step="1" href="javascript:void(0);">1. Basic & Academic</a>
            </li>
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link" id="step-2-tab" data-step="2" href="javascript:void(0);">2. Parents Details</a>
            </li>
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link" id="step-3-tab" data-step="3" href="javascript:void(0);">3. Guardian & History</a>
            </li>
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link" id="step-4-tab" data-step="4" href="javascript:void(0);">4. Logistics & Address</a>
            </li>
        </ul>

        <form method="POST" action="" enctype="multipart/form-data" id="educoreStudentForm">
            <?php wp_nonce_field( 'save_student_action', 'educore_student_nonce' ); ?>
            
            <?php if ( $is_edit && ! empty( $student->photo_url ) ) : ?>
                <div class="mb-4">
                    <label class="form-label d-block fw-bold">Current Photo</label>
                    <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="Student Photo" class="rounded border" style="width: 100px; height: 100px; object-fit: cover;">
                </div>
            <?php endif; ?>

            <!-- STEP 1: Academic & Basic Info -->
            <div class="educore-step-content active" id="educore-step-1">
                <h5 class="mb-3 text-success border-bottom pb-2">Basic & Academic Information</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Student ID (Unique/UID) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="student_id" id="educore_student_id" class="form-control" value="<?php echo $student ? esc_attr( $student->student_id ) : ''; ?>" required>
                            <?php if ( ! $is_edit ) : ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAutoGenerateID" title="Generate Random UID">Auto</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Full Name (English) <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo $student ? esc_attr( $student->full_name ) : ''; ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">শিক্ষার্থীর নাম (বাংলায়)</label>
                        <input type="text" name="name_bn" class="form-control" value="<?php echo $student ? esc_attr( $student->name_bn ) : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <!-- Dynamic Class Selection from DB -->
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Class / Academic Year <span class="text-danger">*</span></label>
                        <select name="class_name" class="form-select" required>
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
                        <div class="form-text text-muted small">Pulled from Academic Setup.</div>
                    </div>

                    <!-- Dynamic Section / Group Selection from DB -->
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Section / Group</label>
                        <select name="section_name" class="form-select">
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

                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Roll Number <span class="text-danger">*</span></label>
                        <input type="number" name="roll_no" class="form-control" value="<?php echo $student ? esc_attr( $student->roll_no ) : ''; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Admission Date</label>
                        <input type="date" name="admission_date" class="form-control" value="<?php echo $student ? esc_attr( $student->admission_date ) : date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Birth Registration No (17 Digit)</label>
                        <input type="text" name="birth_reg_no" class="form-control" maxlength="17" value="<?php echo $student ? esc_attr( $student->birth_reg_no ) : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Date of Birth</label>
                        <input type="date" name="dob" class="form-control" value="<?php echo $student ? esc_attr( $student->dob ) : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Birth District (জন্মস্থান জেলা)</label>
                        <input type="text" name="birth_place" class="form-control" placeholder="e.g., Sylhet" value="<?php echo $student ? esc_attr( $student->birth_place ) : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Gender</label>
                        <select name="gender" class="form-control">
                            <option value="Male" <?php selected( $student ? $student->gender : '', 'Male' ); ?>>Male</option>
                            <option value="Female" <?php selected( $student ? $student->gender : '', 'Female' ); ?>>Female</option>
                            <option value="Other" <?php selected( $student ? $student->gender : '', 'Other' ); ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Blood Group</label>
                        <select name="blood_group" class="form-control">
                            <option value="">Select Blood Group</option>
                            <?php
                            $blood_groups = array('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-');
                            foreach ($blood_groups as $bg) {
                                echo '<option value="' . esc_attr($bg) . '" ' . selected($student ? $student->blood_group : '', $bg, false) . '>' . esc_html($bg) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Religion</label>
                        <select name="religion" class="form-control">
                            <option value="Islam" <?php selected( $student ? $student->religion : '', 'Islam' ); ?>>Islam</option>
                            <option value="Hinduism" <?php selected( $student ? $student->religion : '', 'Hinduism' ); ?>>Hinduism</option>
                            <option value="Christianity" <?php selected( $student ? $student->religion : '', 'Christianity' ); ?>>Christianity</option>
                            <option value="Buddhism" <?php selected( $student ? $student->religion : '', 'Buddhism' ); ?>>Buddhism</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Nationality</label>
                        <input type="text" name="nationality" class="form-control" value="<?php echo $student ? esc_attr( $student->nationality ) : 'Bangladeshi'; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Quota (কোটা)</label>
                        <select name="quota" class="form-control">
                            <option value="General" <?php selected( $student ? $student->quota : '', 'General' ); ?>>General</option>
                            <option value="Freedom Fighter" <?php selected( $student ? $student->quota : '', 'Freedom Fighter' ); ?>>Freedom Fighter (মুক্তিযোদ্ধা)</option>
                            <option value="Tribal" <?php selected( $student ? $student->quota : '', 'Tribal' ); ?>>Tribal (ক্ষুদ্র নৃ-গোষ্ঠী)</option>
                            <option value="Physically Challenged" <?php selected( $student ? $student->quota : '', 'Physically Challenged' ); ?>>Physically Challenged (প্রতিবন্ধী)</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Student Personal Mobile</label>
                        <input type="text" name="student_phone" class="form-control" value="<?php echo $student ? esc_attr( $student->student_phone ) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Student Email</label>
                        <input type="email" name="student_email" class="form-control" value="<?php echo $student ? esc_attr( $student->student_email ) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- STEP 2: Parents Information -->
            <div class="educore-step-content" id="educore-step-2">
                <h5 class="mb-3 text-success border-bottom pb-2">Parents Information</h5>
                <div class="row">
                    <!-- Father Details -->
                    <div class="col-md-6 border-end">
                        <h6 class="fw-bold text-muted mb-3">Father's Details</h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Father's Name (English)</label>
                            <input type="text" name="father_name" class="form-control" value="<?php echo $student ? esc_attr( $student->father_name ) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">পিতার নাম (বাংলায়)</label>
                            <input type="text" name="father_name_bn" class="form-control" value="<?php echo $student ? esc_attr( $student->father_name_bn ) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Father's NID</label>
                            <input type="text" name="father_nid" class="form-control" value="<?php echo $student ? esc_attr( $student->father_nid ) : ''; ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Father's Phone</label>
                                <input type="text" name="father_phone" class="form-control" value="<?php echo $student ? esc_attr( $student->father_phone ) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Profession</label>
                                <input type="text" name="father_profession" class="form-control" value="<?php echo $student ? esc_attr( $student->father_profession ) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Mother Details -->
                    <div class="col-md-6">
                        <h6 class="fw-bold text-muted mb-3">Mother's Details</h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mother's Name (English)</label>
                            <input type="text" name="mother_name" class="form-control" value="<?php echo $student ? esc_attr( $student->mother_name ) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">মাতার নাম (বাংলায়)</label>
                            <input type="text" name="mother_name_bn" class="form-control" value="<?php echo $student ? esc_attr( $student->mother_name_bn ) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mother's NID</label>
                            <input type="text" name="mother_nid" class="form-control" value="<?php echo $student ? esc_attr( $student->mother_nid ) : ''; ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Mother's Phone</label>
                                <input type="text" name="mother_phone" class="form-control" value="<?php echo $student ? esc_attr( $student->mother_phone ) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Profession</label>
                                <input type="text" name="mother_profession" class="form-control" value="<?php echo $student ? esc_attr( $student->mother_profession ) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Legal Guardian & Academic History -->
            <div class="educore-step-content" id="educore-step-3">
                <h5 class="mb-3 text-success border-bottom pb-2">Legal Guardian & Stipend Analytics</h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Guardian Name <span class="text-danger">*</span></label>
                        <input type="text" name="guardian_name" class="form-control" value="<?php echo $student ? esc_attr( $student->guardian_name ) : ''; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Relation with Guardian</label>
                        <input type="text" name="guardian_relation" class="form-control" value="<?php echo $student ? esc_attr( $student->guardian_relation ) : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Guardian NID</label>
                        <input type="text" name="guardian_nid" class="form-control" value="<?php echo $student ? esc_attr( $student->guardian_nid ) : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Guardian Annual Income (BDT)</label>
                        <input type="number" name="guardian_income" class="form-control" placeholder="e.g., 180000" value="<?php echo $student ? esc_attr( $student->guardian_income ) : ''; ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Guardian Contact Mobile (For SMS Alerts) <span class="text-danger">*</span></label>
                        <input type="text" name="guardian_phone" class="form-control" value="<?php echo $student ? esc_attr( $student->guardian_phone ) : ''; ?>" required>
                    </div>
                </div>

                <h5 class="mb-3 text-success border-bottom pb-2 mt-4">Previous Academic History</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Previous School / Institute Name</label>
                        <input type="text" name="prev_school_name" class="form-control" value="<?php echo $student ? esc_attr( $student->prev_school_name ) : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Previous Institute EIIN (If applicable)</label>
                        <input type="text" name="prev_eiin" class="form-control" value="<?php echo $student ? esc_attr( $student->prev_eiin ) : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Passed Class / Examination</label>
                        <input type="text" name="prev_class" class="form-control" placeholder="e.g., PSC, JSC, Class 5" value="<?php echo $student ? esc_attr( $student->prev_class ) : ''; ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Obtained GPA / Marks</label>
                        <input type="text" name="prev_gpa" class="form-control" placeholder="e.g., 5.00" value="<?php echo $student ? esc_attr( $student->prev_gpa ) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- STEP 4: Logistics, Address & Settings -->
            <div class="educore-step-content" id="educore-step-4">
                <h5 class="mb-3 text-success border-bottom pb-2">Address Details</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Present Address (বর্তমান ঠিকানা) <span class="text-danger">*</span></label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Vill/Road, Post Office, Upazila, District" required><?php echo $student ? esc_textarea( $student->address ) : ''; ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Permanent Address (স্থায়ী ঠিকানা)</label>
                        <textarea name="permanent_address" class="form-control" rows="3" placeholder="Vill/Road, Post Office, Upazila, District"><?php echo $student ? esc_textarea( $student->permanent_address ) : ''; ?></textarea>
                    </div>
                </div>

                <h5 class="mb-3 text-success border-bottom pb-2 mt-4">Logistics & Co-Curricular Activities</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Residential Status</label>
                        <select name="residential_status" class="form-control">
                            <option value="Non-Residential" <?php selected( $student ? $student->residential_status : '', 'Non-Residential' ); ?>>Non-Residential (অনাবাসিক)</option>
                            <option value="Residential (School Hostel)" <?php selected( $student ? $student->residential_status : '', 'Residential (School Hostel)' ); ?>>Residential (School Hostel)</option>
                            <option value="Mess / Private Care" <?php selected( $student ? $student->residential_status : '', 'Mess / Private Care' ); ?>>Mess / Private Care</option>
                        </select>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold d-block">Co-Curricular Activities (সহ-শিক্ষা কার্যক্রম)</label>
                        <?php
                        $activities = array('Scout', 'BNCC', 'Red Crescent', 'Cultural Club', 'Sports Club', 'Girls Guide');
                        $current_activities = $student ? explode(', ', $student->co_curricular) : array();
                        foreach ($activities as $act) {
                            $checked = in_array($act, $current_activities) ? 'checked' : '';
                            echo '<div class="form-check form-check-inline mt-2 me-3">';
                            echo '<input class="form-check-input" type="checkbox" name="co_curricular[]" value="' . esc_attr($act) . '" id="act_' . esc_attr($act) . '" ' . $checked . '>';
                            echo '<label class="form-check-label ms-1" for="act_' . esc_attr($act) . '">' . esc_html($act) . '</label>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <h5 class="mb-3 text-success border-bottom pb-2 mt-4">Settings & Uploads</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Student Profile Photo</label>
                        <input type="file" name="student_photo" class="form-control" accept="image/*">
                        <div class="form-text text-muted">Accepted formats: JPG, JPEG, PNG. Max size 2MB.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Account Status</label>
                        <select name="status" class="form-control">
                            <option value="Active" <?php selected( $student ? $student->status : '', 'Active' ); ?>>Active</option>
                            <option value="Inactive" <?php selected( $student ? $student->status : '', 'Inactive' ); ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Dynamic Form Control Steering Infrastructure -->
            <div class="form-step-actions d-flex justify-content-between">
                <button type="button" class="btn btn-secondary px-4" id="educorePrevBtn" style="display: none;">&larr; Previous Step</button>
                <div class="ms-auto">
                    <button type="button" class="btn btn-primary px-4" id="educoreNextBtn" style="background-color: #2563eb; border: none;">Next Step &rarr;</button>
                    <button type="submit" name="educore_save_student" class="btn btn-success px-5" id="educoreSubmitBtn" style="display: none; background-color: #10b981; border: none; font-weight: bold;">
                        <?php echo $is_edit ? 'Update Student Record' : 'Complete Admission'; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var currentStep = 1;
            var totalSteps = 4;

            // Auto Generate Unique Student UID
            $('#btnAutoGenerateID').on('click', function() {
                var randomUid = 'STU-' + new Date().getFullYear() + '-' + Math.floor(1000 + Math.random() * 9000);
                $('#educore_student_id').val(randomUid);
            });

            function updateStepVisibility() {
                $('.educore-step-content').removeClass('active');
                $('#educore-step-' + currentStep).addClass('active');

                // Update Tab Indicator Highlights
                $('#educoreStudentTabs .nav-link').removeClass('active');
                $('#step-' + currentStep + '-tab').addClass('active');

                // Control Dynamic Action Grid Buttons
                if (currentStep === 1) {
                    $('#educorePrevBtn').hide();
                } else {
                    $('#educorePrevBtn').show();
                }

                if (currentStep === totalSteps) {
                    $('#educoreNextBtn').hide();
                    $('#educoreSubmitBtn').show();
                } else {
                    $('#educoreNextBtn').show();
                    $('#educoreSubmitBtn').hide();
                }
            }

            // Step Forward Mechanics with Validation Check
            $('#educoreNextBtn').on('click', function() {
                var currentStepFields = $('#educore-step-' + currentStep).find('input[required], select[required], textarea[required]');
                var isValid = true;

                currentStepFields.each(function() {
                    if (!this.checkValidity()) {
                        isValid = false;
                        this.reportValidity();
                        return false; 
                    }
                });

                if (isValid) {
                    $('#step-' + currentStep + '-tab').addClass('completed');
                    if (currentStep < totalSteps) {
                        currentStep++;
                        updateStepVisibility();
                    }
                }
            });

            // Step Backward Mechanics
            $('#educorePrevBtn').on('click', function() {
                if (currentStep > 1) {
                    currentStep--;
                    updateStepVisibility();
                }
            });

            // Direct tab click navigation
            $('#educoreStudentTabs .nav-link').on('click', function() {
                var targetStep = parseInt($(this).data('step'));
                if (targetStep < currentStep) {
                    currentStep = targetStep;
                    updateStepVisibility();
                } else if (targetStep > currentStep) {
                    for (var i = currentStep; i < targetStep; i++) {
                        $('#educoreNextBtn').trigger('click');
                    }
                }
            });
        });
    </script>
    <?php
}