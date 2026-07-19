<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_student_add_edit_view() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sms_students';
    $is_edit    = isset( $_GET['sub'] ) && $_GET['sub'] === 'edit';
    $student_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    $student = null;
    if ( $is_edit && $student_id > 0 ) {
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $student_id ) );
    }

    // Handle Form Submission
    if ( isset( $_POST['educore_save_student'] ) && wp_verify_nonce( $_POST['educore_student_nonce'], 'save_student_action' ) ) {
        
        // বিদ্যমান ছবির ইউআরএল ডিফল্ট রাখা হলো
        $photo_url = $student ? $student->photo_url : '';

        // ছবি আপলোড প্রসেসিং
        if ( ! empty( $_FILES['student_photo']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            
            $uploaded_file = wp_handle_upload( $_FILES['student_photo'], array( 'test_form' => false ) );
            
            if ( isset( $uploaded_file['error'] ) ) {
                echo '<div class="alert alert-danger">Photo Upload Error: ' . esc_html( $uploaded_file['error'] ) . '</div>';
            } else {
                $photo_url = $uploaded_file['url'];
            }
        }

        $data = array(
            'student_id'     => sanitize_text_field( $_POST['student_id'] ),
            'full_name'      => sanitize_text_field( $_POST['full_name'] ),
            'class_name'     => sanitize_text_field( $_POST['class_name'] ),
            'section_name'   => sanitize_text_field( $_POST['section_name'] ),
            'roll_no'        => intval( $_POST['roll_no'] ),
            'dob'            => sanitize_text_field( $_POST['dob'] ),
            'gender'         => sanitize_text_field( $_POST['gender'] ),
            'guardian_name'  => sanitize_text_field( $_POST['guardian_name'] ),
            'guardian_phone' => sanitize_text_field( $_POST['guardian_phone'] ),
            'address'        => sanitize_textarea_field( $_POST['address'] ),
            'admission_date' => sanitize_text_field( $_POST['admission_date'] ),
            'photo_url'      => $photo_url,
            'status'         => sanitize_text_field( $_POST['status'] )
        );

        if ( $is_edit ) {
            $wpdb->update( $table_name, $data, array( 'id' => $student_id ) );
            echo '<div class="alert alert-success">Student updated successfully.</div>';
            $student = (object) array_merge( (array) $student, $data ); // Local অবজেক্ট আপডেট
        } else {
            $wpdb->insert( $table_name, $data );
            echo '<div class="alert alert-success">New student added successfully.</div>';
            $_POST = array(); // ফর্ম ক্লিয়ার
            $photo_url = '';
        }
        educore_log_activity("Saved student record: " . $data['full_name']);
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );
    ?>

    <div class="mb-3">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; Back to List</a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border">
        <h3 class="mb-4"><?php echo $is_edit ? 'Edit Student Details' : 'Admit New Student'; ?></h3>
        
        <!-- ফাইল আপলোডের জন্য enctype যুক্ত করা হলো -->
        <form method="POST" action="" enctype="multipart/form-data">
            <?php wp_nonce_field( 'save_student_action', 'educore_student_nonce' ); ?>
            
            <!-- ছবি এডিট করার সময় বর্তমান ছবিটির প্রিভিউ দেখানোর জন্য -->
            <?php if ( $is_edit && ! empty( $student->photo_url ) ) : ?>
                <div class="mb-4">
                    <label class="form-label d-block fw-bold">Current Photo</label>
                    <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="Student Photo" class="rounded border" style="width: 100px; height: 100px; object-fit: cover;">
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Student ID (Unique)</label>
                    <input type="text" name="student_id" class="form-control" value="<?php echo $student ? esc_attr( $student->student_id ) : ''; ?>" required>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label fw-bold">Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo $student ? esc_attr( $student->full_name ) : ''; ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Class</label>
                    <input type="text" name="class_name" class="form-control" value="<?php echo $student ? esc_attr( $student->class_name ) : ''; ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Section</label>
                    <input type="text" name="section_name" class="form-control" value="<?php echo $student ? esc_attr( $student->section_name ) : ''; ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Roll Number</label>
                    <input type="number" name="roll_no" class="form-control" value="<?php echo $student ? esc_attr( $student->roll_no ) : ''; ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Date of Birth</label>
                    <input type="date" name="dob" class="form-control" value="<?php echo $student ? esc_attr( $student->dob ) : ''; ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Gender</label>
                    <select name="gender" class="form-control">
                        <option value="Male" <?php selected( $student ? $student->gender : '', 'Male' ); ?>>Male</option>
                        <option value="Female" <?php selected( $student ? $student->gender : '', 'Female' ); ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Admission Date</label>
                    <input type="date" name="admission_date" class="form-control" value="<?php echo $student ? esc_attr( $student->admission_date ) : date('Y-m-d'); ?>">
                </div>
            </div>

            <!-- প্রোফাইল পিকচার আপলোড ইনপুট ফিল্ড -->
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold">Student Profile Photo</label>
                    <input type="file" name="student_photo" class="form-control" accept="image/*">
                    <div class="form-text text-muted">Accepted formats: JPG, JPEG, PNG.</div>
                </div>
            </div>

            <hr class="my-4">
            <h5 class="mb-3 text-muted">Guardian & Contact Information</h5>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Guardian Name</label>
                    <input type="text" name="guardian_name" class="form-control" value="<?php echo $student ? esc_attr( $student->guardian_name ) : ''; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Guardian Phone (SMS)</label>
                    <input type="text" name="guardian_phone" class="form-control" value="<?php echo $student ? esc_attr( $student->guardian_phone ) : ''; ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label fw-bold">Present Address</label>
                    <textarea name="address" class="form-control" rows="2"><?php echo $student ? esc_textarea( $student->address ) : ''; ?></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Account Status</label>
                    <select name="status" class="form-control">
                        <option value="Active" <?php selected( $student ? $student->status : '', 'Active' ); ?>>Active</option>
                        <option value="Inactive" <?php selected( $student ? $student->status : '', 'Inactive' ); ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="educore_save_student" class="btn btn-success px-4 py-2 mt-3" style="background-color: #10b981; border: none;">
                <?php echo $is_edit ? 'Update Student Record' : 'Complete Admission'; ?>
            </button>
        </form>
    </div>
    <?php
}
?>