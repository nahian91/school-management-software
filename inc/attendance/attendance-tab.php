<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Attendance Sub-Navigation Router Engine
 * File: inc/attendance/attendance-tab.php
 */
function educore_attendance_tab() {
    global $wpdb;

    $table_units = $wpdb->prefix . 'sms_academic_units';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage attendance configurations.', 'ifsedu-sms' ) );
    }

    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( wp_unslash( $_GET['sub'] ) ) : 'daily';

    $filter_class   = isset( $_REQUEST['class_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['class_name'] ) ) : '';
    $filter_section = isset( $_REQUEST['section_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['section_name'] ) ) : '';
    $filter_date    = isset( $_REQUEST['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['attendance_date'] ) ) : current_time( 'Y-m-d' );

    // Fetch Unique Classes with Natural Sorting
    $raw_classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    $classes     = array();
    if ( ! empty( $raw_classes ) ) {
        usort( $raw_classes, function( $a, $b ) {
            return strnatcasecmp( $a->class_name, $b->class_name );
        });
        foreach ( $raw_classes as $cls_obj ) {
            $classes[] = $cls_obj->class_name;
        }
    }

    // Fetch Sections for Class Filter
    $sections = array();
    if ( ! empty( $filter_class ) ) {
        $raw_sections = $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT section_name FROM {$table_units} WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
            $filter_class
        ) );
        if ( ! empty( $raw_sections ) ) {
            foreach ( $raw_sections as $sec_obj ) {
                $sections[] = $sec_obj->section_name;
            }
        }
    }

    // Tab URLs
    $daily_url   = admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=daily' );
    $monthly_url = admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=monthly' );
    $staff_url   = admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=staff' );
    $reports_url = admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=reports' );
    ?>

    <style>
        .dpt-attendance-root { margin: 20px 20px 24px 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .afdp-top-nav-wrapper { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 18px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 24px; }
        .dpt-nav-button-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .dpt-nav-link { height: 38px; padding: 0 16px; border-radius: 8px; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; text-decoration: none; transition: all 0.2s ease; border: 1px solid transparent; }
        .dpt-nav-link-active { background: #006a4e; color: #ffffff; font-weight: 700; box-shadow: 0 4px 12px rgba(0, 106, 78, 0.15); }
        .dpt-nav-link-inactive { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
        .dpt-nav-link-inactive:hover { background: #f1f5f9; color: #0f172a; }

        .dpt-bento-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); margin-bottom: 24px; }
        .afdp-success-banner { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 14px 18px; color: #065f46; font-weight: 600; font-size: 14px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }

        .dpt-form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end; }
        .dpt-form-group { display: flex; flex-direction: column; gap: 6px; }
        .dpt-form-label { font-size: 12.5px; font-weight: 700; color: #475569; margin: 0; }
        .dpt-input-field, .dpt-select-field { height: 40px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13.5px; color: #0f172a; background-color: #f8fafc; width: 100%; box-shadow: none; transition: all 0.2s; }
        .dpt-input-field:focus, .dpt-select-field:focus { border-color: #006a4e; background-color: #ffffff; box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1); outline: none; }

        .dpt-btn-submit-trigger { height: 40px; background: #006a4e; border: 1px solid transparent; color: #ffffff; font-weight: 700; font-size: 13.5px; border-radius: 8px; padding: 0 20px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; box-shadow: 0 4px 12px rgba(0, 106, 78, 0.15); }
        .dpt-btn-submit-trigger:hover { background: #00523c; color: #ffffff; }

        .afdp-roster-meta-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px; }
        .dpt-counter-cluster { display: flex; gap: 8px; flex-wrap: wrap; }
        .dpt-badge-pill { font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 20px; border: 1px solid transparent; }
        .dpt-badge-total   { background: #f1f5f9; border-color: #e2e8f0; color: #334155; }
        .dpt-badge-present { background: #e6f4ea; border-color: #ceead6; color: #137333; }
        .dpt-badge-absent  { background: #fce8e6; border-color: #fad2cf; color: #c5221f; }
        .dpt-badge-late    { background: #fef7e0; border-color: #feebc8; color: #b06000; }

        .afdp-bulk-automation-row { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .dpt-bulk-btn { background: #ffffff; border: 1px solid #cbd5e1; padding: 7px 16px; font-size: 12.5px; font-weight: 700; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; }
        .dpt-bulk-btn[data-target-status="Present"]:hover { border-color: #006a4e; color: #006a4e; background: #f0fdf4; }
        .dpt-bulk-btn[data-target-status="Absent"]:hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }
        .dpt-bulk-btn[data-target-status="Late"]:hover { border-color: #d97706; color: #d97706; background: #fffbeb; }

        .dpt-table-responsive { width: 100%; overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 12px; }
        .dpt-attendance-matrix-table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; }
        .dpt-attendance-matrix-table th { background: #f8fafc; color: #475569; font-weight: 700; font-size: 12.5px; padding: 14px 16px; border-bottom: 1px solid #e2e8f0; }
        .dpt-attendance-matrix-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; color: #334155; background: #ffffff; }

        .afdp-checkbox-group { display: inline-flex; background: #f1f5f9; padding: 4px; border-radius: 10px; border: 1px solid #e2e8f0; gap: 4px; }
        .afdp-checkbox-item { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
        .afdp-checkbox-label { display: inline-flex; align-items: center; gap: 6px; padding: 7px 18px; font-size: 12.5px; font-weight: 700; border-radius: 7px; cursor: pointer; transition: all 0.2s ease; color: #64748b; line-height: 1; }
        .afdp-checkbox-label svg { width: 14px; height: 14px; fill: currentColor; opacity: 0.6; }
        
        .afdp-checkbox-item[value="Present"]:checked + .afdp-checkbox-label { background: #006a4e; color: #ffffff; box-shadow: 0 2px 8px rgba(0, 106, 78, 0.3); }
        .afdp-checkbox-item[value="Absent"]:checked + .afdp-checkbox-label { background: #dc2626; color: #ffffff; box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3); }
        .afdp-checkbox-item[value="Late"]:checked + .afdp-checkbox-label { background: #d97706; color: #ffffff; box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3); }

        .afdp-fallback-card { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 40px 20px; text-align: center; }
        .afdp-fallback-card .dashicons { font-size: 36px; width: 36px; height: 36px; color: #94a3b8; margin-bottom: 10px; }
        .afdp-fallback-card p { margin: 0; font-size: 14px; color: #64748b; font-weight: 600; }

        @media print { .no-print { display: none !important; } .dpt-bento-card { border: none !important; box-shadow: none !important; padding: 0 !important; } }
    </style>

    <div class="dpt-attendance-root">
        
        <!-- Sub-Navigation Header Bar -->
        <div class="afdp-top-nav-wrapper no-print">
            <div class="dpt-nav-button-group">
                <a href="<?php echo esc_url( $daily_url ); ?>" class="dpt-nav-link <?php echo ( $sub_tab === 'daily' || $sub_tab === 'roster' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Daily', 'ifsedu-sms' ); ?>
                </a>

                <a href="<?php echo esc_url( $monthly_url ); ?>" class="dpt-nav-link <?php echo ( $sub_tab === 'monthly' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php esc_html_e( 'Monthly', 'ifsedu-sms' ); ?>
                </a>

                <a href="<?php echo esc_url( $staff_url ); ?>" class="dpt-nav-link <?php echo ( $sub_tab === 'staff' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-businessman"></span>
                    <?php esc_html_e( 'Staff', 'ifsedu-sms' ); ?>
                </a>

                <a href="<?php echo esc_url( $reports_url ); ?>" class="dpt-nav-link <?php echo ( $sub_tab === 'reports' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e( 'Reports', 'ifsedu-sms' ); ?>
                </a>
            </div>
        </div>

        <div class="dpt-module-viewport-container">
            <?php
            switch ( $sub_tab ) {
                case 'monthly':
                    if ( function_exists( 'educore_monthly_attendance_summary_view' ) ) {
                        educore_monthly_attendance_summary_view( $classes, $sections, $filter_class, $filter_section );
                    }
                    break;

                case 'staff':
                    if ( function_exists( 'educore_staff_attendance_view' ) ) {
                        educore_staff_attendance_view();
                    }
                    break;

                case 'reports':
                    if ( function_exists( 'educore_student_attendance_log_view' ) ) {
                        educore_student_attendance_log_view( $classes );
                    }
                    break;

                case 'daily':
                case 'roster':
                default:
                    if ( function_exists( 'educore_daily_attendance_view' ) ) {
                        educore_daily_attendance_view( $classes, $sections, $filter_class, $filter_section, $filter_date );
                    }
                    break;
            }
            ?>
        </div>
    </div>

    <!-- Script Layer -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var nonce = '<?php echo esc_js( wp_create_nonce( "educore_attendance_nonce" ) ); ?>';

        $('#educore_attendance_class_select').on('change', function() {
            var selectedClass   = $(this).val();
            var $sectionSelect = $('#educore_attendance_section_select');

            $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Sections... --', 'ifsedu-sms' ) ); ?></option>');

            if (!selectedClass) {
                $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>');
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'educore_get_sections_by_class_attendance',
                    security: nonce,
                    class_name: selectedClass
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var options = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>';
                        $.each(response.data, function(i, sec) {
                            options += '<option value="' + sec + '">' + sec + '</option>';
                        });
                        $sectionSelect.html(options);
                    } else {
                        $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-sms' ) ); ?></option>');
                    }
                }
            });
        });

        function recountLiveStatisticsDashboard() {
            var total   = $('.student-attendance-row').length;
            var present = $('.status-radio-node[value="Present"]:checked').length;
            var absent  = $('.status-radio-node[value="Absent"]:checked').length;
            var late    = $('.status-radio-node[value="Late"]:checked').length;

            $('#cnt-total').text(total);
            $('#cnt-present').text(present);
            $('#cnt-absent').text(absent);
            $('#cnt-late').text(late);
        }

        recountLiveStatisticsDashboard();

        $('.status-radio-node').on('change', function() {
            recountLiveStatisticsDashboard();
        });

        $('.dpt-bulk-btn').on('click', function() {
            var targetedStatusType = $(this).data('target-status');
            $('.student-attendance-row').each(function() {
                $(this).find('.status-radio-node[value="' + targetedStatusType + '"]').prop('checked', true);
            });
            recountLiveStatisticsDashboard();
        });
    });
    </script>
    <?php
}