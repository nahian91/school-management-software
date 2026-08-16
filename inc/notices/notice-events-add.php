<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared Form View for Adding and Editing Notices & Events
 * File: inc/notices/notice-events-add.php
 * Theme Aesthetic: Elite Neo-Bento UI Architecture
 * Custom Prefixes Applied: dpt-, afdp-
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

    $alert_message = '';
    $alert_type    = '';

    // Handle Form Processing
    if ( isset( $_POST['educore_save_item'] ) && wp_verify_nonce( $_POST['educore_item_nonce'], 'save_item_action' ) ) {
        $attachment_url = $item && isset( $item->attachment_url ) ? $item->attachment_url : '';
        $featured_image = $item && isset( $item->featured_image ) ? $item->featured_image : '';

        require_once ABSPATH . 'wp-admin/includes/file.php';

        // 1. Handle Attachment File Upload (PDF, DOC, ZIP etc)
        if ( ! empty( $_FILES['item_file']['name'] ) ) {
            $upload = wp_handle_upload( $_FILES['item_file'], array( 'test_form' => false ) );
            if ( ! isset( $upload['error'] ) ) {
                $attachment_url = $upload['url'];
            }
        }

        // 2. Handle Featured Image Upload (JPG, PNG, WEBP)
        if ( ! empty( $_FILES['featured_image_file']['name'] ) ) {
            $image_upload = wp_handle_upload( $_FILES['featured_image_file'], array( 'test_form' => false ) );
            if ( ! isset( $image_upload['error'] ) ) {
                $featured_image = $image_upload['url'];
            }
        }

        $form_type        = ( $type === 'events' || $type === 'event' ) ? 'event' : 'notice';
        $title            = sanitize_text_field( wp_unslash( $_POST['title'] ) );
        $category         = sanitize_text_field( wp_unslash( $_POST['category'] ) );
        $target_audience  = sanitize_text_field( wp_unslash( $_POST['target_audience'] ) );
        $publish_date_val = ! empty( $_POST['publish_date'] ) ? sanitize_text_field( wp_unslash( $_POST['publish_date'] ) ) : current_time( 'Y-m-d' );
        $event_location   = isset( $_POST['event_location'] ) ? sanitize_text_field( wp_unslash( $_POST['event_location'] ) ) : '';
        $content_body     = wp_kses_post( wp_unslash( $_POST['content'] ) );
        $status           = sanitize_text_field( wp_unslash( $_POST['status'] ) );

        // Universal Schema Data Array (Covers both standard and legacy columns)
        $data = array(
            'title'           => $title,
            'type'            => $form_type,
            'category'        => $category,
            'target_audience' => $target_audience,
            'publish_date'    => $publish_date_val,
            'event_location'  => $event_location,
            'content'         => $content_body,
            'attachment_url'  => sanitize_url( $attachment_url ),
            'status'          => $status,
            'created_by'      => get_current_user_id()
        );

        // Fallback column checks for custom schema variants
        $has_notice_type_col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_notices}` LIKE 'notice_type'" );
        if ( ! empty( $has_notice_type_col ) ) {
            $data['notice_type'] = ( $form_type === 'event' ) ? 'Event' : 'Notice';
        }

        $has_desc_col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_notices}` LIKE 'description'" );
        if ( ! empty( $has_desc_col ) ) {
            $data['description'] = $content_body;
        }

        $has_event_date_col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_notices}` LIKE 'event_date'" );
        if ( ! empty( $has_event_date_col ) ) {
            $data['event_date'] = $publish_date_val;
        }

        $has_feat_img_col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_notices}` LIKE 'featured_image'" );
        if ( ! empty( $has_feat_img_col ) ) {
            $data['featured_image'] = sanitize_url( $featured_image );
        }

        if ( $is_edit && $id > 0 ) {
            $wpdb->update( $table_notices, $data, array( 'id' => $id ) );
            $alert_message = esc_html__( 'Record updated successfully.', 'ifsedu-sms' );
            $alert_type    = 'success';
            $item          = (object) array_merge( (array) $item, $data );
        } else {
            $wpdb->insert( $table_notices, $data );
            $alert_message = esc_html__( 'Published successfully.', 'ifsedu-sms' );
            $alert_type    = 'success';
            $_POST         = array();
            $item          = null;
        }
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . ( ( $type === 'events' || $type === 'event' ) ? 'events' : 'notice' ) . '&sub=list' );

    // Normalize field getters for edit mode
    $current_title     = $item ? $item->title : '';
    $current_category  = $item ? ( isset( $item->category ) ? $item->category : ( isset( $item->notice_type ) ? $item->notice_type : 'General' ) ) : 'General';
    $current_audience  = $item ? $item->target_audience : 'All';
    $current_date      = $item ? ( ! empty( $item->publish_date ) ? $item->publish_date : ( ! empty( $item->event_date ) ? $item->event_date : current_time( 'Y-m-d' ) ) ) : current_time( 'Y-m-d' );
    $current_location  = $item ? ( isset( $item->event_location ) ? $item->event_location : '' ) : '';
    $current_content   = $item ? ( ! empty( $item->content ) ? $item->content : ( isset( $item->description ) ? $item->description : '' ) ) : '';
    $current_status    = $item ? $item->status : 'Published';
    $current_feat_img  = $item ? ( ! empty( $item->featured_image ) ? $item->featured_image : ( ! empty( $item->attachment_url ) ? $item->attachment_url : '' ) ) : '';
    $current_attach    = $item && ! empty( $item->attachment_url ) ? $item->attachment_url : '';
    ?>

    <style>
        .dpt-editor-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .dpt-top-action-bar {
            margin-bottom: 20px;
        }

        .dpt-btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .dpt-btn-back:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .dpt-form-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-form-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 16px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .afdp-form-title {
            font-size: 20px;
            font-weight: 800;
            color: #006a4e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.4px;
        }

        .dpt-grid-row {
            display: grid;
            gap: 20px;
            margin-bottom: 20px;
        }

        .dpt-cols-12   { grid-template-columns: 1fr; }
        .dpt-cols-8-4  { grid-template-columns: 2fr 1fr; }
        .dpt-cols-3    { grid-template-columns: repeat(3, 1fr); }
        .dpt-cols-4    { grid-template-columns: repeat(4, 1fr); }

        @media (max-width: 992px) {
            .dpt-cols-8-4, .dpt-cols-3, .dpt-cols-4 {
                grid-template-columns: 1fr;
            }
        }

        .dpt-field-node {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .dpt-field-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .dpt-field-label span.required {
            color: #dc2626;
        }

        .dpt-input-control,
        .dpt-select-control {
            width: 100%;
            height: 44px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0 14px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .dpt-input-file {
            width: 100%;
            padding: 8px 12px;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
            font-size: 13px;
            color: #475569;
            box-sizing: border-box;
        }

        .dpt-input-control:focus,
        .dpt-select-control:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
            outline: none;
        }

        .dpt-img-preview-box {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 6px;
            padding: 8px;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
        }

        .dpt-img-preview-box img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .dpt-editor-wrapper {
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            overflow: hidden;
            background: #ffffff;
        }

        .dpt-editor-wrapper .wp-editor-container {
            border: none;
        }

        .dpt-submit-action {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }

        .dpt-btn-primary {
            height: 46px;
            padding: 0 32px;
            background: #006a4e;
            border: none;
            color: #ffffff;
            font-weight: 800;
            font-size: 14px;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.25);
        }

        .dpt-btn-primary:hover {
            background: #00523c;
            transform: translateY(-1px);
        }

        .afdp-alert-node {
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .afdp-alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
    </style>

    <div class="dpt-editor-root">
        
        <div class="dpt-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to List', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <?php if ( ! empty( $alert_message ) ) : ?>
            <div class="afdp-alert-node afdp-alert-<?php echo esc_attr( $alert_type ); ?>">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html( $alert_message ); ?>
            </div>
        <?php endif; ?>

        <div class="dpt-form-bento-card">
            
            <div class="afdp-form-header">
                <h3 class="afdp-form-title">
                    <span class="dashicons <?php echo $is_edit ? 'dashicons-edit' : 'dashicons-plus-alt'; ?>"></span>
                    <?php 
                        if ( $is_edit ) {
                            printf( esc_html__( 'Edit %s Record', 'ifsedu-sms' ), ( $type === 'events' ? 'Event' : 'Notice' ) );
                        } else {
                            printf( esc_html__( 'Publish New %s', 'ifsedu-sms' ), ( $type === 'events' ? 'Academic Event' : 'Notice / Announcement' ) );
                        }
                    ?>
                </h3>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <?php wp_nonce_field( 'save_item_action', 'educore_item_nonce' ); ?>

                <!-- Row 1: Title & Date -->
                <div class="dpt-grid-row dpt-cols-8-4">
                    <div class="dpt-field-node">
                        <label class="dpt-field-label">
                            <?php esc_html_e( 'Title', 'ifsedu-sms' ); ?> <span class="required">*</span>
                        </label>
                        <input type="text" name="title" class="dpt-input-control" value="<?php echo esc_attr( $current_title ); ?>" placeholder="<?php esc_attr_e( 'Enter heading...', 'ifsedu-sms' ); ?>" required>
                    </div>

                    <div class="dpt-field-node">
                        <label class="dpt-field-label">
                            <?php echo ( $type === 'events' ) ? esc_html__( 'Event Date', 'ifsedu-sms' ) : esc_html__( 'Publish Date', 'ifsedu-sms' ); ?> <span class="required">*</span>
                        </label>
                        <input type="date" name="publish_date" class="dpt-input-control" value="<?php echo esc_attr( $current_date ); ?>" required>
                    </div>
                </div>

                <!-- Row 2: Category, Audience, Location/Venue, Status -->
                <div class="dpt-grid-row <?php echo ( $type === 'events' ) ? 'dpt-cols-4' : 'dpt-cols-3'; ?>">
                    <div class="dpt-field-node">
                        <label class="dpt-field-label"><?php esc_html_e( 'Category', 'ifsedu-sms' ); ?></label>
                        <select name="category" class="dpt-select-control">
                            <option value="General" <?php selected( $current_category, 'General' ); ?>>General</option>
                            <option value="Academic" <?php selected( $current_category, 'Academic' ); ?>>Academic</option>
                            <option value="Exam" <?php selected( $current_category, 'Exam' ); ?>>Exam</option>
                            <option value="Holiday" <?php selected( $current_category, 'Holiday' ); ?>>Holiday</option>
                        </select>
                    </div>

                    <div class="dpt-field-node">
                        <label class="dpt-field-label"><?php esc_html_e( 'Target Audience', 'ifsedu-sms' ); ?></label>
                        <select name="target_audience" class="dpt-select-control">
                            <option value="All" <?php selected( $current_audience, 'All' ); ?>>All Users</option>
                            <option value="Students" <?php selected( $current_audience, 'Students' ); ?>>Students Only</option>
                            <option value="Teachers" <?php selected( $current_audience, 'Teachers' ); ?>>Teachers Only</option>
                        </select>
                    </div>

                    <?php if ( $type === 'events' ) : ?>
                        <div class="dpt-field-node">
                            <label class="dpt-field-label"><?php esc_html_e( 'Event Venue / Location', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="event_location" class="dpt-input-control" value="<?php echo esc_attr( $current_location ); ?>" placeholder="e.g. Main Auditorium">
                        </div>
                    <?php endif; ?>

                    <div class="dpt-field-node">
                        <label class="dpt-field-label"><?php esc_html_e( 'Publication Status', 'ifsedu-sms' ); ?></label>
                        <select name="status" class="dpt-select-control">
                            <option value="Published" <?php selected( $current_status, 'Published' ); ?>>Published</option>
                            <option value="Draft" <?php selected( $current_status, 'Draft' ); ?>>Draft</option>
                        </select>
                    </div>
                </div>

                <!-- Row 3: Rich Description Editor -->
                <div class="dpt-grid-row dpt-cols-12">
                    <div class="dpt-field-node">
                        <label class="dpt-field-label"><?php esc_html_e( 'Description & Detailed Content', 'ifsedu-sms' ); ?></label>
                        <div class="dpt-editor-wrapper">
                            <?php 
                            wp_editor( 
                                $current_content, 
                                'content', 
                                array( 
                                    'textarea_rows' => 8,
                                    'quicktags'     => true,
                                    'tinymce'       => true
                                ) 
                            ); 
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Row 4: Banner Image & File Attachment -->
                <div class="dpt-grid-row dpt-cols-2">
                    <!-- Featured Image Field -->
                    <div class="dpt-field-node">
                        <label class="dpt-field-label"><?php esc_html_e( 'Banner Image (JPG / PNG / WEBP)', 'ifsedu-sms' ); ?></label>
                        <input type="file" name="featured_image_file" class="dpt-input-file" accept="image/*">
                        <?php if ( ! empty( $current_feat_img ) && preg_match( '/\.(jpg|jpeg|png|webp|gif)$/i', $current_feat_img ) ) : ?>
                            <div class="dpt-img-preview-box">
                                <img src="<?php echo esc_url( $current_feat_img ); ?>" alt="Banner Preview">
                                <span style="font-size: 12px; color: #475569; font-weight:600;"><?php esc_html_e( 'Current Banner Active', 'ifsedu-sms' ); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Attachment File Field -->
                    <div class="dpt-field-node">
                        <label class="dpt-field-label">
                            <?php esc_html_e( 'Attachment Document (PDF / DOC / ZIP)', 'ifsedu-sms' ); ?>
                            <?php if ( ! empty( $current_attach ) ) : ?>
                                &mdash; <a href="<?php echo esc_url( $current_attach ); ?>" target="_blank" style="color:#006a4e; text-decoration:underline; font-weight:600;"><?php esc_html_e( 'View Current', 'ifsedu-sms' ); ?></a>
                            <?php endif; ?>
                        </label>
                        <input type="file" name="item_file" class="dpt-input-file">
                    </div>
                </div>

                <!-- Submit Action Bar -->
                <div class="dpt-submit-action">
                    <button type="submit" name="educore_save_item" class="dpt-btn-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php echo $is_edit ? esc_html__( 'Update Record', 'ifsedu-sms' ) : esc_html__( 'Save & Publish', 'ifsedu-sms' ); ?>
                    </button>
                </div>

            </form>
        </div>

    </div>
    <?php
}