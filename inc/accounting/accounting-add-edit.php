<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enterprise Accounting & General Ledger Transaction Module (Institutional Grade)
 * File: accounting-add-edit.php
 */
function educore_accounting_add_edit_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to manage accounting records.', 'ifsedu-sms' ) );
    }

    global $wpdb;
    $table_accounting = $wpdb->prefix . 'sms_accounting';

    // --------------------------------------------------------------------------
    // 0. AUTO-SCHEMA CHECK (Ensures missing columns exist for professional auditing)
    // --------------------------------------------------------------------------
    $check_party = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_accounting}` LIKE 'party_name'" );
    if ( empty( $check_party ) ) {
        $wpdb->query( "ALTER TABLE `{$table_accounting}` ADD `party_name` varchar(150) DEFAULT '' NOT NULL AFTER `title`" );
    }

    $check_attach = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_accounting}` LIKE 'attachment_url'" );
    if ( empty( $check_attach ) ) {
        $wpdb->query( "ALTER TABLE `{$table_accounting}` ADD `attachment_url` text AFTER `note`" );
    }

    $check_account = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_accounting}` LIKE 'bank_account'" );
    if ( empty( $check_account ) ) {
        $wpdb->query( "ALTER TABLE `{$table_accounting}` ADD `bank_account` varchar(100) DEFAULT 'Cash in Hand' NOT NULL AFTER `payment_method`" );
    }

    $check_dept = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_accounting}` LIKE 'department'" );
    if ( empty( $check_dept ) ) {
        $wpdb->query( "ALTER TABLE `{$table_accounting}` ADD `department` varchar(100) DEFAULT 'General Administration' NOT NULL AFTER `category_name`" );
    }

    $check_tax = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_accounting}` LIKE 'tax_vat_deducted'" );
    if ( empty( $check_tax ) ) {
        $wpdb->query( "ALTER TABLE `{$table_accounting}` ADD `tax_vat_deducted` decimal(10,2) DEFAULT '0.00' NOT NULL AFTER `amount`" );
    }

    $check_status = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_accounting}` LIKE 'approval_status'" );
    if ( empty( $check_status ) ) {
        $wpdb->query( "ALTER TABLE `{$table_accounting}` ADD `approval_status` varchar(50) DEFAULT 'Approved' NOT NULL AFTER `note`" );
    }

    $check_project = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_accounting}` LIKE 'project_tag'" );
    if ( empty( $check_project ) ) {
        $wpdb->query( "ALTER TABLE `{$table_accounting}` ADD `project_tag` varchar(150) DEFAULT 'General Operations' NOT NULL AFTER `department`" );
    }

    // Advanced Institutional Compliance Fields
    $check_fiscal = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_accounting}` LIKE 'fiscal_year'" );
    if ( empty( $check_fiscal ) ) {
        $wpdb->query( "ALTER TABLE `{$table_accounting}` ADD `fiscal_year` varchar(20) DEFAULT '" . date('Y') . "-" . (date('Y') + 1) . "' NOT NULL AFTER `entry_date`" );
    }

    $check_cost_center = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_accounting}` LIKE 'cost_center_code'" );
    if ( empty( $check_cost_center ) ) {
        $wpdb->query( "ALTER TABLE `{$table_accounting}` ADD `cost_center_code` varchar(50) DEFAULT 'CC-ADMIN-01' NOT NULL AFTER `department`" );
    }

    $is_edit  = ( isset( $_GET['sub_mode'] ) && $_GET['sub_mode'] === 'edit' ) || ( isset( $_GET['sub'] ) && $_GET['sub'] === 'edit' );
    $entry_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $db_error = '';

    $entry = null;
    if ( $is_edit && $entry_id > 0 ) {
        $entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_accounting} WHERE id = %d", $entry_id ) );
        if ( ! $entry ) {
            $is_edit = false;
        }
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=list' );

    // --------------------------------------------------------------------------
    // 1. FORM SUBMISSION ENGINE WITH STRICT SANITIZATION
    // --------------------------------------------------------------------------
    if ( isset( $_POST['educore_save_accounting_entry'] ) && isset( $_POST['educore_acct_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_acct_nonce'] ) ), 'save_acct_action' ) ) {
        
        $entry_type       = isset( $_POST['entry_type'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_type'] ) ) : 'Income';
        $category_name    = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
        $department       = isset( $_POST['department'] ) ? sanitize_text_field( wp_unslash( $_POST['department'] ) ) : 'General Administration';
        $cost_center_code = isset( $_POST['cost_center_code'] ) ? sanitize_text_field( wp_unslash( $_POST['cost_center_code'] ) ) : 'CC-ADMIN-01';
        $project_tag      = isset( $_POST['project_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['project_tag'] ) ) : 'General Operations';
        $fiscal_year      = isset( $_POST['fiscal_year'] ) ? sanitize_text_field( wp_unslash( $_POST['fiscal_year'] ) ) : date('Y') . '-' . (date('Y') + 1);
        $title            = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $party_name       = isset( $_POST['party_name'] ) ? sanitize_text_field( wp_unslash( $_POST['party_name'] ) ) : '';
        $amount           = isset( $_POST['amount'] ) ? max( 0, floatval( $_POST['amount'] ) ) : 0;
        $tax_vat_deducted = isset( $_POST['tax_vat_deducted'] ) ? max( 0, floatval( $_POST['tax_vat_deducted'] ) ) : 0;
        $entry_date       = isset( $_POST['entry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_date'] ) ) : current_time( 'Y-m-d' );
        $payment_method   = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'Cash';
        $bank_account     = isset( $_POST['bank_account'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_account'] ) ) : 'Cash in Hand';
        $approval_status  = isset( $_POST['approval_status'] ) ? sanitize_text_field( wp_unslash( $_POST['approval_status'] ) ) : 'Approved';
        $voucher_no       = ! empty( $_POST['voucher_no'] ) ? sanitize_text_field( wp_unslash( $_POST['voucher_no'] ) ) : 'VOU-' . wp_rand( 10000, 99999 );
        $note             = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
        
        $attachment_url = $entry && ! empty( $entry->attachment_url ) ? $entry->attachment_url : '';

        // Handle File Upload securely
        if ( ! empty( $_FILES['voucher_attachment']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $uploaded_file = wp_handle_upload( $_FILES['voucher_attachment'], array( 'test_form' => false ) );
            if ( ! isset( $uploaded_file['error'] ) && isset( $uploaded_file['url'] ) ) {
                $attachment_url = esc_url_raw( $uploaded_file['url'] );
            }
        }

        if ( ! empty( $title ) && $amount > 0 ) {
            $data = array(
                'voucher_no'       => $voucher_no,
                'entry_type'       => $entry_type,
                'category_name'    => $category_name,
                'department'       => $department,
                'cost_center_code' => $cost_center_code,
                'project_tag'      => $project_tag,
                'title'            => $title,
                'party_name'       => $party_name,
                'amount'           => $amount,
                'tax_vat_deducted' => $tax_vat_deducted,
                'payment_method'   => $payment_method,
                'bank_account'     => $bank_account,
                'approval_status'  => $approval_status,
                'entry_date'       => $entry_date,
                'fiscal_year'      => $fiscal_year,
                'note'             => $note,
                'attachment_url'   => $attachment_url,
                'created_by'       => get_current_user_id()
            );

            if ( $is_edit && $entry_id > 0 ) {
                $result = $wpdb->update( $table_accounting, $data, array( 'id' => $entry_id ) );
                $status_flag = 'updated';
            } else {
                $result = $wpdb->insert( $table_accounting, $data );
                $status_flag = 'success';
            }

            if ( false !== $result ) {
                if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                    IFSEdu_School_Management_System::log_activity( sprintf( "Processed Institutional Ledger Entry: (%s) %s - Amount: %.2f", $voucher_no, $title, $amount ) );
                }

                $redirect_url = add_query_arg(
                    array(
                        'page' => 'school_management_system',
                        'tab'  => 'accounting',
                        'sub'  => 'list',
                        'msg'  => $status_flag
                    ),
                    admin_url( 'admin.php' )
                );

                echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_url ) . '";</script>';
                exit;
            } else {
                $db_error = $wpdb->last_error ? $wpdb->last_error : __( 'Database query execution failed.', 'ifsedu-sms' );
            }
        } else {
            $db_error = __( 'Please enter a valid title and a gross amount greater than 0.00', 'ifsedu-sms' );
        }
    }
    ?>

    <style>
        .dpt-add-acct-container {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            max-width: 1000px;
            margin: 20px auto;
        }

        .dpt-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .dpt-back-btn {
            height: 38px;
            padding: 0 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .dpt-back-btn:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .dpt-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-card-title-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 16px;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 24px;
        }

        .afdp-card-title {
            font-size: 19px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dpt-form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .dpt-form-group.span-2 {
            grid-column: span 2;
        }

        .dpt-form-group.full-width {
            grid-column: span 3;
        }

        @media (max-width: 992px) {
            .dpt-form-grid { grid-template-columns: repeat(2, 1fr); }
            .dpt-form-group.span-2, .dpt-form-group.full-width { grid-column: span 2; }
        }
        @media (max-width: 640px) {
            .dpt-form-grid { grid-template-columns: 1fr; }
            .dpt-form-group.span-2, .dpt-form-group.full-width { grid-column: span 1; }
        }

        .dpt-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dpt-form-label {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .dpt-input-field, .dpt-select-field, .dpt-textarea-field {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0 14px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            width: 100%;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .dpt-input-field, .dpt-select-field {
            height: 42px;
        }

        .dpt-textarea-field {
            padding: 10px 14px;
            height: 75px;
            resize: vertical;
        }

        .dpt-input-field:focus, .dpt-select-field:focus, .dpt-textarea-field:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
            outline: none;
        }

        .dpt-select-field.type-income-active {
            border-color: #006a4e;
            background-color: #ecfdf5;
            color: #065f46;
        }

        .dpt-select-field.type-expense-active {
            border-color: #ef4444;
            background-color: #fef2f2;
            color: #991b1b;
        }

        .dpt-amount-words {
            font-size: 11.5px;
            font-weight: 700;
            color: #059669;
            margin-top: 4px;
            font-style: italic;
        }

        .dpt-quick-chips {
            display: flex;
            gap: 6px;
            margin-top: 6px;
            flex-wrap: wrap;
        }
        .dpt-chip-btn {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .dpt-chip-btn:hover {
            background: #006a4e;
            color: #fff;
            border-color: #006a4e;
        }

        .dpt-live-summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .dpt-btn-submit {
            height: 48px;
            background: #006a4e;
            border: none;
            color: #ffffff;
            font-weight: 800;
            font-size: 15px;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.25);
            margin-top: 15px;
        }

        .dpt-btn-submit:hover {
            background: #00523c;
            box-shadow: 0 6px 16px rgba(0, 106, 78, 0.35);
        }

        .afdp-alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 14px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
    </style>

    <div class="dpt-add-acct-container">

        <!-- Top Navigation Bar -->
        <div class="dpt-header-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-back-btn">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size:14px; width:14px; height:14px;"></span>
                <?php esc_html_e( 'Back to Accounts List', 'ifsedu-sms' ); ?>
            </a>
            <?php if ( $is_edit && $entry ) : ?>
                <span style="font-weight:800; font-size:13px; color:#006a4e; background:#ecfdf5; padding:6px 12px; border-radius:20px; border:1px solid #a7f3d0;">
                    <?php echo esc_html( 'Editing Ledger Record #' . $entry->id ); ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ( ! empty( $db_error ) ) : ?>
            <div class="afdp-alert-error">
                <span class="dashicons dashicons-warning" style="font-size:18px; width:18px; height:18px;"></span>
                <span><?php echo esc_html( $db_error ); ?></span>
            </div>
        <?php endif; ?>

        <div class="dpt-bento-card">
            
            <div class="afdp-card-title-group">
                <h4 class="afdp-card-title">
                    <span class="dashicons dashicons-book-alt" style="color: #006a4e;"></span>
                    <?php echo $is_edit ? esc_html__( 'Edit Professional Financial Ledger Entry', 'ifsedu-sms' ) : esc_html__( 'New Institutional Voucher Entry', 'ifsedu-sms' ); ?>
                </h4>
                <span style="font-size: 12px; color: #64748b; font-weight: 600;"><?php esc_html_e( 'Double-Entry Accounting Standard', 'ifsedu-sms' ); ?></span>
            </div>

            <!-- Live Summary Preview Strip -->
            <div class="dpt-live-summary-box">
                <div>
                    <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700; display:block;"><?php esc_html_e( 'Voucher / Purpose Preview', 'ifsedu-sms' ); ?></span>
                    <strong id="previewTitle" style="color:#0f172a; font-size:14px;"><?php esc_html_e( 'New General Ledger Voucher', 'ifsedu-sms' ); ?></strong>
                </div>
                <div style="text-align:right;">
                    <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700; display:block;"><?php esc_html_e( 'Net Amount Preview', 'ifsedu-sms' ); ?></span>
                    <strong id="previewAmount" style="color:#059669; font-size:16px;">৳0.00</strong>
                </div>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <?php wp_nonce_field( 'save_acct_action', 'educore_acct_nonce' ); ?>
                
                <div class="dpt-form-grid">
                    
                    <!-- Flow Type -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Flow Type', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="entry_type" id="educore_entry_type" class="dpt-select-field type-income-active" style="font-weight:700;" required>
                            <option value="Income" <?php selected( $entry ? $entry->entry_type : '', 'Income' ); ?>><?php esc_html_e( 'Income (+ Credit)', 'ifsedu-sms' ); ?></option>
                            <option value="Expense" <?php selected( $entry ? $entry->entry_type : '', 'Expense' ); ?>><?php esc_html_e( 'Expense (- Debit)', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- Gross Amount with Quick Chips -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Gross Amount (৳)', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="number" step="0.01" name="amount" id="educore_amount_input" class="dpt-input-field" style="font-weight:800; font-size:15px;" placeholder="0.00" min="0.01" value="<?php echo $entry ? esc_attr( $entry->amount ) : ''; ?>" required>
                        <div class="dpt-quick-chips">
                            <button type="button" class="dpt-chip-btn" data-add="500">+500</button>
                            <button type="button" class="dpt-chip-btn" data-add="1000">+1,000</button>
                            <button type="button" class="dpt-chip-btn" data-add="5000">+5,000</button>
                            <button type="button" class="dpt-chip-btn" data-add="10000">+10,000</button>
                            <button type="button" class="dpt-chip-btn" id="educore_clear_amt" style="color:#dc2626;">Clear</button>
                        </div>
                        <div id="educore_words_preview" class="dpt-amount-words"></div>
                    </div>

                    <!-- Tax / VAT Deducted -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Tax / VAT Deducted (৳)', 'ifsedu-sms' ); ?></label>
                        <input type="number" step="0.01" name="tax_vat_deducted" class="dpt-input-field" placeholder="0.00" min="0" value="<?php echo ( $entry && isset( $entry->tax_vat_deducted ) ) ? esc_attr( $entry->tax_vat_deducted ) : '0.00'; ?>">
                    </div>

                    <!-- Title -->
                    <div class="dpt-form-group span-2">
                        <label class="dpt-form-label"><?php esc_html_e( 'Transaction Purpose / Ledger Title', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="title" id="educore_title_input" class="dpt-input-field" placeholder="e.g. Monthly Electricity Bill / Annual Sports Sponsorship" value="<?php echo $entry ? esc_attr( $entry->title ) : ''; ?>" required>
                    </div>

                    <!-- Category -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Ledger Category', 'ifsedu-sms' ); ?></label>
                        <select name="category_name" id="educore_category_select" class="dpt-select-field">
                            <!-- Populated dynamically via JS -->
                        </select>
                    </div>

                    <!-- Department / Cost Center -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Department / Cost Center', 'ifsedu-sms' ); ?></label>
                        <select name="department" class="dpt-select-field">
                            <?php 
                            $departments = array( 'General Administration', 'Science Faculty', 'Arts Faculty', 'Commerce Faculty', 'Library & Laboratory', 'Transport & Hostel', 'Sports & Cultural' );
                            $saved_dept  = ( $entry && isset( $entry->department ) ) ? $entry->department : 'General Administration';
                            foreach ( $departments as $dept ) {
                                echo '<option value="' . esc_attr( $dept ) . '" ' . selected( $saved_dept, $dept, false ) . '>' . esc_html( $dept ) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Cost Center Code -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Cost Center Code', 'ifsedu-sms' ); ?></label>
                        <input type="text" name="cost_center_code" class="dpt-input-field" placeholder="e.g. CC-ADMIN-01" value="<?php echo ( $entry && isset( $entry->cost_center_code ) ) ? esc_attr( $entry->cost_center_code ) : 'CC-ADMIN-01'; ?>">
                    </div>

                    <!-- Project Tag / Fund Allocation -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Project / Fund Allocation', 'ifsedu-sms' ); ?></label>
                        <input type="text" name="project_tag" class="dpt-input-field" placeholder="e.g. Annual Sports 2026 / Development Fund" value="<?php echo ( $entry && isset( $entry->project_tag ) ) ? esc_attr( $entry->project_tag ) : 'General Operations'; ?>">
                    </div>

                    <!-- Fiscal Year -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Fiscal Year', 'ifsedu-sms' ); ?></label>
                        <select name="fiscal_year" class="dpt-select-field">
                            <?php 
                            $current_yr = intval( date('Y') );
                            $saved_fiscal = ( $entry && isset( $entry->fiscal_year ) ) ? $entry->fiscal_year : $current_yr . '-' . ($current_yr + 1);
                            for ( $y = $current_yr - 2; $y <= $current_yr + 2; $y++ ) {
                                $f_val = $y . '-' . ($y + 1);
                                echo '<option value="' . esc_attr( $f_val ) . '" ' . selected( $saved_fiscal, $f_val, false ) . '>' . esc_html( 'FY ' . $f_val ) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Payer / Payee Identity -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label" id="educore_party_label"><?php esc_html_e( 'Received From (Payer)', 'ifsedu-sms' ); ?></label>
                        <input type="text" name="party_name" class="dpt-input-field" placeholder="e.g. Bangladesh Education Board / Vendor Name" value="<?php echo ( $entry && isset( $entry->party_name ) ) ? esc_attr( $entry->party_name ) : ''; ?>">
                    </div>

                    <!-- Payment Method -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Payment Method', 'ifsedu-sms' ); ?></label>
                        <select name="payment_method" class="dpt-select-field">
                            <option value="Cash" <?php selected( $entry ? $entry->payment_method : '', 'Cash' ); ?>><?php esc_html_e( 'Cash In Hand', 'ifsedu-sms' ); ?></option>
                            <option value="Bank Transfer" <?php selected( $entry ? $entry->payment_method : '', 'Bank Transfer' ); ?>><?php esc_html_e( 'Bank Wire / Cheque Deposit', 'ifsedu-sms' ); ?></option>
                            <option value="bKash" <?php selected( $entry ? $entry->payment_method : '', 'bKash' ); ?>><?php esc_html_e( 'bKash Mobile Banking', 'ifsedu-sms' ); ?></option>
                            <option value="Nagad" <?php selected( $entry ? $entry->payment_method : '', 'Nagad' ); ?>><?php esc_html_e( 'Nagad Mobile Banking', 'ifsedu-sms' ); ?></option>
                            <option value="Cheque" <?php selected( $entry ? $entry->payment_method : '', 'Cheque' ); ?>><?php esc_html_e( 'Cheque Payment', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- Bank Account / Fund Source -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Account / Fund Source', 'ifsedu-sms' ); ?></label>
                        <select name="bank_account" class="dpt-select-field">
                            <option value="Cash in Hand" <?php selected( ( $entry && isset( $entry->bank_account ) ) ? $entry->bank_account : '', 'Cash in Hand' ); ?>><?php esc_html_e( 'Cash in Hand (Petty Cash)', 'ifsedu-sms' ); ?></option>
                            <option value="Sonali Bank PLC" <?php selected( ( $entry && isset( $entry->bank_account ) ) ? $entry->bank_account : '', 'Sonali Bank PLC' ); ?>><?php esc_html_e( 'Sonali Bank PLC (Main A/C)', 'ifsedu-sms' ); ?></option>
                            <option value="Dutch-Bangla Bank PLC" <?php selected( ( $entry && isset( $entry->bank_account ) ) ? $entry->bank_account : '', 'Dutch-Bangla Bank PLC' ); ?>><?php esc_html_e( 'Dutch-Bangla Bank PLC', 'ifsedu-sms' ); ?></option>
                            <option value="bKash Merchant Wallet" <?php selected( ( $entry && isset( $entry->bank_account ) ) ? $entry->bank_account : '', 'bKash Merchant Wallet' ); ?>><?php esc_html_e( 'bKash Merchant Wallet', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- Voucher No -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Voucher / Ref No.', 'ifsedu-sms' ); ?></label>
                        <input type="text" name="voucher_no" class="dpt-input-field" value="<?php echo $entry ? esc_attr( $entry->voucher_no ) : esc_attr( 'VOU-' . wp_rand( 10000, 99999 ) ); ?>">
                    </div>

                    <!-- Transaction Date -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Transaction Date', 'ifsedu-sms' ); ?></label>
                        <input type="date" name="entry_date" class="dpt-input-field" value="<?php echo $entry ? esc_attr( $entry->entry_date ) : esc_attr( current_time('Y-m-d') ); ?>" required>
                    </div>

                    <!-- Approval Status -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Audit / Approval Status', 'ifsedu-sms' ); ?></label>
                        <select name="approval_status" class="dpt-select-field" style="font-weight:700;">
                            <option value="Approved" <?php selected( ( $entry && isset( $entry->approval_status ) ) ? $entry->approval_status : '', 'Approved' ); ?>><?php esc_html_e( 'Approved & Verified', 'ifsedu-sms' ); ?></option>
                            <option value="Pending Audit" <?php selected( ( $entry && isset( $entry->approval_status ) ) ? $entry->approval_status : '', 'Pending Audit' ); ?>><?php esc_html_e( 'Pending Audit Review', 'ifsedu-sms' ); ?></option>
                            <option value="Flagged" <?php selected( ( $entry && isset( $entry->approval_status ) ) ? $entry->approval_status : '', 'Flagged' ); ?>><?php esc_html_e( 'Flagged / Disputed', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- Attachment Upload -->
                    <div class="dpt-form-group full-width">
                        <label class="dpt-form-label"><?php esc_html_e( 'Attach Receipt / Physical Voucher Slip (PDF, PNG, JPG)', 'ifsedu-sms' ); ?></label>
                        <div style="display:flex; gap:12px; align-items:center;">
                            <input type="file" name="voucher_attachment" accept="image/*,application/pdf" class="dpt-input-field" style="padding-top:8px;">
                            <?php if ( $entry && ! empty( $entry->attachment_url ) ) : ?>
                                <a href="<?php echo esc_url( $entry->attachment_url ); ?>" target="_blank" class="dpt-back-btn" style="height:42px; white-space:nowrap;">
                                    <span class="dashicons dashicons-media-document"></span> View Current Slip
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="dpt-form-group full-width">
                        <label class="dpt-form-label"><?php esc_html_e( 'Auditor Notes / Internal Memo', 'ifsedu-sms' ); ?></label>
                        <textarea name="note" class="dpt-textarea-field" placeholder="Enter detailed transaction summary or internal memo..."><?php echo $entry ? esc_textarea( $entry->note ) : ''; ?></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="dpt-form-group full-width">
                        <button type="submit" name="educore_save_accounting_entry" class="dpt-btn-submit">
                            <span class="dashicons dashicons-saved"></span>
                            <?php echo $is_edit ? esc_html__( 'Update Institutional Ledger Record', 'ifsedu-sms' ) : esc_html__( 'Record & Post to General Ledger', 'ifsedu-sms' ); ?>
                        </button>
                    </div>

                </div>
            </form>
        </div>

    </div>

    <!-- Smart Dynamic Categories & In-Words Engine Script -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const typeSelect     = document.getElementById('educore_entry_type');
        const categorySelect = document.getElementById('educore_category_select');
        const partyLabel     = document.getElementById('educore_party_label');
        const amountInput    = document.getElementById('educore_amount_input');
        const titleInput     = document.getElementById('educore_title_input');
        const wordsPreview   = document.getElementById('educore_words_preview');
        const previewTitle   = document.getElementById('previewTitle');
        const previewAmount  = document.getElementById('previewAmount');
        const savedCategory  = "<?php echo $entry ? esc_js( $entry->category_name ) : ''; ?>";

        const incomeCategories = [
            'Tuition & Academic Fees',
            'Admission & Registration Fees',
            'Government Grants & Subsidies',
            'Donations & Corporate Sponsorships',
            'Facility & Auditorium Rental',
            'Exam Sheet & Form Sales',
            'Bank Interest & Investments',
            'Miscellaneous Income'
        ];

        const expenseCategories = [
            'Staff Salaries & Remunerations',
            'Utility Bills (Electricity, Gas, Water)',
            'Campus Maintenance & Repairs',
            'Office & Laboratory Stationery',
            'Property Lease & Campus Rent',
            'Student Welfare & Sports Events',
            'Software Licenses & IT Hosting',
            'Depreciation & Bank Charges',
            'Miscellaneous Expenses'
        ];

        function updateCategories() {
            const selectedType = typeSelect.value;
            categorySelect.innerHTML = '';

            let activeList = selectedType === 'Income' ? incomeCategories : expenseCategories;

            activeList.forEach(function(cat) {
                let opt = document.createElement('option');
                opt.value = cat;
                opt.textContent = cat;
                if (savedCategory && savedCategory === cat) {
                    opt.selected = true;
                }
                categorySelect.appendChild(opt);
            });

            if (selectedType === 'Income') {
                typeSelect.classList.add('type-income-active');
                typeSelect.classList.remove('type-expense-active');
                partyLabel.textContent = "<?php echo esc_js( __( 'Received From (Payer)', 'ifsedu-sms' ) ); ?>";
            } else {
                typeSelect.classList.add('type-expense-active');
                typeSelect.classList.remove('type-income-active');
                partyLabel.textContent = "<?php echo esc_js( __( 'Paid To / Vendor Name', 'ifsedu-sms' ) ); ?>";
            }
        }

        if (typeSelect) {
            typeSelect.addEventListener('change', updateCategories);
            updateCategories();
        }

        function updatePreview() {
            const tVal = titleInput ? titleInput.value.trim() : '';
            const aVal = amountInput ? parseFloat(amountInput.value) || 0 : 0;

            if (previewTitle) previewTitle.textContent = tVal !== '' ? tVal : '<?php echo esc_js( __( 'New General Ledger Voucher', 'ifsedu-sms' ) ); ?>';
            if (previewAmount) previewAmount.textContent = '৳' + aVal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        if (titleInput) titleInput.addEventListener('input', updatePreview);
        if (amountInput) amountInput.addEventListener('input', updatePreview);
        updatePreview();

        document.querySelectorAll('.dpt-chip-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!amountInput) return;
                if (this.id === 'educore_clear_amt') {
                    amountInput.value = '';
                } else {
                    let addVal = parseFloat(this.getAttribute('data-add')) || 0;
                    let curVal = parseFloat(amountInput.value) || 0;
                    amountInput.value = (curVal + addVal).toFixed(2);
                }
                amountInput.dispatchEvent(new Event('input'));
                updatePreview();
            });
        });

        function inWords(num) {
            const a = ['', 'One ', 'Two ', 'Three ', 'Four ', 'Five ', 'Six ', 'Seven ', 'Eight ', 'Nine ', 'Ten ', 'Eleven ', 'Twelve ', 'Thirteen ', 'Fourteen ', 'Fifteen ', 'Sixteen ', 'Seventeen ', 'Eighteen ', 'Nineteen '];
            const b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            if ((num = num.toString()).length > 9) return 'overflow';
            let n = ('000000000' + num).substr(-9).match(/^(\d{2})(\d{2})(\d{2})(\d{1})(\d{2})$/);
            if (!n) return ''; 
            let str = '';
            str += (n[1] != 0) ? (a[Number(n[1])] || b[n[1][0]] + ' ' + a[n[1][1]]) + 'Crore ' : '';
            str += (n[2] != 0) ? (a[Number(n[2])] || b[n[2][0]] + ' ' + a[n[2][1]]) + 'Lakh ' : '';
            str += (n[3] != 0) ? (a[Number(n[3])] || b[n[3][0]] + ' ' + a[n[3][1]]) + 'Thousand ' : '';
            str += (n[4] != 0) ? (a[Number(n[4])] || b[n[4][0]] + ' ' + a[n[4][1]]) + 'Hundred ' : '';
            str += (n[5] != 0) ? ((str != '') ? 'and ' : '') + (a[Number(n[5])] || b[n[5][0]] + ' ' + a[n[5][1]]) : '';
            return str ? str.trim() + ' Taka Only' : '';
        }

        if (amountInput && wordsPreview) {
            amountInput.addEventListener('input', function() {
                const val = Math.floor(parseFloat(this.value) || 0);
                if (val > 0) {
                    wordsPreview.textContent = inWords(val);
                } else {
                    wordsPreview.textContent = '';
                }
            });
            if (amountInput.value) {
                const val = Math.floor(parseFloat(amountInput.value) || 0);
                if (val > 0) wordsPreview.textContent = inWords(val);
            }
        }
    });
    </script>
    <?php
}