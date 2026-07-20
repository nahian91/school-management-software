<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_students_tab() {
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( $_GET['sub'] ) : 'list';
    
    // Construct URLs for top submenu links
    $all_students_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );
    $add_student_url  = admin_url( 'admin.php?page=school_management_system&tab=students&sub=add' );
    $id_card_url      = admin_url( 'admin.php?page=school_management_system&tab=students&sub=id_card' );
    $admit_card_url   = admin_url( 'admin.php?page=school_management_system&tab=students&sub=admit_card' );
    ?>

    <!-- Top Sub-Navigation Menu Bar -->
    <div class="educore-top-nav mb-4 pb-2 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?php echo esc_url( $all_students_url ); ?>" 
               class="btn <?php echo ( $sub_tab === 'list' ) ? 'btn-success fw-bold' : 'btn-outline-secondary'; ?>">
                <span class="dashicons dashicons-groups align-middle me-1"></span> All Students
            </a>
            <a href="<?php echo esc_url( $add_student_url ); ?>" 
               class="btn <?php echo ( $sub_tab === 'add' ) ? 'btn-success fw-bold' : 'btn-outline-secondary'; ?>">
                <span class="dashicons dashicons-plus-alt2 align-middle me-1"></span> + Add New Student
            </a>
            <a href="<?php echo esc_url( $id_card_url ); ?>" 
               class="btn <?php echo ( $sub_tab === 'id_card' ) ? 'btn-success fw-bold' : 'btn-outline-secondary'; ?>">
                <span class="dashicons dashicons-id-alt align-middle me-1"></span> Student ID Cards
            </a>
            <a href="<?php echo esc_url( $admit_card_url ); ?>" 
               class="btn <?php echo ( $sub_tab === 'admit_card' ) ? 'btn-success fw-bold' : 'btn-outline-secondary'; ?>">
                <span class="dashicons dashicons-tickets-alt align-middle me-1"></span> Admit Cards
            </a>
        </div>

        <?php if ( in_array( $sub_tab, array( 'edit', 'view' ), true ) ) : ?>
            <div>
                <span class="badge bg-info text-dark fs-6 px-3 py-2">
                    <?php echo ucfirst( $sub_tab ); ?>ing Student Record
                </span>
            </div>
        <?php endif; ?>
    </div>

    <div class="educore-module-container">
        <?php
        switch ( $sub_tab ) {
            case 'add':
            case 'edit':
                if ( function_exists( 'educore_student_add_edit_view' ) ) {
                    educore_student_add_edit_view();
                }
                break;

            case 'view':
                if ( function_exists( 'educore_student_profile_view' ) ) {
                    educore_student_profile_view();
                }
                break;

            case 'id_card':
                if ( function_exists( 'educore_student_id_card_view' ) ) {
                    educore_student_id_card_view();
                } else {
                    echo '<div class="alert alert-info">Student ID Card Generator module is initializing. Define <code>educore_student_id_card_view()</code>.</div>';
                }
                break;

            case 'admit_card':
                if ( function_exists( 'educore_student_admit_card_view' ) ) {
                    educore_student_admit_card_view();
                } else {
                    echo '<div class="alert alert-info">Admit Card Generator module is initializing. Define <code>educore_student_admit_card_view()</code>.</div>';
                }
                break;

            case 'delete':
                if ( function_exists( 'educore_student_delete_action' ) ) {
                    educore_student_delete_action();
                }
                break;

            case 'list':
            default:
                if ( function_exists( 'educore_students_list_view' ) ) {
                    educore_students_list_view();
                }
                break;
        }
        ?>
    </div>
    <?php
}
?>