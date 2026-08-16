<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit; 
}

global $wpdb;
$table_units    = $wpdb->prefix . 'sms_academic_units';
$table_subjects = $wpdb->prefix . 'sms_subjects';

// --------------------------------------------------------------------------
// 0. AUTO-SCHEMA CHECK (Ensures Mark Distribution Columns Exist)
// --------------------------------------------------------------------------
$columns_to_check = array(
    'total_marks'     => "decimal(5,2) DEFAULT '100.00' NOT NULL AFTER `subject_code`",
    'pass_marks'      => "decimal(5,2) DEFAULT '33.00' NOT NULL AFTER `total_marks`",
    'cq_marks'        => "decimal(5,2) DEFAULT '70.00' NOT NULL AFTER `pass_marks`",
    'cq_pass'         => "decimal(5,2) DEFAULT '23.00' NOT NULL AFTER `cq_marks`",
    'mcq_marks'       => "decimal(5,2) DEFAULT '30.00' NOT NULL AFTER `cq_pass`",
    'mcq_pass'        => "decimal(5,2) DEFAULT '10.00' NOT NULL AFTER `mcq_marks`",
    'practical_marks' => "decimal(5,2) DEFAULT '0.00' NOT NULL AFTER `mcq_pass`",
    'practical_pass'  => "decimal(5,2) DEFAULT '0.00' NOT NULL AFTER `practical_marks`",
);

foreach ( $columns_to_check as $col => $sql_def ) {
    $col_exists = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_subjects}` LIKE '{$col}'" );
    if ( empty( $col_exists ) ) {
        $wpdb->query( "ALTER TABLE `{$table_subjects}` ADD `{$col}` {$sql_def}" );
    }
}

// Dynamic Base URL
$current_uri = remove_query_arg( array( 'action', 'id', '_wpnonce', 'status', 'count' ), $_SERVER['REQUEST_URI'] );
$base_url    = esc_url_raw( $current_uri );

// --------------------------------------------------------------------------
// 1. DIRECT FORM SUBMISSION: SINGLE SUBJECT UPDATE (Fallback & Standard)
// --------------------------------------------------------------------------
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['educore_update_single_subject'] ) ) {
    if ( isset( $_POST['edit_subject_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['edit_subject_nonce_field'] ) ), 'dpt_edit_subject_nonce' ) ) {
        $sub_id     = isset( $_POST['subject_id'] ) ? absint( $_POST['subject_id'] ) : 0;
        $class_id   = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
        $sub_name   = isset( $_POST['subject_name'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_name'] ) ) : '';
        $sub_code   = isset( $_POST['subject_code'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_code'] ) ) : '';
        $tot_m      = isset( $_POST['total_marks'] ) ? floatval( $_POST['total_marks'] ) : 100.00;
        $pass_m     = isset( $_POST['pass_marks'] ) ? floatval( $_POST['pass_marks'] ) : 33.00;
        $cq_m       = isset( $_POST['cq_marks'] ) ? floatval( $_POST['cq_marks'] ) : 0.00;
        $cq_p       = isset( $_POST['cq_pass'] ) ? floatval( $_POST['cq_pass'] ) : 0.00;
        $mcq_m      = isset( $_POST['mcq_marks'] ) ? floatval( $_POST['mcq_marks'] ) : 0.00;
        $mcq_p      = isset( $_POST['mcq_pass'] ) ? floatval( $_POST['mcq_pass'] ) : 0.00;
        $pr_m       = isset( $_POST['practical_marks'] ) ? floatval( $_POST['practical_marks'] ) : 0.00;
        $pr_p       = isset( $_POST['practical_pass'] ) ? floatval( $_POST['practical_pass'] ) : 0.00;

        if ( $sub_id > 0 && $class_id > 0 && ! empty( $sub_name ) ) {
            $wpdb->update(
                $table_subjects,
                array(
                    'class_id'        => $class_id,
                    'subject_name'    => $sub_name,
                    'subject_code'    => $sub_code,
                    'total_marks'     => $tot_m,
                    'pass_marks'      => $pass_m,
                    'cq_marks'        => $cq_m,
                    'cq_pass'         => $cq_p,
                    'mcq_marks'       => $mcq_m,
                    'mcq_pass'        => $mcq_p,
                    'practical_marks' => $pr_m,
                    'practical_pass'  => $pr_p,
                ),
                array( 'id' => $sub_id ),
                array( '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f' ),
                array( '%d' )
            );

            $redirect_target = add_query_arg( array( 'status' => 'updated' ), $base_url );
            echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_target ) . '";</script>';
            exit;
        }
    }
}

// --------------------------------------------------------------------------
// 2. REPEATER SUBMISSION (BULK ASSIGN)
// --------------------------------------------------------------------------
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_subjects_repeater'] ) ) {
    if ( isset( $_POST['subject_setup_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['subject_setup_nonce'] ) ), 'subject_setup_action' ) ) {
        $class_id        = absint( $_POST['class_id'] );
        $subject_name    = isset( $_POST['subject_name'] ) && is_array( $_POST['subject_name'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['subject_name'] ) ) : array();
        $subject_code    = isset( $_POST['subject_code'] ) && is_array( $_POST['subject_code'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['subject_code'] ) ) : array();
        $total_marks     = isset( $_POST['total_marks'] ) && is_array( $_POST['total_marks'] ) ? array_map( 'floatval', $_POST['total_marks'] ) : array();
        $pass_marks      = isset( $_POST['pass_marks'] ) && is_array( $_POST['pass_marks'] ) ? array_map( 'floatval', $_POST['pass_marks'] ) : array();
        $cq_marks        = isset( $_POST['cq_marks'] ) && is_array( $_POST['cq_marks'] ) ? array_map( 'floatval', $_POST['cq_marks'] ) : array();
        $cq_pass         = isset( $_POST['cq_pass'] ) && is_array( $_POST['cq_pass'] ) ? array_map( 'floatval', $_POST['cq_pass'] ) : array();
        $mcq_marks       = isset( $_POST['mcq_marks'] ) && is_array( $_POST['mcq_marks'] ) ? array_map( 'floatval', $_POST['mcq_marks'] ) : array();
        $mcq_pass        = isset( $_POST['mcq_pass'] ) ? array_map( 'floatval', $_POST['mcq_pass'] ) : array();
        $practical_marks = isset( $_POST['practical_marks'] ) && is_array( $_POST['practical_marks'] ) ? array_map( 'floatval', $_POST['practical_marks'] ) : array();
        $practical_pass  = isset( $_POST['practical_pass'] ) && is_array( $_POST['practical_pass'] ) ? array_map( 'floatval', $_POST['practical_pass'] ) : array();

        if ( $class_id > 0 && ! empty( $subject_name ) ) {
            $inserted_count = 0;
            foreach ( $subject_name as $index => $name ) {
                $s_name = sanitize_text_field( $name );
                if ( empty( $s_name ) ) {
                    continue;
                }

                $s_code      = isset( $subject_code[ $index ] ) ? sanitize_text_field( $subject_code[ $index ] ) : '';
                $s_total     = isset( $total_marks[ $index ] ) && floatval( $total_marks[ $index ] ) > 0 ? floatval( $total_marks[ $index ] ) : 100.00;
                $s_pass      = isset( $pass_marks[ $index ] ) ? floatval( $pass_marks[ $index ] ) : 33.00;
                $s_cq        = isset( $cq_marks[ $index ] ) ? floatval( $cq_marks[ $index ] ) : 70.00;
                $s_cq_p      = isset( $cq_pass[ $index ] ) ? floatval( $cq_pass[ $index ] ) : 23.00;
                $s_mcq       = isset( $mcq_marks[ $index ] ) ? floatval( $mcq_marks[ $index ] ) : 30.00;
                $s_mcq_p     = isset( $mcq_pass[ $index ] ) ? floatval( $mcq_pass[ $index ] ) : 10.00;
                $s_practical = isset( $practical_marks[ $index ] ) ? floatval( $practical_marks[ $index ] ) : 0.00;
                $s_pr_p      = isset( $practical_pass[ $index ] ) ? floatval( $practical_pass[ $index ] ) : 0.00;

                $wpdb->insert( 
                    $table_subjects, 
                    array( 
                        'class_id'        => $class_id, 
                        'subject_name'    => $s_name, 
                        'subject_code'    => $s_code, 
                        'total_marks'     => $s_total, 
                        'pass_marks'      => $s_pass, 
                        'cq_marks'        => $s_cq, 
                        'cq_pass'         => $s_cq_p, 
                        'mcq_marks'       => $s_mcq, 
                        'mcq_pass'        => $s_mcq_p, 
                        'practical_marks' => $s_practical, 
                        'practical_pass'  => $s_pr_p, 
                    ), 
                    array( '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f' ) 
                );
                $inserted_count++;
            }

            if ( $inserted_count > 0 ) {
                $redirect_target = add_query_arg( array( 'status' => 'subjects_added', 'count' => $inserted_count ), $base_url );
                echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_target ) . '";</script>';
                exit;
            }
        }
    }
}

// --------------------------------------------------------------------------
// 3. HANDLE DELETE ACTION
// --------------------------------------------------------------------------
if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_subject' && isset( $_GET['id'] ) ) {
    $delete_id = absint( $_GET['id'] );
    $_nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

    if ( $delete_id > 0 && wp_verify_nonce( $_nonce, 'delete_subject_action_' . $delete_id ) ) {
        $wpdb->delete( $table_subjects, array( 'id' => $delete_id ), array( '%d' ) );
        $redirect_target = add_query_arg( array( 'status' => 'deleted' ), $base_url );
        echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_target ) . '";</script>';
        exit;
    }
}

// --------------------------------------------------------------------------
// 4. DATA QUERIES
// --------------------------------------------------------------------------
$classes = $wpdb->get_results( 
    "SELECT id, class_name, section_name FROM {$table_units} 
     WHERE class_name IS NOT NULL AND class_name != '' 
     ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" 
);

if ( ! empty( $classes ) ) {
    usort( $classes, function( $a, $b ) {
        $res = strnatcasecmp( $a->class_name, $b->class_name );
        return ( $res === 0 ) ? strnatcasecmp( $a->section_name, $b->section_name ) : $res;
    });
}

$subjects_list = $wpdb->get_results("
    SELECT s.*, u.class_name, u.section_name 
    FROM {$table_subjects} s 
    LEFT JOIN {$table_units} u ON s.class_id = u.id 
    ORDER BY CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC, s.subject_name ASC
");

if ( ! empty( $subjects_list ) ) {
    usort( $subjects_list, function( $a, $b ) {
        $classA = $a->class_name ?: '';
        $classB = $b->class_name ?: '';
        $res = strnatcasecmp( $classA, $classB );
        if ( $res === 0 ) {
            $secA = $a->section_name ?: '';
            $secB = $b->section_name ?: '';
            $secRes = strnatcasecmp( $secA, $secB );
            return ( $secRes === 0 ) ? strnatcasecmp( $a->subject_name, $b->subject_name ) : $secRes;
        }
        return $res;
    });
}
?>

<style>
    .dpt-subjects-container {
        font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #0f172a;
        display: flex;
        flex-direction: column;
        gap: 24px;
        margin-top: 15px;
    }

    .dpt-bento-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px 28px;
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

    .dpt-form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .dpt-form-label {
        font-size: 11.5px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .dpt-field-input,
    .dpt-field-select {
        width: 100%;
        height: 40px;
        padding: 0 12px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 13px;
        color: #0f172a;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    .dpt-field-input:focus,
    .dpt-field-select:focus {
        outline: none;
        border-color: #006a4e;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
    }

    .dpt-repeater-canvas {
        display: flex;
        flex-direction: column;
        gap: 14px;
        margin-bottom: 16px;
    }

    .dpt-repeater-row {
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: #f8fafc;
        padding: 16px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }

    .dpt-repeater-grid-top {
        display: grid;
        grid-template-columns: 2fr 1fr 180px 42px;
        gap: 12px;
        align-items: end;
    }

    .dpt-repeater-grid-marks {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        background: #ffffff;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    @media (max-width: 992px) {
        .dpt-repeater-grid-top { grid-template-columns: 1fr; }
        .dpt-repeater-grid-marks { grid-template-columns: 1fr 1fr; }
    }

    .dpt-btn-remove-row {
        height: 40px;
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
        padding: 12px 28px;
        background: #006a4e;
        color: #ffffff;
        font-size: 14px;
        font-weight: 800;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        transition: all 0.2s ease;
    }

    .dpt-btn-submit:hover {
        background: #00523c;
    }

    .dpt-btn-cancel {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #475569;
        font-weight: 700;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
    }

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
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }

    .dpt-architecture-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13px;
    }

    .dpt-architecture-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        padding: 14px 16px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
        white-space: nowrap;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .dpt-architecture-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        vertical-align: middle;
        background: #ffffff;
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
        font-size: 11.5px;
        font-weight: 700;
    }

    .dpt-marks-badge {
        display: inline-block;
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 800;
    }

    .dpt-breakdown-chip {
        display: inline-flex;
        gap: 6px;
        font-size: 12px;
        color: #475569;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 4px 8px;
        border-radius: 6px;
        font-weight: 500;
    }

    .dpt-square-btn {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        text-decoration: none;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .dpt-btn-edit { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    .dpt-btn-edit:hover { background: #2563eb; color: #ffffff; }
    .dpt-btn-delete { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
    .dpt-btn-delete:hover { background: #dc2626; color: #ffffff; }

    .afdp-alert-success {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #047857;
        padding: 14px 18px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 13.5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .dpt-modal-backdrop {
        position: fixed;
        top: 0; left: 0; width: 100vw; height: 100vh;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(4px);
        display: flex; align-items: center; justify-content: center;
        z-index: 999999; opacity: 0; visibility: hidden;
        transition: all 0.25s ease;
    }

    .dpt-modal-backdrop.is-visible { opacity: 1; visibility: visible; }
    .dpt-modal-card {
        background: #ffffff; width: 100%; max-width: 650px;
        border-radius: 16px; padding: 28px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0; transform: translateY(20px);
        transition: transform 0.25s ease;
    }
    .dpt-modal-backdrop.is-visible .dpt-modal-card { transform: translateY(0); }
</style>

<div class="dpt-subjects-container">

    <!-- Status Alerts -->
    <?php if ( isset( $_GET['status'] ) && $_GET['status'] === 'subjects_added' ) : ?>
        <div class="afdp-alert-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php 
                $count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
                printf( esc_html__( 'Successfully assigned %d new subjects to class.', 'ifsedu-sms' ), $count ); 
            ?>
        </div>
    <?php elseif ( isset( $_GET['status'] ) && $_GET['status'] === 'updated' ) : ?>
        <div class="afdp-alert-success" style="background:#eff6ff; border-color:#bfdbfe; color:#1e40af;">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e( 'Subject evaluation scheme updated successfully.', 'ifsedu-sms' ); ?>
        </div>
    <?php elseif ( isset( $_GET['status'] ) && $_GET['status'] === 'deleted' ) : ?>
        <div class="afdp-alert-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e( 'Academic subject deleted successfully.', 'ifsedu-sms' ); ?>
        </div>
    <?php endif; ?>

    <!-- Assign Subjects Repeater Bento Card -->
    <div class="dpt-bento-card">
        <div class="afdp-card-header">
            <h5 class="afdp-card-title">
                <span class="dashicons dashicons-book"></span>
                <?php esc_html_e( 'Assign Subjects & Mark Distributions', 'ifsedu-sms' ); ?>
            </h5>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'subject_setup_action', 'subject_setup_nonce' ); ?>
            
            <div class="dpt-form-group" style="margin-bottom: 20px; max-width: 400px;">
                <label class="dpt-form-label"><?php esc_html_e( 'Target Class & Section', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
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
                    <!-- Row Top -->
                    <div class="dpt-repeater-grid-top">
                        <div class="dpt-form-group">
                            <label class="dpt-form-label"><?php esc_html_e( 'Subject Title', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                            <input type="text" name="subject_name[]" class="dpt-field-input" placeholder="e.g. Physics / Mathematics" required>
                        </div>
                        <div class="dpt-form-group">
                            <label class="dpt-form-label"><?php esc_html_e( 'Code', 'ifsedu-sms' ); ?></label>
                            <input type="text" name="subject_code[]" class="dpt-field-input" placeholder="e.g. 174">
                        </div>
                        <div class="dpt-form-group">
                            <label class="dpt-form-label"><?php esc_html_e( 'Distribution Preset', 'ifsedu-sms' ); ?></label>
                            <select class="dpt-field-select preset-selector">
                                <option value="gen_100">General (70/30)</option>
                                <option value="sci_100">Science (50/25/25)</option>
                                <option value="lang_100">Language (100 CQ)</option>
                                <option value="jun_50">Junior Tier (35/15)</option>
                            </select>
                        </div>
                        <div>
                            <button type="button" class="dpt-btn-remove-row btn-remove-row" disabled>
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Row Marks -->
                    <div class="dpt-repeater-grid-marks">
                        <div class="dpt-form-group">
                            <label class="dpt-form-label"><?php esc_html_e( 'Total / Pass', 'ifsedu-sms' ); ?></label>
                            <div style="display:flex; gap:6px;">
                                <input type="number" step="0.5" name="total_marks[]" class="dpt-field-input f-total" value="100" placeholder="Total" required>
                                <input type="number" step="0.5" name="pass_marks[]" class="dpt-field-input f-pass" value="33" placeholder="Pass">
                            </div>
                        </div>

                        <div class="dpt-form-group">
                            <label class="dpt-form-label"><?php esc_html_e( 'CQ (Total / Pass)', 'ifsedu-sms' ); ?></label>
                            <div style="display:flex; gap:6px;">
                                <input type="number" step="0.5" name="cq_marks[]" class="dpt-field-input f-cq" value="70">
                                <input type="number" step="0.5" name="cq_pass[]" class="dpt-field-input f-cq-pass" value="23">
                            </div>
                        </div>

                        <div class="dpt-form-group">
                            <label class="dpt-form-label"><?php esc_html_e( 'MCQ (Total / Pass)', 'ifsedu-sms' ); ?></label>
                            <div style="display:flex; gap:6px;">
                                <input type="number" step="0.5" name="mcq_marks[]" class="dpt-field-input f-mcq" value="30">
                                <input type="number" step="0.5" name="mcq_pass[]" class="dpt-field-input f-mcq-pass" value="10">
                            </div>
                        </div>

                        <div class="dpt-form-group">
                            <label class="dpt-form-label"><?php esc_html_e( 'Practical (Tot / Pass)', 'ifsedu-sms' ); ?></label>
                            <div style="display:flex; gap:6px;">
                                <input type="number" step="0.5" name="practical_marks[]" class="dpt-field-input f-pr" value="0">
                                <input type="number" step="0.5" name="practical_pass[]" class="dpt-field-input f-pr-pass" value="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" id="btn-add-subject" class="dpt-btn-add-repeater">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Add Another Subject Row', 'ifsedu-sms' ); ?>
            </button>

            <button type="submit" name="save_subjects_repeater" class="dpt-btn-submit">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e( 'Save All Subjects', 'ifsedu-sms' ); ?>
            </button>
        </form>
    </div>

    <!-- Mapped Subjects Table Bento Card -->
    <div class="dpt-bento-card">
        <div class="afdp-card-header">
            <h5 class="afdp-card-title">
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e( 'Academic Subjects Directory', 'ifsedu-sms' ); ?>
            </h5>
            
            <div class="dpt-header-actions">
                <select id="dpt-class-filter" class="dpt-field-select dpt-filter-select">
                    <option value="all"><?php esc_html_e( '-- All Classes --', 'ifsedu-sms' ); ?></option>
                    <?php foreach ( $classes as $cls ) : 
                        $label = $cls->class_name . ( ! empty( $cls->section_name ) ? ' (' . $cls->section_name . ')' : '' );
                    ?>
                        <option value="<?php echo esc_attr( $cls->id ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>

                <span class="dpt-count-pill" id="dpt-subject-count-pill">
                    <?php echo esc_html( count( $subjects_list ) ); ?> <?php esc_html_e( 'Subjects', 'ifsedu-sms' ); ?>
                </span>
            </div>
        </div>

        <div class="dpt-responsive-datatable">
            <table class="dpt-architecture-table" id="dpt-subjects-table">
                <thead>
                    <tr>
                        <th style="width: 22%;"><?php esc_html_e( 'Class / Section', 'ifsedu-sms' ); ?></th>
                        <th style="width: 25%;"><?php esc_html_e( 'Subject Details', 'ifsedu-sms' ); ?></th>
                        <th style="width: 15%;"><?php esc_html_e( 'Full / Pass', 'ifsedu-sms' ); ?></th>
                        <th style="width: 26%;"><?php esc_html_e( 'Mark Distribution (CQ / MCQ / PR)', 'ifsedu-sms' ); ?></th>
                        <th style="width: 12%; text-align:right;"><?php esc_html_e( 'Actions', 'ifsedu-sms' ); ?></th>
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
                            <td>
                                <strong style="color: #0f172a;" class="cell-subject-name"><?php echo esc_html( $sub->subject_name ); ?></strong>
                                <?php if ( ! empty( $sub->subject_code ) ) : ?>
                                    <span class="dpt-code-tag cell-subject-code"><?php echo esc_html( $sub->subject_code ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="dpt-marks-badge cell-total-marks"><?php echo esc_html( floatval( $sub->total_marks ?? 100 ) ); ?></span>
                                <small style="color:#64748b; font-weight:700;">(Pass: <span class="cell-pass-marks"><?php echo esc_html( floatval( $sub->pass_marks ?? 33 ) ); ?></span>)</small>
                            </td>
                            <td>
                                <div class="dpt-breakdown-chip cell-breakdown-marks">
                                    <span>CQ: <strong><?php echo esc_html( floatval( $sub->cq_marks ?? 0 ) ); ?></strong> <small>(≥<?php echo esc_html( floatval( $sub->cq_pass ?? 0 ) ); ?>)</small></span> |
                                    <span>MCQ: <strong><?php echo esc_html( floatval( $sub->mcq_marks ?? 0 ) ); ?></strong> <small>(≥<?php echo esc_html( floatval( $sub->mcq_pass ?? 0 ) ); ?>)</small></span>
                                    <?php if ( floatval( $sub->practical_marks ?? 0 ) > 0 ) : ?>
                                        | <span>PR: <strong><?php echo esc_html( floatval( $sub->practical_marks ) ); ?></strong> <small>(≥<?php echo esc_html( floatval( $sub->practical_pass ?? 0 ) ); ?>)</small></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align: right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <button type="button" 
                                            class="dpt-square-btn dpt-btn-edit btn-trigger-edit" 
                                            data-id="<?php echo esc_attr( $sub->id ); ?>"
                                            data-class-id="<?php echo esc_attr( $sub->class_id ); ?>"
                                            data-name="<?php echo esc_attr( $sub->subject_name ); ?>"
                                            data-code="<?php echo esc_attr( $sub->subject_code ); ?>"
                                            data-total="<?php echo esc_attr( $sub->total_marks ?? 100 ); ?>"
                                            data-pass="<?php echo esc_attr( $sub->pass_marks ?? 33 ); ?>"
                                            data-cq="<?php echo esc_attr( $sub->cq_marks ?? 0 ); ?>"
                                            data-cq-pass="<?php echo esc_attr( $sub->cq_pass ?? 0 ); ?>"
                                            data-mcq="<?php echo esc_attr( $sub->mcq_marks ?? 0 ); ?>"
                                            data-mcq-pass="<?php echo esc_attr( $sub->mcq_pass ?? 0 ); ?>"
                                            data-practical="<?php echo esc_attr( $sub->practical_marks ?? 0 ); ?>"
                                            data-practical-pass="<?php echo esc_attr( $sub->practical_pass ?? 0 ); ?>"
                                            title="<?php esc_attr_e( 'Edit Subject Scheme', 'ifsedu-sms' ); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>

                                    <a href="<?php echo esc_url( $delete_url ); ?>" 
                                       class="dpt-square-btn dpt-btn-delete" 
                                       title="<?php esc_attr_e( 'Delete Subject', 'ifsedu-sms' ); ?>" 
                                       onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove this subject?', 'ifsedu-sms' ) ); ?>');">
                                        <span class="dashicons dashicons-trash"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr id="dpt-no-subjects-row">
                            <td colspan="5" style="text-align:center; padding: 40px; color: #94a3b8;">
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
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:14px; margin-bottom:18px;">
            <h4 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;"><?php esc_html_e( 'Edit Subject & Evaluation Scheme', 'ifsedu-sms' ); ?></h4>
            <button type="button" id="dpt-close-modal" style="background:none; border:none; font-size:22px; cursor:pointer; color:#64748b;">&times;</button>
        </div>

        <form id="dpt-edit-subject-form" method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <input type="hidden" id="edit_subject_id" name="subject_id" value="">
            <input type="hidden" name="educore_update_single_subject" value="1">
            <?php wp_nonce_field( 'dpt_edit_subject_nonce', 'edit_subject_nonce_field' ); ?>

            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:12px; margin-bottom: 12px;">
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Academic Class & Section', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                    <select id="edit_class_id" name="class_id" class="dpt-field-select" required>
                        <?php foreach ( $classes as $cls ) : 
                            $label = $cls->class_name . ( ! empty( $cls->section_name ) ? ' (' . $cls->section_name . ')' : '' );
                        ?>
                            <option value="<?php echo esc_attr( $cls->id ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Preset Distribution', 'ifsedu-sms' ); ?></label>
                    <select class="dpt-field-select" id="edit_preset_selector">
                        <option value="gen_100">General (70/30)</option>
                        <option value="sci_100">Science (50/25/25)</option>
                        <option value="lang_100">Language (100 CQ)</option>
                        <option value="jun_50">Junior Tier (35/15)</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:12px; margin-bottom: 12px;">
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Subject Name', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="edit_subject_name" name="subject_name" class="dpt-field-input" required>
                </div>
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Code', 'ifsedu-sms' ); ?></label>
                    <input type="text" id="edit_subject_code" name="subject_code" class="dpt-field-input">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom: 12px;">
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Full Marks', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                    <input type="number" step="0.5" id="edit_total_marks" name="total_marks" class="dpt-field-input" required>
                </div>
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Overall Pass Marks', 'ifsedu-sms' ); ?></label>
                    <input type="number" step="0.5" id="edit_pass_marks" name="pass_marks" class="dpt-field-input">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; margin-bottom: 18px; background:#f8fafc; padding:14px; border-radius:10px; border:1px solid #e2e8f0;">
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'CQ (Total / Pass)', 'ifsedu-sms' ); ?></label>
                    <div style="display:flex; gap:4px;">
                        <input type="number" step="0.5" id="edit_cq_marks" name="cq_marks" class="dpt-field-input" placeholder="CQ">
                        <input type="number" step="0.5" id="edit_cq_pass" name="cq_pass" class="dpt-field-input" placeholder="Pass">
                    </div>
                </div>
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'MCQ (Total / Pass)', 'ifsedu-sms' ); ?></label>
                    <div style="display:flex; gap:4px;">
                        <input type="number" step="0.5" id="edit_mcq_marks" name="mcq_marks" class="dpt-field-input" placeholder="MCQ">
                        <input type="number" step="0.5" id="edit_mcq_pass" name="mcq_pass" class="dpt-field-input" placeholder="Pass">
                    </div>
                </div>
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Practical (Tot / Pass)', 'ifsedu-sms' ); ?></label>
                    <div style="display:flex; gap:4px;">
                        <input type="number" step="0.5" id="edit_practical_marks" name="practical_marks" class="dpt-field-input" placeholder="PR">
                        <input type="number" step="0.5" id="edit_practical_pass" name="practical_pass" class="dpt-field-input" placeholder="Pass">
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="dpt-btn-cancel" id="dpt-cancel-edit"><?php esc_html_e( 'Cancel', 'ifsedu-sms' ); ?></button>
                <button type="submit" class="dpt-btn-submit" id="dpt-save-edit-btn">
                    <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Update Scheme', 'ifsedu-sms' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    function applyPresetValues(totalInp, passInp, cqInp, cqPass, mcqInp, mcqPass, prInp, prPass, presetKey) {
        if (!totalInp) return;
        if (presetKey === 'gen_100') {
            totalInp.value = 100; passInp.value = 33;
            cqInp.value = 70; cqPass.value = 23;
            mcqInp.value = 30; mcqPass.value = 10;
            prInp.value = 0; prPass.value = 0;
        } else if (presetKey === 'sci_100') {
            totalInp.value = 100; passInp.value = 33;
            cqInp.value = 50; cqPass.value = 17;
            mcqInp.value = 25; mcqPass.value = 8;
            prInp.value = 25; prPass.value = 8;
        } else if (presetKey === 'lang_100') {
            totalInp.value = 100; passInp.value = 33;
            cqInp.value = 100; cqPass.value = 33;
            mcqInp.value = 0; mcqPass.value = 0;
            prInp.value = 0; prPass.value = 0;
        } else if (presetKey === 'jun_50') {
            totalInp.value = 50; passInp.value = 17;
            cqInp.value = 35; cqPass.value = 12;
            mcqInp.value = 15; mcqPass.value = 5;
            prInp.value = 0; prPass.value = 0;
        }
    }

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('preset-selector')) {
            const row = e.target.closest('.dpt-repeater-row');
            if (row) {
                applyPresetValues(
                    row.querySelector('.f-total'), row.querySelector('.f-pass'),
                    row.querySelector('.f-cq'), row.querySelector('.f-cq-pass'),
                    row.querySelector('.f-mcq'), row.querySelector('.f-mcq-pass'),
                    row.querySelector('.f-pr'), row.querySelector('.f-pr-pass'),
                    e.target.value
                );
            }
        }
        if (e.target.id === 'edit_preset_selector') {
            applyPresetValues(
                document.getElementById('edit_total_marks'), document.getElementById('edit_pass_marks'),
                document.getElementById('edit_cq_marks'), document.getElementById('edit_cq_pass'),
                document.getElementById('edit_mcq_marks'), document.getElementById('edit_mcq_pass'),
                document.getElementById('edit_practical_marks'), document.getElementById('edit_practical_pass'),
                e.target.value
            );
        }
    });

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

            newRow.querySelectorAll('input[type="text"]').forEach(inp => inp.value = '');
            applyPresetValues(
                newRow.querySelector('.f-total'), newRow.querySelector('.f-pass'),
                newRow.querySelector('.f-cq'), newRow.querySelector('.f-cq-pass'),
                newRow.querySelector('.f-mcq'), newRow.querySelector('.f-mcq-pass'),
                newRow.querySelector('.f-pr'), newRow.querySelector('.f-pr-pass'),
                'gen_100'
            );

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

    const filterSelect = document.getElementById('dpt-class-filter');
    const tableBody    = document.querySelector('#dpt-subjects-table tbody');
    const countPill    = document.getElementById('dpt-subject-count-pill');

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

            if (countPill) countPill.textContent = visibleCount + ' <?php echo esc_js( __( 'Subjects', 'ifsedu-sms' ) ); ?>';
        });
    }

    const modal          = document.getElementById('dpt-edit-modal');
    const closeModalBtn  = document.getElementById('dpt-close-modal');
    const cancelModalBtn = document.getElementById('dpt-cancel-edit');
    const editForm       = document.getElementById('dpt-edit-subject-form');

    function hideModal() {
        if (modal) modal.classList.remove('is-visible');
    }

    if (closeModalBtn) closeModalBtn.addEventListener('click', hideModal);
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', hideModal);

    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.btn-trigger-edit');
        if (editBtn) {
            document.getElementById('edit_subject_id').value      = editBtn.getAttribute('data-id');
            document.getElementById('edit_class_id').value        = editBtn.getAttribute('data-class-id');
            document.getElementById('edit_subject_name').value    = editBtn.getAttribute('data-name');
            document.getElementById('edit_subject_code').value    = editBtn.getAttribute('data-code');
            document.getElementById('edit_total_marks').value     = editBtn.getAttribute('data-total');
            document.getElementById('edit_pass_marks').value      = editBtn.getAttribute('data-pass');
            document.getElementById('edit_cq_marks').value        = editBtn.getAttribute('data-cq');
            document.getElementById('edit_cq_pass').value         = editBtn.getAttribute('data-cq-pass');
            document.getElementById('edit_mcq_marks').value       = editBtn.getAttribute('data-mcq');
            document.getElementById('edit_mcq_pass').value        = editBtn.getAttribute('data-mcq-pass');
            document.getElementById('edit_practical_marks').value = editBtn.getAttribute('data-practical');
            document.getElementById('edit_practical_pass').value  = editBtn.getAttribute('data-practical-pass');

            modal.classList.add('is-visible');
        }
    });

    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            const submitBtn    = document.getElementById('dpt-save-edit-btn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="dashicons dashicons-update" style="animation: rotation 2s infinite linear;"></span> <?php echo esc_js( __( 'Saving...', 'ifsedu-sms' ) ); ?>';

            const formData = new FormData(editForm);
            formData.append('action', 'dpt_update_subject');
            formData.append('security', document.getElementById('edit_subject_nonce_field').value);

            // Attempt instantaneous AJAX with automatic fallback to standard POST submission
            fetch('<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;

                if (data && data.success) {
                    const subjectId = document.getElementById('edit_subject_id').value;
                    const row = document.querySelector('tr[data-subject-id="' + subjectId + '"]');

                    if (row) {
                        const classSelect = document.getElementById('edit_class_id');
                        const newName     = document.getElementById('edit_subject_name').value;
                        const newCode     = document.getElementById('edit_subject_code').value;
                        const newTotal    = parseFloat(document.getElementById('edit_total_marks').value) || 0;
                        const newPass     = parseFloat(document.getElementById('edit_pass_marks').value) || 0;
                        const newCQ       = parseFloat(document.getElementById('edit_cq_marks').value) || 0;
                        const newCQPass   = parseFloat(document.getElementById('edit_cq_pass').value) || 0;
                        const newMCQ      = parseFloat(document.getElementById('edit_mcq_marks').value) || 0;
                        const newMCQPass  = parseFloat(document.getElementById('edit_mcq_pass').value) || 0;
                        const newPR       = parseFloat(document.getElementById('edit_practical_marks').value) || 0;
                        const newPRPass   = parseFloat(document.getElementById('edit_practical_pass').value) || 0;

                        row.setAttribute('data-class-id', classSelect.value);
                        row.querySelector('.cell-class-name').textContent = classSelect.options[classSelect.selectedIndex].text;
                        row.querySelector('.cell-subject-name').textContent = newName;
                        if (row.querySelector('.cell-subject-code')) {
                            row.querySelector('.cell-subject-code').textContent = newCode;
                        }
                        row.querySelector('.cell-total-marks').textContent = newTotal;
                        row.querySelector('.cell-pass-marks').textContent = newPass;

                        let breakdownHtml = '<span>CQ: <strong>' + newCQ + '</strong> <small>(≥' + newCQPass + ')</small></span> | <span>MCQ: <strong>' + newMCQ + '</strong> <small>(≥' + newMCQPass + ')</small></span>';
                        if (newPR > 0) {
                            breakdownHtml += ' | <span>PR: <strong>' + newPR + '</strong> <small>(≥' + newPRPass + ')</small></span>';
                        }
                        row.querySelector('.cell-breakdown-marks').innerHTML = breakdownHtml;

                        const editBtn = row.querySelector('.btn-trigger-edit');
                        if (editBtn) {
                            editBtn.setAttribute('data-class-id', classSelect.value);
                            editBtn.setAttribute('data-name', newName);
                            editBtn.setAttribute('data-code', newCode);
                            editBtn.setAttribute('data-total', newTotal);
                            editBtn.setAttribute('data-pass', newPass);
                            editBtn.setAttribute('data-cq', newCQ);
                            editBtn.setAttribute('data-cq-pass', newCQPass);
                            editBtn.setAttribute('data-mcq', newMCQ);
                            editBtn.setAttribute('data-mcq-pass', newMCQPass);
                            editBtn.setAttribute('data-practical', newPR);
                            editBtn.setAttribute('data-practical-pass', newPRPass);
                        }
                    }
                    hideModal();
                } else {
                    // Fallback to standard POST form submit
                    editForm.submit();
                }
            })
            .catch(() => {
                // If AJAX endpoint is unreachable from view file, submit standard form
                editForm.submit();
            });

            e.preventDefault();
        });
    }
});
</script>