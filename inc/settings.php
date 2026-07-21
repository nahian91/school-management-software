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
            'educore_school_name'    => sanitize_text_field( $_POST['school_name'] ),
            'educore_eiin_number'    => sanitize_text_field( $_POST['eiin_number'] ),
            'educore_institute_code' => sanitize_text_field( $_POST['institute_code'] ),
            'educore_school_address' => sanitize_textarea_field( $_POST['school_address'] ),
            'educore_school_phone'   => sanitize_text_field( $_POST['school_phone'] ),
            'educore_school_email'   => sanitize_email( $_POST['school_email'] ),
            'educore_currency'       => sanitize_text_field( $_POST['currency_symbol'] ),
            'educore_school_logo'    => esc_url_raw( $_POST['school_logo'] ),
        );

        foreach ( $settings as $key => $value ) {
            update_option( $key, $value );
        }

        $settings_updated = true;
        
        if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
            IFSEdu_School_Management_System::log_activity( "Updated global system settings & institutional profile." );
        }
    }

    // Retrieve existing settings (or defaults)
    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $eiin_number    = get_option( 'educore_eiin_number', '' );
    $institute_code = get_option( 'educore_institute_code', '' );
    $school_address = get_option( 'educore_school_address', '' );
    $school_phone   = get_option( 'educore_school_phone', '' );
    $school_email   = get_option( 'educore_school_email', get_bloginfo( 'admin_email' ) );
    $currency       = get_option( 'educore_currency', '৳' );
    $school_logo    = get_option( 'educore_school_logo', '' );
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

        /* Tactical Notification Nodes */
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

        /* Bento Grid Architecture */
        .dpt-bento-grid {
            display: grid;
            grid-template-columns: 2.2fr 1fr;
            gap: 24px;
            align-items: start;
        }

        @media (max-width: 1080px) {
            .dpt-bento-grid {
                grid-template-columns: 1fr;
            }
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
        .dpt-text-indigo { color: #4f46e5; }
        .dpt-text-slate { color: #64748b; }

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

        .dpt-field-group {
            margin-bottom: 20px;
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
        .dpt-textarea {
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

        .dpt-textarea {
            height: auto;
            padding: 12px 16px;
            resize: vertical;
            line-height: 1.5;
        }

        .dpt-input-text:focus, 
        .dpt-textarea:focus {
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

        /* Sidebar Metadata Systems */
        .dpt-sys-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .dpt-sys-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            font-size: 13px;
            border-bottom: 1px dashed #e2e8f0;
        }

        .dpt-sys-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .dpt-sys-item strong {
            color: #64748b;
            font-weight: 600;
        }

        .dpt-sys-item span {
            font-weight: 700;
            color: #0f172a;
            background: #f1f5f9;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
        }

        /* Module Info Extension Notice Block */
        .dpt-extension-box {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px;
        }

        .dpt-ext-heading {
            font-size: 13.5px;
            font-weight: 800;
            color: #006a4e;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dpt-ext-heading .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }

        .dpt-ext-desc {
            margin: 0;
            font-size: 12.5px;
            color: #475569;
            line-height: 1.6;
            font-weight: 500;
        }
    </style>

    <div class="dpt-settings-root">
        
        <!-- Header Frame -->
        <div class="afdp-header-frame">
            <div class="afdp-header-content">
                <h2>
                    <span class="dashicons dashicons-admin-settings"></span> System Settings & Institutional Profile
                </h2>
                <p>Configure global school identity, contact baselines, metadata parameters, and regional configurations.</p>
            </div>
        </div>

        <!-- Dynamic Flash Notification -->
        <?php if ( $settings_updated ) : ?>
            <div class="afdp-alert-node afdp-alert-success">
                <span class="dashicons dashicons-yes-alt"></span> System settings and institutional profile updated successfully.
            </div>
        <?php endif; ?>

        <!-- Bento Grid Core Matrix -->
        <div class="dpt-bento-grid">
            
            <!-- Left Grid: Primary Configuration Form -->
            <div>
                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_settings_action', 'educore_settings_nonce' ); ?>

                    <!-- Block 1: Identity & Crest -->
                    <div class="dpt-bento-block">
                        <div class="dpt-bento-title">
                            <div class="dpt-bento-title-text dpt-text-emerald">
                                <span class="dashicons dashicons-bank"></span> Institutional Identity
                            </div>
                        </div>

                        <!-- School Name -->
                        <div class="dpt-field-group">
                            <label class="dpt-label">Official Institution Name <span>*</span></label>
                            <input type="text" name="school_name" class="dpt-input-text" value="<?php echo esc_attr( $school_name ); ?>" required>
                            <small class="dpt-help-text">Appears on transcripts, invoices, ID cards, and official reports.</small>
                        </div>

                        <!-- EIIN & Institute Code Matrix -->
                        <div class="dpt-row-matrix">
                            <div>
                                <label class="dpt-label">EIIN Number</label>
                                <input type="text" name="eiin_number" class="dpt-input-text" value="<?php echo esc_attr( $eiin_number ); ?>" placeholder="e.g. 130892">
                            </div>
                            <div>
                                <label class="dpt-label">Institute / Government Code</label>
                                <input type="text" name="institute_code" class="dpt-input-text" value="<?php echo esc_attr( $institute_code ); ?>" placeholder="e.g. 2011">
                            </div>
                        </div>

                        <!-- School Crest Uploader -->
                        <div class="dpt-field-group" style="margin-bottom:0;">
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
                                        <span class="dashicons dashicons-upload"></span> Select Image
                                    </button>
                                    <button type="button" class="dpt-btn-remove" id="dpt_remove_logo_btn" style="<?php echo empty( $school_logo ) ? 'display:none;' : ''; ?>">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Block 2: Communication & Location -->
                    <div class="dpt-bento-block">
                        <div class="dpt-bento-title">
                            <div class="dpt-bento-title-text dpt-text-indigo">
                                <span class="dashicons dashicons-location"></span> Contact & Location Baseline
                            </div>
                        </div>

                        <!-- Contact & Email Matrix -->
                        <div class="dpt-row-matrix">
                            <div>
                                <label class="dpt-label">Official Phone Number</label>
                                <input type="text" name="school_phone" class="dpt-input-text" value="<?php echo esc_attr( $school_phone ); ?>" placeholder="+880 1XXX-XXXXXX">
                            </div>
                            <div>
                                <label class="dpt-label">Official Email Address</label>
                                <input type="email" name="school_email" class="dpt-input-text" value="<?php echo esc_attr( $school_email ); ?>">
                            </div>
                        </div>

                        <!-- Address Textarea Grid -->
                        <div class="dpt-field-group">
                            <label class="dpt-label">Institutional Physical Address</label>
                            <textarea name="school_address" class="dpt-textarea" rows="3" placeholder="Road/Village, Upazila, District, Post Code"><?php echo esc_textarea( $school_address ); ?></textarea>
                        </div>

                        <!-- Currency Symbol -->
                        <div class="dpt-field-group" style="margin-bottom:0;">
                            <label class="dpt-label">Default Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="dpt-input-text" value="<?php echo esc_attr( $currency ); ?>" placeholder="e.g. ৳, $, BDT" style="max-width: 200px;">
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

            <!-- Right Grid: Architectural Metadata & Extension Sidebar -->
            <div>
                <!-- System Information Card -->
                <div class="dpt-bento-block">
                    <div class="dpt-bento-title">
                        <div class="dpt-bento-title-text dpt-text-slate">
                            <span class="dashicons dashicons-info"></span> Engine Metadata
                        </div>
                    </div>
                    
                    <ul class="dpt-sys-list">
                        <li class="dpt-sys-item">
                            <strong>System Core</strong>
                            <span>v1.0.0</span>
                        </li>
                        <li class="dpt-sys-item">
                            <strong>Developer</strong>
                            <span>DevNahian</span>
                        </li>
                        <li class="dpt-sys-item">
                            <strong>PHP Engine</strong>
                            <span><?php echo esc_html( phpversion() ); ?></span>
                        </li>
                        <li class="dpt-sys-item">
                            <strong>WordPress Core</strong>
                            <span>v<?php echo esc_html( get_bloginfo('version') ); ?></span>
                        </li>
                        <li class="dpt-sys-item">
                            <strong>Server Date</strong>
                            <span><?php echo esc_html( date('d M, Y') ); ?></span>
                        </li>
                    </ul>
                </div>
                
                <!-- Extensions / Quick Guidance Node -->
                <div class="dpt-extension-box">
                    <h6 class="dpt-ext-heading">
                        <span class="dashicons dashicons-sos"></span> Module Gateway Guidance
                    </h6>
                    <p class="dpt-ext-desc">
                        To configure SMS notification gateways, print design templates, or custom grading schemes, visit the corresponding <strong>Academic</strong> or <strong>Communication</strong> modules from the main sidebar.
                    </p>
                </div>
            </div>

        </div>
    </div>

    <!-- Media Uploader JS Handler -->
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var mediaUploader;

            $('#dpt_upload_logo_btn').on('click', function(e) {
                e.preventDefault();

                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                mediaUploader = wp.media({
                    title: 'Select Institutional Logo',
                    button: { text: 'Use This Logo' },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#dpt_school_logo_val').val(attachment.url);
                    $('#dpt-logo-preview').html('<img src="' + attachment.url + '" alt="School Logo">');
                    $('#dpt_remove_logo_btn').show();
                });

                mediaUploader.open();
            });

            $('#dpt_remove_logo_btn').on('click', function(e) {
                e.preventDefault();
                $('#dpt_school_logo_val').val('');
                $('#dpt-logo-preview').html('<span class="dashicons dashicons-format-image" style="font-size:32px; width:32px; height:32px; color:#94a3b8;"></span>');
                $(this).hide();
            });
        });
    </script>
    <?php
}