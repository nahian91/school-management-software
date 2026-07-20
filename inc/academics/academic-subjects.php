<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$table_units    = $wpdb->prefix . 'sms_academic_units';
$table_subjects = $wpdb->prefix . 'sms_subjects';

// Handle Repeater Submit
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_subjects_repeater'] ) ) {
    if ( wp_verify_nonce( $_POST['subject_setup_nonce'], 'subject_setup_action' ) ) {
        $class_id     = intval( $_POST['class_id'] );
        $subject_name = isset( $_POST['subject_name'] ) ? $_POST['subject_name'] : array();
        $subject_code = isset( $_POST['subject_code'] ) ? $_POST['subject_code'] : array();
        $subject_type = isset( $_POST['subject_type'] ) ? $_POST['subject_type'] : array();

        if ( $class_id > 0 && ! empty( $subject_name ) ) {
            $inserted_count = 0;
            foreach ( $subject_name as $index => $name ) {
                $s_name = sanitize_text_field( $name );
                $s_code = isset( $subject_code[$index] ) ? sanitize_text_field( $subject_code[$index] ) : '';
                $s_type = isset( $subject_type[$index] ) ? sanitize_text_field( $subject_type[$index] ) : 'Mandatory';

                if ( ! empty( $s_name ) ) {
                    $wpdb->insert( $table_subjects, array( 'class_id' => $class_id, 'subject_name' => $s_name, 'subject_code' => $s_code, 'subject_type' => $s_type ), array( '%d', '%s', '%s', '%s' ) );
                    $inserted_count++;
                }
            }
            if ( $inserted_count > 0 ) {
                echo '<script>window.location.href="'.esc_url(add_query_arg(['subtab'=>'subjects','status'=>'subjects_added','count'=>$inserted_count], $base_url)).'";</script>'; exit;
            }
        }
    }
}

// Handle Delete Subject
if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_subject' && isset( $_GET['id'] ) ) {
    if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_subject_action_' . intval( $_GET['id'] ) ) ) {
        $wpdb->delete( $table_subjects, array( 'id' => intval( $_GET['id'] ) ), array( '%d' ) );
        echo '<script>window.location.href="'.esc_url(add_query_arg(['subtab'=>'subjects','status'=>'deleted'], $base_url)).'";</script>'; exit;
    }
}

$classes = $wpdb->get_results( "SELECT * FROM {$table_units} ORDER BY class_name ASC" );
$subjects_list = $wpdb->get_results("
    SELECT s.*, u.class_name 
    FROM {$table_subjects} s 
    LEFT JOIN {$table_units} u ON s.class_id = u.id 
    ORDER BY u.class_name ASC, s.subject_name ASC
");
?>

<div class="dpt-bento-box">
    <h5 class="dpt-bento-subheading">Assign Subjects to Class</h5>
    <form method="POST" action="">
        <?php wp_nonce_field( 'subject_setup_action', 'subject_setup_nonce' ); ?>
        
        <div style="margin-bottom: 24px; max-width: 400px;">
            <label class="dpt-form-label">Select Target Class <span style="color:#dc2626;">*</span></label>
            <select name="class_id" class="dpt-field-select" required>
                <option value="">-- Choose Class --</option>
                <?php foreach ( $classes as $cls ) : ?>
                    <option value="<?php echo esc_attr( $cls->id ); ?>"><?php echo esc_html( $cls->class_name ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="subject-repeater-canvas">
            <div class="repeater-row" style="display:flex; gap:16px; margin-bottom:12px; align-items:flex-end;">
                <div style="flex:2;">
                    <label class="dpt-form-label">Subject Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="subject_name[]" class="dpt-field-input" placeholder="e.g. Higher Math" required>
                </div>
                <div style="flex:1;">
                    <label class="dpt-form-label">Subject Code</label>
                    <input type="text" name="subject_code[]" class="dpt-field-input" placeholder="e.g. MAT-101">
                </div>
                <div style="flex:1;">
                    <label class="dpt-form-label">Type</label>
                    <select name="subject_type[]" class="dpt-field-select">
                        <option value="Mandatory">Mandatory</option>
                        <option value="Optional">Optional</option>
                    </select>
                </div>
                <button type="button" class="btn-remove-row" style="height:42px; width:42px; border-radius:8px; background:#fef2f2; border:1px solid #fecaca; color:#dc2626; cursor:not-allowed; opacity:0.5; display:flex; align-items:center; justify-content:center; flex-shrink:0;" disabled><span class="dashicons dashicons-no"></span></button>
            </div>
        </div>

        <button type="button" id="btn-add-subject" style="background:#eff6ff; color:#2563eb; border:1px dashed #93c5fd; width:100%; height:42px; border-radius:8px; font-weight:700; cursor:pointer; margin-bottom:20px; display:flex; align-items:center; justify-content:center;">
            + Add Another Subject
        </button>

        <button type="submit" name="save_subjects_repeater" class="dpt-btn-action-trigger">
            <span class="dashicons dashicons-saved"></span> Save All Subjects
        </button>
    </form>
</div>

<div class="dpt-bento-box">
    <h5 class="dpt-bento-subheading">Mapped Subjects Data</h5>
    <div class="dpt-responsive-datatable">
        <table class="dpt-architecture-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Class</th>
                    <th style="width: 35%;">Subject Name</th>
                    <th style="width: 15%;">Code</th>
                    <th style="width: 15%;">Type</th>
                    <th style="width: 10%; text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $subjects_list ) ) : foreach ( $subjects_list as $sub ) : ?>
                    <tr>
                        <td style="font-weight: 600; color: #475569;"><?php echo esc_html( $sub->class_name ); ?></td>
                        <td style="font-weight: 700; color: #0f172a;"><?php echo esc_html( $sub->subject_name ); ?></td>
                        <td><?php echo esc_html( $sub->subject_code ? $sub->subject_code : '-' ); ?></td>
                        <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700;"><?php echo esc_html( $sub->subject_type ); ?></span></td>
                        <td style="text-align: right;">
                            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( ['subtab'=>'subjects', 'action'=>'delete_subject', 'id'=>$sub->id], $base_url ), 'delete_subject_action_' . $sub->id ) ); ?>" class="dpt-square-btn dpt-square-btn-delete" onclick="return confirm('Delete this subject?');"><span class="dashicons dashicons-trash"></span></a>
                        </td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="5" style="text-align:center; padding: 20px;">No subjects assigned yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('btn-add-subject').addEventListener('click', function() {
    const canvas = document.getElementById('subject-repeater-canvas');
    const rows = canvas.querySelectorAll('.repeater-row');
    const newRow = rows[0].cloneNode(true);
    
    newRow.querySelectorAll('input').forEach(inp => inp.value = '');
    newRow.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
    
    const rmvBtn = newRow.querySelector('.btn-remove-row');
    rmvBtn.removeAttribute('disabled');
    rmvBtn.style.opacity = '1';
    rmvBtn.style.cursor = 'pointer';
    
    canvas.appendChild(newRow);
});

document.getElementById('subject-repeater-canvas').addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-remove-row');
    if (btn && !btn.hasAttribute('disabled')) {
        btn.closest('.repeater-row').remove();
    }
});
</script>