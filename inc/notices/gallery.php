<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gallery Module Internal Sub-Router
 */
function educore_gallery_router( $sub_tab ) {
    switch ( $sub_tab ) {
        case 'add':
        case 'edit':
            educore_gallery_add_edit_view();
            break;

        case 'view':
            educore_gallery_single_album_view();
            break;

        case 'delete_photo':
            educore_gallery_photo_delete_action();
            break;

        case 'delete':
            educore_gallery_delete_action();
            break;

        case 'list':
        default:
            educore_gallery_list_view();
            break;
    }
}

/**
 * Photo Albums Grid Directory View
 * Theme Aesthetic: Neo-Bento Card Grid Layout
 */
function educore_gallery_list_view() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-sms' ) );
    }

    $albums  = $wpdb->get_results( "SELECT * FROM {$table_albums} ORDER BY id DESC" );
    $add_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=add' );
    ?>

    <style>
        /* ==========================================================================
           GALLERY DIRECTORY - NEO-BENTO SYSTEM
           ========================================================================== */
        .dpt-gallery-root {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #0f172a;
        }

        .afdp-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .afdp-page-title {
            font-size: 22px;
            font-weight: 800;
            color: #006a4e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.4px;
        }

        .afdp-page-title .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
        }

        .dpt-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #006a4e;
            color: #ffffff;
            font-size: 13.5px;
            font-weight: 700;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 106, 78, 0.2);
            border: none;
            cursor: pointer;
        }

        .dpt-btn-primary:hover {
            background: #00523c;
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Bento Cards Grid Layout */
        .dpt-bento-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .dpt-album-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.25s ease;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
        }

        .dpt-album-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-3px);
            box-shadow: 0 12px 25px -5px rgba(0, 0, 0, 0.08);
        }

        .dpt-cover-container {
            height: 180px;
            position: relative;
            background-color: #f1f5f9;
            overflow: hidden;
        }

        .dpt-cover-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .dpt-album-card:hover .dpt-cover-img {
            transform: scale(1.04);
        }

        .dpt-category-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(4px);
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .dpt-card-body {
            padding: 16px 20px;
            flex: 1;
        }

        .dpt-album-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 6px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dpt-photo-count {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12.5px;
            color: #64748b;
            font-weight: 600;
        }

        .dpt-card-footer {
            padding: 12px 20px 16px 20px;
            background: #ffffff;
            border-top: 1px solid #f8fafc;
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

        .dpt-empty-state {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 60px 20px;
            text-align: center;
            color: #64748b;
        }

        .dpt-empty-state .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            color: #cbd5e1;
            margin-bottom: 12px;
        }

        .dpt-empty-state h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #475569;
        }
    </style>

    <div class="dpt-gallery-root">
        
        <div class="afdp-header-bar">
            <h2 class="afdp-page-title">
                <span class="dashicons dashicons-format-gallery"></span> 
                <?php esc_html_e( 'Photo Albums Directory', 'ifsedu-sms' ); ?>
            </h2>
            <a href="<?php echo esc_url( $add_url ); ?>" class="dpt-btn-primary">
                <span class="dashicons dashicons-plus-alt2" style="font-size:16px; width:16px; height:16px;"></span>
                <?php esc_html_e( 'Create New Album', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <?php if ( ! empty( $albums ) ) : ?>
            <div class="dpt-bento-grid">
                <?php foreach ( $albums as $album ) : 
                    $album_id    = absint( $album->id );
                    $view_url    = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=view&id=' . $album_id );
                    $edit_url    = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=edit&id=' . $album_id );
                    $delete_url  = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=delete&id=' . $album_id ), 'delete_gallery_' . $album_id );
                    $photo_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_photos} WHERE album_id = %d", $album_id ) );

                    $cover_src   = ! empty( $album->cover_image ) ? $album->cover_image : 'https://via.placeholder.com/400x250?text=No+Cover+Image';
                ?>
                <div class="dpt-album-card">
                    <div class="dpt-cover-container">
                        <img src="<?php echo esc_url( $cover_src ); ?>" class="dpt-cover-img" alt="<?php echo esc_attr( $album->title ); ?>">
                        <span class="dpt-category-badge">
                            <?php echo esc_html( $album->category ?? 'General' ); ?>
                        </span>
                    </div>

                    <div class="dpt-card-body">
                        <h3 class="dpt-album-title" title="<?php echo esc_attr( $album->title ); ?>"><?php echo esc_html( $album->title ); ?></h3>
                        <span class="dpt-photo-count">
                            <span class="dashicons dashicons-images-alt2" style="font-size: 14px; width:14px; height:14px;"></span> 
                            <?php echo esc_html( $photo_count ); ?> <?php esc_html_e( 'Photos', 'ifsedu-sms' ); ?>
                        </span>
                    </div>

                    <div class="dpt-card-footer">
                        <a href="<?php echo esc_url( $view_url ); ?>" class="dpt-square-btn dpt-btn-view" title="<?php esc_attr_e( 'View Album', 'ifsedu-sms' ); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </a>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="dpt-square-btn dpt-btn-edit" title="<?php esc_attr_e( 'Edit Album', 'ifsedu-sms' ); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </a>
                        <a href="<?php echo esc_url( $delete_url ); ?>" class="dpt-square-btn dpt-btn-delete" title="<?php esc_attr_e( 'Delete Album', 'ifsedu-sms' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this album and all its images?', 'ifsedu-sms' ) ); ?>');">
                            <span class="dashicons dashicons-trash"></span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="dpt-empty-state">
                <span class="dashicons dashicons-format-gallery"></span>
                <h5><?php esc_html_e( 'No photo albums created yet.', 'ifsedu-sms' ); ?></h5>
            </div>
        <?php endif; ?>

    </div>
    <?php
}

/**
 * Single Album Gallery Photo View
 */
function educore_gallery_single_album_view() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    $album_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $album    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_albums} WHERE id = %d", $album_id ) );

    if ( ! $album ) {
        ?>
        <style>
            .afdp-alert-error {
                background: #fef2f2;
                border: 1px solid #fecaca;
                color: #b91c1c;
                padding: 16px 20px;
                border-radius: 12px;
                font-weight: 700;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 10px;
                margin-top: 20px;
            }
        </style>
        <div class="afdp-alert-error">
            <span class="dashicons dashicons-dismiss"></span>
            <?php esc_html_e( 'Album not found or has been deleted.', 'ifsedu-sms' ); ?>
        </div>
        <?php
        return;
    }

    $photos   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_photos} WHERE album_id = %d ORDER BY id DESC", $album_id ) );
    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=list' );
    $edit_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=edit&id=' . $album_id );
    ?>

    <style>
        /* ==========================================================================
           SINGLE ALBUM VIEW - NEO-BENTO SYSTEM
           ========================================================================== */
        .dpt-single-root {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #0f172a;
        }

        .dpt-top-action-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 16px;
            flex-wrap: wrap;
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

        .dpt-btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #2563eb;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
        }

        .dpt-btn-action:hover {
            background: #1d4ed8;
            color: #ffffff;
        }

        /* Detail Card */
        .dpt-album-detail-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-album-header-title {
            font-size: 22px;
            font-weight: 800;
            color: #006a4e;
            margin: 0 0 8px 0;
            letter-spacing: -0.4px;
        }

        .dpt-meta-strip {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 12.5px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .dpt-album-desc {
            color: #334155;
            font-size: 14px;
            line-height: 1.6;
            margin: 12px 0 0 0;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
        }

        /* Photo Gallery Responsive Grid */
        .dpt-photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
        }

        .dpt-photo-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }

        .dpt-photo-card:hover {
            transform: scale(1.02);
            border-color: #cbd5e1;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        .dpt-photo-link {
            display: block;
            height: 140px;
            width: 100%;
        }

        .dpt-photo-link img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dpt-empty-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            color: #64748b;
            font-weight: 600;
        }
    </style>

    <div class="dpt-single-root">
        
        <div class="dpt-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Album Directory', 'ifsedu-sms' ); ?>
            </a>
            <a href="<?php echo esc_url( $edit_url ); ?>" class="dpt-btn-action">
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e( 'Edit / Upload More Photos', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <div class="dpt-album-detail-card">
            <h3 class="afdp-album-header-title"><?php echo esc_html( $album->title ); ?></h3>
            <div class="dpt-meta-strip">
                <span><strong>Category:</strong> <?php echo esc_html( $album->category ); ?></span>
                <span>•</span>
                <span><strong>Total Photos:</strong> <?php echo count( $photos ); ?></span>
            </div>
            <?php if ( ! empty( $album->description ) ) : ?>
                <p class="dpt-album-desc"><?php echo esc_html( $album->description ); ?></p>
            <?php endif; ?>
        </div>

        <?php if ( ! empty( $photos ) ) : ?>
            <div class="dpt-photo-grid">
                <?php foreach ( $photos as $photo ) : ?>
                    <div class="dpt-photo-card">
                        <a href="<?php echo esc_url( $photo->image_url ); ?>" target="_blank" class="dpt-photo-link">
                            <img src="<?php echo esc_url( $photo->image_url ); ?>" alt="Gallery Photo">
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="dpt-empty-box">
                <?php esc_html_e( 'This album contains no photos yet.', 'ifsedu-sms' ); ?>
            </div>
        <?php endif; ?>

    </div>
    <?php
}

/**
 * Add / Edit Gallery Album Form
 */
function educore_gallery_add_edit_view() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    $is_edit  = isset( $_GET['sub'] ) && $_GET['sub'] === 'edit';
    $album_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

    $album  = null;
    $photos = array();
    $saved_message = false;

    if ( $is_edit && $album_id > 0 ) {
        $album  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_albums} WHERE id = %d", $album_id ) );
        $photos = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_photos} WHERE album_id = %d ORDER BY id DESC", $album_id ) );
    }

    if ( isset( $_POST['educore_save_gallery'] ) && wp_verify_nonce( $_POST['educore_gallery_nonce'], 'save_gallery_action' ) ) {
        $cover_image = $album ? $album->cover_image : '';

        if ( ! empty( $_FILES['cover_image']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $upload = wp_handle_upload( $_FILES['cover_image'], array( 'test_form' => false ) );
            if ( ! isset( $upload['error'] ) ) {
                $cover_image = $upload['url'];
            }
        }

        $album_data = array(
            'title'       => sanitize_text_field( $_POST['title'] ),
            'category'    => sanitize_text_field( $_POST['category'] ),
            'description' => sanitize_textarea_field( $_POST['description'] ),
            'cover_image' => sanitize_url( $cover_image ),
            'status'      => sanitize_text_field( $_POST['status'] )
        );

        if ( $is_edit && $album_id > 0 ) {
            $wpdb->update( $table_albums, $album_data, array( 'id' => $album_id ) );
            $current_id = $album_id;
        } else {
            $wpdb->insert( $table_albums, $album_data );
            $current_id = $wpdb->insert_id;
        }

        // Multi-File Upload Processing
        if ( ! empty( $_FILES['gallery_photos']['name'][0] ) && $current_id > 0 ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $files = $_FILES['gallery_photos'];
            foreach ( $files['name'] as $k => $v ) {
                if ( $files['name'][ $k ] ) {
                    $file = array(
                        'name'     => $files['name'][ $k ],
                        'type'     => $files['type'][ $k ],
                        'tmp_name' => $files['tmp_name'][ $k ],
                        'error'    => $files['error'][ $k ],
                        'size'     => $files['size'][ $k ]
                    );
                    $up = wp_handle_upload( $file, array( 'test_form' => false ) );
                    if ( ! isset( $up['error'] ) ) {
                        $wpdb->insert( $table_photos, array( 'album_id' => $current_id, 'image_url' => sanitize_url( $up['url'] ) ) );

                        if ( empty( $cover_image ) ) {
                            $cover_image = $up['url'];
                            $wpdb->update( $table_albums, array( 'cover_image' => $cover_image ), array( 'id' => $current_id ) );
                        }
                    }
                }
            }
        }

        $saved_message = true;

        // Reload data
        $album  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_albums} WHERE id = %d", $current_id ) );
        $photos = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_photos} WHERE album_id = %d ORDER BY id DESC", $current_id ) );
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=list' );
    ?>

    <style>
        /* ==========================================================================
           ADD/EDIT FORM - NEO-BENTO ARCHITECTURE
           ========================================================================== */
        .dpt-form-root {
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

        .afdp-alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .dpt-bento-form-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-form-title {
            font-size: 20px;
            font-weight: 800;
            color: #006a4e;
            margin: 0 0 24px 0;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
            letter-spacing: -0.4px;
        }

        /* Form Grid Mechanics */
        .dpt-form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .dpt-form-row {
                grid-template-columns: 1fr;
            }
        }

        .dpt-form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .dpt-form-group label {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }

        .dpt-field-input,
        .dpt-field-select,
        .dpt-field-textarea {
            width: 100%;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            color: #0f172a;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .dpt-field-input:focus,
        .dpt-field-select:focus,
        .dpt-field-textarea:focus {
            outline: none;
            border-color: #006a4e;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
        }

        /* Upload Area Accent Node */
        .dpt-upload-bento-node {
            background: #f0fdf4;
            border: 1px dashed #86efac;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .dpt-upload-bento-node label {
            color: #065f46;
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 6px;
            display: block;
        }

        /* Current Photos Management Grid */
        .dpt-photos-manager {
            margin-bottom: 28px;
        }

        .dpt-photos-manager-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
            margin: 0 0 16px 0;
        }

        .dpt-manage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 12px;
        }

        .dpt-manage-photo-card {
            position: relative;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 4px;
            background: #ffffff;
            height: 90px;
        }

        .dpt-manage-photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
        }

        .dpt-btn-photo-del {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 24px;
            height: 24px;
            background: #dc2626;
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 14px;
            font-weight: 800;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: transform 0.2s ease;
        }

        .dpt-btn-photo-del:hover {
            transform: scale(1.15);
            color: #ffffff;
        }

        .dpt-btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 32px;
            background: #006a4e;
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.25);
        }

        .dpt-btn-submit:hover {
            background: #00523c;
            transform: translateY(-1px);
        }
    </style>

    <div class="dpt-form-root">
        
        <div class="dpt-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Gallery', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <?php if ( $saved_message ) : ?>
            <div class="afdp-alert-success">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e( 'Album saved successfully.', 'ifsedu-sms' ); ?>
            </div>
        <?php endif; ?>

        <div class="dpt-bento-form-card">
            <h3 class="afdp-form-title">
                <?php echo $is_edit ? esc_html__( 'Edit Album Details', 'ifsedu-sms' ) : esc_html__( 'Create Photo Album', 'ifsedu-sms' ); ?>
            </h3>

            <form method="POST" action="" enctype="multipart/form-data">
                <?php wp_nonce_field( 'save_gallery_action', 'educore_gallery_nonce' ); ?>

                <div class="dpt-form-row">
                    <div class="dpt-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e( 'Album Title', 'ifsedu-sms' ); ?></label>
                        <input type="text" name="title" class="dpt-field-input" value="<?php echo $album ? esc_attr( $album->title ) : ''; ?>" required>
                    </div>
                    <div class="dpt-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e( 'Category', 'ifsedu-sms' ); ?></label>
                        <select name="category" class="dpt-field-select">
                            <option value="Academic" <?php selected( $album ? $album->category : '', 'Academic' ); ?>>Academic</option>
                            <option value="Sports" <?php selected( $album ? $album->category : '', 'Sports' ); ?>>Sports</option>
                            <option value="Cultural" <?php selected( $album ? $album->category : '', 'Cultural' ); ?>>Cultural</option>
                            <option value="Campus" <?php selected( $album ? $album->category : '', 'Campus' ); ?>>Campus & Infrastructure</option>
                            <option value="General" <?php selected( $album ? $album->category : '', 'General' ); ?>>General</option>
                        </select>
                    </div>
                    <div class="dpt-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></label>
                        <select name="status" class="dpt-field-select">
                            <option value="Published" <?php selected( $album ? $album->status : '', 'Published' ); ?>>Published</option>
                            <option value="Draft" <?php selected( $album ? $album->status : '', 'Draft' ); ?>>Draft</option>
                        </select>
                    </div>
                </div>

                <div class="dpt-form-group">
                    <label><?php esc_html_e( 'Description', 'ifsedu-sms' ); ?></label>
                    <textarea name="description" class="dpt-field-textarea" rows="3"><?php echo $album ? esc_textarea( $album->description ) : ''; ?></textarea>
                </div>

                <div class="dpt-form-group">
                    <label><?php esc_html_e( 'Cover Image (Thumbnail)', 'ifsedu-sms' ); ?></label>
                    <input type="file" name="cover_image" class="dpt-field-input" accept="image/*">
                    <?php if ( $album && ! empty( $album->cover_image ) ) : ?>
                        <div style="margin-top: 10px;">
                            <img src="<?php echo esc_url( $album->cover_image ); ?>" style="width: 80px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #cbd5e1;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dpt-upload-bento-node">
                    <label><?php esc_html_e( 'Upload Photos to Album', 'ifsedu-sms' ); ?></label>
                    <input type="file" name="gallery_photos[]" class="dpt-field-input" accept="image/*" multiple>
                    <p style="margin: 6px 0 0 0; font-size: 12px; color: #047857; font-weight: 600;">
                        <?php esc_html_e( 'Select multiple files to batch upload images into this gallery.', 'ifsedu-sms' ); ?>
                    </p>
                </div>

                <?php if ( $is_edit && ! empty( $photos ) ) : ?>
                    <div class="dpt-photos-manager">
                        <h4 class="dpt-photos-manager-title">
                            <?php esc_html_e( 'Manage Existing Album Photos', 'ifsedu-sms' ); ?> (<?php echo count( $photos ); ?>)
                        </h4>
                        <div class="dpt-manage-grid">
                            <?php foreach ( $photos as $photo ) : 
                                $photo_del_url = wp_nonce_url( 
                                    admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=delete_photo&photo_id=' . $photo->id . '&album_id=' . $album_id ), 
                                    'delete_photo_' . $photo->id 
                                );
                            ?>
                                <div class="dpt-manage-photo-card">
                                    <img src="<?php echo esc_url( $photo->image_url ); ?>" alt="Gallery Image">
                                    <a href="<?php echo esc_url( $photo_del_url ); ?>" class="dpt-btn-photo-del" title="<?php esc_attr_e( 'Delete Photo', 'ifsedu-sms' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove this photo?', 'ifsedu-sms' ) ); ?>');">
                                        &times;
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" name="educore_save_gallery" class="dpt-btn-submit">
                    <?php echo $is_edit ? esc_html__( 'Update Album', 'ifsedu-sms' ) : esc_html__( 'Publish Album', 'ifsedu-sms' ); ?>
                </button>
            </form>
        </div>

    </div>
    <?php
}

/**
 * Action Handler: Delete Individual Gallery Photo
 */
function educore_gallery_photo_delete_action() {
    global $wpdb;
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-sms' ) );
    }

    $photo_id = isset( $_GET['photo_id'] ) ? absint( $_GET['photo_id'] ) : 0;
    $album_id = isset( $_GET['album_id'] ) ? absint( $_GET['album_id'] ) : 0;

    if ( $photo_id > 0 && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_photo_' . $photo_id ) ) {
        $wpdb->delete( $table_photos, array( 'id' => $photo_id ) );
    }

    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=edit&id=' . $album_id );
    wp_safe_redirect( $redirect_url );
    exit;
}

/**
 * Action Handler: Delete Complete Photo Album & Associated Photos
 */
function educore_gallery_delete_action() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-sms' ) );
    }

    $album_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

    if ( $album_id > 0 && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_gallery_' . $album_id ) ) {
        // Remove all images linked to this album
        $wpdb->delete( $table_photos, array( 'album_id' => $album_id ) );
        // Remove the album record
        $wpdb->delete( $table_albums, array( 'id' => $album_id ) );
    }

    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=list' );
    wp_safe_redirect( $redirect_url );
    exit;
}