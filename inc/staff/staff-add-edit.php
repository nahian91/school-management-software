<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access lockdown
}

function educore_staff_add_edit_view() {
    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    // 1. Security & Capability Verification
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to manage staff profiles.', 'educore' ) );
    }

    $is_edit  = isset( $_GET['sub'] ) && $_GET['sub'] === 'edit';
    $staff_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

    $staff = null;
    if ( $is_edit && $staff_id > 0 ) {
        $staff = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_staff} WHERE id = %d", $staff_id ) );
    }

    // 2. Handle Form Submission
    if ( isset( $_POST['educore_save_staff'] ) && wp_verify_nonce( $_POST['educore_staff_nonce'], 'save_staff_action' ) ) {

        $profile_image = $staff ? $staff->profile_image : '';

        // Handle File Upload
        if ( ! empty( $_FILES['staff_photo']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $uploaded_file = wp_handle_upload( $_FILES['staff_photo'], array( 'test_form' => false ) );
            if ( ! isset( $uploaded_file['error'] ) ) {
                $profile_image = $uploaded_file['url'];
            } else {
                echo '<div class="alert alert-danger">' . esc_html__( 'Image Upload Error: ', 'educore' ) . esc_html( $uploaded_file['error'] ) . '</div>';
            }
        }

        $data = array(
            'full_name'          => isset( $_POST['full_name'] ) ? sanitize_text_field( $_POST['full_name'] ) : '',
            'name_bn'            => isset( $_POST['name_bn'] ) ? sanitize_text_field( $_POST['name_bn'] ) : '',
            'father_name'        => isset( $_POST['father_name'] ) ? sanitize_text_field( $_POST['father_name'] ) : '',
            'mother_name'        => isset( $_POST['mother_name'] ) ? sanitize_text_field( $_POST['mother_name'] ) : '',
            'designation'        => isset( $_POST['designation'] ) ? sanitize_text_field( $_POST['designation'] ) : '',
            'staff_type'         => isset( $_POST['staff_type'] ) ? sanitize_text_field( $_POST['staff_type'] ) : '',
            'pay_grade'          => isset( $_POST['pay_grade'] ) ? sanitize_text_field( $_POST['pay_grade'] ) : '',
            'index_no'           => isset( $_POST['index_no'] ) ? sanitize_text_field( $_POST['index_no'] ) : '',
            'nid_no'             => isset( $_POST['nid_no'] ) ? sanitize_text_field( $_POST['nid_no'] ) : '',
            'dob'                => isset( $_POST['dob'] ) ? sanitize_text_field( $_POST['dob'] ) : '1970-01-01',
            'gender'             => isset( $_POST['gender'] ) ? sanitize_text_field( $_POST['gender'] ) : 'Male',
            'phone'              => isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '',
            'whatsapp_no'        => isset( $_POST['whatsapp_no'] ) ? sanitize_text_field( $_POST['whatsapp_no'] ) : '',
            'email'              => isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '',
            'blood_group'        => isset( $_POST['blood_group'] ) ? sanitize_text_field( $_POST['blood_group'] ) : '',
            'quota_type'         => isset( $_POST['quota_type'] ) ? sanitize_text_field( $_POST['quota_type'] ) : 'General',
            'joining_date'       => isset( $_POST['joining_date'] ) ? sanitize_text_field( $_POST['joining_date'] ) : '1970-01-01',
            'salary'             => isset( $_POST['salary'] ) ? floatval( $_POST['salary'] ) : 0.00,
            'subject_expert'     => isset( $_POST['subject_expert'] ) ? sanitize_text_field( $_POST['subject_expert'] ) : '',
            'highest_degree'     => isset( $_POST['highest_degree'] ) ? sanitize_text_field( $_POST['highest_degree'] ) : '',

            'emergency_name'     => isset( $_POST['emergency_name'] ) ? sanitize_text_field( $_POST['emergency_name'] ) : '',
            'emergency_phone'    => isset( $_POST['emergency_phone'] ) ? sanitize_text_field( $_POST['emergency_phone'] ) : '',
            'emergency_relation' => isset( $_POST['emergency_relation'] ) ? sanitize_text_field( $_POST['emergency_relation'] ) : '',

            'bank_name'          => isset( $_POST['bank_name'] ) ? sanitize_text_field( $_POST['bank_name'] ) : '',
            'bank_acc_no'        => isset( $_POST['bank_acc_no'] ) ? sanitize_text_field( $_POST['bank_acc_no'] ) : '',
            'bank_routing'       => isset( $_POST['bank_routing'] ) ? sanitize_text_field( $_POST['bank_routing'] ) : '',

            'address'            => isset( $_POST['address'] ) ? sanitize_textarea_field( $_POST['address'] ) : '',
            'permanent_address'  => isset( $_POST['permanent_address'] ) ? sanitize_textarea_field( $_POST['permanent_address'] ) : '',

            'linkedin_url'       => isset( $_POST['linkedin_url'] ) ? sanitize_url( $_POST['linkedin_url'] ) : '',
            'facebook_url'       => isset( $_POST['facebook_url'] ) ? sanitize_url( $_POST['facebook_url'] ) : '',
            'website_url'        => isset( $_POST['website_url'] ) ? sanitize_url( $_POST['website_url'] ) : '',

            'profile_image'      => $profile_image,
            'status'             => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'Active'
        );

        if ( $is_edit && $staff_id > 0 ) {
            $wpdb->update( $table_staff, $data, array( 'id' => $staff_id ) );
            echo '<div class="alert alert-success">' . esc_html__( 'Staff profile updated successfully.', 'educore' ) . '</div>';
            $staff = (object) array_merge( (array) $staff, $data );
        } else {
            $wpdb->insert( $table_staff, $data );
            echo '<div class="alert alert-success">' . esc_html__( 'New staff member added successfully.', 'educore' ) . '</div>';
            $_POST = array();
            $profile_image = '';
        }

        if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
            IFSEdu_School_Management_System::log_activity( "Saved staff record: " . $data['full_name'] );
        }
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=list' );
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
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; <?php esc_html_e( 'Back to Directory', 'educore' ); ?></a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border">
        <h3 class="pb-2 mb-4 text-success fw-bold border-bottom">
            <?php echo $is_edit ? esc_html__( 'Edit Staff Details', 'educore' ) : esc_html__( 'Add New Staff / Teacher', 'educore' ); ?>
        </h3>
        
        <!-- Tab Indicators -->
        <ul class="nav nav-tabs mb-4 flex-column flex-sm-row" id="educoreStaffTabs" role="tablist">
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link active" id="step-1-tab" data-step="1" href="javascript:void(0);"><?php esc_html_e( '1. Personal Info', 'educore' ); ?></a>
            </li>
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link" id="step-2-tab" data-step="2" href="javascript:void(0);"><?php esc_html_e( '2. Employment & Academic', 'educore' ); ?></a>
            </li>
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link" id="step-3-tab" data-step="3" href="javascript:void(0);"><?php esc_html_e( '3. Payroll & Banking', 'educore' ); ?></a>
            </li>
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link" id="step-4-tab" data-step="4" href="javascript:void(0);"><?php esc_html_e( '4. Address & Socials', 'educore' ); ?></a>
            </li>
        </ul>

        <form method="POST" action="" enctype="multipart/form-data" id="educoreStaffForm">
            <?php wp_nonce_field( 'save_staff_action', 'educore_staff_nonce' ); ?>
            
            <!-- STEP 1: Personal Identification -->
            <div class="educore-step-content active" id="educore-step-1">
                <?php if ( $is_edit && ! empty( $staff->profile_image ) ) : ?>
                    <div class="mb-4">
                        <label class="form-label d-block fw-bold"><?php esc_html_e( 'Current Photo', 'educore' ); ?></label>
                        <img src="<?php echo esc_url( $staff->profile_image ); ?>" alt="Staff Photo" class="rounded border" style="width: 100px; height: 100px; object-fit: cover;">
                    </div>
                <?php endif; ?>

                <h5 class="mb-3 text-success border-bottom pb-2"><?php esc_html_e( 'Personal Identification', 'educore' ); ?></h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Full Name (English)', 'educore' ); ?></label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo $staff ? esc_attr( $staff->full_name ) : ''; ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'নাম (বাংলায়)', 'educore' ); ?></label>
                        <input type="text" name="name_bn" class="form-control" value="<?php echo $staff ? esc_attr( $staff->name_bn ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'National ID / NID No', 'educore' ); ?></label>
                        <input type="text" name="nid_no" class="form-control" maxlength="17" value="<?php echo $staff ? esc_attr( $staff->nid_no ) : ''; ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( "Father's Name (পিতার নাম)", 'educore' ); ?></label>
                        <input type="text" name="father_name" class="form-control" value="<?php echo $staff ? esc_attr( $staff->father_name ) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( "Mother's Name (মাতার নাম)", 'educore' ); ?></label>
                        <input type="text" name="mother_name" class="form-control" value="<?php echo $staff ? esc_attr( $staff->mother_name ) : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Date of Birth', 'educore' ); ?></label>
                        <input type="date" name="dob" class="form-control" value="<?php echo $staff ? esc_attr( $staff->dob ) : ''; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Gender', 'educore' ); ?></label>
                        <select name="gender" class="form-control" required>
                            <option value="Male" <?php selected( $staff ? $staff->gender : '', 'Male' ); ?>><?php esc_html_e( 'Male', 'educore' ); ?></option>
                            <option value="Female" <?php selected( $staff ? $staff->gender : '', 'Female' ); ?>><?php esc_html_e( 'Female', 'educore' ); ?></option>
                            <option value="Other" <?php selected( $staff ? $staff->gender : '', 'Other' ); ?>><?php esc_html_e( 'Other', 'educore' ); ?></option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Mobile Number', 'educore' ); ?></label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $staff ? esc_attr( $staff->phone ) : ''; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'WhatsApp Number', 'educore' ); ?></label>
                        <input type="text" name="whatsapp_no" class="form-control" placeholder="e.g., 01XXXXXXXXX" value="<?php echo $staff ? esc_attr( $staff->whatsapp_no ) : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Email Address', 'educore' ); ?></label>
                        <input type="email" name="email" class="form-control" value="<?php echo $staff ? esc_attr( $staff->email ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Blood Group', 'educore' ); ?></label>
                        <select name="blood_group" class="form-control">
                            <option value=""><?php esc_html_e( 'Select Blood Group', 'educore' ); ?></option>
                            <?php
                            $blood_groups = array( 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-' );
                            foreach ( $blood_groups as $bg ) {
                                echo '<option value="' . esc_attr( $bg ) . '" ' . selected( $staff ? $staff->blood_group : '', $bg, false ) . '>' . esc_html( $bg ) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Employment & Academic Structure -->
            <div class="educore-step-content" id="educore-step-2">
                <h5 class="mb-3 text-success border-bottom pb-2"><?php esc_html_e( 'Employment & Academic Setup', 'educore' ); ?></h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Designation (Official Role)', 'educore' ); ?></label>
                        <input type="text" name="designation" class="form-control" placeholder="e.g., Assistant Teacher, Lecturer" value="<?php echo $staff ? esc_attr( $staff->designation ) : ''; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Employment Type', 'educore' ); ?></label>
                        <select name="staff_type" class="form-control" required>
                            <option value=""><?php esc_html_e( '-- Select Type --', 'educore' ); ?></option>
                            <option value="Teacher (School)" <?php selected( $staff ? $staff->staff_type : '', 'Teacher (School)' ); ?>><?php esc_html_e( 'Teacher (School)', 'educore' ); ?></option>
                            <option value="Teacher (College)" <?php selected( $staff ? $staff->staff_type : '', 'Teacher (College)' ); ?>><?php esc_html_e( 'Teacher (College)', 'educore' ); ?></option>
                            <option value="Officer" <?php selected( $staff ? $staff->staff_type : '', 'Officer' ); ?>><?php esc_html_e( 'Officer', 'educore' ); ?></option>
                            <option value="Staff" <?php selected( $staff ? $staff->staff_type : '', 'Staff' ); ?>><?php esc_html_e( 'Staff', 'educore' ); ?></option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'National Pay Scale Grade', 'educore' ); ?></label>
                        <select name="pay_grade" class="form-control">
                            <option value=""><?php esc_html_e( '-- Select Pay Grade --', 'educore' ); ?></option>
                            <?php
                            for ( $i = 1; $i <= 20; $i++ ) {
                                $grade_str = "Grade " . $i;
                                echo '<option value="' . esc_attr( $grade_str ) . '" ' . selected( $staff ? $staff->pay_grade : '', $grade_str, false ) . '>' . esc_html( $grade_str ) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'MPO Index Number', 'educore' ); ?></label>
                        <input type="text" name="index_no" class="form-control" placeholder="e.g., T1029384" value="<?php echo $staff ? esc_attr( $staff->index_no ) : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Subject Expertise', 'educore' ); ?></label>
                        <input type="text" name="subject_expert" class="form-control" placeholder="e.g., Mathematics, English" value="<?php echo $staff ? esc_attr( $staff->subject_expert ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Highest Qualification', 'educore' ); ?></label>
                        <input type="text" name="highest_degree" class="form-control" placeholder="e.g., MA in English, B.Sc" value="<?php echo $staff ? esc_attr( $staff->highest_degree ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Quota Category (কোটা)', 'educore' ); ?></label>
                        <select name="quota_type" class="form-control">
                            <option value="General" <?php selected( $staff ? $staff->quota_type : '', 'General' ); ?>><?php esc_html_e( 'General (সাধারণ)', 'educore' ); ?></option>
                            <option value="Freedom Fighter" <?php selected( $staff ? $staff->quota_type : '', 'Freedom Fighter' ); ?>><?php esc_html_e( 'Freedom Fighter (মুক্তিযোদ্ধা)', 'educore' ); ?></option>
                            <option value="Tribal" <?php selected( $staff ? $staff->quota_type : '', 'Tribal' ); ?>><?php esc_html_e( 'Tribal (ক্ষুদ্র নৃ-গোষ্ঠী)', 'educore' ); ?></option>
                            <option value="Other" <?php selected( $staff ? $staff->quota_type : '', 'Other' ); ?>><?php esc_html_e( 'Other', 'educore' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Joining Date', 'educore' ); ?></label>
                        <input type="date" name="joining_date" class="form-control" value="<?php echo $staff ? esc_attr( $staff->joining_date ) : esc_attr( date( 'Y-m-d' ) ); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Gross / Basic Salary (৳)', 'educore' ); ?></label>
                        <input type="number" step="0.01" name="salary" class="form-control" value="<?php echo $staff ? esc_attr( $staff->salary ) : '0.00'; ?>" required>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Payroll, Banking & Emergencies -->
            <div class="educore-step-content" id="educore-step-3">
                <h5 class="mb-3 text-success border-bottom pb-2"><?php esc_html_e( 'Bank Accounts & Payroll Mechanics', 'educore' ); ?></h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Bank Name', 'educore' ); ?></label>
                        <input type="text" name="bank_name" class="form-control" placeholder="e.g., Sonali Bank PLC" value="<?php echo $staff ? esc_attr( $staff->bank_name ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Bank Account Number', 'educore' ); ?></label>
                        <input type="text" name="bank_acc_no" class="form-control" placeholder="13-17 Digit" value="<?php echo $staff ? esc_attr( $staff->bank_acc_no ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Bank Routing Number', 'educore' ); ?></label>
                        <input type="text" name="bank_routing" class="form-control" placeholder="9 Digit Routing Code" value="<?php echo $staff ? esc_attr( $staff->bank_routing ) : ''; ?>">
                    </div>
                </div>

                <h5 class="mb-3 text-success border-bottom pb-2 mt-4"><?php esc_html_e( 'Emergency Contact Protocol', 'educore' ); ?></h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Emergency Contact Name', 'educore' ); ?></label>
                        <input type="text" name="emergency_name" class="form-control" value="<?php echo $staff ? esc_attr( $staff->emergency_name ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Emergency Contact Relation', 'educore' ); ?></label>
                        <input type="text" name="emergency_relation" class="form-control" placeholder="e.g., Spouse, Brother" value="<?php echo $staff ? esc_attr( $staff->emergency_relation ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Emergency Contact Phone', 'educore' ); ?></label>
                        <input type="text" name="emergency_phone" class="form-control" value="<?php echo $staff ? esc_attr( $staff->emergency_phone ) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- STEP 4: Logistics, Address & Socials -->
            <div class="educore-step-content" id="educore-step-4">
                <h5 class="mb-3 text-success border-bottom pb-2"><?php esc_html_e( 'Logistics & Status', 'educore' ); ?></h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Upload Profile Photo', 'educore' ); ?></label>
                        <input type="file" name="staff_photo" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Account Status', 'educore' ); ?></label>
                        <select name="status" class="form-control">
                            <option value="Active" <?php selected( $staff ? $staff->status : '', 'Active' ); ?>><?php esc_html_e( 'Active', 'educore' ); ?></option>
                            <option value="Resigned" <?php selected( $staff ? $staff->status : '', 'Resigned' ); ?>><?php esc_html_e( 'Resigned / Left', 'educore' ); ?></option>
                            <option value="Suspended" <?php selected( $staff ? $staff->status : '', 'Suspended' ); ?>><?php esc_html_e( 'Suspended', 'educore' ); ?></option>
                        </select>
                    </div>
                </div>

                <h5 class="mb-3 text-success border-bottom pb-2 mt-4"><?php esc_html_e( 'Address Details', 'educore' ); ?></h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Present Address (বর্তমান ঠিকানা)', 'educore' ); ?></label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Vill/Road, Post Office, Upazila, District"><?php echo $staff ? esc_textarea( $staff->address ) : ''; ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Permanent Address (স্থায়ী ঠিকানা)', 'educore' ); ?></label>
                        <textarea name="permanent_address" class="form-control" rows="3" placeholder="Vill/Road, Post Office, Upazila, District"><?php echo $staff ? esc_textarea( $staff->permanent_address ) : ''; ?></textarea>
                    </div>
                </div>

                <h5 class="mb-3 text-success border-bottom pb-2 mt-4"><?php esc_html_e( 'Social Profiles & Professional Connect', 'educore' ); ?></h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'LinkedIn URL', 'educore' ); ?></label>
                        <input type="url" name="linkedin_url" class="form-control" placeholder="https://linkedin.com/in/username" value="<?php echo $staff ? esc_url( $staff->linkedin_url ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Facebook Profile URL', 'educore' ); ?></label>
                        <input type="url" name="facebook_url" class="form-control" placeholder="https://facebook.com/username" value="<?php echo $staff ? esc_url( $staff->facebook_url ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Portfolio / Personal Website', 'educore' ); ?></label>
                        <input type="url" name="website_url" class="form-control" placeholder="https://example.com" value="<?php echo $staff ? esc_url( $staff->website_url ) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Dynamic Form Control Steering Infrastructure -->
            <div class="form-step-actions d-flex justify-content-between">
                <button type="button" class="btn btn-secondary px-4" id="educorePrevBtn" style="display: none;">&larr; <?php esc_html_e( 'Previous Step', 'educore' ); ?></button>
                <div class="ms-auto">
                    <button type="button" class="btn btn-primary px-4" id="educoreNextBtn" style="background-color: #2563eb; border: none;"><?php esc_html_e( 'Next Step &rarr;', 'educore' ); ?></button>
                    <button type="submit" name="educore_save_staff" class="btn btn-success px-5" id="educoreSubmitBtn" style="display: none; background-color: #10b981; border: none; font-weight: bold;">
                        <?php echo $is_edit ? esc_html__( 'Update Record Stack', 'educore' ) : esc_html__( 'Save Staff Member Details', 'educore' ); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var currentStep = 1;
            var totalSteps = 4;

            function updateStepVisibility() {
                $('.educore-step-content').removeClass('active');
                $('#educore-step-' + currentStep).addClass('active');

                // Update Tab Indicator Highlights
                $('#educoreStaffTabs .nav-link').removeClass('active');
                $('#step-' + currentStep + '-tab').addClass('active');

                // Control Dynamic Action Buttons
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

            // Direct Tab Click Navigation
            $('#educoreStaffTabs .nav-link').on('click', function() {
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
?>