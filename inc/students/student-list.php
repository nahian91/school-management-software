<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_students_list_view() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sms_students';
    $students = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );
    
    $add_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=add' );
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><span class="dashicons dashicons-welcome-learn-more"></span> Students Directory</h2>
        <a href="<?php echo esc_url( $add_url ); ?>" class="btn btn-success" style="background-color: #10b981; border: none;">
            + Add New Student
        </a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border">
        <table class="table table-striped table-hover educore-datatable">
            <thead style="background-color: #f8fafc;">
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Section</th>
                    <th>Roll</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $students ) : foreach ( $students as $student ) : 
                    $view_url   = admin_url( 'admin.php?page=school_management_system&tab=students&sub=view&id=' . $student->id );
                    $edit_url   = admin_url( 'admin.php?page=school_management_system&tab=students&sub=edit&id=' . $student->id );
                    $delete_url = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=delete&id=' . $student->id ), 'delete_student_' . $student->id );
                ?>
                <tr>
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
                    <td>
                        <a href="<?php echo esc_url( $view_url ); ?>" class="btn btn-sm btn-info text-white">View</a>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-sm btn-primary">Edit</a>
                        <a href="<?php echo esc_url( $delete_url ); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this student?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.educore-datatable').DataTable({
            "pageLength": 15,
            "ordering": true
        });
    });
    </script>
    <?php
}
?>