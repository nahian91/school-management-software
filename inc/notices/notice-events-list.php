<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Common Listing Directory for Notices & Academic Events
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

    <div class="bg-white p-4 rounded shadow-sm border">
        <h3 class="pb-2 mb-4 text-success fw-bold border-bottom">
            <span class="dashicons <?php echo $is_event_mode ? 'dashicons-calendar-alt' : 'dashicons-megaphone'; ?>"></span>
            <?php echo $is_event_mode ? esc_html__( 'Academic Events Directory', 'ifsedu-sms' ) : esc_html__( 'Official Notice Board', 'ifsedu-sms' ); ?>
        </h3>

        <table class="table table-striped table-hover align-middle educore-datatable w-100">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th><?php esc_html_e( 'Title', 'ifsedu-sms' ); ?></th>
                    <th><?php esc_html_e( 'Target Audience', 'ifsedu-sms' ); ?></th>
                    <th><?php echo $is_event_mode ? esc_html__( 'Event Date', 'ifsedu-sms' ) : esc_html__( 'Publish Date', 'ifsedu-sms' ); ?></th>
                    <th><?php esc_html_e( 'Priority', 'ifsedu-sms' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></th>
                    <th style="text-align: right; width: 190px;"><?php esc_html_e( 'Actions', 'ifsedu-sms' ); ?></th>
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
                    ?>
                    <tr>
                        <td>#<?php echo $id; ?></td>
                        <td>
                            <strong class="text-dark d-block"><?php echo esc_html( $row->title ); ?></strong>
                            <?php if ( ! empty( $row->attachment_url ) ) : ?>
                                <small>
                                    <a href="<?php echo esc_url( $row->attachment_url ); ?>" target="_blank" class="text-success text-decoration-none">
                                        <span class="dashicons dashicons-paperclip"></span> <?php esc_html_e( 'Attachment Available', 'ifsedu-sms' ); ?>
                                    </a>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?php echo esc_html( $row->target_audience ); ?></span></td>
                        <td><?php echo esc_html( $display_date ); ?></td>
                        <td><span class="badge bg-warning text-dark"><?php echo esc_html( $row->priority ); ?></span></td>
                        <td><span class="badge bg-success"><?php echo esc_html( $row->status ); ?></span></td>
                        <td style="text-align: right;">
                            <a href="<?php echo esc_url( $view_url ); ?>" class="btn btn-sm btn-outline-info me-1"><?php esc_html_e( 'View', 'ifsedu-sms' ); ?></a>
                            <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-sm btn-outline-primary me-1"><?php esc_html_e( 'Edit', 'ifsedu-sms' ); ?></a>
                            <a href="<?php echo esc_url( $delete_url ); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this record?', 'ifsedu-sms' ) ); ?>');"><?php esc_html_e( 'Delete', 'ifsedu-sms' ); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted"><?php esc_html_e( 'No records found.', 'ifsedu-sms' ); ?></td>
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