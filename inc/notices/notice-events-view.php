<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Single Detail View Page for Notices & Events
 */
function educore_notice_events_single_view( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    $id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_notices} WHERE id = %d", $id ) );

    if ( ! $item ) {
        echo '<div class="alert alert-danger">' . esc_html__( 'Record not found.', 'ifsedu-sms' ) . '</div>';
        return;
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notice&type=' . $type . '&sub=list' );
    ?>

    <div class="mb-3">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; <?php esc_html_e( 'Back to Directory', 'ifsedu-sms' ); ?></a>
    </div>

    <div class="bg-white p-5 rounded shadow-sm border">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <h2 class="m-0 text-dark fw-bold"><?php echo esc_html( $item->title ); ?></h2>
            <span class="badge bg-success fs-6"><?php echo esc_html( $item->status ); ?></span>
        </div>

        <div class="row mb-4 text-muted small">
            <div class="col-md-3"><strong>Type:</strong> <?php echo esc_html( $item->notice_type ); ?></div>
            <div class="col-md-3"><strong>Target:</strong> <?php echo esc_html( $item->target_audience ); ?></div>
            <div class="col-md-3"><strong>Priority:</strong> <?php echo esc_html( $item->priority ); ?></div>
            <div class="col-md-3"><strong>Date:</strong> <?php echo esc_html( $item->event_date ?: $item->created_at ); ?></div>
        </div>

        <div class="border p-4 rounded bg-light mb-4">
            <?php echo wp_kses_post( $item->description ); ?>
        </div>

        <?php if ( ! empty( $item->attachment_url ) ) : ?>
            <div class="p-3 border rounded bg-white d-inline-block">
                <strong>Attached Document:</strong> 
                <a href="<?php echo esc_url( $item->attachment_url ); ?>" target="_blank" class="btn btn-sm btn-outline-success ms-2">
                    <span class="dashicons dashicons-download"></span> View / Download
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php
}