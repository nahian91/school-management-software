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

    // Active Tab Handler (URL Key)
    $active_tab = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'school_teacher';

    // Map URL tab keys directly to exact DB `staff_type` values stored in form select
    $tab_to_db_map = array(
        'school_teacher'  => 'Teacher (School)',
        'college_teacher' => 'Teacher (College)',
        'staff'           => 'Staff',
        'officer'         => 'Officer',
    );

    // Fallback to School Teacher if tab is invalid
    if ( ! array_key_exists( $active_tab, $tab_to_db_map ) ) {
        $active_tab = 'school_teacher';
    }

    $db_staff_type = $tab_to_db_map[ $active_tab ];

    // Detect Order Column dynamically in db
    $db_columns  = $wpdb->get_col( "DESCRIBE {$table_staff}", 0 );
    $order_col   = 'id';

    if ( in_array( 'sort_order', $db_columns, true ) ) {
        $order_col = 'sort_order';
    } elseif ( in_array( 'serial_number', $db_columns, true ) ) {
        $order_col = 'serial_number';
    } elseif ( in_array( 'position', $db_columns, true ) ) {
        $order_col = 'position';
    } elseif ( in_array( 'order_no', $db_columns, true ) ) {
        $order_col = 'order_no';
    } elseif ( in_array( 'serial', $db_columns, true ) ) {
        $order_col = 'serial';
    }

    // Fetch DB records ordered strictly by DB order column
    $staff_members = $wpdb->get_results( 
        $wpdb->prepare(
            "SELECT *, {$order_col} AS db_order_number 
             FROM {$table_staff} 
             WHERE staff_type = %s 
             ORDER BY {$order_col} ASC, id DESC",
            $db_staff_type
        )
    );

    // Tab Base URL Generator
    $base_tab_url  = admin_url( 'admin.php?page=school_management_system&tab=staff' );
    $school_url    = add_query_arg( 'type', 'school_teacher', $base_tab_url );
    $college_url   = add_query_arg( 'type', 'college_teacher', $base_tab_url );
    $staff_url     = add_query_arg( 'type', 'staff', $base_tab_url );
    $officer_url   = add_query_arg( 'type', 'officer', $base_tab_url );
    $add_url       = add_query_arg( 'sub', 'add', $base_tab_url );
    ?>

    <style>
        /* ==========================================================================
           EDUCORE CATEGORY TABS & ACTION BUTTONS
           ========================================================================== */
        .educore-tabs-wrapper {
            display: flex;
            align-items: center;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
            gap: 6px;
        }

        .educore-tab-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s ease-in-out;
        }

        .educore-tab-item:hover {
            color: #006a4e;
        }

        .educore-tab-item.active {
            color: #006a4e;
            border-bottom-color: #006a4e;
            background-color: #f0fdf4;
            border-top-left-radius: 6px;
            border-top-right-radius: 6px;
        }

        .educore-tab-item .dashicons {
            font-size: 17px;
            width: 17px;
            height: 17px;
            line-height: 1;
        }

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

        /* View Button (Teal Soft) */
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

        /* Edit Button (Indigo Soft) */
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

        /* Delete Button (Rose Soft) */
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

        /* Order Badge Styling */
        .educore-order-badge {
            display: inline-block;
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            font-size: 13px;
            padding: 3px 9px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            min-width: 34px;
            text-align: center;
        }

        /* Employment Type Badge Styling */
        .educore-emp-type-badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 4px;
            background-color: #f0fdf4;
            color: #006a4e;
            border: 1px solid #bbf7d0;
        }
    </style>

    <!-- Header Title & Action CTA -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>
            <span class="dashicons dashicons-groups text-success me-1"></span> 
            <?php esc_html_e( 'Teachers & Staff Directory', 'educore' ); ?>
        </h2>
        <a href="<?php echo esc_url( $add_url ); ?>" class="btn btn-success fw-bold px-4 shadow-sm" style="background-color: #006a4e; border: none; font-size: 14px; padding: 10px 20px;">
            + <?php esc_html_e( 'Add New Staff Member', 'educore' ); ?>
        </a>
    </div>

    <!-- Category Tabs Navigation (4 Tabs) -->
    <div class="educore-tabs-wrapper">
        <a href="<?php echo esc_url( $school_url ); ?>" class="educore-tab-item <?php echo ( $active_tab === 'school_teacher' ) ? 'active' : ''; ?>">
            <span class="dashicons dashicons-welcome-learn-more"></span>
            <?php esc_html_e( 'School Teacher', 'educore' ); ?>
        </a>

        <a href="<?php echo esc_url( $college_url ); ?>" class="educore-tab-item <?php echo ( $active_tab === 'college_teacher' ) ? 'active' : ''; ?>">
            <span class="dashicons dashicons-bank"></span>
            <?php esc_html_e( 'College Teacher', 'educore' ); ?>
        </a>

        <a href="<?php echo esc_url( $staff_url ); ?>" class="educore-tab-item <?php echo ( $active_tab === 'staff' ) ? 'active' : ''; ?>">
            <span class="dashicons dashicons-id"></span>
            <?php esc_html_e( 'Staff', 'educore' ); ?>
        </a>

        <a href="<?php echo esc_url( $officer_url ); ?>" class="educore-tab-item <?php echo ( $active_tab === 'officer' ) ? 'active' : ''; ?>">
            <span class="dashicons dashicons-businessperson"></span>
            <?php esc_html_e( 'Officers', 'educore' ); ?>
        </a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border">
        <table class="table table-striped table-hover align-middle educore-datatable w-100">
            <thead class="table-light">
                <tr>
                    <th style="width: 70px; text-align: center;"><?php esc_html_e( 'Order', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Name', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Designation', 'educore' ); ?></th>
                    <th style="width: 180px;"><?php esc_html_e( 'Employment Type', 'educore' ); ?></th>
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

                        // Order value direct from DB
                        $order_no = isset( $staff->db_order_number ) ? absint( $staff->db_order_number ) : 0;

                        // Primary Name Resolution with Fallback
                        $full_name = ! empty( $staff->name_bn ) ? $staff->name_bn : ( ! empty( $staff->full_name ) ? $staff->full_name : ( ! empty( $staff->name ) ? $staff->name : '' ) );

                        // Employment Type display value stored directly in staff_type
                        $emp_type_label = ! empty( $staff->staff_type ) ? $staff->staff_type : $db_staff_type;
                    ?>
                    <tr>
                        <!-- Order Number Column (from DB) -->
                        <td class="text-center">
                            <span class="educore-order-badge"><?php echo esc_html( $order_no ); ?></span>
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
                                <?php echo esc_html( ! empty( $staff->designation ) ? $staff->designation : __( 'Staff Member', 'educore' ) ); ?>
                            </span>
                        </td>

                        <!-- Employment Type Column -->
                        <td>
                            <span class="educore-emp-type-badge">
                                <?php echo esc_html( $emp_type_label ); ?>
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
                        <td colspan="5" class="text-center py-4 text-muted">
                            <?php esc_html_e( 'No records found for this category.', 'educore' ); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- DataTables Safe Initialization -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if ($.fn.DataTable) {
            if ($.fn.DataTable.isDataTable('.educore-datatable')) {
                $('.educore-datatable').DataTable().destroy();
            }
            $('.educore-datatable').DataTable({
                "pageLength": 15,
                "ordering": true,
                "order": [[0, "asc"]],
                "responsive": true,
                "columnDefs": [
                    { "orderable": false, "targets": [4] }
                ],
                "language": {
                    "emptyTable": "<?php echo esc_js( __( 'No staff records found.', 'educore' ) ); ?>"
                }
            });
        }
    });
    </script>
    <?php
}