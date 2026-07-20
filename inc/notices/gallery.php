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

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>
            <span class="dashicons dashicons-format-gallery text-success me-1"></span> 
            <?php esc_html_e( 'Photo Albums Directory', 'ifsedu-sms' ); ?>
        </h2>
        <a href="<?php echo esc_url( $add_url ); ?>" class="btn btn-success fw-bold px-4" style="background-color: #006a4e; border: none;">
            + <?php esc_html_e( 'Create New Album', 'ifsedu-sms' ); ?>
        </a>
    </div>

    <?php if ( ! empty( $albums ) ) : ?>
        <div class="row g-4">
            <?php foreach ( $albums as $album ) : 
                $album_id    = absint( $album->id );
                $view_url    = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=view&id=' . $album_id );
                $edit_url    = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=edit&id=' . $album_id );
                $delete_url  = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=delete&id=' . $album_id ), 'delete_gallery_' . $album_id );
                $photo_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_photos} WHERE album_id = %d", $album_id ) );

                $cover_src   = ! empty( $album->cover_image ) ? $album->cover_image : 'https://via.placeholder.com/400x250?text=No+Cover+Image';
            ?>
            <div class="col-md-4 col-lg-3">
                <div class="card h-100 shadow-sm border-0 rounded-3 overflow-hidden">
                    <div style="height: 170px; overflow: hidden; background-color: #e2e8f0;" class="position-relative">
                        <img src="<?php echo esc_url( $cover_src ); ?>" class="w-100 h-100" style="object-fit: cover;" alt="<?php echo esc_attr( $album->title ); ?>">
                        <span class="badge bg-dark position-absolute top-0 end-0 m-2 opacity-75">
                            <?php echo esc_html( $album->category ?? 'General' ); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <h6 class="card-title fw-bold text-dark text-truncate mb-1"><?php echo esc_html( $album->title ); ?></h6>
                        <small class="text-muted d-block mb-2">
                            <span class="dashicons dashicons-images-alt2" style="font-size: 14px; width:14px; height:14px;"></span> 
                            <?php echo esc_html( $photo_count ); ?> <?php esc_html_e( 'Photos', 'ifsedu-sms' ); ?>
                        </small>
                    </div>
                    <div class="card-footer bg-white border-0 text-end pb-3 pt-0">
                        <a href="<?php echo esc_url( $view_url ); ?>" class="btn btn-sm btn-outline-info me-1"><?php esc_html_e( 'View', 'ifsedu-sms' ); ?></a>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-sm btn-outline-primary me-1"><?php esc_html_e( 'Edit', 'ifsedu-sms' ); ?></a>
                        <a href="<?php echo esc_url( $delete_url ); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?php echo esc_js( __( 'Delete this album and all its images?', 'ifsedu-sms' ) ); ?>');"><?php esc_html_e( 'Delete', 'ifsedu-sms' ); ?></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <div class="bg-white p-5 rounded text-center border text-muted">
            <span class="dashicons dashicons-format-gallery fs-1 text-secondary mb-2"></span>
            <h5><?php esc_html_e( 'No photo albums created yet.', 'ifsedu-sms' ); ?></h5>
        </div>
    <?php endif; ?>
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
        echo '<div class="alert alert-danger">' . esc_html__( 'Album not found.', 'ifsedu-sms' ) . '</div>';
        return;
    }

    $photos   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_photos} WHERE album_id = %d ORDER BY id DESC", $album_id ) );
    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=list' );
    $edit_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=edit&id=' . $album_id );
    ?>

    <div class="mb-3 d-flex justify-content-between align-items-center">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; <?php esc_html_e( 'Back to Album Directory', 'ifsedu-sms' ); ?></a>
        <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-primary btn-sm" style="background-color: #2563eb; border: none;">
            <span class="dashicons dashicons-edit" style="vertical-align: middle;"></span> <?php esc_html_e( 'Edit / Upload More Photos', 'ifsedu-sms' ); ?>
        </a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border mb-4">
        <h3 class="fw-bold text-success m-0"><?php echo esc_html( $album->title ); ?></h3>
        <p class="text-muted small mt-1 mb-2">
            <strong>Category:</strong> <?php echo esc_html( $album->category ); ?> | 
            <strong>Total Photos:</strong> <?php echo count( $photos ); ?>
        </p>
        <?php if ( ! empty( $album->description ) ) : ?>
            <p class="text-secondary m-0 border-top pt-2"><?php echo esc_html( $album->description ); ?></p>
        <?php endif; ?>
    </div>

    <?php if ( ! empty( $photos ) ) : ?>
        <div class="row g-3">
            <?php foreach ( $photos as $photo ) : ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <div class="card h-100 shadow-sm border-0 rounded overflow-hidden">
                        <a href="<?php echo esc_url( $photo->image_url ); ?>" target="_blank">
                            <img src="<?php echo esc_url( $photo->image_url ); ?>" class="w-100" style="height: 140px; object-fit: cover;" alt="Gallery Photo">
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <div class="bg-white p-4 rounded text-center border text-muted">
            <p class="m-0"><?php esc_html_e( 'This album contains no photos yet.', 'ifsedu-sms' ); ?></p>
        </div>
    <?php endif; ?>
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

        echo '<div class="alert alert-success">' . esc_html__( 'Album saved successfully.', 'ifsedu-sms' ) . '</div>';

        // Reload data
        $album  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_albums} WHERE id = %d", $current_id ) );
        $photos = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_photos} WHERE album_id = %d ORDER BY id DESC", $current_id ) );
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=list' );
    ?>

    <div class="mb-3">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; <?php esc_html_e( 'Back to Gallery', 'ifsedu-sms' ); ?></a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border">
        <h3 class="pb-2 mb-4 text-success fw-bold border-bottom">
            <?php echo $is_edit ? esc_html__( 'Edit Album Details', 'ifsedu-sms' ) : esc_html__( 'Create Photo Album', 'ifsedu-sms' ); ?>
        </h3>

        <form method="POST" action="" enctype="multipart/form-data">
            <?php wp_nonce_field( 'save_gallery_action', 'educore_gallery_nonce' ); ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold"><?php esc_html_e( 'Album Title', 'ifsedu-sms' ); ?></label>
                    <input type="text" name="title" class="form-control" value="<?php echo $album ? esc_attr( $album->title ) : ''; ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold"><?php esc_html_e( 'Category', 'ifsedu-sms' ); ?></label>
                    <select name="category" class="form-control">
                        <option value="Academic" <?php selected( $album ? $album->category : '', 'Academic' ); ?>>Academic</option>
                        <option value="Sports" <?php selected( $album ? $album->category : '', 'Sports' ); ?>>Sports</option>
                        <option value="Cultural" <?php selected( $album ? $album->category : '', 'Cultural' ); ?>>Cultural</option>
                        <option value="Campus" <?php selected( $album ? $album->category : '', 'Campus' ); ?>>Campus & Infrastructure</option>
                        <option value="General" <?php selected( $album ? $album->category : '', 'General' ); ?>>General</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold"><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></label>
                    <select name="status" class="form-control">
                        <option value="Published" <?php selected( $album ? $album->status : '', 'Published' ); ?>>Published</option>
                        <option value="Draft" <?php selected( $album ? $album->status : '', 'Draft' ); ?>>Draft</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold"><?php esc_html_e( 'Description', 'ifsedu-sms' ); ?></label>
                <textarea name="description" class="form-control" rows="3"><?php echo $album ? esc_textarea( $album->description ) : ''; ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold"><?php esc_html_e( 'Cover Image (Thumbnail)', 'ifsedu-sms' ); ?></label>
                <input type="file" name="cover_image" class="form-control" accept="image/*">
                <?php if ( $album && ! empty( $album->cover_image ) ) : ?>
                    <div class="mt-2">
                        <img src="<?php echo esc_url( $album->cover_image ); ?>" class="rounded border" style="width: 80px; height: 60px; object-fit: cover;">
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-4 p-3 bg-light rounded border">
                <label class="form-label fw-bold text-success"><?php esc_html_e( 'Upload Photos to Album', 'ifsedu-sms' ); ?></label>
                <input type="file" name="gallery_photos[]" class="form-control" multiple accept="image/*">
                <small class="text-muted d-block mt-1"><?php esc_html_e( 'You can select multiple images simultaneously.', 'ifsedu-sms' ); ?></small>
            </div>

            <?php if ( ! empty( $photos ) ) : ?>
                <div class="mb-4">
                    <h5 class="fw-bold border-bottom pb-2"><?php esc_html_e( 'Current Photos in Album', 'ifsedu-sms' ); ?> (<?php echo count( $photos ); ?>)</h5>
                    <div class="row g-3">
                        <?php foreach ( $photos as $photo ) : 
                            $photo_del_url = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=delete_photo&id=' . $photo->id . '&album_id=' . $album_id ), 'delete_photo_' . $photo->id );
                        ?>
                            <div class="col-6 col-sm-4 col-md-3 col-lg-2 position-relative">
                                <div class="border rounded p-1 bg-white h-100 position-relative">
                                    <img src="<?php echo esc_url( $photo->image_url ); ?>" class="w-100 rounded" style="height: 100px; object-fit: cover;">
                                    <a href="<?php echo esc_url( $photo_del_url ); ?>" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 py-0 px-1" onclick="return confirm('Remove photo?');" title="Delete Photo">&times;</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <button type="submit" name="educore_save_gallery" class="btn btn-success px-5 fw-bold" style="background-color: #006a4e; border: none;">
                <?php echo $is_edit ? esc_html__( 'Update Album', 'ifsedu-sms' ) : esc_html__( 'Save Album', 'ifsedu-sms' ); ?>
            </button>
        </form>
    </div>
    <?php
}

/**
 * Handle Single Photo Deletion
 */
function educore_gallery_photo_delete_action() {
    global $wpdb;
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    $photo_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $album_id = isset( $_GET['album_id'] ) ? absint( $_GET['album_id'] ) : 0;
    $_nonce   = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';

    if ( $photo_id > 0 && wp_verify_nonce( $_nonce, 'delete_photo_' . $photo_id ) ) {
        $wpdb->delete( $table_photos, array( 'id' => $photo_id ), array( '%d' ) );
    }

    $target_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=edit&id=' . $album_id );
    educore_safe_redirect( $target_url );
}

/**
 * Handle Full Album Deletion
 */
function educore_gallery_delete_action() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    $id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $_nonce = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';

    if ( $id > 0 && wp_verify_nonce( $_nonce, 'delete_gallery_' . $id ) ) {
        $wpdb->delete( $table_photos, array( 'album_id' => $id ), array( '%d' ) );
        $wpdb->delete( $table_albums, array( 'id' => $id ), array( '%d' ) );
    }

    $target_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=list' );
    educore_safe_redirect( $target_url );
}