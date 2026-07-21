<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Render Core Student Comprehensive Profile Single View
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
        echo '<div class="notice notice-error" style="margin: 20px 0; padding: 12px 16px; border-left-color: #ef4444;"><p>' . esc_html__( 'Student record not found.', 'educore' ) . '</p></div>';
        return;
    }

    // ডাটাবেজ থেকে শিক্ষার্থীর রেজাল্ট এবং ফি হিস্ট্রি তুলে আনা
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

    // কুইক ম্যাট্রিক্স ক্যালকুলেশন
    $total_paid = 0;
    $total_due  = 0;
    if ( ! empty( $fee_ledgers ) ) {
        foreach ( $fee_ledgers as $ledger ) {
            $total_paid += floatval( $ledger->paid_amount );
            $total_due  += floatval( $ledger->due_amount );
        }
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );
    $edit_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=edit&id=' . absint( $student->id ) );
    
    $first_letter = mb_substr( $student->full_name, 0, 1, 'utf-8' );
    ?>
    
    <style>
        .dpt-profile-wrapper {
            margin-top: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }
        
        /* Action Bar */
        .dpt-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .dpt-btn {
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            padding: 9px 16px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .dpt-btn-secondary {
            border: 1px solid #cbd5e1;
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
            border: 1px solid #006a4e;
            color: #ffffff;
        }
        .dpt-btn-primary:hover {
            background-color: #00523d;
            border-color: #00523d;
            color: #ffffff;
        }

        /* Hero Header Card */
        .dpt-profile-header-card {
            background: #ffffff;
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        .dpt-profile-header-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #006a4e 0%, #10b981 100%);
        }
        
        /* Neo-Bento Quick Stats Grid */
        .dpt-bento-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .dpt-bento-card {
            background: #ffffff;
            padding: 18px 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .dpt-bento-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        /* Navigation Tabs */
        .dnt-profile-tabs {
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 24px;
            display: flex;
            gap: 8px;
        }
        .dnt-profile-tabs .nav-link { 
            color: #64748b; 
            font-weight: 600; 
            border: none; 
            border-bottom: 2px solid transparent;
            background: transparent;
            padding: 12px 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .dnt-profile-tabs .nav-link:hover {
            color: #006a4e;
        }
        .dnt-profile-tabs .nav-link.active { 
            color: #006a4e !important; 
            border-bottom: 2px solid #006a4e !important; 
        }

        /* Tab Content Panel */
        .dpt-tab-workspace {
            background: #ffffff;
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        
        /* Tables Styling */
        .dpt-profile-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .dpt-profile-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            color: #334155;
            vertical-align: middle;
        }
        .dpt-profile-table tr:last-child td {
            border-bottom: none;
        }
        .dpt-profile-table td.dpt-label-bg {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            width: 32%;
            border-right: 1px solid #e2e8f0;
        }

        .dpt-data-responsive-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .dpt-data-responsive-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            padding: 14px 16px;
            border-bottom: 2px solid #e2e8f0;
            font-size: 13px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .dpt-data-responsive-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }
        .dpt-data-responsive-table tr:last-child td {
            border-bottom: none;
        }
        .dpt-data-responsive-table tr:hover td {
            background: #f8fafc;
        }

        /* Status Badges */
        .dpt-badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .dpt-status-active { background: #dcfce7; color: #15803d; }
        .dpt-status-inactive { background: #fee2e2; color: #b91c1c; }
        .dpt-status-paid { background: #dcfce7; color: #15803d; }
        .dpt-status-partial { background: #fef9c3; color: #a16207; }
        .dpt-status-unpaid { background: #fee2e2; color: #b91c1c; }
    </style>

    <div class="dpt-profile-wrapper">
        <!-- Action Buttons Bar -->
        <div class="dpt-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn dpt-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'Back to Directory', 'educore' ); ?>
            </a>
            <a href="<?php echo esc_url( $edit_url ); ?>" class="dpt-btn dpt-btn-primary">
                <span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit Profile', 'educore' ); ?>
            </a>
        </div>

        <!-- Profile Hero Header Card -->
        <div class="dpt-profile-header-card">
            <div style="display: flex; align-items: center; gap: 28px; flex-wrap: wrap;">
                <div>
                    <?php if ( ! empty( $student->photo_url ) ) : ?>
                        <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="Student Photo" style="width: 110px; height: 110px; object-fit: cover; border-radius: 50%; border: 4px solid #f1f5f9; box-shadow: 0 4px 10px rgba(0,0,0,0.08);">
                    <?php else : ?>
                        <div style="width: 110px; height: 110px; background: linear-gradient(135deg, #006a4e 0%, #059669 100%); color: #ffffff; font-weight: 700; font-size: 2.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 4px solid #f1f5f9; box-shadow: 0 4px 10px rgba(0,0,0,0.08);">
                            <?php echo esc_html( $first_letter ); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                        <h2 style="margin: 0; font-size: 24px; font-weight: 700; color: #0f172a;"><?php echo esc_html( $student->full_name ); ?></h2>
                        <span class="dpt-badge-status <?php echo ( $student->status === 'Active' ) ? 'dpt-status-active' : 'dpt-status-inactive'; ?>">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor;"></span>
                            <?php echo esc_html( $student->status ); ?>
                        </span>
                    </div>
                    <?php if ( ! empty( $student->name_bn ) ) : ?>
                        <div style="color: #64748b; font-size: 15px; margin-bottom: 10px; font-weight: 500;"><?php echo esc_html( $student->name_bn ); ?></div>
                    <?php endif; ?>
                    <div style="display: flex; gap: 16px; flex-wrap: wrap; color: #475569; font-size: 14px; background: #f8fafc; padding: 10px 16px; border-radius: 8px; border: 1px solid #e2e8f0; display: inline-flex;">
                        <span>ID: <strong style="color: #0f172a;"><?php echo esc_html( $student->student_id ); ?></strong></span>
                        <span style="color: #cbd5e1;">|</span>
                        <span>Class: <strong style="color: #0f172a;"><?php echo esc_html( $student->class_name ); ?></strong></span>
                        <span style="color: #cbd5e1;">|</span>
                        <span>Roll: <strong style="color: #0f172a;"><?php echo esc_html( $student->roll_no ); ?></strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Summary Bento Widgets -->
        <div class="dpt-bento-grid">
            <div class="dpt-bento-card">
                <div class="dpt-bento-icon" style="background: #eff6ff; color: #2563eb;">
                    <span class="dashicons dashicons-welcome-learn-more"></span>
                </div>
                <div>
                    <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Total Exams', 'educore' ); ?></div>
                    <div style="font-size: 20px; font-weight: 700; color: #0f172a;"><?php echo count( $exam_results ); ?></div>
                </div>
            </div>
            <div class="dpt-bento-card">
                <div class="dpt-bento-icon" style="background: #f0fdf4; color: #16a34a;">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div>
                    <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Total Paid', 'educore' ); ?></div>
                    <div style="font-size: 20px; font-weight: 700; color: #16a34a;"><?php echo number_format( $total_paid, 2 ); ?> TK</div>
                </div>
            </div>
            <div class="dpt-bento-card">
                <div class="dpt-bento-icon" style="background: #fef2f2; color: #dc2626;">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div>
                    <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Total Due Balance', 'educore' ); ?></div>
                    <div style="font-size: 20px; font-weight: 700; color: #dc2626;"><?php echo number_format( $total_due, 2 ); ?> TK</div>
                </div>
            </div>
        </div>

        <!-- Tab Switcher Navigation -->
        <div class="dnt-profile-tabs">
            <button class="nav-link active" onclick="educoreSwitchProfileTab(event, 'dpt-details-tab')">
                <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'ব্যক্তিগত তথ্য', 'educore' ); ?>
            </button>
            <button class="nav-link" onclick="educoreSwitchProfileTab(event, 'dpt-results-tab')">
                <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'পরীক্ষার ফলাফল', 'educore' ); ?>
            </button>
            <button class="nav-link" onclick="educoreSwitchProfileTab(event, 'dpt-payments-tab')">
                <span class="dashicons dashicons-bank"></span> <?php esc_html_e( 'ফি ও পেমেন্ট হিস্ট্রি', 'educore' ); ?>
            </button>
        </div>

        <!-- Tab Workspace Area -->
        <div class="dpt-tab-workspace">
            
            <!-- 1. Personal & Academic Details Tab -->
            <div id="dpt-details-tab" class="dpt-tab-content-block">
                <div style="display: flex; gap: 28px; flex-wrap: wrap; margin-bottom: 24px;">
                    <div style="flex: 1; min-width: 320px;">
                        <h4 style="margin: 0 0 16px 0; color: #006a4e; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-book" style="font-size: 18px;"></span> Academic Record
                        </h4>
                        <table class="dpt-profile-table">
                            <tr><td class="dpt-label-bg">Class</td><td style="font-weight: 600;"><?php echo esc_html( $student->class_name ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Section/Group</td><td style="font-weight: 600;"><?php echo $student->section_name ? esc_html( $student->section_name ) : '—'; ?></td></tr>
                            <tr><td class="dpt-label-bg">Roll No</td><td style="font-weight: 600;"><?php echo esc_html( $student->roll_no ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Admission Date</td><td><?php echo ( ! empty( $student->admission_date ) && $student->admission_date !== '0000-00-00' ) ? esc_html( date( "d M Y", strtotime( $student->admission_date ) ) ) : '—'; ?></td></tr>
                        </table>
                    </div>
                    
                    <div style="flex: 1; min-width: 320px;">
                        <h4 style="margin: 0 0 16px 0; color: #006a4e; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-id" style="font-size: 18px;"></span> Personal & Guardian Details
                        </h4>
                        <table class="dpt-profile-table">
                            <tr><td class="dpt-label-bg">Date of Birth</td><td><?php echo ( ! empty( $student->dob ) && $student->dob !== '0000-00-00' ) ? esc_html( date( "d M Y", strtotime( $student->dob ) ) ) : '—'; ?></td></tr>
                            <tr><td class="dpt-label-bg">Gender</td><td><?php echo esc_html( ucfirst( $student->gender ) ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Guardian Name</td><td style="font-weight: 600;"><?php echo esc_html( $student->guardian_name ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Guardian Phone</td><td><?php echo esc_html( $student->guardian_phone ); ?></td></tr>
                        </table>
                    </div>
                </div>
                
                <div>
                    <h4 style="margin: 0 0 12px 0; color: #006a4e; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-location" style="font-size: 18px;"></span> Address Details
                    </h4>
                    <div style="padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; color: #334155; font-size: 14px; line-height: 1.6;">
                        <?php echo ! empty( $student->address ) ? nl2br( esc_html( $student->address ) ) : 'No address record found for this student.'; ?>
                    </div>
                </div>
            </div>

            <!-- 2. Exam Results Tab -->
            <div id="dpt-results-tab" class="dpt-tab-content-block" style="display: none;">
                <h4 style="margin: 0 0 16px 0; color: #006a4e; font-size: 16px; font-weight: 700;"><?php esc_html_e( 'Academic Results Ledger', 'educore' ); ?></h4>
                <table class="dpt-data-responsive-table">
                    <thead>
                        <tr>
                            <th>Exam Name</th>
                            <th>Subject</th>
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
                            <td><span style="font-weight: 700; color: #0f172a;"><?php echo esc_html( $res->obtained_marks ); ?></span></td>
                            <td><span style="background: #f1f5f9; color: #334155; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; border: 1px solid #cbd5e1;"><?php echo esc_html( $res->grade ); ?></span></td>
                            <td><strong style="color: #2563eb;"><?php echo esc_html( $res->gpa ); ?></strong></td>
                        </tr>
                        <?php endforeach; else : ?>
                        <tr><td colspan="6" style="text-align: center; color: #64748b; padding: 28px 0;"><?php esc_html_e( 'No examination results found for this student.', 'educore' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 3. Fee & Payment Ledger Tab -->
            <div id="dpt-payments-tab" class="dpt-tab-content-block" style="display: none;">
                <h4 style="margin: 0 0 16px 0; color: #006a4e; font-size: 16px; font-weight: 700;"><?php esc_html_e( 'Fee Collection & Financial Records', 'educore' ); ?></h4>
                <table class="dpt-data-responsive-table">
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Month / Year</th>
                            <th>Fee Type</th>
                            <th>Net Payable</th>
                            <th>Paid Amount</th>
                            <th>Due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $fee_ledgers ) ) : foreach ( $fee_ledgers as $fee ) : 
                            $pay_status = strtolower( trim( $fee->payment_status ) );
                            $status_class = 'dpt-status-unpaid';
                            if ( $pay_status === 'paid' ) {
                                $status_class = 'dpt-status-paid';
                            } elseif ( $pay_status === 'partial' ) {
                                $status_class = 'dpt-status-partial';
                            }
                            ?>
                        <tr>
                            <td><code><?php echo esc_html( $fee->invoice_id ); ?></code></td>
                            <td><?php echo esc_html( $fee->fee_month . ' / ' . $fee->fee_year ); ?></td>
                            <td><?php echo esc_html( $fee->fee_type ); ?></td>
                            <td><?php echo esc_html( $fee->net_payable ); ?> TK</td>
                            <td style="color: #16a34a; font-weight: 700;"><?php echo esc_html( $fee->paid_amount ); ?> TK</td>
                            <td style="color: #dc2626; font-weight: 600;"><?php echo esc_html( $fee->due_amount ); ?> TK</td>
                            <td>
                                <span class="dpt-badge-status <?php echo esc_attr( $status_class ); ?>">
                                    <?php echo esc_html( ucfirst( $fee->payment_status ) ); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; else : ?>
                        <tr><td colspan="7" style="text-align: center; color: #64748b; padding: 28px 0;"><?php esc_html_e( 'No financial records or fee collection entries found.', 'educore' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- Vanilla Fast Tab Switching Script -->
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