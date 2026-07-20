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

        echo '<div class="afdp-alert-node afdp-alert-success">System settings updated successfully.</div>';
        
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
           CORE MODERN BENTO FRAMEWORK & GLASSMORPHISM
           ========================================================================== */
        .dpt-settings-root {
            margin: 20px 20px 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        /* Top Header Area */
        .afdp-header-frame {
            margin-bottom: 24px;
        }
        .afdp-header-frame h2 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.5px;
        }
        .afdp-header-frame h2 .dashicons {
            font-size: 26px;
            width: 26px;
            height: 26px;
            color: #006a4e;
        }
        .afdp-header-frame p {
            margin: 0;
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        /* Tactical Notification Nodes */
        .afdp-alert-node {
            border-radius: 10px;
            padding: 14px 18px;
            font-weight: 600;
            font-size: 13.5px;
            margin-bottom: 24px;
            border: 1px solid transparent;
        }
        .afdp-alert-success { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }

        /* Bento Matrix Layout Grid */
        .dpt-bento-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 991px) {
            .dpt-bento-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Elegant Bento Block / Cell */
        .dpt-bento-block {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .dpt-bento-title {
            font-size: 15px;
            font-weight: 800;
            color: #1e293b;
            margin: 0 0 20px 0;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dpt-bento-title .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }
        .dpt-text-emerald { color: #006a4e; }
        .dpt-text-slate { color: #64748b; }

        /* Field Layout Matrices */
        .dpt-row-matrix {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }
        .dpt-row-matrix > div {
            flex: 1;
        }
        @media (max-width: 575px) {
            .dpt-row-matrix {
                flex-direction: column;
                gap: 16px;
            }
        }

        .dpt-field-group {
            margin-bottom: 16px;
        }
        .dpt-field-group:last-of-type {
            margin-bottom: 24px;
        }

        .dpt-label {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
        }
        .dpt-label span {
            color: #dc2626;
        }

        .dpt-input-text, 
        .dpt-textarea {
            width: 100%;
            height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 14px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            box-shadow: none;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .dpt-textarea {
            height: auto;
            padding: 10px 14px;
            resize: vertical;
        }
        .dpt-input-text:focus, 
        .dpt-textarea:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
            outline: none;
        }

        .dpt-help-text {
            display: block;
            font-size: 11.5px;
            color: #64748b;
            margin-top: 4px;
            font-weight: 500;
        }

        /* Kinetic Form Submissions Control Button */
        .dpt-btn-submit-trigger {
            height: 44px;
            background: #006a4e;
            border: 1px solid transparent;
            color: #ffffff;
            font-weight: 700;
            font-size: 13.5px;
            border-radius: 8px;
            padding: 0 32px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.15);
        }
        .dpt-btn-submit-trigger:hover {
            background: #00523c;
            color: #ffffff;
            transform: translateY(-0.5px);
        }

        /* Sidebar Glassmorphic Elements */
        .dpt-sys-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .dpt-sys-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            font-size: 13px;
            color: #334155;
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
        }

        /* Extension Notice Block */
        .dpt-extension-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px;
            margin-top: 20px;
        }
        .dpt-ext-heading {
            font-size: 13px;
            font-weight: 700;
            color: #006a4e;
            margin: 0 0 6px 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .dpt-ext-heading .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        .dpt-ext-desc {
            margin: 0;
            font-size: 12px;
            color: #64748b;
            line-height: 1.5;
            font-weight: 500;
        }
    </style>

    <div class="dpt-settings-root">
        
        <div class="afdp-header-frame">
            <h2>
                <span class="dashicons dashicons-admin-settings"></span> System Settings & Institutional Profile
            </h2>
            <p>Configure the global properties, communication baselines, and metadata profiles for the engine.</p>
        </div>

        <div class="dpt-bento-grid">
            
            <!-- Left Grid: Primary Setup Form Block -->
            <div class="dpt-bento-block">
                <h5 class="dpt-bento-title dpt-text-emerald">
                    <span class="dashicons dashicons-admin-generic"></span> General Configuration
                </h5>
                
                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_settings_action', 'educore_settings_nonce' ); ?>
                    
                    <!-- School Name -->
                    <div class="dpt-field-group">
                        <label class="dpt-label">Official Institution Name <span>*</span></label>
                        <input type="text" name="school_name" class="dpt-input-text" value="<?php echo esc_attr( $school_name ); ?>" required>
                        <small class="dpt-help-text">Appears on invoices, ID cards, and report cards.</small>
                    </div>

                    <!-- EIIN & Institute Code Matrix -->
                    <div class="dpt-row-matrix">
                        <div>
                            <label class="dpt-label">EIIN Number</label>
                            <input type="text" name="eiin_number" class="dpt-input-text" value="<?php echo esc_attr( $eiin_number ); ?>" placeholder="e.g. 130892">
                        </div>
                        <div>
                            <label class="dpt-label">Institute Code</label>
                            <input type="text" name="institute_code" class="dpt-input-text" value="<?php echo esc_attr( $institute_code ); ?>" placeholder="e.g. 2011">
                        </div>
                    </div>

                    <!-- Contact & Email Matrix -->
                    <div class="dpt-row-matrix">
                        <div>
                            <label class="dpt-label">Contact Phone Number</label>
                            <input type="text" name="school_phone" class="dpt-input-text" value="<?php echo esc_attr( $school_phone ); ?>" placeholder="+880 1XXX-XXXXXX">
                        </div>
                        <div>
                            <label class="dpt-label">Official Email Address</label>
                            <input type="email" name="school_email" class="dpt-input-text" value="<?php echo esc_attr( $school_email ); ?>">
                        </div>
                    </div>

                    <!-- Address Textarea Grid -->
                    <div class="dpt-field-group">
                        <label class="dpt-label">Institutional Address</label>
                        <textarea name="school_address" class="dpt-textarea" rows="3" placeholder="Road/Village, Upazila, District"><?php echo esc_textarea( $school_address ); ?></textarea>
                    </div>

                    <!-- Currency & Logo URI Matrix -->
                    <div class="dpt-row-matrix">
                        <div>
                            <label class="dpt-label">Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="dpt-input-text" value="<?php echo esc_attr( $currency ); ?>" placeholder="e.g. ৳, $, BDT">
                        </div>
                        <div>
                            <label class="dpt-label">Crest / Logo Image URL</label>
                            <input type="url" name="school_logo" class="dpt-input-text" value="<?php echo esc_url( $school_logo ); ?>" placeholder="https://example.com/logo.png">
                        </div>
                    </div>

                    <!-- Action Trigger Button -->
                    <button type="submit" name="educore_save_settings" class="dpt-btn-submit-trigger">
                        Save Configuration
                    </button>
                </form>
            </div>

            <!-- Right Grid: Architectural System Metadata Sidebar -->
            <div>
                <div class="dpt-bento-block">
                    <h5 class="dpt-bento-title dpt-text-slate">
                        <span class="dashicons dashicons-info"></span> System Information
                    </h5>
                    
                    <ul class="dpt-sys-list">
                        <li class="dpt-sys-item">
                            <strong>Core System Version</strong>
                            <span>1.0.0</span>
                        </li>
                        <li class="dpt-sys-item">
                            <strong>Developed By</strong>
                            <span>DevNahian</span>
                        </li>
                        <li class="dpt-sys-item">
                            <strong>PHP Version</strong>
                            <span><?php echo esc_html( phpversion() ); ?></span>
                        </li>
                        <li class="dpt-sys-item">
                            <strong>WordPress Version</strong>
                            <span><?php echo esc_html( get_bloginfo('version') ); ?></span>
                        </li>
                        <li class="dpt-sys-item">
                            <strong>Server Date</strong>
                            <span><?php echo esc_html( date('d M, Y') ); ?></span>
                        </li>
                    </ul>
                </div>
                
                <!-- Extensions / Info Module Node -->
                <div class="dpt-extension-box">
                    <h6 class="dpt-ext-heading">
                        <span class="dashicons dashicons-sos"></span> Module Extensions
                    </h6>
                    <p class="dpt-ext-desc">
                        To configure SMS API gateways or setup custom grade boundaries, access the respective Academic or Communication modules from the main navigation menu.
                    </p>
                </div>
            </div>

        </div>
    </div>
    <?php
}