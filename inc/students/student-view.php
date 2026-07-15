<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_student_profile_view() {
    global $wpdb;
    $student_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    if ( ! $student_id ) return;

    $table_name = $wpdb->prefix . 'sms_students';
    $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $student_id ) );

    if ( ! $student ) {
        echo '<div class="alert alert-danger">Student not found.</div>';
        return;
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );
    $edit_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=edit&id=' . $student->id );
    ?>
    <div class="d-flex justify-content-between mb-3">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; Back to List</a>
        <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-primary btn-sm">Edit Profile</a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border">
        <div class="row">
            <div class="col-md-3 text-center border-end">
                <div class="mb-3">
                    <div style="width: 120px; height: 120px; background: #e2e8f0; border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #94a3b8;">
                        <span class="dashicons dashicons-businessman" style="font-size: 60px; width: 60px; height: 60px;"></span>
                    </div>
                </div>
                <h4 class="mb-0 text-dark"><?php echo esc_html( $student->full_name ); ?></h4>
                <p class="text-muted mb-2">ID: <?php echo esc_html( $student->student_id ); ?></p>
                <span class="badge <?php echo $student->status === 'Active' ? 'bg-success' : 'bg-danger'; ?> px-3 py-2 rounded-pill">
                    <?php echo esc_html( $student->status ); ?>
                </span>
            </div>
            
            <div class="col-md-9 ps-md-4">
                <h5 class="border-bottom pb-2 mb-3 text-primary">Academic Information</h5>
                <div class="row mb-4">
                    <div class="col-sm-4"><strong class="text-muted">Class:</strong><br> <?php echo esc_html( $student->class_name ); ?></div>
                    <div class="col-sm-4"><strong class="text-muted">Section:</strong><br> <?php echo esc_html( $student->section_name ); ?></div>
                    <div class="col-sm-4"><strong class="text-muted">Roll No:</strong><br> <?php echo esc_html( $student->roll_no ); ?></div>
                </div>

                <h5 class="border-bottom pb-2 mb-3 text-primary">Personal Details</h5>
                <div class="row mb-4">
                    <div class="col-sm-4"><strong class="text-muted">Date of Birth:</strong><br> <?php echo date("d F Y", strtotime($student->dob)); ?></div>
                    <div class="col-sm-4"><strong class="text-muted">Gender:</strong><br> <?php echo esc_html( $student->gender ); ?></div>
                    <div class="col-sm-4"><strong class="text-muted">Admission Date:</strong><br> <?php echo date("d F Y", strtotime($student->admission_date)); ?></div>
                </div>

                <h5 class="border-bottom pb-2 mb-3 text-primary">Guardian & Contact</h5>
                <div class="row">
                    <div class="col-sm-6"><strong class="text-muted">Guardian Name:</strong><br> <?php echo esc_html( $student->guardian_name ); ?></div>
                    <div class="col-sm-6"><strong class="text-muted">Contact Phone:</strong><br> <?php echo esc_html( $student->guardian_phone ); ?></div>
                    <div class="col-sm-12 mt-3"><strong class="text-muted">Address:</strong><br> <?php echo nl2br( esc_html( $student->address ) ); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>