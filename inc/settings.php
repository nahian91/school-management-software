<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Premium Institutional Settings & Configuration Module
 * Theme Aesthetic: Elite Neo-Bento & Kinetic Glassmorphism Layout
 * Custom Prefixes Applied: dpt-, afdp-
 */
function educore_settings_tab() {
    // Enqueue Media Assets for Native WP Uploader
    wp_enqueue_media();

    $settings_updated = false;

    // Handle Form Submission
    if ( isset( $_POST['educore_save_settings'] ) && wp_verify_nonce( $_POST['educore_settings_nonce'], 'save_settings_action' ) ) {
        
        $settings = array(
            // Basic Identity & Institutional Meta
            'educore_school_name'         => sanitize_text_field( $_POST['school_name'] ),
            'educore_school_tagline'      => sanitize_text_field( $_POST['school_tagline'] ),
            'educore_eiin_number'         => sanitize_text_field( $_POST['eiin_number'] ),
            'educore_institute_code'      => sanitize_text_field( $_POST['institute_code'] ),
            'educore_estd_year'           => sanitize_text_field( $_POST['estd_year'] ),
            'educore_school_type'         => sanitize_text_field( $_POST['school_type'] ),

            // Media & Asset URIs
            'educore_school_logo'         => esc_url_raw( $_POST['school_logo'] ),
            'educore_principal_sig'       => esc_url_raw( $_POST['principal_sig'] ),

            // Academic Recognition & Licensing
            'educore_board_affiliation'   => sanitize_text_field( $_POST['board_affiliation'] ),
            'educore_accreditation'       => sanitize_text_field( $_POST['accreditation'] ),
            'educore_approval_no'         => sanitize_text_field( $_POST['approval_no'] ),
            'educore_regulatory_body'     => sanitize_text_field( $_POST['regulatory_body'] ),

            // Institutional Infrastructure
            'educore_campus_type'         => sanitize_text_field( $_POST['campus_type'] ),
            'educore_campus_area'         => sanitize_text_field( $_POST['campus_area'] ),
            'educore_total_classrooms'    => sanitize_text_field( $_POST['total_classrooms'] ),
            'educore_has_library'         => sanitize_text_field( $_POST['has_library'] ),
            'educore_has_lab'             => sanitize_text_field( $_POST['has_lab'] ),

            // Financial Credentials Baseline
            'educore_bank_name'           => sanitize_text_field( $_POST['bank_name'] ),
            'educore_bank_account_no'     => sanitize_text_field( $_POST['bank_account_no'] ),
            'educore_bank_branch'         => sanitize_text_field( $_POST['bank_branch'] ),
            'educore_bank_routing'        => sanitize_text_field( $_POST['bank_routing'] ),
        );

        foreach ( $settings as $key => $value ) {
            update_option( $key, $value );
        }

        $settings_updated = true;
        
        if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
            IFSEdu_School_Management_System::log_activity( "Updated global system settings & institutional profile." );
        }
    }

    // Retrieve existing settings
    $school_name         = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline      = get_option( 'educore_school_tagline', '' );
    $eiin_number         = get_option( 'educore_eiin_number', '' );
    $institute_code      = get_option( 'educore_institute_code', '' );
    $estd_year           = get_option( 'educore_estd_year', '' );
    $school_type         = get_option( 'educore_school_type', 'co_ed' );

    $school_logo         = get_option( 'educore_school_logo', '' );
    $principal_sig       = get_option( 'educore_principal_sig', '' );

    $board_affiliation   = get_option( 'educore_board_affiliation', '' );
    $accreditation       = get_option( 'educore_accreditation', 'A+' );
    $approval_no         = get_option( 'educore_approval_no', '' );
    $regulatory_body     = get_option( 'educore_regulatory_body', '' );

    $campus_type         = get_option( 'educore_campus_type', 'own' );
    $campus_area         = get_option( 'educore_campus_area', '' );
    $total_classrooms    = get_option( 'educore_total_classrooms', '' );
    $has_library         = get_option( 'educore_has_library', 'yes' );
    $has_lab             = get_option( 'educore_has_lab', 'yes' );

    $bank_name           = get_option( 'educore_bank_name', '' );
    $bank_account_no     = get_option( 'educore_bank_account_no', '' );
    $bank_branch         = get_option( 'educore_bank_branch', '' );
    $bank_routing        = get_option( 'educore_bank_routing', '' );
    ?>

    <style>
        /* ==========================================================================
           CORE NEO-BENTO MATRIX & KINETIC GLASSMORPHISM UI ENGINE
           ========================================================================== */
        .dpt-settings-root {
            margin: 20px 20px 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #0f172a;
        }

        /* Top Header Navigation Banner */
        .afdp-header-frame {
            background: linear-gradient(135deg, #006a4e 0%, #004d39 100%);
            padding: 28px 32px;
            border-radius: 16px;
            margin-bottom: 24px;
            color: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(0, 106, 78, 0.25);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .afdp-header-frame::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .afdp-header-content h2 {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 6px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.5px;
        }

        .afdp-header-content h2 .dashicons {
            font-size: 28px;
            width: 28px;
            height: 28px;
            color: #a7f3d0;
        }

        .afdp-header-content p {
            margin: 0;
            font-size: 13.5px;
            color: #d1fae5;
            font-weight: 500;
            opacity: 0.9;
        }

        /* Dynamic Flash Notification */
        .afdp-alert-node {
            border-radius: 12px;
            padding: 16px 20px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: afdpFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes afdpFadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .afdp-alert-success { 
            background: #ecfdf5; 
            border: 1px solid #a7f3d0; 
            color: #065f46; 
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.08);
        }

        /* Full Width Container */
        .dpt-bento-container {
            max-width: 100%;
        }

        /* Elegant Bento Block / Card Framework */
        .dpt-bento-block {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .dpt-bento-block:hover {
            border-color: #cbd5e1;
            box-shadow: 0 10px 30px -4px rgba(0, 0, 0, 0.05);
        }

        .dpt-bento-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 24px 0;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dpt-bento-title-text {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dpt-bento-title .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
        }

        .dpt-text-emerald { color: #006a4e; }
        .dpt-text-purple { color: #8b5cf6; }
        .dpt-text-blue { color: #2563eb; }
        .dpt-text-amber { color: #d97706; }

        /* Structural Field Layout Matrices */
        .dpt-row-matrix {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .dpt-row-matrix > div {
            flex: 1;
        }

        @media (max-width: 640px) {
            .dpt-row-matrix {
                flex-direction: column;
                gap: 20px;
            }
        }

        .dpt-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 8px;
            letter-spacing: -0.1px;
        }

        .dpt-label span {
            color: #ef4444;
        }

        .dpt-input-text, 
        .dpt-select {
            width: 100%;
            height: 44px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0 16px;
            font-size: 14px;
            color: #0f172a;
            background-color: #f8fafc;
            box-shadow: none;
            transition: all 0.2s ease-in-out;
            box-sizing: border-box;
        }

        .dpt-select {
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%2364748b" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
        }

        .dpt-input-text:focus, 
        .dpt-select:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(0, 106, 78, 0.1);
            outline: none;
        }

        .dpt-help-text {
            display: block;
            font-size: 12px;
            color: #64748b;
            margin-top: 6px;
            font-weight: 500;
        }

        /* WP Custom Media Uploader UI Component */
        .dpt-logo-uploader-card {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
        }

        .dpt-logo-uploader-card:hover {
            border-color: #006a4e;
            background: #f0fdf4;
        }

        .dpt-logo-preview-box {
            width: 90px;
            height: 90px;
            border-radius: 12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            margin: 0 auto 14px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .dpt-logo-preview-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .dpt-logo-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .dpt-btn-upload {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #334155;
            font-weight: 600;
            font-size: 12.5px;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .dpt-btn-upload:hover {
            border-color: #006a4e;
            color: #006a4e;
            background: #ffffff;
        }

        .dpt-btn-remove {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            font-weight: 600;
            font-size: 12.5px;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .dpt-btn-remove:hover {
            background: #fee2e2;
        }

        /* Form Submissions Control Trigger */
        .dpt-submit-wrapper {
            margin-top: 10px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: flex-end;
        }

        .dpt-btn-submit-trigger {
            height: 48px;
            background: linear-gradient(135deg, #006a4e 0%, #00523c 100%);
            border: none;
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
            border-radius: 10px;
            padding: 0 36px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 6px 18px rgba(0, 106, 78, 0.25);
        }

        .dpt-btn-submit-trigger:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(0, 106, 78, 0.35);
            background: linear-gradient(135deg, #007a5a 0%, #005a42 100%);
        }

        .dpt-btn-submit-trigger:active {
            transform: translateY(0);
        }
    </style>

    <div class="dpt-settings-root">
        
        <!-- Header Frame -->
        <div class="afdp-header-frame">
            <div class="afdp-header-content">
                <h2>
                    <span class="dashicons dashicons-admin-settings"></span> Institutional Profile & Setup
                </h2>
                <p>Configure primary school identity, official accreditations, campus infrastructure, and financial information.</p>
            </div>
        </div>

        <!-- Dynamic Flash Notification -->
        <?php if ( $settings_updated ) : ?>
            <div class="afdp-alert-node afdp-alert-success">
                <span class="dashicons dashicons-yes-alt"></span> System settings and institutional profile updated successfully.
            </div>
        <?php endif; ?>

        <!-- Full Width Bento Container -->
        <div class="dpt-bento-container">
            <form method="POST" action="">
                <?php wp_nonce_field( 'save_settings_action', 'educore_settings_nonce' ); ?>

                <!-- Block 1: Identity & Crest -->
                <div class="dpt-bento-block">
                    <div class="dpt-bento-title">
                        <div class="dpt-bento-title-text dpt-text-emerald">
                            <span class="dashicons dashicons-bank"></span> Institutional Identity & Crest
                        </div>
                    </div>

                    <!-- School Name & Tagline -->
                    <div class="dpt-row-matrix">
                        <div>
                            <label class="dpt-label">Official Institution Name <span>*</span></label>
                            <input type="text" name="school_name" class="dpt-input-text" value="<?php echo esc_attr( $school_name ); ?>" required>
                            <small class="dpt-help-text">Appears on transcripts, invoices, ID cards, and official reports.</small>
                        </div>
                        <div>
                            <label class="dpt-label">Motto / Tagline</label>
                            <input type="text" name="school_tagline" class="dpt-input-text" value="<?php echo esc_attr( $school_tagline ); ?>" placeholder="e.g. Education for Enlightenment">
                        </div>
                    </div>

                    <!-- EIIN & Institute Code -->
                    <div class="dpt-row-matrix">
                        <div>
                            <label class="dpt-label">EIIN Number</label>
                            <input type="text" name="eiin_number" class="dpt-input-text" value="<?php echo esc_attr( $eiin_number ); ?>" placeholder="e.g. 130892">
                        </div>
                        <div>
                            <label class="dpt-label">Institute / Board Code</label>
                            <input type="text" name="institute_code" class="dpt-input-text" value="<?php echo esc_attr( $institute_code ); ?>" placeholder="e.g. 2011">
                        </div>
                    </div>

                    <!-- Established Year & Type -->
                    <div class="dpt-row-matrix">
                        <div>
                            <label class="dpt-label">Established Year</label>
                            <input type="text" name="estd_year" class="dpt-input-text" value="<?php echo esc_attr( $estd_year ); ?>" placeholder="e.g. 1995">
                        </div>
                        <div>
                            <label class="dpt-label">Institution Category</label>
                            <select name="school_type" class="dpt-select">
                                <option value="co_ed" <?php selected( $school_type, 'co_ed' ); ?>>Co-Educational</option>
                                <option value="boys" <?php selected( $school_type, 'boys' ); ?>>Boys School</option>
                                <option value="girls" <?php selected( $school_type, 'girls' ); ?>>Girls School</option>
                            </select>
                        </div>
                    </div>

                    <!-- School Crest & Principal Signature Uploader Matrix -->
                    <div class="dpt-row-matrix" style="margin-bottom:0;">
                        <div>
                            <label class="dpt-label">Institutional Crest / Logo</label>
                            <div class="dpt-logo-uploader-card">
                                <div class="dpt-logo-preview-box" id="dpt-logo-preview">
                                    <?php if ( ! empty( $school_logo ) ) : ?>
                                        <img src="<?php echo esc_url( $school_logo ); ?>" alt="School Logo">
                                    <?php else : ?>
                                        <span class="dashicons dashicons-format-image" style="font-size:32px; width:32px; height:32px; color:#94a3b8;"></span>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="school_logo" id="dpt_school_logo_val" value="<?php echo esc_url( $school_logo ); ?>">
                                <div class="dpt-logo-actions">
                                    <button type="button" class="dpt-btn-upload" id="dpt_upload_logo_btn">
                                        <span class="dashicons dashicons-upload"></span> Select Logo
                                    </button>
                                    <button type="button" class="dpt-btn-remove" id="dpt_remove_logo_btn" style="<?php echo empty( $school_logo ) ? 'display:none;' : ''; ?>">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="dpt-label">Principal / Authority Signature</label>
                            <div class="dpt-logo-uploader-card">
                                <div class="dpt-logo-preview-box" id="dpt-sig-preview">
                                    <?php if ( ! empty( $principal_sig ) ) : ?>
                                        <img src="<?php echo esc_url( $principal_sig ); ?>" alt="Principal Signature">
                                    <?php else : ?>
                                        <span class="dashicons dashicons-edit" style="font-size:32px; width:32px; height:32px; color:#94a3b8;"></span>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="principal_sig" id="dpt_principal_sig_val" value="<?php echo esc_url( $principal_sig ); ?>">
                                <div class="dpt-logo-actions">
                                    <button type="button" class="dpt-btn-upload" id="dpt_upload_sig_btn">
                                        <span class="dashicons dashicons-upload"></span> Select Signature
                                    </button>
                                    <button type="button" class="dpt-btn-remove" id="dpt_remove_sig_btn" style="<?php echo empty( $principal_sig ) ? 'display:none;' : ''; ?>">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Block 2: Academic Recognition & Licensing -->
                <div class="dpt-bento-block">
                    <div class="dpt-bento-title">
                        <div class="dpt-bento-title-text dpt-text-purple">
                            <span class="dashicons dashicons-awards"></span> Academic Recognition & Licensing
                        </div>
                    </div>

                    <div class="dpt-row-matrix">
                        <div>
                            <label class="dpt-label">Board / University Affiliation</label>
                            <input type="text" name="board_affiliation" class="dpt-input-text" value="<?php echo esc_attr( $board_affiliation ); ?>" placeholder="e.g. BISE Dhaka / Cambridge International">
                        </div>
                        <div>
                            <label class="dpt-label">Accreditation Grade / Status</label>
                            <input type="text" name="accreditation" class="dpt-input-text" value="<?php echo esc_attr( $accreditation ); ?>" placeholder="e.g. Grade A+ / ISO 9001 Certified">
                        </div>
                    </div>

                    <div class="dpt-row-matrix" style="margin-bottom:0;">
                        <div>
                            <label class="dpt-label">Govt. Approval / License No.</label>
                            <input type="text" name="approval_no" class="dpt-input-text" value="<?php echo esc_attr( $approval_no ); ?>" placeholder="e.g. MOE/REG/2026/8892">
                        </div>
                        <div>
                            <label class="dpt-label">Primary Regulatory Body</label>
                            <input type="text" name="regulatory_body" class="dpt-input-text" value="<?php echo esc_attr( $regulatory_body ); ?>" placeholder="e.g. Ministry of Education">
                        </div>
                    </div>
                </div>

                <!-- Block 3: Institutional Infrastructure Matrix -->
                <div class="dpt-bento-block">
                    <div class="dpt-bento-title">
                        <div class="dpt-bento-title-text dpt-text-blue">
                            <span class="dashicons dashicons-admin-multisite"></span> Institutional Infrastructure
                        </div>
                    </div>

                    <div class="dpt-row-matrix">
                        <div>
                            <label class="dpt-label">Campus Ownership Type</label>
                            <select name="campus_type" class="dpt-select">
                                <option value="own" <?php selected( $campus_type, 'own' ); ?>>Own Permanent Campus</option>
                                <option value="rented" <?php selected( $campus_type, 'rented' ); ?>>Rented Premises</option>
                                <option value="trust" <?php selected( $campus_type, 'trust' ); ?>>Trust / Lease Property</option>
                            </select>
                        </div>
                        <div>
                            <label class="dpt-label">Total Campus Area (Acres/Sq Ft)</label>
                            <input type="text" name="campus_area" class="dpt-input-text" value="<?php echo esc_attr( $campus_area ); ?>" placeholder="e.g. 3.5 Acres">
                        </div>
                        <div>
                            <label class="dpt-label">Total Active Classrooms</label>
                            <input type="number" name="total_classrooms" class="dpt-input-text" value="<?php echo esc_attr( $total_classrooms ); ?>" placeholder="e.g. 48">
                        </div>
                    </div>

                    <div class="dpt-row-matrix" style="margin-bottom:0;">
                        <div>
                            <label class="dpt-label">Central Library Facility</label>
                            <select name="has_library" class="dpt-select">
                                <option value="yes" <?php selected( $has_library, 'yes' ); ?>>Available (Digital & Physical)</option>
                                <option value="physical_only" <?php selected( $has_library, 'physical_only' ); ?>>Physical Library Only</option>
                                <option value="no" <?php selected( $has_library, 'no' ); ?>>Not Available</option>
                            </select>
                        </div>
                        <div>
                            <label class="dpt-label">Science & Computer Labs</label>
                            <select name="has_lab" class="dpt-select">
                                <option value="yes" <?php selected( $has_lab, 'yes' ); ?>>Fully Equipped Multi-Labs</option>
                                <option value="basic" <?php selected( $has_lab, 'basic' ); ?>>Basic Lab Facilities</option>
                                <option value="no" <?php selected( $has_lab, 'no' ); ?>>Not Available</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Block 4: Financial Credentials Baseline -->
                <div class="dpt-bento-block">
                    <div class="dpt-bento-title">
                        <div class="dpt-bento-title-text dpt-text-amber">
                            <span class="dashicons dashicons-money-alt"></span> Primary Financial & Bank Credentials
                        </div>
                    </div>

                    <div class="dpt-row-matrix">
                        <div>
                            <label class="dpt-label">Official Bank Name</label>
                            <input type="text" name="bank_name" class="dpt-input-text" value="<?php echo esc_attr( $bank_name ); ?>" placeholder="e.g. Sonali Bank PLC">
                        </div>
                        <div>
                            <label class="dpt-label">Account Number</label>
                            <input type="text" name="bank_account_no" class="dpt-input-text" value="<?php echo esc_attr( $bank_account_no ); ?>" placeholder="e.g. 001122334455">
                        </div>
                    </div>

                    <div class="dpt-row-matrix" style="margin-bottom:0;">
                        <div>
                            <label class="dpt-label">Branch Name</label>
                            <input type="text" name="bank_branch" class="dpt-input-text" value="<?php echo esc_attr( $bank_branch ); ?>" placeholder="e.g. Main Branch">
                        </div>
                        <div>
                            <label class="dpt-label">Routing Number</label>
                            <input type="text" name="bank_routing" class="dpt-input-text" value="<?php echo esc_attr( $bank_routing ); ?>" placeholder="e.g. 120271890">
                        </div>
                    </div>
                </div>

                <!-- Submission Trigger Container -->
                <div class="dpt-submit-wrapper">
                    <button type="submit" name="educore_save_settings" class="dpt-btn-submit-trigger">
                        <span class="dashicons dashicons-saved"></span> Save Institutional Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Media Uploader JS Handler -->
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            
            // Helper function for WordPress Media Library Trigger
            function initMediaUploader(btnId, valInputId, previewBoxId, removeBtnId, dialogTitle) {
                var mediaUploader;

                $(btnId).on('click', function(e) {
                    e.preventDefault();

                    if (mediaUploader) {
                        mediaUploader.open();
                        return;
                    }

                    mediaUploader = wp.media({
                        title: dialogTitle,
                        button: { text: 'Use Selected Media' },
                        multiple: false
                    });

                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        $(valInputId).val(attachment.url);
                        $(previewBoxId).html('<img src="' + attachment.url + '" alt="Selected Media">');
                        $(removeBtnId).show();
                    });

                    mediaUploader.open();
                });

                $(removeBtnId).on('click', function(e) {
                    e.preventDefault();
                    $(valInputId).val('');
                    $(previewBoxId).html('<span class="dashicons dashicons-format-image" style="font-size:32px; width:32px; height:32px; color:#94a3b8;"></span>');
                    $(this).hide();
                });
            }

            // Initialize Logo & Signature Uploaders
            initMediaUploader('#dpt_upload_logo_btn', '#dpt_school_logo_val', '#dpt-logo-preview', '#dpt_remove_logo_btn', 'Select Institutional Logo');
            initMediaUploader('#dpt_upload_sig_btn', '#dpt_principal_sig_val', '#dpt-sig-preview', '#dpt_remove_sig_btn', 'Select Principal/Authority Signature');
        });
    </script>
    <?php
}