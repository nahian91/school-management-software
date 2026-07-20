<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access lockdown
}

/**
 * High-End Staff Profile Bento Visualizer
 */
function educore_staff_profile_view() {
    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    // 1. Security & Permission Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to view staff profiles.', 'educore' ) );
    }

    $staff_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $staff    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_staff} WHERE id = %d", $staff_id ) );

    if ( ! $staff ) {
        echo '<div class="alert alert-danger my-4">' . esc_html__( 'Staff record not found.', 'educore' ) . '</div>';
        return;
    }

    // Processing variables
    $back_url  = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=list' );
    $edit_url  = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=edit&id=' . $staff_id );
    $is_active = strtolower( trim( $staff->status ?? '' ) ) === 'active';

    // Date Format Handling
    $dob          = ( ! empty( $staff->dob ) && $staff->dob !== '1970-01-01' ) ? date_i18n( 'd M Y', strtotime( $staff->dob ) ) : '—';
    $joining_date = ( ! empty( $staff->joining_date ) && $staff->joining_date !== '1970-01-01' ) ? date_i18n( 'd M Y', strtotime( $staff->joining_date ) ) : '—';
    $salary       = number_format( (float) ( $staff->salary ?? 0 ), 2 );
    ?>

    <style>
        .educore-profile-container {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            height: 100%;
        }
        .bento-header-card {
            background: linear-gradient(135deg, #006a4e 0%, #004d38 100%);
            color: #ffffff;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 10px 15px -3px rgba(0, 106, 78, 0.2);
        }
        .profile-avatar-img {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
        }
        .profile-avatar-placeholder {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: #ffffff;
            color: #006a4e;
            font-size: 2.5rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid rgba(255, 255, 255, 0.3);
        }
        .info-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .info-value {
            font-size: 0.95rem;
            color: #0f172a;
            font-weight: 600;
        }
        .social-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            background: #f1f5f9;
            color: #334155;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .social-pill:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        @media print {
            .no-print, .btn, .dpt-staff-nav-root { display: none !important; }
            .bento-header-card { background: #006a4e !important; color: #fff !important; }
        }
    </style>

    <div class="educore-profile-container my-3">
        
        <!-- Navigation Controls -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-outline-secondary btn-sm fw-bold">
                &larr; <?php esc_html_e( 'Back to Directory', 'educore' ); ?>
            </a>
            <div>
                <button onclick="window.print();" class="btn btn-light btn-sm border me-2 fw-bold">
                    <span class="dashicons dashicons-printer" style="vertical-align:middle;"></span> <?php esc_html_e( 'Print Profile', 'educore' ); ?>
                </button>
                <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-primary btn-sm fw-bold" style="background-color: #2563eb;">
                    <span class="dashicons dashicons-edit" style="vertical-align:middle;"></span> <?php esc_html_e( 'Edit Profile', 'educore' ); ?>
                </a>
            </div>
        </div>

        <!-- Header Card -->
        <div class="bento-header-card mb-4">
            <div class="row align-items-center">
                <div class="col-md-auto text-center text-md-start mb-3 mb-md-0">
                    <?php if ( ! empty( $staff->profile_image ) ) : ?>
                        <img src="<?php echo esc_url( $staff->profile_image ); ?>" alt="<?php echo esc_attr( $staff->full_name ); ?>" class="profile-avatar-img">
                    <?php else : 
                        $first_letter = mb_substr( $staff->full_name ?? 'S', 0, 1, 'UTF-8' );
                    ?>
                        <div class="profile-avatar-placeholder mx-auto">
                            <?php echo esc_html( mb_strtoupper( $first_letter, 'UTF-8' ) ); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md text-center text-md-start">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
                        <h2 class="m-0 fw-bold text-white"><?php echo esc_html( $staff->full_name ); ?></h2>
                        <span class="badge <?php echo $is_active ? 'bg-success' : 'bg-danger'; ?> px-2 py-1 fs-6">
                            <?php echo esc_html( $staff->status ); ?>
                        </span>
                    </div>

                    <?php if ( ! empty( $staff->name_bn ) ) : ?>
                        <h5 class="fw-normal text-white-50 mb-2"><?php echo esc_html( $staff->name_bn ); ?></h5>
                    <?php endif; ?>

                    <p class="m-0 text-white-50 font-monospace">
                        <span class="badge bg-light text-dark me-2"><?php echo esc_html( $staff->designation ); ?></span>
                        <span class="me-3"><span class="dashicons dashicons-category"></span> <?php echo esc_html( $staff->staff_type ); ?></span>
                        <?php if ( ! empty( $staff->index_no ) ) : ?>
                            <span><span class="dashicons dashicons-id"></span> MPO Index: <strong><?php echo esc_html( $staff->index_no ); ?></strong></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Bento Grid Layout -->
        <div class="row g-4">
            
            <!-- Left Column: Primary Details -->
            <div class="col-lg-8">
                <div class="row g-4">
                    
                    <!-- General Personal Information -->
                    <div class="col-12">
                        <div class="bento-card">
                            <h5 class="text-success fw-bold border-bottom pb-2 mb-3">
                                <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'Personal & Identification Details', 'educore' ); ?>
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-label"><?php esc_html_e( 'National ID (NID)', 'educore' ); ?></div>
                                    <div class="info-value"><?php echo esc_html( $staff->nid_no ?: '—' ); ?></div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-label"><?php esc_html_e( 'Date of Birth', 'educore' ); ?></div>
                                    <div class="info-value"><?php echo esc_html( $dob ); ?></div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-label"><?php esc_html_e( 'Gender', 'educore' ); ?></div>
                                    <div class="info-value"><?php echo esc_html( $staff->gender ); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-label"><?php esc_html_e( "Father's Name", 'educore' ); ?></div>
                                    <div class="info-value"><?php echo esc_html( $staff->father_name ?: '—' ); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-label"><?php esc_html_e( "Mother's Name", 'educore' ); ?></div>
                                    <div class="info-value"><?php echo esc_html( $staff->mother_name ?: '—' ); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-label"><?php esc_html_e( 'Blood Group', 'educore' ); ?></div>
                                    <div class="info-value text-danger fw-bold"><?php echo esc_html( $staff->blood_group ?: '—' ); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-label"><?php esc_html_e( 'Quota Category', 'educore' ); ?></div>
                                    <div class="info-value"><?php echo esc_html( $staff->quota_type ); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-label"><?php esc_html_e( 'Linked WP User', 'educore' ); ?></div>
                                    <div class="info-value"><?php echo $staff->wp_user_id ? '#' . absint( $staff->wp_user_id ) : 'Unlinked'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employment & Academic Qualifications -->
                    <div class="col-12">
                        <div class="bento-card">
                            <h5 class="text-success fw-bold border-bottom pb-2 mb-3">
                                <span class="dashicons dashicons-welcome-learn-more"></span> <?php esc_html_e( 'Academic & Service Portfolio', 'educore' ); ?>
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-label"><?php esc_html_e( 'Subject Expertise', 'educore' ); ?></div>
                                    <div class="info-value"><?php echo esc_html( $staff->subject_expert ?: '—' ); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-label"><?php esc_html_e( 'Highest Qualification', 'educore' ); ?></div>
                                    <div class="info-value"><?php echo esc_html( $staff->highest_degree ?: '—' ); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-label"><?php esc_html_e( 'Joining Date', 'educore' ); ?></div>
                                    <div class="info-value"><?php echo esc_html( $joining_date ); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-label"><?php esc_html_e( 'Pay Scale Grade', 'educore' ); ?></div>
                                    <div class="info-value"><?php echo esc_html( $staff->pay_grade ?: '—' ); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-label"><?php esc_html_e( 'Gross Monthly Salary', 'educore' ); ?></div>
                                    <div class="info-value text-success fw-bold">৳<?php echo esc_html( $salary ); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Addresses -->
                    <div class="col-12">
                        <div class="bento-card">
                            <h5 class="text-success fw-bold border-bottom pb-2 mb-3">
                                <span class="dashicons dashicons-location-alt"></span> <?php esc_html_e( 'Address Records', 'educore' ); ?>
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-label"><?php esc_html_e( 'Present Address', 'educore' ); ?></div>
                                    <div class="info-value text-secondary"><?php echo nl2br( esc_html( $staff->address ?: '—' ) ); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-label"><?php esc_html_e( 'Permanent Address', 'educore' ); ?></div>
                                    <div class="info-value text-secondary"><?php echo nl2br( esc_html( $staff->permanent_address ?: '—' ) ); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Right Column: Contact, Banking & Emergency -->
            <div class="col-lg-4">
                <div class="row g-4">
                    
                    <!-- Direct Contacts -->
                    <div class="col-12">
                        <div class="bento-card">
                            <h5 class="text-success fw-bold border-bottom pb-2 mb-3">
                                <span class="dashicons dashicons-phone"></span> <?php esc_html_e( 'Contact Info', 'educore' ); ?>
                            </h5>
                            <div class="mb-3">
                                <div class="info-label"><?php esc_html_e( 'Mobile Phone', 'educore' ); ?></div>
                                <div class="info-value">
                                    <a href="tel:<?php echo esc_attr( $staff->phone ); ?>" class="text-decoration-none text-dark">
                                        <?php echo esc_html( $staff->phone ); ?>
                                    </a>
                                </div>
                            </div>
                            <?php if ( ! empty( $staff->whatsapp_no ) ) : ?>
                                <div class="mb-3">
                                    <div class="info-label"><?php esc_html_e( 'WhatsApp Number', 'educore' ); ?></div>
                                    <div class="info-value">
                                        <a href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $staff->whatsapp_no ) ); ?>" target="_blank" class="text-decoration-none text-success">
                                            <?php echo esc_html( $staff->whatsapp_no ); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <div class="info-label"><?php esc_html_e( 'Email Address', 'educore' ); ?></div>
                                <div class="info-value">
                                    <?php if ( ! empty( $staff->email ) ) : ?>
                                        <a href="mailto:<?php echo esc_attr( $staff->email ); ?>" class="text-decoration-none">
                                            <?php echo esc_html( $staff->email ); ?>
                                        </a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Banking Details -->
                    <div class="col-12">
                        <div class="bento-card">
                            <h5 class="text-success fw-bold border-bottom pb-2 mb-3">
                                <span class="dashicons dashicons-bank"></span> <?php esc_html_e( 'EFT / Payroll Banking', 'educore' ); ?>
                            </h5>
                            <div class="mb-2">
                                <div class="info-label"><?php esc_html_e( 'Bank Name', 'educore' ); ?></div>
                                <div class="info-value"><?php echo esc_html( $staff->bank_name ?: '—' ); ?></div>
                            </div>
                            <div class="mb-2">
                                <div class="info-label"><?php esc_html_e( 'Account Number', 'educore' ); ?></div>
                                <div class="info-value font-monospace"><?php echo esc_html( $staff->bank_acc_no ?: '—' ); ?></div>
                            </div>
                            <div>
                                <div class="info-label"><?php esc_html_e( 'Routing Number', 'educore' ); ?></div>
                                <div class="info-value font-monospace"><?php echo esc_html( $staff->bank_routing ?: '—' ); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="col-12">
                        <div class="bento-card border-warning">
                            <h5 class="text-danger fw-bold border-bottom pb-2 mb-3">
                                <span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Emergency Contact', 'educore' ); ?>
                            </h5>
                            <div class="mb-2">
                                <div class="info-label"><?php esc_html_e( 'Contact Person', 'educore' ); ?></div>
                                <div class="info-value"><?php echo esc_html( $staff->emergency_name ?: '—' ); ?></div>
                            </div>
                            <div class="mb-2">
                                <div class="info-label"><?php esc_html_e( 'Relationship', 'educore' ); ?></div>
                                <div class="info-value"><?php echo esc_html( $staff->emergency_relation ?: '—' ); ?></div>
                            </div>
                            <div>
                                <div class="info-label"><?php esc_html_e( 'Phone Number', 'educore' ); ?></div>
                                <div class="info-value">
                                    <?php if ( ! empty( $staff->emergency_phone ) ) : ?>
                                        <a href="tel:<?php echo esc_attr( $staff->emergency_phone ); ?>" class="text-decoration-none text-danger fw-bold">
                                            <?php echo esc_html( $staff->emergency_phone ); ?>
                                        </a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Networks -->
                    <?php if ( ! empty( $staff->linkedin_url ) || ! empty( $staff->facebook_url ) || ! empty( $staff->website_url ) ) : ?>
                        <div class="col-12 no-print">
                            <div class="bento-card">
                                <h5 class="text-success fw-bold border-bottom pb-2 mb-3">
                                    <span class="dashicons dashicons-share"></span> <?php esc_html_e( 'Profiles & Links', 'educore' ); ?>
                                </h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if ( ! empty( $staff->linkedin_url ) ) : ?>
                                        <a href="<?php echo esc_url( $staff->linkedin_url ); ?>" target="_blank" class="social-pill">
                                            LinkedIn
                                        </a>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $staff->facebook_url ) ) : ?>
                                        <a href="<?php echo esc_url( $staff->facebook_url ); ?>" target="_blank" class="social-pill">
                                            Facebook
                                        </a>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $staff->website_url ) ) : ?>
                                        <a href="<?php echo esc_url( $staff->website_url ); ?>" target="_blank" class="social-pill">
                                            Website
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
    <?php
}