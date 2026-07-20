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
    $student    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $student_id ) );

    if ( ! $student ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Student record not found.', 'educore' ) . '</p></div>';
        return;
    }

    // ডাটাবেজ থেকে শিক্ষার্থীর রেজাল্ট এবং ফি হিস্ট্রি তুলে আনা
    $results_table = $wpdb->prefix . 'sms_results';
    $exams_table   = $wpdb->prefix . 'sms_exams';
    $fees_table    = $wpdb->prefix . 'sms_fees';

    $exam_results = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.*, e.exam_name FROM $results_table r 
         LEFT JOIN $exams_table e ON r.exam_id = e.id 
         WHERE r.student_id = %d ORDER BY r.id DESC", $student->id
    ) );

    $fee_ledgers = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $fees_table WHERE student_id = %d ORDER BY id DESC", $student->id
    ) );

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );
    $edit_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=edit&id=' . absint( $student->id ) );
    
    $first_letter = mb_substr( $student->full_name, 0, 1, 'utf-8' );
    ?>
    
    <!-- CSS ফর সাব-ট্যাব এবং ক্লিন বেন্টো ডিজাইন ম্যাট্রিক্স -->
    <style>
        .dpt-profile-wrapper {
            margin-top: 20px;
        }
        .dpt-profile-header-card {
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .dnt-profile-tabs {
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }
        .dnt-profile-tabs .nav-link { 
            color: #64748b; 
            font-weight: 600; 
            border: none; 
            border-bottom: 2px solid transparent;
            background: transparent;
            padding: 10px 16px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.15s ease;
        }
        .dnt-profile-tabs .nav-link.active { 
            color: #006a4e !important; 
            border-bottom: 2px solid #006a4e !important; 
        }
        .dpt-tab-workspace {
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
        }
        .dpt-profile-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .dpt-profile-table td {
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            font-size: 14px;
            color: #334155;
            vertical-align: middle;
        }
        .dpt-profile-table td.dpt-label-bg {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            width: 30%;
        }
        .dpt-data-responsive-table {
            width: 100%;
            border-collapse: collapse;
        }
        .dpt-data-responsive-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            font-size: 13px;
            text-align: left;
        }
        .dpt-data-responsive-table td {
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            font-size: 14px;
            color: #334155;
        }
        .dpt-data-responsive-table tr:hover td {
            background: #f8fafc;
        }
        .dpt-badge-status {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
        }
        .dpt-status-active { background: #dcfce7; color: #15803d; }
        .dpt-status-inactive { background: #fee2e2; color: #b91c1c; }
        .dpt-status-paid { background: #dcfce7; color: #15803d; }
        .dpt-status-partial { background: #fef9c3; color: #a16207; }
        .dpt-status-unpaid { background: #fee2e2; color: #b91c1c; }
    </style>

    <div class="dpt-profile-wrapper">
        <!-- Action Buttons Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2" style="border-radius: 6px; font-weight: 600; text-decoration: none; padding: 8px 14px; border: 1px solid #cbd5e1; color: #475569; background: #fff; display: inline-flex; align-items: center; gap: 6px;">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size: 16px; width: 16px; height: 16px;"></span> Back to Directory
            </a>
            <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2" style="background-color: #006a4e; border: none; border-radius: 6px; font-weight: 600; padding: 8px 16px; color: #fff; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px;"></span> Edit Profile
            </a>
        </div>

        <!-- প্রোফাইল কার্ড মূল কন্টেইনার -->
        <div class="dpt-profile-header-card">
            <div style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
                <div>
                    <?php if ( ! empty( $student->photo_url ) ) : ?>
                        <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="Student Photo" class="rounded-circle border shadow-sm" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 3px solid #e2e8f0;">
                    <?php else : ?>
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center border shadow-sm" style="width: 100px; height: 100px; background-color: #e6f3ef; color: #006a4e; font-weight: 700; font-size: 2.2rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                            <?php echo esc_html( $first_letter ); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 style="margin: 0 0 6px 0; font-size: 22px; font-weight: 700; color: #0f172a;"><?php echo esc_html( $student->full_name ); ?></h3>
                    <p style="margin: 0 0 10px 0; color: #64748b; font-size: 14px;">
                        ID: <strong style="color: #334155;"><?php echo esc_html( $student->student_id ); ?></strong> | 
                        Class: <strong style="color: #334155;"><?php echo esc_html( $student->class_name ); ?></strong> | 
                        Roll: <strong style="color: #334155;"><?php echo esc_html( $student->roll_no ); ?></strong>
                    </p>
                    <span class="dpt-badge-status <?php echo ( $student->status === 'Active' ) ? 'dpt-status-active' : 'dpt-status-inactive'; ?>">
                        <?php echo esc_html( $student->status ); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- ট্যাব নেভিগেশন মেনুবার -->
        <div class="dnt-profile-tabs" style="display: flex; gap: 4px;">
            <button class="nav-link active" onclick="educoreSwitchProfileTab(event, 'dpt-details-tab')">ব্যক্তিগত তথ্য</button>
            <button class="nav-link" onclick="educoreSwitchProfileTab(event, 'dpt-results-tab')">পরীক্ষার ফলাফল</button>
            <button class="nav-link" onclick="educoreSwitchProfileTab(event, 'dpt-payments-tab')">ফি ও পেমেন্ট হিস্ট্রি</button>
        </div>

        <!-- ট্যাব কন্টেন্ট এরিয়া -->
        <div class="dpt-tab-workspace">
            
            <!-- ১. ব্যক্তিগত তথ্য ট্যাব -->
            <div id="dpt-details-tab" class="dpt-tab-content-block">
                <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 20px;">
                    <div style="flex: 1; min-width: 300px;">
                        <h4 style="margin: 0 0 12px 0; color: #006a4e; font-size: 16px; font-weight: 700; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px;">অ্যাকাডেমিক রেকর্ড</h4>
                        <table class="dpt-profile-table">
                            <tr><td class="dpt-label-bg">Class</td><td style="font-weight: 600;"><?php echo esc_html( $student->class_name ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Section/Group</td><td style="font-weight: 600;"><?php echo $student->section_name ? esc_html( $student->section_name ) : '—'; ?></td></tr>
                            <tr><td class="dpt-label-bg">Roll No</td><td style="font-weight: 600;"><?php echo esc_html( $student->roll_no ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Admission Date</td><td><?php echo ( ! empty( $student->admission_date ) && $student->admission_date !== '0000-00-00' ) ? esc_html( date( "d M Y", strtotime( $student->admission_date ) ) ) : '—'; ?></td></tr>
                        </table>
                    </div>
                    
                    <div style="flex: 1; min-width: 300px;">
                        <h4 style="margin: 0 0 12px 0; color: #006a4e; font-size: 16px; font-weight: 700; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px;">ব্যক্তিগত ও অভিভাবকের তথ্য</h4>
                        <table class="dpt-profile-table">
                            <tr><td class="dpt-label-bg">Date of Birth</td><td><?php echo ( ! empty( $student->dob ) && $student->dob !== '0000-00-00' ) ? esc_html( date( "d M Y", strtotime( $student->dob ) ) ) : '—'; ?></td></tr>
                            <tr><td class="dpt-label-bg">Gender</td><td><?php echo esc_html( $student->gender ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Guardian Name</td><td style="font-weight: 600;"><?php echo esc_html( $student->guardian_name ); ?></td></tr>
                            <tr><td class="dpt-label-bg">Guardian Phone</td><td><?php echo esc_html( $student->guardian_phone ); ?></td></tr>
                        </table>
                    </div>
                </div>
                
                <div style="margin-top: 16px;">
                    <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 6px;">স্থায়ী ও বর্তমান ঠিকানা:</strong>
                    <div style="padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; color: #334155; font-size: 14px; line-height: 1.5;">
                        <?php echo ! empty( $student->address ) ? nl2br( esc_html( $student->address ) ) : 'কোনো ঠিকানা যুক্ত করা হয়নি।'; ?>
                    </div>
                </div>
            </div>

            <!-- ২. পরীক্ষার ফলাফল ট্যাব -->
            <div id="dpt-results-tab" class="dpt-tab-content-block" style="display: none;">
                <h4 style="margin: 0 0 14px 0; color: #006a4e; font-size: 16px; font-weight: 700;">অ্যাকাডেমিক ফলাফল শিট</h4>
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
                            <td><span style="background: #e2e8f0; color: #334155; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;"><?php echo esc_html( $res->grade ); ?></span></td>
                            <td><strong style="color: #2563eb;"><?php echo esc_html( $res->gpa ); ?></strong></td>
                        </tr>
                        <?php endforeach; else : ?>
                        <tr><td colspan="6" style="text-align: center; color: #64748b; padding: 20px 0;">এই শিক্ষার্থীর কোনো পরীক্ষার ফলাফল পাওয়া যায়নি।</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ৩. ফি ও পেমেন্ট হিস্ট্রি ট্যাব -->
            <div id="dpt-payments-tab" class="dpt-tab-content-block" style="display: none;">
                <h4 style="margin: 0 0 14px 0; color: #006a4e; font-size: 16px; font-weight: 700;">ফি সংগ্রহ ও লেজার ইনভয়েস</h4>
                <table class="dpt-data-responsive-table">
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Month/Year</th>
                            <th>Fee Type</th>
                            <th>Net Payable</th>
                            <th>Paid Amount</th>
                            <th>Due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $fee_ledgers ) ) : foreach ( $fee_ledgers as $fee ) : 
                            $pay_status = strtolower( $fee->payment_status );
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
                            <td style="color: #dc2626;"><?php echo esc_html( $fee->due_amount ); ?> TK</td>
                            <td>
                                <span class="dpt-badge-status <?php echo esc_attr( $status_class ); ?>">
                                    <?php echo esc_html( $fee->payment_status ); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; else : ?>
                        <tr><td colspan="7" style="text-align: center; color: #64748b; padding: 20px 0;">ফি আদায়ের কোনো আর্থিক রেকর্ড পাওয়া যায়নি।</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- Vanilla Client-Side Fast Switching Engine -->
    <script type="text/javascript">
    function educoreSwitchProfileTab(evt, tabId) {
        var i, tabcontent, tablinks;
        
        // Hide all target layers
        tabcontent = document.getElementsByClassName("dpt-tab-content-block");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }

        // Deactivate old pointer links
        tablinks = document.getElementById("profileTabs") ? document.getElementById("profileTabs").getElementsByClassName("nav-link") : document.getElementsByClassName("nav-link");
        // Fallback scope check for standard class lists
        var fallbackLinks = evt.currentTarget.parentNode.getElementsByClassName("nav-link");
        for (i = 0; i < fallbackLinks.length; i++) {
            fallbackLinks[i].classList.remove("active");
        }

        // Show targets and assign layout badges
        document.getElementById(tabId).style.display = "block";
        evt.currentTarget.classList.add("active");
    }
    </script>
    <?php
}