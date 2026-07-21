<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Common Listing Directory for Notices & Academic Events
 * Theme Aesthetic: Elite Neo-Bento UI & Data Architecture
 * Custom Prefixes Applied: dpt-, afdp-
 */
function educore_notice_events_list_view( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this module.', 'ifsedu-sms' ) );
    }

    $is_event_mode = ( $type === 'events' );
    $filter_type   = $is_event_mode ? 'Event' : 'Notice';
    
    // Query records based on type filter
    $records = $wpdb->get_results( 
        $wpdb->prepare( "SELECT * FROM {$table_notices} WHERE notice_type = %s ORDER BY id DESC", $filter_type ) 
    );
    ?>

    <style>
        /* ==========================================================================
           NOTICE/EVENTS DIRECTORY - NEO-BENTO TABLE SYSTEM
           ========================================================================== */
        .dpt-list-root {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #0f172a;
        }

        .dpt-bento-card-table {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-table-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .afdp-table-title {
            font-size: 20px;
            font-weight: 800;
            color: #006a4e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.4px;
        }

        /* Responsive Datatable Container */
        .dpt-responsive-datatable {
            width: 100%;
            overflow-x: auto;
        }

        .dpt-architecture-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13.5px;
        }

        .dpt-architecture-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            white-space: nowrap;
        }

        .dpt-architecture-table td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .dpt-architecture-table tbody tr:hover td {
            background-color: #f8fafc;
        }

        /* Status & Priority Badge Engine */
        .dpt-badge-node {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }

        /* Audience Badge */
        .dpt-badge-audience { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

        /* Priority Badges */
        .dpt-priority-normal { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
        .dpt-priority-high   { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .dpt-priority-urgent { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        /* Status Badges */
        .dpt-status-published { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .dpt-status-draft     { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        /* Square Action Buttons */
        .dpt-actions-flex {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .dpt-square-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .dpt-square-btn .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }

        .dpt-btn-view { background: #f0f9ff; color: #0284c7; border-color: #bae6fd; }
        .dpt-btn-view:hover { background: #0284c7; color: #ffffff; }

        .dpt-btn-edit { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
        .dpt-btn-edit:hover { background: #16a34a; color: #ffffff; }

        .dpt-btn-delete { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .dpt-btn-delete:hover { background: #dc2626; color: #ffffff; }

        /* Attachment Link */
        .dpt-attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 4px;
            font-size: 11.5px;
            color: #006a4e;
            text-decoration: none;
            font-weight: 600;
        }

        .dpt-attachment-link:hover {
            text-decoration: underline;
        }

        /* DataTables Overrides for Neo Bento Integration */
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            padding: 6px 10px !important;
            background-color: #f8fafc !important;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #006a4e !important;
            color: #ffffff !important;
            border: none !important;
            border-radius: 6px !important;
        }
    </style>

    <div class="dpt-list-root">
        <div class="dpt-bento-card-table">
            
            <div class="afdp-table-header">
                <h3 class="afdp-table-title">
                    <span class="dashicons <?php echo $is_event_mode ? 'dashicons-calendar-alt' : 'dashicons-megaphone'; ?>"></span>
                    <?php echo $is_event_mode ? esc_html__( 'Academic Events Directory', 'ifsedu-sms' ) : esc_html__( 'Official Notice Board', 'ifsedu-sms' ); ?>
                </h3>
            </div>

            <div class="dpt-responsive-datatable">
                <table class="dpt-architecture-table educore-datatable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th><?php esc_html_e( 'Title & Details', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Target Audience', 'ifsedu-sms' ); ?></th>
                            <th><?php echo $is_event_mode ? esc_html__( 'Event Date', 'ifsedu-sms' ) : esc_html__( 'Publish Date', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Priority', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></th>
                            <th style="text-align: right; width: 120px;"><?php esc_html_e( 'Actions', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $records ) ) : ?>
                            <?php foreach ( $records as $row ) : 
                                $id         = absint( $row->id );
                                $view_url   = admin_url( 'admin.php?page=school_management_system&tab=notice&type=' . $type . '&sub=view&id=' . $id );
                                $edit_url   = admin_url( 'admin.php?page=school_management_system&tab=notice&type=' . $type . '&sub=edit&id=' . $id );
                                $delete_url = wp_nonce_url( 
                                    admin_url( 'admin.php?page=school_management_system&tab=notice&type=' . $type . '&sub=delete&id=' . $id ), 
                                    'delete_item_' . $id 
                                );

                                $display_date = ( ! empty( $row->event_date ) && $row->event_date !== '1970-01-01' ) 
                                    ? date_i18n( 'd M Y', strtotime( $row->event_date ) ) 
                                    : date_i18n( 'd M Y', strtotime( $row->created_at ) );

                                // Priority Dynamic Class
                                $priority_class = 'dpt-priority-normal';
                                if ( $row->priority === 'High' ) {
                                    $priority_class = 'dpt-priority-high';
                                } elseif ( $row->priority === 'Urgent' ) {
                                    $priority_class = 'dpt-priority-urgent';
                                }

                                // Status Dynamic Class
                                $status_class = ( $row->status === 'Published' ) ? 'dpt-status-published' : 'dpt-status-draft';
                            ?>
                            <tr>
                                <td style="font-weight: 700; color: #64748b;">#<?php echo $id; ?></td>
                                <td>
                                    <strong style="color: #0f172a; display: block; font-size: 14px;"><?php echo esc_html( $row->title ); ?></strong>
                                    <?php if ( ! empty( $row->attachment_url ) ) : ?>
                                        <a href="<?php echo esc_url( $row->attachment_url ); ?>" target="_blank" class="dpt-attachment-link">
                                            <span class="dashicons dashicons-paperclip"></span>
                                            <?php esc_html_e( 'Attachment Available', 'ifsedu-sms' ); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="dpt-badge-node dpt-badge-audience">
                                        <?php echo esc_html( $row->target_audience ); ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600; color: #334155;"><?php echo esc_html( $display_date ); ?></td>
                                <td>
                                    <span class="dpt-badge-node <?php echo esc_attr( $priority_class ); ?>">
                                        <?php echo esc_html( $row->priority ); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="dpt-badge-node <?php echo esc_attr( $status_class ); ?>">
                                        <?php echo esc_html( $row->status ); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="dpt-actions-flex">
                                        <a href="<?php echo esc_url( $view_url ); ?>" class="dpt-square-btn dpt-btn-view" title="<?php esc_attr_e( 'View', 'ifsedu-sms' ); ?>">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </a>
                                        <a href="<?php echo esc_url( $edit_url ); ?>" class="dpt-square-btn dpt-btn-edit" title="<?php esc_attr_e( 'Edit', 'ifsedu-sms' ); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                        <a href="<?php echo esc_url( $delete_url ); ?>" class="dpt-square-btn dpt-btn-delete" title="<?php esc_attr_e( 'Delete', 'ifsedu-sms' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this record?', 'ifsedu-sms' ) ); ?>');">
                                            <span class="dashicons dashicons-trash"></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">
                                    <?php esc_html_e( 'No records found.', 'ifsedu-sms' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if ($.fn.DataTable && !$.fn.DataTable.isDataTable('.educore-datatable')) {
            $('.educore-datatable').DataTable({
                "pageLength": 15,
                "ordering": true,
                "responsive": true,
                "language": {
                    "search": "Filter Records:"
                }
            });
        }
    });
    </script>
    <?php
}

/**
 * Handle Record Deletion
 */
function educore_notice_events_delete_action( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-sms' ) );
    }

    $id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $_nonce = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';

    if ( $id > 0 && wp_verify_nonce( $_nonce, 'delete_item_' . $id ) ) {
        $wpdb->delete( $table_notices, array( 'id' => $id ), array( '%d' ) );
    }

    $target_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=' . $type . '&sub=list' );
    educore_safe_redirect( $target_url );
}