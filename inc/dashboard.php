<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * High-End Executive Bento Dashboard Control Panel (Enterprise Edition)
 * Database Mapping: sms_students, sms_attendance, sms_fees, sms_staff, sms_accounting, sms_academic_units, sms_exams
 */
function educore_dashboard_tab() {
    global $wpdb;

    // Database Table Registries
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $table_fees       = $wpdb->prefix . 'sms_fees';
    $table_staff      = $wpdb->prefix . 'sms_staff';
    $table_accounting = $wpdb->prefix . 'sms_accounting';
    $table_units      = $wpdb->prefix . 'sms_academic_units';
    $table_exams      = $wpdb->prefix . 'sms_exams';

    // Time Frames & Ranges
    $today_date          = current_time( 'Y-m-d' );
    $today_start         = current_time( 'Y-m-d 00:00:00' );
    $today_end           = current_time( 'Y-m-d 23:59:59' );
    $current_month_start = current_time( 'Y-m-01 00:00:00' );
    $current_month_end   = current_time( 'Y-m-t 23:59:59' );

    // 1. STUDENT STATS & GENDER RATIO
    $total_students  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_students} WHERE status = %s", 'Active' ) );
    $male_students   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_students} WHERE status = %s AND gender = %s", 'Active', 'Male' ) );
    $female_students = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_students} WHERE status = %s AND gender = %s", 'Active', 'Female' ) );
    $total_classes   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT class_name) FROM {$table_units} WHERE class_name != ''" );
    $total_exams     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_exams}" );

    // 2. TODAY'S ATTENDANCE ANALYTICS
    $today_present = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_attendance} WHERE attendance_date = %s AND status = %s",
        $today_date, 'Present'
    ) );

    $today_absent = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_attendance} WHERE attendance_date = %s AND status = %s",
        $today_date, 'Absent'
    ) );

    $attendance_total_records = $today_present + $today_absent;
    $attendance_percentage    = $attendance_total_records > 0 ? round( ( $today_present / $attendance_total_records ) * 100, 1 ) : 0;

    // Class-Wise Attendance Summary Today
    $class_attendance_summary = $wpdb->get_results( $wpdb->prepare( "
        SELECT 
            s.class_name,
            COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_count,
            COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_count,
            COUNT(a.id) as total_logged
        FROM {$table_attendance} a
        INNER JOIN {$table_students} s ON a.student_id = s.id
        WHERE a.attendance_date = %s
        GROUP BY s.class_name
        ORDER BY CAST(s.class_name AS UNSIGNED) ASC, s.class_name ASC
    ", $today_date ) );

    // 3. COMPREHENSIVE FINANCIAL HEALTH
    $today_fee_collection = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT IFNULL(SUM(paid_amount), 0.00) FROM {$table_fees} WHERE payment_date BETWEEN %s AND %s",
        $today_start, $today_end
    ) );

    $month_fee_collection = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT IFNULL(SUM(paid_amount), 0.00) FROM {$table_fees} WHERE payment_date BETWEEN %s AND %s",
        $current_month_start, $current_month_end
    ) );

    $month_expenses = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT IFNULL(SUM(amount), 0.00) FROM {$table_accounting} WHERE entry_type = 'Expense' AND entry_date BETWEEN %s AND %s",
        current_time( 'Y-m-01' ), current_time( 'Y-m-t' )
    ) );

    $total_pending_fees = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT IFNULL(SUM(due_amount), 0.00) FROM {$table_fees} WHERE payment_status IN (%s, %s)",
        'Unpaid', 'Partial'
    ) );

    $total_all_income   = (float) $wpdb->get_var( "SELECT IFNULL(SUM(paid_amount), 0.00) FROM {$table_fees}" ) + (float) $wpdb->get_var( "SELECT IFNULL(SUM(amount), 0.00) FROM {$table_accounting} WHERE entry_type = 'Income'" );
    $total_all_expense  = (float) $wpdb->get_var( "SELECT IFNULL(SUM(amount), 0.00) FROM {$table_accounting} WHERE entry_type = 'Expense'" );
    $net_operating_cash = $total_all_income - $total_all_expense;

    // 4. FACULTY & STAFF STATS
    $total_staff    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_staff} WHERE status = %s", 'Active' ) );
    $total_teachers = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_staff} WHERE status = %s AND staff_type LIKE %s", 'Active', '%Teacher%' ) );

    // 5. UNIFIED RECENT FINANCIAL TRANSACTIONS FEED
    $recent_receipts = $wpdb->get_results( "
        (SELECT 
            'Student Fee' as flow_group, 
            'Income' as flow_type, 
            invoice_id as ref_code, 
            paid_amount as amount, 
            payment_date as trans_date, 
            payment_method, 
            'Tuition & Academic' as title
        FROM {$table_fees} 
        WHERE paid_amount > 0)
        UNION ALL
        (SELECT 
            CONCAT('General ', entry_type) as flow_group, 
            entry_type as flow_type, 
            voucher_no as ref_code, 
            amount, 
            entry_date as trans_date, 
            payment_method, 
            title
        FROM {$table_accounting})
        ORDER BY trans_date DESC 
        LIMIT 6
    " );

    // Routing URLs
    $students_tab_url   = admin_url( 'admin.php?page=school_management_system&tab=students' );
    $attendance_tab_url = admin_url( 'admin.php?page=school_management_system&tab=attendance' );
    $fees_tab_url       = admin_url( 'admin.php?page=school_management_system&tab=fees' );
    $acct_tab_url       = admin_url( 'admin.php?page=school_management_system&tab=accounting' );
    $exams_tab_url      = admin_url( 'admin.php?page=school_management_system&tab=exams' );
    $reports_tab_url    = admin_url( 'admin.php?page=school_management_system&tab=reports&sub=finance' );

    // Dynamic Greeting Engine
    $current_hour = (int) current_time( 'H' );
    if ( $current_hour >= 5 && $current_hour < 12 ) {
        $greeting_prefix = __( 'Good Morning', 'ifsedu-sms' );
        $greeting_icon   = 'dashicons-visibility';
    } elseif ( $current_hour >= 12 && $current_hour < 18 ) {
        $greeting_prefix = __( 'Good Afternoon', 'ifsedu-sms' );
        $greeting_icon   = 'dashicons-lightbulb';
    } else {
        $greeting_prefix = __( 'Good Evening', 'ifsedu-sms' );
        $greeting_icon   = 'dashicons-star-filled';
    }

    $current_wp_user   = wp_get_current_user();
    $user_display_name = ! empty( $current_wp_user->display_name ) ? $current_wp_user->display_name : __( 'Administrator', 'ifsedu-sms' );
    ?>

    <style>
        .educore-dashboard-wrapper {
            margin: 20px 20px 40px 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
        }

        /* 1. Ultra-Modern Aurora Hero Card */
        .dpt-hero-aurora {
            background: radial-gradient(circle at 80% 20%, rgba(0, 106, 78, 0.45) 0%, transparent 60%),
                        radial-gradient(circle at 20% 80%, rgba(37, 99, 235, 0.25) 0%, transparent 50%),
                        linear-gradient(135deg, #090d16 0%, #111827 50%, #061e16 100%);
            border-radius: 24px;
            padding: 34px 38px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.4);
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 24px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .dpt-hero-left {
            display: flex;
            align-items: center;
            gap: 22px;
            z-index: 2;
        }

        .dpt-hero-avatar-box {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.08);
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #34d399;
            flex-shrink: 0;
            backdrop-filter: blur(16px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .dpt-hero-avatar-box .dashicons {
            font-size: 36px;
            width: 36px;
            height: 36px;
        }

        .dpt-hero-text h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.6px;
            line-height: 1.2;
        }

        .dpt-hero-text p {
            margin: 6px 0 0 0;
            color: #94a3b8;
            font-size: 13.5px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dpt-pill-live {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16, 185, 129, 0.16);
            color: #34d399;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            border: 1px solid rgba(52, 211, 153, 0.3);
            text-transform: uppercase;
        }

        .dpt-pulse-dot {
            width: 7px;
            height: 7px;
            background-color: #34d399;
            border-radius: 50%;
            animation: dptPulse 1.8s infinite;
        }

        @keyframes dptPulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(52, 211, 153, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0); }
        }

        .dpt-clock-glass {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 12px 20px;
            border-radius: 18px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            backdrop-filter: blur(16px);
            z-index: 2;
        }

        .dpt-clock-digits {
            font-size: 22px;
            font-weight: 800;
            color: #34d399;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* 2. Top Metric Matrix (6 Primary Indicators) */
        .dpt-bento-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 26px;
        }

        @media (max-width: 1200px) { .dpt-bento-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px)  { .dpt-bento-grid { grid-template-columns: 1fr; } }

        .dpt-card-node {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 22px 26px;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease;
            min-height: 175px;
        }

        .dpt-card-node:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 28px -8px rgba(0, 0, 0, 0.06);
            border-color: #cbd5e1;
        }

        .dpt-card-node::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 4.5px;
        }

        .border-blue::before    { background: linear-gradient(180deg, #2563eb, #3b82f6); }
        .border-emerald::before { background: linear-gradient(180deg, #059669, #10b981); }
        .border-amber::before   { background: linear-gradient(180deg, #d97706, #f59e0b); }
        .border-purple::before  { background: linear-gradient(180deg, #7c3aed, #a855f7); }
        .border-rose::before    { background: linear-gradient(180deg, #dc2626, #f43f5e); }
        .border-teal::before    { background: linear-gradient(180deg, #006a4e, #0d9488); }

        .dpt-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .dpt-card-label {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
        }

        .dpt-icon-badge {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dpt-card-val {
            font-size: 32px;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.8px;
            margin-bottom: 16px;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }

        .dpt-card-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid #f1f5f9;
            padding-top: 12px;
            margin-top: auto;
        }

        .dpt-tag-pill {
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .dpt-link-arrow {
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: transform 0.2s ease;
        }
        .dpt-link-arrow:hover { transform: translateX(3px); }

        /* 3. Middle Strip: Month-at-a-Glance Financial Bar */
        .dpt-month-summary-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 20px 24px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 26px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
        }

        @media (max-width: 900px) {
            .dpt-month-summary-bar { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .dpt-month-summary-bar { grid-template-columns: 1fr; }
        }

        .dpt-month-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .dpt-month-item:not(:last-child) {
            border-right: 1px dashed #e2e8f0;
            padding-right: 15px;
        }

        @media (max-width: 900px) {
            .dpt-month-item:not(:last-child) { border-right: none; }
        }

        .dpt-month-lbl {
            font-size: 11px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dpt-month-amt {
            font-size: 20px;
            font-weight: 800;
        }

        /* 4. Lower Two-Column Grid: Feeds, Actions & Attendance Analytics */
        .dpt-lower-grid {
            display: grid;
            grid-template-columns: 1.8fr 1.2fr;
            gap: 24px;
        }

        @media (max-width: 1024px) { .dpt-lower-grid { grid-template-columns: 1fr; } }

        .dpt-panel-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 26px;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.03);
        }

        .dpt-panel-head {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 18px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dpt-quick-dock {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(105px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .dpt-dock-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 8px;
            text-align: center;
            text-decoration: none;
            color: #334155;
            font-weight: 700;
            font-size: 11.5px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .dpt-dock-btn:hover {
            background: #006a4e;
            color: #ffffff;
            border-color: #006a4e;
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0, 106, 78, 0.2);
        }

        .dpt-dock-btn .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
        }

        /* Feed Tables */
        .dpt-feed-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
        }

        .dpt-feed-table th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .dpt-feed-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12.5px;
            color: #334155;
            vertical-align: middle;
        }

        .dpt-feed-table tr:hover td { background: #f8fafc; }

        .dpt-invoice-pill {
            background: #f1f5f9;
            color: #0f172a;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-weight: 700;
            font-size: 11px;
            border: 1px solid #cbd5e1;
        }

        .dpt-progress-strip {
            background: #e2e8f0;
            border-radius: 10px;
            height: 8px;
            width: 100%;
            overflow: hidden;
            margin-top: 8px;
        }

        .dpt-progress-fill {
            background: linear-gradient(90deg, #059669, #34d399);
            height: 100%;
            border-radius: 10px;
        }
    </style>

    <div class="educore-dashboard-wrapper">

        <!-- 1. Aurora Hero Glassmorphism Header -->
        <div class="dpt-hero-aurora">
            <div class="dpt-hero-left">
                <div class="dpt-hero-avatar-box">
                    <span class="dashicons <?php echo esc_attr( $greeting_icon ); ?>"></span>
                </div>
                <div class="dpt-hero-text">
                    <h1><?php echo esc_html( $greeting_prefix . ', ' . $user_display_name ); ?></h1>
                    <p>
                        <?php echo sprintf( esc_html__( 'Academic Operations Engine &mdash; %d Classes, %d Students', 'ifsedu-sms' ), $total_classes, $total_students ); ?>
                        <span class="dpt-pill-live">
                            <span class="dpt-pulse-dot"></span>
                            <?php esc_html_e( 'System Active', 'ifsedu-sms' ); ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="dpt-clock-glass">
                <div style="font-size:12px; color:#cbd5e1; font-weight:600; display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-calendar-alt" style="font-size:14px; width:14px; height:14px;"></span>
                    <?php echo esc_html( date_i18n( 'l, jS F Y' ) ); ?>
                </div>
                <div class="dpt-clock-digits">
                    <span class="dashicons dashicons-clock" style="font-size:18px; width:18px; height:18px; color:#34d399;"></span>
                    <span id="educoreLiveDashboardClock">--:--:--</span>
                </div>
            </div>
        </div>

        <!-- 2. Primary 6-Block Bento Metric Matrix -->
        <div class="dpt-bento-grid">
            
            <!-- Card 1: Total Active Students -->
            <div class="dpt-card-node border-blue">
                <div class="dpt-card-top">
                    <span class="dpt-card-label"><?php esc_html_e( 'Total Active Students', 'ifsedu-sms' ); ?></span>
                    <div class="dpt-icon-badge" style="background:#eff6ff; color:#2563eb;">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                </div>
                <div class="dpt-card-val" style="color:#0f172a;">
                    <?php echo esc_html( number_format_i18n( $total_students ) ); ?>
                    <small style="font-size:13px; color:#64748b; font-weight:600;"><?php echo sprintf( esc_html__( '(%d M / %d F)', 'ifsedu-sms' ), $male_students, $female_students ); ?></small>
                </div>
                <div class="dpt-card-bottom">
                    <span class="dpt-tag-pill" style="background:#eff6ff; color:#2563eb;">
                        <?php echo sprintf( esc_html__( '%d Academic Classes', 'ifsedu-sms' ), $total_classes ); ?>
                    </span>
                    <a href="<?php echo esc_url( $students_tab_url ); ?>" class="dpt-link-arrow" style="color:#2563eb;">
                        <?php esc_html_e( 'Students Directory', 'ifsedu-sms' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 2: Attendance Present Today -->
            <div class="dpt-card-node border-emerald">
                <div class="dpt-card-top">
                    <span class="dpt-card-label"><?php esc_html_e( 'Attendance Present Today', 'ifsedu-sms' ); ?></span>
                    <div class="dpt-icon-badge" style="background:#ecfdf5; color:#059669;">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                </div>
                <div class="dpt-card-val" style="color:#059669;">
                    <?php echo esc_html( number_format_i18n( $today_present ) ); ?>
                    <small style="font-size:14px; color:#64748b; font-weight:700;">(<?php echo esc_html( $attendance_percentage ); ?>%)</small>
                </div>
                <div class="dpt-card-bottom">
                    <span class="dpt-tag-pill" style="background:#ecfdf5; color:#059669;"><?php esc_html_e( 'In Classrooms', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( $attendance_tab_url ); ?>" class="dpt-link-arrow" style="color:#059669;">
                        <?php esc_html_e( 'Take Attendance', 'ifsedu-sms' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 3: Today Absent Count -->
            <div class="dpt-card-node border-amber">
                <div class="dpt-card-top">
                    <span class="dpt-card-label"><?php esc_html_e( 'Absent Count Today', 'ifsedu-sms' ); ?></span>
                    <div class="dpt-icon-badge" style="background:#fffbeb; color:#d97706;">
                        <span class="dashicons dashicons-dismiss"></span>
                    </div>
                </div>
                <div class="dpt-card-val" style="color:#d97706;">
                    <?php echo esc_html( number_format_i18n( $today_absent ) ); ?>
                    <small style="font-size:13px; color:#94a3b8; font-weight:600;">of <?php echo esc_html( $attendance_total_records ); ?> logged</small>
                </div>
                <div class="dpt-card-bottom">
                    <span class="dpt-tag-pill" style="background:#fffbeb; color:#d97706;"><?php esc_html_e( 'Unexcused', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( $attendance_tab_url ); ?>" class="dpt-link-arrow" style="color:#d97706;">
                        <?php esc_html_e( 'Attendance Logs', 'ifsedu-sms' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 4: Today Fees Collection -->
            <div class="dpt-card-node border-purple">
                <div class="dpt-card-top">
                    <span class="dpt-card-label"><?php esc_html_e( "Today's Fee Collection", 'ifsedu-sms' ); ?></span>
                    <div class="dpt-icon-badge" style="background:#f3e8ff; color:#7c3aed;">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                </div>
                <div class="dpt-card-val" style="color:#7c3aed;">
                    ৳<?php echo esc_html( number_format( $today_fee_collection, 2 ) ); ?>
                </div>
                <div class="dpt-card-bottom">
                    <span class="dpt-tag-pill" style="background:#f3e8ff; color:#7c3aed;"><?php esc_html_e( 'Daily Inflow', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( $fees_tab_url ); ?>" class="dpt-link-arrow" style="color:#7c3aed;">
                        <?php esc_html_e( 'Collect Fee', 'ifsedu-sms' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 5: Total Pending Receivables -->
            <div class="dpt-card-node border-rose">
                <div class="dpt-card-top">
                    <span class="dpt-card-label"><?php esc_html_e( 'Pending Dues (Receivables)', 'ifsedu-sms' ); ?></span>
                    <div class="dpt-icon-badge" style="background:#fef2f2; color:#dc2626;">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                </div>
                <div class="dpt-card-val" style="color:#dc2626;">
                    ৳<?php echo esc_html( number_format( $total_pending_fees, 2 ) ); ?>
                </div>
                <div class="dpt-card-bottom">
                    <span class="dpt-tag-pill" style="background:#fef2f2; color:#dc2626;"><?php esc_html_e( 'Outstanding', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( $reports_tab_url ); ?>" class="dpt-link-arrow" style="color:#dc2626;">
                        <?php esc_html_e( 'Audit Report', 'ifsedu-sms' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 6: Faculty & Teachers -->
            <div class="dpt-card-node border-teal">
                <div class="dpt-card-top">
                    <span class="dpt-card-label"><?php esc_html_e( 'Faculty & Staff Members', 'ifsedu-sms' ); ?></span>
                    <div class="dpt-icon-badge" style="background:#ecfdf5; color:#006a4e;">
                        <span class="dashicons dashicons-businessman"></span>
                    </div>
                </div>
                <div class="dpt-card-val" style="color:#006a4e;">
                    <?php echo esc_html( number_format_i18n( $total_staff ) ); ?>
                    <small style="font-size:13px; color:#64748b; font-weight:600;"><?php echo sprintf( esc_html__( '(%d Teachers)', 'ifsedu-sms' ), $total_teachers ); ?></small>
                </div>
                <div class="dpt-card-bottom">
                    <span class="dpt-tag-pill" style="background:#ecfdf5; color:#006a4e;"><?php esc_html_e( 'Active Personnel', 'ifsedu-sms' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=staff' ) ); ?>" class="dpt-link-arrow" style="color:#006a4e;">
                        <?php esc_html_e( 'Faculty List', 'ifsedu-sms' ); ?> &rarr;
                    </a>
                </div>
            </div>

        </div>

        <!-- 3. Current Month Financial Analytics Bar -->
        <div class="dpt-month-summary-bar">
            <div class="dpt-month-item">
                <span class="dpt-month-lbl"><?php esc_html_e( 'Month Collections (Fees)', 'ifsedu-sms' ); ?></span>
                <span class="dpt-month-amt" style="color:#059669;">৳<?php echo esc_html( number_format( $month_fee_collection, 2 ) ); ?></span>
            </div>
            <div class="dpt-month-item">
                <span class="dpt-month-lbl"><?php esc_html_e( 'Month General Expenses', 'ifsedu-sms' ); ?></span>
                <span class="dpt-month-amt" style="color:#dc2626;">৳<?php echo esc_html( number_format( $month_expenses, 2 ) ); ?></span>
            </div>
            <div class="dpt-month-item">
                <span class="dpt-month-lbl"><?php esc_html_e( 'Net Operating Cash (Total)', 'ifsedu-sms' ); ?></span>
                <span class="dpt-month-amt" style="color:<?php echo $net_operating_cash >= 0 ? '#006a4e' : '#dc2626'; ?>;">৳<?php echo esc_html( number_format( $net_operating_cash, 2 ) ); ?></span>
            </div>
            <div class="dpt-month-item">
                <span class="dpt-month-lbl"><?php esc_html_e( 'Examinations Evaluated', 'ifsedu-sms' ); ?></span>
                <span class="dpt-month-amt" style="color:#2563eb;"><?php echo esc_html( $total_exams ); ?> <small style="font-size:12px; font-weight:600; color:#64748b;"><?php esc_html_e( 'Exams Configured', 'ifsedu-sms' ); ?></small></span>
            </div>
        </div>

        <!-- 4. Lower Two-Column Grid: Feeds, Actions & Class Attendance Analytics -->
        <div class="dpt-lower-grid">
            
            <!-- Left Panel: Action Command Dock & Live Transaction Feed -->
            <div class="dpt-panel-box">
                <h3 class="dpt-panel-head">
                    <span>
                        <span class="dashicons dashicons-admin-links" style="color:#006a4e; vertical-align:middle; margin-right:4px;"></span> 
                        <?php esc_html_e( 'Administrative Command Dock', 'ifsedu-sms' ); ?>
                    </span>
                </h3>

                <!-- Quick Action Buttons -->
                <div class="dpt-quick-dock">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=add' ) ); ?>" class="dpt-dock-btn">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e( 'Admit Student', 'ifsedu-sms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $attendance_tab_url ); ?>" class="dpt-dock-btn">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e( 'Attendance', 'ifsedu-sms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=fees&sub=collect' ) ); ?>" class="dpt-dock-btn">
                        <span class="dashicons dashicons-money-alt"></span>
                        <?php esc_html_e( 'Collect Fee', 'ifsedu-sms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $exams_tab_url ); ?>" class="dpt-dock-btn">
                        <span class="dashicons dashicons-awards"></span>
                        <?php esc_html_e( 'Marks Matrix', 'ifsedu-sms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=id_card' ) ); ?>" class="dpt-dock-btn">
                        <span class="dashicons dashicons-id-alt"></span>
                        <?php esc_html_e( 'ID Cards', 'ifsedu-sms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=add' ) ); ?>" class="dpt-dock-btn">
                        <span class="dashicons dashicons-calculator"></span>
                        <?php esc_html_e( 'Add Expense', 'ifsedu-sms' ); ?>
                    </a>
                </div>

                <!-- Recent Financial Transactions Stream -->
                <div style="margin-top:20px;">
                    <div style="font-size:12px; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
                        <span><?php esc_html_e( 'Recent Financial Ledger Activity', 'ifsedu-sms' ); ?></span>
                        <a href="<?php echo esc_url( $fees_tab_url ); ?>" style="font-size:11.5px; font-weight:700; color:#006a4e; text-decoration:none;"><?php esc_html_e( 'View All', 'ifsedu-sms' ); ?> &rarr;</a>
                    </div>
                    <div style="overflow-x:auto; border:1px solid #e2e8f0; border-radius:12px;">
                        <table class="dpt-feed-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Reference', 'ifsedu-sms' ); ?></th>
                                    <th><?php esc_html_e( 'Particulars', 'ifsedu-sms' ); ?></th>
                                    <th><?php esc_html_e( 'Method', 'ifsedu-sms' ); ?></th>
                                    <th><?php esc_html_e( 'Date', 'ifsedu-sms' ); ?></th>
                                    <th style="text-align:right;"><?php esc_html_e( 'Amount', 'ifsedu-sms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $recent_receipts ) ) : foreach ( $recent_receipts as $rc ) : 
                                    $is_income = ( $rc->flow_type === 'Income' );
                                ?>
                                    <tr>
                                        <td><span class="dpt-invoice-pill">#<?php echo esc_html( $rc->ref_code ); ?></span></td>
                                        <td>
                                            <strong style="color:#0f172a;"><?php echo esc_html( $rc->title ); ?></strong>
                                            <small style="color:#64748b; display:block; font-size:11px;"><?php echo esc_html( $rc->flow_group ); ?></small>
                                        </td>
                                        <td>
                                            <span style="background:#f1f5f9; color:#475569; padding:2px 8px; border-radius:4px; font-weight:700; font-size:11px; border:1px solid #cbd5e1;">
                                                <?php echo esc_html( $rc->payment_method ); ?>
                                            </span>
                                        </td>
                                        <td><small style="color:#64748b; font-weight:600;"><?php echo esc_html( date_i18n( 'd M, Y', strtotime( $rc->trans_date ) ) ); ?></small></td>
                                        <td style="text-align:right; font-weight:800; color:<?php echo $is_income ? '#059669' : '#dc2626'; ?>; font-size:13.5px;">
                                            <?php echo $is_income ? '+' : '-'; ?>৳<?php echo esc_html( number_format( (float) $rc->amount, 2 ) ); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:20px; font-weight:600;"><?php esc_html_e( 'No recent financial transactions logged yet.', 'ifsedu-sms' ); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Institutional Metrics & Class-wise Attendance Today -->
            <div class="dpt-panel-box">
                <h3 class="dpt-panel-head">
                    <span>
                        <span class="dashicons dashicons-chart-pie" style="color:#006a4e; vertical-align:middle; margin-right:4px;"></span> 
                        <?php esc_html_e( 'Class Attendance Breakdown', 'ifsedu-sms' ); ?>
                    </span>
                    <span style="font-size:11.5px; font-weight:700; color:#059669;"><?php echo esc_html( $attendance_percentage ); ?>% <?php esc_html_e( 'Overall', 'ifsedu-sms' ); ?></span>
                </h3>

                <!-- Overall Attendance Progress Bar -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; font-size:11.5px; font-weight:800; text-transform:uppercase; color:#64748b;">
                        <span><?php esc_html_e( 'Present vs Logged Today', 'ifsedu-sms' ); ?></span>
                        <span style="color:#059669;"><?php echo esc_html( $today_present ); ?> / <?php echo esc_html( $attendance_total_records ); ?></span>
                    </div>
                    <div class="dpt-progress-strip">
                        <div class="dpt-progress-fill" style="width: <?php echo esc_attr( min( 100, $attendance_percentage ) ); ?>%;"></div>
                    </div>
                </div>

                <!-- Class-by-Class Attendance Grid -->
                <div style="overflow-x:auto; border:1px solid #e2e8f0; border-radius:12px;">
                    <table class="dpt-feed-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Class', 'ifsedu-sms' ); ?></th>
                                <th><?php esc_html_e( 'Present', 'ifsedu-sms' ); ?></th>
                                <th><?php esc_html_e( 'Absent', 'ifsedu-sms' ); ?></th>
                                <th style="text-align:right;"><?php esc_html_e( 'Ratio', 'ifsedu-sms' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $class_attendance_summary ) ) : foreach ( $class_attendance_summary as $ca ) : 
                                $class_pct = $ca->total_logged > 0 ? round( ( $ca->present_count / $ca->total_logged ) * 100, 1 ) : 0;
                            ?>
                                <tr>
                                    <td><strong style="color:#0f172a;"><?php echo esc_html( $ca->class_name ); ?></strong></td>
                                    <td style="color:#059669; font-weight:700;"><?php echo esc_html( $ca->present_count ); ?></td>
                                    <td style="color:#dc2626; font-weight:700;"><?php echo esc_html( $ca->absent_count ); ?></td>
                                    <td style="text-align:right; font-weight:800; color:<?php echo $class_pct >= 75 ? '#059669' : ( $class_pct >= 50 ? '#d97706' : '#dc2626' ); ?>;">
                                        <?php echo esc_html( $class_pct ); ?>%
                                    </td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr><td colspan="4" style="text-align:center; color:#94a3b8; padding:16px; font-weight:600;"><?php esc_html_e( 'No attendance logged for any class today.', 'ifsedu-sms' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Academic Routine & Timetable Indicator -->
                <div style="margin-top:18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <span style="font-size:11px; font-weight:800; text-transform:uppercase; color:#64748b; display:block;"><?php esc_html_e( 'Routine & Schedule', 'ifsedu-sms' ); ?></span>
                        <span style="font-size:13.5px; font-weight:700; color:#0f172a;"><?php esc_html_e( 'Class Timetable Active', 'ifsedu-sms' ); ?></span>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=routine' ) ); ?>" class="dpt-link-arrow" style="color:#006a4e; font-size:12px;">
                        <?php esc_html_e( 'Routine', 'ifsedu-sms' ); ?> &rarr;
                    </a>
                </div>
            </div>

        </div>

    </div>

    <!-- Live Clock Script -->
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            function updateClock() {
                var now = new Date();
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                var seconds = String(now.getSeconds()).padStart(2, '0');
                var clockElem = document.getElementById('educoreLiveDashboardClock');
                if (clockElem) {
                    clockElem.textContent = hours + ':' + minutes + ':' + seconds;
                }
            }
            updateClock();
            setInterval(updateClock, 1000);
        });
    </script>
    <?php
}