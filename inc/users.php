<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Enterprise User Management & Role Administration Module
 * Roles Supported: Administrator, Teacher, Accountant, Staff, Officers
 * File: inc/users.php
 */
function educore_users_tab() {
    if ( ! current_user_can( 'create_users' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage users.', 'ifsedu-sms' ) );
    }

    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    $sub_mode = isset( $_GET['sub'] ) ? sanitize_text_field( wp_unslash( $_GET['sub'] ) ) : 'list';

    // --------------------------------------------------------------------------
    // 1. CREATE USER CUSTOM ROLES ON FIRST LOAD
    // --------------------------------------------------------------------------
    if ( ! get_role( 'teacher' ) ) {
        add_role( 'teacher', __( 'Teacher', 'ifsedu-sms' ), array(
            'read'         => true,
            'upload_files' => true,
        ) );
    }
    if ( ! get_role( 'accountant' ) ) {
        add_role( 'accountant', __( 'Accountant', 'ifsedu-sms' ), array(
            'read'         => true,
            'upload_files' => true,
        ) );
    }
    if ( ! get_role( 'staff' ) ) {
        add_role( 'staff', __( 'Staff / Officer', 'ifsedu-sms' ), array(
            'read'         => true,
            'upload_files' => true,
        ) );
    }

    // --------------------------------------------------------------------------
    // 2. HANDLE DELETE ACTION
    // --------------------------------------------------------------------------
    if ( $sub_mode === 'delete' && isset( $_GET['id'] ) ) {
        $del_user_id = absint( $_GET['id'] );
        check_admin_referer( 'delete_sms_user_' . $del_user_id );

        if ( $del_user_id === get_current_user_id() ) {
            $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=users&msg=self_delete' );
        } else {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            // Unlink from staff table if applicable
            $wpdb->update( $table_staff, array( 'wp_user_id' => null ), array( 'wp_user_id' => $del_user_id ), array( '%s' ), array( '%d' ) );
            wp_delete_user( $del_user_id );
            $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=users&msg=deleted' );
        }
        echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_url ) . '";</script>';
        exit;
    }

    // --------------------------------------------------------------------------
    // 3. HANDLE ADD / EDIT USER FORM SUBMISSION
    // --------------------------------------------------------------------------
    $form_error = '';
    if ( isset( $_POST['educore_save_user_btn'] ) && isset( $_POST['educore_user_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_user_nonce'] ) ), 'save_user_action' ) ) {
        
        $username   = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : '';
        $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
        $last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
        $email      = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
        $pass1      = isset( $_POST['pass1'] ) ? (string) $_POST['pass1'] : '';
        $pass2      = isset( $_POST['pass2'] ) ? (string) $_POST['pass2'] : '';
        $role       = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : 'teacher';
        $staff_link = isset( $_POST['staff_link_id'] ) ? absint( $_POST['staff_link_id'] ) : 0;
        $edit_id    = isset( $_POST['edit_user_id'] ) ? absint( $_POST['edit_user_id'] ) : 0;

        $allowed_roles = array( 'administrator', 'teacher', 'accountant', 'staff' );
        if ( ! in_array( $role, $allowed_roles, true ) ) {
            $role = 'teacher';
        }

        // Validation Rules
        if ( ! is_email( $email ) ) {
            $form_error = __( 'Invalid email address provided.', 'ifsedu-sms' );
        } elseif ( $edit_id === 0 && empty( $username ) ) {
            $form_error = __( 'Username is required.', 'ifsedu-sms' );
        } elseif ( $edit_id === 0 && username_exists( $username ) ) {
            $form_error = __( 'This username is already registered. Please choose another.', 'ifsedu-sms' );
        } elseif ( email_exists( $email ) && ( $edit_id === 0 || email_exists( $email ) != $edit_id ) ) {
            $form_error = __( 'This email address is already assigned to an existing user.', 'ifsedu-sms' );
        } elseif ( ( $edit_id === 0 || ! empty( $pass1 ) ) && ( $pass1 !== $pass2 ) ) {
            $form_error = __( 'Passwords do not match. Please re-type both password fields.', 'ifsedu-sms' );
        } elseif ( $edit_id === 0 && strlen( $pass1 ) < 6 ) {
            $form_error = __( 'Password must be at least 6 characters long.', 'ifsedu-sms' );
        } else {
            $user_args = array(
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => trim( $first_name . ' ' . $last_name ) ?: ( $edit_id ? '' : $username ),
                'user_email'   => $email,
                'role'         => $role,
            );

            if ( ! empty( $pass1 ) ) {
                $user_args['user_pass'] = $pass1;
            }

            if ( $edit_id > 0 ) {
                $user_args['ID'] = $edit_id;
                $updated_user_id = wp_update_user( $user_args );

                if ( ! is_wp_error( $updated_user_id ) ) {
                    // Update staff table association
                    $wpdb->update( $table_staff, array( 'wp_user_id' => null ), array( 'wp_user_id' => $edit_id ), array( '%s' ), array( '%d' ) );
                    if ( $staff_link > 0 ) {
                        $wpdb->update( $table_staff, array( 'wp_user_id' => $edit_id ), array( 'id' => $staff_link ), array( '%d' ), array( '%d' ) );
                    }

                    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=users&msg=updated' );
                    echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_url ) . '";</script>';
                    exit;
                } else {
                    $form_error = $updated_user_id->get_error_message();
                }
            } else {
                $user_args['user_login'] = $username;
                $new_user_id = wp_insert_user( $user_args );

                if ( ! is_wp_error( $new_user_id ) ) {
                    if ( $staff_link > 0 ) {
                        $wpdb->update( $table_staff, array( 'wp_user_id' => $new_user_id ), array( 'id' => $staff_link ), array( '%d' ), array( '%d' ) );
                    }

                    if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                        IFSEdu_School_Management_System::log_activity( "Created new user: {$username} with role [{$role}]" );
                    }
                    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=users&msg=created' );
                    echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_url ) . '";</script>';
                    exit;
                } else {
                    $form_error = $new_user_id->get_error_message();
                }
            }
        }
    }

    // Fetch all active staff / officers / teachers for linking
    $all_staff_members = $wpdb->get_results( "SELECT id, full_name, designation, phone, email, wp_user_id FROM {$table_staff} WHERE status = 'Active' ORDER BY full_name ASC" );

    // --------------------------------------------------------------------------
    // 4. VIEW CONTROLLER (ADD / EDIT VS LIST)
    // --------------------------------------------------------------------------
    $list_url = admin_url( 'admin.php?page=school_management_system&tab=users&sub=list' );
    $add_url  = admin_url( 'admin.php?page=school_management_system&tab=users&sub=add' );
    ?>

    <style>
        .dpt-users-container {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            max-width: 100%;
        }

        .dpt-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .dpt-page-title {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dpt-btn {
            height: 40px;
            padding: 0 18px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }

        .dpt-btn-primary { background: #006a4e; color: #ffffff; box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2); }
        .dpt-btn-primary:hover { background: #00523c; color: #ffffff; }

        .dpt-btn-secondary { background: #ffffff; border-color: #cbd5e1; color: #475569; }
        .dpt-btn-secondary:hover { background: #f8fafc; color: #0f172a; border-color: #94a3b8; }

        .dpt-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }

        .dpt-form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        @media (max-width: 768px) {
            .dpt-form-grid { grid-template-columns: 1fr; }
        }

        .dpt-field-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dpt-field-label {
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
            padding: 0 14px;
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
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
            outline: none;
        }

        .dpt-feedback-alert {
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dpt-feedback-alert.success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .dpt-feedback-alert.error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .dpt-feedback-alert.info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }

        /* Role Badges */
        .dpt-role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .role-admin      { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .role-teacher    { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .role-accountant { background: #f3e8ff; color: #7c3aed; border: 1px solid #e9d5ff; }
        .role-staff      { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .role-default    { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        .dpt-table-responsive {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .dpt-users-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
        }

        .dpt-users-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .dpt-users-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: #334155;
            vertical-align: middle;
        }

        .dpt-users-table tr:last-child td { border-bottom: none; }
        .dpt-users-table tr:hover td { background: #f8fafc; }

        .dpt-avatar-fallback {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #e6f4f1;
            color: #006a4e;
            font-weight: 800;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #a7f3d0;
        }

        .dpt-action-group {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .dpt-action-btn-sm {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #64748b;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .dpt-action-btn-sm.edit:hover   { background: #006a4e; color: #ffffff; border-color: #006a4e; }
        .dpt-action-btn-sm.delete:hover { background: #dc2626; color: #ffffff; border-color: #dc2626; }
    </style>

    <div class="dpt-users-container">

        <!-- Top Header Navigation -->
        <div class="dpt-header-bar">
            <h2 class="dpt-page-title">
                <span class="dashicons dashicons-admin-users" style="color:#006a4e; font-size:26px; width:26px; height:26px;"></span>
                <?php esc_html_e( 'System User Accounts & Staff Roles', 'ifsedu-sms' ); ?>
            </h2>
            <?php if ( $sub_mode === 'add' || $sub_mode === 'edit' ) : ?>
                <a href="<?php echo esc_url( $list_url ); ?>" class="dpt-btn dpt-btn-secondary">
                    <span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'Back to All Users', 'ifsedu-sms' ); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( $add_url ); ?>" class="dpt-btn dpt-btn-primary">
                    <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add New User', 'ifsedu-sms' ); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Feedback Alert Messages -->
        <?php if ( ! empty( $form_error ) ) : ?>
            <div class="dpt-feedback-alert error">
                <span class="dashicons dashicons-warning"></span>
                <span><?php echo esc_html( $form_error ); ?></span>
            </div>
        <?php endif; ?>

        <?php if ( isset( $_GET['msg'] ) ) : 
            $msg = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
        ?>
            <?php if ( $msg === 'created' ) : ?>
                <div class="dpt-feedback-alert success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <span><?php esc_html_e( 'New user account created successfully with assigned role.', 'ifsedu-sms' ); ?></span>
                </div>
            <?php elseif ( $msg === 'updated' ) : ?>
                <div class="dpt-feedback-alert info">
                    <span class="dashicons dashicons-saved"></span>
                    <span><?php esc_html_e( 'User credentials and roles updated successfully.', 'ifsedu-sms' ); ?></span>
                </div>
            <?php elseif ( $msg === 'deleted' ) : ?>
                <div class="dpt-feedback-alert error">
                    <span class="dashicons dashicons-trash"></span>
                    <span><?php esc_html_e( 'User account has been deleted permanently.', 'ifsedu-sms' ); ?></span>
                </div>
            <?php elseif ( $msg === 'self_delete' ) : ?>
                <div class="dpt-feedback-alert error">
                    <span class="dashicons dashicons-warning"></span>
                    <span><?php esc_html_e( 'Security Alert: You cannot delete your own logged-in user account.', 'ifsedu-sms' ); ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- =========================================================================
             1. ADD / EDIT USER FORM VIEW
             ========================================================================= -->
        <?php if ( $sub_mode === 'add' || $sub_mode === 'edit' ) : 
            $edit_user = null;
            $linked_staff_id = 0;
            if ( $sub_mode === 'edit' && isset( $_GET['id'] ) ) {
                $edit_user = get_userdata( absint( $_GET['id'] ) );
                if ( $edit_user ) {
                    $linked_staff_obj = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table_staff} WHERE wp_user_id = %d LIMIT 1", $edit_user->ID ) );
                    if ( $linked_staff_obj ) {
                        $linked_staff_id = intval( $linked_staff_obj->id );
                    }
                }
            }
            $current_role = ( $edit_user && ! empty( $edit_user->roles ) ) ? $edit_user->roles[0] : 'teacher';

            // Map staff details for auto-fill JS engine
            $staff_map_js = array();
            if ( ! empty( $all_staff_members ) ) {
                foreach ( $all_staff_members as $st_item ) {
                    $name_parts = explode( ' ', trim( $st_item->full_name ), 2 );
                    $f_name = isset( $name_parts[0] ) ? $name_parts[0] : '';
                    $l_name = isset( $name_parts[1] ) ? $name_parts[1] : '';
                    
                    // Suggest clean username from email or phone/name
                    $slug_login = '';
                    if ( ! empty( $st_item->email ) ) {
                        $slug_login = sanitize_user( current( explode( '@', $st_item->email ) ) );
                    } else {
                        $slug_login = sanitize_user( strtolower( str_replace( ' ', '_', $st_item->full_name ) ) );
                    }

                    $staff_map_js[ $st_item->id ] = array(
                        'first_name' => $f_name,
                        'last_name'  => $l_name,
                        'email'      => $st_item->email,
                        'username'   => $slug_login
                    );
                }
            }
        ?>
            <div class="dpt-bento-card">
                <h3 style="margin:0 0 20px 0; font-size:18px; font-weight:800; color:#0f172a; border-bottom:1px solid #f1f5f9; padding-bottom:14px;">
                    <?php echo $edit_user ? esc_html__( 'Edit User Profile & Credentials', 'ifsedu-sms' ) : esc_html__( 'Register New System User', 'ifsedu-sms' ); ?>
                </h3>

                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_user_action', 'educore_user_nonce' ); ?>
                    <input type="hidden" name="edit_user_id" value="<?php echo $edit_user ? esc_attr( $edit_user->ID ) : '0'; ?>">

                    <div class="dpt-form-grid">
                        
                        <!-- Link Staff Member -->
                        <div class="dpt-field-group full-width" style="grid-column: span 2;">
                            <label class="dpt-field-label"><?php esc_html_e( 'Link with Existing Staff / Teacher Profile (Auto-fills Profile Data)', 'ifsedu-sms' ); ?></label>
                            <select name="staff_link_id" id="educore_staff_linker" class="dpt-select">
                                <option value="0"><?php esc_html_e( '-- Choose Staff Profile to Auto-Fill --', 'ifsedu-sms' ); ?></option>
                                <?php foreach ( $all_staff_members as $st ) : ?>
                                    <option value="<?php echo intval( $st->id ); ?>" <?php selected( $linked_staff_id, $st->id ); ?>>
                                        <?php echo esc_html( $st->full_name . ' (' . $st->designation . ' - ' . $st->phone . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Username -->
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Username (Login ID)', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="user_login" id="educore_user_login" class="dpt-input" value="<?php echo $edit_user ? esc_attr( $edit_user->user_login ) : ''; ?>" <?php echo $edit_user ? 'readonly style="background:#f1f5f9;"' : 'required'; ?> placeholder="e.g. teacher_john">
                        </div>

                        <!-- Email -->
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Email Address', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="email" name="user_email" id="educore_user_email" class="dpt-input" value="<?php echo $edit_user ? esc_attr( $edit_user->user_email ) : ''; ?>" placeholder="e.g. user@school.edu.bd" required>
                        </div>

                        <!-- First Name -->
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'First Name', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="first_name" id="educore_first_name" class="dpt-input" value="<?php echo $edit_user ? esc_attr( $edit_user->first_name ) : ''; ?>" placeholder="e.g. Tanvir">
                        </div>

                        <!-- Last Name -->
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Last Name', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="last_name" id="educore_last_name" class="dpt-input" value="<?php echo $edit_user ? esc_attr( $edit_user->last_name ) : ''; ?>" placeholder="e.g. Ahmed">
                        </div>

                        <!-- Password -->
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php echo $edit_user ? esc_html__( 'New Password (Leave blank to keep unchanged)', 'ifsedu-sms' ) : esc_html__( 'Password', 'ifsedu-sms' ) . ' <span style="color:#ef4444;">*</span>'; ?></label>
                            <input type="password" name="pass1" class="dpt-input" <?php echo $edit_user ? '' : 'required'; ?> autocomplete="new-password">
                        </div>

                        <!-- Re-type Password -->
                        <div class="dpt-field-group">
                            <label class="dpt-field-label"><?php esc_html_e( 'Re-Type Password', 'ifsedu-sms' ); ?> <?php echo $edit_user ? '' : '<span style="color:#ef4444;">*</span>'; ?></label>
                            <input type="password" name="pass2" class="dpt-input" <?php echo $edit_user ? '' : 'required'; ?> autocomplete="new-password">
                        </div>

                        <!-- System Role Dropdown -->
                        <div class="dpt-field-group" style="grid-column: span 2;">
                            <label class="dpt-field-label"><?php esc_html_e( 'System Staff Role & Access Level', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select name="role" class="dpt-select" required>
                                <option value="administrator" <?php selected( $current_role, 'administrator' ); ?>>👑 <?php esc_html_e( 'Administrator (Full Access & Settings)', 'ifsedu-sms' ); ?></option>
                                <option value="teacher" <?php selected( $current_role, 'teacher' ); ?>>👨‍🏫 <?php esc_html_e( 'Teacher (Students, Attendance & Marks Matrix)', 'ifsedu-sms' ); ?></option>
                                <option value="accountant" <?php selected( $current_role, 'accountant' ); ?>>💼 <?php esc_html_e( 'Accountant (Fees Collection & Accounting Ledger)', 'ifsedu-sms' ); ?></option>
                                <option value="staff" <?php selected( $current_role, 'staff' ); ?>>👔 <?php esc_html_e( 'Office Staff / Officer (General Portal & Records)', 'ifsedu-sms' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top:24px; text-align:right;">
                        <button type="submit" name="educore_save_user_btn" class="dpt-btn dpt-btn-primary" style="height:44px; padding:0 32px;">
                            <span class="dashicons dashicons-saved"></span>
                            <?php echo $edit_user ? esc_html__( 'Update User Account', 'ifsedu-sms' ) : esc_html__( 'Create User Account', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Auto-Fill JS Engine -->
            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                const staffMap = <?php echo wp_json_encode( $staff_map_js ); ?>;
                const linkerSelect = document.getElementById('educore_staff_linker');

                if (linkerSelect) {
                    linkerSelect.addEventListener('change', function() {
                        const staffId = this.value;
                        if (staffId && staffMap[staffId]) {
                            const data = staffMap[staffId];

                            const firstNameInput = document.getElementById('educore_first_name');
                            const lastNameInput  = document.getElementById('educore_last_name');
                            const emailInput     = document.getElementById('educore_user_email');
                            const loginInput     = document.getElementById('educore_user_login');

                            if (firstNameInput) firstNameInput.value = data.first_name;
                            if (lastNameInput) lastNameInput.value = data.last_name;
                            if (emailInput) emailInput.value = data.email;
                            if (loginInput && !loginInput.hasAttribute('readonly')) {
                                loginInput.value = data.username;
                            }
                        }
                    });
                }
            });
            </script>

        <!-- =========================================================================
             2. ALL USERS DIRECTORY VIEW
             ========================================================================= -->
        <?php else : 
            $all_users = get_users( array(
                'role__in' => array( 'administrator', 'teacher', 'accountant', 'staff' ),
                'orderby'  => 'registered',
                'order'    => 'DESC'
            ) );
        ?>
            <div class="dpt-bento-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid #f1f5f9;">
                    <h3 style="margin:0; font-size:17px; font-weight:800; color:#0f172a;">
                        <?php printf( esc_html__( 'Loaded Staff, Teachers & Officers (%d Total)', 'ifsedu-sms' ), count( $all_users ) ); ?>
                    </h3>
                </div>

                <div class="dpt-table-responsive">
                    <table class="dpt-users-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><?php esc_html_e( 'Avatar', 'ifsedu-sms' ); ?></th>
                                <th><?php esc_html_e( 'Username', 'ifsedu-sms' ); ?></th>
                                <th><?php esc_html_e( 'Full Name', 'ifsedu-sms' ); ?></th>
                                <th><?php esc_html_e( 'Email Address', 'ifsedu-sms' ); ?></th>
                                <th><?php esc_html_e( 'Assigned Role', 'ifsedu-sms' ); ?></th>
                                <th><?php esc_html_e( 'Registered Date', 'ifsedu-sms' ); ?></th>
                                <th style="text-align: right;"><?php esc_html_e( 'Actions', 'ifsedu-sms' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $all_users ) ) : foreach ( $all_users as $user_obj ) : 
                                $user_roles   = (array) $user_obj->roles;
                                $primary_role = ! empty( $user_roles ) ? $user_roles[0] : 'none';
                                
                                $badge_class = 'role-default';
                                if ( $primary_role === 'administrator' ) $badge_class = 'role-admin';
                                elseif ( $primary_role === 'teacher' )   $badge_class = 'role-teacher';
                                elseif ( $primary_role === 'accountant' ) $badge_class = 'role-accountant';
                                elseif ( $primary_role === 'staff' )      $badge_class = 'role-staff';

                                $user_edit_url = admin_url( 'admin.php?page=school_management_system&tab=users&sub=edit&id=' . $user_obj->ID );
                                $user_del_url  = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=users&sub=delete&id=' . $user_obj->ID ), 'delete_sms_user_' . $user_obj->ID );
                                $initial       = mb_substr( $user_obj->display_name ?: $user_obj->user_login, 0, 1, 'UTF-8' );
                            ?>
                                <tr>
                                    <td>
                                        <div class="dpt-avatar-fallback"><?php echo esc_html( strtoupper( $initial ) ); ?></div>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $user_obj->user_login ); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo esc_html( trim( $user_obj->first_name . ' ' . $user_obj->last_name ) ?: '—' ); ?>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo esc_attr( $user_obj->user_email ); ?>" style="color:#2563eb; text-decoration:none; font-weight:600;">
                                            <?php echo esc_html( $user_obj->user_email ); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="dpt-role-badge <?php echo esc_attr( $badge_class ); ?>">
                                            <?php echo esc_html( ucfirst( $primary_role ) ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small style="color:#64748b; font-weight:600;">
                                            <?php echo esc_html( date_i18n( 'd M Y', strtotime( $user_obj->user_registered ) ) ); ?>
                                        </small>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="dpt-action-group">
                                            <a href="<?php echo esc_url( $user_edit_url ); ?>" class="dpt-action-btn-sm edit" title="<?php esc_attr_e( 'Edit User', 'ifsedu-sms' ); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                            </a>
                                            <?php if ( $user_obj->ID !== get_current_user_id() ) : ?>
                                                <a href="<?php echo esc_url( $user_del_url ); ?>" class="dpt-action-btn-sm delete" title="<?php esc_attr_e( 'Delete User', 'ifsedu-sms' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this user?', 'ifsedu-sms' ) ); ?>');">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:30px; color:#94a3b8;">
                                        <?php esc_html_e( 'No registered staff, teacher or officer accounts found.', 'ifsedu-sms' ); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>
    <?php
}