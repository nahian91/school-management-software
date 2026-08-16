<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Monthly Attendance Summary Audit & Reports
 * File: inc/attendance/attendance-monthly.php
 * Teacher Scope: Restricts Class/Section/Student dropdowns to `sms_teacher_subjects` for logged-in Teachers.
 */
function educore_monthly_attendance_summary_view( $classes, $sections, $filter_class, $filter_section ) {
    global $wpdb;

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' );

    $table_students         = $wpdb->prefix . 'sms_students';
    $table_attendance       = $wpdb->prefix . 'sms_attendance';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_staff            = $wpdb->prefix . 'sms_staff';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';

    // 1. Resolve Exact Assigned Classes & Sections for Non-Admin Teachers from sms_teacher_subjects
    $teacher_assigned_classes  = array();
    $teacher_assigned_sections = array();
    $assigned_unit_ids         = array();

    if ( ! $is_admin ) {
        $teacher_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table_staff} WHERE email = %s OR full_name = %s LIMIT 1",
            $current_user->user_email,
            $current_user->display_name
        ) );

        if ( $teacher_id ) {
            $allocations = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT u.id AS unit_id, u.class_name, u.section_name 
                 FROM {$table_teacher_subjects} ts
                 INNER JOIN {$table_units} u ON ts.class_id = u.id
                 WHERE ts.teacher_id = %d AND u.class_name != ''",
                $teacher_id
            ) );

            foreach ( $allocations as $al ) {
                $assigned_unit_ids[] = intval( $al->unit_id );
                if ( ! in_array( $al->class_name, $teacher_assigned_classes, true ) ) {
                    $teacher_assigned_classes[] = $al->class_name;
                }
                if ( ! empty( $al->section_name ) && ! in_array( $al->section_name, $teacher_assigned_sections, true ) ) {
                    $teacher_assigned_sections[] = $al->section_name;
                }
            }
        }
        $classes = $teacher_assigned_classes;
    }

    // 2. Fetch academic units scoped to teacher's assignments or global
    if ( ! $is_admin && ! empty( $assigned_unit_ids ) ) {
        $unit_placeholders = implode( ',', array_fill( 0, count( $assigned_unit_ids ), '%d' ) );
        $all_units = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, class_name, section_name FROM {$table_units} WHERE id IN ($unit_placeholders) AND section_name != '' ORDER BY section_name ASC",
            ...$assigned_unit_ids
        ) );
    } else {
        $all_units = $wpdb->get_results( "SELECT id, class_name, section_name FROM {$table_units} WHERE section_name != '' ORDER BY section_name ASC" );
    }

    // Auto-select class & section for teachers if not explicitly chosen
    if ( ! $is_admin && empty( $filter_class ) && ! empty( $classes[0] ) ) {
        $filter_class = $classes[0];
    }
    if ( ! $is_admin && empty( $filter_section ) && ! empty( $all_units ) ) {
        foreach ( $all_units as $unit_row ) {
            if ( $unit_row->class_name === $filter_class && ! empty( $unit_row->section_name ) ) {
                $filter_section = $unit_row->section_name;
                break;
            }
        }
    }

    // 3. Fetch all active students scoped to Assigned Classes & Sections
    if ( ! $is_admin && ! empty( $classes ) ) {
        $class_placeholders = implode( ',', array_fill( 0, count( $classes ), '%s' ) );
        $st_query = "SELECT id, class_name, section_name, full_name, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name IN ($class_placeholders)";
        $st_args  = $classes;

        if ( ! empty( $teacher_assigned_sections ) ) {
            $sec_placeholders = implode( ',', array_fill( 0, count( $teacher_assigned_sections ), '%s' ) );
            $st_query .= " AND section_name IN ($sec_placeholders)";
            $st_args = array_merge( $st_args, $teacher_assigned_sections );
        }

        $st_query .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $all_active_students = $wpdb->get_results( $wpdb->prepare( $st_query, ...$st_args ) );
    } else {
        $all_active_students = $wpdb->get_results( "SELECT id, class_name, section_name, full_name, roll_no FROM {$table_students} WHERE status = 'Active' ORDER BY CAST(roll_no AS UNSIGNED) ASC" );
    }

    $selected_month = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : current_time( 'Y-m' );
    $filter_student = isset( $_GET['filter_student'] ) ? intval( $_GET['filter_student'] ) : 0;
    
    $start_date     = $selected_month . '-01';
    $end_date       = date( 'Y-m-t', strtotime( $start_date ) );
    $days_in_month  = (int) date( 't', strtotime( $start_date ) );

    $students       = array();
    $daily_records  = array();
    $summary_counts = array();

    if ( ! empty( $filter_class ) ) {
        // Enforce boundary check for non-admin teachers
        if ( ! $is_admin && ! in_array( $filter_class, $classes, true ) ) {
            echo '<div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:20px; border-radius:12px; text-align:center; font-weight:600;"><p style="margin:0;">' . esc_html__( 'You are not authorized to view the monthly summary for this class.', 'ifsedu-sms' ) . '</p></div>';
            return;
        }

        $query = "SELECT id, student_id, full_name, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $params = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $query .= " AND section_name = %s";
            $params[] = $filter_section;
        } elseif ( ! $is_admin && ! empty( $teacher_assigned_sections ) ) {
            $sec_placeholders = implode( ',', array_fill( 0, count( $teacher_assigned_sections ), '%s' ) );
            $query .= " AND section_name IN ($sec_placeholders)";
            $params = array_merge( $params, $teacher_assigned_sections );
        }

        if ( $filter_student > 0 ) {
            $query .= " AND id = %d";
            $params[] = $filter_student;
        }

        $query .= " ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC";
        $students = $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );

        if ( ! empty( $students ) ) {
            $student_ids  = wp_list_pluck( $students, 'id' );
            $placeholders = implode( ',', array_fill( 0, count( $student_ids ), '%d' ) );

            // Fetch day-by-day attendance entries for matrix grid
            $raw_daily = $wpdb->get_results( $wpdb->prepare(
                "SELECT student_id, attendance_date, status
                 FROM {$table_attendance}
                 WHERE attendance_date BETWEEN %s AND %s AND student_id IN ($placeholders)",
                array_merge( array( $start_date, $end_date ), $student_ids )
            ) );

            foreach ( $raw_daily as $entry ) {
                $day_num = (int) date( 'j', strtotime( $entry->attendance_date ) );
                $daily_records[ $entry->student_id ][ $day_num ] = $entry->status;
                
                if ( ! isset( $summary_counts[ $entry->student_id ][ $entry->status ] ) ) {
                    $summary_counts[ $entry->student_id ][ $entry->status ] = 0;
                }
                $summary_counts[ $entry->student_id ][ $entry->status ]++;
            }
        }
    }
    ?>

    <style>
        .att-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
        }
        .att-badge-p { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .att-badge-a { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .att-badge-l { background: #fff7ed; color: #d97706; border: 1px solid #fed7aa; }
        .att-badge-empty { color: #cbd5e1; font-weight: 400; }
    </style>

    <!-- Monthly Filter Control Bento Card -->
    <div class="dpt-bento-card no-print" style="background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:12px; margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h4 style="margin:0; font-size:16px; font-weight:800; color:#006a4e;"><?php esc_html_e( 'Monthly Attendance Summary & Audit', 'ifsedu-sms' ); ?></h4>
            <?php if ( ! $is_admin ) : ?>
                <span style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700;">
                    <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                    <?php esc_html_e( 'Teacher Mode: Assigned Allocations Only', 'ifsedu-sms' ); ?>
                </span>
            <?php endif; ?>
        </div>

        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            <input type="hidden" name="sub" value="monthly">

            <div class="dpt-form-group" style="flex:1; min-width:160px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Target Month', 'ifsedu-sms' ); ?> *</label>
                <input type="month" name="month" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" value="<?php echo esc_attr( $selected_month ); ?>" max="<?php echo esc_attr( current_time( 'Y-m' ) ); ?>" required>
            </div>

            <div class="dpt-form-group" style="flex:1; min-width:160px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">
                    <?php esc_html_e( 'Class', 'ifsedu-sms' ); ?> *
                    <?php if ( ! $is_admin ) : ?>
                        <span style="color:#059669; font-size:11px; font-weight:700;">(<?php esc_html_e( 'Assigned Only', 'ifsedu-sms' ); ?>)</span>
                    <?php endif; ?>
                </label>
                <select name="class_name" id="educore_attendance_class_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" required>
                    <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-sms' ); ?></option>
                    <?php foreach ( $classes as $cls ) : ?>
                        <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="dpt-form-group" style="flex:1; min-width:160px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">
                    <?php esc_html_e( 'Section', 'ifsedu-sms' ); ?>
                    <?php if ( ! $is_admin ) : ?>
                        <span style="color:#059669; font-size:11px; font-weight:700;">(<?php esc_html_e( 'Assigned', 'ifsedu-sms' ); ?>)</span>
                    <?php endif; ?>
                </label>
                <select name="section_name" id="educore_attendance_section_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;">
                    <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
                    <!-- Options populated via JS -->
                </select>
            </div>
            
            <div class="dpt-form-group" style="flex:1; min-width:200px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Student (Optional)', 'ifsedu-sms' ); ?></label>
                <select name="filter_student" id="educore_attendance_student_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;">
                    <option value=""><?php esc_html_e( '-- All Students --', 'ifsedu-sms' ); ?></option>
                    <!-- Options populated via JS -->
                </select>
            </div>

            <div class="dpt-form-group">
                <button type="submit" style="height:40px; padding:0 24px; background:#006a4e; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Generate Monthly Audit', 'ifsedu-sms' ); ?></button>
            </div>
        </form>
    </div>

    <?php if ( ! empty( $filter_class ) && ! empty( $students ) ) : ?>
        <div class="dpt-bento-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:20px;">
                <div>
                    <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;"><?php printf( esc_html__( 'Monthly Attendance Audit Statement: %s', 'ifsedu-sms' ), esc_html( date_i18n( 'F Y', strtotime( $start_date ) ) ) ); ?></h3>
                    <span style="color:#64748b; font-size:13px; font-weight:600;"><?php printf( esc_html__( 'Class: %1$s %2$s', 'ifsedu-sms' ), esc_html( $filter_class ), esc_html( $filter_section ? '(' . $filter_section . ')' : '' ) ); ?></span>
                </div>
                <button type="button" onclick="window.print();" class="no-print" style="height:36px; padding:0 16px; background:#0f172a; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer;">
                    <span class="dashicons dashicons-printer" style="vertical-align:middle; font-size:16px; width:16px; height:16px;"></span>
                    <?php esc_html_e( 'Print Summary', 'ifsedu-sms' ); ?>
                </button>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; text-align:left; font-size:12.5px;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="padding:10px 8px; color:#475569; border-bottom:1px solid #e2e8f0; sticky:left; background:#f8fafc; min-width:50px;"><?php esc_html_e( 'Roll', 'ifsedu-sms' ); ?></th>
                            <th style="padding:10px 8px; color:#475569; border-bottom:1px solid #e2e8f0; sticky:left; background:#f8fafc; min-width:140px;"><?php esc_html_e( 'Student Name', 'ifsedu-sms' ); ?></th>
                            
                            <!-- Calendar Days Columns -->
                            <?php for ( $d = 1; $d <= $days_in_month; $d++ ) : ?>
                                <th style="padding:6px 2px; color:#475569; border-bottom:1px solid #e2e8f0; text-align:center; min-width:28px; font-size:11px;"><?php echo $d; ?></th>
                            <?php endfor; ?>

                            <th style="padding:10px 8px; border-bottom:1px solid #e2e8f0; text-align:center; color:#059669; font-weight:800;"><?php esc_html_e( 'P', 'ifsedu-sms' ); ?></th>
                            <th style="padding:10px 8px; border-bottom:1px solid #e2e8f0; text-align:center; color:#dc2626; font-weight:800;"><?php esc_html_e( 'A', 'ifsedu-sms' ); ?></th>
                            <th style="padding:10px 8px; border-bottom:1px solid #e2e8f0; text-align:center; color:#d97706; font-weight:800;"><?php esc_html_e( 'L', 'ifsedu-sms' ); ?></th>
                            <th style="padding:10px 8px; color:#475569; border-bottom:1px solid #e2e8f0; text-align:right; min-width:100px;"><?php esc_html_e( 'Ratio', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $students as $st ) : 
                            $st_id          = (int) $st->id;
                            $p_cnt          = isset( $summary_counts[ $st_id ]['Present'] ) ? $summary_counts[ $st_id ]['Present'] : 0;
                            $a_cnt          = isset( $summary_counts[ $st_id ]['Absent'] ) ? $summary_counts[ $st_id ]['Absent'] : 0;
                            $l_cnt          = isset( $summary_counts[ $st_id ]['Late'] ) ? $summary_counts[ $st_id ]['Late'] : 0;
                            $total_recorded = $p_cnt + $a_cnt + $l_cnt;
                            $pct            = $total_recorded > 0 ? round( ( $p_cnt / $total_recorded ) * 100, 1 ) : 0;
                            $pct_color      = $pct >= 80 ? '#059669' : ( $pct >= 60 ? '#d97706' : '#dc2626' );
                        ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:10px 8px;"><strong>#<?php echo esc_html( $st->roll_no ); ?></strong></td>
                                <td style="padding:10px 8px;"><strong style="color:#0f172a;"><?php echo esc_html( $st->full_name ); ?></strong></td>
                                
                                <!-- Day Grid Output -->
                                <?php for ( $d = 1; $d <= $days_in_month; $d++ ) : 
                                    $st_status = isset( $daily_records[ $st_id ][ $d ] ) ? $daily_records[ $st_id ][ $d ] : '';
                                ?>
                                    <td style="padding:4px 1px; text-align:center;">
                                        <?php if ( $st_status === 'Present' ) : ?>
                                            <span class="att-status-badge att-badge-p">P</span>
                                        <?php elseif ( $st_status === 'Absent' ) : ?>
                                            <span class="att-status-badge att-badge-a">A</span>
                                        <?php elseif ( $st_status === 'Late' ) : ?>
                                            <span class="att-status-badge att-badge-l">L</span>
                                        <?php else : ?>
                                            <span class="att-status-badge att-badge-empty">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>

                                <td style="padding:10px 6px; text-align:center; font-weight:800; color:#059669; background:rgba(5, 150, 105, 0.03);"><?php echo $p_cnt; ?></td>
                                <td style="padding:10px 6px; text-align:center; font-weight:800; color:#dc2626; background:rgba(220, 38, 38, 0.03);"><?php echo $a_cnt; ?></td>
                                <td style="padding:10px 6px; text-align:center; font-weight:800; color:#d97706; background:rgba(217, 119, 6, 0.03);"><?php echo $l_cnt; ?></td>
                                <td style="padding:10px 8px; text-align:right;">
                                    <strong style="color:<?php echo $pct_color; ?>; font-size:12px;"><?php echo $pct; ?>%</strong>
                                    <div style="height:5px; background:#e2e8f0; border-radius:10px; overflow:hidden; margin-top:4px;">
                                        <div style="width:<?php echo $pct; ?>%; height:100%; background:<?php echo $pct_color; ?>; border-radius:10px;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ( ! empty( $filter_class ) ) : ?>
        <div style="background:#fffbeb; border:1px solid #fed7aa; color:#9a3412; padding:20px; border-radius:12px; text-align:center; font-weight:600;"><span class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px; margin-bottom:10px; display:block; margin:0 auto;"></span><p style="margin:0;"><?php esc_html_e( 'No active student records found for the selected Class/Section.', 'ifsedu-sms' ); ?></p></div>
    <?php else : ?>
        <div style="background:#f0f9ff; border:1px solid #bae6fd; color:#0369a1; padding:20px; border-radius:12px; text-align:center; font-weight:600;"><span class="dashicons dashicons-info" style="font-size:24px; width:24px; height:24px; margin-bottom:10px; display:block; margin:0 auto;"></span><p style="margin:0;"><?php esc_html_e( 'Select a Target Month and Academic Class above to generate the attendance audit statement.', 'ifsedu-sms' ); ?></p></div>
    <?php endif; ?>

    <!-- Dynamic JS Engine: Safe Class->Section->Student Chaining -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        
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
                opt.textContent = stu.roll_no ? `[Roll: ${stu.roll_no}] ${stu.full_name}` : stu.full_name;
                
                if (String(stu.id) === String(selectedStudentId)) {
                    opt.selected = true;
                }
                studentSelect.appendChild(opt);
            });
        }

        if (classSelect && sectionSelect && studentSelect) {
            populateSections(classSelect.value, currentFilterSection);
            populateStudents(classSelect.value, currentFilterSection, currentFilterStudent);

            classSelect.addEventListener('change', function() {
                populateSections(classSelect.value);
                populateStudents(classSelect.value, sectionSelect.value);
            });

            sectionSelect.addEventListener('change', function() {
                populateStudents(classSelect.value, sectionSelect.value);
            });
        }
    });
    </script>
<?php
}