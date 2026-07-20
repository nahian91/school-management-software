<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_staff_add_edit_view() {
    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';
    $is_edit     = isset( $_GET['sub'] ) && $_GET['sub'] === 'edit';
    $staff_id    = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    $staff = null;
    if ( $is_edit && $staff_id > 0 ) {
        $staff = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_staff WHERE id = %d", $staff_id ) );
    }

    // Handle Form Submission
    if ( isset( $_POST['educore_save_staff'] ) && wp_verify_nonce( $_POST['educore_staff_nonce'], 'save_staff_action' ) ) {
        
        $profile_image = $staff ? $staff->profile_image : '';

        // Handle Profile Image Upload
        if ( ! empty( $_FILES['staff_photo']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $uploaded_file = wp_handle_upload( $_FILES['staff_photo'], array( 'test_form' => false ) );
            if ( ! isset( $uploaded_file['error'] ) ) {
                $profile_image = $uploaded_file['url'];
            } else {
                echo '<div class="alert alert-danger">Image Upload Error: ' . esc_html( $uploaded_file['error'] ) . '</div>';
            }
        }

        $data = array(
            'full_name'          => sanitize_text_field( $_POST['full_name'] ),
            'name_bn'            => sanitize_text_field( $_POST['name_bn'] ),
            'father_name'        => sanitize_text_field( $_POST['father_name'] ),
            'mother_name'        => sanitize_text_field( $_POST['mother_name'] ),
            'designation'        => sanitize_text_field( $_POST['designation'] ),
            'staff_type'         => sanitize_text_field( $_POST['staff_type'] ),
            'pay_grade'          => sanitize_text_field( $_POST['pay_grade'] ),
            'index_no'           => sanitize_text_field( $_POST['index_no'] ),
            'nid_no'             => sanitize_text_field( $_POST['nid_no'] ),
            'dob'                => sanitize_text_field( $_POST['dob'] ),
            'gender'             => sanitize_text_field( $_POST['gender'] ),
            'phone'              => sanitize_text_field( $_POST['phone'] ),
            'whatsapp_no'        => sanitize_text_field( $_POST['whatsapp_no'] ),
            'email'              => sanitize_email( $_POST['email'] ),
            'blood_group'        => sanitize_text_field( $_POST['blood_group'] ),
            'quota_type'         => sanitize_text_field( $_POST['quota_type'] ),
            'joining_date'       => sanitize_text_field( $_POST['joining_date'] ),
            'salary'             => floatval( $_POST['salary'] ),
            'subject_expert'     => sanitize_text_field( $_POST['subject_expert'] ),
            'highest_degree'     => sanitize_text_field( $_POST['highest_degree'] ),
            
            // Emergency Contact Data Structure
            'emergency_name'     => sanitize_text_field( $_POST['emergency_name'] ),
            'emergency_phone'    => sanitize_text_field( $_POST['emergency_phone'] ),
            'emergency_relation' => sanitize_text_field( $_POST['emergency_relation'] ),
            
            // Banking Infrastructure for Government EFT / Payroll
            'bank_name'          => sanitize_text_field( $_POST['bank_name'] ),
            'bank_acc_no'        => sanitize_text_field( $_POST['bank_acc_no'] ),
            'bank_routing'       => sanitize_text_field( $_POST['bank_routing'] ),
            
            // Address Details
            'address'            => sanitize_textarea_field( $_POST['address'] ),
            'permanent_address'  => sanitize_textarea_field( $_POST['permanent_address'] ),
            
            // Social & Professional Profiles
            'linkedin_url'       => sanitize_url( $_POST['linkedin_url'] ),
            'facebook_url'       => sanitize_url( $_POST['facebook_url'] ),
            'website_url'        => sanitize_url( $_POST['website_url'] ),
            
            'profile_image'      => $profile_image,
            'status'             => sanitize_text_field( $_POST['status'] )
        );

        if ( $is_edit ) {
            $wpdb->update( $table_staff, $data, array( 'id' => $staff_id ) );
            echo '<div class="alert alert-success">Staff profile updated successfully.</div>';
            $staff = (object) array_merge( (array) $staff, $data );
        } else {
            $wpdb->insert( $table_staff, $data );
            echo '<div class="alert alert-success">New staff member added successfully.</div>';
            $_POST = array(); 
            $profile_image = '';
        }

        if ( class_exists('IFSEdu_School_Management_System') ) {
            IFSEdu_School_Management_System::log_activity("Saved staff record: " . $data['full_name']);
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
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; Back to Directory</a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border">
        <h3 class="pb-2 mb-4 text-success fw-bold border-bottom"><?php echo $is_edit ? 'Edit Staff Details' : 'Add New Staff / Teacher'; ?></h3>
        
        <!-- Tab Indicators -->
        <ul class="nav nav-tabs mb-4 flex-column flex-sm-row" id="educoreStaffTabs" role="tablist">
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link active" id="step-1-tab" data-step="1" href="javascript:void(0);">1. Personal Info</a>
            </li>
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link" id="step-2-tab" data-step="2" href="javascript:void(0);">2. Employment & Academic</a>
            </li>
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link" id="step-3-tab" data-step="3" href="javascript:void(0);">3. Payroll & Banking</a>
            </li>
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link" id="step-4-tab" data-step="4" href="javascript:void(0);">4. Address & Socials</a>
            </li>
        </ul>

        <form method="POST" action="" enctype="multipart/form-data" id="educoreStaffForm">
            <?php wp_nonce_field( 'save_staff_action', 'educore_staff_nonce' ); ?>
            
            <!-- STEP 1: Personal Identification -->
            <div class="educore-step-content active" id="educore-step-1">
                <?php if ( $is_edit && ! empty( $staff->profile_image ) ) : ?>
                    <div class="mb-4">
                        <label class="form-label d-block fw-bold">Current Photo</label>
                        <img src="<?php echo esc_url( $staff->profile_image ); ?>" alt="Staff Photo" class="rounded border" style="width: 100px; height: 100px; object-fit: cover;">
                    </div>
                <?php endif; ?>

                <h5 class="mb-3 text-success border-bottom pb-2">Personal Identification</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Full Name (English)</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo $staff ? esc_attr( $staff->full_name ) : ''; ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">নাম (বাংলায়)</label>
                        <input type="text" name="name_bn" class="form-control" value="<?php echo $staff ? esc_attr( $staff->name_bn ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">National ID / NID No</label>
                        <input type="text" name="nid_no" class="form-control" maxlength="17" value="<?php echo $staff ? esc_attr( $staff->nid_no ) : ''; ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Father's Name (পিতার নাম)</label>
                        <input type="text" name="father_name" class="form-control" value="<?php echo $staff ? esc_attr( $staff->father_name ) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Mother's Name (মাতার নাম)</label>
                        <input type="text" name="mother_name" class="form-control" value="<?php echo $staff ? esc_attr( $staff->mother_name ) : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Date of Birth</label>
                        <input type="date" name="dob" class="form-control" value="<?php echo $staff ? esc_attr( $staff->dob ) : ''; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Gender</label>
                        <select name="gender" class="form-control" required>
                            <option value="Male" <?php selected( $staff ? $staff->gender : '', 'Male' ); ?>>Male</option>
                            <option value="Female" <?php selected( $staff ? $staff->gender : '', 'Female' ); ?>>Female</option>
                            <option value="Other" <?php selected( $staff ? $staff->gender : '', 'Other' ); ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Mobile Number</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $staff ? esc_attr( $staff->phone ) : ''; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">WhatsApp Number</label>
                        <input type="text" name="whatsapp_no" class="form-control" placeholder="e.g., 01XXXXXXXXX" value="<?php echo $staff ? esc_attr( $staff->whatsapp_no ) : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $staff ? esc_attr( $staff->email ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Blood Group</label>
                        <select name="blood_group" class="form-control">
                            <option value="">Select Blood Group</option>
                            <?php
                            $blood_groups = array('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-');
                            foreach ($blood_groups as $bg) {
                                echo '<option value="' . esc_attr($bg) . '" ' . selected($staff ? $staff->blood_group : '', $bg, false) . '>' . esc_html($bg) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Employment & Academic Structure -->
            <div class="educore-step-content" id="educore-step-2">
                <h5 class="mb-3 text-success border-bottom pb-2">Employment & Academic Setup</h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Designation (Official Role)</label>
                        <input type="text" name="designation" class="form-control" placeholder="e.g., Assistant Teacher, Lecturer" value="<?php echo $staff ? esc_attr( $staff->designation ) : ''; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Employment Type</label>
                        <select name="staff_type" class="form-control" required>
                            <option value="">-- Select Type --</option>
                            <option value="Teacher (School)" <?php selected( $staff ? $staff->staff_type : '', 'Teacher (School)' ); ?>>Teacher (School)</option>
                            <option value="Teacher (College)" <?php selected( $staff ? $staff->staff_type : '', 'Teacher (College)' ); ?>>Teacher (College)</option>
                            <option value="Officer" <?php selected( $staff ? $staff->staff_type : '', 'Officer' ); ?>>Officer</option>
                            <option value="Staff" <?php selected( $staff ? $staff->staff_type : '', 'Staff' ); ?>>Staff</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">National Pay Scale Grade</label>
                        <select name="pay_grade" class="form-control">
                            <option value="">-- Select Pay Grade --</option>
                            <?php
                            for ( $i = 1; $i <= 20; $i++ ) {
                                $grade_str = "Grade " . $i;
                                echo '<option value="' . $grade_str . '" ' . selected( $staff ? $staff->pay_grade : '', $grade_str, false ) . '>' . $grade_str . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">MPO Index Number</label>
                        <input type="text" name="index_no" class="form-control" placeholder="e.g., T1029384" value="<?php echo $staff ? esc_attr( $staff->index_no ) : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Subject Expertise</label>
                        <input type="text" name="subject_expert" class="form-control" placeholder="e.g., Mathematics, English" value="<?php echo $staff ? esc_attr( $staff->subject_expert ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Highest Qualification</label>
                        <input type="text" name="highest_degree" class="form-control" placeholder="e.g., MA in English, B.Sc" value="<?php echo $staff ? esc_attr( $staff->highest_degree ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Quota Category (কোটা)</label>
                        <select name="quota_type" class="form-control">
                            <option value="General" <?php selected( $staff ? $staff->quota_type : '', 'General' ); ?>>General (সাধারণ)</option>
                            <option value="Freedom Fighter" <?php selected( $staff ? $staff->quota_type : '', 'Freedom Fighter' ); ?>>Freedom Fighter (মুক্তিযোদ্ধা)</option>
                            <option value="Tribal" <?php selected( $staff ? $staff->quota_type : '', 'Tribal' ); ?>>Tribal (ক্ষুদ্র নৃ-গোষ্ঠী)</option>
                            <option value="Other" <?php selected( $staff ? $staff->quota_type : '', 'Other' ); ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Joining Date</label>
                        <input type="date" name="joining_date" class="form-control" value="<?php echo $staff ? esc_attr( $staff->joining_date ) : date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Gross / Basic Salary (৳)</label>
                        <input type="number" step="0.01" name="salary" class="form-control" value="<?php echo $staff ? esc_attr( $staff->salary ) : '0.00'; ?>" required>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Payroll, Banking & Emergencies -->
            <div class="educore-step-content" id="educore-step-3">
                <h5 class="mb-3 text-success border-bottom pb-2">Bank Accounts & Payroll Mechanics</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="e.g., Sonali Bank PLC" value="<?php echo $staff ? esc_attr( $staff->bank_name ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Bank Account Number</label>
                        <input type="text" name="bank_acc_no" class="form-control" placeholder="13-17 Digit" value="<?php echo $staff ? esc_attr( $staff->bank_acc_no ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Bank Routing Number</label>
                        <input type="text" name="bank_routing" class="form-control" placeholder="9 Digit Routing Code" value="<?php echo $staff ? esc_attr( $staff->bank_routing ) : ''; ?>">
                    </div>
                </div>

                <h5 class="mb-3 text-success border-bottom pb-2 mt-4">Emergency Contact Protocol</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Emergency Contact Name</label>
                        <input type="text" name="emergency_name" class="form-control" value="<?php echo $staff ? esc_attr( $staff->emergency_name ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Emergency Contact Relation</label>
                        <input type="text" name="emergency_relation" class="form-control" placeholder="e.g., Spouse, Brother" value="<?php echo $staff ? esc_attr( $staff->emergency_relation ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Emergency Contact Phone</label>
                        <input type="text" name="emergency_phone" class="form-control" value="<?php echo $staff ? esc_attr( $staff->emergency_phone ) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- STEP 4: Logistics, Address & Socials -->
            <div class="educore-step-content" id="educore-step-4">
                <h5 class="mb-3 text-success border-bottom pb-2">Logistics & Status</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Upload Profile Photo</label>
                        <input type="file" name="staff_photo" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Account Status</label>
                        <select name="status" class="form-control">
                            <option value="Active" <?php selected( $staff ? $staff->status : '', 'Active' ); ?>>Active</option>
                            <option value="Resigned" <?php selected( $staff ? $staff->status : '', 'Resigned' ); ?>>Resigned / Left</option>
                            <option value="Suspended" <?php selected( $staff ? $staff->status : '', 'Suspended' ); ?>>Suspended</option>
                        </select>
                    </div>
                </div>

                <h5 class="mb-3 text-success border-bottom pb-2 mt-4">Address Details</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Present Address (বর্তমান ঠিকানা)</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Vill/Road, Post Office, Upazila, District"><?php echo $staff ? esc_textarea( $staff->address ) : ''; ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Permanent Address (স্থায়ী ঠিকানা)</label>
                        <textarea name="permanent_address" class="form-control" rows="3" placeholder="Vill/Road, Post Office, Upazila, District"><?php echo $staff ? esc_textarea( $staff->permanent_address ) : ''; ?></textarea>
                    </div>
                </div>

                <h5 class="mb-3 text-success border-bottom pb-2 mt-4">Social Profiles & Professional Connect</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">LinkedIn URL</label>
                        <input type="url" name="linkedin_url" class="form-control" placeholder="https://linkedin.com/in/username" value="<?php echo $staff ? esc_url( $staff->linkedin_url ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Facebook Profile URL</label>
                        <input type="url" name="facebook_url" class="form-control" placeholder="https://facebook.com/username" value="<?php echo $staff ? esc_url( $staff->facebook_url ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Portfolio / Personal Website</label>
                        <input type="url" name="website_url" class="form-control" placeholder="https://example.com" value="<?php echo $staff ? esc_url( $staff->website_url ) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Dynamic Form Control Steering Infrastructure -->
            <div class="form-step-actions d-flex justify-content-between">
                <button type="button" class="btn btn-secondary px-4" id="educorePrevBtn" style="display: none;">&larr; Previous Step</button>
                <div class="ms-auto">
                    <button type="button" class="btn btn-primary px-4" id="educoreNextBtn" style="background-color: #2563eb; border: none;">Next Step &rarr;</button>
                    <button type="submit" name="educore_save_staff" class="btn btn-success px-5" id="educoreSubmitBtn" style="display: none; background-color: #10b981; border: none; font-weight: bold;">
                        <?php echo $is_edit ? 'Update Record Stack' : 'Save Staff Member Details'; ?>
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

            // Optional direct tab click navigation (Only allowed if validated)
            $('#educoreStaffTabs .nav-link').on('click', function() {
                var targetStep = parseInt($(this).data('step'));
                if (targetStep < currentStep) {
                    currentStep = targetStep;
                    updateStepVisibility();
                } else if (targetStep > currentStep) {
                    // Trigger step logic forwards one by one to ensure safety checks
                    for (var i = currentStep; i < targetStep; i++) {
                        $('#educoreNextBtn').trigger('click');
                    }
                }
            });
        });
    </script>
    <?php
}