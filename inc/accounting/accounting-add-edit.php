<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Record Income / Expense Transaction View
 * File: accounting-add-edit.php
 */
function educore_accounting_add_edit_view() {
    global $wpdb;

    $table_accounting = $wpdb->prefix . 'sms_accounting';
    $notice_message   = '';

    // Handle Form Submission
    if ( isset( $_POST['educore_save_accounting_entry'] ) && isset( $_POST['educore_acct_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_acct_nonce'] ) ), 'save_acct_action' ) ) {
        $entry_type     = isset( $_POST['entry_type'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_type'] ) ) : 'Income';
        $category_name  = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
        $title          = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $amount         = isset( $_POST['amount'] ) ? max( 0, floatval( $_POST['amount'] ) ) : 0;
        $entry_date     = isset( $_POST['entry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_date'] ) ) : current_time( 'Y-m-d' );
        $payment_method = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'Cash';
        $voucher_no     = isset( $_POST['voucher_no'] ) ? sanitize_text_field( wp_unslash( $_POST['voucher_no'] ) ) : 'VOU-' . wp_rand( 10000, 99999 );
        $note           = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';

        if ( ! empty( $title ) && $amount > 0 ) {
            $wpdb->insert(
                $table_accounting,
                array(
                    'voucher_no'     => $voucher_no,
                    'entry_type'     => $entry_type,
                    'category_name'  => $category_name,
                    'title'          => $title,
                    'amount'         => $amount,
                    'payment_method' => $payment_method,
                    'entry_date'     => $entry_date,
                    'note'           => $note,
                    'created_by'     => get_current_user_id()
                ),
                array( '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%d' )
            );
            $notice_message = esc_html__( 'Transaction entry recorded successfully.', 'ifsedu-sms' );
        }
    }
    ?>

    <style>
        .dpt-add-acct-container {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            max-width: 800px;
            margin: 0 auto;
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
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* 2-Column Grid Layout */
        .dpt-form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .dpt-form-group.full-width {
            grid-column: span 2;
        }

        @media (max-width: 640px) {
            .dpt-form-grid {
                grid-template-columns: 1fr;
            }
            .dpt-form-group.full-width {
                grid-column: span 1;
            }
        }

        .dpt-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dpt-form-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 4px;
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
            height: 80px;
            resize: vertical;
        }

        .dpt-input-field:focus, .dpt-select-field:focus, .dpt-textarea-field:focus {
            border-color: #10b981;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
            outline: none;
        }

        /* Dynamic Indicator Colors for Flow Type */
        .dpt-select-field.type-income-active {
            border-color: #10b981;
            background-color: #ecfdf5;
            color: #065f46;
        }

        .dpt-select-field.type-expense-active {
            border-color: #ef4444;
            background-color: #fef2f2;
            color: #991b1b;
        }

        .dpt-btn-submit {
            height: 46px;
            background: #10b981;
            border: none;
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            margin-top: 10px;
        }

        .dpt-btn-submit:hover {
            background: #059669;
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
        }

        .afdp-status-banner {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            padding: 14px 20px;
            color: #065f46;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>

    <div class="dpt-add-acct-container">

        <?php if ( ! empty( $notice_message ) ) : ?>
            <div class="afdp-status-banner">
                <span class="dashicons dashicons-yes-alt" style="font-size: 20px; width:20px; height:20px; color:#10b981;"></span>
                <?php echo esc_html( $notice_message ); ?>
            </div>
        <?php endif; ?>

        <div class="dpt-bento-card">
            
            <div class="afdp-card-title-group">
                <h4 class="afdp-card-title">
                    <span class="dashicons dashicons-plus-alt2" style="color: #10b981;"></span>
                    <?php esc_html_e( 'Record Financial Entry', 'ifsedu-sms' ); ?>
                </h4>
                <span style="font-size: 12px; color: #64748b; font-weight: 600;"><?php esc_html_e( 'General Ledger', 'ifsedu-sms' ); ?></span>
            </div>

            <form method="POST" action="">
                <?php wp_nonce_field( 'save_acct_action', 'educore_acct_nonce' ); ?>
                
                <div class="dpt-form-grid">
                    
                    <!-- Flow Type -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Flow Type', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="entry_type" id="educore_entry_type" class="dpt-select-field type-income-active" style="font-weight:700;" required>
                            <option value="Income"><?php esc_html_e( 'Income (+ Credit)', 'ifsedu-sms' ); ?></option>
                            <option value="Expense"><?php esc_html_e( 'Expense (- Debit)', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- Amount -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Amount (৳)', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="number" step="0.01" name="amount" class="dpt-input-field" style="font-weight:800; font-size:15px;" placeholder="0.00" min="0.01" required>
                    </div>

                    <!-- Title -->
                    <div class="dpt-form-group full-width">
                        <label class="dpt-form-label"><?php esc_html_e( 'Transaction Purpose / Title', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="title" class="dpt-input-field" placeholder="e.g. Electrical Repair Bill / Government Grant" required>
                    </div>

                    <!-- Category -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Category', 'ifsedu-sms' ); ?></label>
                        <select name="category_name" id="educore_category_select" class="dpt-select-field">
                            <!-- Populated dynamically via JS -->
                        </select>
                    </div>

                    <!-- Payment Method -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Payment Method', 'ifsedu-sms' ); ?></label>
                        <select name="payment_method" class="dpt-select-field">
                            <option value="Cash"><?php esc_html_e( 'Cash In Hand', 'ifsedu-sms' ); ?></option>
                            <option value="Bank Transfer"><?php esc_html_e( 'Bank Transfer', 'ifsedu-sms' ); ?></option>
                            <option value="bKash / Mobile"><?php esc_html_e( 'bKash / Mobile Wallet', 'ifsedu-sms' ); ?></option>
                            <option value="Cheque"><?php esc_html_e( 'Cheque', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <!-- Voucher No -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Voucher / Ref No.', 'ifsedu-sms' ); ?></label>
                        <input type="text" name="voucher_no" class="dpt-input-field" value="<?php echo esc_attr( 'VOU-' . wp_rand( 10000, 99999 ) ); ?>">
                    </div>

                    <!-- Transaction Date -->
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Transaction Date', 'ifsedu-sms' ); ?></label>
                        <input type="date" name="entry_date" class="dpt-input-field" value="<?php echo esc_attr( current_time('Y-m-d') ); ?>" required>
                    </div>

                    <!-- Notes -->
                    <div class="dpt-form-group full-width">
                        <label class="dpt-form-label"><?php esc_html_e( 'Notes / Description', 'ifsedu-sms' ); ?></label>
                        <textarea name="note" class="dpt-textarea-field" placeholder="Additional details or remarks..."></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="dpt-form-group full-width">
                        <button type="submit" name="educore_save_accounting_entry" class="dpt-btn-submit">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e( 'Record Transaction', 'ifsedu-sms' ); ?>
                        </button>
                    </div>

                </div>
            </form>
        </div>

    </div>

    <!-- Inline Script for Smart Dynamic Categories -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const typeSelect = document.getElementById('educore_entry_type');
        const categorySelect = document.getElementById('educore_category_select');

        const incomeCategories = [
            'General Income',
            'Government Grant',
            'Donation / Sponsorship',
            'Facility Rental',
            'Other Income'
        ];

        const expenseCategories = [
            'Utility Bills',
            'Maintenance & Repair',
            'Office & Supplies',
            'Staff Refreshment',
            'Property Rent',
            'Other Expenses'
        ];

        function updateCategories() {
            const selectedType = typeSelect.value;
            categorySelect.innerHTML = '';

            let activeList = selectedType === 'Income' ? incomeCategories : expenseCategories;

            activeList.forEach(function(cat) {
                let opt = document.createElement('option');
                opt.value = cat;
                opt.textContent = cat;
                categorySelect.appendChild(opt);
            });

            if (selectedType === 'Income') {
                typeSelect.classList.add('type-income-active');
                typeSelect.classList.remove('type-expense-active');
            } else {
                typeSelect.classList.add('type-expense-active');
                typeSelect.classList.remove('type-income-active');
            }
        }

        typeSelect.addEventListener('change', updateCategories);
        updateCategories(); // Initial load trigger
    });
    </script>
    <?php
}