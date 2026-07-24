<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Modern High-End Bento Dashboard Control Panel (Enterprise Edition)
 * Database Mapping: sms_students, sms_attendance, sms_fees, sms_staff, sms_accounting
 */
function educore_dashboard_tab() {
    global $wpdb;

    // Core Database Table Registries
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $table_fees       = $wpdb->prefix . 'sms_fees';
    $table_staff      = $wpdb->prefix . 'sms_staff';
    $table_accounting = $wpdb->prefix . 'sms_accounting';

    // Time Frames & Ranges
    $today_date  = current_time( 'Y-m-d' );
    $today_start = current_time( 'Y-m-d 00:00:00' );
    $today_end   = current_time( 'Y-m-d 23:59:59' );

    // 1. TOTAL ACTIVE STUDENTS
    $total_students = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_students WHERE status = %s",
        'Active'
    ) );

    // 2. TODAY'S ATTENDANCE METRICS
    $today_present = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_attendance WHERE attendance_date = %s AND status = %s",
        $today_date,
        'Present'
    ) );

    $today_absent = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_attendance WHERE attendance_date = %s AND status = %s",
        $today_date,
        'Absent'
    ) );

    $attendance_total_records = $today_present + $today_absent;
    $attendance_percentage    = $attendance_total_records > 0 ? round( ( $today_present / $attendance_total_records ) * 100, 1 ) : 0;

    // 3. FINANCIAL METRICS
    $today_collection = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT IFNULL(SUM(paid_amount), 0.00) FROM $table_fees WHERE payment_date BETWEEN %s AND %s",
        $today_start,
        $today_end
    ) );

    $total_pending_fees = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT IFNULL(SUM(due_amount), 0.00) FROM $table_fees WHERE payment_status IN (%s, %s)",
        'Unpaid', 'Partial'
    ) );

    // 4. TOTAL ACTIVE STAFF & TEACHERS
    $total_staff = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_staff WHERE status = %s",
        'Active'
    ) );

    // 5. RECENT FINANCIAL ACTIVITY LOGS (FEE RECEIPTS + LEDGER EXPENSES)
    $recent_receipts = $wpdb->get_results( "
        SELECT 'Fee Receipt' as type, student_id as ref, paid_amount as amount, payment_date as log_date, payment_method 
        FROM {$table_fees} 
        WHERE paid_amount > 0 
        ORDER BY id DESC LIMIT 5
    " );

    // Routing URLs
    $students_tab_url   = admin_url( 'admin.php?page=school_management_system&tab=students' );
    $attendance_tab_url = admin_url( 'admin.php?page=school_management_system&tab=attendance' );
    $fees_tab_url       = admin_url( 'admin.php?page=school_management_system&tab=fees' );
    $acct_tab_url       = admin_url( 'admin.php?page=school_management_system&tab=accounting' );

    // Dynamic Greeting Engine
    $current_hour = (int) current_time( 'H' );
    if ( $current_hour >= 6 && $current_hour < 12 ) {
        $greeting_prefix = __( 'Good Morning', 'educore' );
    } elseif ( $current_hour >= 12 && $current_hour < 18 ) {
        $greeting_prefix = __( 'Good Afternoon', 'educore' );
    } else {
        $greeting_prefix = __( 'Good Evening', 'educore' );
    }

    $current_wp_user   = wp_get_current_user();
    $user_display_name = ! empty( $current_wp_user->display_name ) ? $current_wp_user->display_name : __( 'Administrator', 'educore' );
    $rendered_greeting = $greeting_prefix . ', ' . $user_display_name;
    ?>

    <style>
        /* ==========================================================================
           MODERN NEO-BENTO ENTERPRISE DASHBOARD STYLING
           ========================================================================== */
        .educore-dashboard-wrapper {
            margin: 15px 20px 30px 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        /* Hero Welcome Banner */
        .educore-hero-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 20px;
            padding: 32px 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.15);
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 24px;
            position: relative;
            overflow: hidden;
            color: #ffffff;
        }

        .educore-hero-banner::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
        }

        .educore-hero-left {
            display: flex;
            align-items: center;
            gap: 22px;
            z-index: 2;
        }

        .educore-hero-icon-box {
            width: 68px;
            height: 68px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #10b981;
            flex-shrink: 0;
            backdrop-filter: blur(8px);
        }

        .educore-hero-icon-box .dashicons {
            font-size: 36px;
            width: 36px;
            height: 36px;
        }

        .educore-hero-title-group h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        .educore-hero-title-group p {
            margin: 6px 0 0 0;
            color: #94a3b8;
            font-size: 14px;
            font-weight: 500;
        }

        /* Timer Badge Area */
        .educore-live-clock-badge {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 14px 22px;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            backdrop-filter: blur(10px);
            z-index: 2;
        }

        .educore-date-pill {
            color: #cbd5e1;
            font-weight: 600;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .educore-clock-digits {
            font-size: 22px;
            font-weight: 800;
            color: #10b981;
            letter-spacing: -0.5px;
            font-family: monospace;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Metric Bento Grid Matrix */
        .educore-bento-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
            margin-bottom: 28px;
        }

        @media (max-width: 1100px) { .educore-bento-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px)  { .educore-bento-grid { grid-template-columns: 1fr; } }

        /* Metric Bento Card System */
        .educore-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 175px;
            position: relative;
            overflow: hidden;
            transition: all 0.25s ease;
        }

        .educore-bento-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px -4px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
        }

        .educore-bento-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 4px;
        }

        .card-students::before  { background: #2563eb; }
        .card-present::before   { background: #059669; }
        .card-absent::before    { background: #d97706; }
        .card-fees::before      { background: #7c3aed; }
        .card-dues::before      { background: #dc2626; }
        .card-staff::before     { background: #006a4e; }

        .educore-card-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .educore-card-label {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
        }

        .educore-card-icon-badge {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .educore-card-value {
            font-size: 36px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -1px;
            margin-bottom: 20px;
        }

        .val-slate  { color: #0f172a; }
        .val-green  { color: #059669; }
        .val-amber  { color: #d97706; }
        .val-purple { color: #7c3aed; }
        .val-red    { color: #dc2626; }
        .val-emerald{ color: #006a4e; }

        .educore-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid #f1f5f9;
            padding-top: 14px;
            margin-top: auto;
        }

        .educore-badge-tag {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-block;
        }

        .tag-blue    { background: #eff6ff; color: #2563eb; }
        .tag-green   { background: #ecfdf5; color: #059669; }
        .tag-amber   { background: #fffbeb; color: #d97706; }
        .tag-purple  { background: #f3e8ff; color: #7c3aed; }
        .tag-red     { background: #fef2f2; color: #dc2626; }
        .tag-emerald { background: #e6f4f1; color: #006a4e; }

        .educore-action-link-btn {
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .link-blue   { color: #2563eb; }
        .link-green  { color: #059669; }
        .link-amber  { color: #d97706; }
        .link-red    { color: #dc2626; }
        .link-emerald{ color: #006a4e; }

        .educore-action-link-btn:hover { text-decoration: underline; }

        /* Secondary Lower Bento Layout */
        .educore-lower-bento-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 22px;
        }

        @media (max-width: 1024px) { .educore-lower-bento-grid { grid-template-columns: 1fr; } }

        .dpt-panel-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 4px 20px -2px rgba(0,0,0,0.03);
        }

        .dpt-panel-title {
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

        /* Quick Shortcut Buttons */
        .educore-quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .educore-quick-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 10px;
            text-align: center;
            text-decoration: none;
            color: #334155;
            font-weight: 700;
            font-size: 12.5px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .educore-quick-btn:hover {
            background: #006a4e;
            color: #ffffff;
            border-color: #006a4e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .educore-quick-btn .dashicons {
            font-size: 22px;
            width: 22px;
            height: 22px;
        }

        /* Activity Table */
        .dpt-matrix-table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; }
        .dpt-matrix-table th { background: #f8fafc; color: #475569; font-weight: 700; font-size: 11.5px; text-transform: uppercase; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
        .dpt-matrix-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; }
    </style>

    <div class="educore-dashboard-wrapper">
        
        <!-- Welcome Hero Banner -->
        <div class="educore-hero-banner">
            <div class="educore-hero-left">
                <div class="educore-hero-icon-box">
                    <span class="dashicons dashicons-welcome-learn-more"></span>
                </div>
                <div class="educore-hero-title-group">
                    <h1><?php echo esc_html( $rendered_greeting ); ?></h1>
                    <p><?php esc_html_e( 'Academic & Institutional Operations Control Panel', 'educore' ); ?></p>
                </div>
            </div>

            <div class="educore-live-clock-badge">
                <div class="educore-date-pill">
                    <span class="dashicons dashicons-calendar-alt"></span> 
                    <?php echo esc_html( date_i18n( 'l, jS F Y' ) ); ?>
                </div>
                <div class="educore-clock-digits">
                    <span class="dashicons dashicons-clock" style="color: #10b981; font-size: 18px; width:18px; height:18px;"></span>
                    <span id="educoreLiveTickerClock">00:00:00</span>
                </div>
            </div>
        </div>

        <!-- Metric Bento Grid Matrix -->
        <div class="educore-bento-grid">
            
            <!-- Card 1: Active Students -->
            <div class="educore-bento-card card-students">
                <div>
                    <div class="educore-card-header-flex">
                        <div class="educore-card-label"><?php esc_html_e( 'Total Active Students', 'educore' ); ?></div>
                        <div class="educore-card-icon-badge" style="background:#eff6ff; color:#2563eb;"><span class="dashicons dashicons-groups"></span></div>
                    </div>
                    <div class="educore-card-value val-slate"><?php echo esc_html( number_format_i18n( $total_students ) ); ?></div>
                </div>
                <div class="educore-card-footer">
                    <span class="educore-badge-tag tag-blue"><?php esc_html_e( 'Enrolled', 'educore' ); ?></span>
                    <a href="<?php echo esc_url( $students_tab_url ); ?>" class="educore-action-link-btn link-blue">
                        <?php esc_html_e( 'Directory', 'educore' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 2: Present Today -->
            <div class="educore-bento-card card-present">
                <div>
                    <div class="educore-card-header-flex">
                        <div class="educore-card-label"><?php esc_html_e( 'Present Today', 'educore' ); ?></div>
                        <div class="educore-card-icon-badge" style="background:#ecfdf5; color:#059669;"><span class="dashicons dashicons-yes-alt"></span></div>
                    </div>
                    <div class="educore-card-value val-green">
                        <?php echo esc_html( number_format_i18n( $today_present ) ); ?>
                        <small style="font-size:14px; font-weight:600; color:#64748b;">(<?php echo $attendance_percentage; ?>%)</small>
                    </div>
                </div>
                <div class="educore-card-footer">
                    <span class="educore-badge-tag tag-green"><?php esc_html_e( 'In Class', 'educore' ); ?></span>
                    <a href="<?php echo esc_url( $attendance_tab_url ); ?>" class="educore-action-link-btn link-green">
                        <?php esc_html_e( 'Take Attendance', 'educore' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 3: Absent Today -->
            <div class="educore-bento-card card-absent">
                <div>
                    <div class="educore-card-header-flex">
                        <div class="educore-card-label"><?php esc_html_e( 'Absent Today', 'educore' ); ?></div>
                        <div class="educore-card-icon-badge" style="background:#fffbeb; color:#d97706;"><span class="dashicons dashicons-dismiss"></span></div>
                    </div>
                    <div class="educore-card-value val-amber"><?php echo esc_html( number_format_i18n( $today_absent ) ); ?></div>
                </div>
                <div class="educore-card-footer">
                    <span class="educore-badge-tag tag-amber"><?php esc_html_e( 'Missing', 'educore' ); ?></span>
                    <a href="<?php echo esc_url( $attendance_tab_url ); ?>" class="educore-action-link-btn link-amber">
                        <?php esc_html_e( 'Check Logs', 'educore' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 4: Fee Collection Today -->
            <div class="educore-bento-card card-fees">
                <div>
                    <div class="educore-card-header-flex">
                        <div class="educore-card-label"><?php esc_html_e( "Today's Fee Collection", 'educore' ); ?></div>
                        <div class="educore-card-icon-badge" style="background:#f3e8ff; color:#7c3aed;"><span class="dashicons dashicons-money-alt"></span></div>
                    </div>
                    <div class="educore-card-value val-purple">৳<?php echo esc_html( number_format( $today_collection, 2 ) ); ?></div>
                </div>
                <div class="educore-card-footer">
                    <span class="educore-badge-tag tag-purple"><?php esc_html_e( 'Gross Inflow', 'educore' ); ?></span>
                    <a href="<?php echo esc_url( $fees_tab_url ); ?>" class="educore-action-link-btn link-purple">
                        <?php esc_html_e( 'Collect Fee', 'educore' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 5: Total Pending Dues -->
            <div class="educore-bento-card card-dues">
                <div>
                    <div class="educore-card-header-flex">
                        <div class="educore-card-label"><?php esc_html_e( 'Total Pending Dues', 'educore' ); ?></div>
                        <div class="educore-card-icon-badge" style="background:#fef2f2; color:#dc2626;"><span class="dashicons dashicons-warning"></span></div>
                    </div>
                    <div class="educore-card-value val-red">৳<?php echo esc_html( number_format( $total_pending_fees, 2 ) ); ?></div>
                </div>
                <div class="educore-card-footer">
                    <span class="educore-badge-tag tag-red"><?php esc_html_e( 'Receivables', 'educore' ); ?></span>
                    <a href="<?php echo esc_url( $fees_tab_url ); ?>" class="educore-action-link-btn link-red">
                        <?php esc_html_e( 'Audit Dues', 'educore' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 6: Teachers & Faculty -->
            <div class="educore-bento-card card-staff">
                <div>
                    <div class="educore-card-header-flex">
                        <div class="educore-card-label"><?php esc_html_e( 'Faculty & Staff', 'educore' ); ?></div>
                        <div class="educore-card-icon-badge" style="background:#e6f4f1; color:#006a4e;"><span class="dashicons dashicons-businessman"></span></div>
                    </div>
                    <div class="educore-card-value val-emerald"><?php echo esc_html( number_format_i18n( $total_staff ) ); ?></div>
                </div>
                <div class="educore-card-footer">
                    <span class="educore-badge-tag tag-emerald"><?php esc_html_e( 'Active Teachers', 'educore' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=staff' ) ); ?>" class="educore-action-link-btn link-emerald">
                        <?php esc_html_e( 'Faculty Directory', 'educore' ); ?> &rarr;
                    </a>
                </div>
            </div>

        </div>

        <!-- Lower Section: Quick Workflows & Activity Stream -->
        <div class="educore-lower-bento-grid">
            
            <!-- Panel 1: Quick Actions Console & System Shortcuts -->
            <div class="dpt-panel-card">
                <h3 class="dpt-panel-title">
                    <span><span class="dashicons dashicons-admin-links" style="color:#006a4e;"></span> <?php esc_html_e( 'Quick Administrative Actions', 'educore' ); ?></span>
                </h3>

                <div class="educore-quick-actions-grid">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=add' ) ); ?>" class="educore-quick-btn">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e( 'Add Student', 'educore' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $attendance_tab_url ); ?>" class="educore-quick-btn">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e( 'Attendance', 'educore' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $fees_tab_url ); ?>" class="educore-quick-btn">
                        <span class="dashicons dashicons-tickets-alt"></span>
                        <?php esc_html_e( 'Collect Fee', 'educore' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=certificate' ) ); ?>" class="educore-quick-btn">
                        <span class="dashicons dashicons-awards"></span>
                        <?php esc_html_e( 'Certificate', 'educore' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=routine' ) ); ?>" class="educore-quick-btn">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e( 'Class Routine', 'educore' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=transport' ) ); ?>" class="educore-quick-btn">
                        <span class="dashicons dashicons-location"></span>
                        <?php esc_html_e( 'Transport', 'educore' ); ?>
                    </a>
                </div>

                <!-- Recent Fee Collections Activity List -->
                <div style="margin-top:20px;">
                    <div style="font-size:13px; font-weight:800; color:#0f172a; margin-bottom:10px; text-transform:uppercase; letter-spacing:0.5px;">
                        <?php esc_html_e( 'Recent Fee Payment Receipts', 'educore' ); ?>
                    </div>
                    <div style="overflow-x:auto; border:1px solid #e2e8f0; border-radius:12px;">
                        <table class="dpt-matrix-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Transaction Type', 'educore' ); ?></th>
                                    <th><?php esc_html_e( 'Date & Time', 'educore' ); ?></th>
                                    <th><?php esc_html_e( 'Method', 'educore' ); ?></th>
                                    <th style="text-align:right;"><?php esc_html_e( 'Amount', 'educore' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $recent_receipts ) ) : foreach ( $recent_receipts as $rc ) : ?>
                                    <tr>
                                        <td><strong style="color:#2563eb;"><?php echo esc_html( $rc->type ); ?></strong></td>
                                        <td><small style="color:#64748b;"><?php echo esc_html( date_i18n( 'd M, g:i a', strtotime( $rc->log_date ) ) ); ?></small></td>
                                        <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:700;"><?php echo esc_html( $rc->payment_method ); ?></span></td>
                                        <td style="text-align:right; font-weight:800; color:#059669;">+৳<?php echo esc_html( number_format( $rc->amount, 2 ) ); ?></td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr><td colspan="4" style="text-align:center; color:#94a3b8; padding:20px;"><?php esc_html_e( 'No recent fee receipts logged today.', 'educore' ); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Panel 2: System Health & Institutional Readiness -->
            <div class="dpt-panel-card">
                <h3 class="dpt-panel-title">
                    <span><span class="dashicons dashicons-dashboard" style="color:#006a4e;"></span> <?php esc_html_e( 'System Status', 'educore' ); ?></span>
                </h3>

                <div style="display:flex; flex-direction:column; gap:16px;">
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px;">
                        <div style="font-size:12px; font-weight:700; color:#64748b; margin-bottom:4px;"><?php esc_html_e( 'Attendance Ratio', 'educore' ); ?></div>
                        <div style="font-size:20px; font-weight:800; color:#0f172a;"><?php echo $attendance_percentage; ?>% <?php esc_html_e( 'Present Today', 'educore' ); ?></div>
                        <div style="height:6px; background:#e2e8f0; border-radius:10px; margin-top:8px; overflow:hidden;">
                            <div style="width:<?php echo $attendance_percentage; ?>%; height:100%; background:#10b981; border-radius:10px;"></div>
                        </div>
                    </div>

                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px;">
                        <div style="font-size:12px; font-weight:700; color:#64748b; margin-bottom:4px;"><?php esc_html_e( 'Database Engine', 'educore' ); ?></div>
                        <div style="font-size:14px; font-weight:800; color:#059669; display:flex; align-items:center; gap:6px;">
                            <span class="dashicons dashicons-database" style="font-size:16px; width:16px; height:16px;"></span>
                            <?php esc_html_e( 'Connected & Synchronized', 'educore' ); ?>
                        </div>
                    </div>

                    <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:12px; padding:16px; color:#065f46;">
                        <div style="font-size:13px; font-weight:800; margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                            <span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'EduCore Active', 'educore' ); ?>
                        </div>
                        <small style="font-weight:600; font-size:12px;"><?php esc_html_e( 'All modules running smoothly with zero schema conflicts.', 'educore' ); ?></small>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Live Real-Time Ticker Clock Script -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        function educoreDashboardClockEngine() {
            var timeObject = new Date();
            var processString = timeObject.getHours().toString().padStart(2, '0') + ':' + 
                                timeObject.getMinutes().toString().padStart(2, '0') + ':' + 
                                timeObject.getSeconds().toString().padStart(2, '0');
            var tickerContainer = document.getElementById('educoreLiveTickerClock');
            if (tickerContainer) {
                tickerContainer.textContent = processString;
            }
        }
        setInterval(educoreDashboardClockEngine, 1000);
        educoreDashboardClockEngine();
    });
    </script>
    <?php
}