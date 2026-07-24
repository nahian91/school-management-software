<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Single Detail View Page for Notices & Events
 * Theme Aesthetic: Elite Neo-Bento UI Architecture
 * Custom Prefixes Applied: dpt-, afdp-
 */
function educore_notice_events_single_view( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    $id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_notices} WHERE id = %d", $id ) );

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=' . $type . '&sub=list' );

    if ( ! $item ) {
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
            <?php esc_html_e( 'Record not found or has been deleted.', 'ifsedu-sms' ); ?>
        </div>
        <?php
        return;
    }

    // Dynamic Date Processing
    $display_date = ( ! empty( $item->event_date ) && $item->event_date !== '1970-01-01' ) 
        ? date_i18n( 'F j, Y', strtotime( $item->event_date ) ) 
        : date_i18n( 'F j, Y', strtotime( $item->created_at ) );

    // Dynamic Badge Styling Logic
    $priority_class = 'dpt-priority-normal';
    if ( $item->priority === 'High' ) {
        $priority_class = 'dpt-priority-high';
    } elseif ( $item->priority === 'Urgent' ) {
        $priority_class = 'dpt-priority-urgent';
    }

    $status_class   = ( $item->status === 'Published' ) ? 'dpt-status-published' : 'dpt-status-draft';
    $featured_image = isset( $item->featured_image ) ? $item->featured_image : '';
    ?>

    <style>
        /* ==========================================================================
           SINGLE NOTICE/EVENT DETAIL VIEW - NEO-BENTO ARCHITECTURE
           ========================================================================== */
        .dpt-single-root {
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

        /* Main Bento Container Card */
        .dpt-single-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        /* Header Frame */
        .afdp-single-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            flex-wrap: wrap;
        }

        .afdp-single-title {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 8px 0;
            letter-spacing: -0.5px;
            line-height: 1.3;
        }

        /* Hero Featured Image Banner Frame */
        .dpt-hero-featured-banner {
            width: 100%;
            max-height: 420px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dpt-hero-featured-banner img {
            width: 100%;
            height: 100%;
            max-height: 420px;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease;
        }

        .dpt-hero-featured-banner img:hover {
            transform: scale(1.01);
        }

        /* Metadata Bento Metrics Strip */
        .dpt-meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
        }

        @media (max-width: 768px) {
            .dpt-meta-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .dpt-meta-grid {
                grid-template-columns: 1fr;
            }
        }

        .dpt-meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .dpt-meta-label {
            font-size: 11.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dpt-meta-value {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Status & Priority Badge System */
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

        .dpt-priority-normal { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
        .dpt-priority-high   { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .dpt-priority-urgent { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        .dpt-status-published { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .dpt-status-draft     { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        /* Rich Content Viewport Node */
        .dpt-content-body {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            font-size: 14.5px;
            line-height: 1.7;
            color: #334155;
            margin-bottom: 28px;
            min-height: 120px;
        }

        .dpt-content-body p {
            margin-top: 0;
        }

        .dpt-content-body p:last-child {
            margin-bottom: 0;
        }

        /* Attachment Download Bento Component */
        .dpt-attachment-card {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .dpt-attachment-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13.5px;
            font-weight: 700;
            color: #065f46;
        }

        .dpt-attachment-info .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
            color: #006a4e;
        }

        .dpt-btn-download {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            background: #006a4e;
            color: #ffffff;
            font-size: 12.5px;
            font-weight: 700;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 106, 78, 0.2);
        }

        .dpt-btn-download:hover {
            background: #00523c;
            color: #ffffff;
            transform: translateY(-1px);
        }
    </style>

    <div class="dpt-single-root">
        
        <!-- Navigation Back Action -->
        <div class="dpt-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Directory', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <!-- Main Detail Bento Card -->
        <div class="dpt-single-bento-card">
            
            <!-- Header Section -->
            <div class="afdp-single-header">
                <div>
                    <h2 class="afdp-single-title"><?php echo esc_html( $item->title ); ?></h2>
                </div>
                <div>
                    <span class="dpt-badge-node <?php echo esc_attr( $status_class ); ?>">
                        <?php echo esc_html( $item->status ); ?>
                    </span>
                </div>
            </div>

            <!-- Hero Featured Image Banner Node (if available) -->
            <?php if ( ! empty( $featured_image ) ) : ?>
                <div class="dpt-hero-featured-banner">
                    <a href="<?php echo esc_url( $featured_image ); ?>" target="_blank" title="<?php esc_attr_e( 'View Full Image', 'ifsedu-sms' ); ?>">
                        <img src="<?php echo esc_url( $featured_image ); ?>" alt="<?php echo esc_attr( $item->title ); ?>">
                    </a>
                </div>
            <?php endif; ?>

            <!-- Metadata Metrics Grid -->
            <div class="dpt-meta-grid">
                <div class="dpt-meta-item">
                    <span class="dpt-meta-label"><?php esc_html_e( 'Category Type', 'ifsedu-sms' ); ?></span>
                    <span class="dpt-meta-value">
                        <span class="dashicons dashicons-tag" style="color: #64748b; font-size:16px;"></span>
                        <?php echo esc_html( $item->notice_type ); ?>
                    </span>
                </div>

                <div class="dpt-meta-item">
                    <span class="dpt-meta-label"><?php esc_html_e( 'Target Audience', 'ifsedu-sms' ); ?></span>
                    <span class="dpt-meta-value">
                        <span class="dashicons dashicons-groups" style="color: #64748b; font-size:16px;"></span>
                        <?php echo esc_html( $item->target_audience ); ?>
                    </span>
                </div>

                <div class="dpt-meta-item">
                    <span class="dpt-meta-label"><?php esc_html_e( 'Priority Level', 'ifsedu-sms' ); ?></span>
                    <span class="dpt-meta-value">
                        <span class="dpt-badge-node <?php echo esc_attr( $priority_class ); ?>">
                            <?php echo esc_html( $item->priority ); ?>
                        </span>
                    </span>
                </div>

                <div class="dpt-meta-item">
                    <span class="dpt-meta-label">
                        <?php echo ( $type === 'events' ) ? esc_html__( 'Event Date', 'ifsedu-sms' ) : esc_html__( 'Published Date', 'ifsedu-sms' ); ?>
                    </span>
                    <span class="dpt-meta-value">
                        <span class="dashicons dashicons-calendar-alt" style="color: #006a4e; font-size:16px;"></span>
                        <?php echo esc_html( $display_date ); ?>
                    </span>
                </div>
            </div>

            <!-- Rich Description Viewport -->
            <div class="dpt-content-body">
                <?php echo wp_kses_post( $item->description ); ?>
            </div>

            <!-- Attachment Node (if present) -->
            <?php if ( ! empty( $item->attachment_url ) ) : ?>
                <div class="dpt-attachment-card">
                    <div class="dpt-attachment-info">
                        <span class="dashicons dashicons-paperclip"></span>
                        <?php esc_html_e( 'Official Attached Document / File Available', 'ifsedu-sms' ); ?>
                    </div>
                    <a href="<?php echo esc_url( $item->attachment_url ); ?>" target="_blank" class="dpt-btn-download">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e( 'View / Download Attachment', 'ifsedu-sms' ); ?>
                    </a>
                </div>
            <?php endif; ?>

        </div>

    </div>
    <?php
}