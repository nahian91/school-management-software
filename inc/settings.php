<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function educore_settings_tab() {
    // Handle Form Submission
    if ( isset( $_POST['educore_save_settings'] ) && wp_verify_nonce( $_POST['educore_settings_nonce'], 'save_settings_action' ) ) {
        
        $settings = array(
            'educore_school_name'    => sanitize_text_field( $_POST['school_name'] ),
            'educore_school_address' => sanitize_textarea_field( $_POST['school_address'] ),
            'educore_school_phone'   => sanitize_text_field( $_POST['school_phone'] ),
            'educore_school_email'   => sanitize_email( $_POST['school_email'] ),
            'educore_currency'       => sanitize_text_field( $_POST['currency_symbol'] ),
        );

        foreach ( $settings as $key => $value ) {
            update_option( $key, $value );
        }

        echo '<div class="alert alert-success">System settings updated successfully.</div>';
        
        if ( function_exists('educore_log_activity') ) {
            educore_log_activity("Updated System Settings.");
        }
    }

    // Retrieve existing settings (or defaults)
    $school_name    = get_option( 'educore_school_name', get_bloginfo('name') );
    $school_address = get_option( 'educore_school_address', '' );
    $school_phone   = get_option( 'educore_school_phone', '' );
    $school_email   = get_option( 'educore_school_email', get_bloginfo('admin_email') );
    $currency       = get_option( 'educore_currency', '৳' );
    ?>

    <div class="educore-module-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><span class="dashicons dashicons-admin-settings"></span> System Settings</h2>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="bg-white p-4 rounded shadow-sm border">
                    <h4 class="border-bottom pb-2 mb-4 text-primary">School Profile & Configuration</h4>
                    
                    <form method="POST" action="">
                        <?php wp_nonce_field( 'save_settings_action', 'educore_settings_nonce' ); ?>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Official School Name</label>
                            <input type="text" name="school_name" class="form-control" value="<?php echo esc_attr( $school_name ); ?>" required>
                            <small class="text-muted">This name will appear on receipts and report cards.</small>
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
                            <label class="form-label fw-bold">School Address</label>
                            <textarea name="school_address" class="form-control" rows="3"><?php echo esc_textarea( $school_address ); ?></textarea>
                        </div>

                        <div class="mb-4 w-50">
                            <label class="form-label fw-bold">Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="form-control" value="<?php echo esc_attr( $currency ); ?>" placeholder="e.g. ৳, $, BDT">
                        </div>

                        <button type="submit" name="educore_save_settings" class="btn btn-success px-5 py-2 fs-6" style="background-color: #10b981; border: none; font-weight: bold;">
                            Save Settings
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <div class="bg-white p-4 rounded shadow-sm border mb-4">
                    <h5 class="border-bottom pb-2 mb-3 text-secondary">System Information</h5>
                    <p><strong>Version:</strong> 1.0.0</p>
                    <p><strong>Developed By:</strong> DevNahian</p>
                    <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                    <p><strong>Current Date:</strong> <?php echo date('d M Y'); ?></p>
                </div>
                
                <div class="alert alert-info border border-info">
                    <h6 class="alert-heading fw-bold"><span class="dashicons dashicons-info"></span> Need Help?</h6>
                    <p class="mb-0 text-sm">If you need to add custom grading scales or integrate an SMS gateway API, please check the Communication module or contact the developer.</p>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>