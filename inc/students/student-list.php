<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_students_list_view() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sms_students';

    // Handle Enrolment Form Submission inside the Tab
    $success_msg = '';
    $error_msg   = '';

    if ( isset( $_POST['educore_save_student'] ) && wp_verify_nonce( $_POST['educore_student_nonce'], 'save_student_action' ) ) {
        $student_uid = sanitize_text_field( $_POST['student_id'] );
        
        // Duplication Check
        $check_duplicate = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE student_id = %s", $student_uid ) );

        if ( $check_duplicate ) {
            $error_msg = 'The Student ID "' . esc_html($student_uid) . '" is already assigned to another record.';
        } else {
            $photo_url = '';
            // Photo Upload Process
            if ( ! empty( $_FILES['student_photo']['name'] ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $uploaded_file = wp_handle_upload( $_FILES['student_photo'], array( 'test_form' => false ) );
                if ( ! isset( $uploaded_file['error'] ) ) {
                    $photo_url = $uploaded_file['url'];
                }
            }

            $data = array(
                'student_id'     => $student_uid,
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

            $inserted = $wpdb->insert( $table_name, $data );
            if ( $inserted ) {
                $success_msg = 'New student enrolled and added successfully.';
                $_POST = array(); // Clear state
            }
            if ( function_exists( 'educore_log_activity' ) ) {
                educore_log_activity("Saved student record via Directory Tab: " . $data['full_name']);
            }
        }
    }

    // Fetch refreshed list
    $students = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );
    ?>

    <style>
        .dnt-directory-tabs .nav-link { color: #64748b; font-weight: 600; border: none; border-bottom: 2px solid transparent; padding: 10px 20px; }
        .dnt-directory-tabs .nav-link.active { color: #006a4e !important; background: transparent !important; border-color: #006a4e !important; }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><span class="dashicons dashicons-welcome-learn-more"></span> Students Directory</h2>
    </div>

    <!-- Feedback Alerts Component -->
    <?php if ( ! empty( $error_msg ) ) : ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4"><?php echo esc_html( $error_msg ); ?></div>
    <?php endif; ?>
    <?php if ( ! empty( $success_msg ) ) : ?>
        <div class="alert alert-success border-0 shadow-sm mb-4"><?php echo esc_html( $success_msg ); ?></div>
    <?php endif; ?>

    <!-- Navigation Tab Links -->
    <ul class="nav nav-tabs dnt-directory-tabs mb-4" id="directoryTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="all-students-tab" data-bs-toggle="tab" data-bs-target="#all-students" type="button" role="tab">All Students</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="add-student-tab" data-bs-toggle="tab" data-bs-target="#add-student" type="button" role="tab">+ Add Student</button>
        </li>
    </ul>

    <!-- Tab Content Engine -->
    <div class="tab-content" id="directoryTabsContent">
        
        <!-- TAB 1: ALL STUDENTS LIST VIEW -->
        <div class="tab-pane fade show active" id="all-students" role="tabpanel">
            <div class="bg-white p-4 rounded shadow-sm border">
                <table class="table table-striped table-hover educore-datatable align-middle">
                    <thead style="background-color: #f8fafc;">
                        <tr>
                            <th style="width: 60px;">Photo</th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Roll</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $students ) : foreach ( $students as $student ) : 
                            $view_url   = admin_url( 'admin.php?page=school_management_system&tab=students&sub=view&id=' . $student->id );
                            $edit_url   = admin_url( 'admin.php?page=school_management_system&tab=students&sub=edit&id=' . $student->id );
                            $delete_url = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=delete&id=' . $student->id ), 'delete_student_' . $student->id );
                            $first_letter = mb_substr( $student->full_name, 0, 1, 'utf-8' );
                        ?>
                        <tr>
                            <td>
                                <?php if ( ! empty( $student->photo_url ) ) : ?>
                                    <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="Student" class="rounded-circle border shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                                <?php else : ?>
                                    <div class="rounded-circle d-flex align-items-center justify-content-center border shadow-sm" style="width: 40px; height: 40px; background-color: #e6f3ef; color: #006a4e; font-weight: 700; font-size: 1.1rem;">
                                        <?php echo esc_html( $first_letter ); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html( $student->student_id ); ?></strong></td>
                            <td><?php echo esc_html( $student->full_name ); ?></td>
                            <td><?php echo esc_html( $student->class_name ); ?></td>
                            <td><?php echo esc_html( $student->section_name ); ?></td>
                            <td><?php echo esc_html( $student->roll_no ); ?></td>
                            <td>
                                <span class="badge <?php echo $student->status === 'Active' ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo esc_html( $student->status ); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="d-inline-flex gap-2">
                                    <a href="<?php echo esc_url( $view_url ); ?>" class="btn btn-sm btn-info text-white d-inline-flex align-items-center justify-content-center" title="View Details" style="width: 32px; height: 32px; padding: 0; border-radius: 6px;">
                                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                    </a>
                                    <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-sm btn-primary d-inline-flex align-items-center justify-content-center" title="Edit Record" style="width: 32px; height: 32px; padding: 0; border-radius: 6px;">
                                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                    </a>
                                    <a href="<?php echo esc_url( $delete_url ); ?>" class="btn btn-sm btn-danger d-inline-flex align-items-center justify-content-center" title="Delete Student" onclick="return confirm('Are you sure you want to delete this student?');" style="width: 32px; height: 32px; padding: 0; border-radius: 6px;">
                                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: ADMIT/ADD NEW STUDENT FORM VIEW -->
        <div class="tab-pane fade" id="add-student" role="tabpanel">
            <div class="bg-white p-4 rounded shadow-sm border">
                <h3 class="mb-4">Admit New Student</h3>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'save_student_action', 'educore_student_nonce' ); ?>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Student ID (Unique)</label>
                            <input type="text" name="student_id" class="form-control" value="<?php echo isset($_POST['student_id']) ? esc_attr($_POST['student_id']) : ''; ?>" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo isset($_POST['full_name']) ? esc_attr($_POST['full_name']) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Class</label>
                            <input type="text" name="class_name" class="form-control" value="<?php echo isset($_POST['class_name']) ? esc_attr($_POST['class_name']) : ''; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Section</label>
                            <input type="text" name="section_name" class="form-control" value="<?php echo isset($_POST['section_name']) ? esc_attr($_POST['section_name']) : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Roll Number</label>
                            <input type="number" name="roll_no" class="form-control" value="<?php echo isset($_POST['roll_no']) ? esc_attr($_POST['roll_no']) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="<?php echo isset($_POST['dob']) ? esc_attr($_POST['dob']) : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Gender</label>
                            <select name="gender" class="form-control">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Admission Date</label>
                            <input type="date" name="admission_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

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
                            <input type="text" name="guardian_name" class="form-control" value="<?php echo isset($_POST['guardian_name']) ? esc_attr($_POST['guardian_name']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Guardian Phone (SMS)</label>
                            <input type="text" name="guardian_phone" class="form-control" value="<?php echo isset($_POST['guardian_phone']) ? esc_attr($_POST['guardian_phone']) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">Present Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo isset($_POST['address']) ? esc_textarea($_POST['address']) : ''; ?></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Account Status</label>
                            <select name="status" class="form-control">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="educore_save_student" class="btn btn-success px-5 py-2 mt-3" style="background-color: #006a4e; border: none; font-weight: 600;">
                        Complete Admission
                    </button>
                </form>
            </div>
        </div>

    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.educore-datatable').DataTable({
            "pageLength": 15,
            "ordering": true,
            "columnDefs": [
                { "orderable": false, "targets": [0, 7] }
            ]
        });
    });
    </script>
    <?php
}
?>