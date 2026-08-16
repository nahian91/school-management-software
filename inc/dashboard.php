<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety
}

/**
 * Enterprise Multi-Role Dashboard Dispatcher & Neo-Bento Matrix
 * File: inc/dashboard.php
 * Palette: Institutional Green Accent (#006a4e) with User Profile Header
 * Note: Administrator Dashboard includes the top Welcome Banner with live time and system status.
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
    $avatar_url = get_avatar_url( $user->ID, array( 'size' => 128 ) );
    ?>
    <style>
        .dpt-dash-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            margin: 20px 20px 30px 0;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Unified Institutional Green Hero Profile Banner */
        .dpt-dash-hero-green {
            background: linear-gradient(135deg, #002e20 0%, #004d38 60%, #006a4e 100%);
            border-radius: 16px;
            padding: 28px 32px;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 106, 78, 0.25);
            position: relative;
            overflow: hidden;
        }

        .dpt-dash-hero-green::after {
            content: '';
            position: absolute;
            right: -40px;
            top: -40px;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .dpt-profile-flex {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .dpt-profile-avatar {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            object-fit: cover;
            background: #ffffff;
            flex-shrink: 0;
        }

        .dpt-hero-badge {
            background: rgba(255, 255, 255, 0.18);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 4px;
        }

        .dpt-hero-title {
            margin: 0 0 4px 0;
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.4px;
        }

        .dpt-hero-meta-strip {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 13px;
            opacity: 0.92;
        }

        .dpt-hero-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .dpt-hero-meta-item strong {
            color: #ffffff;
        }

        /* Top Banner Right Date & Live Clock Pill */
        .dpt-banner-datetime-pill {
            background: rgba(0, 0, 0, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 12px 18px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            text-align: right;
            backdrop-filter: blur(4px);
        }

        .dpt-banner-date {
            font-size: 12px;
            font-weight: 600;
            color: #a7f3d0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 5px;
        }

        .dpt-banner-clock {
            font-size: 18px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .dpt-hero-action-btn {
            height: 42px;
            line-height: 42px;
            padding: 0 20px;
            background: #ffffff;
            color: #006a4e !important;
            border-radius: 10px;
            font-weight: 800;
            font-size: 13.5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .dpt-hero-action-btn:hover {
            background: #f0fdf4;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.18);
        }

        /* 3-Bento Grid Metric Matrix (Top Row) */
        .dpt-bento-grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
        }

        /* 4-Bento Grid Metric Matrix (Second Row) */
        .dpt-bento-grid-4-sub {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 20px;
            box-shadow: 0 4px 15px -3px rgba(0,0,0,0.02);
        }

        @media (max-width: 900px) {
            .dpt-bento-grid-4-sub { grid-template-columns: repeat(2, 1fr); }
        }

        .dpt-sub-stat-box {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .dpt-sub-stat-box:not(:last-child) {
            border-right: 1px solid #f1f5f9;
            padding-right: 12px;
        }

        .dpt-sub-stat-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.3px;
        }

        .dpt-sub-stat-val {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
        }

        .dpt-stat-bento {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dpt-stat-bento:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -4px rgba(0, 0, 0, 0.06);
        }

        .dpt-stat-top-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .dpt-stat-icon-badge {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .dpt-stat-footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .dpt-stat-footer-row a {
            text-decoration: none;
            transition: opacity 0.2s ease;
        }
        .dpt-stat-footer-row a:hover {
            opacity: 0.75;
        }

        /* Split Screen Content Matrix */
        .dpt-split-layout {
            display: grid;
            grid-template-columns: 1.8fr 1.2fr;
            gap: 20px;
        }

        @media (max-width: 1024px) {
            .dpt-split-layout { grid-template-columns: 1fr; }
        }

        .dpt-panel-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px 26px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 20px;
        }

        .dpt-panel-header {
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dpt-panel-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Command Dock Grid (6 Actions) */
        .dpt-command-dock-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {
            .dpt-command-dock-grid { grid-template-columns: repeat(2, 1fr); }
        }

        .dpt-command-tile {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 12px;
            text-decoration: none;
            color: #334155;
            font-weight: 700;
            font-size: 12.5px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .dpt-command-tile .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
            color: #006a4e;
        }

        .dpt-command-tile:hover {
            background: #006a4e;
            color: #ffffff;
            border-color: #006a4e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .dpt-command-tile:hover .dashicons {
            color: #ffffff !important;
        }

        .dpt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .dpt-table th, .dpt-table td {
            border-bottom: 1px solid #f1f5f9;
            padding: 10px 12px;
            text-align: left;
            vertical-align: middle;
        }

        .dpt-table th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* Teacher Academic Setup Bento Unit Cards */
        .dpt-teacher-unit-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }

        .dpt-teacher-unit-card {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            transition: all 0.2s ease;
        }

        .dpt-teacher-unit-card:hover {
            background: #ffffff;
            border-color: #a7f3d0;
            box-shadow: 0 6px 18px rgba(0, 106, 78, 0.08);
            transform: translateY(-2px);
        }

        .dpt-subject-tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }

        .dpt-subject-pill {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
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
            padding: 14px 18px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            transition: background 0.15s ease;
        }

        .dpt-list-item:hover {
            background: #ffffff;
            border-color: #cbd5e1;
        }

        .dpt-badge-pill {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            display: inline-block;
        }
    </style>
    <?php
}

// ==============================================================================
// 1. ADMIN / HEADMASTER DASHBOARD (Matches Reference Layout & Metrics)
// ==============================================================================
function educore_admin_dashboard_view( $user ) {
    global $wpdb;
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_teachers   = $wpdb->prefix . 'sms_teachers';
    $table_staff      = $wpdb->prefix . 'sms_staff';
    $table_accounting = $wpdb->prefix . 'sms_accounting';
    $table_fees       = $wpdb->prefix . 'sms_fees';
    $table_exams      = $wpdb->prefix . 'sms_exams';
    $table_attendance = $wpdb->prefix . 'sms_attendance';

    // 1. Metrics Aggregation
    $total_students = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_students} WHERE status = 'Active'" );
    $male_students  = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_students} WHERE status = 'Active' AND (gender = 'Male' OR gender = 'M')" );
    $female_students = $total_students - $male_students;

    // Total Teachers (Checks sms_teachers or sms_staff)
    $total_teachers = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_teachers} WHERE status = 'Active'" );
    if ( $total_teachers === 0 && $wpdb->get_var( "SHOW TABLES LIKE '{$table_staff}'" ) ) {
        $total_teachers = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_staff} WHERE status = 'Active'" );
    }

    // Attendance Today
    $today_date = current_time( 'Y-m-d' );
    $present_today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_attendance} WHERE attendance_date = %s AND status = 'Present'", $today_date ) );
    $attendance_pct = $total_students > 0 ? round( ( $present_today / $total_students ) * 100 ) : 0;

    // Fees Today & Receivables
    $today_fee_collection = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(paid_amount) FROM {$table_fees} WHERE DATE(payment_date) = %s", $today_date ) ) ?: 0.00;
    $pending_receivables  = (float) $wpdb->get_var( "SELECT SUM(due_amount) FROM {$table_fees} WHERE due_amount > 0" ) ?: 0.00;

    // Month Collections & Expenses
    $month_start = current_time( 'Y-m-01' );
    $month_end   = current_time( 'Y-m-t' );
    $month_collections = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(paid_amount) FROM {$table_fees} WHERE payment_date BETWEEN %s AND %s", $month_start, $month_end ) ) ?: 0.00;
    $month_expenses    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Expense' AND entry_date BETWEEN %s AND %s", $month_start, $month_end ) ) ?: 0.00;
    $net_operating_cash = $month_collections - $month_expenses;

    // Exams Evaluated Count
    $exams_count = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_exams}" );

    // Recent Financial Ledger Activity
    $recent_ledger = $wpdb->get_results( "SELECT * FROM {$table_fees} ORDER BY id DESC LIMIT 6" );
    
    // Greeting time calculation
    $current_hour = (int) current_time( 'G' );
    $greeting = __( 'Good Morning', 'ifsedu-sms' );
    if ( $current_hour >= 12 && $current_hour < 17 ) {
        $greeting = __( 'Good Afternoon', 'ifsedu-sms' );
    } elseif ( $current_hour >= 17 ) {
        $greeting = __( 'Good Evening', 'ifsedu-sms' );
    }

    $avatar_url = get_avatar_url( $user->ID, array( 'size' => 128 ) );
    ?>
    <style>
        .dpt-dash-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            margin: 20px 20px 30px 0;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .dpt-dash-hero-green {
            background: linear-gradient(135deg, #003828 0%, #006a4e 100%);
            border-radius: 16px;
            padding: 26px 32px;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 106, 78, 0.25);
            position: relative;
            overflow: hidden;
        }

        .dpt-dash-hero-green::after {
            content: '';
            position: absolute;
            right: -40px;
            top: -40px;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .dpt-profile-flex {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .dpt-profile-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            object-fit: cover;
            background: #ffffff;
            flex-shrink: 0;
        }

        .dpt-hero-badge {
            background: rgba(255, 255, 255, 0.18);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 4px;
        }

        .dpt-hero-title {
            margin: 0 0 4px 0;
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.4px;
        }

        .dpt-hero-meta-strip {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 13px;
            opacity: 0.92;
        }

        .dpt-hero-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .dpt-banner-datetime-pill {
            background: rgba(0, 0, 0, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 12px 18px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            text-align: right;
            backdrop-filter: blur(4px);
        }

        .dpt-banner-date {
            font-size: 12px;
            font-weight: 600;
            color: #a7f3d0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 5px;
        }

        .dpt-banner-clock {
            font-size: 18px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .dpt-bento-grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
        }

        .dpt-bento-grid-4-sub {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 20px;
            box-shadow: 0 4px 15px -3px rgba(0,0,0,0.02);
        }

        @media (max-width: 900px) {
            .dpt-bento-grid-4-sub { grid-template-columns: repeat(2, 1fr); }
        }

        .dpt-sub-stat-box {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .dpt-sub-stat-box:not(:last-child) {
            border-right: 1px solid #f1f5f9;
            padding-right: 12px;
        }

        .dpt-sub-stat-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.3px;
        }

        .dpt-sub-stat-val {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
        }

        .dpt-stat-bento {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dpt-stat-bento:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -4px rgba(0, 0, 0, 0.06);
        }

        .dpt-stat-top-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .dpt-stat-icon-badge {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .dpt-stat-footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .dpt-stat-footer-row a {
            text-decoration: none;
            transition: opacity 0.2s ease;
        }
        .dpt-stat-footer-row a:hover {
            opacity: 0.75;
        }

        .dpt-split-layout {
            display: grid;
            grid-template-columns: 1.8fr 1.2fr;
            gap: 20px;
        }

        @media (max-width: 1024px) {
            .dpt-split-layout { grid-template-columns: 1fr; }
        }

        .dpt-panel-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px 26px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 20px;
        }

        .dpt-panel-header {
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dpt-panel-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dpt-command-dock-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {
            .dpt-command-dock-grid { grid-template-columns: repeat(2, 1fr); }
        }

        .dpt-command-tile {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 12px;
            text-decoration: none;
            color: #334155;
            font-weight: 700;
            font-size: 12.5px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .dpt-command-tile .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
            color: #006a4e;
        }

        .dpt-command-tile:hover {
            background: #006a4e;
            color: #ffffff;
            border-color: #006a4e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .dpt-command-tile:hover .dashicons {
            color: #ffffff !important;
        }

        .dpt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .dpt-table th, .dpt-table td {
            border-bottom: 1px solid #f1f5f9;
            padding: 10px 12px;
            text-align: left;
            vertical-align: middle;
        }

        .dpt-table th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
    </style>

    <!-- Top Welcome Banner -->
    <div class="dpt-dash-hero-green">
        <div class="dpt-profile-flex">
            <img src="<?php echo esc_url( $avatar_url ); ?>" alt="User Avatar" class="dpt-profile-avatar">
            <div>
                <span class="dpt-hero-badge"><?php echo esc_html( ucfirst( $user->roles[0] ?? 'Administrator' ) ); ?></span>
                <h2 class="dpt-hero-title">
                    <?php printf( esc_html__( '%s, %s', 'ifsedu-sms' ), esc_html( $greeting ), esc_html( $user->display_name ) ); ?>
                </h2>
                <div class="dpt-hero-meta-strip">
                    <span class="dpt-hero-meta-item">
                        <?php printf( esc_html__( 'Academic Operations Engine &mdash; 9 Classes, %d Students', 'ifsedu-sms' ), intval( $total_students ) ); ?>
                    </span>
                    &bull;
                    <span class="dpt-hero-meta-item" style="background: rgba(16, 185, 129, 0.25); color: #a7f3d0; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 11.5px;">
                        <span class="dashicons dashicons-yes-alt" style="font-size: 13px; width: 13px; height: 13px; vertical-align: middle;"></span> <?php esc_html_e( 'SYSTEM ACTIVE', 'ifsedu-sms' ); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Live Clock & Date Pill -->
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

    <div class="dpt-dash-root" style="margin-top: 0;">
        <!-- 6 Bento Cards Grid (Matching Reference Screenshot) -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
            
            <!-- Card 1: Total Active Students -->
            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'TOTAL ACTIVE STUDENTS', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 4px;"><?php echo esc_html( number_format( $total_students ) ); ?></div>
                        <span style="font-size: 11.5px; color: #64748b; font-weight: 600;">(<?php echo esc_html( $male_students ); ?>M / <?php echo esc_html( $female_students ); ?>F)</span>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #eff6ff; color: #2563eb;">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                </div>
                <div class="dpt-stat-footer-row">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=academic_setup' ) ); ?>" style="color: #006a4e; font-weight: 700;"><?php esc_html_e( '9 Academic Classes', 'ifsedu-sms' ); ?></a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' ) ); ?>" style="color: #2563eb; font-weight: 700;"><?php esc_html_e( 'Students Directory &rarr;', 'ifsedu-sms' ); ?></a>
                </div>
            </div>

            <!-- Card 2: Attendance Present Today -->
            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'ATTENDANCE PRESENT TODAY', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                            <?php echo esc_html( number_format( $present_today ) ); ?> <span style="font-size: 14px; font-weight: 600; color: #64748b;">(<?php echo esc_html( $attendance_pct ); ?>%)</span>
                        </div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #ecfdf5; color: #059669;">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                </div>
                <div class="dpt-stat-footer-row">
                    <span style="color: #059669; font-weight: 700;"><?php esc_html_e( 'In Classrooms', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=daily' ) ); ?>" style="color: #059669; font-weight: 700;"><?php esc_html_e( 'Take Attendance &rarr;', 'ifsedu-sms' ); ?></a>
                </div>
            </div>

            <!-- Card 3: Absent Count Today -->
            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'ABSENT COUNT TODAY', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                            <?php 
                                $absent_today = max( 0, $total_students - $present_today );
                                echo esc_html( number_format( $absent_today ) ); 
                            ?> 
                            <span style="font-size: 12px; font-weight: 600; color: #64748b;"><?php esc_html_e( 'of 0 logged', 'ifsedu-sms' ); ?></span>
                        </div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #fef2f2; color: #dc2626;">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                </div>
                <div class="dpt-stat-footer-row">
                    <span style="background: #fef2f2; color: #dc2626; padding: 2px 8px; border-radius: 4px; font-size: 11px;"><?php esc_html_e( 'Unexcused', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=reports' ) ); ?>" style="color: #dc2626; font-weight: 700;"><?php esc_html_e( 'Attendance Logs &rarr;', 'ifsedu-sms' ); ?></a>
                </div>
            </div>

            <!-- Card 4: Today's Fee Collection -->
            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'TODAY\'S FEE COLLECTION', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 24px; font-weight: 800; color: #006a4e; margin-top: 4px;">৳<?php echo esc_html( number_format( $today_fee_collection, 2 ) ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #ecfdf5; color: #006a4e;">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                </div>
                <div class="dpt-stat-footer-row">
                    <span style="color: #64748b; font-weight: 600;"><?php esc_html_e( 'Daily Inflow', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=fees&sub=collect' ) ); ?>" style="color: #006a4e; font-weight: 700;"><?php esc_html_e( 'Collect Fee &rarr;', 'ifsedu-sms' ); ?></a>
                </div>
            </div>

            <!-- Card 5: Pending Dues (Receivables) -->
            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'PENDING DUES (RECEIVABLES)', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 24px; font-weight: 800; color: #dc2626; margin-top: 4px;">৳<?php echo esc_html( number_format( $pending_receivables, 2 ) ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #fef2f2; color: #dc2626;">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                </div>
                <div class="dpt-stat-footer-row">
                    <span style="color: #64748b; font-weight: 600;"><?php esc_html_e( 'Outstanding', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=fees&sub=list' ) ); ?>" style="color: #dc2626; font-weight: 700;"><?php esc_html_e( 'Audit Report &rarr;', 'ifsedu-sms' ); ?></a>
                </div>
            </div>

            <!-- Card 6: Faculty & Staff Members -->
            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'FACULTY & STAFF MEMBERS', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                            <?php echo esc_html( $total_teachers ); ?> <span style="font-size: 13px; font-weight: 600; color: #64748b;">(<?php echo esc_html( $total_teachers ); ?> Teachers)</span>
                        </div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #ecfdf5; color: #006a4e;">
                        <span class="dashicons dashicons-businessman"></span>
                    </div>
                </div>
                <div class="dpt-stat-footer-row">
                    <span style="color: #059669; font-weight: 700;"><?php esc_html_e( 'Active Personnel', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=teachers_staff' ) ); ?>" style="color: #006a4e; font-weight: 700;"><?php esc_html_e( 'Faculty List &rarr;', 'ifsedu-sms' ); ?></a>
                </div>
            </div>

        </div>

        <!-- Sub-Metrics Strip -->
        <div class="dpt-bento-grid-4-sub">
            <div class="dpt-sub-stat-box">
                <span class="dpt-sub-stat-label"><?php esc_html_e( 'MONTH COLLECTIONS (FEES)', 'ifsedu-sms' ); ?></span>
                <span class="dpt-sub-stat-val" style="color: #006a4e;">৳<?php echo esc_html( number_format( $month_collections, 2 ) ); ?></span>
            </div>
            <div class="dpt-sub-stat-box">
                <span class="dpt-sub-stat-label"><?php esc_html_e( 'MONTH GENERAL EXPENSES', 'ifsedu-sms' ); ?></span>
                <span class="dpt-sub-stat-val" style="color: #dc2626;">৳<?php echo esc_html( number_format( $month_expenses, 2 ) ); ?></span>
            </div>
            <div class="dpt-sub-stat-box">
                <span class="dpt-sub-stat-label"><?php esc_html_e( 'NET OPERATING CASH (TOTAL)', 'ifsedu-sms' ); ?></span>
                <span class="dpt-sub-stat-val" style="color: #2563eb;">৳<?php echo esc_html( number_format( $net_operating_cash, 2 ) ); ?></span>
            </div>
            <div class="dpt-sub-stat-box">
                <span class="dpt-sub-stat-label"><?php esc_html_e( 'EXAMINATIONS EVALUATED', 'ifsedu-sms' ); ?></span>
                <span class="dpt-sub-stat-val"><?php echo esc_html( $exams_count ); ?> <span style="font-size: 12px; font-weight: 600; color: #64748b;"><?php esc_html_e( 'Exams Configured', 'ifsedu-sms' ); ?></span></span>
            </div>
        </div>

        <!-- Split Layout (Left: Command Dock & Ledger | Right: Attendance Breakdown & Schedule) -->
        <div class="dpt-split-layout">
            
            <!-- Left Side -->
            <div>
                <!-- Administrative Command Dock -->
                <div class="dpt-panel-card">
                    <h3 class="dpt-panel-title" style="margin-bottom: 16px;">
                        <span class="dashicons dashicons-admin-generic" style="color:#006a4e;"></span>
                        <?php esc_html_e( 'Administrative Command Dock', 'ifsedu-sms' ); ?>
                    </h3>
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

                <!-- Recent Financial Ledger Activity -->
                <div class="dpt-panel-card">
                    <div class="dpt-panel-header" style="margin-bottom: 12px; padding-bottom: 10px;">
                        <h3 class="dpt-panel-title">
                            <?php esc_html_e( 'RECENT FINANCIAL LEDGER ACTIVITY', 'ifsedu-sms' ); ?>
                        </h3>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=fees&sub=list' ) ); ?>" style="font-size: 12px; font-weight: 700; color: #006a4e; text-decoration: none;"><?php esc_html_e( 'VIEW ALL &rarr;', 'ifsedu-sms' ); ?></a>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="dpt-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'REFERENCE', 'ifsedu-sms' ); ?></th>
                                    <th><?php esc_html_e( 'PARTICULARS', 'ifsedu-sms' ); ?></th>
                                    <th><?php esc_html_e( 'METHOD', 'ifsedu-sms' ); ?></th>
                                    <th><?php esc_html_e( 'DATE', 'ifsedu-sms' ); ?></th>
                                    <th style="text-align: right;"><?php esc_html_e( 'AMOUNT', 'ifsedu-sms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $recent_ledger ) ) : foreach ( $recent_ledger as $trx ) : ?>
                                    <tr>
                                        <td><code>#<?php echo esc_html( $trx->invoice_id ); ?></code></td>
                                        <td>
                                            <strong style="color: #0f172a; display: block;"><?php echo esc_html( $trx->fee_type ); ?></strong>
                                            <span style="font-size: 11px; color: #64748b;"><?php esc_html_e( 'Student Fee', 'ifsedu-sms' ); ?></span>
                                        </td>
                                        <td><span style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700;"><?php echo esc_html( $trx->payment_method ); ?></span></td>
                                        <td style="font-size: 12px; color: #64748b;"><?php echo esc_html( date_i18n( 'd M, Y', strtotime( $trx->payment_date ) ) ); ?></td>
                                        <td style="text-align: right;"><strong style="color: #059669;">+৳<?php echo esc_html( number_format( $trx->paid_amount, 2 ) ); ?></strong></td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;"><?php esc_html_e( 'No recent financial transactions found.', 'ifsedu-sms' ); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Side -->
            <div>
                <!-- Class Attendance Breakdown -->
                <div class="dpt-panel-card">
                    <div class="dpt-panel-header" style="margin-bottom: 14px; padding-bottom: 10px;">
                        <h3 class="dpt-panel-title">
                            <span class="dashicons dashicons-chart-bar" style="color:#006a4e;"></span>
                            <?php esc_html_e( 'Class Attendance Breakdown', 'ifsedu-sms' ); ?>
                        </h3>
                        <span style="font-size: 11.5px; font-weight: 800; color: #059669; background: #ecfdf5; padding: 2px 8px; border-radius: 4px;"><?php echo esc_html( $attendance_pct ); ?>% Overall</span>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            <span>PRESENT VS LOGGED TODAY</span>
                            <span><?php echo esc_html( $present_today ); ?> / <?php echo esc_html( $total_students ); ?></span>
                        </div>
                        <div style="height: 8px; background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                            <div style="width: <?php echo esc_html( $attendance_pct ); ?>%; height: 100%; background: #006a4e; border-radius: 10px;"></div>
                        </div>
                    </div>

                    <table class="dpt-table" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th style="text-align: center;">Present</th>
                                <th style="text-align: center;">Absent</th>
                                <th style="text-align: right;">Ratio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #94a3b8; padding: 24px;">
                                    <?php esc_html_e( 'No attendance logged for any class today.', 'ifsedu-sms' ); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Routine & Schedule -->
                <div class="dpt-panel-card">
                    <div class="dpt-panel-header" style="margin-bottom: 12px; padding-bottom: 10px;">
                        <h3 class="dpt-panel-title" style="font-size: 13px; text-transform: uppercase;">
                            <?php esc_html_e( 'Routine & Schedule', 'ifsedu-sms' ); ?>
                        </h3>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=academic_setup' ) ); ?>" style="font-size: 11.5px; font-weight: 700; color: #006a4e; text-decoration: none;"><?php esc_html_e( 'Routine &rarr;', 'ifsedu-sms' ); ?></a>
                    </div>
                    <div style="background: #f0fdf4; border: 1px solid #a7f3d0; border-radius: 10px; padding: 14px; color: #065f46; font-weight: 700; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e( 'Class Timetable Active', 'ifsedu-sms' ); ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Live JS Clock Script -->
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
// 2. TEACHER / FACULTY DASHBOARD (Dynamic Academic Setup Allocation Sync)
// ==============================================================================
function educore_teacher_dashboard_view( $user ) {
    global $wpdb;
    $table_staff            = $wpdb->prefix . 'sms_staff';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_subjects         = $wpdb->prefix . 'sms_subjects';
    $table_notices          = $wpdb->prefix . 'sms_notices';

    // 1. Fetch Teacher Profile from Staff table
    $teacher_profile = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table_staff} WHERE email = %s OR full_name = %s LIMIT 1",
        $user->user_email,
        $user->display_name
    ) );

    $extra_meta = array();
    if ( $teacher_profile ) {
        if ( ! empty( $teacher_profile->designation ) ) $extra_meta[ __( 'Designation', 'ifsedu-sms' ) ] = $teacher_profile->designation;
        if ( ! empty( $teacher_profile->staff_type ) )  $extra_meta[ __( 'Staff Type', 'ifsedu-sms' ) ]  = $teacher_profile->staff_type;
    }

    // 2. Query Exact Assigned Classes, Sections & Subjects from Academic Setup (sms_teacher_subjects)
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

        // Group allocations by Class + Section
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

    // Greeting time calculation
    $current_hour = (int) current_time( 'G' );
    $greeting = __( 'Good Morning', 'ifsedu-sms' );
    if ( $current_hour >= 12 && $current_hour < 17 ) {
        $greeting = __( 'Good Afternoon', 'ifsedu-sms' );
    } elseif ( $current_hour >= 17 ) {
        $greeting = __( 'Good Evening', 'ifsedu-sms' );
    }

    $teacher_notices = $wpdb->get_results( "SELECT * FROM {$table_notices} WHERE target_audience IN ('All', 'Teachers') ORDER BY publish_date DESC LIMIT 4" );

    educore_dashboard_render_hero_profile( 
        $user, 
        __( 'Teacher & Faculty Workspace', 'ifsedu-sms' ),
        $extra_meta,
        array(
            'url'   => admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=daily' ),
            'icon'  => 'dashicons-yes',
            'label' => __( 'Mark Today\'s Attendance', 'ifsedu-sms' )
        )
    ); 
    ?>
    <div class="dpt-dash-root">
        <!-- 4-Bento Summary Cards for Teacher -->
        <div class="dpt-bento-grid-4">
            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'ASSIGNED CLASS UNITS', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 4px;"><?php echo esc_html( $unique_classes_count ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #ecfdf5; color: #006a4e;">
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                    </div>
                </div>
            </div>

            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'ASSIGNED SUBJECTS', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 4px;"><?php echo esc_html( $total_subjects_count ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #eff6ff; color: #2563eb;">
                        <span class="dashicons dashicons-book"></span>
                    </div>
                </div>
            </div>

            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'TODAY\'S DATE', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 18px; font-weight: 800; color: #059669; margin-top: 8px;"><?php echo esc_html( date_i18n( 'd M, Y' ) ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #ecfdf5; color: #059669;">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                </div>
            </div>

            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'FACULTY CIRCULARS', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 4px;"><?php echo esc_html( count( $teacher_notices ) ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #fef2f2; color: #dc2626;">
                        <span class="dashicons dashicons-bell"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="dpt-split-layout">
            <!-- Left: Dynamic Assigned Classes & Subjects Matrix -->
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
                            ?>
                                <div class="dpt-teacher-unit-card">
                                    <div>
                                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                            <div>
                                                <strong style="font-size:18px; color:#0f172a; display:block;">
                                                    <?php printf( esc_html__( 'Class %s', 'ifsedu-sms' ), esc_html( $unit['class_name'] ) ); ?>
                                                </strong>
                                                <span style="font-size:12px; color:#64748b; font-weight:700;">
                                                    <?php echo ! empty( $unit['section_name'] ) ? esc_html( 'Section: ' . $unit['section_name'] ) : esc_html__( 'All Sections', 'ifsedu-sms' ); ?>
                                                </span>
                                            </div>
                                            <span style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; font-size:11px; font-weight:800; padding:2px 8px; border-radius:12px;">
                                                <?php echo esc_html( count( $unit['subjects'] ) ); ?> <?php esc_html_e( 'Sub', 'ifsedu-sms' ); ?>
                                            </span>
                                        </div>

                                        <div style="margin-top:12px;">
                                            <span style="font-size:11px; font-weight:800; text-transform:uppercase; color:#64748b; letter-spacing:0.3px; display:block;">
                                                <?php esc_html_e( 'Assigned Subjects:', 'ifsedu-sms' ); ?>
                                            </span>
                                            <div class="dpt-subject-tag-list">
                                                <?php foreach ( $unit['subjects'] as $sub ) : ?>
                                                    <span class="dpt-subject-pill" title="<?php echo esc_attr( $sub['code'] ? 'Code: ' . $sub['code'] : '' ); ?>">
                                                        <span class="dashicons dashicons-book-alt" style="font-size:12px; width:12px; height:12px; vertical-align:middle;"></span>
                                                        <?php echo esc_html( $sub['name'] ); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="display:flex; gap:8px; border-top:1px solid #e2e8f0; padding-top:12px; margin-top:6px;">
                                        <a href="<?php echo esc_url( $att_url ); ?>" class="dpt-action-tile" style="padding:6px 12px; font-size:12px; flex:1; justify-content:center;">
                                            <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Attendance', 'ifsedu-sms' ); ?>
                                        </a>
                                        <a href="<?php echo esc_url( $marks_url ); ?>" class="dpt-action-tile" style="padding:6px 12px; font-size:12px; flex:1; justify-content:center; background:#006a4e; color:#ffffff; border-color:#006a4e;">
                                            <span class="dashicons dashicons-edit" style="color:#ffffff !important;"></span> <?php esc_html_e( 'Marks', 'ifsedu-sms' ); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:12px; padding:36px 20px; text-align:center;">
                            <span class="dashicons dashicons-info" style="font-size:32px; width:32px; height:32px; color:#94a3b8; margin-bottom:8px;"></span>
                            <p style="margin:0; font-size:14px; font-weight:700; color:#475569;">
                                <?php esc_html_e( 'No subjects or classes are currently assigned to your teacher account in Academic Setup.', 'ifsedu-sms' ); ?>
                            </p>
                            <small style="color:#64748b; margin-top:4px; display:block;">
                                <?php esc_html_e( 'Please contact the administration to map your subjects in Academic Setup -> Assign Subjects.', 'ifsedu-sms' ); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Faculty Circulars & Quick Links -->
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
                            <?php foreach ( $teacher_notices as $n ) : ?>
                                <div class="dpt-list-item">
                                    <div>
                                        <strong style="color:#0f172a; font-size:13.5px;"><?php echo esc_html( $n->title ); ?></strong>
                                        <div style="font-size:11.5px; color:#64748b; margin-top:2px;">
                                            <?php echo esc_html( date_i18n( 'd M Y', strtotime( $n->publish_date ) ) ); ?> &bull; 
                                            <span class="dpt-badge-pill"><?php echo esc_html( $n->category ); ?></span>
                                        </div>
                                    </div>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notice&type=notice&sub=view&id=' . $n->id ) ); ?>" class="dpt-action-tile" style="padding:4px 10px; font-size:12px;">
                                        <?php esc_html_e( 'Read', 'ifsedu-sms' ); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p style="color:#94a3b8; font-size:13px; margin:0;"><?php esc_html_e( 'No notices found.', 'ifsedu-sms' ); ?></p>
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

    $today_income = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Income' AND entry_date = %s", current_time('Y-m-d') ) ) ?: 0.00;
    $month_income = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Income' AND entry_date BETWEEN %s AND %s", current_time('Y-m-01'), current_time('Y-m-t') ) ) ?: 0.00;
    $month_exp    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$table_accounting} WHERE entry_type = 'Expense' AND entry_date BETWEEN %s AND %s", current_time('Y-m-01'), current_time('Y-m-t') ) ) ?: 0.00;
    $recent_trans = $wpdb->get_results( "SELECT * FROM {$table_accounting} ORDER BY entry_date DESC, id DESC LIMIT 5" );

    educore_dashboard_render_hero_profile( 
        $user, 
        __( 'Accounts & Cash Office', 'ifsedu-sms' ),
        array(
            __( 'Role', 'ifsedu-sms' ) => __( 'Accounts Officer', 'ifsedu-sms' )
        ),
        array(
            'url'   => admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=add' ),
            'icon'  => 'dashicons-plus-alt2',
            'label' => __( 'Record Cash Voucher', 'ifsedu-sms' )
        )
    ); 
    ?>
    <div class="dpt-dash-root">
        <div class="dpt-bento-grid-4">
            <div class="dpt-stat-bento stat-collected">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'TODAY COLLECTED', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 24px; font-weight: 800; color: #059669; margin-top: 4px;">৳<?php echo esc_html( number_format($today_income, 2) ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #ecfdf5; color: #059669;">
                        <span class="dashicons dashicons-money"></span>
                    </div>
                </div>
            </div>
            <div class="dpt-stat-bento">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'MONTH COLLECTIONS', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 24px; font-weight: 800; color: #2563eb; margin-top: 4px;">৳<?php echo esc_html( number_format($month_income, 2) ); ?></div>
                    </div>
                    <div class="dpt-stat-icon-badge" style="background: #eff6ff; color: #2563eb;">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                </div>
            </div>
            <div class="dpt-stat-bento stat-expense">
                <div class="dpt-stat-top-row">
                    <div>
                        <span class="dpt-sub-stat-label"><?php esc_html_e( 'MONTH EXPENSES', 'ifsedu-sms' ); ?></span>
                        <div style="font-size: 24px; font-weight: 800; color: #dc2626; margin-top: 4px;">৳<?php echo esc_html( number_format($month_exp, 2) ); ?></div>
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
            </div>
            <div style="overflow-x:auto;">
                <table class="dpt-table">
                    <thead><tr><th>Date</th><th>Voucher No</th><th>Title</th><th>Type</th><th>Amount</th></tr></thead>
                    <tbody>
                        <?php if ( ! empty( $recent_trans ) ) : foreach ($recent_trans as $t) : ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n('d M Y', strtotime($t->entry_date)) ); ?></td>
                                <td><code><?php echo esc_html($t->voucher_no); ?></code></td>
                                <td><strong><?php echo esc_html($t->title); ?></strong></td>
                                <td><span style="font-weight:700; color:<?php echo $t->entry_type === 'Income' ? '#006a4e' : '#dc2626'; ?>;"><?php echo esc_html($t->entry_type); ?></span></td>
                                <td><strong>৳<?php echo esc_html( number_format($t->amount, 2) ); ?></strong></td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:20px;"><?php esc_html_e( 'No transactions recorded yet.', 'ifsedu-sms' ); ?></td></tr>
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

    $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_students} WHERE email = %s OR full_name = %s LIMIT 1", $user->user_email, $user->display_name ) );
    $student_notices = $wpdb->get_results( "SELECT * FROM {$table_notices} WHERE target_audience IN ('All', 'Students') ORDER BY publish_date DESC LIMIT 4" );

    $extra_meta = array();
    if ( $student ) {
        $extra_meta[ __( 'Class', 'ifsedu-sms' ) ]   = $student->class_name;
        $extra_meta[ __( 'Section', 'ifsedu-sms' ) ] = $student->section_name ?: 'A';
        $extra_meta[ __( 'Roll', 'ifsedu-sms' ) ]    = '#' . $student->roll_no;
        $extra_meta[ __( 'ID', 'ifsedu-sms' ) ]      = $student->student_id;
    }

    educore_dashboard_render_hero_profile( 
        $user, 
        __( 'Student & Parent Academic Portal', 'ifsedu-sms' ),
        $extra_meta,
        array(
            'url'   => admin_url( 'admin.php?page=school_management_system&tab=results&sub=report' ),
            'icon'  => 'dashicons-clipboard',
            'label' => __( 'View My Marksheet', 'ifsedu-sms' )
        )
    ); 
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
                                        <div style="font-size:12px; color:#64748b; margin-top:2px;"><?php echo esc_html( date_i18n('d M Y', strtotime($n->publish_date)) ); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p style="color:#94a3b8; font-size:13px; margin:0;"><?php esc_html_e( 'No active notices.', 'ifsedu-sms' ); ?></p>
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
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <a href="<?php echo esc_url( admin_url('admin.php?page=school_management_system&tab=students&sub=id_card') ); ?>" class="dpt-action-tile">
                            <span class="dashicons dashicons-id"></span> Digital ID Card
                        </a>
                        <a href="<?php echo esc_url( admin_url('admin.php?page=school_management_system&tab=students&sub=admit_card') ); ?>" class="dpt-action-tile">
                            <span class="dashicons dashicons-tickets-alt"></span> Admit Card
                        </a>
                        <a href="<?php echo esc_url( admin_url('admin.php?page=school_management_system&tab=results&sub=report') ); ?>" class="dpt-action-tile">
                            <span class="dashicons dashicons-media-document"></span> Academic Transcript
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}