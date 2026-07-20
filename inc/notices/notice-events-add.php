<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared Form View for Adding and Editing Notices & Events
 */
function educore_notice_events_add_edit_view( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-sms' ) );
    }

    $is_edit = isset( $_GET['sub'] ) && $_GET['sub'] === 'edit';
    $id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

    $item = null;
    if ( $is_edit && $id > 0 ) {
        $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_notices} WHERE id = %d", $id ) );
    }

    // Handle Form Processing
    if ( isset( $_POST['educore_save_item'] ) && wp_verify_nonce( $_POST['educore_item_nonce'], 'save_item_action' ) ) {
        $attachment_url = $item ? $item->attachment_url : '';

        if ( ! empty( $_FILES['item_file']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $upload = wp_handle_upload( $_FILES['item_file'], array( 'test_form' => false ) );
            if ( ! isset( $upload['error'] ) ) {
                $attachment_url = $upload['url'];
            }
        }

        $data = array(
            'title'           => sanitize_text_field( $_POST['title'] ),
            'notice_type'     => ( $type === 'events' ) ? 'Event' : sanitize_text_field( $_POST['notice_type'] ),
            'priority'        => sanitize_text_field( $_POST['priority'] ),
            'target_audience' => sanitize_text_field( $_POST['target_audience'] ),
            'description'     => wp_kses_post( $_POST['description'] ),
            'event_date'      => ! empty( $_POST['event_date'] ) ? sanitize_text_field( $_POST['event_date'] ) : NULL,
            'attachment_url'  => sanitize_url( $attachment_url ),
            'created_by'      => get_current_user_id(),
            'status'          => sanitize_text_field( $_POST['status'] )
        );

        if ( $is_edit && $id > 0 ) {
            $wpdb->update( $table_notices, $data, array( 'id' => $id ) );
            echo '<div class="alert alert-success">' . esc_html__( 'Record updated successfully.', 'ifsedu-sms' ) . '</div>';
            $item = (object) array_merge( (array) $item, $data );
        } else {
            $wpdb->insert( $table_notices, $data );
            echo '<div class="alert alert-success">' . esc_html__( 'Published successfully.', 'ifsedu-sms' ) . '</div>';
            $_POST = array();
            $item  = null;
        }
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=' . $type . '&sub=list' );
    ?>

    <div class="mb-3">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; <?php esc_html_e( 'Back to List', 'ifsedu-sms' ); ?></a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border">
        <h3 class="pb-2 mb-4 text-success fw-bold border-bottom">
            <?php echo $is_edit ? esc_html__( 'Edit Record', 'ifsedu-sms' ) : esc_html__( 'Add New Record', 'ifsedu-sms' ); ?>
        </h3>

        <form method="POST" action="" enctype="multipart/form-data">
            <?php wp_nonce_field( 'save_item_action', 'educore_item_nonce' ); ?>

            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label fw-bold"><?php esc_html_e( 'Title', 'ifsedu-sms' ); ?></label>
                    <input type="text" name="title" class="form-control" value="<?php echo $item ? esc_attr( $item->title ) : ''; ?>" required>
                </div>

                <?php if ( $type !== 'events' ) : ?>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Category Type', 'ifsedu-sms' ); ?></label>
                        <select name="notice_type" class="form-control">
                            <option value="Notice" <?php selected( $item ? $item->notice_type : '', 'Notice' ); ?>>General Notice</option>
                            <option value="Holiday" <?php selected( $item ? $item->notice_type : '', 'Holiday' ); ?>>Holiday Notice</option>
                            <option value="Exam" <?php selected( $item ? $item->notice_type : '', 'Exam' ); ?>>Exam Notice</option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold"><?php esc_html_e( 'Target Audience', 'ifsedu-sms' ); ?></label>
                    <select name="target_audience" class="form-control">
                        <option value="All" <?php selected( $item ? $item->target_audience : '', 'All' ); ?>>All</option>
                        <option value="Students" <?php selected( $item ? $item->target_audience : '', 'Students' ); ?>>Students</option>
                        <option value="Teachers" <?php selected( $item ? $item->target_audience : '', 'Teachers' ); ?>>Teachers</option>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold"><?php esc_html_e( 'Priority Level', 'ifsedu-sms' ); ?></label>
                    <select name="priority" class="form-control">
                        <option value="Normal" <?php selected( $item ? $item->priority : '', 'Normal' ); ?>>Normal</option>
                        <option value="High" <?php selected( $item ? $item->priority : '', 'High' ); ?>>High</option>
                        <option value="Urgent" <?php selected( $item ? $item->priority : '', 'Urgent' ); ?>>Urgent</option>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold"><?php echo ( $type === 'events' ) ? esc_html__( 'Event Date', 'ifsedu-sms' ) : esc_html__( 'Effective Date', 'ifsedu-sms' ); ?></label>
                    <input type="date" name="event_date" class="form-control" value="<?php echo $item ? esc_attr( $item->event_date ) : ''; ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold"><?php esc_html_e( 'Description Details', 'ifsedu-sms' ); ?></label>
                <?php wp_editor( $item ? $item->description : '', 'description', array( 'textarea_rows' => 6 ) ); ?>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold"><?php esc_html_e( 'Attachment (PDF/Image)', 'ifsedu-sms' ); ?></label>
                    <input type="file" name="item_file" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold"><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></label>
                    <select name="status" class="form-control">
                        <option value="Published" <?php selected( $item ? $item->status : '', 'Published' ); ?>>Published</option>
                        <option value="Draft" <?php selected( $item ? $item->status : '', 'Draft' ); ?>>Draft</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="educore_save_item" class="btn btn-success px-5 fw-bold" style="background-color: #006a4e; border: none;">
                <?php esc_html_e( 'Save Record', 'ifsedu-sms' ); ?>
            </button>
        </form>
    </div>
    <?php
}