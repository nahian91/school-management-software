<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_exams_tab() {
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( $_GET['sub'] ) : 'list';

    // Construct URLs for top submenu links
    $all_exams_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=list' );
    $marks_url     = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=marks' );
    $report_url    = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=report' );
    ?>

    <!-- Top Sub-Navigation Menu Bar -->
    <div class="educore-top-nav mb-4 pb-2 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?php echo esc_url( $all_exams_url ); ?>" 
               class="btn <?php echo ( $sub_tab === 'list' ) ? 'btn-success fw-bold' : 'btn-outline-secondary'; ?>">
                <span class="dashicons dashicons-welcome-write-blog align-middle me-1"></span> All Examinations
            </a>
            <a href="<?php echo esc_url( $marks_url ); ?>" 
               class="btn <?php echo ( $sub_tab === 'marks' ) ? 'btn-success fw-bold' : 'btn-outline-secondary'; ?>">
                <span class="dashicons dashicons-edit align-middle me-1"></span> Marks Entry Matrix
            </a>
            <a href="<?php echo esc_url( $report_url ); ?>" 
               class="btn <?php echo ( $sub_tab === 'report' ) ? 'btn-success fw-bold' : 'btn-outline-secondary'; ?>">
                <span class="dashicons dashicons-clipboard align-middle me-1"></span> Progress & Tabulation Sheet
            </a>
        </div>

        <?php if ( in_array( $sub_tab, array( 'edit', 'view' ), true ) ) : ?>
            <div>
                <span class="badge bg-info text-dark fs-6 px-3 py-2">
                    <?php echo ucfirst( $sub_tab ); ?>ing Exam Scheme
                </span>
            </div>
        <?php endif; ?>
    </div>

    <div class="educore-module-container">
        <?php
        switch ( $sub_tab ) {
            case 'marks':
                if ( function_exists( 'educore_exams_marks_view' ) ) {
                    educore_exams_marks_view();
                } else {
                    echo '<div class="alert alert-info">Marks Entry Matrix module is initializing. Define <code>educore_exams_marks_view()</code>.</div>';
                }
                break;

            case 'report':
                if ( function_exists( 'educore_exams_report_view' ) ) {
                    educore_exams_report_view();
                } else {
                    echo '<div class="alert alert-info">Progress & Tabulation Sheet module is initializing. Define <code>educore_exams_report_view()</code>.</div>';
                }
                break;

            case 'list':
            default:
                if ( function_exists( 'educore_exams_list_view' ) ) {
                    educore_exams_list_view();
                } else {
                    echo '<div class="alert alert-info">Exams List View module is initializing. Define <code>educore_exams_list_view()</code>.</div>';
                }
                break;
        }
        ?>
    </div>
    <?php
}
?>