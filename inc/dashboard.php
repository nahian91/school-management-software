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
        "SELECT SUM(paid_amount) FROM $table_fees WHERE payment_date BETWEEN %s AND %s",
        $today_start,
        $today_end
    ) );

    /* =========================================================================
       5. FINANCIAL METRICS: TOTAL PENDING FEES BALANCE
       ========================================================================= */
    $total_pending_fees = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(due_amount) FROM $table_fees WHERE payment_status IN (%s, %s)",
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
        $greeting_prefix = 'Good Morning';
    } elseif ( $current_hour >= 12 && $current_hour < 18 ) {
        $greeting_prefix = 'Good Afternoon';
    } else {
        $greeting_prefix = 'Good Evening';
    }

    $current_wp_user   = wp_get_current_user();
    $user_display_name = ! empty( $current_wp_user->display_name ) ? $current_wp_user->display_name : 'User';
    $rendered_greeting = $greeting_prefix . ', ' . $user_display_name;
    ?>
    <div class="educore-dashboard-wrapper">
        
        <div class="educore-header-block">
            <div style="display: flex; align-items: center; gap: 18px;">
                <div class="educore-header-logo">
                    <span class="dashicons dashicons-welcome-learn-more" style="font-size: 50px; width: 50px; height: 50px; color: #10b981;"></span>
                </div>
                <div>
                    <h1 style="margin: 0; font-size: 24px; color: #1e293b;"><?php echo esc_html( $rendered_greeting ); ?></h1>
                    <p style="margin: 5px 0 0; color: #64748b;">Academic & Administrative Control Panel</p>
                </div>
            </div>

            <div class="educore-live-timer-container" style="text-align: right;">
                <div style="color: #64748b; font-weight: 500;"><span class="dashicons dashicons-calendar-alt" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> <?php echo date( 'l, jS F Y' ); ?></div>
                <div class="educore-ticker-digits" style="font-size: 20px; font-weight: 700; color: #1e293b; margin-top: 5px;">
                    <span class="dashicons dashicons-clock" style="font-size:20px; vertical-align:middle; margin-right:4px; color: #10b981;"></span>
                    <span id="educoreLiveTickerClock">00:00:00</span>
                </div>
            </div>
        </div>

        <div class="educore-summary-grid-matrix" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 30px;">
            
            <div class="educore-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #3b82f6;">
                <div>
                    <div class="educore-stat-label" style="color: #64748b; font-size: 14px; font-weight: 600;">Total Active Students</div>
                    <div class="educore-stat-counter" style="font-size: 28px; font-weight: 700; color: #1e293b; margin: 10px 0;"><?php echo $total_students; ?></div>
                </div>
                <div class="educore-card-footer" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 15px; margin-top: 10px;">
                    <span class="educore-status-badge" style="background: #eff6ff; color: #3b82f6; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Enrolled</span>
                    <a href="<?php echo esc_url( $students_tab_url ); ?>" style="color: #3b82f6; text-decoration: none; font-size: 13px; font-weight: 600;">View List &rarr;</a>
                </div>
            </div>

            <div class="educore-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #10b981;">
                <div>
                    <div class="educore-stat-label" style="color: #64748b; font-size: 14px; font-weight: 600;">Present Today</div>
                    <div class="educore-stat-counter" style="font-size: 28px; font-weight: 700; color: #10b981; margin: 10px 0;"><?php echo $today_present; ?></div>
                </div>
                <div class="educore-card-footer" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 15px; margin-top: 10px;">
                    <span class="educore-status-badge" style="background: #ecfdf5; color: #10b981; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">In Class</span>
                    <a href="<?php echo esc_url( $attendance_tab_url ); ?>" style="color: #10b981; text-decoration: none; font-size: 13px; font-weight: 600;">Take Attendance &rarr;</a>
                </div>
            </div>

            <div class="educore-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #f59e0b;">
                <div>
                    <div class="educore-stat-label" style="color: #64748b; font-size: 14px; font-weight: 600;">Absent Today</div>
                    <div class="educore-stat-counter" style="font-size: 28px; font-weight: 700; color: #f59e0b; margin: 10px 0;"><?php echo $today_absent; ?></div>
                </div>
                <div class="educore-card-footer" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 15px; margin-top: 10px;">
                    <span class="educore-status-badge" style="background: #fffbeb; color: #f59e0b; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Missing</span>
                    <a href="<?php echo esc_url( $attendance_tab_url ); ?>" style="color: #f59e0b; text-decoration: none; font-size: 13px; font-weight: 600;">Check Logs &rarr;</a>
                </div>
            </div>

            <div class="educore-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #8b5cf6;">
                <div>
                    <div class="educore-stat-label" style="color: #64748b; font-size: 14px; font-weight: 600;">Today's Fee Collection</div>
                    <div class="educore-stat-counter" style="font-size: 28px; font-weight: 700; color: #8b5cf6; margin: 10px 0;">৳<?php echo number_format( $today_collection, 2 ); ?></div>
                </div>
                <div class="educore-card-footer" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 15px; margin-top: 10px;">
                    <span class="educore-status-badge" style="background: #f5f3ff; color: #8b5cf6; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Gross Inflow</span>
                    <span style="font-size: 12px; color: #64748b; font-weight: 500;">Real-time</span>
                </div>
            </div>

            <div class="educore-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #ef4444;">
                <div>
                    <div class="educore-stat-label" style="color: #64748b; font-size: 14px; font-weight: 600;">Total Pending Dues</div>
                    <div class="educore-stat-counter" style="font-size: 28px; font-weight: 700; color: #ef4444; margin: 10px 0;">৳<?php echo number_format( $total_pending_fees, 2 ); ?></div>
                </div>
                <div class="educore-card-footer" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 15px; margin-top: 10px;">
                    <span class="educore-status-badge" style="background: #fef2f2; color: #ef4444; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Receivables</span>
                    <a href="<?php echo esc_url( $fees_tab_url ); ?>" style="color: #ef4444; text-decoration: none; font-size: 13px; font-weight: 600;">Collect &rarr;</a>
                </div>
            </div>

            <div class="educore-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #64748b;">
                <div>
                    <div class="educore-stat-label" style="color: #64748b; font-size: 14px; font-weight: 600;">Teachers & Staff</div>
                    <div class="educore-stat-counter" style="font-size: 28px; font-weight: 700; color: #1e293b; margin: 10px 0;"><?php echo $total_staff; ?></div>
                </div>
                <div class="educore-card-footer" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 15px; margin-top: 10px;">
                    <span class="educore-status-badge" style="background: #f8fafc; color: #64748b; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Active Directory</span>
                    <span style="font-size: 12px; color: #64748b; font-weight: 500;">Faculty</span>
                </div>
            </div>

        </div>
    </div>

    <script>
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