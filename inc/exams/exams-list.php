<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * High-End Academic Examinations & Scheme Setup Management Engine
 * File: exams-list-view.php
 * Custom Prefixes Applied: dpt-, afdp-
 * Architecture: Neo-Bento Interface with Kinetic Data Tables & Secure Execution
 */
function educore_exams_list_view() {
    global $wpdb;
    
    $table_exams = $wpdb->prefix . 'sms_exams';
    $table_units = $wpdb->prefix . 'sms_academic_units';

    // Strict Security Control: Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage examination schemes.', 'ifsedu-sms' ) );
    }

    // Dynamic Base URL Preservation
    $current_uri = remove_query_arg( array( 'action', 'id', '_wpnonce', 'status' ), $_SERVER['REQUEST_URI'] );
    $base_url    = esc_url_raw( $current_uri );

    // Determine Edit Mode safely
    $get_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
    $get_id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

    $is_edit   = ( 'edit' === $get_action && $get_id > 0 );
    $edit_id   = $is_edit ? $get_id : 0;
    $edit_exam = null;

    $edit_exam_title = '';
    $edit_exam_year  = current_time( 'Y' );

    if ( $is_edit ) {
        $edit_exam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_exams} WHERE id = %d", $edit_id ) );
        if ( ! $edit_exam ) {
            $is_edit = false; // Fallback if record does not exist
        } else {
            // Parse Exam Name & Year if stored as "Exam Name - Year"
            $parts = explode( ' - ', $edit_exam->exam_name );
            if ( count( $parts ) > 1 && is_numeric( end( $parts ) ) ) {
                $edit_exam_year  = array_pop( $parts );
                $edit_exam_title = implode( ' - ', $parts );
            } else {
                $edit_exam_title = $edit_exam->exam_name;
            }
        }
    }

    // 1. Handle Form Submission (INSERT / UPDATE)
    if ( isset( $_POST['educore_save_exam'] ) && isset( $_POST['educore_exam_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_exam_nonce'] ) ), 'save_exam_action' ) ) {
        $exam_id_input = isset( $_POST['exam_id'] ) ? intval( $_POST['exam_id'] ) : 0;
        
        $exam_title_input = isset( $_POST['exam_title'] ) ? sanitize_text_field( wp_unslash( $_POST['exam_title'] ) ) : '';
        $exam_year_input  = isset( $_POST['exam_year'] ) ? sanitize_text_field( wp_unslash( $_POST['exam_year'] ) ) : current_time( 'Y' );
        
        // Combine Title and Year for DB storage
        $full_exam_name = ! empty( $exam_year_input ) ? $exam_title_input . ' - ' . $exam_year_input : $exam_title_input;

        $class_name = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : current_time( 'Y-m-d' );
        $end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : current_time( 'Y-m-d' );
        $status     = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Upcoming';

        $data = array(
            'exam_name'  => $full_exam_name,
            'class_name' => $class_name,
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'status'     => $status,
        );
        $format = array( '%s', '%s', '%s', '%s', '%s' );

        if ( $exam_id_input > 0 ) {
            // Update Existing Exam Record
            $wpdb->update( 
                $table_exams, 
                $data, 
                array( 'id' => $exam_id_input ), 
                $format, 
                array( '%d' ) 
            );

            if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                IFSEdu_School_Management_System::log_activity( "Updated exam scheme: " . $data['exam_name'] );
            }

            $redirect_target = add_query_arg( array( 'status' => 'updated' ), $base_url );
        } else {
            // Insert New Exam Record
            $wpdb->insert( $table_exams, $data, $format );

            if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                IFSEdu_School_Management_System::log_activity( "Created exam scheme: " . $data['exam_name'] );
            }

            $redirect_target = add_query_arg( array( 'status' => 'success' ), $base_url );
        }

        // Safe Redirect Execution
        if ( function_exists( 'educore_safe_redirect_helper' ) ) {
            educore_safe_redirect_helper( $redirect_target );
        } elseif ( function_exists( 'educore_safe_redirect' ) ) {
            educore_safe_redirect( $redirect_target );
        } else {
            wp_safe_redirect( $redirect_target );
        }
        echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_target ) . '";</script>';
        exit;
    }

    // 2. Handle Delete Exam
    if ( 'delete' === $get_action && $get_id > 0 ) {
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_exam_' . $get_id ) ) {
            $wpdb->delete( $table_exams, array( 'id' => $get_id ), array( '%d' ) );

            if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                IFSEdu_School_Management_System::log_activity( "Deleted exam ID: " . $get_id );
            }

            $redirect_target = add_query_arg( array( 'status' => 'deleted' ), $base_url );

            if ( function_exists( 'educore_safe_redirect_helper' ) ) {
                educore_safe_redirect_helper( $redirect_target );
            } elseif ( function_exists( 'educore_safe_redirect' ) ) {
                educore_safe_redirect( $redirect_target );
            } else {
                wp_safe_redirect( $redirect_target );
            }
            echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_target ) . '";</script>';
            exit;
        }
    }

    // Fetch Unique Classes with Natural Numeric Sorting (1, 2, 3... 11)
    $raw_classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    if ( ! empty( $raw_classes ) ) {
        usort( $raw_classes, function( $a, $b ) {
            return strnatcasecmp( $a->class_name, $b->class_name );
        });
    }

    $exams = $wpdb->get_results( "SELECT * FROM {$table_exams} ORDER BY id DESC" );
    
    $marks_url  = add_query_arg( array( 'sub' => 'marks' ), $base_url );
    $cancel_url = $base_url;
    $status_msg = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
    ?>

    <style>
        /* ==========================================================================
           1. CORE BENTO UI ENGINE LAYERING
           ========================================================================== */
        .dpt-exams-root {
            margin: 20px 20px 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        .afdp-header-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .afdp-header-block h2 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.5px;
        }
        .afdp-header-block h2 .dashicons {
            font-size: 26px;
            width: 26px;
            height: 26px;
            color: #006a4e;
        }

        .afdp-status-banner {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            padding: 14px 18px;
            color: #065f46;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Bento Grid Architecture */
        .dpt-bento-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 1024px) {
            .dpt-bento-grid {
                grid-template-columns: 1fr;
            }
        }

        .dpt-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
        }

        .afdp-card-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Form Control Framework */
        .dpt-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 18px;
        }
        
        .dpt-form-row-2col {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 12px;
        }

        .dpt-form-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            margin: 0;
        }
        .dpt-input-field, .dpt-select-field {
            height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            width: 100%;
            box-shadow: none;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .dpt-input-field:focus, .dpt-select-field:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
            outline: none;
        }

        /* Trigger Buttons Styling */
        .dpt-btn-submit-trigger {
            height: 40px;
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
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.15);
        }
        .dpt-btn-submit-trigger:hover {
            background: #00523c;
            color: #ffffff;
            transform: translateY(-0.5px);
        }

        .dpt-btn-secondary {
            height: 40px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-weight: 700;
            font-size: 13.5px;
            border-radius: 8px;
            padding: 0 16px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            text-decoration: none;
        }
        .dpt-btn-secondary:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* Matrix Table Styling */
        .dpt-table-responsive {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        .dpt-exams-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
        }
        .dpt-exams-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 12.5px;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .dpt-exams-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13.5px;
            color: #334155;
            background: #ffffff;
        }
        .dpt-exams-table tr:last-child td {
            border-bottom: none;
        }
        .dpt-exams-table tr.afdp-active-edit td {
            background: #fefce8;
        }
        .dpt-exams-table tr:hover td {
            background: #f8fafc;
        }

        /* Badges & SVG Action Buttons */
        .afdp-badge {
            font-size: 11.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
        }
        .afdp-badge-class { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
        .afdp-badge-upcoming { background: #fef7e0; color: #b06000; border: 1px solid #feebc8; }
        .afdp-badge-ongoing { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .afdp-badge-completed { background: #e6f4ea; color: #137333; border: 1px solid #ceead6; }

        .afdp-action-btn-svg {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #475569;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .afdp-action-btn-svg svg {
            width: 15px;
            height: 15px;
            fill: currentColor;
            flex-shrink: 0;
        }
        .afdp-action-btn-svg.edit:hover {
            border-color: #006a4e;
            color: #ffffff;
            background: #006a4e;
            box-shadow: 0 2px 6px rgba(0, 106, 78, 0.25);
        }
        .afdp-action-btn-svg.delete:hover {
            border-color: #dc2626;
            color: #ffffff;
            background: #dc2626;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);
        }

        /* DataTables Custom Polish */
        .dataTables_wrapper .dataTables_length, 
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 16px;
            font-size: 13px;
            color: #475569;
        }
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 4px 10px;
            background: #f8fafc;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #006a4e !important;
            color: #ffffff !important;
            border: none !important;
            border-radius: 6px !important;
        }
    </style>

    <div class="dpt-exams-root">
        
        <!-- Header Console Block -->
        <div class="afdp-header-block">
            <h2>
                <span class="dashicons dashicons-welcome-write-blog"></span> 
                <?php esc_html_e( 'Examinations & Scheme Setup', 'ifsedu-sms' ); ?>
            </h2>
            <a href="<?php echo esc_url( $marks_url ); ?>" class="dpt-btn-submit-trigger">
                <?php esc_html_e( 'Enter Marks Matrix', 'ifsedu-sms' ); ?> &rarr;
            </a>
        </div>

        <!-- Status Alert Notification Bar -->
        <?php if ( ! empty( $status_msg ) ) : ?>
            <div class="afdp-status-banner">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php 
                    if ( 'success' === $status_msg ) {
                        esc_html_e( 'New examination scheme created successfully.', 'ifsedu-sms' );
                    } elseif ( 'updated' === $status_msg ) {
                        esc_html_e( 'Examination details updated successfully.', 'ifsedu-sms' );
                    } elseif ( 'deleted' === $status_msg ) {
                        esc_html_e( 'Examination record removed successfully.', 'ifsedu-sms' );
                    }
                ?>
            </div>
        <?php endif; ?>

        <!-- Bento Grid Main Container -->
        <div class="dpt-bento-grid">
            
            <!-- Left Side: Add / Edit Exam Form -->
            <div class="dpt-bento-card">
                <h4 class="afdp-card-title">
                    <span><?php echo $is_edit ? esc_html__( 'Edit Exam Scheme', 'ifsedu-sms' ) : esc_html__( 'Create New Exam', 'ifsedu-sms' ); ?></span>
                    <?php if ( $is_edit ) : ?>
                        <span class="afdp-badge afdp-badge-ongoing"><?php esc_html_e( 'Editing Mode', 'ifsedu-sms' ); ?></span>
                    <?php endif; ?>
                </h4>

                <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                    <?php wp_nonce_field( 'save_exam_action', 'educore_exam_nonce' ); ?>
                    <input type="hidden" name="exam_id" value="<?php echo $is_edit ? intval( $edit_exam->id ) : 0; ?>">
                    
                    <!-- Split Exam Name & Year Inputs -->
                    <div class="dpt-form-group">
                        <div class="dpt-form-row-2col">
                            <div>
                                <label class="dpt-form-label"><?php esc_html_e( 'Exam Name', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                                <input type="text" name="exam_title" class="dpt-input-field" placeholder="<?php esc_attr_e( 'e.g. First Term / Annual', 'ifsedu-sms' ); ?>" value="<?php echo esc_attr( $edit_exam_title ); ?>" required>
                            </div>
                            <div>
                                <label class="dpt-form-label"><?php esc_html_e( 'Exam Year', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                                <input type="number" name="exam_year" class="dpt-input-field" placeholder="YYYY" min="2020" max="2099" value="<?php echo esc_attr( $edit_exam_year ); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Class / Tier', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" class="dpt-select-field" required>
                            <option value="All Classes" <?php selected( $is_edit ? $edit_exam->class_name : '', 'All Classes' ); ?>><?php esc_html_e( 'All Classes', 'ifsedu-sms' ); ?></option>
                            <?php if ( ! empty( $raw_classes ) ) : foreach ( $raw_classes as $cls_obj ) : ?>
                                <option value="<?php echo esc_attr( $cls_obj->class_name ); ?>" <?php selected( $is_edit ? $edit_exam->class_name : '', $cls_obj->class_name ); ?>>
                                    <?php echo esc_html( $cls_obj->class_name ); ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Start Date', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="date" name="start_date" class="dpt-input-field" value="<?php echo $is_edit ? esc_attr( $edit_exam->start_date ) : esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                    </div>

                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'End Date', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="date" name="end_date" class="dpt-input-field" value="<?php echo $is_edit ? esc_attr( $edit_exam->end_date ) : esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                    </div>

                    <div class="dpt-form-group" style="margin-bottom: 24px;">
                        <label class="dpt-form-label"><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></label>
                        <select name="status" class="dpt-select-field">
                            <option value="Upcoming" <?php selected( $is_edit ? $edit_exam->status : '', 'Upcoming' ); ?>><?php esc_html_e( 'Upcoming', 'ifsedu-sms' ); ?></option>
                            <option value="Ongoing" <?php selected( $is_edit ? $edit_exam->status : '', 'Ongoing' ); ?>><?php esc_html_e( 'Ongoing', 'ifsedu-sms' ); ?></option>
                            <option value="Completed" <?php selected( $is_edit ? $edit_exam->status : '', 'Completed' ); ?>><?php esc_html_e( 'Completed', 'ifsedu-sms' ); ?></option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="educore_save_exam" class="dpt-btn-submit-trigger" style="flex: 1;">
                            <?php echo $is_edit ? esc_html__( 'Update Exam', 'ifsedu-sms' ) : esc_html__( 'Save Exam', 'ifsedu-sms' ); ?>
                        </button>
                        <?php if ( $is_edit ) : ?>
                            <a href="<?php echo esc_url( $cancel_url ); ?>" class="dpt-btn-secondary"><?php esc_html_e( 'Cancel', 'ifsedu-sms' ); ?></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Right Side: Exam List Table -->
            <div class="dpt-bento-card">
                <h4 class="afdp-card-title"><?php esc_html_e( 'Active Examination Schemes', 'ifsedu-sms' ); ?></h4>
                
                <div class="dpt-table-responsive">
                    <table class="dpt-exams-table educore-datatable">
                        <thead>
                            <tr>
                                <th style="width: 30%;"><?php esc_html_e( 'Exam Name', 'ifsedu-sms' ); ?></th>
                                <th style="width: 20%;"><?php esc_html_e( 'Class', 'ifsedu-sms' ); ?></th>
                                <th style="width: 25%;"><?php esc_html_e( 'Duration', 'ifsedu-sms' ); ?></th>
                                <th style="width: 15%;"><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></th>
                                <th style="width: 10%; text-align: right;"><?php esc_html_e( 'Actions', 'ifsedu-sms' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $exams ) ) : foreach ( $exams as $exam ) : 
                                $exam_id  = intval( $exam->id );
                                $edit_url = add_query_arg( array( 'action' => 'edit', 'id' => $exam_id ), $base_url );
                                $del_url  = wp_nonce_url( add_query_arg( array( 'action' => 'delete', 'id' => $exam_id ), $base_url ), 'delete_exam_' . $exam_id );
                                $is_row_active = ( $is_edit && $edit_id === $exam_id );
                            ?>
                            <tr class="<?php echo $is_row_active ? 'afdp-active-edit' : ''; ?>">
                                <td><strong><?php echo esc_html( $exam->exam_name ); ?></strong></td>
                                <td><span class="afdp-badge afdp-badge-class"><?php echo esc_html( $exam->class_name ); ?></span></td>
                                <td>
                                    <small style="color: #64748b; font-weight: 600;">
                                        <?php echo esc_html( date_i18n( 'd M Y', strtotime( $exam->start_date ) ) ); ?> - <?php echo esc_html( date_i18n( 'd M Y', strtotime( $exam->end_date ) ) ); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php 
                                        $badge_class = 'afdp-badge-class';
                                        if ( 'Completed' === $exam->status ) {
                                            $badge_class = 'afdp-badge-completed';
                                        } elseif ( 'Ongoing' === $exam->status ) {
                                            $badge_class = 'afdp-badge-ongoing';
                                        } elseif ( 'Upcoming' === $exam->status ) {
                                            $badge_class = 'afdp-badge-upcoming';
                                        }
                                    ?>
                                    <span class="afdp-badge <?php echo esc_attr( $badge_class ); ?>">
                                        <?php echo esc_html( $exam->status ); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px; justify-content: flex-end;">
                                        <!-- SVG Edit Button -->
                                        <a href="<?php echo esc_url( $edit_url ); ?>" class="afdp-action-btn-svg edit" title="<?php esc_attr_e( 'Edit Exam', 'ifsedu-sms' ); ?>">
                                            <svg viewBox="0 0 24 24"><path d="M3 17.25V21h4.75L17.81 9.94l-4.75-4.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 4.75 4.75 1.83-1.83z"/></svg>
                                        </a>
                                        <!-- SVG Delete Button -->
                                        <a href="<?php echo esc_url( $del_url ); ?>" class="afdp-action-btn-svg delete" title="<?php esc_attr_e( 'Delete Exam', 'ifsedu-sms' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this exam scheme permanently?', 'ifsedu-sms' ) ); ?>');">
                                            <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if ($.fn.DataTable) {
            $('.educore-datatable').DataTable({ 
                "pageLength": 10,
                "language": {
                    "search": "<?php echo esc_js( __( 'Search Schemes:', 'ifsedu-sms' ) ); ?>"
                }
            });
        }
    });
    </script>
    <?php
}