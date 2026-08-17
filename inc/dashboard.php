<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety
}

/**
 * Enterprise Multi-Role Dashboard Dispatcher & Elite Neo-Bento Matrix
 * File: inc/dashboard.php
 * Palette: Institutional Green Accent (#006a4e) with Dynamic Profile Headers across all roles
 */
function educore_dashboard_view() {
    $current_user = wp_get_current_user();
    $roles        = (array) $current_user->roles;

    // Detect user persona & route to dedicated dashboard view
    if ( in_array( 'administrator', $roles, true ) || current_user_can( 'manage_options' ) ) {
        educore_admin_dashboard_view( $current_user );
    } elseif ( in_array( 'teacher', $roles, true ) || in_array( 'instructor', $roles, true ) || current_user_can( 'edit_posts' ) ) {
        educore_teacher_dashboard_view( $current_user );
    } elseif ( in_array( 'accountant', $roles, true ) ) {
        educore_accountant_dashboard_view( $current_user );
    } else {
        educore_student_guardian_dashboard_view( $current_user );
    }
}

/**
 * Global Shared Design Stylesheet & User Profile Hero Section
 */
function educore_dashboard_render_hero_profile( $user, $role_title, $extra_meta = array(), $action_btn = null ) {
    global $wpdb;

    $table_staff    = $wpdb->prefix . 'sms_staff';
    $table_students = $wpdb->prefix . 'sms_students';
    
    $custom_avatar = '';
    $designation   = '';
    $display_name  = $user->display_name;

    // 1. Resolve Profile Details from Staff Table
    $staff_row = $wpdb->get_row( $wpdb->prepare( "SELECT full_name, designation, profile_image FROM {$table_staff} WHERE wp_user_id = %d OR email = %s LIMIT 1", $user->ID, $user->user_email ) );
    
    if ( $staff_row ) {
        $display_name  = $staff_row->full_name ?: $display_name;
        $designation   = $staff_row->designation;
        $custom_avatar = $staff_row->profile_image;
    } else {
        // 2. Resolve Profile Details from Student Table
        $student_row = $wpdb->get_row( $wpdb->prepare( "SELECT full_name, photo_url, class_name, section_name, roll_no FROM {$table_students} WHERE student_email = %s OR full_name = %s LIMIT 1", $user->user_email, $user->display_name ) );
        if ( $student_row ) {
            $display_name  = $student_row->full_name ?: $display_name;
            $custom_avatar = $student_row->photo_url;
            $designation   = sprintf( __( 'Class %s (Sec: %s, Roll: #%d)', 'ifsedu-sms' ), $student_row->class_name, $student_row->section_name ?: 'A', $student_row->roll_no );
        }
    }

    if ( empty( $custom_avatar ) ) {
        $custom_avatar = get_avatar_url( $user->ID, array( 'size' => 128 ) );
    }

    $current_hour = (int) current_time( 'G' );
    $greeting = __( 'Good Morning', 'ifsedu-sms' );
    if ( $current_hour >= 12 && $current_hour < 17 ) {
        $greeting = __( 'Good Afternoon', 'ifsedu-sms' );
    } elseif ( $current_hour >= 17 ) {
        $greeting = __( 'Good Evening', 'ifsedu-sms' );
    }
    ?>
    <style>
        .dpt-dash-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            margin: 20px 24px 32px 0;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Elite Frosted Glass Institutional Hero Banner */
        .dpt-dash-hero-green {
            background: linear-gradient(135deg, #002e20 0%, #004d38 50%, #006a4e 100%);
            border-radius: 20px;
            padding: 30px 36px;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 24px;
            box-shadow: 0 14px 35px -10px rgba(0, 106, 78, 0.3);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .dpt-dash-hero-green::before {
            content: '';
            position: absolute;
            right: -60px;
            bottom: -60px;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .dpt-profile-flex {
            display: flex;
            align-items: center;
            gap: 22px;
            z-index: 1;
        }

        .dpt-profile-avatar {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
            object-fit: cover;
            background: #ffffff;
            flex-shrink: 0;
        }

        .dpt-hero-badge {
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(6px);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 6px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dpt-hero-title {
            margin: 0 0 6px 0;
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        .dpt-hero-meta-strip {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            font-size: 13.5px;
            opacity: 0.92;
        }

        .dpt-hero-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .dpt-hero-meta-item strong {
            color: #ffffff;
        }

        .dpt-banner-datetime-pill {
            background: rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 14px 22px;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            text-align: right;
            z-index: 1;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .dpt-banner-date {
            font-size: 12px;
            font-weight: 600;
            color: #a7f3d0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .dpt-banner-clock {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        /* Elite Bento Cards Architecture */
        .dpt-stat-bento {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 24px 26px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
            box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dpt-stat-bento:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px -6px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
        }

        .dpt-stat-top-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .dpt-sub-stat-label {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }

        .dpt-stat-icon-badge {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }

        .dpt-stat-footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 14px;
            font-size: 12.5px;
            font-weight: 700;
        }

        .dpt-stat-footer-row a {
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .dpt-stat-footer-row a:hover {
            opacity: 0.75;
        }

        .dpt-bento-grid-4-sub {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 4px 15px -3px rgba(0,0,0,0.02);
        }

        @media (max-width: 900px) {
            .dpt-bento-grid-4-sub { grid-template-columns: repeat(2, 1fr); }
        }

        .dpt-sub-stat-box {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .dpt-sub-stat-box:not(:last-child) {
            border-right: 1px solid #f1f5f9;
            padding-right: 16px;
        }

        .dpt-sub-stat-val {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
        }

        .dpt-split-layout {
            display: grid;
            grid-template-columns: 1.8fr 1.2fr;
            gap: 24px;
        }

        @media (max-width: 1024px) {
            .dpt-split-layout { grid-template-columns: 1fr; }
        }

        .dpt-panel-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 26px 28px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }

        .dpt-panel-header {
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dpt-panel-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.2px;
        }

        .dpt-command-dock-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        @media (max-width: 600px) {
            .dpt-command-dock-grid { grid-template-columns: repeat(2, 1fr); }
        }

        .dpt-command-tile {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 14px;
            text-decoration: none;
            color: #334155;
            font-weight: 800;
            font-size: 13px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-align: center;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dpt-command-tile .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
            color: #006a4e;
            transition: transform 0.2s ease;
        }

        .dpt-command-tile:hover {
            background: #006a4e;
            color: #ffffff;
            border-color: #006a4e;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 106, 78, 0.25);
        }

        .dpt-command-tile:hover .dashicons {
            color: #ffffff !important;
            transform: scale(1.1);
        }

        .dpt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }

        .dpt-table th, .dpt-table td {
            border-bottom: 1px solid #f1f5f9;
            padding: 12px 14px;
            text-align: left;
            vertical-align: middle;
        }

        .dpt-table th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 800;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .dpt-dash-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .dpt-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            padding: 16px 20px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .dpt-list-item:hover {
            background: #ffffff;
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }

        .dpt-badge-pill {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 800;
            display: inline-block;
        }
    </style>

    <!-- Unified Profile Banner for All Roles -->
    <div class="dpt-dash-hero-green">
        <div class="dpt-profile-flex">
            <img src="<?php echo esc_url( $custom_avatar ); ?>" alt="User Avatar" class="dpt-profile-avatar">
            <div>
                <span class="dpt-hero-badge"><?php echo esc_html( $role_title ); ?></span>
                <h2 class="dpt-hero-title">
                    <?php printf( esc_html__( '%s, %s', 'ifsedu-sms' ), esc_html( $greeting ), esc_html( $display_name ) ); ?>
                </h2>
                <div class="dpt-hero-meta-strip">
                    <?php if ( ! empty( $designation ) ) : ?>
                        <span class="dpt-hero-meta-item">
                            <span class="dashicons dashicons-id" style="font-size: 15px; width: 15px; height: 15px;"></span>
                            <?php echo esc_html( $designation ); ?>
                        </span>
                    <?php endif; ?>

                    <?php foreach ( $extra_meta as $meta_label => $meta_val ) : ?>
                        &bull;
                        <span class="dpt-hero-meta-item">
                            <strong><?php echo esc_html( $meta_label ); ?>:</strong> <?php echo esc_html( $meta_val ); ?>
                        </span>
                    <?php endforeach; ?>

                    &bull;
                    <span class="dpt-hero-meta-item" style="background: rgba(16, 185, 129, 0.25); color: #a7f3d0; padding: 3px 10px; border-radius: 8px; font-weight: 700; font-size: 12px; border: 1px solid rgba(16,185,129,0.3);">
                        <span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> <?php esc_html_e( 'ACCOUNT ACTIVE', 'ifsedu-sms' ); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="dpt-banner-datetime-pill">
            <div class="dpt-banner-date">
                <span class="dashicons dashicons-calendar-alt" style="font-size: 13px; width: 13px; height: 13px;"></span>
                <?php echo esc_html( date_i18n( 'l, jS F Y' ) ); ?>
            </div>
            <div class="dpt-banner-clock">
                <span class="dashicons dashicons-clock" style="font-size: 16px; width: 16px; height: 16px; color: #a7f3d0;"></span>
                <span id="educoreLiveClock"><?php echo esc_html( current_time( 'H:i:s' ) ); ?></span>
            </div>
        </div>
    </div>

    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const clockEl = document.getElementById('educoreLiveClock');
        if (clockEl) {
            setInterval(function() {
                const now = new Date();
                let hours = now.getHours();
                let minutes = now.getMinutes();
                let seconds = now.getSeconds();
                hours = hours < 10 ? '0' + hours : hours;
                minutes = minutes < 10 ? '0' + minutes : minutes;
                seconds = seconds < 10 ? '0' + seconds : seconds;
                clockEl.textContent = hours + ':' + minutes + ':' + seconds;
            }, 1000);
        }
    });
    </script>
    <?php
}

// ==============================================================================
// 1. ADMIN / HEADMASTER DASHBOARD
// ==============================================================================
function educore_admin_dashboard_view( $user ) {
    global $wpdb;
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_staff      = $wpdb->prefix . 'sms_staff';
    $table_accounting = $wpdb->prefix . 'sms_accounting';
    $table_fees       = $wpdb->prefix . 'sms_fees';
    $table_exams      = $wpdb->prefix . 'sms_exams';
    $table_attendance = $wpdb->prefix . 'sms_attendance';

    // 1. Metrics Aggregation
    $total_students = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_students} WHERE status = 'Active'" );
    $male_students  = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_students} WHERE status = 'Active' AND (gender = 'Male' OR gender = 'M')" );
    $female_students = $total_students - $male_students;

    $total_teachers = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_staff} WHERE status = 'Active'" );

    $today_date = current_time( 'Y-m-d' );
    $present_today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_attendance} WHERE attendance_date = %s AND status = 'Present'", $today_date ) );
    $attendance_pct = $total_students > 0 ? round( ( $present_today / $total_students ) * 100 ) : 0;
    $absent_today = max( 0, $total_students - $present_today );

    $today_fee_collection = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(paid_amount) FROM {$table_fees} WHERE DATE(payment_date) = %s", $today_date ) ) ?: 0.00;
    $pending_receivables  = (float) $wpdb->get_var( "SELECT SUM(due_amount) FROM {$table_fees} WHERE due_amount > 0" ) ?: 0.00;

    $month_start = current_time( 'Y-m-01' );
    $month_end   = current_time( 'Y-m-t' );
    $month_collections = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(paid_amount) FROM {$table_fees} WHERE payment_date BETWEEN %s AND %s", $month_start, $month_end ) ) ?: 0.00;
    $month_expenses    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Expense' AND entry_date BETWEEN %s AND %s", $month_start, $month_end ) ) ?: 0.00;
    $net_operating_cash = $month_collections - $month_expenses;

    $exams_count = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_exams}" );
    $recent_ledger = $wpdb->get_results( "SELECT * FROM {$table_fees} ORDER BY id DESC LIMIT 5" );

    educore_dashboard_render_hero_profile( $user, __( 'System Administrator', 'ifsedu-sms' ), array(
        __( 'Students', 'ifsedu-sms' ) => $total_students,
        __( 'Faculty', 'ifsedu-sms' )  => $total_teachers
    ) );
    ?>
    <div class="dpt-dash-root">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            
            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'Total Active Students', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #0f172a; margin-top: 6px; letter-spacing: -0.5px;"><?php echo esc_html( number_format( $total_students ) ); ?></div>
                        <span style="font-size: 12px; color: #64748b; font-weight: 700; display: inline-block; margin-top: 2px;">
                            (<?php echo esc_html( $male_students ); ?> Male / <?php echo esc_html( $female_students ); ?> Female)
                        </span>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #eff6ff; color: #2563eb;">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                </div>
                <div class="dpt-stat-footer-row">
                    <span style="color: #006a4e; font-weight: 700;"><?php esc_html_e( '9 Academic Classes', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' ) ); ?>" style="color: #2563eb; font-weight: 700;"><?php esc_html_e( 'Directory &rarr;', 'ifsedu-sms' ); ?></a>
                </div>
            </div>

            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'Attendance Present Today', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #0f172a; margin-top: 6px; letter-spacing: -0.5px;">
                            <?php echo esc_html( number_format( $present_today ) ); ?> <span style="font-size: 14px; font-weight: 700; color: #059669;">(<?php echo esc_html( $attendance_pct ); ?>%)</span>
                        </div>
                        <span style="font-size: 12px; color: #64748b; font-weight: 700; display: inline-block; margin-top: 2px;">
                            <?php esc_html_e( 'Real-time classroom sync', 'ifsedu-sms' ); ?>
                        </span>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #ecfdf5; color: #059669;">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                </div>
                <div class="dpt-stat-footer-row">
                    <span style="color: #059669; font-weight: 700;"><?php esc_html_e( 'Active Logins', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=daily' ) ); ?>" style="color: #059669; font-weight: 700;"><?php esc_html_e( 'Attendance &rarr;', 'ifsedu-sms' ); ?></a>
                </div>
            </div>

            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'Absent Count Today', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #dc2626; margin-top: 6px; letter-spacing: -0.5px;">
                            <?php echo esc_html( number_format( $absent_today ) ); ?>
                        </div>
                        <span style="font-size: 12px; color: #64748b; font-weight: 700; display: inline-block; margin-top: 2px;">
                            <?php esc_html_e( 'Requires attention/followup', 'ifsedu-sms' ); ?>
                        </span>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #fef2f2; color: #dc2626;">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                </div>
                <div class="dpt-stat-footer-row">
                    <span style="background: #fef2f2; color: #dc2626; padding: 2px 8px; border-radius: 4px; font-size: 11px;"><?php esc_html_e( 'Unexcused', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=reports' ) ); ?>" style="color: #dc2626; font-weight: 700;"><?php esc_html_e( 'Logs &rarr;', 'ifsedu-sms' ); ?></a>
                </div>
            </div>

            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'Today\'s Fee Collection', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 28px; font-weight: 800; color: #006a4e; margin-top: 6px; letter-spacing: -0.5px;">৳<?php echo esc_html( number_format( $today_fee_collection, 2 ) ); ?></div>
                        <span style="font-size: 12px; color: #64748b; font-weight: 700; display: inline-block; margin-top: 2px;">
                            <?php esc_html_e( 'Daily cash & online inflows', 'ifsedu-sms' ); ?>
                        </span>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #ecfdf5; color: #006a4e;">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                </div>
                <div class="dpt-stat-footer-row">
                    <span style="color: #64748b; font-weight: 700;"><?php esc_html_e( 'Daily Inflow', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=fees&sub=collect' ) ); ?>" style="color: #006a4e; font-weight: 700;"><?php esc_html_e( 'Collect Fee &rarr;', 'ifsedu-sms' ); ?></a>
                </div>
            </div>

            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'Pending Dues (Receivables)', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 28px; font-weight: 800; color: #dc2626; margin-top: 6px; letter-spacing: -0.5px;">৳<?php echo esc_html( number_format( $pending_receivables, 2 ) ); ?></div>
                        <span style="font-size: 12px; color: #64748b; font-weight: 700; display: inline-block; margin-top: 2px;">
                            <?php esc_html_e( 'Total outstanding student dues', 'ifsedu-sms' ); ?>
                        </span>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #fef2f2; color: #dc2626;">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                </div>
                <div class="dpt-stat-footer-row">
                    <span style="color: #64748b; font-weight: 700;"><?php esc_html_e( 'Outstanding', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=fees&sub=list' ) ); ?>" style="color: #dc2626; font-weight: 700;"><?php esc_html_e( 'Audit Report &rarr;', 'ifsedu-sms' ); ?></a>
                </div>
            </div>

            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'Faculty & Staff Members', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #0f172a; margin-top: 6px; letter-spacing: -0.5px;"><?php echo esc_html( $total_teachers ); ?></div>
                        <span style="font-size: 12px; color: #64748b; font-weight: 700; display: inline-block; margin-top: 2px;">
                            <?php esc_html_e( 'Active academic personnel', 'ifsedu-sms' ); ?>
                        </span>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #ecfdf5; color: #006a4e;">
                        <span class="dashicons dashicons-businessman"></span>
                    </div>
                </div>
                <div class="dpt-stat-footer-row">
                    <span style="color: #059669; font-weight: 700;"><?php esc_html_e( 'Active Payroll', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=staff' ) ); ?>" style="color: #006a4e; font-weight: 700;"><?php esc_html_e( 'Faculty List &rarr;', 'ifsedu-sms' ); ?></a>
                </div>
            </div>
        </div>

        <div class="dpt-bento-grid-4-sub">
            <div class="dpt-sub-stat-box">
                <span class="dpt-sub-stat-label"><?php esc_html_e( 'Month Collections (Fees)', 'ifsedu-sms' ); ?></span>
                <span class="dpt-sub-stat-val" style="color: #006a4e;">৳<?php echo esc_html( number_format( $month_collections, 2 ) ); ?></span>
            </div>
            <div class="dpt-sub-stat-box">
                <span class="dpt-sub-stat-label"><?php esc_html_e( 'Month General Expenses', 'ifsedu-sms' ); ?></span>
                <span class="dpt-sub-stat-val" style="color: #dc2626;">৳<?php echo esc_html( number_format( $month_expenses, 2 ) ); ?></span>
            </div>
            <div class="dpt-sub-stat-box">
                <span class="dpt-sub-stat-label"><?php esc_html_e( 'Net Operating Cash (Total)', 'ifsedu-sms' ); ?></span>
                <span class="dpt-sub-stat-val" style="color: #2563eb;">৳<?php echo esc_html( number_format( $net_operating_cash, 2 ) ); ?></span>
            </div>
            <div class="dpt-sub-stat-box">
                <span class="dpt-sub-stat-label"><?php esc_html_e( 'Examinations Evaluated', 'ifsedu-sms' ); ?></span>
                <span class="dpt-sub-stat-val"><?php echo esc_html( $exams_count ); ?></span>
            </div>
        </div>

        <div class="dpt-split-layout">
            <div>
                <div class="dpt-panel-card">
                    <div class="dpt-panel-header" style="margin-bottom: 16px;">
                        <h3 class="dpt-panel-title">
                            <span class="dashicons dashicons-admin-generic" style="color:#006a4e;"></span>
                            <?php esc_html_e( 'Administrative Command Dock', 'ifsedu-sms' ); ?>
                        </h3>
                    </div>
                    <div class="dpt-command-dock-grid">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=add' ) ); ?>" class="dpt-command-tile">
                            <span class="dashicons dashicons-plus-alt2"></span> Admit Student
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=daily' ) ); ?>" class="dpt-command-tile">
                            <span class="dashicons dashicons-calendar-alt"></span> Attendance
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=fees&sub=collect' ) ); ?>" class="dpt-command-tile">
                            <span class="dashicons dashicons-money-alt"></span> Collect Fee
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=results&sub=marks' ) ); ?>" class="dpt-command-tile">
                            <span class="dashicons dashicons-edit"></span> Marks Matrix
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=id_card' ) ); ?>" class="dpt-command-tile">
                            <span class="dashicons dashicons-id"></span> ID Cards
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=add' ) ); ?>" class="dpt-command-tile">
                            <span class="dashicons dashicons-cart"></span> Add Expense
                        </a>
                    </div>
                </div>

                <div class="dpt-panel-card">
                    <div class="dpt-panel-header">
                        <h3 class="dpt-panel-title">
                            <span class="dashicons dashicons-book-alt" style="color:#006a4e;"></span>
                            <?php esc_html_e( 'Recent Financial Ledger Activity', 'ifsedu-sms' ); ?>
                        </h3>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=fees&sub=list' ) ); ?>" style="font-size: 12px; font-weight: 800; color: #006a4e; text-decoration: none;"><?php esc_html_e( 'VIEW ALL &rarr;', 'ifsedu-sms' ); ?></a>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="dpt-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Reference', 'ifsedu-sms' ); ?></th>
                                    <th><?php esc_html_e( 'Particulars', 'ifsedu-sms' ); ?></th>
                                    <th><?php esc_html_e( 'Method', 'ifsedu-sms' ); ?></th>
                                    <th><?php esc_html_e( 'Date', 'ifsedu-sms' ); ?></th>
                                    <th style="text-align: right;"><?php esc_html_e( 'Amount', 'ifsedu-sms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $recent_ledger ) ) : foreach ( $recent_ledger as $trx ) : ?>
                                    <tr>
                                        <td><code>#<?php echo esc_html( $trx->invoice_id ); ?></code></td>
                                        <td>
                                            <strong style="color: #0f172a; display: block;"><?php echo esc_html( $trx->fee_type ); ?></strong>
                                            <span style="font-size: 11px; color: #64748b;"><?php esc_html_e( 'Student Fee Ledger', 'ifsedu-sms' ); ?></span>
                                        </td>
                                        <td><span style="background: #f1f5f9; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; color: #475569;"><?php echo esc_html( $trx->payment_method ); ?></span></td>
                                        <td style="font-size: 12px; color: #64748b;"><?php echo esc_html( date_i18n( 'd M, Y', strtotime( $trx->payment_date ) ) ); ?></td>
                                        <td style="text-align: right;"><strong style="color: #059669;">+৳<?php echo esc_html( number_format( $trx->paid_amount, 2 ) ); ?></strong></td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 24px;"><?php esc_html_e( 'No recent financial transactions found.', 'ifsedu-sms' ); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                <div class="dpt-panel-card">
                    <div class="dpt-panel-header">
                        <h3 class="dpt-panel-title">
                            <span class="dashicons dashicons-chart-bar" style="color:#006a4e;"></span>
                            <?php esc_html_e( 'Class Attendance Overview', 'ifsedu-sms' ); ?>
                        </h3>
                        <span class="dpt-badge-pill"><?php echo esc_html( $attendance_pct ); ?>% Overall</span>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 800; color: #475569; margin-bottom: 8px;">
                            <span>PRESENT VS TOTAL ENROLLED</span>
                            <span><?php echo esc_html( $present_today ); ?> / <?php echo esc_html( $total_students ); ?></span>
                        </div>
                        <div style="height: 10px; background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                            <div style="width: <?php echo esc_html( $attendance_pct ); ?>%; height: 100%; background: #006a4e; border-radius: 10px; transition: width 0.6s ease;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ==============================================================================
// 2. TEACHER / FACULTY DASHBOARD (Fixed Notices & Term Formats)
// ==============================================================================
function educore_teacher_dashboard_view( $user ) {
    global $wpdb;
    $table_staff            = $wpdb->prefix . 'sms_staff';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_subjects         = $wpdb->prefix . 'sms_subjects';
    $table_notices          = $wpdb->prefix . 'sms_notices';

    $teacher_profile = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table_staff} WHERE wp_user_id = %d OR email = %s LIMIT 1",
        $user->ID,
        $user->user_email
    ) );

    $extra_meta = array();
    if ( $teacher_profile ) {
        if ( ! empty( $teacher_profile->designation ) ) $extra_meta[ __( 'Designation', 'ifsedu-sms' ) ] = $teacher_profile->designation;
        if ( ! empty( $teacher_profile->phone ) )        $extra_meta[ __( 'Phone', 'ifsedu-sms' ) ]       = $teacher_profile->phone;
    }

    $assigned_units_data  = array();
    $unique_classes_count = 0;
    $total_subjects_count = 0;

    if ( $teacher_profile ) {
        $raw_allocations = $wpdb->get_results( $wpdb->prepare(
            "SELECT 
                ts.id AS assign_id,
                u.id AS class_unit_id,
                u.class_name,
                u.section_name,
                s.id AS subject_id,
                s.subject_name,
                s.subject_code
               FROM {$table_teacher_subjects} ts
               INNER JOIN {$table_units} u ON ts.class_id = u.id
               INNER JOIN {$table_subjects} s ON ts.subject_id = s.id
               WHERE ts.teacher_id = %d
               ORDER BY CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC, s.subject_name ASC",
            $teacher_profile->id
        ) );

        if ( ! empty( $raw_allocations ) ) {
            $total_subjects_count = count( $raw_allocations );
            foreach ( $raw_allocations as $row ) {
                $group_key = $row->class_name . '|' . $row->section_name;
                if ( ! isset( $assigned_units_data[ $group_key ] ) ) {
                    $assigned_units_data[ $group_key ] = array(
                        'class_name'   => $row->class_name,
                        'section_name' => $row->section_name,
                        'unit_id'      => $row->class_unit_id,
                        'subjects'     => array(),
                    );
                }
                $assigned_units_data[ $group_key ]['subjects'][] = array(
                    'id'   => $row->subject_id,
                    'name' => $row->subject_name,
                    'code' => $row->subject_code,
                );
            }
            $unique_classes_count = count( $assigned_units_data );
        }
    }

    if ( $unique_classes_count > 0 ) {
        $extra_meta[ __( 'Assigned Classes', 'ifsedu-sms' ) ] = $unique_classes_count . ' Units';
    }

    $teacher_notices = $wpdb->get_results( "SELECT * FROM {$table_notices} WHERE target_audience IN ('All', 'Teachers') ORDER BY id DESC LIMIT 4" );

    educore_dashboard_render_hero_profile( $user, __( 'Faculty & Teacher Workspace', 'ifsedu-sms' ), $extra_meta );
    ?>
    <div class="dpt-dash-root">
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'Assigned Class Units', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #0f172a; margin-top: 6px;"><?php echo esc_html( $unique_classes_count ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #ecfdf5; color: #006a4e;">
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                    </div>
                </div>
            </div>

            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'Assigned Subjects', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #0f172a; margin-top: 6px;"><?php echo esc_html( $total_subjects_count ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #eff6ff; color: #2563eb;">
                        <span class="dashicons dashicons-book"></span>
                    </div>
                </div>
            </div>

            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'Academic Term', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 20px; font-weight: 800; color: #059669; margin-top: 8px;">
                            <?php echo esc_html( date_i18n( 'Y' ) . ' Session' ); ?>
                        </div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #ecfdf5; color: #059669;">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                </div>
            </div>

            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'Faculty Circulars', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #0f172a; margin-top: 6px;"><?php echo esc_html( count( $teacher_notices ) ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #fef2f2; color: #dc2626;">
                        <span class="dashicons dashicons-bell"></span>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .dpt-teacher-unit-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
            }
            .dpt-teacher-unit-card {
                background: #f8fafc;
                border: 1.5px solid #e2e8f0;
                border-radius: 16px;
                padding: 22px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                gap: 16px;
                transition: all 0.2s ease;
            }
            .dpt-teacher-unit-card:hover {
                background: #ffffff;
                border-color: #a7f3d0;
                box-shadow: 0 8px 22px rgba(0, 106, 78, 0.08);
                transform: translateY(-2px);
            }
            .dpt-subject-tag-list {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin-top: 8px;
            }
            .dpt-subject-pill {
                background: #ecfdf5;
                color: #065f46;
                border: 1px solid #a7f3d0;
                padding: 4px 10px;
                border-radius: 8px;
                font-size: 12px;
                font-weight: 700;
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
        </style>

        <div class="dpt-split-layout">
            <div>
                <div class="dpt-panel-card">
                    <div class="dpt-panel-header">
                        <h3 class="dpt-panel-title">
                            <span class="dashicons dashicons-welcome-learn-more" style="color:#006a4e;"></span>
                            <?php esc_html_e( 'Academic Setup: My Assigned Classes & Subjects', 'ifsedu-sms' ); ?>
                        </h3>
                    </div>

                    <?php if ( ! empty( $assigned_units_data ) ) : ?>
                        <div class="dpt-teacher-unit-grid">
                            <?php foreach ( $assigned_units_data as $unit ) : 
                                $att_url   = admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=daily&class_name=' . urlencode( $unit['class_name'] ) . '&section_name=' . urlencode( $unit['section_name'] ) );
                                $first_sub = ! empty( $unit['subjects'][0]['name'] ) ? $unit['subjects'][0]['name'] : '';
                                $marks_url = admin_url( 'admin.php?page=school_management_system&tab=results&sub=marks&class_name=' . urlencode( $unit['class_name'] ) . '&section_name=' . urlencode( $unit['section_name'] ) . ( $first_sub ? '&subject_name=' . urlencode( $first_sub ) : '' ) );
                                
                                // Prevent duplicated "Class Class X"
                                $display_class_name = preg_match( '/^class\s+/i', $unit['class_name'] ) ? $unit['class_name'] : 'Class ' . $unit['class_name'];
                            ?>
                                <div class="dpt-teacher-unit-card">
                                    <div>
                                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                            <div>
                                                <strong style="font-size:18px; color:#0f172a; display:block;">
                                                    <?php echo esc_html( $display_class_name ); ?>
                                                </strong>
                                                <span style="font-size:12.5px; color:#64748b; font-weight:700;">
                                                    <?php echo ! empty( $unit['section_name'] ) ? esc_html( 'Section: ' . $unit['section_name'] ) : esc_html__( 'All Sections', 'ifsedu-sms' ); ?>
                                                </span>
                                            </div>
                                            <span style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; font-size:11.5px; font-weight:800; padding:3px 10px; border-radius:12px;">
                                                <?php echo esc_html( count( $unit['subjects'] ) ); ?> <?php esc_html_e( 'Subjects', 'ifsedu-sms' ); ?>
                                            </span>
                                        </div>

                                        <div style="margin-top:14px;">
                                            <span style="font-size:11px; font-weight:800; text-transform:uppercase; color:#64748b; letter-spacing:0.3px; display:block;">
                                                <?php esc_html_e( 'Assigned Curriculum:', 'ifsedu-sms' ); ?>
                                            </span>
                                            <div class="dpt-subject-tag-list">
                                                <?php foreach ( $unit['subjects'] as $sub ) : ?>
                                                    <span class="dpt-subject-pill" title="<?php echo esc_attr( $sub['code'] ? 'Code: ' . $sub['code'] : '' ); ?>">
                                                        <span class="dashicons dashicons-book-alt" style="font-size:13px; width:13px; height:13px; vertical-align:middle;"></span>
                                                        <?php echo esc_html( $sub['name'] ); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="display:flex; gap:10px; border-top:1px solid #e2e8f0; padding-top:14px; margin-top:4px;">
                                        <a href="<?php echo esc_url( $att_url ); ?>" class="dpt-command-tile" style="padding: 10px; font-size:12px; flex:1; flex-direction:row; gap:6px;">
                                            <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Attendance', 'ifsedu-sms' ); ?>
                                        </a>
                                        <a href="<?php echo esc_url( $marks_url ); ?>" class="dpt-command-tile" style="padding: 10px; font-size:12px; flex:1; flex-direction:row; gap:6px; background:#006a4e; color:#ffffff; border-color:#006a4e;">
                                            <span class="dashicons dashicons-edit" style="color:#ffffff !important;"></span> <?php esc_html_e( 'Marks', 'ifsedu-sms' ); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:14px; padding:40px 20px; text-align:center;">
                            <span class="dashicons dashicons-info" style="font-size:36px; width:36px; height:36px; color:#94a3b8; margin-bottom:10px;"></span>
                            <p style="margin:0; font-size:14.5px; font-weight:700; color:#475569;">
                                <?php esc_html_e( 'No subjects or classes are currently assigned to your teacher account in Academic Setup.', 'ifsedu-sms' ); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="dpt-panel-card">
                    <div class="dpt-panel-header">
                        <h3 class="dpt-panel-title">
                            <span class="dashicons dashicons-bell" style="color:#006a4e;"></span>
                            <?php esc_html_e( 'Faculty Circulars & Notices', 'ifsedu-sms' ); ?>
                        </h3>
                    </div>
                    <?php if ( ! empty( $teacher_notices ) ) : ?>
                        <div class="dpt-dash-list">
                            <?php foreach ( $teacher_notices as $n ) : 
                                $n_category = ! empty( $n->notice_type ) ? $n->notice_type : ( ! empty( $n->category ) ? $n->category : 'Notice' );
                                $n_date = ! empty( $n->event_date ) && $n->event_date !== '1970-01-01' ? $n->event_date : ( ! empty( $n->publish_date ) ? $n->publish_date : $n->created_at );
                            ?>
                                <div class="dpt-list-item">
                                    <div>
                                        <strong style="color:#0f172a; font-size:14px;"><?php echo esc_html( $n->title ); ?></strong>
                                        <div style="font-size:12px; color:#64748b; margin-top:4px;">
                                            <?php echo esc_html( date_i18n( 'd M Y', strtotime( $n_date ) ) ); ?> &bull; 
                                            <span class="dpt-badge-pill"><?php echo esc_html( $n_category ); ?></span>
                                        </div>
                                    </div>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notices&type=notice&sub=view&id=' . $n->id ) ); ?>" class="dpt-command-tile" style="padding: 6px 14px; font-size: 12px; flex-direction:row;">
                                        <?php esc_html_e( 'Read', 'ifsedu-sms' ); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p style="color:#94a3b8; font-size:13.5px; margin:0;"><?php esc_html_e( 'No notices found.', 'ifsedu-sms' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ==============================================================================
// 3. ACCOUNTANT DASHBOARD
// ==============================================================================
function educore_accountant_dashboard_view( $user ) {
    global $wpdb;
    $table_accounting = $wpdb->prefix . 'sms_accounting';
    $table_staff      = $wpdb->prefix . 'sms_staff';

    $accountant_profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_staff} WHERE wp_user_id = %d OR email = %s LIMIT 1", $user->ID, $user->user_email ) );
    $extra_meta = array();
    if ( $accountant_profile && ! empty( $accountant_profile->phone ) ) {
        $extra_meta[ __( 'Desk Phone', 'ifsedu-sms' ) ] = $accountant_profile->phone;
    }

    $today_income = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Income' AND entry_date = %s", current_time('Y-m-d') ) ) ?: 0.00;
    $month_income = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Income' AND entry_date BETWEEN %s AND %s", current_time('Y-m-01'), current_time('Y-m-t') ) ) ?: 0.00;
    $month_exp    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Expense' AND entry_date BETWEEN %s AND %s", current_time('Y-m-01'), current_time('Y-m-t') ) ) ?: 0.00;
    $recent_trans = $wpdb->get_results( "SELECT * FROM {$table_accounting} ORDER BY entry_date DESC, id DESC LIMIT 6" );

    educore_dashboard_render_hero_profile( $user, __( 'Accounts & Financial Officer', 'ifsedu-sms' ), $extra_meta );
    ?>
    <div class="dpt-dash-root">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'Today Collected', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #059669; margin-top: 6px;">৳<?php echo esc_html( number_format($today_income, 2) ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #ecfdf5; color: #059669;">
                        <span class="dashicons dashicons-money"></span>
                    </div>
                </div>
            </div>

            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'Month Collections', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #2563eb; margin-top: 6px;">৳<?php echo esc_html( number_format($month_income, 2) ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #eff6ff; color: #2563eb;">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                </div>
            </div>

            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'Month Expenses', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #dc2626; margin-top: 6px;">৳<?php echo esc_html( number_format($month_exp, 2) ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #fef2f2; color: #dc2626;">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="dpt-panel-card">
            <div class="dpt-panel-header">
                <h3 class="dpt-panel-title">
                    <span class="dashicons dashicons-list-view" style="color:#006a4e;"></span>
                    <?php esc_html_e( 'Recent Accounting Ledger Records', 'ifsedu-sms' ); ?>
                </h3>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=add' ) ); ?>" class="dpt-command-tile" style="padding: 6px 14px; font-size: 12px; flex-direction:row;">
                    + Record Voucher
                </a>
            </div>
            <div style="overflow-x:auto;">
                <table class="dpt-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Voucher No', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Title', 'ifsedu-sms' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'ifsedu-sms' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Amount', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $recent_trans ) ) : foreach ($recent_trans as $t) : ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n('d M Y', strtotime($t->entry_date)) ); ?></td>
                                <td><code><?php echo esc_html($t->voucher_no); ?></code></td>
                                <td><strong><?php echo esc_html($t->title); ?></strong></td>
                                <td><span class="dpt-badge-pill" style="background: <?php echo $t->entry_type === 'Income' ? '#ecfdf5; color: #059669;' : '#fef2f2; color: #dc2626;'; ?>"><?php echo esc_html($t->entry_type); ?></span></td>
                                <td style="text-align: right;"><strong style="color: <?php echo $t->entry_type === 'Income' ? '#059669' : '#dc2626'; ?>;">৳<?php echo esc_html( number_format($t->amount, 2) ); ?></strong></td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:24px;"><?php esc_html_e( 'No transactions recorded yet.', 'ifsedu-sms' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

// ==============================================================================
// 4. STUDENT / GUARDIAN PORTAL DASHBOARD
// ==============================================================================
function educore_student_guardian_dashboard_view( $user ) {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_notices  = $wpdb->prefix . 'sms_notices';

    $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_students} WHERE student_email = %s OR full_name = %s LIMIT 1", $user->user_email, $user->display_name ) );
    $student_notices = $wpdb->get_results( "SELECT * FROM {$table_notices} WHERE target_audience IN ('All', 'Students') ORDER BY event_date DESC, id DESC LIMIT 4" );

    $extra_meta = array();
    if ( $student ) {
        $extra_meta[ __( 'Class', 'ifsedu-sms' ) ]   = $student->class_name;
        $extra_meta[ __( 'Section', 'ifsedu-sms' ) ] = $student->section_name ?: 'A';
        $extra_meta[ __( 'Roll', 'ifsedu-sms' ) ]    = '#' . $student->roll_no;
        $extra_meta[ __( 'ID', 'ifsedu-sms' ) ]      = $student->student_id;
    }

    educore_dashboard_render_hero_profile( $user, __( 'Student & Parent Academic Portal', 'ifsedu-sms' ), $extra_meta );
    ?>
    <div class="dpt-dash-root">
        <div class="dpt-split-layout">
            <div>
                <div class="dpt-panel-card">
                    <div class="dpt-panel-header">
                        <h3 class="dpt-panel-title">
                            <span class="dashicons dashicons-bell" style="color:#006a4e;"></span>
                            <?php esc_html_e( 'School Notices & Announcements', 'ifsedu-sms' ); ?>
                        </h3>
                    </div>
                    <?php if ( ! empty( $student_notices ) ) : ?>
                        <div class="dpt-dash-list">
                            <?php foreach ( $student_notices as $n ) : ?>
                                <div class="dpt-list-item">
                                    <div>
                                        <strong style="color:#0f172a; font-size:14px;"><?php echo esc_html( $n->title ); ?></strong>
                                        <div style="font-size:12px; color:#64748b; margin-top:4px;">
                                            <?php echo esc_html( date_i18n('d M Y', strtotime($n->event_date ?: $n->publish_date)) ); ?></div>
                                    </div>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notices&type=notice&sub=view&id=' . $n->id ) ); ?>" class="dpt-command-tile" style="padding: 6px 14px; font-size: 12px; flex-direction:row;">
                                        Read
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p style="color:#94a3b8; font-size:13.5px; margin:0;"><?php esc_html_e( 'No active notices.', 'ifsedu-sms' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="dpt-panel-card">
                    <div class="dpt-panel-header">
                        <h3 class="dpt-panel-title">
                            <span class="dashicons dashicons-id-alt" style="color:#006a4e;"></span>
                            <?php esc_html_e( 'Student Documents & Cards', 'ifsedu-sms' ); ?>
                        </h3>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <a href="<?php echo esc_url( admin_url('admin.php?page=school_management_system&tab=students&sub=id_card') ); ?>" class="dpt-command-tile" style="flex-direction: row; justify-content: flex-start; padding: 14px 18px; gap: 12px;">
                            <span class="dashicons dashicons-id" style="font-size:22px;"></span> 
                            <div style="text-align: left;">
                                <strong style="display:block; font-size:13.5px; color:#0f172a;"><?php esc_html_e( 'Digital ID Card', 'ifsedu-sms' ); ?></strong>
                                <span style="font-size:11.5px; color:#64748b; font-weight:normal;"><?php esc_html_e( 'Generate & print official school ID', 'ifsedu-sms' ); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url( admin_url('admin.php?page=school_management_system&tab=students&sub=admit_card') ); ?>" class="dpt-command-tile" style="flex-direction: row; justify-content: flex-start; padding: 14px 18px; gap: 12px;">
                            <span class="dashicons dashicons-tickets-alt" style="font-size:22px;"></span> 
                            <div style="text-align: left;">
                                <strong style="display:block; font-size:13.5px; color:#0f172a;"><?php esc_html_e( 'Admit Card', 'ifsedu-sms' ); ?></strong>
                                <span style="font-size:11.5px; color:#64748b; font-weight:normal;"><?php esc_html_e( 'Examination entry pass card', 'ifsedu-sms' ); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url( admin_url('admin.php?page=school_management_system&tab=results&sub=report') ); ?>" class="dpt-command-tile" style="flex-direction: row; justify-content: flex-start; padding: 14px 18px; gap: 12px;">
                            <span class="dashicons dashicons-media-document" style="font-size:22px;"></span> 
                            <div style="text-align: left;">
                                <strong style="display:block; font-size:13.5px; color:#0f172a;"><?php esc_html_e( 'Academic Transcript', 'ifsedu-sms' ); ?></strong>
                                <span style="font-size:11.5px; color:#64748b; font-weight:normal;"><?php esc_html_e( 'Term marksheets & GPA records', 'ifsedu-sms' ); ?></span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}