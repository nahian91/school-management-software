<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

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
        .educore-academic-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
        }
        .educore-field-grid {
            display: flex;
            align-items: flex-end;
            gap: 16px;
            width: 100%;
        }
        .educore-input-block { flex: 1; }
        .educore-type-block { max-width: 180px; flex-shrink: 0; }
        .educore-btn-block { max-width: 220px; flex-shrink: 0; }
        .educore-badge-school { background-color: #e0f2fe; color: #0369a1; font-weight: 600; }
        .educore-badge-college { background-color: #f0fdf4; color: #15803d; font-weight: 600; }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-slate-800 mb-1">
                <span class="dashicons dashicons-welcome-learn-more text-success align-middle me-1 fs-3"></span> Academic Architecture Setup
            </h2>
            <p class="text-muted mb-0 small">Manage School (Class/Section) and College (Department/Year) structural units.</p>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ( ! empty( $message_text ) ) : ?>
        <div class="alert alert-<?php echo esc_attr( $message_type ); ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <strong><?php echo $message_type === 'success' ? 'Success:' : 'Notice:'; ?></strong> <?php echo esc_html( $message_text ); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Dynamic Form Bar -->
    <div class="educore-academic-card mb-4">
        <h5 class="fw-bold text-slate-700 mb-3 border-bottom pb-2">
            <?php echo $is_edit ? 'Edit Academic Configuration' : 'Add Academic Configuration'; ?>
        </h5>
        <form method="POST" action="">
            <?php wp_nonce_field( 'academic_setup_action', 'educore_academic_nonce' ); ?>
            <input type="hidden" name="row_id" value="<?php echo $is_edit ? intval( $edit_row->id ) : 0; ?>">

            <div class="educore-field-grid">
                <!-- Structure Type Selector -->
                <div class="educore-input-block educore-type-block">
                    <label class="form-label fw-bold small text-secondary">Tier / Type</label>
                    <select name="unit_type" id="educore-unit-type-select" class="form-select shadow-none" style="height: 42px;" required>
                        <option value="School" <?php selected( $is_edit ? $edit_row->unit_type : '', 'School' ); ?>>School Tier</option>
                        <option value="College" <?php selected( $is_edit ? $edit_row->unit_type : '', 'College' ); ?>>College Tier</option>
                    </select>
                </div>

                <!-- SCHOOL FIELDS: Class & Section -->
                <div class="educore-input-block school-field">
                    <label class="form-label fw-bold small text-secondary">Class Name <span class="text-danger">*</span></label>
                    <input type="text" name="class_name" id="school-class-input" class="form-control shadow-none" placeholder="e.g. Class 9, Class 10" style="height: 42px;" value="<?php echo ( $is_edit && $edit_row->unit_type === 'School' ) ? esc_attr( $edit_row->class_name ) : ''; ?>">
                </div>

                <div class="educore-input-block school-field">
                    <label class="form-label fw-bold small text-secondary">Section Name <span class="text-danger">*</span></label>
                    <input type="text" name="section_name" id="school-section-input" class="form-control shadow-none" placeholder="e.g. Section A, Padma" style="height: 42px;" value="<?php echo ( $is_edit && $edit_row->unit_type === 'School' ) ? esc_attr( $edit_row->section_name ) : ''; ?>">
                </div>

                <!-- COLLEGE FIELDS: Department & Year -->
                <div class="educore-input-block college-field" style="display: none;">
                    <label class="form-label fw-bold small text-secondary">Department / Group <span class="text-danger">*</span></label>
                    <input type="text" name="dept_name" id="college-dept-input" class="form-control shadow-none" placeholder="e.g. Science, Arts, BBA" style="height: 42px;" value="<?php echo ( $is_edit && $edit_row->unit_type === 'College' ) ? esc_attr( $edit_row->dept_name ) : ''; ?>">
                </div>

                <div class="educore-input-block college-field" style="display: none;">
                    <label class="form-label fw-bold small text-secondary">Academic Year <span class="text-danger">*</span></label>
                    <input type="text" name="academic_year" id="college-year-input" class="form-control shadow-none" placeholder="e.g. 11th Year, 1st Year" style="height: 42px;" value="<?php echo ( $is_edit && $edit_row->unit_type === 'College' ) ? esc_attr( $edit_row->class_name ) : ''; ?>">
                </div>

                <!-- Submit & Cancel Buttons -->
                <div class="educore-input-block educore-btn-block d-flex gap-2">
                    <button type="submit" name="save_academic_row" class="btn btn-success flex-grow-1 fw-bold d-inline-flex align-items-center justify-content-center gap-1" style="background-color: #006a4e; border: none; height: 42px;">
                        <span class="dashicons <?php echo $is_edit ? 'dashicons-edit' : 'dashicons-plus-alt2'; ?>"></span>
                        <?php echo $is_edit ? 'Update Row' : 'Insert Row'; ?>
                    </button>

                    <?php if ( $is_edit ) : ?>
                        <a href="<?php echo esc_url( $cancel_url ); ?>" class="btn btn-outline-secondary fw-bold d-inline-flex align-items-center justify-content-center" style="height: 42px;" title="Cancel Editing">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Data Display Table -->
    <div class="border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-slate-800">Configured Academic Units</h6>
            <span class="badge bg-secondary"><?php echo count( $academic_rows ); ?> Records</span>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 15%;">Tier Type</th>
                        <th style="width: 35%;">Primary Classification (Class / Dept)</th>
                        <th style="width: 35%;">Secondary Parameter (Section / Year)</th>
                        <th style="width: 15%; text-align: right;" class="pe-4">Actions</th>
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
                    ?>
                        <tr class="<?php echo ($is_edit && $edit_id === intval($row->id)) ? 'table-warning' : ''; ?>">
                            <td class="ps-4">
                                <span class="badge <?php echo $is_college ? 'educore-badge-college' : 'educore-badge-school'; ?> px-3 py-2 border">
                                    <?php echo esc_html( $row->unit_type ); ?>
                                </span>
                            </td>
                            <td class="fw-bold text-dark">
                                <?php if ( $is_college ) : ?>
                                    <span class="text-primary"><span class="dashicons dashicons-category align-middle me-1"></span><?php echo esc_html( $row->dept_name ); ?></span>
                                <?php else : ?>
                                    <span>Class: <?php echo esc_html( $row->class_name ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold text-secondary">
                                <?php if ( $is_college ) : ?>
                                    <span class="badge bg-light text-dark border">Year: <?php echo esc_html( $row->class_name ); ?></span>
                                <?php else : ?>
                                    <span class="badge bg-light text-dark border">Sec: <?php echo esc_html( $row->section_name ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;" class="pe-4">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?php echo esc_url( $edit_url ); ?>" 
                                       class="btn btn-sm btn-outline-primary d-inline-flex align-items-center justify-content-center p-0 rounded-2" 
                                       style="width: 34px; height: 34px;" 
                                       title="Edit Unit">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <a href="<?php echo esc_url( $del_url ); ?>" 
                                       class="btn btn-sm btn-outline-danger d-inline-flex align-items-center justify-content-center p-0 rounded-2" 
                                       style="width: 34px; height: 34px;" 
                                       title="Delete Unit" 
                                       onclick="return confirm('Delete this structural configuration row?');">
                                        <span class="dashicons dashicons-trash"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr>
                            <td colspan="4" class="text-muted text-center py-5">
                                <span class="dashicons dashicons-category fs-2 d-block mb-2 text-muted"></span>
                                No academic units configured yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- JavaScript Field Switcher -->
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