<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * High-End Academic Daily Attendance Roster Matrix & Interactivity Engine
 * Custom Prefixes Applied: dpt-, afdp-
 * Architecture: Elite Bento Box Interface with In-Memory Caching and Kinetic States
 */
function educore_attendance_tab() {
    global $wpdb;
    
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $table_units      = $wpdb->prefix . 'sms_academic_units';

    // Strict Security Control: Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage attendance configurations.', 'educore' ) );
    }

    // 1. Handle Form Submission (Save Attendance Data via Single Optimized Execution)
    if ( isset( $_POST['educore_save_attendance'] ) && wp_verify_nonce( $_POST['educore_attendance_nonce'], 'save_attendance_action' ) ) {
        $attendance_date = sanitize_text_field( $_POST['attendance_date'] );
        $attendance_data = isset( $_POST['attendance'] ) ? (array) $_POST['attendance'] : array();
        $current_user_id = get_current_user_id();

        $saved_count = 0;

        if ( ! empty( $attendance_data ) ) {
            // Extract targeted student IDs to pull existing state records in 1 database hit
            $target_student_ids = array_map( 'intval', array_keys( $attendance_data ) );
            $ids_placeholder    = implode( ',', array_fill( 0, count( $target_student_ids ), '%d' ) );
            
            $prep_query = $wpdb->prepare(
                "SELECT student_id, id FROM {$table_attendance} WHERE attendance_date = %s AND student_id IN ($ids_placeholder)",
                array_merge( array( $attendance_date ), $target_student_ids )
            );
            
            $existing_records = $wpdb->get_results( $prep_query, OBJECT_K ); // Map student_id as array key

            foreach ( $attendance_data as $student_id => $status ) {
                $student_id = intval( $student_id );
                $status     = sanitize_text_field( $status );

                if ( isset( $existing_records[ $student_id ] ) ) {
                    // Update existing record using pre-fetched internal ID
                    $wpdb->update( 
                        $table_attendance, 
                        array( 'status' => $status, 'recorded_by' => $current_user_id ), 
                        array( 'id' => intval( $existing_records[ $student_id ]->id ) ),
                        array( '%s', '%d' ),
                        array( '%d' )
                    );
                } else {
                    // Insert brand new tracking node
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

        echo '<div class="afdp-success-banner"><span class="dashicons dashicons-yes-alt"></span> Attendance records successfully parsed and updated for ' . intval( $saved_count ) . ' students.</div>';
        
        if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
            IFSEdu_School_Management_System::log_activity( "Recorded daily attendance grid for Date: " . $attendance_date );
        }
    }

    // 2. Filter Inputs Configuration
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( $_GET['class_name'] ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( $_GET['section_name'] ) : '';
    $filter_date    = isset( $_GET['attendance_date'] ) ? sanitize_text_field( $_GET['attendance_date'] ) : current_time('Y-m-d');

    // 3. Fetch Classes & Sections Dynamically from Academic Units Table
    $classes = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_units} ORDER BY class_name ASC" );
    
    // Build Sections and Department Groups list safely
    $raw_sections = $wpdb->get_results( "SELECT DISTINCT section_name, dept_name FROM {$table_units} WHERE section_name != '' OR dept_name != ''" );
    $sections     = array();
    if ( ! empty( $raw_sections ) ) {
        foreach ( $raw_sections as $sec_obj ) {
            if ( ! empty( $sec_obj->section_name ) ) $sections[] = $sec_obj->section_name;
            if ( ! empty( $sec_obj->dept_name ) )    $sections[] = $sec_obj->dept_name;
        }
        $sections = array_unique( $sections );
    }
    ?>

    <style>
        /* ==========================================================================
           1. CORE BENTO UI ENGINE LAYERING
           ========================================================================== */
        .dpt-attendance-root {
            margin: 20px 20px 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        .afdp-header-block {
            margin-bottom: 24px;
        }
        .afdp-header-block h2 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.5px;
        }
        .afdp-header-block h2 .dashicons {
            font-size: 26px;
            width: 26px;
            height: 26px;
            color: #006a4e;
        }

        /* Modern Bento Block Framework */
        .dpt-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            margin-bottom: 24px;
        }

        .afdp-success-banner {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            padding: 14px 18px;
            color: #065f46;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Form Layout Elements */
        .dpt-form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            align-items: end;
        }
        .dpt-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .dpt-form-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            margin: 0;
        }
        .dpt-input-field, .dpt-select-field {
            height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            width: 100%;
            box-shadow: none;
            transition: all 0.2s;
        }
        .dpt-input-field:focus, .dpt-select-field:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
            outline: none;
        }

        .dpt-btn-submit-trigger {
            height: 40px;
            background: #006a4e;
            border: 1px solid transparent;
            color: #ffffff;
            font-weight: 700;
            font-size: 13.5px;
            border-radius: 8px;
            padding: 0 20px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.15);
        }
        .dpt-btn-submit-trigger:hover {
            background: #00523c;
            color: #ffffff;
            transform: translateY(-0.5px);
        }

        /* Roster Matrix Visual Top Layer */
        .afdp-roster-meta-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .afdp-roster-title h4 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }
        .afdp-roster-title small {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
        }

        /* Live Analytic Counter Badges */
        .dpt-counter-cluster {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .dpt-badge-pill {
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid transparent;
        }
        .dpt-badge-total   { background: #f1f5f9; border-color: #e2e8f0; color: #334155; }
        .dpt-badge-present { background: #e6f4ea; border-color: #ceead6; color: #137333; }
        .dpt-badge-absent  { background: #fce8e6; border-color: #fad2cf; color: #c5221f; }
        .dpt-badge-late    { background: #fef7e0; border-color: #feebc8; color: #b06000; }

        /* Automation Bar Layout */
        .afdp-bulk-automation-row {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .afdp-bulk-label {
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .afdp-bulk-actions {
            display: flex;
            gap: 8px;
        }
        .dpt-bulk-btn {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .dpt-bulk-btn[data-target-status="Present"]:hover { border-color: #137333; color: #137333; background: #e6f4ea; }
        .dpt-bulk-btn[data-target-status="Absent"]:hover { border-color: #c5221f; color: #c5221f; background: #fce8e6; }
        .dpt-bulk-btn[data-target-status="Late"]:hover { border-color: #b06000; color: #b06000; background: #fef7e0; }

        /* Precise Roster Grid Table Matrix */
        .dpt-table-responsive {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        .dpt-attendance-matrix-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
        }
        .dpt-attendance-matrix-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 12.5px;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .dpt-attendance-matrix-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13.5px;
            color: #334155;
            background: #ffffff;
        }
        .dpt-attendance-matrix-table tr:last-child td {
            border-bottom: none;
        }
        .dpt-attendance-matrix-table tr:hover td {
            background: #f8fafc;
        }

        /* Kinetic Multi-State Selector Radio Pill Layout */
        .afdp-radio-pill-group {
            display: inline-flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .afdp-radio-pill-item {
            display: none;
        }
        .afdp-radio-pill-label {
            padding: 6px 16px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            color: #64748b;
            display: inline-block;
            margin: 0;
        }
        
        /* Present Check Node CSS */
        .afdp-radio-pill-item[value="Present"]:checked + .afdp-radio-pill-label {
            background: #006a4e;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 106, 78, 0.25);
        }
        /* Absent Check Node CSS */
        .afdp-radio-pill-item[value="Absent"]:checked + .afdp-radio-pill-label {
            background: #ef4444;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.25);
        }
        /* Late Check Node CSS */
        .afdp-radio-pill-item[value="Late"]:checked + .afdp-radio-pill-label {
            background: #f59e0b;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25);
        }

        .afdp-fallback-card {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
        }
        .afdp-fallback-card .dashicons {
            font-size: 36px;
            width: 36px;
            height: 36px;
            color: #94a3b8;
            margin-bottom: 10px;
        }
        .afdp-fallback-card p {
            margin: 0;
            font-size: 14px;
            color: #64748b;
            font-weight: 600;
        }

        /* ==========================================================================
           2. HARDWARE PRINT METRICS INCLUSIONS
           ========================================================================== */
        @media print {
            .no-print, 
            .dpt-bento-card:first-of-type,
            .afdp-bulk-automation-row,
            .mt-4.text-end {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .dpt-attendance-root { margin: 0 !important; }
            .dpt-bento-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
            .afdp-radio-pill-group { border: none !important; background: transparent !important; }
            .afdp-radio-pill-item:not(:checked) + .afdp-radio-pill-label { display: none !important; }
            .afdp-radio-pill-label { padding: 0 !important; font-size: 13px !important; color: #000000 !important; background: transparent !important; box-shadow: none !important; }
        }
    </style>

    <div class="dpt-attendance-root">
        
        <div class="afdp-header-block no-print">
            <h2><span class="dashicons dashicons-clipboard"></span> Daily Attendance Matrix</h2>
        </div>

        <!-- Filter Console Bento Box Block -->
        <div class="dpt-bento-card">
            <form method="GET" action="">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="attendance">
                
                <div class="dpt-form-row">
                    <div class="dpt-form-group">
                        <label class="dpt-form-label">Select Target Date <span style="color:#ef4444;">*</span></label>
                        <input type="date" name="attendance_date" class="dpt-input-field" value="<?php echo esc_attr( $filter_date ); ?>" required>
                    </div>
                    
                    <div class="dpt-form-group">
                        <label class="dpt-form-label">Academic Class / Year <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" class="dpt-select-field" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ( $classes as $cls ) : ?>
                                <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="dpt-form-group">
                        <label class="dpt-form-label">Section / Group</label>
                        <select name="section_name" class="dpt-select-field">
                            <option value="">-- All Sections / Groups --</option>
                            <?php foreach ( $sections as $sec ) : ?>
                                <option value="<?php echo esc_attr( $sec ); ?>" <?php selected( $filter_section, $sec ); ?>><?php echo esc_html( $sec ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="dpt-form-group">
                        <button type="submit" class="dpt-btn-submit-trigger" style="width: 100%;">Load Roster Structure</button>
                    </div>
                </div>
            </form>
        </div>

        <?php
        // 4. Render Roster Frame If Criteria Loaded
        if ( ! empty( $filter_class ) ) {
            
            $query = "SELECT id, student_id, full_name, roll_no FROM {$table_students} WHERE status = 'Active' AND class_name = %s";
            $sql_args = array( $filter_class );

            if ( ! empty( $filter_section ) ) {
                $query .= " AND section_name = %s";
                $sql_args[] = $filter_section;
            }

            $query .= " ORDER BY roll_no ASC";
            
            // Bulletproof approach using call_user_func_array to prevent any variable unpacking parse errors
            $prep_roster_query = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $query ), $sql_args ) );
            $students = $wpdb->get_results( $prep_roster_query );

            if ( $students ) {
                // Bulk memory caching for loaded layout state to prevent individual database calls in table loop
                $student_ids = wp_list_pluck( $students, 'id' );
                $placeholders = implode( ',', array_fill( 0, count( $student_ids ), '%d' ) );
                
                $cached_attendance_query = $wpdb->prepare(
                    "SELECT student_id, status FROM {$table_attendance} WHERE attendance_date = %s AND student_id IN ($placeholders)",
                    array_merge( array( $filter_date ), $student_ids )
                );
                $loaded_attendance_states = $wpdb->get_results( $cached_attendance_query, OBJECT_K ); // Map student_id as matrix array key
                ?>
                <div class="dpt-bento-card">
                    
                    <!-- Dynamic Metrics Real-time Analytics Dashboard Header -->
                    <div class="afdp-roster-meta-bar">
                        <div class="afdp-roster-title">
                            <h4>Mark Attendance: <span style="color: #006a4e;"><?php echo esc_html( $filter_class . ( $filter_section ? ' (' . $filter_section . ')' : '' ) ); ?></span></h4>
                            <small>Target Date: <?php echo date('d F, Y', strtotime($filter_date)); ?></small>
                        </div>
                        
                        <!-- Live Stats Dynamic Counters Grid -->
                        <div class="dpt-counter-cluster">
                            <span class="dpt-badge-pill dpt-badge-total">Total: <span id="cnt-total"><?php echo count($students); ?></span></span>
                            <span class="dpt-badge-pill dpt-badge-present">Present: <span id="cnt-present">0</span></span>
                            <span class="dpt-badge-pill dpt-badge-absent">Absent: <span id="cnt-absent">0</span></span>
                            <span class="dpt-badge-pill dpt-badge-late">Late: <span id="cnt-late">0</span></span>
                        </div>
                    </div>

                    <!-- Global Action Automation Controllers Bar -->
                    <div class="afdp-bulk-automation-row no-print">
                        <div class="afdp-bulk-label">
                            <span class="dashicons dashicons-admin-tools"></span> Bulk Operations:
                        </div>
                        <div class="afdp-bulk-actions">
                            <button type="button" class="dpt-bulk-btn" data-target-status="Present">Set All Present</button>
                            <button type="button" class="dpt-bulk-btn" data-target-status="Absent">Set All Absent</button>
                            <button type="button" class="dpt-bulk-btn" data-target-status="Late">Set All Late</button>
                        </div>
                    </div>
                    
                    <form method="POST" action="" id="educoreAttendanceSubmitEngine">
                        <?php wp_nonce_field( 'save_attendance_action', 'educore_attendance_nonce' ); ?>
                        <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                        <div class="dpt-table-responsive">
                            <table class="dpt-attendance-matrix-table">
                                <thead>
                                    <tr>
                                        <th style="width: 12%;">Roll No</th>
                                        <th style="width: 18%;">Student ID</th>
                                        <th style="width: 40%;">Student Name</th>
                                        <th style="width: 30%; text-align: center;">Attendance Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ( $students as $student ) : 
                                        $student_internal_id = intval( $student->id );
                                        $current_status = isset( $loaded_attendance_states[ $student_internal_id ] ) ? $loaded_attendance_states[ $student_internal_id ]->status : 'Present';
                                    ?>
                                    <tr class="student-attendance-row">
                                        <td><strong># <?php echo esc_html( $student->roll_no ); ?></strong></td>
                                        <td><code style="color: #0f172a; font-weight: 700; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?php echo esc_html( $student->student_id ); ?></code></td>
                                        <td><span style="font-weight: 600; color: #1e293b;"><?php echo esc_html( $student->full_name ); ?></span></td>
                                        <td style="text-align: center;">
                                            <div class="afdp-radio-pill-group">
                                                <input type="radio" class="afdp-radio-pill-item status-radio-node" name="attendance[<?php echo $student_internal_id; ?>]" id="present_<?php echo $student_internal_id; ?>" value="Present" <?php checked( $current_status, 'Present' ); ?>>
                                                <label class="afdp-radio-pill-label" for="present_<?php echo $student_internal_id; ?>">Present</label>

                                                <input type="radio" class="afdp-radio-pill-item status-radio-node" name="attendance[<?php echo $student_internal_id; ?>]" id="absent_<?php echo $student_internal_id; ?>" value="Absent" <?php checked( $current_status, 'Absent' ); ?>>
                                                <label class="afdp-radio-pill-label" for="absent_<?php echo $student_internal_id; ?>">Absent</label>

                                                <input type="radio" class="afdp-radio-pill-item status-radio-node" name="attendance[<?php echo $student_internal_id; ?>]" id="late_<?php echo $student_internal_id; ?>" value="Late" <?php checked( $current_status, 'Late' ); ?>>
                                                <label class="afdp-radio-pill-label" for="late_<?php echo $student_internal_id; ?>">Late</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 text-end" style="margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
                            <button type="submit" name="educore_save_attendance" class="dpt-btn-submit-trigger" style="padding: 0 32px; height: 44px; font-size: 14px;">
                                Commit & Save Roster Stack
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Dynamic Interactivity Engine Layer -->
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    function recountLiveStatisticsDashboard() {
                        var total   = $('.student-attendance-row').length;
                        var present = $('.status-radio-node[value="Present"]:checked').length;
                        var absent  = $('.status-radio-node[value="Absent"]:checked').length;
                        var late    = $('.status-radio-node[value="Late"]:checked').length;

                        $('#cnt-total').text(total);
                        $('#cnt-present').text(present);
                        $('#cnt-absent').text(absent);
                        $('#cnt-late').text(late);
                    }

                    // Initial load baseline analytics trigger
                    recountLiveStatisticsDashboard();

                    $('.status-radio-node').on('change', function() {
                        recountLiveStatisticsDashboard();
                    });

                    $('.bulk-controller, .dpt-bulk-btn').on('click', function() {
                        var targetedStatusType = $(this).data('target-status');
                        $('.student-attendance-row').each(function() {
                            $(this).find('.status-radio-node[value="' + targetedStatusType + '"]').prop('checked', true);
                        });
                        // Trigger dynamic recount once after final iteration block loop completes
                        recountLiveStatisticsDashboard();
                    });
                });
                </script>
                <?php
            } else {
                echo '<div class="afdp-fallback-card"><span class="dashicons dashicons-warning"></span><p>No active students found matching current Class/Section requirements.</p></div>';
            }
        } else {
            echo '<div class="afdp-fallback-card"><span class="dashicons dashicons-info"></span><p>Select a target Date and Class above to load the attendance workspace.</p></div>';
        }
    }
