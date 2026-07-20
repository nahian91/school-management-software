<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_staff_tab() {
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( $_GET['sub'] ) : 'list';

    // Construct URLs for top submenu links
    $all_staff_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=list' );
    $add_staff_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=add' );
    ?>

    <!-- Top Sub-Navigation Menu Bar -->
    <div class="educore-top-nav mb-4 pb-2 border-bottom d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <a href="<?php echo esc_url( $all_staff_url ); ?>" 
               class="btn <?php echo ( $sub_tab === 'list' ) ? 'btn-success fw-bold' : 'btn-outline-secondary'; ?>">
                <span class="dashicons dashicons-businessman align-middle me-1"></span> All Staff & Teachers
            </a>
            <a href="<?php echo esc_url( $add_staff_url ); ?>" 
               class="btn <?php echo ( $sub_tab === 'add' ) ? 'btn-success fw-bold' : 'btn-outline-secondary'; ?>">
                <span class="dashicons dashicons-plus-alt2 align-middle me-1"></span> + Add New Staff
            </a>
        </div>

        <?php if ( $sub_tab === 'edit' || $sub_tab === 'view' ) : ?>
            <div>
                <span class="badge bg-info text-dark fs-6 px-3 py-2">
                    <?php echo ucfirst( $sub_tab ); ?>ing Staff Record
                </span>
            </div>
        <?php endif; ?>
    </div>

    <div class="educore-module-container">
        <?php
        switch ( $sub_tab ) {
            case 'add':
            case 'edit':
                if ( function_exists( 'educore_staff_add_edit_view' ) ) {
                    educore_staff_add_edit_view();
                }
                break;

            case 'delete':
                if ( function_exists( 'educore_staff_delete_action' ) ) {
                    educore_staff_delete_action();
                }
                break;

            case 'list':
            default:
                if ( function_exists( 'educore_staff_list_view' ) ) {
                    educore_staff_list_view();
                }
                break;
        }
        ?>
    </div>
    <?php
}
?>