<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_staff_list_view() {
    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';
    
    // Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to view the staff directory.', 'educore' ) );
    }

    // Fetch records safely directly from DB
    $staff_members = $wpdb->get_results( "SELECT id, wp_user_id, full_name, designation, phone, email, joining_date, salary, profile_image, status FROM {$table_staff} ORDER BY id DESC" );
    $add_url       = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=add' );
    ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><span class="dashicons dashicons-groups text-success me-1"></span> <?php esc_html_e( 'Teachers & Staff Directory', 'educore' ); ?></h2>
        <a href="<?php echo esc_url( $add_url ); ?>" class="btn btn-success fw-bold px-4" style="background-color: #006a4e; border: none;">
            + <?php esc_html_e( 'Add New Staff Member', 'educore' ); ?>
        </a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border">
        <table class="table table-striped table-hover align-middle educore-datatable w-100">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;"><?php esc_html_e( 'Photo', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Name', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Designation', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Contact', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Joining Date', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Salary (৳)', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'educore' ); ?></th>
                    <th style="text-align: right; width: 140px;"><?php esc_html_e( 'Actions', 'educore' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $staff_members ) ) : ?>
                    <?php foreach ( $staff_members as $staff ) : 
                        $edit_url   = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=edit&id=' . absint( $staff->id ) );
                        $delete_url = wp_nonce_url( 
                            admin_url( 'admin.php?page=school_management_system&tab=staff&sub=delete&id=' . absint( $staff->id ) ), 
                            'delete_staff_' . $staff->id 
                        );

                        // Safe date formatting
                        $joining_date = ( ! empty( $staff->joining_date ) && $staff->joining_date !== '1970-01-01' ) 
                            ? date_i18n( 'd M Y', strtotime( $staff->joining_date ) ) 
                            : '—';
                            
                        $salary    = isset( $staff->salary ) ? number_format( (float) $staff->salary, 2 ) : '0.00';
                        $is_active = strtolower( trim( $staff->status ?? '' ) ) === 'active';
                        
                        // Avatar Initial Fallback
                        $first_letter = mb_substr( $staff->full_name ?? 'S', 0, 1, 'utf-8' );
                    ?>
                    <tr>
                        <td>
                            <?php if ( ! empty( $staff->profile_image ) ) : ?>
                                <img src="<?php echo esc_url( $staff->profile_image ); ?>" 
                                     alt="<?php echo esc_attr( $staff->full_name ); ?>" 
                                     class="rounded-circle border" 
                                     style="width: 42px; height: 42px; object-fit: cover;">
                            <?php else : ?>
                                <div class="rounded-circle bg-light text-success fw-bold d-flex align-items-center justify-content-center border" 
                                     style="width: 42px; height: 42px; font-size: 1.1rem; border-color: #006a4e !important;">
                                    <?php echo esc_html( strtoupper( $first_letter ) ); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong class="text-slate-800 d-block"><?php echo esc_html( $staff->full_name ?? '' ); ?></strong>
                            <?php if ( ! empty( $staff->wp_user_id ) ) : ?>
                                <small class="text-muted"><span class="dashicons dashicons-admin-users" style="font-size: 14px; width: 14px; height: 14px;"></span> Linked WP User #<?php echo intval( $staff->wp_user_id ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-secondary px-2 py-1"><?php echo esc_html( $staff->designation ?? 'Staff Member' ); ?></span>
                        </td>
                        <td>
                            <span class="fw-semibold text-slate-700"><?php echo esc_html( $staff->phone ?? 'N/A' ); ?></span><br>
                            <small class="text-muted"><?php echo esc_html( $staff->email ?? '' ); ?></small>
                        </td>
                        <td><?php echo esc_html( $joining_date ); ?></td>
                        <td class="fw-bold text-success">৳<?php echo esc_html( $salary ); ?></td>
                        <td>
                            <span class="badge <?php echo $is_active ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo esc_html( ucfirst( $staff->status ?? 'Inactive' ) ); ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-sm btn-outline-primary me-1">
                                <?php esc_html_e( 'Edit', 'educore' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $delete_url ); ?>" 
                               class="btn btn-sm btn-outline-danger" 
                               onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this staff record?', 'educore' ) ); ?>');">
                                <?php esc_html_e( 'Delete', 'educore' ); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <?php esc_html_e( 'No staff records found in system database.', 'educore' ); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if ($.fn.DataTable && !$.fn.DataTable.isDataTable('.educore-datatable')) {
            $('.educore-datatable').DataTable({
                "pageLength": 15,
                "ordering": true,
                "responsive": true
            });
        }
    });
    </script>
    <?php
}
?>