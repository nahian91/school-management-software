<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_reports_tab() {
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( $_GET['sub'] ) : 'finance';

    $finance_url    = admin_url( 'admin.php?page=school_management_system&tab=reports&sub=finance' );
    $attendance_url = admin_url( 'admin.php?page=school_management_system&tab=reports&sub=attendance' );

    echo '<div class="educore-module-container">';
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><span class="dashicons dashicons-chart-pie"></span> System Reports</h2>
    </div>

    <!-- Report Navigation -->
    <div class="mb-4 pb-2 border-bottom">
        <a href="<?php echo esc_url( $finance_url ); ?>" class="btn <?php echo $sub_tab === 'finance' ? 'btn-primary' : 'btn-outline-primary'; ?> me-2" style="<?php echo $sub_tab === 'finance' ? 'background-color: #3b82f6; border-color: #3b82f6;' : ''; ?>">
            <span class="dashicons dashicons-money-alt"></span> Financial Report
        </a>
        <a href="<?php echo esc_url( $attendance_url ); ?>" class="btn <?php echo $sub_tab === 'attendance' ? 'btn-primary' : 'btn-outline-primary'; ?>" style="<?php echo $sub_tab === 'attendance' ? 'background-color: #3b82f6; border-color: #3b82f6;' : ''; ?>">
            <span class="dashicons dashicons-clipboard"></span> Attendance Report
        </a>
    </div>

    <?php
    switch ( $sub_tab ) {
        case 'attendance':
            if ( function_exists( 'educore_reports_attendance_view' ) ) {
                educore_reports_attendance_view();
            }
            break;
        case 'finance':
        default:
            if ( function_exists( 'educore_reports_finance_view' ) ) {
                educore_reports_finance_view();
            }
            break;
    }

    echo '</div>';
}
?>