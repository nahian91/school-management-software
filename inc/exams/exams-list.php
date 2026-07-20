<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_exams_list_view() {
    global $wpdb;
    $table_exams = $wpdb->prefix . 'sms_exams';
    $table_units = $wpdb->prefix . 'sms_academic_units';

    // Security Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to manage exams.', 'educore' ) );
    }

    // Determine Edit Mode
    $is_edit = isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] );
    $edit_id = $is_edit ? intval( $_GET['id'] ) : 0;
    $edit_exam = null;

    if ( $is_edit && $edit_id > 0 ) {
        $edit_exam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_exams} WHERE id = %d", $edit_id ) );
        if ( ! $edit_exam ) {
            $is_edit = false; // Fallback if record does not exist
        }
    }

    // 1. Handle Form Submission (INSERT / UPDATE)
    if ( isset( $_POST['educore_save_exam'] ) && wp_verify_nonce( $_POST['educore_exam_nonce'], 'save_exam_action' ) ) {
        $exam_id_input = isset( $_POST['exam_id'] ) ? intval( $_POST['exam_id'] ) : 0;
        
        $data = array(
            'exam_name'  => sanitize_text_field( $_POST['exam_name'] ),
            'class_name' => sanitize_text_field( $_POST['class_name'] ),
            'start_date' => sanitize_text_field( $_POST['start_date'] ),
            'end_date'   => sanitize_text_field( $_POST['end_date'] ),
            'status'     => sanitize_text_field( $_POST['status'] )
        );
        $format = array( '%s', '%s', '%s', '%s', '%s' );

        if ( $exam_id_input > 0 ) {
            // Update Exam
            $wpdb->update( $table_exams, $data, array( 'id' => $exam_id_input ), $format, array( '%d' ) );
            if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                IFSEdu_School_Management_System::log_activity( "Updated exam scheme: " . $data['exam_name'] );
            }
            $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=list&status=updated' );
            echo '<script type="text/javascript">window.location.href="' . esc_url( $redirect_url ) . '";</script>';
            exit;
        } else {
            // Insert New Exam
            $wpdb->insert( $table_exams, $data, $format );
            if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                IFSEdu_School_Management_System::log_activity( "Created exam scheme: " . $data['exam_name'] );
            }
            $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=list&status=success' );
            echo '<script type="text/javascript">window.location.href="' . esc_url( $redirect_url ) . '";</script>';
            exit;
        }
    }

    // 2. Handle Delete Exam
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
        $delete_id = intval( $_GET['id'] );
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_exam_' . $delete_id ) ) {
            $wpdb->delete( $table_exams, array( 'id' => $delete_id ), array( '%d' ) );
            if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                IFSEdu_School_Management_System::log_activity( "Deleted exam ID: " . $delete_id );
            }
            $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=list&status=deleted' );
            echo '<script type="text/javascript">window.location.href="' . esc_url( $redirect_url ) . '";</script>';
            exit;
        }
    }

    // Fetch classes dynamically from academic units table
    $dynamic_classes = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} ORDER BY class_name ASC" );
    $exams           = $wpdb->get_results( "SELECT * FROM {$table_exams} ORDER BY id DESC" );
    
    $marks_url  = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=marks' );
    $cancel_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=list' );
    ?>

    <!-- Status Alert Bar -->
    <?php if ( isset( $_GET['status'] ) ) : ?>
        <div class="alert alert-success border-0 shadow-sm mb-4 alert-dismissible fade show" role="alert">
            <?php 
                if ( $_GET['status'] === 'success' ) echo 'New examination scheme created successfully.';
                if ( $_GET['status'] === 'updated' ) echo 'Examination details updated successfully.';
                if ( $_GET['status'] === 'deleted' ) echo 'Examination record removed successfully.';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><span class="dashicons dashicons-welcome-write-blog text-success"></span> Examinations & Scheme Setup</h2>
        <a href="<?php echo esc_url( $marks_url ); ?>" class="btn btn-primary fw-bold px-4" style="background-color: #006a4e; border: none;">
            Enter Marks Matrix &rarr;
        </a>
    </div>

    <div class="row">
        <!-- Add / Edit Exam Form -->
        <div class="col-md-4 mb-4">
            <div class="bg-white p-4 rounded shadow-sm border">
                <h4 class="border-bottom pb-2 mb-3 text-success fw-bold">
                    <?php echo $is_edit ? 'Edit Exam Scheme' : 'Create New Exam'; ?>
                </h4>
                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_exam_action', 'educore_exam_nonce' ); ?>
                    <input type="hidden" name="exam_id" value="<?php echo $is_edit ? intval( $edit_exam->id ) : 0; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Exam Name <span class="text-danger">*</span></label>
                        <input type="text" name="exam_name" class="form-control" placeholder="e.g. First Term / Annual Exam" value="<?php echo $is_edit ? esc_attr( $edit_exam->exam_name ) : ''; ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Class / Tier <span class="text-danger">*</span></label>
                        <select name="class_name" class="form-select" required>
                            <option value="All Classes" <?php selected( $is_edit ? $edit_exam->class_name : '', 'All Classes' ); ?>>All Classes</option>
                            <?php if ( ! empty( $dynamic_classes ) ) : foreach ( $dynamic_classes as $cls ) : ?>
                                <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $is_edit ? $edit_exam->class_name : '', $cls ); ?>>
                                    <?php echo esc_html( $cls ); ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $is_edit ? esc_attr( $edit_exam->start_date ) : date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">End Date <span class="text-danger">*</span></label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $is_edit ? esc_attr( $edit_exam->end_date ) : date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Status</label>
                        <select name="status" class="form-select">
                            <option value="Upcoming" <?php selected( $is_edit ? $edit_exam->status : '', 'Upcoming' ); ?>>Upcoming</option>
                            <option value="Ongoing" <?php selected( $is_edit ? $edit_exam->status : '', 'Ongoing' ); ?>>Ongoing</option>
                            <option value="Completed" <?php selected( $is_edit ? $edit_exam->status : '', 'Completed' ); ?>>Completed</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="educore_save_exam" class="btn btn-success flex-grow-1 fw-bold" style="background-color: #006a4e; border: none;">
                            <?php echo $is_edit ? 'Update Exam' : 'Save Exam'; ?>
                        </button>
                        <?php if ( $is_edit ) : ?>
                            <a href="<?php echo esc_url( $cancel_url ); ?>" class="btn btn-outline-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Exam List Table -->
        <div class="col-md-8 mb-4">
            <div class="bg-white p-4 rounded shadow-sm border">
                <table class="table table-striped table-hover align-middle educore-datatable w-100">
                    <thead style="background-color: #f8fafc;">
                        <tr>
                            <th>Exam Name</th>
                            <th>Class</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th style="text-align: right; width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $exams ) ) : foreach ( $exams as $exam ) : 
                            $edit_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=list&action=edit&id=' . $exam->id );
                            $del_url  = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=exams&sub=list&action=delete&id=' . $exam->id ), 'delete_exam_' . $exam->id );
                        ?>
                        <tr class="<?php echo ($is_edit && $edit_id === intval($exam->id)) ? 'table-warning' : ''; ?>">
                            <td><strong><?php echo esc_html( $exam->exam_name ); ?></strong></td>
                            <td><span class="badge bg-light text-dark border"><?php echo esc_html( $exam->class_name ); ?></span></td>
                            <td>
                                <small class="text-muted fw-semibold">
                                    <?php echo date('d M Y', strtotime($exam->start_date)); ?> - <?php echo date('d M Y', strtotime($exam->end_date)); ?>
                                </small>
                            </td>
                            <td>
                                <?php 
                                    $badge_class = 'bg-secondary';
                                    if ( $exam->status === 'Completed' ) $badge_class = 'bg-success';
                                    if ( $exam->status === 'Ongoing' )   $badge_class = 'bg-primary';
                                    if ( $exam->status === 'Upcoming' )  $badge_class = 'bg-warning text-dark';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo esc_html( $exam->status ); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-sm btn-outline-primary" title="Edit Exam">
                                        <span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                    </a>
                                    <a href="<?php echo esc_url( $del_url ); ?>" class="btn btn-sm btn-outline-danger" title="Delete Exam" onclick="return confirm('Delete this exam permanently?');">
                                        <span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if ($.fn.DataTable) {
            $('.educore-datatable').DataTable({ "pageLength": 10 });
        }
    });
    </script>
    <?php
}