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
        
        $data = array(
            'full_name'     => sanitize_text_field( $_POST['full_name'] ),
            'designation'   => sanitize_text_field( $_POST['designation'] ),
            'phone'         => sanitize_text_field( $_POST['phone'] ),
            'email'         => sanitize_email( $_POST['email'] ),
            'joining_date'  => sanitize_text_field( $_POST['joining_date'] ),
            'salary'        => floatval( $_POST['salary'] ),
            'status'        => sanitize_text_field( $_POST['status'] )
        );

        if ( $is_edit ) {
            $wpdb->update( $table_staff, $data, array( 'id' => $staff_id ) );
            echo '<div class="alert alert-success">Staff profile updated successfully.</div>';
            $staff = (object) array_merge( (array) $staff, $data ); // Update local object for display
        } else {
            $wpdb->insert( $table_staff, $data );
            echo '<div class="alert alert-success">New staff member added successfully.</div>';
            $_POST = array(); // Clear form after insert
        }

        if ( function_exists('educore_log_activity') ) {
            educore_log_activity("Saved staff record: " . $data['full_name']);
        }
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=list' );
    ?>

    <div class="mb-3">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; Back to Directory</a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border" style="max-width: 900px;">
        <h3 class="border-bottom pb-2 mb-4 text-primary"><?php echo $is_edit ? 'Edit Staff Details' : 'Add New Staff / Teacher'; ?></h3>
        
        <form method="POST" action="">
            <?php wp_nonce_field( 'save_staff_action', 'educore_staff_nonce' ); ?>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo $staff ? esc_attr( $staff->full_name ) : ''; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Designation (Role)</label>
                    <select name="designation" class="form-control" required>
                        <option value="">-- Select Designation --</option>
                        <option value="Headmaster / Principal" <?php selected( $staff ? $staff->designation : '', 'Headmaster / Principal' ); ?>>Headmaster / Principal</option>
                        <option value="Senior Teacher" <?php selected( $staff ? $staff->designation : '', 'Senior Teacher' ); ?>>Senior Teacher</option>
                        <option value="Assistant Teacher" <?php selected( $staff ? $staff->designation : '', 'Assistant Teacher' ); ?>>Assistant Teacher</option>
                        <option value="Accountant" <?php selected( $staff ? $staff->designation : '', 'Accountant' ); ?>>Accountant</option>
                        <option value="Admin Staff" <?php selected( $staff ? $staff->designation : '', 'Admin Staff' ); ?>>Admin Staff</option>
                        <option value="Support Staff" <?php selected( $staff ? $staff->designation : '', 'Support Staff' ); ?>>Support Staff</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Mobile Number</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo $staff ? esc_attr( $staff->phone ) : ''; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo $staff ? esc_attr( $staff->email ) : ''; ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Joining Date</label>
                    <input type="date" name="joining_date" class="form-control" value="<?php echo $staff ? esc_attr( $staff->joining_date ) : date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Basic Salary (৳)</label>
                    <input type="number" step="0.01" name="salary" class="form-control" value="<?php echo $staff ? esc_attr( $staff->salary ) : '0.00'; ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Account Status</label>
                    <select name="status" class="form-control">
                        <option value="Active" <?php selected( $staff ? $staff->status : '', 'Active' ); ?>>Active</option>
                        <option value="Resigned" <?php selected( $staff ? $staff->status : '', 'Resigned' ); ?>>Resigned / Left</option>
                        <option value="Suspended" <?php selected( $staff ? $staff->status : '', 'Suspended' ); ?>>Suspended</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="educore_save_staff" class="btn btn-success px-5 py-2 mt-3" style="background-color: #10b981; border: none; font-weight: bold;">
                <?php echo $is_edit ? 'Update Record' : 'Save Staff Member'; ?>
            </button>
        </form>
    </div>
    <?php
}
?>