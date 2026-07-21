<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Integrated Navigation Router Matrix for Notice, Events & Gallery
 * Theme Aesthetic: Elite Neo-Bento Grid System
 * Custom Prefixes Applied: dpt-, afdp-
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

    <style>
        /* ==========================================================================
           NOTICE BOARD & COMMUNICATIONS NEO-BENTO ROUTER SYSTEM
           ========================================================================== */
        .dpt-communications-root {
            margin: 20px 20px 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #0f172a;
        }

        /* Top Modern Sub-Navigation Bar */
        .afdp-nav-bento-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
        }

        .dpt-nav-tabs-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .dpt-nav-tab-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
            color: #64748b;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .dpt-nav-tab-item .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
            color: #64748b;
            transition: color 0.2s ease-in-out;
        }

        .dpt-nav-tab-item:hover {
            color: #006a4e;
            background: #f0fdf4;
            border-color: #a7f3d0;
        }

        .dpt-nav-tab-item:hover .dashicons {
            color: #006a4e;
        }

        /* Active Tab State */
        .dpt-nav-tab-item.dpt-tab-active {
            color: #ffffff !important;
            background: #006a4e !important;
            border-color: #006a4e !important;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .dpt-nav-tab-item.dpt-tab-active .dashicons {
            color: #a7f3d0 !important;
        }

        /* Create Action Trigger Button */
        .dpt-btn-action-add {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            color: #006a4e;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            transition: all 0.2s ease-in-out;
        }

        .dpt-btn-action-add:hover {
            color: #ffffff;
            background: #006a4e;
            border-color: #006a4e;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.25);
            transform: translateY(-1px);
        }

        .dpt-viewport-container {
            width: 100%;
        }
    </style>

    <div class="dpt-communications-root">
        
        <!-- Top Sub-Navigation Menu Bar -->
        <div class="afdp-nav-bento-bar">
            <div class="dpt-nav-tabs-group">
                <!-- Notice Board Button -->
                <a href="<?php echo esc_url( $notice_url ); ?>" 
                   class="dpt-nav-tab-item <?php echo ( $current_type === 'notice' ) ? 'dpt-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'Notice Board', 'ifsedu-sms' ); ?>
                </a>

                <!-- Academic Events Button -->
                <a href="<?php echo esc_url( $events_url ); ?>" 
                   class="dpt-nav-tab-item <?php echo ( $current_type === 'events' ) ? 'dpt-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Academic Events', 'ifsedu-sms' ); ?>
                </a>

                <!-- Photo Gallery Button -->
                <a href="<?php echo esc_url( $gallery_url ); ?>" 
                   class="dpt-nav-tab-item <?php echo ( $current_type === 'gallery' ) ? 'dpt-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-format-gallery"></span> <?php esc_html_e( 'Photo Gallery', 'ifsedu-sms' ); ?>
                </a>
            </div>
            
            <div>
                <?php if ( $current_type === 'notice' || $current_type === 'events' ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notice&type=' . $current_type . '&sub=add' ) ); ?>" class="dpt-btn-action-add">
                        + <?php echo ( $current_type === 'events' ) ? esc_html__( 'Add New Event', 'ifsedu-sms' ) : esc_html__( 'Add New Notice', 'ifsedu-sms' ); ?>
                    </a>
                <?php elseif ( $current_type === 'gallery' ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notice&type=gallery&sub=add' ) ); ?>" class="dpt-btn-action-add">
                        + <?php esc_html_e( 'Create Photo Album', 'ifsedu-sms' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dynamic Viewport Engine Container -->
        <div class="dpt-viewport-container">
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