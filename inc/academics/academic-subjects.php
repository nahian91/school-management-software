<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit; 
}

global $wpdb;
$table_units    = $wpdb->prefix . 'sms_academic_units';
$table_subjects = $wpdb->prefix . 'sms_subjects';

// Dynamic Base URL preservation from current URI without action state params
$current_uri = remove_query_arg( array( 'action', 'id', '_wpnonce', 'status', 'count' ), $_SERVER['REQUEST_URI'] );
$base_url    = esc_url_raw( $current_uri );

// Handle Edit AJAX Request
add_action( 'wp_ajax_dpt_update_subject', 'dpt_handle_update_subject_ajax' );
function dpt_handle_update_subject_ajax() {
    check_ajax_referer( 'dpt_edit_subject_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'ifsedu-sms' ) ) );
    }

    global $wpdb;
    $table_subjects = $wpdb->prefix . 'sms_subjects';

    $subject_id   = isset( $_POST['subject_id'] ) ? absint( $_POST['subject_id'] ) : 0;
    $class_id     = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
    $subject_name = isset( $_POST['subject_name'] ) ? sanitize_text_field( $_POST['subject_name'] ) : '';
    $subject_code = isset( $_POST['subject_code'] ) ? sanitize_text_field( $_POST['subject_code'] ) : '';

    if ( ! $subject_id || ! $class_id || empty( $subject_name ) ) {
        wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'ifsedu-sms' ) ) );
    }

    $updated = $wpdb->update(
        $table_subjects,
        array(
            'class_id'     => $class_id,
            'subject_name' => $subject_name,
            'subject_code' => $subject_code,
        ),
        array( 'id' => $subject_id ),
        array( '%d', '%s', '%s' ),
        array( '%d' )
    );

    if ( false !== $updated ) {
        if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
            IFSEdu_School_Management_System::log_activity( "Updated subject ID #{$subject_id} ({$subject_name})" );
        }
        wp_send_json_success( array( 'message' => __( 'Subject updated successfully.', 'ifsedu-sms' ) ) );
    } else {
        wp_send_json_error( array( 'message' => __( 'Failed to update subject database record.', 'ifsedu-sms' ) ) );
    }
}

// Handle Repeater Submit
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_subjects_repeater'] ) ) {
    if ( isset( $_POST['subject_setup_nonce'] ) && wp_verify_nonce( $_POST['subject_setup_nonce'], 'subject_setup_action' ) ) {
        $class_id     = absint( $_POST['class_id'] );
        $subject_name = isset( $_POST['subject_name'] ) && is_array( $_POST['subject_name'] ) ? $_POST['subject_name'] : array();
        $subject_code = isset( $_POST['subject_code'] ) && is_array( $_POST['subject_code'] ) ? $_POST['subject_code'] : array();

        if ( $class_id > 0 && ! empty( $subject_name ) ) {
            $inserted_count = 0;
            foreach ( $subject_name as $index => $name ) {
                $s_name = sanitize_text_field( $name );
                $s_code = isset( $subject_code[$index] ) ? sanitize_text_field( $subject_code[$index] ) : '';

                if ( ! empty( $s_name ) ) {
                    $wpdb->insert( 
                        $table_subjects, 
                        array( 
                            'class_id'     => $class_id, 
                            'subject_name' => $s_name, 
                            'subject_code' => $s_code
                        ), 
                        array( '%d', '%s', '%s' ) 
                    );
                    $inserted_count++;
                }
            }
            if ( $inserted_count > 0 ) {
                if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                    IFSEdu_School_Management_System::log_activity( "Bulk assigned {$inserted_count} subjects to class ID #{$class_id}" );
                }
                
                $redirect_target = add_query_arg( array( 'status' => 'subjects_added', 'count' => $inserted_count ), $base_url );

                if ( function_exists( 'educore_safe_redirect_helper' ) ) {
                    educore_safe_redirect_helper( $redirect_target );
                } elseif ( function_exists( 'educore_safe_redirect' ) ) {
                    educore_safe_redirect( $redirect_target );
                } else {
                    echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_target ) . '";</script>';
                }
                exit;
            }
        }
    }
}

// Handle Delete Subject
if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_subject' && isset( $_GET['id'] ) ) {
    $delete_id = absint( $_GET['id'] );
    $_nonce    = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';

    if ( $delete_id > 0 && wp_verify_nonce( $_nonce, 'delete_subject_action_' . $delete_id ) ) {
        $wpdb->delete( $table_subjects, array( 'id' => $delete_id ), array( '%d' ) );
        
        if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
            IFSEdu_School_Management_System::log_activity( "Deleted academic subject ID #{$delete_id}" );
        }
        
        $redirect_target = add_query_arg( array( 'status' => 'deleted' ), $base_url );

        if ( function_exists( 'educore_safe_redirect_helper' ) ) {
            educore_safe_redirect_helper( $redirect_target );
        } elseif ( function_exists( 'educore_safe_redirect' ) ) {
            educore_safe_redirect( $redirect_target );
        } else {
            echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_target ) . '";</script>';
        }
        exit;
    }
}

// Fetch Classes with Sections and Natural Serial Numeric Order (1,2,3...7,8,9,10)
$classes = $wpdb->get_results( 
    "SELECT id, class_name, section_name FROM {$table_units} 
     WHERE class_name IS NOT NULL AND class_name != '' 
     ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" 
);

if ( ! empty( $classes ) ) {
    usort( $classes, function( $a, $b ) {
        $res = strnatcasecmp( $a->class_name, $b->class_name );
        if ( $res === 0 ) {
            return strnatcasecmp( $a->section_name, $b->section_name );
        }
        return $res;
    });
}

// Subjects Directory List with Class/Section Join and Natural Serial Numeric Order
$subjects_list = $wpdb->get_results("
    SELECT s.*, u.class_name, u.section_name 
    FROM {$table_subjects} s 
    LEFT JOIN {$table_units} u ON s.class_id = u.id 
    ORDER BY CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC, s.subject_name ASC
");

if ( ! empty( $subjects_list ) ) {
    usort( $subjects_list, function( $a, $b ) {
        $classA = $a->class_name ? $a->class_name : '';
        $classB = $b->class_name ? $b->class_name : '';
        
        $res = strnatcasecmp( $classA, $classB );
        if ( $res === 0 ) {
            $secA = $a->section_name ? $a->section_name : '';
            $secB = $b->section_name ? $b->section_name : '';
            $secRes = strnatcasecmp( $secA, $secB );
            if ( $secRes === 0 ) {
                return strnatcasecmp( $a->subject_name, $b->subject_name );
            }
            return $secRes;
        }
        return $res;
    });
}
?>

<style>
    /* ==========================================================================
       ACADEMIC SUBJECTS - NEO-BENTO ARCHITECTURE
       ========================================================================== */
    .dpt-subjects-container {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        color: #0f172a;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .dpt-bento-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
    }

    .afdp-card-header {
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 16px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .afdp-card-title {
        font-size: 18px;
        font-weight: 800;
        color: #006a4e;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        letter-spacing: -0.3px;
    }

    .afdp-card-title .dashicons {
        font-size: 20px;
        width: 20px;
        height: 20px;
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

    .dpt-field-input,
    .dpt-field-select {
        width: 100%;
        padding: 10px 12px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 13.5px;
        color: #0f172a;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    .dpt-field-input:focus,
    .dpt-field-select:focus {
        outline: none;
        border-color: #006a4e;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
    }

    /* Dynamic Repeater Node */
    .dpt-repeater-canvas {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 16px;
    }

    .dpt-repeater-row {
        display: grid;
        grid-template-columns: 2fr 1fr 42px;
        gap: 12px;
        align-items: end;
        background: #f8fafc;
        padding: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        transition: all 0.2s ease;
    }

    @media (max-width: 768px) {
        .dpt-repeater-row {
            grid-template-columns: 1fr;
        }
    }

    .dpt-btn-remove-row {
        height: 42px;
        width: 42px;
        border-radius: 8px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #dc2626;
        cursor: not-allowed;
        opacity: 0.5;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all 0.2s ease;
    }

    .dpt-btn-remove-row.is-active {
        cursor: pointer;
        opacity: 1;
    }

    .dpt-btn-remove-row.is-active:hover {
        background: #dc2626;
        color: #ffffff;
    }

    .dpt-btn-add-repeater {
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

    .dpt-btn-add-repeater:hover {
        background: #dcfce7;
        border-color: #4ade80;
    }

    .dpt-btn-submit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 11px 28px;
        background: #006a4e;
        color: #ffffff;
        font-size: 14px;
        font-weight: 800;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
    }

    .dpt-btn-submit:hover {
        background: #00523c;
        transform: translateY(-1px);
    }

    /* Header Controls */
    .dpt-header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .dpt-filter-select {
        min-width: 220px;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        font-weight: 600;
    }

    /* Datatable UI Architecture */
    .dpt-count-pill {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
        font-size: 12px;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 20px;
    }

    .dpt-responsive-datatable {
        width: 100%;
        overflow-x: auto;
    }

    .dpt-architecture-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13.5px;
    }

    .dpt-architecture-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
        white-space: nowrap;
    }

    .dpt-architecture-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        vertical-align: middle;
    }

    .dpt-architecture-table tbody tr:hover td {
        background-color: #f8fafc;
    }

    .dpt-code-tag {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        padding: 2px 8px;
        border-radius: 6px;
        font-family: monospace;
        font-size: 12px;
        font-weight: 700;
    }

    .dpt-action-group {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
    }

    .dpt-square-btn {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        cursor: pointer;
    }

    .dpt-btn-edit {
        background: #eff6ff;
        color: #2563eb;
        border-color: #bfdbfe;
    }

    .dpt-btn-edit:hover {
        background: #2563eb;
        color: #ffffff;
    }

    .dpt-btn-delete {
        background: #fef2f2;
        color: #dc2626;
        border-color: #fecaca;
    }

    .dpt-btn-delete:hover {
        background: #dc2626;
        color: #ffffff;
    }

    .dpt-square-btn .dashicons {
        font-size: 15px;
        width: 15px;
        height: 15px;
    }

    .afdp-alert-success {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #047857;
        padding: 12px 16px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 13.5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Edit Modal Backdrop & Glassmorphism Box */
    .dpt-modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 999999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.25s ease;
    }

    .dpt-modal-backdrop.is-visible {
        opacity: 1;
        visibility: visible;
    }

    .dpt-modal-card {
        background: #ffffff;
        width: 100%;
        max-width: 480px;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0;
        transform: translateY(20px);
        transition: transform 0.25s ease;
    }

    .dpt-modal-backdrop.is-visible .dpt-modal-card {
        transform: translateY(0);
    }

    .dpt-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 14px;
        margin-bottom: 20px;
    }

    .dpt-modal-title {
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }

    .dpt-modal-close {
        background: transparent;
        border: none;
        color: #64748b;
        cursor: pointer;
        font-size: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .dpt-modal-close:hover {
        color: #dc2626;
    }

    .dpt-modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 24px;
        padding-top: 16px;
        border-top: 1px solid #f1f5f9;
    }

    .dpt-btn-cancel {
        padding: 9px 18px;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-weight: 700;
        cursor: pointer;
        font-size: 13px;
    }

    .dpt-btn-cancel:hover {
        background: #e2e8f0;
    }
</style>

<div class="dpt-subjects-container">

    <!-- Status Feedback Notifications -->
    <?php if ( isset( $_GET['status'] ) && $_GET['status'] === 'subjects_added' ) : ?>
        <div class="afdp-alert-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php 
                $count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
                printf( esc_html__( 'Successfully assigned %d new subjects to class.', 'ifsedu-sms' ), $count ); 
            ?>
        </div>
    <?php elseif ( isset( $_GET['status'] ) && $_GET['status'] === 'deleted' ) : ?>
        <div class="afdp-alert-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e( 'Subject deleted successfully.', 'ifsedu-sms' ); ?>
        </div>
    <?php endif; ?>

    <!-- Assign Subjects Repeater Bento Card -->
    <div class="dpt-bento-card">
        <div class="afdp-card-header">
            <h5 class="afdp-card-title">
                <span class="dashicons dashicons-book"></span>
                <?php esc_html_e( 'Assign Subjects to Academic Class', 'ifsedu-sms' ); ?>
            </h5>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'subject_setup_action', 'subject_setup_nonce' ); ?>
            
            <div class="dpt-form-group" style="margin-bottom: 20px; max-width: 400px;">
                <label class="dpt-form-label"><?php esc_html_e( 'Select Target Class & Section', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                <select name="class_id" class="dpt-field-select" required>
                    <option value=""><?php esc_html_e( '-- Choose Target Class --', 'ifsedu-sms' ); ?></option>
                    <?php foreach ( $classes as $cls ) : 
                        $label = $cls->class_name . ( ! empty( $cls->section_name ) ? ' (' . $cls->section_name . ')' : '' );
                    ?>
                        <option value="<?php echo esc_attr( $cls->id ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="subject-repeater-canvas" class="dpt-repeater-canvas">
                <div class="dpt-repeater-row">
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Subject Name', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="subject_name[]" class="dpt-field-input" placeholder="e.g. Higher Mathematics" required>
                    </div>
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Subject Code', 'ifsedu-sms' ); ?></label>
                        <input type="text" name="subject_code[]" class="dpt-field-input" placeholder="e.g. MAT-101">
                    </div>
                    <div>
                        <button type="button" class="dpt-btn-remove-row btn-remove-row" disabled>
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                </div>
            </div>

            <button type="button" id="btn-add-subject" class="dpt-btn-add-repeater">
                <span class="dashicons dashicons-plus-alt2" style="font-size:16px; width:16px; height:16px;"></span>
                <?php esc_html_e( 'Add Another Subject Entry', 'ifsedu-sms' ); ?>
            </button>

            <button type="submit" name="save_subjects_repeater" class="dpt-btn-submit">
                <span class="dashicons dashicons-saved" style="font-size:18px; width:18px; height:18px;"></span>
                <?php esc_html_e( 'Save All Subjects', 'ifsedu-sms' ); ?>
            </button>
        </form>
    </div>

    <!-- Mapped Subjects Table Bento Card -->
    <div class="dpt-bento-card">
        <div class="afdp-card-header">
            <h5 class="afdp-card-title">
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e( 'Mapped Academic Subjects Directory', 'ifsedu-sms' ); ?>
            </h5>
            
            <div class="dpt-header-actions">
                <!-- Class Filter Dropdown -->
                <select id="dpt-class-filter" class="dpt-field-select dpt-filter-select">
                    <option value="all"><?php esc_html_e( 'All Classes Filter', 'ifsedu-sms' ); ?></option>
                    <?php foreach ( $classes as $cls ) : 
                        $label = $cls->class_name . ( ! empty( $cls->section_name ) ? ' (' . $cls->section_name . ')' : '' );
                    ?>
                        <option value="<?php echo esc_attr( $cls->id ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>

                <span class="dpt-count-pill" id="dpt-subject-count-pill">
                    <?php echo esc_html( count( $subjects_list ) ); ?> <?php esc_html_e( 'Subjects Configured', 'ifsedu-sms' ); ?>
                </span>
            </div>
        </div>

        <div class="dpt-responsive-datatable">
            <table class="dpt-architecture-table" id="dpt-subjects-table">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php esc_html_e( 'Class & Section', 'ifsedu-sms' ); ?></th>
                        <th style="width: 40%;"><?php esc_html_e( 'Subject Title', 'ifsedu-sms' ); ?></th>
                        <th style="width: 15%;"><?php esc_html_e( 'Code', 'ifsedu-sms' ); ?></th>
                        <th style="width: 15%; text-align:right;"><?php esc_html_e( 'Actions', 'ifsedu-sms' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $subjects_list ) ) : foreach ( $subjects_list as $sub ) : 
                        $delete_url = wp_nonce_url( 
                            add_query_arg( array( 'action' => 'delete_subject', 'id' => $sub->id ), $base_url ), 
                            'delete_subject_action_' . $sub->id 
                        );
                        $class_label = $sub->class_name ? $sub->class_name . ( ! empty( $sub->section_name ) ? ' (' . $sub->section_name . ')' : '' ) : 'N/A';
                    ?>
                        <tr data-class-id="<?php echo esc_attr( $sub->class_id ); ?>" data-subject-id="<?php echo esc_attr( $sub->id ); ?>">
                            <td style="font-weight: 700; color: #006a4e;" class="cell-class-name"><?php echo esc_html( $class_label ); ?></td>
                            <td style="font-weight: 700; color: #0f172a;" class="cell-subject-name"><?php echo esc_html( $sub->subject_name ); ?></td>
                            <td>
                                <span class="dpt-code-tag cell-subject-code"><?php echo esc_html( $sub->subject_code ? $sub->subject_code : '-' ); ?></span>
                            </td>
                            <td style="text-align: right;">
                                <div class="dpt-action-group">
                                    <button type="button" 
                                            class="dpt-square-btn dpt-btn-edit btn-trigger-edit" 
                                            data-id="<?php echo esc_attr( $sub->id ); ?>"
                                            data-class-id="<?php echo esc_attr( $sub->class_id ); ?>"
                                            data-name="<?php echo esc_attr( $sub->subject_name ); ?>"
                                            data-code="<?php echo esc_attr( $sub->subject_code ); ?>"
                                            title="<?php esc_attr_e( 'Edit Subject', 'ifsedu-sms' ); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>

                                    <a href="<?php echo esc_url( $delete_url ); ?>" 
                                       class="dpt-square-btn dpt-btn-delete" 
                                       title="<?php esc_attr_e( 'Delete Subject', 'ifsedu-sms' ); ?>" 
                                       onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this subject?', 'ifsedu-sms' ) ); ?>');">
                                        <span class="dashicons dashicons-trash"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr id="dpt-no-subjects-row">
                            <td colspan="4" style="text-align:center; padding: 40px; color: #94a3b8;">
                                <?php esc_html_e( 'No subjects assigned to any class yet.', 'ifsedu-sms' ); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Edit Subject Dynamic Modal -->
<div class="dpt-modal-backdrop" id="dpt-edit-modal">
    <div class="dpt-modal-card">
        <div class="dpt-modal-header">
            <h4 class="dpt-modal-title"><?php esc_html_e( 'Edit Academic Subject', 'ifsedu-sms' ); ?></h4>
            <button type="button" class="dpt-modal-close" id="dpt-close-modal">&times;</button>
        </div>
        <form id="dpt-edit-subject-form">
            <input type="hidden" id="edit_subject_id" name="subject_id" value="">
            <?php wp_nonce_field( 'dpt_edit_subject_nonce', 'edit_subject_nonce_field' ); ?>

            <div class="dpt-form-group" style="margin-bottom: 14px;">
                <label class="dpt-form-label"><?php esc_html_e( 'Academic Class & Section', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                <select id="edit_class_id" name="class_id" class="dpt-field-select" required>
                    <option value=""><?php esc_html_e( '-- Choose Target Class --', 'ifsedu-sms' ); ?></option>
                    <?php foreach ( $classes as $cls ) : 
                        $label = $cls->class_name . ( ! empty( $cls->section_name ) ? ' (' . $cls->section_name . ')' : '' );
                    ?>
                        <option value="<?php echo esc_attr( $cls->id ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="dpt-form-group" style="margin-bottom: 14px;">
                <label class="dpt-form-label"><?php esc_html_e( 'Subject Name', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                <input type="text" id="edit_subject_name" name="subject_name" class="dpt-field-input" required>
            </div>

            <div class="dpt-form-group">
                <label class="dpt-form-label"><?php esc_html_e( 'Subject Code', 'ifsedu-sms' ); ?></label>
                <input type="text" id="edit_subject_code" name="subject_code" class="dpt-field-input">
            </div>

            <div class="dpt-modal-footer">
                <button type="button" class="dpt-btn-cancel" id="dpt-cancel-edit"><?php esc_html_e( 'Cancel', 'ifsedu-sms' ); ?></button>
                <button type="submit" class="dpt-btn-submit" id="dpt-save-edit-btn">
                    <span class="dashicons dashicons-saved" style="font-size:16px; width:16px; height:16px;"></span>
                    <?php esc_html_e( 'Update Subject', 'ifsedu-sms' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // --------------------------------------------------------------------------
    // 1. REPEATER ENGINE
    // --------------------------------------------------------------------------
    const canvas = document.getElementById('subject-repeater-canvas');
    const addBtn = document.getElementById('btn-add-subject');

    function updateRemoveButtons() {
        if (!canvas) return;
        const rows = canvas.querySelectorAll('.dpt-repeater-row');
        rows.forEach((row) => {
            const btn = row.querySelector('.btn-remove-row');
            if (rows.length > 1) {
                btn.removeAttribute('disabled');
                btn.classList.add('is-active');
            } else {
                btn.setAttribute('disabled', 'disabled');
                btn.classList.remove('is-active');
            }
        });
    }

    if (addBtn && canvas) {
        addBtn.addEventListener('click', function() {
            const rows = canvas.querySelectorAll('.dpt-repeater-row');
            const newRow = rows[0].cloneNode(true);

            newRow.querySelectorAll('input').forEach(inp => inp.value = '');

            canvas.appendChild(newRow);
            updateRemoveButtons();
        });

        canvas.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-remove-row');
            if (btn && !btn.hasAttribute('disabled')) {
                const row = btn.closest('.dpt-repeater-row');
                if (row) {
                    row.remove();
                    updateRemoveButtons();
                }
            }
        });
    }

    // --------------------------------------------------------------------------
    // 2. DATATABLE CLASS FILTERING
    // --------------------------------------------------------------------------
    const filterSelect = document.getElementById('dpt-class-filter');
    const tableBody = document.querySelector('#dpt-subjects-table tbody');
    const countPill = document.getElementById('dpt-subject-count-pill');

    if (filterSelect && tableBody) {
        filterSelect.addEventListener('change', function() {
            const selectedClassId = this.value;
            const rows = tableBody.querySelectorAll('tr[data-class-id]');
            let visibleCount = 0;

            rows.forEach(row => {
                const rowClassId = row.getAttribute('data-class-id');
                if (selectedClassId === 'all' || rowClassId === selectedClassId) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (countPill) {
                countPill.textContent = visibleCount + ' ' + '<?php echo esc_js( __( 'Subjects Configured', 'ifsedu-sms' ) ); ?>';
            }
        });
    }

    // --------------------------------------------------------------------------
    // 3. EDIT MODAL AJAX ENGINE
    // --------------------------------------------------------------------------
    const modal = document.getElementById('dpt-edit-modal');
    const closeModalBtn = document.getElementById('dpt-close-modal');
    const cancelModalBtn = document.getElementById('dpt-cancel-edit');
    const editForm = document.getElementById('dpt-edit-subject-form');

    function hideModal() {
        if (modal) modal.classList.remove('is-visible');
    }

    if (closeModalBtn) closeModalBtn.addEventListener('click', hideModal);
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', hideModal);

    // Event delegation for dynamically triggered Edit buttons
    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.btn-trigger-edit');
        if (editBtn) {
            const id = editBtn.getAttribute('data-id');
            const classId = editBtn.getAttribute('data-class-id');
            const name = editBtn.getAttribute('data-name');
            const code = editBtn.getAttribute('data-code');

            document.getElementById('edit_subject_id').value = id;
            document.getElementById('edit_class_id').value = classId;
            document.getElementById('edit_subject_name').value = name;
            document.getElementById('edit_subject_code').value = code;

            modal.classList.add('is-visible');
        }
    });

    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('dpt-save-edit-btn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Save...';

            const formData = new FormData();
            formData.append('action', 'dpt_update_subject');
            formData.append('security', document.getElementById('edit_subject_nonce_field').value);
            formData.append('subject_id', document.getElementById('edit_subject_id').value);
            formData.append('class_id', document.getElementById('edit_class_id').value);
            formData.append('subject_name', document.getElementById('edit_subject_name').value);
            formData.append('subject_code', document.getElementById('edit_subject_code').value);

            const ajaxUrl = '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>';

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;

                if (data.success) {
                    const subjectId = document.getElementById('edit_subject_id').value;
                    const row = document.querySelector('tr[data-subject-id="' + subjectId + '"]');

                    if (row) {
                        const classSelect = document.getElementById('edit_class_id');
                        const selectedClassText = classSelect.options[classSelect.selectedIndex].text;
                        const newName = document.getElementById('edit_subject_name').value;
                        const newCode = document.getElementById('edit_subject_code').value;

                        row.setAttribute('data-class-id', classSelect.value);
                        row.querySelector('.cell-class-name').textContent = selectedClassText;
                        row.querySelector('.cell-subject-name').textContent = newName;
                        row.querySelector('.cell-subject-code').textContent = newCode ? newCode : '-';

                        const editBtn = row.querySelector('.btn-trigger-edit');
                        editBtn.setAttribute('data-class-id', classSelect.value);
                        editBtn.setAttribute('data-name', newName);
                        editBtn.setAttribute('data-code', newCode);
                    }

                    hideModal();
                } else {
                    alert(data.data.message || 'Error occurred while updating.');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                alert('Connection error.');
            });
        });
    }
});
</script>