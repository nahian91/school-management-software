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

// Handle Repeater Submit
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_subjects_repeater'] ) ) {
    if ( isset( $_POST['subject_setup_nonce'] ) && wp_verify_nonce( $_POST['subject_setup_nonce'], 'subject_setup_action' ) ) {
        $class_id     = absint( $_POST['class_id'] );
        $subject_name = isset( $_POST['subject_name'] ) && is_array( $_POST['subject_name'] ) ? $_POST['subject_name'] : array();
        $subject_code = isset( $_POST['subject_code'] ) && is_array( $_POST['subject_code'] ) ? $_POST['subject_code'] : array();
        $subject_type = isset( $_POST['subject_type'] ) && is_array( $_POST['subject_type'] ) ? $_POST['subject_type'] : array();

        if ( $class_id > 0 && ! empty( $subject_name ) ) {
            $inserted_count = 0;
            foreach ( $subject_name as $index => $name ) {
                $s_name = sanitize_text_field( $name );
                $s_code = isset( $subject_code[$index] ) ? sanitize_text_field( $subject_code[$index] ) : '';
                $s_type = isset( $subject_type[$index] ) ? sanitize_text_field( $subject_type[$index] ) : 'Mandatory';

                if ( ! empty( $s_name ) ) {
                    $wpdb->insert( 
                        $table_subjects, 
                        array( 
                            'class_id'     => $class_id, 
                            'subject_name' => $s_name, 
                            'subject_code' => $s_code, 
                            'subject_type' => $s_type 
                        ), 
                        array( '%d', '%s', '%s', '%s' ) 
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

// Natural Numeric Sorting Query Strategy for Classes Dropdown & Datatable mapping
$classes = $wpdb->get_results( 
    "SELECT * FROM {$table_units} 
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

// Subjects Directory List with Class Join and Natural Numeric Sorting
$subjects_list = $wpdb->get_results("
    SELECT s.*, u.class_name, u.section_name 
    FROM {$table_subjects} s 
    LEFT JOIN {$table_units} u ON s.class_id = u.id 
    ORDER BY CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, s.subject_name ASC
");

if ( ! empty( $subjects_list ) ) {
    usort( $subjects_list, function( $a, $b ) {
        $classA = $a->class_name ? $a->class_name : '';
        $classB = $b->class_name ? $b->class_name : '';
        $res = strnatcasecmp( $classA, $classB );
        if ( $res === 0 ) {
            return strnatcasecmp( $a->subject_name, $b->subject_name );
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
        grid-template-columns: 2fr 1fr 1fr 42px;
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

    .dpt-type-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.2px;
        text-transform: uppercase;
    }

    .dpt-badge-mandatory { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
    .dpt-badge-optional  { background: #fefce8; color: #a16207; border: 1px solid #fef08a; }

    .dpt-square-btn {
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.2s ease;
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }

    .dpt-square-btn:hover {
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
                <label class="dpt-form-label"><?php esc_html_e( 'Select Target Class', 'ifsedu-sms' ); ?> <span style="color:#dc2626;">*</span></label>
                <select name="class_id" class="dpt-field-select" required>
                    <option value=""><?php esc_html_e( '-- Choose Target Class --', 'ifsedu-sms' ); ?></option>
                    <?php foreach ( $classes as $cls ) : 
                        $cls_display = ! empty( $cls->section_name ) ? $cls->class_name . ' (' . $cls->section_name . ')' : $cls->class_name;
                    ?>
                        <option value="<?php echo esc_attr( $cls->id ); ?>"><?php echo esc_html( $cls_display ); ?></option>
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
                    <div class="dpt-form-group">
                        <label class="dpt-form-label"><?php esc_html_e( 'Subject Type', 'ifsedu-sms' ); ?></label>
                        <select name="subject_type[]" class="dpt-field-select">
                            <option value="Mandatory"><?php esc_html_e( 'Mandatory', 'ifsedu-sms' ); ?></option>
                            <option value="Optional"><?php esc_html_e( 'Optional', 'ifsedu-sms' ); ?></option>
                        </select>
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
            <span class="dpt-count-pill">
                <?php echo esc_html( count( $subjects_list ) ); ?> <?php esc_html_e( 'Subjects Configured', 'ifsedu-sms' ); ?>
            </span>
        </div>

        <div class="dpt-responsive-datatable">
            <table class="dpt-architecture-table">
                <thead>
                    <tr>
                        <th style="width: 25%;"><?php esc_html_e( 'Class Name', 'ifsedu-sms' ); ?></th>
                        <th style="width: 35%;"><?php esc_html_e( 'Subject Title', 'ifsedu-sms' ); ?></th>
                        <th style="width: 15%;"><?php esc_html_e( 'Code', 'ifsedu-sms' ); ?></th>
                        <th style="width: 15%;"><?php esc_html_e( 'Subject Type', 'ifsedu-sms' ); ?></th>
                        <th style="width: 10%; text-align:right;"><?php esc_html_e( 'Action', 'ifsedu-sms' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $subjects_list ) ) : foreach ( $subjects_list as $sub ) : 
                        $delete_url = wp_nonce_url( 
                            add_query_arg( array( 'action' => 'delete_subject', 'id' => $sub->id ), $base_url ), 
                            'delete_subject_action_' . $sub->id 
                        );
                        $type_badge_class = ( $sub->subject_type === 'Optional' ) ? 'dpt-badge-optional' : 'dpt-badge-mandatory';
                        $class_label      = $sub->class_name ? $sub->class_name : 'N/A';
                        if ( ! empty( $sub->section_name ) ) {
                            $class_label .= ' (' . $sub->section_name . ')';
                        }
                    ?>
                        <tr>
                            <td style="font-weight: 700; color: #006a4e;"><?php echo esc_html( $class_label ); ?></td>
                            <td style="font-weight: 700; color: #0f172a;"><?php echo esc_html( $sub->subject_name ); ?></td>
                            <td>
                                <span class="dpt-code-tag"><?php echo esc_html( $sub->subject_code ? $sub->subject_code : '-' ); ?></span>
                            </td>
                            <td>
                                <span class="dpt-type-badge <?php echo esc_attr( $type_badge_class ); ?>">
                                    <?php echo esc_html( $sub->subject_type ); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="<?php echo esc_url( $delete_url ); ?>" class="dpt-square-btn" title="<?php esc_attr_e( 'Delete Subject', 'ifsedu-sms' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this subject?', 'ifsedu-sms' ) ); ?>');">
                                    <span class="dashicons dashicons-trash"></span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr>
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

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('subject-repeater-canvas');
    const addBtn = document.getElementById('btn-add-subject');

    function updateRemoveButtons() {
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

            // Reset inputs & selects
            newRow.querySelectorAll('input').forEach(inp => inp.value = '');
            newRow.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);

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
});
</script>