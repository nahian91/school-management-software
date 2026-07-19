<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_student_profile_view() {
    global $wpdb;
    $student_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    if ( ! $student_id ) return;

    $table_name = $wpdb->prefix . 'sms_students';
    $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $student_id ) );

    if ( ! $student ) {
        echo '<div class="alert alert-danger border-0 shadow-sm">Student record not found.</div>';
        return;
    }

    // ডেটাবেজ থেকে শিক্ষার্থীর রেজাল্ট এবং ফি হিস্ট্রি তুলে আনা
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
    $edit_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=edit&id=' . $student->id );
    
    $first_letter = mb_substr( $student->full_name, 0, 1, 'utf-8' );
    ?>
    
    <!-- CSS ফর সাব-ট্যাব এবং ক্লিন ডিজাইন -->
    <style>
        .dnt-profile-tabs .nav-link { color: #64748b; font-weight: 600; border: none; border-bottom: 2px solid transparent; }
        .dnt-profile-tabs .nav-link.active { color: #006a4e !important; background: transparent !important; border-color: #006a4e !important; }
        .table-responsive { font-size: 0.95rem; }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2" style="border-radius: 6px; font-weight: 600;">
            <svg style="width:16px; height:16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Directory
        </a>
        <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2" style="background-color: #006a4e; border: none; border-radius: 6px; font-weight: 600; padding: 6px 16px;">
            <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; fill: currentColor;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
            Edit Profile
        </a>
    </div>

    <!-- প্রোফাইল কার্ড মূল কন্টেইনার -->
    <div class="bg-white p-4 rounded shadow-sm border mb-4">
        <div class="row align-items-center">
            <div class="col-md-2 text-center text-md-start mb-3 mb-md-0">
                <?php if ( ! empty( $student->photo_url ) ) : ?>
                    <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="Student Photo" class="rounded-circle border shadow-sm" style="width: 110px; height: 110px; object-fit: cover;">
                <?php else : ?>
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center border shadow-sm" style="width: 110px; height: 110px; background-color: #e6f3ef; color: #006a4e; font-weight: 700; font-size: 2.5rem;">
                        <?php echo esc_html( $first_letter ); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-7 text-center text-md-start">
                <h3 class="mb-1 fw-bold text-dark"><?php echo esc_html( $student->full_name ); ?></h3>
                <p class="text-muted mb-2">ID: <strong><?php echo esc_html( $student->student_id ); ?></strong> | Class: <strong><?php echo esc_html( $student->class_name ); ?></strong> | Roll: <strong><?php echo esc_html( $student->roll_no ); ?></strong></p>
                <span class="badge <?php echo $student->status === 'Active' ? 'bg-success' : 'bg-danger'; ?> px-3 py-1.5 rounded-pill">
                    <?php echo esc_html( $student->status ); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ট্যাব নেভিগেশন মেনুবার -->
    <ul class="nav nav-tabs dnt-profile-tabs mb-4" id="profileTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">ব্যক্তিগত তথ্য</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="results-tab" data-bs-toggle="tab" data-bs-target="#results" type="button" role="tab">পরীক্ষার ফলাফল</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">ফি ও পেমেন্ট হিস্ট্রি</button>
        </li>
    </ul>

    <!-- ট্যাব কন্টেন্ট এরিয়া -->
    <div class="tab-content bg-white p-4 rounded shadow-sm border" id="profileTabsContent">
        
        <!-- ১. ব্যক্তিগত তথ্য ট্যাব -->
        <div class="tab-pane fade show active" id="details" role="tabpanel">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <h5 class="fw-bold text-dark mb-3" style="color:#006a4e !important;">অ্যাকাডেমিক রেকর্ড</h5>
                    <table class="table table-bordered table-sm">
                        <tr><td class="bg-light text-muted style='width:35%'">Class</td><td class="fw-semibold"><?php echo esc_html($student->class_name); ?></td></tr>
                        <tr><td class="bg-light text-muted">Section/Group</td><td class="fw-semibold"><?php echo $student->section_name ? esc_html($student->section_name) : '—'; ?></td></tr>
                        <tr><td class="bg-light text-muted">Roll No</td><td class="fw-semibold"><?php echo esc_html($student->roll_no); ?></td></tr>
                        <tr><td class="bg-light text-muted">Admission Date</td><td><?php echo date("d M Y", strtotime($student->admission_date)); ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6 mb-4">
                    <h5 class="fw-bold text-dark mb-3" style="color:#006a4e !important;">ব্যক্তিগত ও অভিভাবকের তথ্য</h5>
                    <table class="table table-bordered table-sm">
                        <tr><td class="bg-light text-muted style='width:35%'">Date of Birth</td><td><?php echo date("d M Y", strtotime($student->dob)); ?></td></tr>
                        <tr><td class="bg-light text-muted">Gender</td><td><?php echo esc_html($student->gender); ?></td></tr>
                        <tr><td class="bg-light text-muted">Guardian Name</td><td class="fw-semibold"><?php echo esc_html($student->guardian_name); ?></td></tr>
                        <tr><td class="bg-light text-muted">Guardian Phone</td><td><?php echo esc_html($student->guardian_phone); ?></td></tr>
                    </table>
                </div>
                <div class="col-sm-12">
                    <strong class="text-secondary small d-block mb-1">স্থায়ী ও বর্তমান ঠিকানা:</strong>
                    <div class="p-3 bg-light rounded text-dark"><?php echo !empty($student->address) ? nl2br(esc_html($student->address)) : 'কোনো ঠিকানা যুক্ত করা হয়নি।'; ?></div>
                </div>
            </div>
        </div>

        <!-- ২. পরীক্ষার ফলাফল ট্যাব -->
        <div class="tab-pane fade" id="results" role="tabpanel">
            <h5 class="fw-bold text-dark mb-3" style="color:#006a4e !important;">অ্যাকাডেমিক ফলাফল শিট</h5>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle">
                    <thead class="table-light">
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
                            <td><strong><?php echo esc_html( $res->exam_name ); ?></strong></td>
                            <td><?php echo esc_html( $res->subject_name ); ?></td>
                            <td><?php echo esc_html( $res->total_marks ); ?></td>
                            <td><span class="fw-bold text-dark"><?php echo esc_html( $res->obtained_marks ); ?></span></td>
                            <td><span class="badge bg-secondary"><?php echo esc_html( $res->grade ); ?></span></td>
                            <td><strong class="text-primary"><?php echo esc_html( $res->gpa ); ?></strong></td>
                        </tr>
                        <?php endforeach; else : ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">এই শিক্ষার্থীর কোনো পরীক্ষার ফলাফল পাওয়া যায়নি।</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ৩. ফি ও পement হিস্ট্রি ট্যাব -->
        <div class="tab-pane fade" id="payments" role="tabpanel">
            <h5 class="fw-bold text-dark mb-3" style="color:#006a4e !important;">ফি সংগ্রহ ও লেজার ইনভয়েস</h5>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle">
                    <thead class="table-light">
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
                        <?php if ( ! empty( $fee_ledgers ) ) : foreach ( $fee_ledgers as $fee ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $fee->invoice_id ); ?></code></td>
                            <td><?php echo esc_html( $fee->fee_month . ' / ' . $fee->fee_year ); ?></td>
                            <td><?php echo esc_html( $fee->fee_type ); ?></td>
                            <td><?php echo esc_html( $fee->net_payable ); ?> TK</td>
                            <td class="text-success fw-bold"><?php echo esc_html( $fee->paid_amount ); ?> TK</td>
                            <td class="text-danger"><?php echo esc_html( $fee->due_amount ); ?> TK</td>
                            <td>
                                <span class="badge <?php echo $fee->payment_status === 'Paid' ? 'bg-success' : ($fee->payment_status === 'Partial' ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                    <?php echo esc_html( $fee->payment_status ); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; else : ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">ফি আদায়ের কোনো আর্থিক রেকর্ড পাওয়া যায়নি।</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    <?php
}
?>