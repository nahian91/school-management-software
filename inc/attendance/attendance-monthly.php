<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Monthly Attendance Summary Audit & Reports
 * File: inc/attendance/attendance-monthly.php
 */
function educore_monthly_attendance_summary_view( $classes, $sections, $filter_class, $filter_section ) {
    global $wpdb;

    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $table_units      = $wpdb->prefix . 'sms_academic_units';

    // Fetch academic units for dynamic section dropdown
    $all_units = $wpdb->get_results( "SELECT id, class_name, section_name FROM {$table_units} WHERE section_name != '' ORDER BY section_name ASC" );

    // Fetch all active students for the dynamic student dropdown
    $all_active_students = $wpdb->get_results( "SELECT id, class_name, section_name, full_name, roll_no FROM {$table_students} WHERE status = 'Active' ORDER BY CAST(roll_no AS UNSIGNED) ASC" );

    $selected_month = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : current_time( 'Y-m' );
    $filter_student = isset( $_GET['filter_student'] ) ? intval( $_GET['filter_student'] ) : 0;
    
    $start_date     = $selected_month . '-01';
    $end_date       = date( 'Y-m-t', strtotime( $start_date ) );

    $students     = array();
    $summary_data = array();

    if ( ! empty( $filter_class ) ) {
        $query = "SELECT id, student_id, full_name, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
        $params = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $query .= " AND section_name = %s";
            $params[] = $filter_section;
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

            $raw_summary = $wpdb->get_results( $wpdb->prepare(
                "SELECT student_id, status, COUNT(*) as count_total
                 FROM {$table_attendance}
                 WHERE attendance_date BETWEEN %s AND %s AND student_id IN ($placeholders)
                 GROUP BY student_id, status",
                array_merge( array( $start_date, $end_date ), $student_ids )
            ) );

            foreach ( $raw_summary as $row ) {
                $summary_data[ $row->student_id ][ $row->status ] = (int) $row->count_total;
            }
        }
    }
    ?>

    <!-- Monthly Filter Control Bento Card -->
    <div class="dpt-bento-card no-print" style="background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:12px; margin-bottom:24px;">
        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            <input type="hidden" name="sub" value="monthly">

            <div class="dpt-form-group" style="flex:1; min-width:160px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Target Month', 'ifsedu-sms' ); ?> *</label>
                <!-- Input type "month" with max attribute set to current YYYY-MM -->
                <input type="month" name="month" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" value="<?php echo esc_attr( $selected_month ); ?>" max="<?php echo esc_attr( current_time( 'Y-m' ) ); ?>" required>
            </div>

            <div class="dpt-form-group" style="flex:1; min-width:160px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Class', 'ifsedu-sms' ); ?> *</label>
                <select name="class_name" id="educore_attendance_class_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" required>
                    <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-sms' ); ?></option>
                    <?php foreach ( $classes as $cls ) : ?>
                        <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="dpt-form-group" style="flex:1; min-width:160px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Section', 'ifsedu-sms' ); ?></label>
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
                <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="padding:12px; color:#475569; border-bottom:1px solid #e2e8f0;"><?php esc_html_e( 'Roll', 'ifsedu-sms' ); ?></th>
                            <th style="padding:12px; color:#475569; border-bottom:1px solid #e2e8f0;"><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                            <th style="padding:12px; color:#475569; border-bottom:1px solid #e2e8f0;"><?php esc_html_e( 'Student Name', 'ifsedu-sms' ); ?></th>
                            <th style="padding:12px; border-bottom:1px solid #e2e8f0; text-align:center; color:#059669;"><?php esc_html_e( 'Presents', 'ifsedu-sms' ); ?></th>
                            <th style="padding:12px; border-bottom:1px solid #e2e8f0; text-align:center; color:#dc2626;"><?php esc_html_e( 'Absents', 'ifsedu-sms' ); ?></th>
                            <th style="padding:12px; border-bottom:1px solid #e2e8f0; text-align:center; color:#d97706;"><?php esc_html_e( 'Lates', 'ifsedu-sms' ); ?></th>
                            <th style="padding:12px; color:#475569; border-bottom:1px solid #e2e8f0; text-align:right; min-width:140px;"><?php esc_html_e( 'Attendance Ratio', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $students as $st ) : 
                            $st_id          = (int) $st->id;
                            $p_cnt          = isset( $summary_data[ $st_id ]['Present'] ) ? $summary_data[ $st_id ]['Present'] : 0;
                            $a_cnt          = isset( $summary_data[ $st_id ]['Absent'] ) ? $summary_data[ $st_id ]['Absent'] : 0;
                            $l_cnt          = isset( $summary_data[ $st_id ]['Late'] ) ? $summary_data[ $st_id ]['Late'] : 0;
                            $total_recorded = $p_cnt + $a_cnt + $l_cnt;
                            $pct            = $total_recorded > 0 ? round( ( $p_cnt / $total_recorded ) * 100, 1 ) : 0;
                            $pct_color      = $pct >= 80 ? '#059669' : ( $pct >= 60 ? '#d97706' : '#dc2626' );
                        ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:12px;"><strong>#<?php echo esc_html( $st->roll_no ); ?></strong></td>
                                <td style="padding:12px;"><code style="background:#f1f5f9; padding:3px 6px; border-radius:4px; border:1px solid #e2e8f0; color:#0f172a; font-weight:700;"><?php echo esc_html( $st->student_id ); ?></code></td>
                                <td style="padding:12px;"><strong style="color:#0f172a;"><?php echo esc_html( $st->full_name ); ?></strong></td>
                                <td style="padding:12px; text-align:center; font-weight:800; color:#059669; background:rgba(5, 150, 105, 0.03);"><?php echo $p_cnt; ?></td>
                                <td style="padding:12px; text-align:center; font-weight:800; color:#dc2626; background:rgba(220, 38, 38, 0.03);"><?php echo $a_cnt; ?></td>
                                <td style="padding:12px; text-align:center; font-weight:800; color:#d97706; background:rgba(217, 119, 6, 0.03);"><?php echo $l_cnt; ?></td>
                                <td style="padding:12px; text-align:right;">
                                    <strong style="color:<?php echo $pct_color; ?>;"><?php echo $pct; ?>%</strong>
                                    <div style="height:6px; background:#e2e8f0; border-radius:10px; overflow:hidden; margin-top:6px;">
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
        
        // 1. Safe JSON Injection
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
    });
    </script>
<?php
}