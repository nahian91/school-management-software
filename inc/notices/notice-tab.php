<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Integrated Navigation Router Matrix for Notice, Events & Gallery
 */
function educore_notice_tab() {
    // Keep 'tab' locked to 'notice' to preserve WP page context, use 'type' for sub-modules
    $current_type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : 'notice';
    $sub_tab      = isset( $_GET['sub'] ) ? sanitize_text_field( $_GET['sub'] ) : 'list';

    // Validate type parameter
    if ( ! in_array( $current_type, array( 'notice', 'events', 'gallery' ), true ) ) {
        $current_type = 'notice';
    }

    // Dynamic Navigation URLs
    $notice_url  = admin_url( 'admin.php?page=school_management_system&tab=notice&type=notice&sub=list' );
    $events_url  = admin_url( 'admin.php?page=school_management_system&tab=notice&type=events&sub=list' );
    $gallery_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=list' );
    ?>

    <div class="educore-module-wrapper my-3">
        <!-- Top Sub-Navigation Menu Bar -->
        <div class="bg-white p-3 rounded border shadow-sm mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex gap-2">
                <a href="<?php echo esc_url( $notice_url ); ?>" 
                   class="btn btn-sm <?php echo ( $current_type === 'notice' ) ? 'btn-success fw-bold' : 'btn-light border'; ?>" 
                   style="<?php echo ( $current_type === 'notice' ) ? 'background-color: #006a4e; border-color: #006a4e; color: #fff;' : ''; ?>">
                    <span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'Notice Board', 'ifsedu-sms' ); ?>
                </a>

                <a href="<?php echo esc_url( $events_url ); ?>" 
                   class="btn btn-sm <?php echo ( $current_type === 'events' ) ? 'btn-success fw-bold' : 'btn-light border'; ?>" 
                   style="<?php echo ( $current_type === 'events' ) ? 'background-color: #006a4e; border-color: #006a4e; color: #fff;' : ''; ?>">
                    <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Academic Events', 'ifsedu-sms' ); ?>
                </a>

                <a href="<?php echo esc_url( $gallery_url ); ?>" 
                   class="btn btn-sm <?php echo ( $current_type === 'gallery' ) ? 'btn-success fw-bold' : 'btn-light border'; ?>" 
                   style="<?php echo ( $current_type === 'gallery' ) ? 'background-color: #006a4e; border-color: #006a4e; color: #fff;' : ''; ?>">
                    <span class="dashicons dashicons-format-gallery"></span> <?php esc_html_e( 'Photo Gallery', 'ifsedu-sms' ); ?>
                </a>
            </div>
            
            <div>
                <?php if ( $current_type === 'notice' || $current_type === 'events' ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notice&type=' . $current_type . '&sub=add' ) ); ?>" class="btn btn-sm btn-outline-success fw-bold">
                        + <?php echo ( $current_type === 'events' ) ? esc_html__( 'Add New Event', 'ifsedu-sms' ) : esc_html__( 'Add New Notice', 'ifsedu-sms' ); ?>
                    </a>
                <?php elseif ( $current_type === 'gallery' ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=add' ) ); ?>" class="btn btn-sm btn-outline-success fw-bold">
                        + <?php esc_html_e( 'Create Photo Album', 'ifsedu-sms' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dynamic Viewport Engine -->
        <div class="educore-viewport-container">
            <?php
            if ( $current_type === 'gallery' ) {
                if ( function_exists( 'educore_gallery_router' ) ) {
                    educore_gallery_router( $sub_tab );
                }
            } else {
                educore_notice_events_router( $current_type, $sub_tab );
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Sub-Router for Notices & Academic Events
 */
function educore_notice_events_router( $type, $sub_tab ) {
    switch ( $sub_tab ) {
        case 'add':
        case 'edit':
            if ( function_exists( 'educore_notice_events_add_edit_view' ) ) {
                educore_notice_events_add_edit_view( $type );
            }
            break;

        case 'view':
            if ( function_exists( 'educore_notice_events_single_view' ) ) {
                educore_notice_events_single_view( $type );
            }
            break;

        case 'delete':
            if ( function_exists( 'educore_notice_events_delete_action' ) ) {
                educore_notice_events_delete_action( $type );
            }
            break;

        case 'list':
        default:
            if ( function_exists( 'educore_notice_events_list_view' ) ) {
                educore_notice_events_list_view( $type );
            }
            break;
    }
}

/**
 * Universal Safe JS/PHP Redirection Helper
 */
function educore_safe_redirect( $url ) {
    if ( ! headers_sent() ) {
        wp_safe_redirect( $url );
        exit;
    } else {
        echo '<script type="text/javascript">';
        echo 'window.location.href="' . esc_url_raw( $url ) . '";';
        echo '</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url( $url ) . '" /></noscript>';
        exit;
    }
}