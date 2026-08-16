<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fee Configuration, Categories & Class-wise Fee Structure Matrix
 * File: inc/fees/fees-settings.php
 */
function educore_fees_settings_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access this page.', 'ifsedu-sms' ) );
    }

    global $wpdb;
    $table_fee_types = $wpdb->prefix . 'sms_fee_types';
    $table_units     = $wpdb->prefix . 'sms_academic_units';

    // --------------------------------------------------------------------------
    // 0. AUTO-SCHEMA CHECK (Ensures Fee Types Table Exists)
    // --------------------------------------------------------------------------
    $check_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_fee_types}'" );
    if ( empty( $check_table ) ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $sql_fee_types = "CREATE TABLE {$table_fee_types} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            class_name varchar(50) NOT NULL,
            fee_title varchar(150) NOT NULL,
            amount decimal(10,2) DEFAULT '0.00' NOT NULL,
            period_type varchar(30) DEFAULT 'Monthly' NOT NULL,
            description text,
            PRIMARY KEY  (id),
            KEY class_idx (class_name)
        ) $charset_collate;";
        dbDelta( $sql_fee_types );
    }

    // --------------------------------------------------------------------------
    // 1. FORM SUBMISSION (Save Class-wise Fee Structures with Repeater)
    // --------------------------------------------------------------------------
    if ( isset( $_POST['save_class_fee_structure'] ) && check_admin_referer( 'educore_save_fees_settings_action', 'educore_fees_settings_nonce' ) ) {
        $target_class = isset( $_POST['target_class'] ) ? sanitize_text_field( wp_unslash( $_POST['target_class'] ) ) : '';
        $fee_titles   = isset( $_POST['fee_title'] ) && is_array( $_POST['fee_title'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fee_title'] ) ) : array();
        $fee_amounts  = isset( $_POST['amount'] ) && is_array( $_POST['amount'] ) ? array_map( 'floatval', $_POST['amount'] ) : array();
        $period_types = isset( $_POST['period_type'] ) && is_array( $_POST['period_type'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['period_type'] ) ) : array();

        if ( ! empty( $target_class ) && ! empty( $fee_titles ) ) {
            // Remove existing fee mappings for this specific class to avoid duplicates
            $wpdb->delete( $table_fee_types, array( 'class_name' => $target_class ), array( '%s' ) );

            $inserted_count = 0;
            foreach ( $fee_titles as $index => $title ) {
                $trimmed_title = trim( $title );
                $amount        = isset( $fee_amounts[ $index ] ) ? floatval( $fee_amounts[ $index ] ) : 0.00;
                $period        = isset( $period_types[ $index ] ) ? sanitize_text_field( $period_types[ $index ] ) : 'Monthly';

                if ( ! empty( $trimmed_title ) ) {
                    $wpdb->insert(
                        $table_fee_types,
                        array(
                            'class_name'  => $target_class,
                            'fee_title'   => $trimmed_title,
                            'amount'      => $amount,
                            'period_type' => $period,
                        ),
                        array( '%s', '%s', '%f', '%s' )
                    );
                    $inserted_count++;
                }
            }

            echo '<div class="notice notice-success is-dismissible" style="padding:12px; margin-bottom:20px; font-weight:700; border-left:4px solid #006a4e; background:#ecfdf5; color:#065f46;">' .
                 sprintf( esc_html__( 'Successfully mapped %d fee categories for Class: %s', 'ifsedu-sms' ), $inserted_count, esc_html( $target_class ) ) .
                 '</div>';
        }
    }

    // Handle Delete Single Fee Item
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_fee_type' && isset( $_GET['id'] ) ) {
        $del_id = absint( $_GET['id'] );
        $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( $del_id > 0 && wp_verify_nonce( $del_nonce, 'delete_fee_type_' . $del_id ) ) {
            $wpdb->delete( $table_fee_types, array( 'id' => $del_id ), array( '%d' ) );
            echo '<div class="notice notice-success is-dismissible" style="padding:12px; margin-bottom:20px; font-weight:700;">' . esc_html__( 'Fee category deleted.', 'ifsedu-sms' ) . '</div>';
        }
    }

    // --------------------------------------------------------------------------
    // 2. DATA QUERIES
    // --------------------------------------------------------------------------
    $raw_classes = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    $academic_classes = array();
    if ( ! empty( $raw_classes ) ) {
        $academic_classes = array_values( array_unique( $raw_classes ) );
        usort( $academic_classes, 'strnatcasecmp' );
    }

    $all_fee_types = $wpdb->get_results( "SELECT * FROM {$table_fee_types} ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC, fee_title ASC" );
    ?>

    <style>
        .dpt-fees-settings-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .dpt-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px 28px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .dpt-card-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dpt-card-title {
            font-size: 18px;
            font-weight: 800;
            color: #006a4e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
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
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .dpt-input, .dpt-select {
            height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            width: 100%;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .dpt-input:focus, .dpt-select:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
        }

        /* Repeater Engine Styling */
        .dpt-repeater-canvas {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 16px;
        }

        .dpt-repeater-row {
            display: grid;
            grid-template-columns: 2fr 1.2fr 1.5fr 42px;
            gap: 12px;
            align-items: end;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
        }

        @media (max-width: 768px) {
            .dpt-repeater-row { grid-template-columns: 1fr; }
        }

        .dpt-btn-remove {
            height: 42px;
            width: 42px;
            border-radius: 8px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .dpt-btn-remove:hover { background: #dc2626; color: #ffffff; }

        .dpt-btn-add {
            background: #f0fdf4;
            color: #166534;
            border: 1px dashed #86efac;
            width: 100%;
            height: 42px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .dpt-btn-add:hover { background: #dcfce7; border-color: #4ade80; }

        .dpt-btn-submit {
            height: 44px;
            background: #006a4e;
            color: #ffffff;
            font-weight: 800;
            font-size: 14px;
            border-radius: 10px;
            padding: 0 28px;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .dpt-btn-submit:hover { background: #00523c; }

        /* Data Directory Table */
        .dpt-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13.5px;
        }

        .dpt-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
        }

        .dpt-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .dpt-table tbody tr:hover td { background-color: #f8fafc; }
    </style>

    <div class="dpt-fees-settings-root">

        <!-- Setup Form Card -->
        <div class="dpt-bento-card">
            <div class="dpt-card-header">
                <h4 class="dpt-card-title">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php esc_html_e( 'Class-wise Fee Category & Amount Setup', 'ifsedu-sms' ); ?>
                </h4>
            </div>

            <form method="POST" action="">
                <?php wp_nonce_field( 'educore_save_fees_settings_action', 'educore_fees_settings_nonce' ); ?>

                <div class="dpt-form-group" style="max-width: 380px; margin-bottom: 20px;">
                    <label class="dpt-form-label"><?php esc_html_e( 'Select Target Class', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                    <select name="target_class" id="target_class_selector" class="dpt-select" required>
                        <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $academic_classes as $cls_name ) : ?>
                            <option value="<?php echo esc_attr( $cls_name ); ?>"><?php printf( esc_html__( 'Class %s', 'ifsedu-sms' ), esc_html( $cls_name ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="dpt-form-label" style="margin-bottom: 8px; display:block;"><?php esc_html_e( 'Fee Categories & Distribution', 'ifsedu-sms' ); ?></label>

                <div id="dpt-fee-repeater-canvas" class="dpt-repeater-canvas">
                    <div class="dpt-repeater-row">
                        <div class="dpt-form-group">
                            <label class="dpt-form-label"><?php esc_html_e( 'Fee Title / Category', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                            <input type="text" name="fee_title[]" class="dpt-input" placeholder="e.g. Monthly Tuition Fee / Exam Fee" required>
                        </div>

                        <div class="dpt-form-group">
                            <label class="dpt-form-label"><?php esc_html_e( 'Amount (৳)', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                            <input type="number" step="0.01" min="0" name="amount[]" class="dpt-input" placeholder="0.00" required>
                        </div>

                        <div class="dpt-form-group">
                            <label class="dpt-form-label"><?php esc_html_e( 'Billing Period', 'ifsedu-sms' ); ?></label>
                            <select name="period_type[]" class="dpt-select">
                                <option value="Monthly"><?php esc_html_e( 'Monthly', 'ifsedu-sms' ); ?></option>
                                <option value="Term/Exam"><?php esc_html_e( 'Term / Exam-wise', 'ifsedu-sms' ); ?></option>
                                <option value="Annual/Admission"><?php esc_html_e( 'Annual / Admission', 'ifsedu-sms' ); ?></option>
                                <option value="One-Time"><?php esc_html_e( 'One-Time / Miscellaneous', 'ifsedu-sms' ); ?></option>
                            </select>
                        </div>

                        <div>
                            <button type="button" class="dpt-btn-remove btn-remove-fee" title="<?php esc_attr_e( 'Remove Row', 'ifsedu-sms' ); ?>">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="button" id="btn-add-fee-row" class="dpt-btn-add">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Add Another Fee Category Row', 'ifsedu-sms' ); ?>
                </button>

                <button type="submit" name="save_class_fee_structure" class="dpt-btn-submit">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save Fee Structure for Selected Class', 'ifsedu-sms' ); ?>
                </button>
            </form>
        </div>

        <!-- Mapped Fee Matrix Directory Card -->
        <div class="dpt-bento-card">
            <div class="dpt-card-header">
                <h4 class="dpt-card-title">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Configured Class Fee Directory', 'ifsedu-sms' ); ?>
                </h4>
            </div>

            <div style="overflow-x: auto;">
                <table class="dpt-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;"><?php esc_html_e( 'Class Name', 'ifsedu-sms' ); ?></th>
                            <th style="width: 35%;"><?php esc_html_e( 'Fee Title / Category', 'ifsedu-sms' ); ?></th>
                            <th style="width: 15%;"><?php esc_html_e( 'Amount', 'ifsedu-sms' ); ?></th>
                            <th style="width: 18%;"><?php esc_html_e( 'Billing Cycle', 'ifsedu-sms' ); ?></th>
                            <th style="width: 12%; text-align: right;"><?php esc_html_e( 'Action', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $all_fee_types ) ) : foreach ( $all_fee_types as $item ) : 
                            $del_link = wp_nonce_url(
                                add_query_arg( array( 'action' => 'delete_fee_type', 'id' => $item->id ) ),
                                'delete_fee_type_' . $item->id
                            );
                        ?>
                            <tr>
                                <td><strong style="color: #006a4e;"><?php printf( esc_html__( 'Class %s', 'ifsedu-sms' ), esc_html( $item->class_name ) ); ?></strong></td>
                                <td><strong style="color: #0f172a;"><?php echo esc_html( $item->fee_title ); ?></strong></td>
                                <td><span style="background: #f0fdf4; color: #166534; padding: 2px 8px; border-radius: 4px; font-weight: 800;">৳<?php echo number_format( floatval( $item->amount ), 2 ); ?></span></td>
                                <td><span style="background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 700;"><?php echo esc_html( $item->period_type ); ?></span></td>
                                <td style="text-align: right;">
                                    <a href="<?php echo esc_url( $del_link ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove this fee category?', 'ifsedu-sms' ) ); ?>');" style="color:#dc2626; text-decoration:none; font-size:16px;">
                                        <span class="dashicons dashicons-trash"></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 30px;">
                                    <?php esc_html_e( 'No class-wise fee categories configured yet.', 'ifsedu-sms' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Client-Side Repeater Scripts -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const canvas = document.getElementById('dpt-fee-repeater-canvas');
        const addBtn = document.getElementById('btn-add-fee-row');

        if (addBtn && canvas) {
            addBtn.addEventListener('click', function() {
                const rows = canvas.querySelectorAll('.dpt-repeater-row');
                const newRow = rows[0].cloneNode(true);

                newRow.querySelectorAll('input').forEach(inp => inp.value = '');
                newRow.querySelector('select').selectedIndex = 0;

                canvas.appendChild(newRow);
            });

            canvas.addEventListener('click', function(e) {
                const removeBtn = e.target.closest('.btn-remove-fee');
                if (removeBtn) {
                    const rows = canvas.querySelectorAll('.dpt-repeater-row');
                    if (rows.length > 1) {
                        removeBtn.closest('.dpt-repeater-row').remove();
                    } else {
                        alert('<?php echo esc_js( __( 'At least one fee category row is required.', 'ifsedu-sms' ) ); ?>');
                    }
                }
            });
        }
    });
    </script>
    <?php
}