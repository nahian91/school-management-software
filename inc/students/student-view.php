<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Render Core Student Comprehensive Profile Single View
 * Architecture: Neo-Bento Dashboard with Interactive Tab Matrix
 * Database Scope: sms_students, sms_results, sms_exams, sms_fees, sms_attendance
 * File: student-profile-view.php
 */
function educore_student_profile_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access this page.', 'ifsedu-sms' ) );
    }

    global $wpdb;
    $student_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

    if ( ! $student_id ) {
        return;
    }

    $table_students   = $wpdb->prefix . 'sms_students';
    $results_table    = $wpdb->prefix . 'sms_results';
    $exams_table      = $wpdb->prefix . 'sms_exams';
    $fees_table       = $wpdb->prefix . 'sms_fees';
    $attendance_table = $wpdb->prefix . 'sms_attendance';

    $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_students} WHERE id = %d", $student_id ) );

    if ( ! $student ) {
        echo '<div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:16px; border-radius:12px; margin:20px 20px 20px 0; font-weight:700;">' . esc_html__( 'Student record not found in system database.', 'ifsedu-sms' ) . '</div>';
        return;
    }

    // Query exam results, fee ledgers, and attendance records
    $exam_results = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.*, e.exam_name FROM {$results_table} r 
         LEFT JOIN {$exams_table} e ON r.exam_id = e.id 
         WHERE r.student_id = %d ORDER BY r.id DESC", $student->id
    ) );

    $fee_ledgers = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$fees_table} WHERE student_id = %d ORDER BY id DESC", $student->id
    ) );

    $attendance_logs = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$attendance_table} WHERE student_id = %d ORDER BY attendance_date DESC LIMIT 30", $student->id
    ) );

    // Financial Summary
    $total_paid = 0;
    $total_due  = 0;
    if ( ! empty( $fee_ledgers ) ) {
        foreach ( $fee_ledgers as $ledger ) {
            $total_paid += floatval( $ledger->paid_amount );
            $total_due  += floatval( $ledger->due_amount );
        }
    }

    // Attendance Calculations
    $total_present = 0;
    $total_absent  = 0;
    $total_late    = 0;

    if ( ! empty( $attendance_logs ) ) {
        foreach ( $attendance_logs as $att ) {
            $st = strtolower( trim( $att->status ) );
            if ( $st === 'present' ) $total_present++;
            elseif ( $st === 'absent' ) $total_absent++;
            elseif ( $st === 'late' ) $total_late++;
        }
    }

    $total_days = $total_present + $total_absent + $total_late;
    $attendance_ratio = $total_days > 0 ? round( ( $total_present / $total_days ) * 100, 1 ) : 0;

    $back_url  = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );
    $edit_url  = admin_url( 'admin.php?page=school_management_system&tab=students&sub=edit&id=' . absint( $student->id ) );

    $first_letter = mb_substr( $student->full_name ?? 'S', 0, 1, 'utf-8' );
    $is_active    = strtolower( trim( $student->status ?? '' ) ) === 'active';
    ?>

    <style>
        /* Modern Neo-Bento Layout Structure */
        .dpt-profile-wrapper {
            margin: 20px 20px 24px 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .dpt-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .dpt-btn {
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            padding: 9px 18px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
            line-height: 1;
            border: 1px solid transparent;
        }

        .dpt-btn-secondary {
            border-color: #cbd5e1;
            color: #475569;
            background: #ffffff;
        }

        .dpt-btn-secondary:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .dpt-btn-primary {
            background-color: #006a4e;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .dpt-btn-primary:hover {
            background-color: #00523d;
            color: #ffffff;
        }

        /* Profile Banner Hero */
        .dpt-profile-header-card {
            position: relative;
            background: linear-gradient(135deg, #006a4e 0%, #004d38 100%);
            color: #ffffff;
            border-radius: 20px;
            padding: 32px;
            overflow: hidden;
            box-shadow: 0 12px 30px -5px rgba(0, 106, 78, 0.2);
            margin-bottom: 24px;
        }

        .dpt-hero-flex {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }

        .dpt-avatar-img {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.35);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }

        .dpt-avatar-placeholder {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: #ffffff;
            color: #006a4e;
            font-size: 2.6rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid rgba(255, 255, 255, 0.35);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }

        .glass-id-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 10px;
            padding: 6px 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #ffffff;
        }

        /* Metric Bento Grid */
        .dpt-bento-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .dpt-bento-card {
            background: #ffffff;
            padding: 20px 24px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .dpt-bento-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* Tabs Navigation */
        .dnt-profile-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .dnt-profile-tabs .nav-link {
            color: #64748b;
            font-weight: 700;
            border: none;
            border-bottom: 3px solid transparent;
            background: transparent;
            padding: 12px 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: -2px;
        }

        .dnt-profile-tabs .nav-link:hover { color: #006a4e; }
        .dnt-profile-tabs .nav-link.active {
            color: #006a4e !important;
            border-bottom: 3px solid #006a4e !important;
        }

        .dpt-tab-workspace {
            background: #ffffff;
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
        }

        .dpt-grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        @media (max-width: 900px) { .dpt-grid-2col { grid-template-columns: 1fr; } }

        .dpt-section-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: #006a4e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dpt-profile-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .dpt-profile-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13.5px;
            color: #334155;
        }

        .dpt-profile-table td.dpt-label-bg {
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            width: 35%;
            font-size: 12px;
            text-transform: uppercase;
            border-right: 1px solid #e2e8f0;
        }

        .dpt-data-responsive-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .dpt-data-responsive-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
            text-align: left;
            text-transform: uppercase;
        }

        .dpt-data-responsive-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: #334155;
        }

        .dpt-badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-flex;
        }

        .dpt-status-paid { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .dpt-status-partial { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
        .dpt-status-unpaid { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        @media print {
            .no-print, .dpt-action-bar, .dnt-profile-tabs { display: none !important; }
            .dpt-profile-header-card { background: #006a4e !important; color: #ffffff !important; box-shadow: none !important; }
        }
    </style>

    <div class="dpt-profile-wrapper">
        
        <!-- Action Bar -->
        <div class="dpt-action-bar no-print">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn dpt-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Directory', 'ifsedu-sms' ); ?>
            </a>
            <div style="display:flex; gap:10px;">
                <button onclick="window.print();" class="dpt-btn dpt-btn-secondary">
                    <span class="dashicons dashicons-printer"></span>
                    <?php esc_html_e( 'Print Profile', 'ifsedu-sms' ); ?>
                </button>
                <a href="<?php echo esc_url( $edit_url ); ?>" class="dpt-btn dpt-btn-primary">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e( 'Edit Profile', 'ifsedu-sms' ); ?>
                </a>
            </div>
        </div>

        <!-- Banner Card -->
        <div class="dpt-profile-header-card">
            <div class="dpt-hero-flex">
                <div>
                    <?php if ( ! empty( $student->photo_url ) ) : ?>
                        <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="<?php echo esc_attr( $student->full_name ); ?>" class="dpt-avatar-img">
                    <?php else : ?>
                        <div class="dpt-avatar-placeholder">
                            <?php echo esc_html( mb_strtoupper( $first_letter, 'utf-8' ) ); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:6px;">
                        <h2 style="margin:0; font-size:26px; font-weight:800; color:#ffffff;"><?php echo esc_html( $student->full_name ); ?></h2>
                        <span style="background:#ffffff; color:#0f172a; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:800;">
                            <?php echo esc_html( ucfirst( $student->status ) ); ?>
                        </span>
                    </div>

                    <?php if ( ! empty( $student->name_bn ) ) : ?>
                        <div style="font-size:16px; opacity:0.85; margin-bottom:12px;"><?php echo esc_html( $student->name_bn ); ?></div>
                    <?php endif; ?>

                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <div class="glass-id-badge"><strong>ID:</strong> <?php echo esc_html( $student->student_id ); ?></div>
                        <div class="glass-id-badge"><strong>Class:</strong> <?php echo esc_html( $student->class_name ); ?></div>
                        <div class="glass-id-badge"><strong>Roll:</strong> #<?php echo esc_html( $student->roll_no ); ?></div>
                        <?php if ( ! empty( $student->section_name ) ) : ?>
                            <div class="glass-id-badge"><strong>Section:</strong> <?php echo esc_html( $student->section_name ); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Micro Stats -->
        <div class="dpt-bento-grid">
            <div class="dpt-bento-card">
                <div class="dpt-bento-icon" style="background:#eff6ff; color:#2563eb;"><span class="dashicons dashicons-clipboard"></span></div>
                <div>
                    <div style="font-size:11.5px; color:#64748b; font-weight:800; text-transform:uppercase;"><?php esc_html_e( 'Exams Evaluated', 'ifsedu-sms' ); ?></div>
                    <div style="font-size:22px; font-weight:800; color:#0f172a;"><?php echo count( $exam_results ); ?></div>
                </div>
            </div>

            <div class="dpt-bento-card">
                <div class="dpt-bento-icon" style="background:#ecfdf5; color:#059669;"><span class="dashicons dashicons-yes-alt"></span></div>
                <div>
                    <div style="font-size:11.5px; color:#64748b; font-weight:800; text-transform:uppercase;"><?php esc_html_e( 'Attendance Ratio', 'ifsedu-sms' ); ?></div>
                    <div style="font-size:22px; font-weight:800; color:#059669;"><?php echo $attendance_ratio; ?>%</div>
                </div>
            </div>

            <div class="dpt-bento-card">
                <div class="dpt-bento-icon" style="background:#f0fdf4; color:#006a4e;"><span class="dashicons dashicons-money-alt"></span></div>
                <div>
                    <div style="font-size:11.5px; color:#64748b; font-weight:800; text-transform:uppercase;"><?php esc_html_e( 'Total Fees Paid', 'ifsedu-sms' ); ?></div>
                    <div style="font-size:22px; font-weight:800; color:#006a4e;">৳<?php echo number_format( $total_paid, 2 ); ?></div>
                </div>
            </div>

            <div class="dpt-bento-card">
                <div class="dpt-bento-icon" style="background:#fef2f2; color:#dc2626;"><span class="dashicons dashicons-warning"></span></div>
                <div>
                    <div style="font-size:11.5px; color:#64748b; font-weight:800; text-transform:uppercase;"><?php esc_html_e( 'Total Due Balance', 'ifsedu-sms' ); ?></div>
                    <div style="font-size:22px; font-weight:800; color:#dc2626;">৳<?php echo number_format( $total_due, 2 ); ?></div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="dnt-profile-tabs no-print">
            <button class="nav-link active" onclick="educoreSwitchProfileTab(event, 'dpt-details-tab')">
                <span class="dashicons dashicons-admin-users"></span>
                <?php esc_html_e( 'Personal & Academic Info', 'ifsedu-sms' ); ?>
            </button>
            <button class="nav-link" onclick="educoreSwitchProfileTab(event, 'dpt-results-tab')">
                <span class="dashicons dashicons-welcome-learn-more"></span>
                <?php esc_html_e( 'Academic Results', 'ifsedu-sms' ); ?>
            </button>
            <button class="nav-link" onclick="educoreSwitchProfileTab(event, 'dpt-payments-tab')">
                <span class="dashicons dashicons-tickets-alt"></span>
                <?php esc_html_e( 'Fee History', 'ifsedu-sms' ); ?>
            </button>
            <button class="nav-link" onclick="educoreSwitchProfileTab(event, 'dpt-attendance-tab')">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php esc_html_e( 'Attendance Logs', 'ifsedu-sms' ); ?>
            </button>
        </div>

        <!-- Tab Workspace -->
        <div class="dpt-tab-workspace">
            
            <!-- 1. Details Tab -->
            <div id="dpt-details-tab" class="dpt-tab-content-block">
                <div class="dpt-grid-2col">
                    <div>
                        <div class="dpt-section-title"><?php esc_html_e( 'Academic Profile', 'ifsedu-sms' ); ?></div>
                        <table class="dpt-profile-table">
                            <tr><td class="dpt-label-bg">Academic Class</td><td style="font-weight:700; color:#006a4e;"><?php echo esc_html( $student->class_name ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Section</td><td><?php echo $student->section_name ? esc_html( $student->section_name ) : '—'; ?></td></tr>
                            <tr><td class="dpt-label-bg">Roll Number</td><td style="font-weight:700;">#<?php echo esc_html( $student->roll_no ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Admission Date</td><td><?php echo ( ! empty( $student->admission_date ) && $student->admission_date !== '0000-00-00' ) ? esc_html( date_i18n( 'd M Y', strtotime( $student->admission_date ) ) ) : '—'; ?></td></tr>
                        </table>
                    </div>

                    <div>
                        <div class="dpt-section-title"><?php esc_html_e( 'Personal & Guardian Details', 'ifsedu-sms' ); ?></div>
                        <table class="dpt-profile-table">
                            <tr><td class="dpt-label-bg">Date of Birth</td><td><?php echo ( ! empty( $student->dob ) && $student->dob !== '0000-00-00' ) ? esc_html( date_i18n( 'd M Y', strtotime( $student->dob ) ) ) : '—'; ?></td></tr>
                            <tr><td class="dpt-label-bg">Gender / Blood</td><td><?php echo esc_html( ucfirst( $student->gender ) . ' | Blood: ' . ( $student->blood_group ? $student->blood_group : 'N/A' ) ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Guardian Name</td><td style="font-weight:700;"><?php echo esc_html( $student->guardian_name ? $student->guardian_name : $student->father_name ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Guardian Phone</td><td style="font-weight:700; color:#2563eb;"><?php echo esc_html( $student->guardian_phone ); ?></td></tr>
                        </table>
                    </div>
                </div>

                <div>
                    <div class="dpt-section-title"><?php esc_html_e( 'Address & Contact Records', 'ifsedu-sms' ); ?></div>
                    <div style="padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; color:#334155; font-size:14px; line-height:1.6;">
                        <?php echo ! empty( $student->address ) ? nl2br( esc_html( $student->address ) ) : esc_html__( 'No registered residential address found.', 'ifsedu-sms' ); ?>
                    </div>
                </div>
            </div>

            <!-- 2. Results Tab -->
            <div id="dpt-results-tab" class="dpt-tab-content-block" style="display:none;">
                <div class="dpt-section-title"><?php esc_html_e( 'Academic Marks Matrix', 'ifsedu-sms' ); ?></div>
                <div style="overflow-x:auto;">
                    <table class="dpt-data-responsive-table">
                        <thead>
                            <tr>
                                <th>Exam Scheme</th>
                                <th>Subject Title</th>
                                <th>Total Marks</th>
                                <th>Obtained Marks</th>
                                <th>Grade</th>
                                <th>GPA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $exam_results ) ) : foreach ( $exam_results as $res ) : ?>
                                <tr>
                                    <td><strong style="color:#0f172a;"><?php echo esc_html( $res->exam_name ); ?></strong></td>
                                    <td><?php echo esc_html( $res->subject_name ); ?></td>
                                    <td><?php echo esc_html( $res->total_marks ); ?></td>
                                    <td><strong style="color:#0f172a;"><?php echo esc_html( $res->obtained_marks ); ?></strong></td>
                                    <td><span style="background:#f1f5f9; padding:3px 8px; border-radius:4px; font-weight:700; border:1px solid #cbd5e1;"><?php echo esc_html( $res->grade ); ?></span></td>
                                    <td><strong style="color:#2563eb;"><?php echo esc_html( $res->gpa ); ?></strong></td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr><td colspan="6" style="text-align:center; color:#94a3b8; padding:30px;"><?php esc_html_e( 'No examination records evaluated for this student.', 'ifsedu-sms' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. Payments Tab -->
            <div id="dpt-payments-tab" class="dpt-tab-content-block" style="display:none;">
                <div class="dpt-section-title"><?php esc_html_e( 'Fee Payment History & Invoices', 'ifsedu-sms' ); ?></div>
                <div style="overflow-x:auto;">
                    <table class="dpt-data-responsive-table">
                        <thead>
                            <tr>
                                <th>Invoice ID</th>
                                <th>Period</th>
                                <th>Fee Type</th>
                                <th>Net Payable</th>
                                <th>Paid Amount</th>
                                <th>Due Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $fee_ledgers ) ) : foreach ( $fee_ledgers as $fee ) : 
                                $pay_status   = strtolower( trim( $fee->payment_status ) );
                                $status_class = ( $pay_status === 'paid' ) ? 'dpt-status-paid' : ( ( $pay_status === 'partial' ) ? 'dpt-status-partial' : 'dpt-status-unpaid' );
                            ?>
                                <tr>
                                    <td><code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; border:1px solid #cbd5e1; font-weight:700; color:#0f172a;"><?php echo esc_html( $fee->invoice_id ); ?></code></td>
                                    <td><?php echo esc_html( $fee->fee_month . ' / ' . $fee->fee_year ); ?></td>
                                    <td><?php echo esc_html( $fee->fee_type ); ?></td>
                                    <td>৳<?php echo number_format( (float)$fee->net_payable, 2 ); ?></td>
                                    <td style="color:#006a4e; font-weight:800;">৳<?php echo number_format( (float)$fee->paid_amount, 2 ); ?></td>
                                    <td style="color:#dc2626; font-weight:700;">৳<?php echo number_format( (float)$fee->due_amount, 2 ); ?></td>
                                    <td><span class="dpt-badge-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( ucfirst( $fee->payment_status ) ); ?></span></td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr><td colspan="7" style="text-align:center; color:#94a3b8; padding:30px;"><?php esc_html_e( 'No fee collection logs found.', 'ifsedu-sms' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 4. Attendance Tab -->
            <div id="dpt-attendance-tab" class="dpt-tab-content-block" style="display:none;">
                <div class="dpt-section-title"><?php esc_html_e( 'Daily Attendance Audit Logs (Recent 30 Days)', 'ifsedu-sms' ); ?></div>
                <div style="overflow-x:auto;">
                    <table class="dpt-data-responsive-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $attendance_logs ) ) : foreach ( $attendance_logs as $att ) : 
                                $status_lower = strtolower( trim( $att->status ) );
                                $badge_color  = ( $status_lower === 'present' ) ? '#059669' : ( ( $status_lower === 'late' ) ? '#d97706' : '#dc2626' );
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html( date_i18n( 'd F, Y', strtotime( $att->attendance_date ) ) ); ?></strong></td>
                                    <td><?php echo esc_html( date_i18n( 'l', strtotime( $att->attendance_date ) ) ); ?></td>
                                    <td><strong style="color:<?php echo $badge_color; ?>;"><?php echo esc_html( ucfirst( $att->status ) ); ?></strong></td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr><td colspan="3" style="text-align:center; color:#94a3b8; padding:30px;"><?php esc_html_e( 'No daily attendance records logged.', 'ifsedu-sms' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script type="text/javascript">
    function educoreSwitchProfileTab(evt, tabId) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("dpt-tab-content-block");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tablinks = document.querySelectorAll(".dnt-profile-tabs .nav-link");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        document.getElementById(tabId).style.display = "block";
        evt.currentTarget.classList.add("active");
    }
    </script>
    <?php
}