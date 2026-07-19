<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function educore_academics_tab() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sms_academic_units';

    // ১. ফর্ম সাবমিশন হ্যান্ডেলিং
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['educore_academic_nonce'] ) && wp_verify_nonce( $_POST['educore_academic_nonce'], 'academic_setup_action' ) ) {
        if ( isset( $_POST['add_academic_row'] ) ) {
            $type   = sanitize_text_field( $_POST['unit_type'] ); // 'School' or 'College'
            $class  = sanitize_text_field( trim( $_POST['class_name'] ) );
            $section = sanitize_text_field( trim( $_POST['section_name'] ) );
            $dept   = ( $type === 'College' ) ? sanitize_text_field( trim( $_POST['dept_name'] ) ) : '';

            if ( ! empty( $class ) && ! empty( $section ) ) {
                // ডুপ্লিকেশন চেক
                $is_duplicate = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM $table_name WHERE unit_type = %s AND class_name = %s AND section_name = %s AND dept_name = %s",
                    $type, $class, $section, $dept
                ) );

                if ( ! $is_duplicate ) {
                    $wpdb->insert( $table_name, array(
                        'unit_type'    => $type,
                        'class_name'   => $class,
                        'section_name' => $section,
                        'dept_name'    => $dept
                    ) );
                    echo '<div class="alert alert-success border-0 shadow-sm mb-4">Academic configuration row added successfully.</div>';
                } else {
                    echo '<div class="alert alert-warning border-0 shadow-sm mb-4">This identical configuration row already exists.</div>';
                }
            }
        }
    }

    // ২. ডিলিট অপারেশন হ্যান্ডেলিং
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_unit' && isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_unit_action' ) ) {
        $id_to_delete = intval( $_GET['id'] );
        $wpdb->delete( $table_name, array( 'id' => $id_to_delete ) );
        echo '<div class="alert alert-success border-0 shadow-sm mb-4">Configuration row deleted.</div>';
    }

    // ৩. ডেটা রিট্রিভ
    $academic_rows = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY unit_type DESC, class_name ASC" );
    ?>

    <style>
        .dnt-full-width-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }
        .dnt-field-flex {
            display: flex;
            align-items: flex-end;
            gap: 16px;
            width: 100%;
        }
        .dnt-input-block {
            flex: 1;
            transition: all 0.3s ease;
        }
        .dnt-input-block.dnt-type-block {
            max-width: 180px;
            flex-shrink: 0;
        }
        .dnt-input-block.dnt-btn-block {
            max-width: 160px;
            flex-shrink: 0;
        }
        #dnt-dept-input-container {
            display: none;
            animation: dntFadeIn 0.25s ease forwards;
        }
        @keyframes dntFadeIn {
            from { opacity: 0; transform: translateY(-3px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><span class="dashicons dashicons-welcome-learn-more"></span> Academic Structure Setup</h2>
    </div>

    <!-- ফুল-উইডথ ওয়ান-লাইন গ্রিড ইনপুট বার -->
    <div class="dnt-full-width-bar mb-4">
        <form method="POST" action="">
            <?php wp_nonce_field( 'academic_setup_action', 'educore_academic_nonce' ); ?>
            
            <div class="dnt-field-flex">
                <div class="dnt-input-block dnt-type-block">
                    <label class="form-label fw-bold small text-muted">Structure Type</label>
                    <select name="unit_type" id="dnt-unit-type-select" class="form-select" style="height: 42px;" required>
                        <option value="School">School</option>
                        <option value="College">College</option>
                    </select>
                </div>

                <!-- ডিপার্টমেন্ট ফিল্ড (কলেজ মোডে লাইনে পুশ হবে) -->
                <div class="dnt-input-block" id="dnt-dept-input-container">
                    <label class="form-label fw-bold small text-muted">Department / Group</label>
                    <input type="text" name="dept_name" class="form-control" placeholder="e.g. Science, Arts, Commerce" style="height: 42px;">
                </div>

                <div class="dnt-input-block">
                    <label class="form-label fw-bold small text-muted">Class / Academic Year</label>
                    <input type="text" name="class_name" class="form-control" placeholder="e.g. Class 9, 11th Year" style="height: 42px;" required>
                </div>

                <div class="dnt-input-block">
                    <label class="form-label fw-bold small text-muted">Section</label>
                    <input type="text" name="section_name" class="form-control" placeholder="e.g. A, B, Rose" style="height: 42px;" required>
                </div>

                <div class="dnt-input-block dnt-btn-block">
                    <button type="submit" name="add_academic_row" class="btn btn-success w-100 fw-semibold d-inline-flex align-items-center justify-content-center gap-2" style="background-color: #006a4e; border: none; height: 42px;">
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        Insert Row
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ডাটা ডিসপ্লে ডাটা-টেবিল লেজার -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-body p-0">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 15%;">Type</th>
                        <th style="width: 30%;">Department / Group</th>
                        <th style="width: 25%;">Class / Academic Year</th>
                        <th style="width: 15%;">Section</th>
                        <th style="width: 15%; text-align: right;" class="pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $academic_rows ) ) : foreach ( $academic_rows as $row ) : 
                        $del_url = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=academics&action=delete_unit&id=' . $row->id ), 'delete_unit_action' );
                    ?>
                        <tr>
                            <td class="ps-4">
                                <span class="badge <?php echo $row->unit_type === 'College' ? 'bg-primary' : 'bg-secondary'; ?> px-2.5 py-1.5 shadow-sm">
                                    <?php echo esc_html( $row->unit_type ); ?>
                                </span>
                            </td>
                            <td class="fw-semibold text-dark"><?php echo $row->dept_name ? esc_html( $row->dept_name ) : '<span class="text-muted font-monospace">—</span>'; ?></td>
                            <td class="fw-bold text-dark"><?php echo esc_html( $row->class_name ); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo esc_html( $row->section_name ); ?></span></td>
                            <td style="text-align: right;" class="pe-4">
                                <a href="<?php echo esc_url( $del_url ); ?>" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center justify-content-center" onclick="return confirm('Remove this entire configuration row?');" style="width: 32px; height: 32px; padding: 0; border-radius: 6px;" title="Delete Row">
                                    <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="5" class="text-muted text-center py-4">No structural configuration matrix rows have been added yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ডিপার্টমেন্ট ফিল্ড অন-ফ্লাই টগল করার জন্য জাভাস্ক্রিপ্ট -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const typeSelect = document.getElementById('dnt-unit-type-select');
        const deptContainer = document.getElementById('dnt-dept-input-container');
        const deptInput = deptContainer.querySelector('input');

        function toggleDeptField() {
            if (typeSelect.value === 'College') {
                deptContainer.style.display = 'block';
                deptInput.setAttribute('required', 'required');
            } else {
                deptContainer.style.display = 'none';
                deptInput.removeAttribute('required');
                deptInput.value = ''; 
            }
        }

        typeSelect.addEventListener('change', toggleDeptField);
        toggleDeptField(); 
    });
    </script>
    <?php
}
?>