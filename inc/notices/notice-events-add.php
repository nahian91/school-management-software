<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared Form View for Adding and Editing Notices & Events
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

        $data = array(
            'title'           => sanitize_text_field( $_POST['title'] ),
            'notice_type'     => ( $type === 'events' ) ? 'Event' : sanitize_text_field( $_POST['notice_type'] ),
            'priority'        => sanitize_text_field( $_POST['priority'] ),
            'target_audience' => sanitize_text_field( $_POST['target_audience'] ),
            'description'     => wp_kses_post( $_POST['description'] ),
            'event_date'      => ! empty( $_POST['event_date'] ) ? sanitize_text_field( $_POST['event_date'] ) : NULL,
            'attachment_url'  => sanitize_url( $attachment_url ),
            'featured_image'  => sanitize_url( $featured_image ),
            'created_by'      => get_current_user_id(),
            'status'          => sanitize_text_field( $_POST['status'] )
        );

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

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=' . $type . '&sub=list' );
    ?>

    <style>
        /* ==========================================================================
           NOTICE/EVENT FORM MATRIX - NEO BENTO AESTHETICS
           ========================================================================== */
        .dpt-editor-root {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
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

        /* Main Form Card Node */
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

        /* Form Grid Mechanics */
        .dpt-grid-row {
            display: grid;
            gap: 20px;
            margin-bottom: 20px;
        }

        .dpt-cols-12 { grid-template-columns: 1fr; }
        .dpt-cols-8-4 { grid-template-columns: 2fr 1fr; }
        .dpt-cols-3   { grid-template-columns: repeat(3, 1fr); }
        .dpt-cols-2   { grid-template-columns: repeat(2, 1fr); }

        @media (max-width: 868px) {
            .dpt-cols-8-4, .dpt-cols-3, .dpt-cols-2 {
                grid-template-columns: 1fr;
            }
        }

        .dpt-field-node {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .dpt-field-label {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            letter-spacing: -0.1px;
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

        /* Image Preview Box Style */
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
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        /* WYSIWYG Editor Wrap Styling */
        .dpt-editor-wrapper {
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            overflow: hidden;
            background: #ffffff;
        }

        .dpt-editor-wrapper .wp-editor-container {
            border: none;
        }

        /* Action Buttons */
        .dpt-submit-action {
            margin-top: 30px;
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

        /* Custom Alert Banner */
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
        
        <!-- Navigation Back Action -->
        <div class="dpt-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to List', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <?php if ( ! empty( $alert_message ) ) : ?>
            <div class="afdp-alert-node afdp-alert-<?php echo esc_attr($alert_type); ?>">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html( $alert_message ); ?>
            </div>
        <?php endif; ?>

        <!-- Main Form Container Matrix -->
        <div class="dpt-form-bento-card">
            
            <div class="afdp-form-header">
                <h3 class="afdp-form-title">
                    <span class="dashicons <?php echo $is_edit ? 'dashicons-edit' : 'dashicons-plus-alt'; ?>"></span>
                    <?php echo $is_edit ? esc_html__( 'Edit Record Details', 'ifsedu-sms' ) : esc_html__( 'Add New Announcement / Event', 'ifsedu-sms' ); ?>
                </h3>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <?php wp_nonce_field( 'save_item_action', 'educore_item_nonce' ); ?>

                <!-- Row 1: Title & Category -->
                <div class="dpt-grid-row <?php echo ( $type !== 'events' ) ? 'dpt-cols-8-4' : 'dpt-cols-12'; ?>">
                    <div class="dpt-field-node">
                        <label class="dpt-field-label">
                            <?php esc_html_e( 'Title', 'ifsedu-sms' ); ?> <span class="required">*</span>
                        </label>
                        <input type="text" name="title" class="dpt-input-control" value="<?php echo $item ? esc_attr( $item->title ) : ''; ?>" placeholder="Enter notice or event heading..." required>
                    </div>

                    <?php if ( $type !== 'events' ) : ?>
                        <div class="dpt-field-node">
                            <label class="dpt-field-label"><?php esc_html_e( 'Category Type', 'ifsedu-sms' ); ?></label>
                            <select name="notice_type" class="dpt-select-control">
                                <option value="Notice" <?php selected( $item ? $item->notice_type : '', 'Notice' ); ?>>General Notice</option>
                                <option value="Holiday" <?php selected( $item ? $item->notice_type : '', 'Holiday' ); ?>>Holiday Notice</option>
                                <option value="Exam" <?php selected( $item ? $item->notice_type : '', 'Exam' ); ?>>Exam Notice</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Row 2: Target, Priority, Date -->
                <div class="dpt-grid-row dpt-cols-3">
                    <div class="dpt-field-node">
                        <label class="dpt-field-label"><?php esc_html_e( 'Target Audience', 'ifsedu-sms' ); ?></label>
                        <select name="target_audience" class="dpt-select-control">
                            <option value="All" <?php selected( $item ? $item->target_audience : '', 'All' ); ?>>All Stakeholders</option>
                            <option value="Students" <?php selected( $item ? $item->target_audience : '', 'Students' ); ?>>Students Only</option>
                            <option value="Teachers" <?php selected( $item ? $item->target_audience : '', 'Teachers' ); ?>>Teachers Only</option>
                        </select>
                    </div>

                    <div class="dpt-field-node">
                        <label class="dpt-label dpt-field-label"><?php esc_html_e( 'Priority Level', 'ifsedu-sms' ); ?></label>
                        <select name="priority" class="dpt-select-control">
                            <option value="Normal" <?php selected( $item ? $item->priority : '', 'Normal' ); ?>>Normal</option>
                            <option value="High" <?php selected( $item ? $item->priority : '', 'High' ); ?>>High</option>
                            <option value="Urgent" <?php selected( $item ? $item->priority : '', 'Urgent' ); ?>>Urgent</option>
                        </select>
                    </div>

                    <div class="dpt-field-node">
                        <label class="dpt-field-label">
                            <?php echo ( $type === 'events' ) ? esc_html__( 'Event Date', 'ifsedu-sms' ) : esc_html__( 'Effective Date', 'ifsedu-sms' ); ?>
                        </label>
                        <input type="date" name="event_date" class="dpt-input-control" value="<?php echo $item ? esc_attr( $item->event_date ) : ''; ?>">
                    </div>
                </div>

                <!-- Row 3: Rich Description Editor -->
                <div class="dpt-grid-row dpt-cols-12">
                    <div class="dpt-field-node">
                        <label class="dpt-field-label"><?php esc_html_e( 'Description Details', 'ifsedu-sms' ); ?></label>
                        <div class="dpt-editor-wrapper">
                            <?php 
                            wp_editor( 
                                $item ? $item->description : '', 
                                'description', 
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

                <!-- Row 4: Featured Image, File Upload & Status -->
                <div class="dpt-grid-row dpt-cols-3">
                    <!-- Featured Image Field -->
                    <div class="dpt-field-node">
                        <label class="dpt-field-label">
                            <?php esc_html_e( 'Featured Image (JPG / PNG)', 'ifsedu-sms' ); ?>
                        </label>
                        <input type="file" name="featured_image_file" class="dpt-input-file" accept="image/*">
                        <?php if ( $item && ! empty( $item->featured_image ) ) : ?>
                            <div class="dpt-img-preview-box">
                                <img src="<?php echo esc_url( $item->featured_image ); ?>" alt="Featured Image Preview">
                                <span style="font-size: 12px; color: #475569; font-weight:600;">Current Banner</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Attachment File Field -->
                    <div class="dpt-field-node">
                        <label class="dpt-field-label">
                            <?php esc_html_e( 'Attachment File (PDF / DOC)', 'ifsedu-sms' ); ?>
                            <?php if ( $item && ! empty( $item->attachment_url ) ) : ?>
                                &mdash; <a href="<?php echo esc_url( $item->attachment_url ); ?>" target="_blank" style="color:#006a4e; text-decoration:underline;">View Current</a>
                            <?php endif; ?>
                        </label>
                        <input type="file" name="item_file" class="dpt-input-file">
                    </div>

                    <!-- Publication Status Field -->
                    <div class="dpt-field-node">
                        <label class="dpt-field-label"><?php esc_html_e( 'Publication Status', 'ifsedu-sms' ); ?></label>
                        <select name="status" class="dpt-select-control">
                            <option value="Published" <?php selected( $item ? $item->status : '', 'Published' ); ?>>Published</option>
                            <option value="Draft" <?php selected( $item ? $item->status : '', 'Draft' ); ?>>Draft</option>
                        </select>
                    </div>
                </div>

                <!-- Form Submit Action Bar -->
                <div class="dpt-submit-action">
                    <button type="submit" name="educore_save_item" class="dpt-btn-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'Save & Publish Record', 'ifsedu-sms' ); ?>
                    </button>
                </div>

            </form>
        </div>

    </div>
    <?php
}