<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_staff    = $wpdb->prefix . 'sms_staff';
$table_subjects = $wpdb->prefix . 'sms_subjects';
$table_units    = $wpdb->prefix . 'sms_academic_units';
$table_routine  = $wpdb->prefix . 'sms_routine';

// Check or create teacher-subject mapping table
$table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';
$check_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_teacher_subjects}'" );
if ( empty( $check_table ) ) {
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table_teacher_subjects} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        teacher_id bigint(20) NOT NULL,
        subject_id bigint(20) NOT NULL,
        class_id bigint(20) NOT NULL,
        assigned_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        KEY teacher_idx (teacher_id),
        KEY subject_idx (subject_id),
        KEY class_idx (class_id)
    ) {$charset_collate};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

$notice_msg = '';

// Handle Subject Assignment (Add / Edit)
if ( isset( $_POST['assign_teacher_subject'] ) && check_admin_referer( 'assign_teacher_subject_action', 'educore_ts_nonce' ) ) {
    $assignment_id = isset( $_POST['assignment_id'] ) ? absint( $_POST['assignment_id'] ) : 0;
    $teacher_id    = isset( $_POST['teacher_id'] ) ? absint( $_POST['teacher_id'] ) : 0;
    $class_id      = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
    $subject_id    = isset( $_POST['subject_id'] ) ? absint( $_POST['subject_id'] ) : 0;

    if ( $teacher_id > 0 && $class_id > 0 && $subject_id > 0 ) {
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table_teacher_subjects} WHERE teacher_id = %d AND class_id = %d AND subject_id = %d AND id != %d",
            $teacher_id, $class_id, $subject_id, $assignment_id
        ) );

        if ( ! $exists ) {
            if ( $assignment_id > 0 ) {
                $wpdb->update(
                    $table_teacher_subjects,
                    array(
                        'teacher_id' => $teacher_id,
                        'class_id'   => $class_id,
                        'subject_id' => $subject_id,
                    ),
                    array( 'id' => $assignment_id ),
                    array( '%d', '%d', '%d' ),
                    array( '%d' )
                );
                $notice_msg = __( 'Assignment updated successfully.', 'ifsedu-sms' );
            } else {
                $wpdb->insert(
                    $table_teacher_subjects,
                    array(
                        'teacher_id' => $teacher_id,
                        'class_id'   => $class_id,
                        'subject_id' => $subject_id,
                    ),
                    array( '%d', '%d', '%d' )
                );
                $notice_msg = __( 'Subject successfully assigned to teacher.', 'ifsedu-sms' );
            }
        } else {
            $notice_msg = __( 'This subject is already assigned to this teacher for the selected class.', 'ifsedu-sms' );
        }
    }
}

// Handle Delete Assignment
if ( isset( $_GET['action'] ) && 'delete_assignment' === $_GET['action'] && isset( $_GET['assign_id'] ) ) {
    $assign_id = absint( $_GET['assign_id'] );
    check_admin_referer( 'delete_ts_' . $assign_id );
    $wpdb->delete( $table_teacher_subjects, array( 'id' => $assign_id ), array( '%d' ) );
    $notice_msg = __( 'Assignment removed successfully.', 'ifsedu-sms' );
}

// Fetch All Active Teachers
$teachers = $wpdb->get_results( "SELECT id, full_name, designation, phone, staff_type FROM {$table_staff} WHERE status = 'Active' ORDER BY full_name ASC" );

// Fetch ONLY Classes that have at least one subject configured
$classes = $wpdb->get_results( "
    SELECT DISTINCT u.id, u.class_name, u.section_name 
    FROM {$table_units} u
    INNER JOIN {$table_subjects} s ON s.class_id = u.id
    WHERE u.class_name != '' 
    ORDER BY CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC
" );

// Fetch All Subjects
$subjects = $wpdb->get_results( "SELECT s.id, s.subject_name, s.subject_code, s.class_id, u.class_name FROM {$table_subjects} s LEFT JOIN {$table_units} u ON s.class_id = u.id ORDER BY s.subject_name ASC" );

// Fetch Existing Assignments
$assignments = $wpdb->get_results( "
    SELECT ts.id, ts.teacher_id, ts.class_id, ts.subject_id, t.full_name as teacher_name, t.designation, s.subject_name, s.subject_code, u.class_name, u.section_name 
    FROM {$table_teacher_subjects} ts
    INNER JOIN {$table_staff} t ON ts.teacher_id = t.id
    INNER JOIN {$table_subjects} s ON ts.subject_id = s.id
    INNER JOIN {$table_units} u ON ts.class_id = u.id
    ORDER BY t.full_name ASC, u.class_name ASC
" );
?>

<style>
    .dpt-searchable-box { position: relative; }
    .dpt-search-input, .dpt-table-filter { width: 100%; height: 40px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13.5px; background: #f8fafc; box-sizing: border-box; }
    .dpt-search-input:focus, .dpt-table-filter:focus { border-color: #006a4e; background: #fff; outline: none; box-shadow: 0 0 0 3px rgba(0,106,78,0.1); }
    
    /* Edit Modal Backdrop */
    .dpt-modal-backdrop {
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
        background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(4px);
        display: flex; align-items: center; justify-content: center; z-index: 999999;
        opacity: 0; visibility: hidden; transition: all 0.25s ease;
    }
    .dpt-modal-backdrop.is-visible { opacity: 1; visibility: visible; }
    .dpt-modal-card {
        background: #ffffff; width: 100%; max-width: 460px; border-radius: 16px;
        padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;
        transform: translateY(20px); transition: transform 0.25s ease;
    }
    .dpt-modal-backdrop.is-visible .dpt-modal-card { transform: translateY(0); }
</style>

<?php if ( ! empty( $notice_msg ) ) : ?>
    <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:12px 16px; border-radius:8px; font-weight:700; margin-bottom:20px;">
        <?php echo esc_html( $notice_msg ); ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:1.2fr 2fr; gap:24px;">

    <!-- Left Form: Assign Subject to Teacher with Live Search -->
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; box-shadow:0 4px 20px -2px rgba(0,0,0,0.03); height:fit-content;">
        <h3 style="margin:0 0 16px 0; font-size:16px; font-weight:800; color:#0f172a; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
            <span class="dashicons dashicons-plus-alt2" style="color:#006a4e;"></span>
            <?php esc_html_e( 'Assign Subject to Teacher', 'ifsedu-sms' ); ?>
        </h3>

        <form method="POST" action="">
            <?php wp_nonce_field( 'assign_teacher_subject_action', 'educore_ts_nonce' ); ?>

            <!-- Live Searchable Teacher Select -->
            <div style="margin-bottom:16px;" class="dpt-searchable-box">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:6px;">
                    <?php esc_html_e( 'Search & Select Teacher', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span>
                </label>
                <input type="text" id="teacher_search_input" class="dpt-search-input" placeholder="<?php esc_attr_e( 'Type teacher name...', 'ifsedu-sms' ); ?>" autocomplete="off" style="margin-bottom: 6px;">
                <select name="teacher_id" id="teacher_dropdown" required style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; font-size:13.5px; background:#f8fafc;">
                    <option value=""><?php esc_html_e( '-- Choose Teacher --', 'ifsedu-sms' ); ?></option>
                    <?php foreach ( $teachers as $t ) : ?>
                        <option value="<?php echo intval( $t->id ); ?>" data-name="<?php echo esc_attr( strtolower( $t->full_name . ' ' . $t->designation ) ); ?>">
                            <?php echo esc_html( $t->full_name . ' (' . $t->designation . ')' ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Class Select -->
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:6px;">
                    <?php esc_html_e( 'Select Class / Section', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span>
                </label>
                <select name="class_id" id="ts_class_select" required style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; font-size:13.5px; background:#f8fafc;">
                    <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-sms' ); ?></option>
                    <?php if ( ! empty( $classes ) ) : foreach ( $classes as $c ) : 
                        $sec_info = ! empty( $c->section_name ) ? ' (' . $c->section_name . ')' : '';
                    ?>
                        <option value="<?php echo intval( $c->id ); ?>" data-classname="<?php echo esc_attr( $c->class_name ); ?>">
                            <?php echo esc_html( $c->class_name . $sec_info ); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <!-- Subject Select with Search -->
            <div style="margin-bottom:20px;" class="dpt-searchable-box">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:6px;">
                    <?php esc_html_e( 'Search & Select Subject', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span>
                </label>
                <input type="text" id="subject_search_input" class="dpt-search-input" placeholder="<?php esc_attr_e( 'Type subject name...', 'ifsedu-sms' ); ?>" autocomplete="off" style="margin-bottom: 6px;">
                <select name="subject_id" id="ts_subject_select" required style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; font-size:13.5px; background:#f8fafc;">
                    <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-sms' ); ?></option>
                    <?php foreach ( $subjects as $s ) : ?>
                        <option value="<?php echo intval( $s->id ); ?>" data-classid="<?php echo esc_attr( $s->class_id ); ?>" data-name="<?php echo esc_attr( strtolower( $s->subject_name ) ); ?>">
                            <?php echo esc_html( $s->subject_name . ( $s->subject_code ? ' [' . $s->subject_code . ']' : '' ) . ( $s->class_name ? ' — Class: ' . $s->class_name : '' ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" name="assign_teacher_subject" style="width:100%; height:42px; background:#006a4e; color:#ffffff; font-weight:700; font-size:14px; border:none; border-radius:8px; cursor:pointer; box-shadow:0 4px 12px rgba(0,106,78,0.2);">
                <span class="dashicons dashicons-saved" style="vertical-align:middle;"></span>
                <?php esc_html_e( 'Save Assignment', 'ifsedu-sms' ); ?>
            </button>
        </form>
    </div>

    <!-- Right List: Active Teacher-Subject Allocations with Filter -->
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; box-shadow:0 4px 20px -2px rgba(0,0,0,0.03);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #f1f5f9; padding-bottom:12px; flex-wrap:wrap; gap:12px;">
            <h3 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">
                <span class="dashicons dashicons-list-view" style="color:#006a4e;"></span>
                <?php esc_html_e( 'Assigned Teacher & Subject Matrix', 'ifsedu-sms' ); ?>
            </h3>
            <!-- Live Table Filter Input -->
            <input type="text" id="table_filter_input" class="dpt-table-filter" placeholder="<?php esc_attr_e( 'Filter matrix list...', 'ifsedu-sms' ); ?>" style="max-width: 220px; height: 34px;">
        </div>

        <div style="overflow-x:auto;">
            <table id="assignment_matrix_table" style="width:100%; border-collapse:separate; border-spacing:0; font-size:13.5px;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="padding:12px 14px; text-align:left; font-size:11px; font-weight:800; text-transform:uppercase; color:#475569; border-bottom:2px solid #e2e8f0;"><?php esc_html_e( 'Teacher', 'ifsedu-sms' ); ?></th>
                        <th style="padding:12px 14px; text-align:left; font-size:11px; font-weight:800; text-transform:uppercase; color:#475569; border-bottom:2px solid #e2e8f0;"><?php esc_html_e( 'Class / Section', 'ifsedu-sms' ); ?></th>
                        <th style="padding:12px 14px; text-align:left; font-size:11px; font-weight:800; text-transform:uppercase; color:#475569; border-bottom:2px solid #e2e8f0;"><?php esc_html_e( 'Subject', 'ifsedu-sms' ); ?></th>
                        <th style="padding:12px 14px; text-align:right; font-size:11px; font-weight:800; text-transform:uppercase; color:#475569; border-bottom:2px solid #e2e8f0;"><?php esc_html_e( 'Actions', 'ifsedu-sms' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $assignments ) ) : foreach ( $assignments as $row ) : 
                        $del_url = wp_nonce_url( add_query_arg( array( 'subtab' => 'teacher_subjects', 'action' => 'delete_assignment', 'assign_id' => $row->id ), $base_url ), 'delete_ts_' . $row->id );
                    ?>
                        <tr class="matrix-row" data-searchable="<?php echo esc_attr( strtolower( $row->teacher_name . ' ' . $row->class_name . ' ' . $row->subject_name ) ); ?>">
                            <td style="padding:12px 14px; border-bottom:1px solid #f1f5f9;">
                                <strong style="color:#0f172a;"><?php echo esc_html( $row->teacher_name ); ?></strong>
                                <small style="display:block; color:#64748b; font-size:11px;"><?php echo esc_html( $row->designation ); ?></small>
                            </td>
                            <td style="padding:12px 14px; border-bottom:1px solid #f1f5f9;">
                                <span style="background:#eff6ff; color:#2563eb; padding:3px 10px; border-radius:12px; font-weight:700; font-size:12px;">
                                    <?php echo esc_html( $row->class_name . ( $row->section_name ? ' (' . $row->section_name . ')' : '' ) ); ?>
                                </span>
                            </td>
                            <td style="padding:12px 14px; border-bottom:1px solid #f1f5f9;">
                                <strong style="color:#006a4e;"><?php echo esc_html( $row->subject_name ); ?></strong>
                                <?php if ( $row->subject_code ) : ?>
                                    <code style="font-size:11px; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?php echo esc_html( $row->subject_code ); ?></code>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px 14px; border-bottom:1px solid #f1f5f9; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <!-- Edit Trigger Button -->
                                    <button type="button" 
                                            class="btn-trigger-edit" 
                                            data-id="<?php echo esc_attr( $row->id ); ?>"
                                            data-teacher="<?php echo esc_attr( $row->teacher_id ); ?>"
                                            data-class="<?php echo esc_attr( $row->class_id ); ?>"
                                            data-subject="<?php echo esc_attr( $row->subject_id ); ?>"
                                            style="color:#2563eb; border:1px solid #bfdbfe; border-radius:6px; background:#eff6ff; padding:4px 8px; cursor:pointer;" 
                                            title="<?php esc_attr_e( 'Edit Assignment', 'ifsedu-sms' ); ?>">
                                        <span class="dashicons dashicons-edit" style="font-size:15px; width:15px; height:15px; vertical-align:middle;"></span>
                                    </button>

                                    <!-- Delete Button -->
                                    <a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove this assignment?', 'ifsedu-sms' ) ); ?>');" style="color:#dc2626; text-decoration:none; padding:4px 8px; border:1px solid #fecaca; border-radius:6px; background:#fef2f2;" title="<?php esc_attr_e( 'Delete Assignment', 'ifsedu-sms' ); ?>">
                                        <span class="dashicons dashicons-trash" style="font-size:15px; width:15px; height:15px; vertical-align:middle;"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr>
                            <td colspan="4" style="padding:24px; text-align:center; color:#94a3b8;">
                                <?php esc_html_e( 'No subjects assigned to any teacher yet.', 'ifsedu-sms' ); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Edit Modal Backdrop -->
<div class="dpt-modal-backdrop" id="editAssignmentModal">
    <div class="dpt-modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:16px;">
            <h4 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;"><?php esc_html_e( 'Edit Teacher Subject Assignment', 'ifsedu-sms' ); ?></h4>
            <button type="button" id="closeModalBtn" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748b;">&times;</button>
        </div>

        <form method="POST" action="">
            <?php wp_nonce_field( 'assign_teacher_subject_action', 'educore_ts_nonce' ); ?>
            <input type="hidden" name="assignment_id" id="modal_assignment_id" value="">

            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:4px;"><?php esc_html_e( 'Teacher', 'ifsedu-sms' ); ?></label>
                <select name="teacher_id" id="modal_teacher_id" required style="width:100%; height:38px; border:1px solid #cbd5e1; border-radius:8px; padding:0 10px; background:#f8fafc;">
                    <?php foreach ( $teachers as $t ) : ?>
                        <option value="<?php echo intval( $t->id ); ?>"><?php echo esc_html( $t->full_name . ' (' . $t->designation . ')' ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:4px;"><?php esc_html_e( 'Class / Section', 'ifsedu-sms' ); ?></label>
                <select name="class_id" id="modal_class_id" required style="width:100%; height:38px; border:1px solid #cbd5e1; border-radius:8px; padding:0 10px; background:#f8fafc;">
                    <?php foreach ( $classes as $c ) : 
                        $sec_info = ! empty( $c->section_name ) ? ' (' . $c->section_name . ')' : '';
                    ?>
                        <option value="<?php echo intval( $c->id ); ?>"><?php echo esc_html( $c->class_name . $sec_info ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:4px;"><?php esc_html_e( 'Subject', 'ifsedu-sms' ); ?></label>
                <select name="subject_id" id="modal_subject_id" required style="width:100%; height:38px; border:1px solid #cbd5e1; border-radius:8px; padding:0 10px; background:#f8fafc;">
                    <?php foreach ( $subjects as $s ) : ?>
                        <option value="<?php echo intval( $s->id ); ?>"><?php echo esc_html( $s->subject_name . ( $s->subject_code ? ' [' . $s->subject_code . ']' : '' ) ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" id="cancelModalBtn" style="padding:8px 16px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Cancel', 'ifsedu-sms' ); ?></button>
                <button type="submit" name="assign_teacher_subject" style="padding:8px 20px; background:#006a4e; color:#fff; border:none; border-radius:6px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Update Assignment', 'ifsedu-sms' ); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Script Engine: Live Search, Table Filter & Modal Logic -->
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // 1. Teacher Live Search
    const teacherSearch = document.getElementById('teacher_search_input');
    const teacherDropdown = document.getElementById('teacher_dropdown');
    if (teacherSearch && teacherDropdown) {
        const teacherOptions = Array.from(teacherDropdown.options).slice(1);
        teacherSearch.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            teacherDropdown.innerHTML = '<option value=""><?php esc_html_e( '-- Choose Teacher --', 'ifsedu-sms' ); ?></option>';
            teacherOptions.forEach(opt => {
                const name = opt.getAttribute('data-name') || '';
                if (!query || name.includes(query)) {
                    teacherDropdown.appendChild(opt.cloneNode(true));
                }
            });
        });
    }

    // 2. Subject Live Search & Class Chaining
    const classSelect = document.getElementById('ts_class_select');
    const subjectSearch = document.getElementById('subject_search_input');
    const subjectDropdown = document.getElementById('ts_subject_select');

    if (classSelect && subjectDropdown && subjectSearch) {
        const allSubjectOptions = Array.from(subjectDropdown.options).slice(1);

        function filterSubjects() {
            const selectedClassId = classSelect.value;
            const query = subjectSearch.value.toLowerCase().trim();

            subjectDropdown.innerHTML = '<option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-sms' ); ?></option>';

            allSubjectOptions.forEach(opt => {
                const classIdMatch = !selectedClassId || opt.getAttribute('data-classid') === selectedClassId;
                const nameMatch = !query || (opt.getAttribute('data-name') || '').includes(query);

                if (classIdMatch && nameMatch) {
                    subjectDropdown.appendChild(opt.cloneNode(true));
                }
            });
        }

        classSelect.addEventListener('change', filterSubjects);
        subjectSearch.addEventListener('input', filterSubjects);
    }

    // 3. Matrix Table Live Filter
    const tableFilter = document.getElementById('table_filter_input');
    const matrixRows = document.querySelectorAll('.matrix-row');

    if (tableFilter) {
        tableFilter.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            matrixRows.forEach(row => {
                const searchText = row.getAttribute('data-searchable') || '';
                if (!query || searchText.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // 4. Edit Modal Control
    const modal = document.getElementById('editAssignmentModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');

    function closeModal() {
        if (modal) modal.classList.remove('is-visible');
    }

    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);

    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.btn-trigger-edit');
        if (editBtn && modal) {
            document.getElementById('modal_assignment_id').value = editBtn.getAttribute('data-id');
            document.getElementById('modal_teacher_id').value = editBtn.getAttribute('data-teacher');
            document.getElementById('modal_class_id').value = editBtn.getAttribute('data-class');
            document.getElementById('modal_subject_id').value = editBtn.getAttribute('data-subject');
            modal.classList.add('is-visible');
        }
    });
});
</script>