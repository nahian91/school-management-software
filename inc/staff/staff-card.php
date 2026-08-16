<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Lockdown direct access
}

/**
 * AJAX Handler: Fetch Staff Names by Staff Type
 */
add_action( 'wp_ajax_educore_get_staff_names_by_type', 'educore_get_staff_names_by_type_handler' );
function educore_get_staff_names_by_type_handler() {
    check_ajax_referer( 'educore_staff_id_nonce', 'security' );

    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';
    $staff_type  = isset( $_POST['staff_type'] ) ? sanitize_text_field( $_POST['staff_type'] ) : '';

    if ( empty( $staff_type ) ) {
        wp_send_json_success( array() );
    }

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, full_name, index_no FROM {$table_staff} WHERE staff_type = %s ORDER BY full_name ASC",
            $staff_type
        )
    );

    wp_send_json_success( $results );
}

/**
 * High-End Academic Staff ID Card Printing Engine
 * Schema: {$wpdb->prefix}sms_staff
 * Dimensions: CR80 Standard (85.6mm x 53.98mm)
 */
function educore_staff_id_cards_view() {
    global $wpdb;

    $table_staff = $wpdb->prefix . 'sms_staff';

    // Check Table Existence
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_staff ) ) !== $table_staff ) {
        echo '<div style="margin:20px; padding:16px; border-radius:8px; border-left:4px solid #ef4444; background:#fef2f2; color:#991b1b; font-weight:600;">';
        echo 'Database Error: Table <code>' . esc_html( $table_staff ) . '</code> does not exist.';
        echo '</div>';
        return;
    }

    // Trigger Parameters
    $is_loaded     = isset( $_GET['load_staff'] ) && $_GET['load_staff'] === '1';
    $selected_type = isset( $_GET['staff_type'] ) ? sanitize_text_field( $_GET['staff_type'] ) : '';
    $selected_id   = isset( $_GET['staff_id'] ) ? intval( $_GET['staff_id'] ) : 0;
    $search_query  = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

    // Fetch Staff Types
    $staff_types = $wpdb->get_results( "SELECT DISTINCT staff_type FROM {$table_staff} WHERE staff_type != '' ORDER BY staff_type ASC" );

    // Pre-fetch staff list for selected staff type if reloading page
    $type_staff_members = array();
    if ( ! empty( $selected_type ) ) {
        $type_staff_members = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, full_name, index_no FROM {$table_staff} WHERE staff_type = %s ORDER BY full_name ASC",
                $selected_type
            )
        );
    }

    $staff_members = array();

    // Query executed ONLY when requested
    if ( $is_loaded || ! empty( $selected_type ) || ! empty( $search_query ) || $selected_id > 0 ) {
        $where_clauses = array( "1=1" );
        $params        = array();

        if ( ! empty( $selected_type ) ) {
            $where_clauses[] = "staff_type = %s";
            $params[]        = $selected_type;
        }

        if ( $selected_id > 0 ) {
            $where_clauses[] = "id = %d";
            $params[]        = $selected_id;
        }

        if ( ! empty( $search_query ) ) {
            $where_clauses[] = "(full_name LIKE %s OR designation LIKE %s OR phone LIKE %s OR index_no LIKE %s OR nid_no LIKE %s)";
            $like_s          = '%' . $wpdb->esc_like( $search_query ) . '%';
            $params[]        = $like_s;
            $params[]        = $like_s;
            $params[]        = $like_s;
            $params[]        = $like_s;
            $params[]        = $like_s;
        }

        $where_sql = implode( ' AND ', $where_clauses );
        $sql       = "SELECT * FROM {$table_staff} WHERE {$where_sql} ORDER BY order_number ASC, id DESC";

        if ( ! empty( $params ) ) {
            $staff_members = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        } else {
            $staff_members = $wpdb->get_results( $sql );
        }
    }

    $site_name   = get_bloginfo( 'name' );
    $ajax_nonce  = wp_create_nonce( 'educore_staff_id_nonce' );
    ?>

 <style>
        /* Container UI */
        .afdp-id-controls-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 24px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .afdp-filter-form {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .afdp-filter-form select,
        .afdp-filter-form input[type="text"] {
            height: 40px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 0 14px;
            font-size: 13.5px;
            color: #1e293b;
            background: #f8fafc;
            transition: all 0.2s ease;
            max-width: 220px;
        }
        .afdp-filter-form select:focus,
        .afdp-filter-form input[type="text"]:focus {
            border-color: #006a4e;
            background: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.15);
        }

        .afdp-btn-primary {
            height: 40px;
            padding: 0 20px;
            background: linear-gradient(135deg, #006a4e 0%, #00523c 100%);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0, 106, 78, 0.2);
            transition: all 0.2s ease;
        }
        .afdp-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 106, 78, 0.3);
            color: #ffffff;
        }

        .afdp-btn-secondary {
            height: 40px;
            padding: 0 16px;
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .afdp-btn-secondary:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .afdp-btn-load {
            background: #0f172a;
            color: #ffffff;
            border: none;
        }
        .afdp-btn-load:hover {
            background: #1e293b;
            color: #ffffff;
        }

        /* Screen Grid */
        .dpt-id-cards-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            justify-content: flex-start;
        }

        /* Checkbox Indicator */
        .afdp-card-wrapper {
            position: relative;
        }
        .afdp-card-checkbox-label {
            position: absolute;
            top: -12px;
            left: 14px;
            z-index: 10;
            background: #0f172a;
            color: #ffffff;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10.5px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* ==========================================================================
           PREMIUM CR80 ID CARD DESIGN ARCHITECTURE (Full Name Visibility Fix)
           ========================================================================== */
        .dpt-id-card-unit {
            width: 85.6mm;
            height: 53.98mm;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 3.18mm;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            page-break-inside: avoid;
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.08), 0 2px 6px -1px rgba(0, 0, 0, 0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Card Header */
        .dpt-card-header {
            background: linear-gradient(135deg, #006a4e 0%, #004d38 100%);
            color: #ffffff;
            padding: 4px 8px;
            text-align: center;
            position: relative;
            border-bottom: 2.5px solid #f59e0b;
        }
        .dpt-card-header .dpt-inst-name {
            font-size: 8.5pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dpt-card-header .dpt-card-title {
            font-size: 4.5pt;
            letter-spacing: 1px;
            text-transform: uppercase;
            opacity: 0.85;
            font-weight: 600;
            margin-top: 1px;
        }

        /* Card Body */
        .dpt-card-body {
            padding: 5px 8px;
            display: flex;
            gap: 8px;
            align-items: center;
            flex: 1;
        }

        /* Photo Box */
        .dpt-photo-box {
            width: 19mm;
            height: 23mm;
            border-radius: 2mm;
            border: 1.5px solid #006a4e;
            overflow: hidden;
            background: #f1f5f9;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.06);
        }
        .dpt-photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .dpt-photo-box .dashicons {
            font-size: 26px;
            width: 26px;
            height: 26px;
            color: #cbd5e1;
        }

        /* Detail Area */
        .dpt-info-box {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        /* Full Name Display Fix: Scales down slightly and wraps nicely up to 2 lines */
        .dpt-info-name {
            font-size: 7.8pt;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 1.5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            word-break: break-word;
        }
        .dpt-info-designation {
            font-size: 5.5pt;
            font-weight: 700;
            color: #006a4e;
            text-transform: uppercase;
            margin-bottom: 3.5px;
            line-height: 1.1;
            letter-spacing: 0.2px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dpt-info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .dpt-info-table td {
            font-size: 5.2pt;
            padding: 0.5px 0;
            line-height: 1.1;
            vertical-align: top;
        }
        .dpt-info-table td.lbl {
            font-weight: 700;
            color: #64748b;
            width: 32%;
        }
        .dpt-info-table td.val {
            font-weight: 600;
            color: #1e293b;
        }

        /* Card Footer & Authority Sign */
        .dpt-card-footer {
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
            padding: 2px 8px 3px 8px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            height: 8.5mm;
        }
        .dpt-barcode-sim {
            font-family: 'Courier New', Courier, monospace;
            font-size: 5.2pt;
            font-weight: 900;
            letter-spacing: 1.2px;
            color: #1e293b;
        }
        .dpt-sign-block {
            text-align: center;
        }
        .dpt-sign-line {
            width: 16mm;
            border-top: 1px dashed #94a3b8;
            margin-bottom: 1px;
        }
        .dpt-sign-text {
            font-size: 4pt;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        /* Print Engine */
        @media print {
            #adminmenumain, #wpadminbar, #wpfooter, .no-print, .afdp-id-controls-card {
                display: none !important;
            }
            #wpcontent, #body {
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
            }
            html.wp-toolbar {
                padding-top: 0 !important;
            }

            @page {
                size: A4 portrait;
                margin: 8mm;
            }

            body {
                background: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .dpt-id-cards-grid {
                display: grid !important;
                grid-template-columns: repeat(2, 85.6mm) !important;
                gap: 6mm 8mm !important;
                justify-content: center !important;
                margin: 0 auto !important;
            }

            .dpt-id-card-unit {
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .afdp-card-wrapper.dpt-print-hide {
                display: none !important;
            }
            .afdp-card-checkbox-label {
                display: none !important;
            }
        }
    </style>

    <!-- Top Navigation & Control Panel -->
    <div class="afdp-id-controls-card no-print">
        <form method="get" class="afdp-filter-form">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="staff">
            <input type="hidden" name="sub" value="id_card">

            <!-- Primary Filter: Staff Type -->
            <select name="staff_type" id="staff_type_select" onchange="dptFetchStaffNames(this.value)">
                <option value="">-- All Staff Types --</option>
                <?php if ( ! empty( $staff_types ) ) : ?>
                    <?php foreach ( $staff_types as $st ) : ?>
                        <option value="<?php echo esc_attr( $st->staff_type ); ?>" <?php selected( $selected_type, $st->staff_type ); ?>>
                            <?php echo esc_html( ucfirst( $st->staff_type ) ); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <!-- Dependent Dropdown: Staff Names -->
            <select name="staff_id" id="staff_name_select" <?php echo empty( $selected_type ) ? 'disabled' : ''; ?>>
                <option value="">-- All Persons --</option>
                <?php if ( ! empty( $type_staff_members ) ) : ?>
                    <?php foreach ( $type_staff_members as $person ) : ?>
                        <option value="<?php echo esc_attr( $person->id ); ?>" <?php selected( $selected_id, $person->id ); ?>>
                            <?php echo esc_html( $person->full_name . ( $person->index_no ? ' (' . $person->index_no . ')' : '' ) ); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <input type="text" name="s" placeholder="Search phone, index..." value="<?php echo esc_attr( $search_query ); ?>">

            <button type="submit" name="load_staff" value="1" class="afdp-btn-secondary">
                <span class="dashicons dashicons-filter"></span> Filter & Load
            </button>
            
            <?php if ( ! $is_loaded && empty( $selected_type ) && empty( $search_query ) && $selected_id === 0 ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=staff&sub=id_card&load_staff=1' ) ); ?>" class="afdp-btn-secondary afdp-btn-load">
                    <span class="dashicons dashicons-groups"></span> Load All Staff
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=staff&sub=id_card' ) ); ?>" class="afdp-btn-secondary">
                    Clear / Reset
                </a>
            <?php endif; ?>
        </form>

        <?php if ( ! empty( $staff_members ) ) : ?>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="afdp-btn-secondary" onclick="dptToggleSelectAll(this)">
                    <span class="dashicons dashicons-checkbox"></span> Toggle Select All
                </button>
                <button type="button" class="afdp-btn-primary" onclick="window.print();">
                    <span class="dashicons dashicons-printer"></span> Print Selected Cards
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cards Layout Grid -->
    <?php if ( ! empty( $staff_members ) ) : ?>
        <div class="dpt-id-cards-grid" id="dptIDCardsGrid">
            <?php foreach ( $staff_members as $staff ) : 
                $photo_url    = ! empty( $staff->profile_image ) ? $staff->profile_image : '';
                $staff_code   = ! empty( $staff->index_no ) ? $staff->index_no : 'STF-' . str_pad( $staff->id, 4, '0', STR_PAD_LEFT );
                $joining_date = ( ! empty( $staff->joining_date ) && $staff->joining_date !== '1970-01-01' ) ? date( 'M Y', strtotime( $staff->joining_date ) ) : 'N/A';
                $blood_group  = ! empty( $staff->blood_group ) ? $staff->blood_group : 'N/A';
                $full_name    = ! empty( $staff->full_name ) ? $staff->full_name : 'Staff Member';
                $phone        = ! empty( $staff->phone ) ? $staff->phone : 'N/A';
                ?>
                
                <div class="afdp-card-wrapper" id="card-wrap-<?php echo esc_attr( $staff->id ); ?>">
                    <label class="afdp-card-checkbox-label no-print">
                        <input type="checkbox" class="dpt-card-select-cb" checked data-target="card-wrap-<?php echo esc_attr( $staff->id ); ?>" onchange="dptSyncCardPrintState(this)">
                        Print Card
                    </label>

                    <div class="dpt-id-card-unit">
                        <!-- Header -->
                        <div class="dpt-card-header">
                            <div class="dpt-inst-name"><?php echo esc_html( $site_name ); ?></div>
                            <div class="dpt-card-title">Staff Identity Card</div>
                        </div>

                        <!-- Body -->
                        <div class="dpt-card-body">
                            <div class="dpt-photo-box">
                                <?php if ( $photo_url ) : ?>
                                    <img src="<?php echo esc_url( $photo_url ); ?>" alt="Staff Photo">
                                <?php else : ?>
                                    <span class="dashicons dashicons-admin-users"></span>
                                <?php endif; ?>
                            </div>

                            <div class="dpt-info-box">
                                <div class="dpt-info-name"><?php echo esc_html( $full_name ); ?></div>
                                <div class="dpt-info-designation"><?php echo esc_html( ! empty( $staff->designation ) ? $staff->designation : 'Staff Member' ); ?></div>

                                <table class="dpt-info-table">
                                    <tr>
                                        <td class="lbl">ID / Index:</td>
                                        <td class="val"><?php echo esc_html( $staff_code ); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Phone:</td>
                                        <td class="val"><?php echo esc_html( $phone ); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Joined:</td>
                                        <td class="val"><?php echo esc_html( $joining_date ); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Blood:</td>
                                        <td class="val" style="color:#dc2626; font-weight:800;"><?php echo esc_html( $blood_group ); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="dpt-card-footer">
                            <div class="dpt-barcode-sim">||| | |||| | |||</div>
                            <div class="dpt-sign-block">
                                <div class="dpt-sign-line"></div>
                                <div class="dpt-sign-text">Authority Sign</div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        </div>
    <?php elseif ( $is_loaded || ! empty( $selected_type ) || ! empty( $search_query ) || $selected_id > 0 ) : ?>
        <div style="background: #ffffff; padding: 48px; border-radius: 12px; text-align: center; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <span class="dashicons dashicons-id-alt" style="font-size: 48px; width: 48px; height: 48px; color: #cbd5e1;"></span>
            <h3 style="margin: 12px 0 6px 0; color: #0f172a; font-weight:700;">No Staff Records Found</h3>
            <p style="color: #64748b; margin: 0;">No matching records were found in <code><?php echo esc_html( $table_staff ); ?></code> for your filter criteria.</p>
        </div>
    <?php else : ?>
        <!-- Idle State prior to loading -->
        <div style="background: #ffffff; padding: 50px 20px; border-radius: 12px; text-align: center; border: 1px dashed #cbd5e1;">
            <span class="dashicons dashicons-groups" style="font-size: 52px; width: 52px; height: 52px; color: #006a4e; opacity: 0.8;"></span>
            <h3 style="margin: 16px 0 8px 0; color: #0f172a; font-weight:700; font-size:18px;">Staff ID Card Printing Panel</h3>
            <p style="color: #64748b; max-width: 460px; margin: 0 auto 20px auto; font-size:14px; line-height:1.5;">
                Select a staff type (e.g. Office, Teacher) to view specific names, or click <strong>Load All Staff</strong> to preview all ID cards.
            </p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=staff&sub=id_card&load_staff=1' ) ); ?>" class="afdp-btn-primary" style="display:inline-flex;">
                <span class="dashicons dashicons-download"></span> Load Staff Records
            </a>
        </div>
    <?php endif; ?>

    <script>
        function dptFetchStaffNames(staffType) {
            var nameSelect = document.getElementById('staff_name_select');
            
            if (!staffType) {
                nameSelect.innerHTML = '<option value="">-- All Persons --</option>';
                nameSelect.disabled = true;
                return;
            }

            nameSelect.disabled = true;
            nameSelect.innerHTML = '<option value="">Loading...</option>';

            var formData = new FormData();
            formData.append('action', 'educore_get_staff_names_by_type');
            formData.append('security', '<?php echo esc_js( $ajax_nonce ); ?>');
            formData.append('staff_type', staffType);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var options = '<option value="">-- All Persons --</option>';
                    data.data.forEach(function(person) {
                        var extra = person.index_no ? ' (' + person.index_no + ')' : '';
                        options += '<option value="' + person.id + '">' + person.full_name + extra + '</option>';
                    });
                    nameSelect.innerHTML = options;
                    nameSelect.disabled = false;
                } else {
                    nameSelect.innerHTML = '<option value="">-- All Persons --</option>';
                }
            })
            .catch(function() {
                nameSelect.innerHTML = '<option value="">-- All Persons --</option>';
            });
        }

        function dptSyncCardPrintState(cb) {
            var targetId = cb.getAttribute('data-target');
            var wrapper = document.getElementById(targetId);
            if (wrapper) {
                if (cb.checked) {
                    wrapper.classList.remove('dpt-print-hide');
                } else {
                    wrapper.classList.add('dpt-print-hide');
                }
            }
        }

        function dptToggleSelectAll(btn) {
            var checkboxes = document.querySelectorAll('.dpt-card-select-cb');
            var allChecked = true;

            checkboxes.forEach(function(cb) {
                if (!cb.checked) allChecked = false;
            });

            checkboxes.forEach(function(cb) {
                cb.checked = !allChecked;
                dptSyncCardPrintState(cb);
            });
        }
    </script>
    <?php
}