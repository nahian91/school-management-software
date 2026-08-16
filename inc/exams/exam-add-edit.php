<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add / Edit Examination Scheme View
 * File: inc/exams/exam-add-edit.php
 */
function educore_exam_add_edit_view() {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to configure examinations.', 'ifsedu-sms' ) );
    }

    $table_exams = $wpdb->prefix . 'sms_exams';
    $table_units = $wpdb->prefix . 'sms_academic_units';

    $list_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=list' );

    $get_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
    $get_id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    $is_edit    = ( 'edit' === $get_action && $get_id > 0 );
    $edit_exam  = null;

    $edit_exam_title  = '';
    $edit_exam_year   = current_time( 'Y' );
    $selected_classes = array();

    if ( $is_edit ) {
        $edit_exam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_exams} WHERE id = %d", $get_id ) );
        if ( $edit_exam ) {
            $parts = explode( ' - ', $edit_exam->exam_name );
            if ( count( $parts ) > 1 && is_numeric( end( $parts ) ) ) {
                $edit_exam_year  = array_pop( $parts );
                $edit_exam_title = implode( ' - ', $parts );
            } else {
                $edit_exam_title = $edit_exam->exam_name;
            }

            if ( ! empty( $edit_exam->class_name ) ) {
                $selected_classes = array_map( 'trim', explode( ',', $edit_exam->class_name ) );
            }
        } else {
            $is_edit = false;
        }
    }

    // Handle Save / Update
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['educore_save_exam'] ) ) {
        check_admin_referer( 'save_exam_action', 'educore_exam_nonce' );

        $exam_id_input    = isset( $_POST['exam_id'] ) ? intval( $_POST['exam_id'] ) : 0;
        $exam_title_input = isset( $_POST['exam_title'] ) ? sanitize_text_field( wp_unslash( $_POST['exam_title'] ) ) : '';
        $exam_year_input  = isset( $_POST['exam_year'] ) ? sanitize_text_field( wp_unslash( $_POST['exam_year'] ) ) : current_time( 'Y' );
        $full_exam_name   = ! empty( $exam_year_input ) ? $exam_title_input . ' - ' . $exam_year_input : $exam_title_input;

        $class_names_input = isset( $_POST['class_name'] ) && is_array( $_POST['class_name'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['class_name'] ) ) : array();
        $class_name        = ! empty( $class_names_input ) ? implode( ', ', $class_names_input ) : '';

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
            $wpdb->update( $table_exams, $data, array( 'id' => $exam_id_input ), $format, array( '%d' ) );
            $redirect_target = add_query_arg( array( 'status' => 'updated' ), $list_url );
        } else {
            $wpdb->insert( $table_exams, $data, $format );
            $redirect_target = add_query_arg( array( 'status' => 'success' ), $list_url );
        }

        echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_target ) . '";</script>';
        exit;
    }

    // Fetch classes for tier selection with natural numeric sorting
    $raw_classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    if ( ! empty( $raw_classes ) ) {
        usort( $raw_classes, function( $a, $b ) {
            return strnatcasecmp( $a->class_name, $b->class_name );
        } );
    }
    ?>

    <style>
        .dpt-exam-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            max-width: 800px;
            margin: 0 auto;
        }
        .dpt-form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
        .dpt-form-label { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.3px; }
        .dpt-input-field, .dpt-select-field {
            width: 100%; height: 42px; border: 1px solid #cbd5e1; border-radius: 8px;
            padding: 0 14px; font-size: 13.5px; color: #0f172a; background: #f8fafc;
            box-sizing: border-box; transition: all 0.2s;
        }
        .dpt-input-field:focus, .dpt-select-field:focus {
            border-color: #006a4e; background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12); outline: none;
        }
        .dpt-checkbox-box {
            background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px;
            padding: 14px; max-height: 180px; overflow-y: auto; display: flex;
            flex-direction: column; gap: 8px;
        }
        .dpt-checkbox-item { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600; color: #334155; cursor: pointer; }
        .dpt-btn-save {
            height: 42px; padding: 0 28px; background: #006a4e; color: #ffffff;
            font-weight: 700; font-size: 14px; border: none; border-radius: 8px;
            cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }
        .dpt-btn-save:hover { background: #00523c; }
    </style>

    <div class="dpt-exam-card">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:24px;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">
                <span class="dashicons dashicons-edit" style="color:#006a4e;"></span>
                <?php echo $is_edit ? esc_html__( 'Edit Examination Scheme', 'ifsedu-sms' ) : esc_html__( 'Create New Examination Scheme', 'ifsedu-sms' ); ?>
            </h3>
            <a href="<?php echo esc_url( $list_url ); ?>" style="text-decoration:none; color:#475569; font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:4px;">
                <span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'Back to List', 'ifsedu-sms' ); ?>
            </a>
        </div>

        <form method="POST" action="" id="educoreExamForm">
            <?php wp_nonce_field( 'save_exam_action', 'educore_exam_nonce' ); ?>
            <input type="hidden" name="exam_id" value="<?php echo $is_edit ? intval( $edit_exam->id ) : 0; ?>">

            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px;">
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Exam Title', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="exam_title" class="dpt-input-field" placeholder="e.g. First Term Examination / Annual Exam" value="<?php echo esc_attr( $edit_exam_title ); ?>" required>
                </div>
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Academic Year', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="number" name="exam_year" class="dpt-input-field" min="2020" max="2099" value="<?php echo esc_attr( $edit_exam_year ); ?>" required>
                </div>
            </div>

            <div class="dpt-form-group">
                <label class="dpt-form-label"><?php esc_html_e( 'Applicable Classes', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                <div class="dpt-checkbox-box">
                    <?php if ( ! empty( $raw_classes ) ) : foreach ( $raw_classes as $cls_obj ) : ?>
                        <label class="dpt-checkbox-item">
                            <input type="checkbox" name="class_name[]" value="<?php echo esc_attr( $cls_obj->class_name ); ?>" class="cb-single" <?php checked( in_array( $cls_obj->class_name, $selected_classes, true ) ); ?>>
                            <span><?php echo esc_html( $cls_obj->class_name ); ?></span>
                        </label>
                    <?php endforeach; else : ?>
                        <span style="font-size:12px; color:#94a3b8;"><?php esc_html_e( 'No classes configured yet. Please add classes in Academic Setup first.', 'ifsedu-sms' ); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px;">
                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Start Date', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="start_date" class="dpt-input-field" value="<?php echo $is_edit ? esc_attr( $edit_exam->start_date ) : current_time( 'Y-m-d' ); ?>" required>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'End Date', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="end_date" class="dpt-input-field" value="<?php echo $is_edit ? esc_attr( $edit_exam->end_date ) : current_time( 'Y-m-d' ); ?>" required>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-form-label"><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></label>
                    <select name="status" class="dpt-select-field">
                        <option value="Upcoming" <?php selected( $is_edit ? $edit_exam->status : '', 'Upcoming' ); ?>><?php esc_html_e( 'Upcoming', 'ifsedu-sms' ); ?></option>
                        <option value="Ongoing" <?php selected( $is_edit ? $edit_exam->status : '', 'Ongoing' ); ?>><?php esc_html_e( 'Ongoing', 'ifsedu-sms' ); ?></option>
                        <option value="Completed" <?php selected( $is_edit ? $edit_exam->status : '', 'Completed' ); ?>><?php esc_html_e( 'Completed', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>
            </div>

            <div style="margin-top:24px; text-align:right;">
                <button type="submit" name="educore_save_exam" class="dpt-btn-save">
                    <span class="dashicons dashicons-saved"></span>
                    <?php echo $is_edit ? esc_html__( 'Update Exam Scheme', 'ifsedu-sms' ) : esc_html__( 'Save Exam Scheme', 'ifsedu-sms' ); ?>
                </button>
            </div>
        </form>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#educoreExamForm').on('submit', function(e) {
            if ($('input[name="class_name[]"]:checked').length === 0) {
                e.preventDefault();
                alert('<?php echo esc_js( __( 'Please select at least one class.', 'ifsedu-sms' ) ); ?>');
            }
        });
    });
    </script>
    <?php
}