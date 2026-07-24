<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Render Core Student Comprehensive Profile Single View
 * Architecture: Neo-Bento Dashboard with Interactive Tab Matrix & Vector Systems
 * Database Scope: sms_students, sms_results, sms_exams, sms_fees
 */
function educore_student_profile_view() {
    global $wpdb;
    $student_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    if ( ! $student_id ) {
        return;
    }

    $table_name = $wpdb->prefix . 'sms_students';
    $student    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $student_id ) );

    if ( ! $student ) {
        echo '<div class="alert alert-danger my-4 border-0 shadow-sm" style="border-radius: 12px;">' . esc_html__( 'Student record not found in system database.', 'educore' ) . '</div>';
        return;
    }

    // Query exam results and fee ledger data
    $results_table = $wpdb->prefix . 'sms_results';
    $exams_table   = $wpdb->prefix . 'sms_exams';
    $fees_table    = $wpdb->prefix . 'sms_fees';

    $exam_results = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.*, e.exam_name FROM {$results_table} r 
         LEFT JOIN {$exams_table} e ON r.exam_id = e.id 
         WHERE r.student_id = %d ORDER BY r.id DESC", $student->id
    ) );

    $fee_ledgers = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$fees_table} WHERE student_id = %d ORDER BY id DESC", $student->id
    ) );

    // Quick Matrix Calculations
    $total_paid = 0;
    $total_due  = 0;
    if ( ! empty( $fee_ledgers ) ) {
        foreach ( $fee_ledgers as $ledger ) {
            $total_paid += floatval( $ledger->paid_amount );
            $total_due  += floatval( $ledger->due_amount );
        }
    }

    $back_url  = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );
    $edit_url  = admin_url( 'admin.php?page=school_management_system&tab=students&sub=edit&id=' . absint( $student->id ) );
    
    $full_name    = ! empty( $student->name_bn ) ? $student->name_bn : $student->full_name;
    $first_letter = mb_substr( $student->full_name ?? 'S', 0, 1, 'utf-8' );
    $is_active    = strtolower( trim( $student->status ?? '' ) ) === 'active';
    ?>
    
    <style>
        /* ==========================================================================
           EDUCORE PROFESSIONAL PROFILE BENTO SYSTEM
           ========================================================================== */
        .dpt-profile-wrapper {
            margin: 20px 20px 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
        }

        /* Action Bar */
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
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            line-height: 1;
        }
        .dpt-btn svg {
            width: 15px;
            height: 15px;
            fill: currentColor;
            flex-shrink: 0;
        }
        .dpt-btn-secondary {
            border: 1px solid #cbd5e1;
            color: #475569;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .dpt-btn-secondary:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }
        .dpt-btn-primary {
            background-color: #006a4e;
            border: 1px solid #006a4e;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }
        .dpt-btn-primary:hover {
            background-color: #00523d;
            border-color: #00523d;
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Hero Header Card */
        .dpt-profile-header-card {
            position: relative;
            background: linear-gradient(135deg, #006a4e 0%, #004d38 100%);
            color: #ffffff;
            border-radius: 20px;
            padding: 32px;
            overflow: hidden;
            box-shadow: 0 12px 30px -5px rgba(0, 106, 78, 0.25);
            margin-bottom: 24px;
        }
        .dpt-header-bg-pattern {
            position: absolute;
            right: -20px;
            bottom: -30px;
            opacity: 0.08;
            pointer-events: none;
        }

        /* Avatar System */
        .dpt-avatar-wrapper {
            position: relative;
            display: inline-block;
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

        /* Glassmorphic Tags */
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

        /* Status Dot Indicator */
        .status-indicator-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 4px;
        }
        .status-dot-active { background-color: #22c55e; box-shadow: 0 0 8px #22c55e; }
        .status-dot-inactive { background-color: #ef4444; box-shadow: 0 0 8px #ef4444; }

        /* Quick Stats Grid */
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
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .dpt-bento-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -3px rgba(0, 106, 78, 0.06);
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
        .dpt-bento-icon svg {
            width: 22px;
            height: 22px;
            fill: currentColor;
        }

        /* Tabs System */
        .dnt-profile-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
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
        .dnt-profile-tabs .nav-link svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }
        .dnt-profile-tabs .nav-link:hover {
            color: #006a4e;
        }
        .dnt-profile-tabs .nav-link.active { 
            color: #006a4e !important; 
            border-bottom: 3px solid #006a4e !important; 
        }

        /* Workspace Card */
        .dpt-tab-workspace {
            background: #ffffff;
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
        }

        /* Tables Architecture */
        .dpt-section-title {
            font-size: 0.92rem;
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
        .dpt-section-title svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
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
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13.5px;
            color: #334155;
            vertical-align: middle;
        }
        .dpt-profile-table tr:last-child td {
            border-bottom: none;
        }
        .dpt-profile-table td.dpt-label-bg {
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            width: 35%;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
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
            letter-spacing: 0.5px;
        }
        .dpt-data-responsive-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: #334155;
        }
        .dpt-data-responsive-table tr:last-child td {
            border-bottom: none;
        }
        .dpt-data-responsive-table tr:hover td {
            background: #f8fafc;
        }

        /* Pill Badges */
        .dpt-badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .dpt-status-paid { background: #e6f4ea; color: #137333; border: 1px solid #ceead6; }
        .dpt-status-partial { background: #fef7e0; color: #b06000; border: 1px solid #feebc8; }
        .dpt-status-unpaid { background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf; }

        @media print {
            .no-print, .dpt-action-bar, .dnt-profile-tabs { display: none !important; }
            .dpt-profile-header-card { background: #006a4e !important; color: #ffffff !important; box-shadow: none !important; }
            .dpt-tab-workspace, .dpt-bento-card { box-shadow: none !important; border: 1px solid #cbd5e1 !important; }
        }
    </style>

    <div class="dpt-profile-wrapper">
        
        <!-- Action Buttons Bar -->
        <div class="dpt-action-bar no-print">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn dpt-btn-secondary">
                <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                <?php esc_html_e( 'Back to Directory', 'educore' ); ?>
            </a>
            <div class="d-flex gap-2">
                <button onclick="window.print();" class="dpt-btn dpt-btn-secondary">
                    <svg viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                    <?php esc_html_e( 'Print Profile', 'educore' ); ?>
                </button>
                <a href="<?php echo esc_url( $edit_url ); ?>" class="dpt-btn dpt-btn-primary">
                    <svg viewBox="0 0 24 24"><path d="M3 17.25V21h4.75L17.81 9.94l-4.75-4.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 4.75 4.75 1.83-1.83z"/></svg>
                    <?php esc_html_e( 'Edit Profile', 'educore' ); ?>
                </a>
            </div>
        </div>

        <!-- Profile Hero Header Card -->
        <div class="dpt-profile-header-card">
            <svg class="dpt-header-bg-pattern" width="200" height="200" viewBox="0 0 24 24"><path fill="#ffffff" d="M12 2l-7 7v11c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V9l-7-7zm0 2.84L17.16 9H6.84L12 4.84zM7 19v-8h10v8H7z"/></svg>

            <div class="row align-items-center">
                <div class="col-md-auto text-center text-md-start mb-3 mb-md-0">
                    <div class="dpt-avatar-wrapper">
                        <?php if ( ! empty( $student->photo_url ) ) : ?>
                            <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="<?php echo esc_attr( $student->full_name ); ?>" class="dpt-avatar-img">
                        <?php else : ?>
                            <div class="dpt-avatar-placeholder mx-auto">
                                <?php echo esc_html( mb_strtoupper( $first_letter, 'utf-8' ) ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md text-center text-md-start">
                    <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-2 mb-2">
                        <h2 class="m-0 fw-bold text-white" style="letter-spacing: -0.5px;"><?php echo esc_html( $student->full_name ); ?></h2>
                        <span class="badge rounded-pill bg-white text-dark px-3 py-1 fs-6 shadow-sm">
                            <span class="status-indicator-dot <?php echo $is_active ? 'status-dot-active' : 'status-dot-inactive'; ?>"></span>
                            <?php echo esc_html( ucfirst( $student->status ) ); ?>
                        </span>
                    </div>

                    <?php if ( ! empty( $student->name_bn ) ) : ?>
                        <h5 class="fw-normal text-white-50 mb-3" style="font-family: inherit;"><?php echo esc_html( $student->name_bn ); ?></h5>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-2">
                        <div class="glass-id-badge">
                            <span class="fw-bold">ID: <?php echo esc_html( $student->student_id ); ?></span>
                        </div>
                        <div class="glass-id-badge">
                            <span>Class: <strong><?php echo esc_html( $student->class_name ); ?></strong></span>
                        </div>
                        <div class="glass-id-badge">
                            <span>Roll: <strong>#<?php echo esc_html( $student->roll_no ); ?></strong></span>
                        </div>
                        <?php if ( ! empty( $student->section_name ) ) : ?>
                            <div class="glass-id-badge">
                                <span>Section: <strong><?php echo esc_html( $student->section_name ); ?></strong></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Summary Bento Widgets -->
        <div class="dpt-bento-grid">
            <div class="dpt-bento-card">
                <div class="dpt-bento-icon" style="background: #eff6ff; color: #2563eb;">
                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                </div>
                <div>
                    <div style="font-size: 11.5px; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Total Exams Evaluated', 'educore' ); ?></div>
                    <div style="font-size: 22px; font-weight: 800; color: #0f172a; line-height: 1.2;"><?php echo count( $exam_results ); ?></div>
                </div>
            </div>
            <div class="dpt-bento-card">
                <div class="dpt-bento-icon" style="background: #f0fdf4; color: #006a4e;">
                    <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 3.93 2.5.42 3 1.34 3 2.22 0 1.02-.9 1.83-2.7 1.83-2.1 0-2.88-.95-2.98-2.25H6.88c.11 2.25 1.77 3.45 3.62 3.97V21h3v-2.11c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-5.2-4.44z"/></svg>
                </div>
                <div>
                    <div style="font-size: 11.5px; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Total Amount Paid', 'educore' ); ?></div>
                    <div style="font-size: 22px; font-weight: 800; color: #006a4e; line-height: 1.2;">৳<?php echo number_format( $total_paid, 2 ); ?></div>
                </div>
            </div>
            <div class="dpt-bento-card">
                <div class="dpt-bento-icon" style="background: #fef2f2; color: #dc2626;">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                </div>
                <div>
                    <div style="font-size: 11.5px; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Total Due Balance', 'educore' ); ?></div>
                    <div style="font-size: 22px; font-weight: 800; color: #dc2626; line-height: 1.2;">৳<?php echo number_format( $total_due, 2 ); ?></div>
                </div>
            </div>
        </div>

        <!-- Tab Switcher Navigation -->
        <div class="dnt-profile-tabs no-print">
            <button class="nav-link active" onclick="educoreSwitchProfileTab(event, 'dpt-details-tab')">
                <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                <?php esc_html_e( 'Personal & Academic Details', 'educore' ); ?>
            </button>
            <button class="nav-link" onclick="educoreSwitchProfileTab(event, 'dpt-results-tab')">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                <?php esc_html_e( 'Academic Results Ledger', 'educore' ); ?>
            </button>
            <button class="nav-link" onclick="educoreSwitchProfileTab(event, 'dpt-payments-tab')">
                <svg viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H3c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h16c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
                <?php esc_html_e( 'Financial & Fee History', 'educore' ); ?>
            </button>
        </div>

        <!-- Tab Workspace Area -->
        <div class="dpt-tab-workspace">
            
            <!-- 1. Personal & Academic Details Tab -->
            <div id="dpt-details-tab" class="dpt-tab-content-block">
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="dpt-section-title">
                            <svg viewBox="0 0 24 24"><path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/></svg>
                            <?php esc_html_e( 'Academic Profile Records', 'educore' ); ?>
                        </div>
                        <table class="dpt-profile-table">
                            <tr><td class="dpt-label-bg">Academic Class</td><td style="font-weight: 700; color:#006a4e;"><?php echo esc_html( $student->class_name ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Section / Group</td><td style="font-weight: 600;"><?php echo $student->section_name ? esc_html( $student->section_name ) : '—'; ?></td></tr>
                            <tr><td class="dpt-label-bg">Roll Number</td><td style="font-weight: 700;">#<?php echo esc_html( $student->roll_no ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Admission Date</td><td><?php echo ( ! empty( $student->admission_date ) && $student->admission_date !== '0000-00-00' ) ? esc_html( date_i18n( "d M Y", strtotime( $student->admission_date ) ) ) : '—'; ?></td></tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="dpt-section-title">
                            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            <?php esc_html_e( 'Personal & Guardian Info', 'educore' ); ?>
                        </div>
                        <table class="dpt-profile-table">
                            <tr><td class="dpt-label-bg">Date of Birth</td><td><?php echo ( ! empty( $student->dob ) && $student->dob !== '0000-00-00' ) ? esc_html( date_i18n( "d M Y", strtotime( $student->dob ) ) ) : '—'; ?></td></tr>
                            <tr><td class="dpt-label-bg">Gender</td><td><?php echo esc_html( ucfirst( $student->gender ) ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Guardian Name</td><td style="font-weight: 600;"><?php echo esc_html( $student->guardian_name ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Guardian Phone</td><td style="font-weight: 600; color:#2563eb;"><?php echo esc_html( $student->guardian_phone ); ?></td></tr>
                        </table>
                    </div>
                </div>
                
                <div>
                    <div class="dpt-section-title">
                        <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                        <?php esc_html_e( 'Residential Address Records', 'educore' ); ?>
                    </div>
                    <div style="padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; color: #334155; font-size: 14px; line-height: 1.6;">
                        <?php echo ! empty( $student->address ) ? nl2br( esc_html( $student->address ) ) : esc_html__( 'No address record registered for this student.', 'educore' ); ?>
                    </div>
                </div>
            </div>

            <!-- 2. Exam Results Tab -->
            <div id="dpt-results-tab" class="dpt-tab-content-block" style="display: none;">
                <div class="dpt-section-title">
                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                    <?php esc_html_e( 'Academic Examinations & Marks Matrix', 'educore' ); ?>
                </div>
                <div class="table-responsive">
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
                                <td><strong style="color: #0f172a;"><?php echo esc_html( $res->exam_name ); ?></strong></td>
                                <td><?php echo esc_html( $res->subject_name ); ?></td>
                                <td><?php echo esc_html( $res->total_marks ); ?></td>
                                <td><span style="font-weight: 800; color: #0f172a;"><?php echo esc_html( $res->obtained_marks ); ?></span></td>
                                <td><span style="background: #f1f5f9; color: #334155; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; border: 1px solid #cbd5e1;"><?php echo esc_html( $res->grade ); ?></span></td>
                                <td><strong style="color: #2563eb; font-size: 15px;"><?php echo esc_html( $res->gpa ); ?></strong></td>
                            </tr>
                            <?php endforeach; else : ?>
                            <tr><td colspan="6" style="text-align: center; color: #64748b; padding: 36px 0;"><?php esc_html_e( 'No examination results evaluated for this student yet.', 'educore' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. Fee & Payment Ledger Tab -->
            <div id="dpt-payments-tab" class="dpt-tab-content-block" style="display: none;">
                <div class="dpt-section-title">
                    <svg viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H3c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h16c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
                    <?php esc_html_e( 'Financial Transactions & Fee Ledger', 'educore' ); ?>
                </div>
                <div class="table-responsive">
                    <table class="dpt-data-responsive-table">
                        <thead>
                            <tr>
                                <th>Invoice ID</th>
                                <th>Period</th>
                                <th>Fee Type</th>
                                <th>Net Payable</th>
                                <th>Paid Amount</th>
                                <th>Due Balance</th>
                                <th>Payment Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $fee_ledgers ) ) : foreach ( $fee_ledgers as $fee ) : 
                                $pay_status   = strtolower( trim( $fee->payment_status ) );
                                $status_class = 'dpt-status-unpaid';
                                if ( $pay_status === 'paid' ) {
                                    $status_class = 'dpt-status-paid';
                                } elseif ( $pay_status === 'partial' ) {
                                    $status_class = 'dpt-status-partial';
                                }
                                ?>
                            <tr>
                                <td><code style="background:#f1f5f9; padding:3px 8px; border-radius:6px; border:1px solid #cbd5e1; font-weight:700; color:#0f172a;"><?php echo esc_html( $fee->invoice_id ); ?></code></td>
                                <td><?php echo esc_html( $fee->fee_month . ' / ' . $fee->fee_year ); ?></td>
                                <td><?php echo esc_html( $fee->fee_type ); ?></td>
                                <td>৳<?php echo number_format( (float)$fee->net_payable, 2 ); ?></td>
                                <td style="color: #006a4e; font-weight: 800;">৳<?php echo number_format( (float)$fee->paid_amount, 2 ); ?></td>
                                <td style="color: #dc2626; font-weight: 700;">৳<?php echo number_format( (float)$fee->due_amount, 2 ); ?></td>
                                <td>
                                    <span class="dpt-badge-status <?php echo esc_attr( $status_class ); ?>">
                                        <?php echo esc_html( ucfirst( $fee->payment_status ) ); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; else : ?>
                            <tr><td colspan="7" style="text-align: center; color: #64748b; padding: 36px 0;"><?php esc_html_e( 'No financial records or fee collection entries found.', 'educore' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Tab Switching Engine Script -->
    <script type="text/javascript">
    function educoreSwitchProfileTab(evt, tabId) {
        var i, tabcontent, tablinks;
        
        tabcontent = document.getElementsByClassName("dpt-tab-content-block");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }

        tablinks = evt.currentTarget.parentNode.getElementsByClassName("nav-link");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }

        document.getElementById(tabId).style.display = "block";
        evt.currentTarget.classList.add("active");
    }
    </script>
    <?php
}