<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * High-End Academic Architecture Configuration & Management Module
 * Custom Prefixes Applied: dpt-, afdp-
 * Architecture: Bento Grid Dashboard Component with Kinetic Field Toggling
 */
function educore_academics_tab() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sms_academic_units';

    // Security Check: User Capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage academic configurations.', 'educore' ) );
    }

    $message_type = '';
    $message_text = '';

    // Determine Edit Mode
    $is_edit = isset( $_GET['action'] ) && $_GET['action'] === 'edit_unit' && isset( $_GET['id'] );
    $edit_id = $is_edit ? intval( $_GET['id'] ) : 0;
    $edit_row = null;

    if ( $is_edit && $edit_id > 0 ) {
        $edit_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $edit_id ) );
        if ( ! $edit_row ) {
            $is_edit = false; // Fallback if record not found
        }
    }

    // 1. Handle Form Submissions (INSERT / UPDATE)
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['educore_academic_nonce'] ) ) {
        if ( wp_verify_nonce( $_POST['educore_academic_nonce'], 'academic_setup_action' ) ) {
            if ( isset( $_POST['save_academic_row'] ) ) {
                $type = sanitize_text_field( $_POST['unit_type'] ); // 'School' or 'College'
                $row_id = isset( $_POST['row_id'] ) ? intval( $_POST['row_id'] ) : 0;
                
                if ( $type === 'College' ) {
                    $dept    = sanitize_text_field( trim( $_POST['dept_name'] ) );
                    $class   = sanitize_text_field( trim( $_POST['academic_year'] ) );
                    $section = '';
                } else {
                    $dept    = '';
                    $class   = sanitize_text_field( trim( $_POST['class_name'] ) );
                    $section = sanitize_text_field( trim( $_POST['section_name'] ) );
                }

                if ( ! empty( $class ) && ( $type === 'College' ? ! empty( $dept ) : ! empty( $section ) ) ) {
                    // Duplication Check Matrix (excluding current record if editing)
                    $dup_query = "SELECT id FROM {$table_name} WHERE unit_type = %s AND class_name = %s AND section_name = %s AND dept_name = %s";
                    $dup_params = array( $type, $class, $section, $dept );

                    if ( $row_id > 0 ) {
                        $dup_query .= " AND id != %d";
                        $dup_params[] = $row_id;
                    }

                    $is_duplicate = $wpdb->get_var( $wpdb->prepare( $dup_query, $dup_params ) );

                    if ( ! $is_duplicate ) {
                        $data = array(
                            'unit_type'    => $type,
                            'class_name'   => $class,
                            'section_name' => $section,
                            'dept_name'    => $dept,
                        );
                        $format = array( '%s', '%s', '%s', '%s' );

                        if ( $row_id > 0 ) {
                            // UPDATE existing row
                            $updated = $wpdb->update( $table_name, $data, array( 'id' => $row_id ), $format, array( '%d' ) );
                            if ( $updated !== false ) {
                                if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                                    IFSEdu_School_Management_System::log_activity( "Updated academic unit ID {$row_id}: {$class}" );
                                }
                                $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=academics&status=updated' );
                                echo '<script type="text/javascript">window.location.href="' . esc_url( $redirect_url ) . '";</script>';
                                exit;
                            }
                        } else {
                            // INSERT new row
                            $inserted = $wpdb->insert( $table_name, $data, $format );
                            if ( $inserted ) {
                                if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                                    IFSEdu_School_Management_System::log_activity( "Added academic unit ({$type}): {$class}" );
                                }
                                $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=academics&status=success' );
                                echo '<script type="text/javascript">window.location.href="' . esc_url( $redirect_url ) . '";</script>';
                                exit;
                            }
                        }
                    } else {
                        $message_type = 'warning';
                        $message_text = __( 'This exact academic unit configuration already exists.', 'educore' );
                    }
                } else {
                    $message_type = 'danger';
                    $message_text = __( 'Please fill in all required fields for the selected tier.', 'educore' );
                }
            }
        } else {
            $message_type = 'danger';
            $message_text = __( 'Security nonce validation failed. Access denied.', 'educore' );
        }
    }

    // 2. Handle Delete Actions
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_unit' && isset( $_GET['id'] ) ) {
        $id_to_delete = intval( $_GET['id'] );
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_unit_action_' . $id_to_delete ) ) {
            $deleted = $wpdb->delete( $table_name, array( 'id' => $id_to_delete ), array( '%d' ) );
            if ( $deleted ) {
                if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                    IFSEdu_School_Management_System::log_activity( "Deleted academic unit ID: {$id_to_delete}" );
                }
                $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=academics&status=deleted' );
                echo '<script type="text/javascript">window.location.href="' . esc_url( $redirect_url ) . '";</script>';
                exit;
            }
        } else {
            $message_type = 'danger';
            $message_text = __( 'Security check failed. Unable to delete record.', 'educore' );
        }
    }

    // Capture URL feedback notices
    if ( isset( $_GET['status'] ) ) {
        if ( $_GET['status'] === 'success' ) {
            $message_type = 'success';
            $message_text = __( 'Academic unit added successfully.', 'educore' );
        } elseif ( $_GET['status'] === 'updated' ) {
            $message_type = 'success';
            $message_text = __( 'Academic unit updated successfully.', 'educore' );
        } elseif ( $_GET['status'] === 'deleted' ) {
            $message_type = 'success';
            $message_text = __( 'Academic unit deleted successfully.', 'educore' );
        }
    }

    // 3. Fetch Data
    $academic_rows = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY unit_type DESC, class_name ASC, dept_name ASC" );
    $cancel_url    = admin_url( 'admin.php?page=school_management_system&tab=academics' );
    ?>

    <style>
        /* ==========================================================================
           1. BENTO DASHBOARD CORE COMPONENT STYLING
           ========================================================================== */
        .dpt-academics-root {
            margin: 20px 20px 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

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

        /* Bento Workspace Box */
        .dpt-bento-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            margin-bottom: 24px;
        }

        .dpt-bento-subheading {
            font-size: 15px;
            font-weight: 800;
            color: #1e293b;
            margin: 0 0 18px 0;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
        }

        /* Notice & Alert Management System */
        .afdp-alert-node {
            border-radius: 10px;
            padding: 14px 18px;
            font-weight: 600;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid transparent;
        }
        .afdp-alert-success { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        .afdp-alert-warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        .afdp-alert-danger  { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }

        /* Tactical Inline Grid Structure */
        .dpt-field-matrix-grid {
            display: flex;
            align-items: flex-end;
            gap: 16px;
            width: 100%;
            flex-wrap: wrap;
        }
        .dpt-input-wrapper { 
            flex: 1; 
            min-width: 200px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .dpt-type-wrapper { max-width: 180px; flex-shrink: 0; }
        .dpt-action-wrapper { min-width: 200px; max-width: 260px; flex-shrink: 0; }

        .dpt-form-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            margin: 0;
        }
        .dpt-field-input, .dpt-field-select {
            height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 14px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            width: 100%;
            box-shadow: none;
            transition: all 0.2s;
        }
        .dpt-field-input:focus, .dpt-field-select:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
            outline: none;
        }

        /* Kinetic Form Submissions Controls */
        .dpt-btn-action-trigger {
            height: 42px;
            background: #006a4e;
            border: 1px solid transparent;
            color: #ffffff;
            font-weight: 700;
            font-size: 13.5px;
            border-radius: 8px;
            padding: 0 20px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.15);
            text-decoration: none;
        }
        .dpt-btn-action-trigger:hover {
            background: #00523c;
            color: #ffffff;
            transform: translateY(-0.5px);
        }
        .dpt-btn-cancel-trigger {
            height: 42px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-weight: 600;
            font-size: 13.5px;
            border-radius: 8px;
            padding: 0 16px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s;
        }
        .dpt-btn-cancel-trigger:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #cbd5e1;
        }

        /* Roster Elements & Custom Tier Badges */
        .afdp-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 14px;
        }
        .afdp-header-bar h6 {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            color: #1e293b;
        }
        .dpt-count-pill {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 12px;
        }

        .dpt-tier-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            border: 1px solid transparent;
        }
        .dpt-badge-school { background: #e0f2fe; border-color: #bae6fd; color: #0369a1; }
        .dpt-badge-college { background: #ecfdf4; border-color: #a7f3d0; color: #15803d; }

        /* Datatable Grid Matrix Layout */
        .dpt-responsive-datatable {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        .dpt-architecture-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
        }
        .dpt-architecture-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 12.5px;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .dpt-architecture-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13.5px;
            color: #334155;
            background: #ffffff;
        }
        .dpt-architecture-table tr:last-child td {
            border-bottom: none;
        }
        .dpt-architecture-table tr:hover td {
            background: #f8fafc;
        }

        /* Table Grid Row Focus States */
        .dpt-tr-edit-focus td {
            background: #fffbeb !important;
            border-bottom: 1px solid #fef3c7;
        }

        /* Action Controls Layout */
        .afdp-action-cluster {
            display: inline-flex;
            gap: 6px;
        }
        .dpt-square-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .dpt-square-btn .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        .dpt-square-btn-edit:hover { border-color: #2563eb; color: #2563eb; background: #eff6ff; }
        .dpt-square-btn-delete:hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }

        .afdp-empty-row {
            text-align: center;
            padding: 40px 20px !important;
            color: #64748b;
            font-weight: 600;
        }
        .afdp-empty-row .dashicons {
            font-size: 32px;
            width: 32px;
            height: 32px;
            color: #94a3b8;
            margin-bottom: 8px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        /* ==========================================================================
           2. HARDWARE PRINT METRICS INCLUSIONS
           ========================================================================== */
        @media print {
            .no-print, 
            .dpt-bento-box:first-of-type,
            .afdp-header-bar span.badge {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .dpt-academics-root { margin: 0 !important; }
            .dpt-bento-box { border: none !important; box-shadow: none !important; padding: 0 !important; }
            .dpt-architecture-table th { background: #ffffff !important; color: #000000 !important; border-bottom: 2px solid #000000 !important; }
            .dpt-architecture-table td { background: #ffffff !important; color: #000000 !important; }
            .dpt-tier-badge { border: 1px solid #000000 !important; background: transparent !important; color: #000000 !important; padding: 2px 8px !important; }
        }
    </style>

    <div class="dpt-academics-root">
        
        <div class="afdp-header-frame no-print">
            <h2>
                <span class="dashicons dashicons-welcome-learn-more"></span> Academic Architecture Setup
            </h2>
            <p>Manage School (Class/Section) and College (Department/Year) structural units.</p>
        </div>

        <!-- Alert Notifications Node Matrix -->
        <?php if ( ! empty( $message_text ) ) : ?>
            <div class="afdp-alert-node afdp-alert-<?php echo esc_attr( $message_type ); ?> no-print" role="alert">
                <span><strong><?php echo $message_type === 'success' ? 'Success:' : 'Notice:'; ?></strong> <?php echo esc_html( $message_text ); ?></span>
            </div>
        <?php endif; ?>

        <!-- Dynamic Filter Input Control Panel Bento Block -->
        <div class="dpt-bento-box no-print">
            <h5 class="dpt-bento-subheading">
                <?php echo $is_edit ? 'Edit Academic Configuration' : 'Add Academic Configuration'; ?>
            </h5>
            
            <form method="POST" action="">
                <?php wp_nonce_field( 'academic_setup_action', 'educore_academic_nonce' ); ?>
                <input type="hidden" name="row_id" value="<?php echo $is_edit ? intval( $edit_row->id ) : 0; ?>">

                <div class="dpt-field-matrix-grid">
                    <!-- Structure Type Selector Tier -->
                    <div class="dpt-input-wrapper dpt-type-wrapper">
                        <label class="dpt-form-label">Tier / Type</label>
                        <select name="unit_type" id="educore-unit-type-select" class="dpt-field-select" required>
                            <option value="School" <?php selected( $is_edit ? $edit_row->unit_type : '', 'School' ); ?>>School Tier</option>
                            <option value="College" <?php selected( $is_edit ? $edit_row->unit_type : '', 'College' ); ?>>College Tier</option>
                        </select>
                    </div>

                    <!-- SCHOOL TIER INTERFACE: Class & Section Fields -->
                    <div class="dpt-input-wrapper school-field">
                        <label class="dpt-form-label">Class Name <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="class_name" id="school-class-input" class="dpt-field-input" placeholder="e.g. Class 9, Class 10" value="<?php echo ( $is_edit && $edit_row->unit_type === 'School' ) ? esc_attr( $edit_row->class_name ) : ''; ?>">
                    </div>

                    <div class="dpt-input-wrapper school-field">
                        <label class="dpt-form-label">Section Name <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="section_name" id="school-section-input" class="dpt-field-input" placeholder="e.g. Section A, Padma" value="<?php echo ( $is_edit && $edit_row->unit_type === 'School' ) ? esc_attr( $edit_row->section_name ) : ''; ?>">
                    </div>

                    <!-- COLLEGE TIER INTERFACE: Department & Year Fields -->
                    <div class="dpt-input-wrapper college-field" style="display: none;">
                        <label class="dpt-form-label">Department / Group <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="dept_name" id="college-dept-input" class="dpt-field-input" placeholder="e.g. Science, Arts, BBA" value="<?php echo ( $is_edit && $edit_row->unit_type === 'College' ) ? esc_attr( $edit_row->dept_name ) : ''; ?>">
                    </div>

                    <div class="dpt-input-wrapper college-field" style="display: none;">
                        <label class="dpt-form-label">Academic Year <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="academic_year" id="college-year-input" class="dpt-field-input" placeholder="e.g. 11th Year, 1st Year" value="<?php echo ( $is_edit && $edit_row->unit_type === 'College' ) ? esc_attr( $edit_row->class_name ) : ''; ?>">
                    </div>

                    <!-- Submit & Mutation Controls Actions -->
                    <div class="dpt-input-wrapper dpt-action-wrapper">
                        <div style="display:flex; gap:8px; width:100%;">
                            <button type="submit" name="save_academic_row" class="dpt-btn-action-trigger" style="flex:1;">
                                <span class="dashicons <?php echo $is_edit ? 'dashicons-edit' : 'dashicons-plus-alt2'; ?>"></span>
                                <?php echo $is_edit ? 'Update Row' : 'Insert Row'; ?>
                            </button>

                            <?php if ( $is_edit ) : ?>
                                <a href="<?php echo esc_url( $cancel_url ); ?>" class="dpt-btn-cancel-trigger" title="Cancel Editing">
                                    Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Structural Data Architecture Viewport Bento Block -->
        <div class="dpt-bento-box">
            <div class="afdp-header-bar">
                <h6>Configured Academic Units</h6>
                <span class="dpt-count-pill"><?php echo count( $academic_rows ); ?> Records</span>
            </div>
            
            <div class="dpt-responsive-datatable">
                <table class="dpt-architecture-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Tier Type</th>
                            <th style="width: 35%;">Primary Classification (Class / Dept)</th>
                            <th style="width: 35%;">Secondary Parameter (Section / Year)</th>
                            <th style="width: 15%; text-align: right;" class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $academic_rows ) ) : foreach ( $academic_rows as $row ) : 
                            $edit_url = admin_url( 'admin.php?page=school_management_system&tab=academics&action=edit_unit&id=' . $row->id );
                            $del_url  = wp_nonce_url( 
                                admin_url( 'admin.php?page=school_management_system&tab=academics&action=delete_unit&id=' . $row->id ), 
                                'delete_unit_action_' . $row->id 
                            );
                            $is_college = ($row->unit_type === 'College');
                            $is_row_focused = ($is_edit && $edit_id === intval($row->id));
                        ?>
                            <tr class="<?php echo $is_row_focused ? 'dpt-tr-edit-focus' : ''; ?>">
                                <td>
                                    <span class="dpt-tier-badge <?php echo $is_college ? 'dpt-badge-college' : 'dpt-badge-school'; ?>">
                                        <?php echo esc_html( $row->unit_type ); ?>
                                    </span>
                                </td>
                                <td style="font-weight: 700; color: #0f172a;">
                                    <?php if ( $is_college ) : ?>
                                        <span style="color: #0369a1;"><span class="dashicons dashicons-category" style="font-size:16px; width:16px; height:16px; vertical-align:middle; margin-right:4px;"></span><?php echo esc_html( $row->dept_name ); ?></span>
                                    <?php else : ?>
                                        <span>Class: <?php echo esc_html( $row->class_name ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: 600; color: #475569;">
                                    <?php if ( $is_college ) : ?>
                                        <span style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 4px; font-size:12px;">Year: <?php echo esc_html( $row->class_name ); ?></span>
                                    <?php else : ?>
                                        <span style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 4px; font-size:12px;">Sec: <?php echo esc_html( $row->section_name ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;" class="no-print">
                                    <div class="afdp-action-cluster">
                                        <a href="<?php echo esc_url( $edit_url ); ?>" class="dpt-square-btn dpt-square-btn-edit" title="Edit Unit">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                        <a href="<?php echo esc_url( $del_url ); ?>" class="dpt-square-btn dpt-square-btn-delete" title="Delete Unit" onclick="return confirm('Delete this structural configuration row?');">
                                            <span class="dashicons dashicons-trash"></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr>
                                <td colspan="4" class="afdp-empty-row">
                                    <span class="dashicons dashicons-category"></span>
                                    No academic units configured yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- JavaScript Field Runtime Tier Switcher Layer -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const typeSelect     = document.getElementById('educore-unit-type-select');
        const schoolFields   = document.querySelectorAll('.school-field');
        const collegeFields  = document.querySelectorAll('.college-field');

        const schoolClassInput   = document.getElementById('school-class-input');
        const schoolSectionInput = document.getElementById('school-section-input');
        const collegeDeptInput   = document.getElementById('college-dept-input');
        const collegeYearInput   = document.getElementById('college-year-input');

        function toggleAcademicTier(isUserAction) {
            if (typeSelect.value === 'College') {
                schoolFields.forEach(el => el.style.display = 'none');
                schoolClassInput.removeAttribute('required');
                schoolSectionInput.removeAttribute('required');

                collegeFields.forEach(el => el.style.display = 'block');
                collegeDeptInput.setAttribute('required', 'required');
                collegeYearInput.setAttribute('required', 'required');

                if (isUserAction) {
                    schoolClassInput.value = '';
                    schoolSectionInput.value = '';
                }
            } else {
                collegeFields.forEach(el => el.style.display = 'none');
                collegeDeptInput.removeAttribute('required');
                collegeYearInput.removeAttribute('required');

                schoolFields.forEach(el => el.style.display = 'block');
                schoolClassInput.setAttribute('required', 'required');
                schoolSectionInput.setAttribute('required', 'required');

                if (isUserAction) {
                    collegeDeptInput.value = '';
                    collegeYearInput.value = '';
                }
            }
        }

        typeSelect.addEventListener('change', function() {
            toggleAcademicTier(true);
        });

        // Initialize state on load without wiping pre-populated edit values
        toggleAcademicTier(false);
    });
    </script>
    <?php
}