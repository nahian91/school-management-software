<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

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

        echo '<div class="alert alert-success border-0 shadow-sm mb-4">System settings updated successfully.</div>';
        
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

    <div class="educore-module-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><span class="dashicons dashicons-admin-settings text-success me-1"></span> System Settings & Institutional Profile</h2>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="bg-white p-4 rounded shadow-sm border">
                    <h4 class="border-bottom pb-2 mb-4 text-success fw-bold">General Configuration</h4>
                    
                    <form method="POST" action="">
                        <?php wp_nonce_field( 'save_settings_action', 'educore_settings_nonce' ); ?>
                        
                        <!-- School Name & Logo -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Official Institution Name <span class="text-danger">*</span></label>
                            <input type="text" name="school_name" class="form-control" value="<?php echo esc_attr( $school_name ); ?>" required>
                            <small class="text-muted">Appears on invoices, ID cards, and report cards.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">EIIN Number</label>
                                <input type="text" name="eiin_number" class="form-control" value="<?php echo esc_attr( $eiin_number ); ?>" placeholder="e.g. 130892">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Institute Code</label>
                                <input type="text" name="institute_code" class="form-control" value="<?php echo esc_attr( $institute_code ); ?>" placeholder="e.g. 2011">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Contact Phone Number</label>
                                <input type="text" name="school_phone" class="form-control" value="<?php echo esc_attr( $school_phone ); ?>" placeholder="+880 1XXX-XXXXXX">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Official Email Address</label>
                                <input type="email" name="school_email" class="form-control" value="<?php echo esc_attr( $school_email ); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Institutional Address</label>
                            <textarea name="school_address" class="form-control" rows="3" placeholder="Road/Village, Upazila, District"><?php echo esc_textarea( $school_address ); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold">Currency Symbol</label>
                                <input type="text" name="currency_symbol" class="form-control" value="<?php echo esc_attr( $currency ); ?>" placeholder="e.g. ৳, $, BDT">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold">Crest / Logo Image URL</label>
                                <input type="url" name="school_logo" class="form-control" value="<?php echo esc_url( $school_logo ); ?>" placeholder="https://example.com/logo.png">
                            </div>
                        </div>

                        <button type="submit" name="educore_save_settings" class="btn btn-success px-5 py-2 fw-bold" style="background-color: #006a4e; border: none;">
                            Save Configuration
                        </button>
                    </form>
                </div>
            </div>

            <!-- System Info Sidebar -->
            <div class="col-md-4">
                <div class="bg-white p-4 rounded shadow-sm border mb-4">
                    <h5 class="border-bottom pb-2 mb-3 text-secondary fw-bold">
                        <span class="dashicons dashicons-info me-1"></span> System Information
                    </h5>
                    <p class="mb-2"><strong>Core System Version:</strong> 1.0.0</p>
                    <p class="mb-2"><strong>Developed By:</strong> DevNahian</p>
                    <p class="mb-2"><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                    <p class="mb-2"><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
                    <p class="mb-0"><strong>Server Date:</strong> <?php echo date('d M, Y'); ?></p>
                </div>
                
                <div class="alert alert-light border shadow-sm p-4">
                    <h6 class="alert-heading fw-bold text-success"><span class="dashicons dashicons-sos me-1"></span> Module Extensions</h6>
                    <p class="mb-0 small text-muted">To configure SMS API gateways or setup custom grade boundaries, access the respective Academic or Communication modules from the main navigation menu.</p>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>