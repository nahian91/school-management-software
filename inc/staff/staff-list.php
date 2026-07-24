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
        "SELECT id, wp_user_id, full_name, name_bn, designation, profile_image 
         FROM {$table_staff} 
         ORDER BY id DESC" 
    );

    $add_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=add' );
    ?>

    <style>
        /* ==========================================================================
           EDUCORE MODERN ACTION BUTTONS WITH SVG ICONS
           ========================================================================== */
        .educore-action-group {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            justify-content: flex-end;
        }

        .educore-btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 6px 12px;
            font-size: 12.5px;
            font-weight: 700;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            line-height: 1;
            cursor: pointer;
        }

        .educore-btn-action svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
            flex-shrink: 0;
        }

        /* View Button (Teal / Cyan Soft) */
        .educore-btn-view {
            background-color: #f0fdf4;
            color: #006a4e;
            border-color: #bbf7d0;
        }
        .educore-btn-view:hover {
            background-color: #006a4e;
            color: #ffffff;
            border-color: #006a4e;
            box-shadow: 0 2px 6px rgba(0, 106, 78, 0.25);
        }

        /* Edit Button (Indigo / Blue Soft) */
        .educore-btn-edit {
            background-color: #eff6ff;
            color: #2563eb;
            border-color: #bfdbfe;
        }
        .educore-btn-edit:hover {
            background-color: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
        }

        /* Delete Button (Rose / Red Soft) */
        .educore-btn-delete {
            background-color: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }
        .educore-btn-delete:hover {
            background-color: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>
            <span class="dashicons dashicons-groups text-success me-1"></span> 
            <?php esc_html_e( 'Teachers & Staff Directory', 'educore' ); ?>
        </h2>
        <a href="<?php echo esc_url( $add_url ); ?>" class="btn btn-success fw-bold px-4 shadow-sm" style="background-color: #006a4e; border: none; font-size: 14px; padding: 10px 20px;">
            + <?php esc_html_e( 'Add New Staff Member', 'educore' ); ?>
        </a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border">
        <table class="table table-striped table-hover align-middle educore-datatable w-100">
            <thead class="table-light">
                <tr>
                    <th style="width: 65px;"><?php esc_html_e( 'Photo', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Name', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Designation', 'educore' ); ?></th>
                    <th style="text-align: right; width: 220px;"><?php esc_html_e( 'Actions', 'educore' ); ?></th>
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

                        // Fallback handling for Bengali & Multibyte Name Initials
                        $full_name    = ! empty( $staff->name_bn ) ? $staff->name_bn : ( $staff->full_name ?? 'S' );
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
                            <span class="badge bg-secondary px-2 py-1" style="font-weight: 600; font-size: 12px;">
                                <?php echo esc_html( $staff->designation ?? __( 'Staff Member', 'educore' ) ); ?>
                            </span>
                        </td>

                        <!-- Action Buttons with Modern SVG Icons -->
                        <td style="text-align: right;">
                            <div class="educore-action-group">
                                <!-- View Button -->
                                <a href="<?php echo esc_url( $view_url ); ?>" class="educore-btn-action educore-btn-view" title="<?php esc_attr_e( 'View Profile', 'educore' ); ?>">
                                    <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                    <?php esc_html_e( 'View', 'educore' ); ?>
                                </a>

                                <!-- Edit Button -->
                                <a href="<?php echo esc_url( $edit_url ); ?>" class="educore-btn-action educore-btn-edit" title="<?php esc_attr_e( 'Edit Record', 'educore' ); ?>">
                                    <svg viewBox="0 0 24 24"><path d="M3 17.25V21h4.75L17.81 9.94l-4.75-4.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 4.75 4.75 1.83-1.83z"/></svg>
                                    <?php esc_html_e( 'Edit', 'educore' ); ?>
                                </a>

                                <!-- Delete Button -->
                                <a href="<?php echo esc_url( $delete_url ); ?>" 
                                   class="educore-btn-action educore-btn-delete" 
                                   title="<?php esc_attr_e( 'Delete Record', 'educore' ); ?>"
                                   onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this staff record?', 'educore' ) ); ?>');">
                                    <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                    <?php esc_html_e( 'Delete', 'educore' ); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4" class="text-center py-4 text-muted">
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