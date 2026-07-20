<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Dashboard Tab - Academic Operations & Financial Control Panel
 * Database Mapping: sms_students, sms_attendance, sms_fees, sms_staff
 */
function educore_dashboard_tab() {
    global $wpdb;

    // Core Database Table Registries
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $table_fees       = $wpdb->prefix . 'sms_fees';
    $table_staff      = $wpdb->prefix . 'sms_staff';

    // Time Frames & Ranges
    $today_date  = current_time( 'Y-m-d' );
    $today_start = current_time( 'Y-m-d 00:00:00' );
    $today_end   = current_time( 'Y-m-d 23:59:59' );

    /* =========================================================================
       1. TOTAL ACTIVE STUDENTS
       ========================================================================= */
    $total_students = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_students WHERE status = %s",
        'Active'
    ) );

    /* =========================================================================
       2. TODAY'S ATTENDANCE (PRESENT)
       ========================================================================= */
    $today_present = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_attendance WHERE attendance_date = %s AND status = %s",
        $today_date,
        'Present'
    ) );

    /* =========================================================================
       3. TODAY'S ABSENTEES
       ========================================================================= */
    $today_absent = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_attendance WHERE attendance_date = %s AND status = %s",
        $today_date,
        'Absent'
    ) );

    /* =========================================================================
       4. FINANCIAL METRICS: TODAY'S FEE COLLECTION
       ========================================================================= */
    $today_collection = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT IFNULL(SUM(paid_amount), 0.00) FROM $table_fees WHERE payment_date BETWEEN %s AND %s",
        $today_start,
        $today_end
    ) );

    /* =========================================================================
       5. FINANCIAL METRICS: TOTAL PENDING FEES BALANCE
       ========================================================================= */
    $total_pending_fees = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT IFNULL(SUM(due_amount), 0.00) FROM $table_fees WHERE payment_status IN (%s, %s)",
        'Unpaid', 'Partial'
    ) );

    /* =========================================================================
       6. TOTAL ACTIVE STAFF & TEACHERS
       ========================================================================= */
    $total_staff = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_staff WHERE status = %s",
        'Active'
    ) );

    // Admin redirection routing anchors
    $students_tab_url   = admin_url( 'admin.php?page=school_management_system&tab=students' );
    $attendance_tab_url = admin_url( 'admin.php?page=school_management_system&tab=attendance' );
    $fees_tab_url       = admin_url( 'admin.php?page=school_management_system&tab=fees' );

    /* =========================================================================
       CUSTOM TIMELINE GREETING CALCULATOR ENGINE
       ========================================================================= */
    $current_hour = (int) current_time( 'H' ); // 24-hour scale format (00 - 23)
    
    if ( $current_hour >= 6 && $current_hour < 12 ) {
        $greeting_prefix = __( 'Good Morning', 'educore' );
    } elseif ( $current_hour >= 12 && $current_hour < 18 ) {
        $greeting_prefix = __( 'Good Afternoon', 'educore' );
    } else {
        $greeting_prefix = __( 'Good Evening', 'educore' );
    }

    $current_wp_user   = wp_get_current_user();
    $user_display_name = ! empty( $current_wp_user->display_name ) ? $current_wp_user->display_name : __( 'User', 'educore' );
    $rendered_greeting = $greeting_prefix . ', ' . $user_display_name;
    ?>
    <style>
        .educore-dashboard-wrapper {
            margin: 20px 20px 0 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }
        .educore-header-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .educore-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .educore-header-icon-box {
            background: #ecfdf5;
            padding: 12px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .educore-header-icon-box .dashicons {
            font-size: 40px;
            width: 40px;
            height: 40px;
            color: #10b981;
        }
        .educore-header-title {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
            color: #0f172a;
        }
        .educore-header-subtitle {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 14px;
        }
        .educore-live-timer-container {
            text-align: right;
        }
        .educore-date-string {
            color: #64748b;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }
        .educore-ticker-digits {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 6px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }
        .educore-summary-grid-matrix {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }
        .educore-stat-card {
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .educore-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
        }
        .educore-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }
        .educore-card-blue::before { background: #3b82f6; }
        .educore-card-green::before { background: #10b981; }
        .educore-card-amber::before { background: #f59e0b; }
        .educore-card-purple::before { background: #8b5cf6; }
        .educore-card-red::before { background: #ef4444; }
        .educore-card-slate::before { background: #64748b; }

        .educore-stat-label {
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .educore-stat-counter {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            margin: 14px 0;
            line-height: 1;
        }
        .educore-counter-green { color: #10b981; }
        .educore-counter-amber { color: #f59e0b; }
        .educore-counter-purple { color: #8b5cf6; }
        .educore-counter-red { color: #ef4444; }

        .educore-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 16px;
            margin-top: 12px;
        }
        .educore-status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        .educore-badge-blue { background: #eff6ff; color: #3b82f6; }
        .educore-badge-green { background: #ecfdf5; color: #10b981; }
        .educore-badge-amber { background: #fffbeb; color: #f59e0b; }
        .educore-badge-purple { background: #f5f3ff; color: #8b5cf6; }
        .educore-badge-red { background: #fef2f2; color: #ef4444; }
        .educore-badge-slate { background: #f8fafc; color: #64748b; }

        .educore-action-link {
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color 0.15s ease;
        }
        .educore-link-blue { color: #3b82f6; }
        .educore-link-blue:hover { color: #1d4ed8; }
        .educore-link-green { color: #10b981; }
        .educore-link-green:hover { color: #047857; }
        .educore-link-amber { color: #f59e0b; }
        .educore-link-amber:hover { color: #b45309; }
        .educore-link-red { color: #ef4444; }
        .educore-link-red:hover { color: #b91c1c; }
        
        .educore-text-span {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
        }
    </style>

    <div class="educore-dashboard-wrapper">
        
        <!-- Header Panel Section -->
        <div class="educore-header-block">
            <div class="educore-header-left">
                <div class="educore-header-icon-box">
                    <span class="dashicons dashicons-welcome-learn-more"></span>
                </div>
                <div>
                    <h1 class="educore-header-title"><?php echo esc_html( $rendered_greeting ); ?></h1>
                    <p class="educore-header-subtitle"><?php esc_html_e( 'Academic & Administrative Control Panel', 'educore' ); ?></p>
                </div>
            </div>

            <div class="educore-live-timer-container">
                <div class="educore-date-string">
                    <span class="dashicons dashicons-calendar-alt"></span> 
                    <?php echo esc_html( date_i18n( 'l, jS F Y' ) ); ?>
                </div>
                <div class="educore-ticker-digits">
                    <span class="dashicons dashicons-clock" style="color: #10b981;"></span>
                    <span id="educoreLiveTickerClock">00:00:00</span>
                </div>
            </div>
        </div>

        <!-- Matrix Bento Grid Layout -->
        <div class="educore-summary-grid-matrix">
            
            <!-- Card 1: Total Active Students -->
            <div class="educore-stat-card educore-card-blue">
                <div>
                    <div class="educore-stat-label"><?php esc_html_e( 'Total Active Students', 'educore' ); ?></div>
                    <div class="educore-stat-counter"><?php echo esc_html( number_format_i18n( $total_students ) ); ?></div>
                </div>
                <div class="educore-card-footer">
                    <span class="educore-status-badge educore-badge-blue"><?php esc_html_e( 'Enrolled', 'educore' ); ?></span>
                    <a href="<?php echo esc_url( $students_tab_url ); ?>" class="educore-action-link educore-link-blue">
                        <?php esc_html_e( 'View List', 'educore' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 2: Present Today -->
            <div class="educore-stat-card educore-card-green">
                <div>
                    <div class="educore-stat-label"><?php esc_html_e( 'Present Today', 'educore' ); ?></div>
                    <div class="educore-stat-counter educore-counter-green"><?php echo esc_html( number_format_i18n( $today_present ) ); ?></div>
                </div>
                <div class="educore-card-footer">
                    <span class="educore-status-badge educore-badge-green"><?php esc_html_e( 'In Class', 'educore' ); ?></span>
                    <a href="<?php echo esc_url( $attendance_tab_url ); ?>" class="educore-action-link educore-link-green">
                        <?php esc_html_e( 'Take Attendance', 'educore' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 3: Absent Today -->
            <div class="educore-stat-card educore-card-amber">
                <div>
                    <div class="educore-stat-label"><?php esc_html_e( 'Absent Today', 'educore' ); ?></div>
                    <div class="educore-stat-counter educore-counter-amber"><?php echo esc_html( number_format_i18n( $today_absent ) ); ?></div>
                </div>
                <div class="educore-card-footer">
                    <span class="educore-status-badge educore-badge-amber"><?php esc_html_e( 'Missing', 'educore' ); ?></span>
                    <a href="<?php echo esc_url( $attendance_tab_url ); ?>" class="educore-action-link educore-link-amber">
                        <?php esc_html_e( 'Check Logs', 'educore' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 4: Today's Fee Collection -->
            <div class="educore-stat-card educore-card-purple">
                <div>
                    <div class="educore-stat-label"><?php esc_html_e( "Today's Fee Collection", 'educore' ); ?></div>
                    <div class="educore-stat-counter educore-counter-purple">৳<?php echo esc_html( number_format( $today_collection, 2 ) ); ?></div>
                </div>
                <div class="educore-card-footer">
                    <span class="educore-status-badge educore-badge-purple"><?php esc_html_e( 'Gross Inflow', 'educore' ); ?></span>
                    <span class="educore-text-span"><?php esc_html_e( 'Real-time', 'educore' ); ?></span>
                </div>
            </div>

            <!-- Card 5: Total Pending Dues -->
            <div class="educore-stat-card educore-card-red">
                <div>
                    <div class="educore-stat-label"><?php esc_html_e( 'Total Pending Dues', 'educore' ); ?></div>
                    <div class="educore-stat-counter educore-counter-red">৳<?php echo esc_html( number_format( $total_pending_fees, 2 ) ); ?></div>
                </div>
                <div class="educore-card-footer">
                    <span class="educore-status-badge educore-badge-red"><?php esc_html_e( 'Receivables', 'educore' ); ?></span>
                    <a href="<?php echo esc_url( $fees_tab_url ); ?>" class="educore-action-link educore-link-red">
                        <?php esc_html_e( 'Collect', 'educore' ); ?> &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 6: Teachers & Staff -->
            <div class="educore-stat-card educore-card-slate">
                <div>
                    <div class="educore-stat-label"><?php esc_html_e( 'Teachers & Staff', 'educore' ); ?></div>
                    <div class="educore-stat-counter"><?php echo esc_html( number_format_i18n( $total_staff ) ); ?></div>
                </div>
                <div class="educore-card-footer">
                    <span class="educore-status-badge educore-badge-slate"><?php esc_html_e( 'Active Directory', 'educore' ); ?></span>
                    <span class="educore-text-span"><?php esc_html_e( 'Faculty', 'educore' ); ?></span>
                </div>
            </div>

        </div>
    </div>

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