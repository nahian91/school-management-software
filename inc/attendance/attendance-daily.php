<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Daily Student Attendance Entry Workspace
 * File: inc/attendance/attendance-daily.php
 */
function educore_daily_attendance_view( $classes, $sections, $filter_class, $filter_section, $filter_date ) {
    global $wpdb;

    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $table_units      = $wpdb->prefix . 'sms_academic_units';

    // Fetch academic units for dynamic section dropdown
    $all_units = $wpdb->get_results( "SELECT id, class_name, section_name FROM {$table_units} WHERE section_name != '' ORDER BY section_name ASC" );

    // Fetch all active students for the dynamic student dropdown
    $all_active_students = $wpdb->get_results( "SELECT id, class_name, section_name, full_name, roll_no FROM {$table_students} WHERE status = 'Active' ORDER BY CAST(roll_no AS UNSIGNED) ASC" );

    // Additional Filter for specific student
    $filter_student = isset( $_GET['filter_student'] ) ? intval( $_GET['filter_student'] ) : 0;

    // Handle Attendance Form Commit
    if ( isset( $_POST['educore_save_attendance'] ) && isset( $_POST['educore_attendance_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_attendance_nonce'] ) ), 'save_attendance_action' ) ) {
        $attendance_date = isset( $_POST['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_POST['attendance_date'] ) ) : current_time( 'Y-m-d' );
        $attendance_data = isset( $_POST['attendance'] ) ? (array) $_POST['attendance'] : array();
        $current_user_id = get_current_user_id();

        $saved_count = 0;

        if ( ! empty( $attendance_data ) ) {
            $target_student_ids = array_map( 'intval', array_keys( $attendance_data ) );
            $ids_placeholder    = implode( ',', array_fill( 0, count( $target_student_ids ), '%d' ) );

            $prep_query = $wpdb->prepare(
                "SELECT student_id, id FROM {$table_attendance} WHERE attendance_date = %s AND student_id IN ($ids_placeholder)",
                array_merge( array( $attendance_date ), $target_student_ids )
            );

            $existing_records = $wpdb->get_results( $prep_query, OBJECT_K );

            foreach ( $attendance_data as $student_id => $status ) {
                $student_id = intval( $student_id );
                $status     = sanitize_text_field( wp_unslash( $status ) );

                if ( isset( $existing_records[ $student_id ] ) ) {
                    $wpdb->update(
                        $table_attendance,
                        array(
                            'status'      => $status,
                            'recorded_by' => $current_user_id
                        ),
                        array( 'id' => intval( $existing_records[ $student_id ]->id ) ),
                        array( '%s', '%d' ),
                        array( '%d' )
                    );
                } else {
                    $wpdb->insert(
                        $table_attendance,
                        array(
                            'student_id'      => $student_id,
                            'attendance_date' => $attendance_date,
                            'status'          => $status,
                            'remarks'         => '',
                            'recorded_by'     => $current_user_id
                        ),
                        array( '%d', '%s', '%s', '%s', '%d' )
                    );
                }
                $saved_count++;
            }
        }

        echo '<div class="afdp-success-banner" style="background:#ecfdf5; border:1px solid #a7f3d0; color:#047857; padding:12px; border-radius:8px; margin-bottom:20px;"><span class="dashicons dashicons-yes-alt"></span> ' . sprintf( esc_html__( 'Attendance records successfully updated for %d students.', 'ifsedu-sms' ), intval( $saved_count ) ) . '</div>';
    }
    ?>

    <!-- Daily Filter Controls Bento Card -->
    <div class="dpt-bento-card no-print" style="background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:12px; margin-bottom:24px;">
        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            <input type="hidden" name="sub" value="daily">
            
            <div class="dpt-form-group" style="flex:1; min-width:180px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Target Date', 'ifsedu-sms' ); ?> *</label>
                <!-- Added max="current_time('Y-m-d')" to block future dates -->
                <input type="date" name="attendance_date" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" value="<?php echo esc_attr( $filter_date ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
            </div>
            
            <div class="dpt-form-group" style="flex:1; min-width:180px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Academic Class', 'ifsedu-sms' ); ?> *</label>
                <select name="class_name" id="educore_attendance_class_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" required>
                    <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-sms' ); ?></option>
                    <?php foreach ( $classes as $cls ) : ?>
                        <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="dpt-form-group" style="flex:1; min-width:180px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Section', 'ifsedu-sms' ); ?></label>
                <select name="section_name" id="educore_attendance_section_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;">
                    <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                    <!-- Options populated via JS -->
                </select>
            </div>

            <div class="dpt-form-group" style="flex:1; min-width:220px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Student (Optional)', 'ifsedu-sms' ); ?></label>
                <select name="filter_student" id="educore_attendance_student_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;">
                    <option value=""><?php esc_html_e( '-- All Students --', 'ifsedu-sms' ); ?></option>
                    <!-- Options populated via JS -->
                </select>
            </div>
            
            <div class="dpt-form-group">
                <button type="submit" style="height:40px; padding:0 24px; background:#006a4e; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Load Students', 'ifsedu-sms' ); ?></button>
            </div>
        </form>
    </div>

    <?php
    if ( ! empty( $filter_class ) ) {
        $query = "SELECT id, student_id, full_name, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $sql_args = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $query .= " AND section_name = %s";
            $sql_args[] = $filter_section;
        }

        // Apply student filter if selected
        if ( $filter_student > 0 ) {
            $query .= " AND id = %d";
            $sql_args[] = $filter_student;
        }

        $query .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $students = $wpdb->get_results( $wpdb->prepare( $query, ...$sql_args ) );

        if ( $students ) {
            $student_ids  = wp_list_pluck( $students, 'id' );
            $placeholders = implode( ',', array_fill( 0, count( $student_ids ), '%d' ) );
            
            $cached_attendance_query = $wpdb->prepare(
                "SELECT student_id, status FROM {$table_attendance} WHERE attendance_date = %s AND student_id IN ($placeholders)",
                array_merge( array( $filter_date ), $student_ids )
            );
            $loaded_attendance_states = $wpdb->get_results( $cached_attendance_query, OBJECT_K );
            ?>
            <div class="dpt-bento-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
                
                <div class="afdp-roster-meta-bar" style="padding:20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                    <div class="afdp-roster-title">
                        <h4 style="margin:0; font-weight:800; font-size:18px;"><?php esc_html_e( 'Mark Attendance:', 'ifsedu-sms' ); ?> <span style="color: #006a4e;"><?php echo esc_html( $filter_class . ( $filter_section ? ' (' . $filter_section . ')' : '' ) ); ?></span></h4>
                        <small style="color:#64748b; font-weight:600; font-size:13px;"><?php esc_html_e( 'Target Date:', 'ifsedu-sms' ); ?> <?php echo esc_html( date_i18n( 'd F, Y', strtotime( $filter_date ) ) ); ?></small>
                    </div>
                    
                    <div class="dpt-counter-cluster" style="display:flex; gap:10px;">
                        <span style="background:#e2e8f0; color:#475569; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Total:', 'ifsedu-sms' ); ?> <span id="cnt-total"><?php echo count( $students ); ?></span></span>
                        <span style="background:#ecfdf5; color:#059669; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Present:', 'ifsedu-sms' ); ?> <span id="cnt-present">0</span></span>
                        <span style="background:#fef2f2; color:#dc2626; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Absent:', 'ifsedu-sms' ); ?> <span id="cnt-absent">0</span></span>
                        <span style="background:#fff7ed; color:#ea580c; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Late:', 'ifsedu-sms' ); ?> <span id="cnt-late">0</span></span>
                    </div>
                </div>

                <div class="afdp-bulk-automation-row no-print" style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; gap:16px; align-items:center;">
                    <div style="font-size:13px; font-weight:700; color:#475569; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Bulk Operations:', 'ifsedu-sms' ); ?>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="dpt-bulk-btn" data-target-status="Present" style="cursor:pointer; background:#fff; border:1px solid #a7f3d0; color:#059669; font-weight:600; padding:6px 12px; border-radius:6px; font-size:12px; transition:0.2s;"><?php esc_html_e( 'Set All Present', 'ifsedu-sms' ); ?></button>
                        <button type="button" class="dpt-bulk-btn" data-target-status="Absent" style="cursor:pointer; background:#fff; border:1px solid #fecaca; color:#dc2626; font-weight:600; padding:6px 12px; border-radius:6px; font-size:12px; transition:0.2s;"><?php esc_html_e( 'Set All Absent', 'ifsedu-sms' ); ?></button>
                        <button type="button" class="dpt-bulk-btn" data-target-status="Late" style="cursor:pointer; background:#fff; border:1px solid #fed7aa; color:#ea580c; font-weight:600; padding:6px 12px; border-radius:6px; font-size:12px; transition:0.2s;"><?php esc_html_e( 'Set All Late', 'ifsedu-sms' ); ?></button>
                    </div>
                </div>
                
                <form method="POST" action="" id="educoreAttendanceSubmitEngine">
                    <?php wp_nonce_field( 'save_attendance_action', 'educore_attendance_nonce' ); ?>
                    <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
                            <thead>
                                <tr style="background:#f8fafc;">
                                    <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:12%;"><?php esc_html_e( 'Roll No', 'ifsedu-sms' ); ?></th>
                                    <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:18%;"><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                                    <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%;"><?php esc_html_e( 'Student Name', 'ifsedu-sms' ); ?></th>
                                    <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%; text-align:center;"><?php esc_html_e( 'Attendance Status', 'ifsedu-sms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ( $students as $student ) : 
                                    $student_internal_id = intval( $student->id );
                                    $current_status = isset( $loaded_attendance_states[ $student_internal_id ] ) ? $loaded_attendance_states[ $student_internal_id ]->status : 'Present';
                                ?>
                                <tr class="student-attendance-row" style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:12px 20px;"><strong># <?php echo esc_html( $student->roll_no ); ?></strong></td>
                                    <td style="padding:12px 20px;"><code style="color:#0f172a; font-weight:700; background:#f1f5f9; padding:4px 8px; border-radius:6px; border:1px solid #e2e8f0;"><?php echo esc_html( $student->student_id ); ?></code></td>
                                    <td style="padding:12px 20px;"><span style="font-weight:700; color:#0f172a;"><?php echo esc_html( $student->full_name ); ?></span></td>
                                    <td style="padding:12px 20px; text-align:center;">
                                        <div style="display:inline-flex; gap:12px; background:#f8fafc; padding:6px 8px; border-radius:8px; border:1px solid #e2e8f0;">
                                            
                                            <label style="display:flex; align-items:center; gap:4px; font-weight:600; font-size:12px; cursor:pointer; color:#059669;">
                                                <input type="radio" class="status-radio-node" name="attendance[<?php echo $student_internal_id; ?>]" value="Present" <?php checked( $current_status, 'Present' ); ?>>
                                                <?php esc_html_e( 'Present', 'ifsedu-sms' ); ?>
                                            </label>

                                            <label style="display:flex; align-items:center; gap:4px; font-weight:600; font-size:12px; cursor:pointer; color:#dc2626;">
                                                <input type="radio" class="status-radio-node" name="attendance[<?php echo $student_internal_id; ?>]" value="Absent" <?php checked( $current_status, 'Absent' ); ?>>
                                                <?php esc_html_e( 'Absent', 'ifsedu-sms' ); ?>
                                            </label>

                                            <label style="display:flex; align-items:center; gap:4px; font-weight:600; font-size:12px; cursor:pointer; color:#ea580c;">
                                                <input type="radio" class="status-radio-node" name="attendance[<?php echo $student_internal_id; ?>]" value="Late" <?php checked( $current_status, 'Late' ); ?>>
                                                <?php esc_html_e( 'Late', 'ifsedu-sms' ); ?>
                                            </label>

                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="padding:20px; background:#f8fafc; text-align:right; border-top:1px solid #e2e8f0;">
                        <button type="submit" name="educore_save_attendance" style="padding:0 32px; height:44px; font-size:14px; font-weight:700; background:#006a4e; color:#fff; border:none; border-radius:8px; cursor:pointer; box-shadow:0 4px 12px rgba(0, 106, 78, 0.2);">
                            <span class="dashicons dashicons-saved" style="margin-top:5px;"></span> <?php esc_html_e( 'Save Attendance Data', 'ifsedu-sms' ); ?>
                        </button>
                    </div>
                </form>
            </div>
            <?php
        } else {
            echo '<div style="background:#fffbeb; border:1px solid #fed7aa; color:#9a3412; padding:20px; border-radius:12px; text-align:center; font-weight:600;"><span class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px; margin-bottom:10px; display:block; margin:0 auto;"></span><p style="margin:0;">' . esc_html__( 'No active students found matching current filters.', 'ifsedu-sms' ) . '</p></div>';
        }
    } else {
        echo '<div style="background:#f0f9ff; border:1px solid #bae6fd; color:#0369a1; padding:20px; border-radius:12px; text-align:center; font-weight:600;"><span class="dashicons dashicons-info" style="font-size:24px; width:24px; height:24px; margin-bottom:10px; display:block; margin:0 auto;"></span><p style="margin:0;">' . esc_html__( 'Select a Target Date and Academic Class above to load the attendance workspace.', 'ifsedu-sms' ) . '</p></div>';
    }
    ?>
    
    <!-- Dynamic JS Engine: Safe Class->Section->Student Chaining & Bulk Logic -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        
        // 1. Safe JSON Injection to prevent JS crashing if arrays are empty
        const rawUnits = <?php echo wp_json_encode( !empty($all_units) ? $all_units : array() ); ?>;
        const rawStudents = <?php echo wp_json_encode( !empty($all_active_students) ? $all_active_students : array() ); ?>;
        
        const unitsMap = Array.isArray(rawUnits) ? rawUnits : [];
        const studentsMap = Array.isArray(rawStudents) ? rawStudents : [];
        
        const currentFilterSection = "<?php echo esc_js( $filter_section ); ?>";
        const currentFilterStudent = "<?php echo esc_js( $filter_student ); ?>";
        
        const classSelect = document.getElementById('educore_attendance_class_select');
        const sectionSelect = document.getElementById('educore_attendance_section_select');
        const studentSelect = document.getElementById('educore_attendance_student_select');

        // Populate Sections based on Class
        function populateSections(selectedClass, selectedSecName = '') {
            if (!sectionSelect) return;
            sectionSelect.innerHTML = '<option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>';
            if (!selectedClass) return;

            const filtered = unitsMap.filter(item => item.class_name == selectedClass);
            const uniqueSections = [...new Set(filtered.map(item => item.section_name).filter(Boolean))];

            uniqueSections.forEach(secName => {
                const opt = document.createElement('option');
                opt.value = secName;
                opt.textContent = secName;
                if (secName == selectedSecName) {
                    opt.selected = true;
                }
                sectionSelect.appendChild(opt);
            });
        }

        // Populate Students based on Class and Section
        function populateStudents(selectedClass, selectedSecName, selectedStudentId = '') {
            if (!studentSelect) return;
            studentSelect.innerHTML = '<option value=""><?php esc_html_e( '-- All Students --', 'ifsedu-sms' ); ?></option>';
            if (!selectedClass) return;

            let filteredStudents = studentsMap.filter(item => item.class_name == selectedClass);
            
            if (selectedSecName) {
                filteredStudents = filteredStudents.filter(item => item.section_name == selectedSecName);
            }

            filteredStudents.forEach(stu => {
                const opt = document.createElement('option');
                opt.value = stu.id;
                // Format: [Roll: 1] John Doe
                opt.textContent = stu.roll_no ? `[Roll: ${stu.roll_no}] ${stu.full_name}` : stu.full_name;
                
                if (String(stu.id) === String(selectedStudentId)) {
                    opt.selected = true;
                }
                studentSelect.appendChild(opt);
            });
        }

        if (classSelect && sectionSelect && studentSelect) {
            // Initial load (Preserve selections if page reloaded)
            populateSections(classSelect.value, currentFilterSection);
            populateStudents(classSelect.value, currentFilterSection, currentFilterStudent);

            // On change Class -> Update Section and Student dropdowns
            classSelect.addEventListener('change', function() {
                populateSections(classSelect.value);
                populateStudents(classSelect.value, sectionSelect.value);
            });

            // On change Section -> Update Student dropdown to only show students in that section
            sectionSelect.addEventListener('change', function() {
                populateStudents(classSelect.value, sectionSelect.value);
            });
        }
        
        // 2. Bulk Action & Live Counter Engine
        function updateLiveCounters() {
            const total = document.querySelectorAll('.student-attendance-row').length;
            const present = document.querySelectorAll('.status-radio-node[value="Present"]:checked').length;
            const absent = document.querySelectorAll('.status-radio-node[value="Absent"]:checked').length;
            const late = document.querySelectorAll('.status-radio-node[value="Late"]:checked').length;
            
            const elTotal = document.getElementById('cnt-total');
            const elPresent = document.getElementById('cnt-present');
            const elAbsent = document.getElementById('cnt-absent');
            const elLate = document.getElementById('cnt-late');
            
            if (elTotal) elTotal.textContent = total;
            if (elPresent) elPresent.textContent = present;
            if (elAbsent) elAbsent.textContent = absent;
            if (elLate) elLate.textContent = late;
        }

        // Attach listeners to all individual radio buttons to update counters when changed manually
        const allRadios = document.querySelectorAll('.status-radio-node');
        allRadios.forEach(radio => {
            radio.addEventListener('change', updateLiveCounters);
        });
        
        // Attach listeners to Bulk action buttons
        const bulkBtns = document.querySelectorAll('.dpt-bulk-btn');
        bulkBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const targetStatus = this.getAttribute('data-target-status');
                const matchingRadios = document.querySelectorAll('.status-radio-node[value="' + targetStatus + '"]');
                
                matchingRadios.forEach(radio => {
                    radio.checked = true;
                });
                
                updateLiveCounters();
            });
        });

        // Initialize counters on page load
        updateLiveCounters();
        
    });
    </script>

<?php
}