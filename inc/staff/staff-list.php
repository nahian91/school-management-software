<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access lockdown
}

function educore_staff_list_view() {
    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';
    
    // 1. Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to view the staff directory.', 'educore' ) );
    }

    // 2. Fetch staff records safely directly from database
    $staff_members = $wpdb->get_results( 
        "SELECT id, wp_user_id, full_name, designation, phone, email, joining_date, salary, profile_image, status 
         FROM {$table_staff} 
         ORDER BY id DESC" 
    );

    $add_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=add' );
    ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>
            <span class="dashicons dashicons-groups text-success me-1"></span> 
            <?php esc_html_e( 'Teachers & Staff Directory', 'educore' ); ?>
        </h2>
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
                    <th style="text-align: right; width: 190px;"><?php esc_html_e( 'Actions', 'educore' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $staff_members ) ) : ?>
                    <?php foreach ( $staff_members as $staff ) : 
                        $staff_id   = absint( $staff->id );
                        $view_url   = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=view&id=' . $staff_id );
                        $edit_url   = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=edit&id=' . $staff_id );
                        $delete_url = wp_nonce_url( 
                            admin_url( 'admin.php?page=school_management_system&tab=staff&sub=delete&id=' . $staff_id ), 
                            'delete_staff_' . $staff_id 
                        );

                        // Safe Date Formatting
                        $joining_date = ( ! empty( $staff->joining_date ) && $staff->joining_date !== '1970-01-01' ) 
                            ? date_i18n( get_option( 'date_format', 'd M Y' ), strtotime( $staff->joining_date ) ) 
                            : '—';
                            
                        $salary    = isset( $staff->salary ) ? number_format( (float) $staff->salary, 2 ) : '0.00';
                        $is_active = strtolower( trim( $staff->status ?? '' ) ) === 'active';
                        
                        // Bengali & Multibyte Initial Fallback
                        $full_name    = $staff->full_name ?? 'S';
                        $first_letter = mb_substr( $full_name, 0, 1, 'UTF-8' );
                    ?>
                    <tr>
                        <!-- Photo Avatar -->
                        <td>
                            <?php if ( ! empty( $staff->profile_image ) ) : ?>
                                <img src="<?php echo esc_url( $staff->profile_image ); ?>" 
                                     alt="<?php echo esc_attr( $full_name ); ?>" 
                                     class="rounded-circle border" 
                                     style="width: 42px; height: 42px; object-fit: cover;">
                            <?php else : ?>
                                <div class="rounded-circle bg-light text-success fw-bold d-flex align-items-center justify-content-center border" 
                                     style="width: 42px; height: 42px; font-size: 1.1rem; border-color: #006a4e !important;">
                                    <?php echo esc_html( mb_strtoupper( $first_letter, 'UTF-8' ) ); ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Name & WP User Link -->
                        <td>
                            <strong class="text-slate-800 d-block"><?php echo esc_html( $full_name ); ?></strong>
                            <?php if ( ! empty( $staff->wp_user_id ) ) : ?>
                                <small class="text-muted">
                                    <span class="dashicons dashicons-admin-users" style="font-size: 14px; width: 14px; height: 14px;"></span> 
                                    <?php printf( esc_html__( 'Linked WP User #%d', 'educore' ), absint( $staff->wp_user_id ) ); ?>
                                </small>
                            <?php endif; ?>
                        </td>

                        <!-- Designation -->
                        <td>
                            <span class="badge bg-secondary px-2 py-1">
                                <?php echo esc_html( $staff->designation ?? __( 'Staff Member', 'educore' ) ); ?>
                            </span>
                        </td>

                        <!-- Contact Info -->
                        <td>
                            <span class="fw-semibold text-slate-700"><?php echo esc_html( $staff->phone ?? 'N/A' ); ?></span><br>
                            <small class="text-muted"><?php echo esc_html( $staff->email ?? '' ); ?></small>
                        </td>

                        <!-- Joining Date & Salary -->
                        <td><?php echo esc_html( $joining_date ); ?></td>
                        <td class="fw-bold text-success">৳<?php echo esc_html( $salary ); ?></td>

                        <!-- Status Badge -->
                        <td>
                            <span class="badge <?php echo $is_active ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo esc_html( ucfirst( $staff->status ?? 'Inactive' ) ); ?>
                            </span>
                        </td>

                        <!-- Action Buttons -->
                        <td style="text-align: right;">
                            <a href="<?php echo esc_url( $view_url ); ?>" class="btn btn-sm btn-outline-info me-1" title="<?php esc_attr_e( 'View Profile', 'educore' ); ?>">
                                <?php esc_html_e( 'View', 'educore' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-sm btn-outline-primary me-1" title="<?php esc_attr_e( 'Edit Record', 'educore' ); ?>">
                                <?php esc_html_e( 'Edit', 'educore' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $delete_url ); ?>" 
                               class="btn btn-sm btn-outline-danger" 
                               title="<?php esc_attr_e( 'Delete Record', 'educore' ); ?>"
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

    <!-- DataTables Safe Initialization -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if ($.fn.DataTable && !$.fn.DataTable.isDataTable('.educore-datatable')) {
            $('.educore-datatable').DataTable({
                "pageLength": 15,
                "ordering": true,
                "responsive": true,
                "language": {
                    "emptyTable": "<?php echo esc_js( __( 'No staff records found.', 'educore' ) ); ?>"
                }
            });
        }
    });
    </script>
    <?php
}
?>