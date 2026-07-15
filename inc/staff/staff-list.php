<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_staff_list_view() {
    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';
    
    $staff_members = $wpdb->get_results( "SELECT * FROM $table_staff ORDER BY id DESC" );
    $add_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=add' );
    ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><span class="dashicons dashicons-groups"></span> Teachers & Staff Directory</h2>
        <a href="<?php echo esc_url( $add_url ); ?>" class="btn btn-success" style="background-color: #10b981; border: none;">
            + Add New Staff
        </a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border">
        <table class="table table-striped table-hover educore-datatable">
            <thead style="background-color: #f8fafc;">
                <tr>
                    <th>Name</th>
                    <th>Designation</th>
                    <th>Contact</th>
                    <th>Joining Date</th>
                    <th>Salary (৳)</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $staff_members ) : foreach ( $staff_members as $staff ) : 
                    $edit_url   = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=edit&id=' . $staff->id );
                    $delete_url = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=staff&sub=delete&id=' . $staff->id ), 'delete_staff_' . $staff->id );
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html( $staff->full_name ); ?></strong>
                    </td>
                    <td><span class="badge bg-secondary"><?php echo esc_html( $staff->designation ); ?></span></td>
                    <td>
                        <?php echo esc_html( $staff->phone ); ?><br>
                        <small class="text-muted"><?php echo esc_html( $staff->email ); ?></small>
                    </td>
                    <td><?php echo date('d M Y', strtotime($staff->joining_date)); ?></td>
                    <td class="fw-bold text-success"><?php echo number_format( $staff->salary, 2 ); ?></td>
                    <td>
                        <span class="badge <?php echo $staff->status === 'Active' ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo esc_html( $staff->status ); ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-sm btn-primary">Edit</a>
                        <a href="<?php echo esc_url( $delete_url ); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this staff record?');">Delete</a>
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