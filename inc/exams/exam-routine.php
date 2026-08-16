<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Examination Timetable & Routine Scheduler View
 * File: inc/exams/exam-routine.php
 */
function educore_exam_routine_view() {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to configure exam routines.', 'ifsedu-sms' ) );
    }

    $table_exams        = $wpdb->prefix . 'sms_exams';
    $table_units        = $wpdb->prefix . 'sms_academic_units';
    $table_subjects     = $wpdb->prefix . 'sms_subjects';
    $table_exam_routine = $wpdb->prefix . 'sms_exam_routine';

    // Auto-create exam routine schema if not present
    $check_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_exam_routine}'" );
    if ( empty( $check_table ) ) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_exam_routine} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            exam_id bigint(20) NOT NULL,
            class_id bigint(20) NOT NULL,
            subject_id bigint(20) NOT NULL,
            exam_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            room_no varchar(50) DEFAULT '' NOT NULL,
            PRIMARY KEY (id),
            KEY exam_class_idx (exam_id, class_id)
        ) {$charset_collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    $base_url   = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=routine' );
    $notice_msg = '';

    // 1. Handle Add Slot
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_exam_slot'] ) && check_admin_referer( 'exam_routine_action', 'educore_er_nonce' ) ) {
        $exam_id    = absint( $_POST['exam_id'] );
        $class_id   = absint( $_POST['class_id'] );
        $subject_id = absint( $_POST['subject_id'] );
        $exam_date  = sanitize_text_field( wp_unslash( $_POST['exam_date'] ) );
        $start_time = sanitize_text_field( wp_unslash( $_POST['start_time'] ) );
        $end_time   = sanitize_text_field( wp_unslash( $_POST['end_time'] ) );
        $room_no    = sanitize_text_field( wp_unslash( $_POST['room_no'] ) );

        if ( $exam_id > 0 && $class_id > 0 && $subject_id > 0 && ! empty( $exam_date ) ) {
            $wpdb->insert(
                $table_exam_routine,
                array(
                    'exam_id'    => $exam_id,
                    'class_id'   => $class_id,
                    'subject_id' => $subject_id,
                    'exam_date'  => $exam_date,
                    'start_time' => date( 'H:i:s', strtotime( $start_time ) ),
                    'end_time'   => date( 'H:i:s', strtotime( $end_time ) ),
                    'room_no'    => $room_no,
                ),
                array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
            );
            $notice_msg = __( 'Exam routine slot added successfully.', 'ifsedu-sms' );
        }
    }

    // 2. Handle Delete Slot
    if ( isset( $_GET['action'] ) && 'delete_slot' === $_GET['action'] && isset( $_GET['slot_id'] ) ) {
        $slot_id = absint( $_GET['slot_id'] );
        check_admin_referer( 'delete_slot_' . $slot_id );
        $wpdb->delete( $table_exam_routine, array( 'id' => $slot_id ), array( '%d' ) );
        $notice_msg = __( 'Exam routine slot removed.', 'ifsedu-sms' );
    }

    // Filters
    $filter_exam_id  = isset( $_GET['filter_exam'] ) ? absint( $_GET['filter_exam'] ) : 0;
    $filter_class_id = isset( $_GET['filter_class'] ) ? absint( $_GET['filter_class'] ) : 0;

    $exams    = $wpdb->get_results( "SELECT id, exam_name FROM {$table_exams} ORDER BY id DESC" );
    $classes  = $wpdb->get_results( "SELECT id, class_name, section_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    $subjects = $wpdb->get_results( "SELECT id, subject_name, subject_code, class_id FROM {$table_subjects} ORDER BY subject_name ASC" );

    // Fetch Filtered Schedules
    $where_clauses = array();
    if ( $filter_exam_id > 0 ) {
        $where_clauses[] = $wpdb->prepare( "er.exam_id = %d", $filter_exam_id );
    }
    if ( $filter_class_id > 0 ) {
        $where_clauses[] = $wpdb->prepare( "er.class_id = %d", $filter_class_id );
    }
    $where_sql = ! empty( $where_clauses ) ? "WHERE " . implode( " AND ", $where_clauses ) : "";

    $schedules = $wpdb->get_results( "
        SELECT er.*, e.exam_name, u.class_name, u.section_name, s.subject_name, s.subject_code 
        FROM {$table_exam_routine} er
        INNER JOIN {$table_exams} e ON er.exam_id = e.id
        INNER JOIN {$table_units} u ON er.class_id = u.id
        INNER JOIN {$table_subjects} s ON er.subject_id = s.id
        {$where_sql}
        ORDER BY er.exam_date ASC, er.start_time ASC
    " );

    // Group Schedules by Date for Bottom Preview
    $preview_by_date = array();
    if ( ! empty( $schedules ) ) {
        foreach ( $schedules as $slot ) {
            $preview_by_date[ $slot->exam_date ][] = $slot;
        }
    }

    $school_name = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    ?>

    <style>
        .dpt-routine-grid { display: grid; grid-template-columns: 1.1fr 2fr; gap: 24px; margin-bottom: 24px; }
        @media (max-width: 1024px) { .dpt-routine-grid { grid-template-columns: 1fr; } }
        .dpt-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; box-shadow: 0 4px 20px -2px rgba(0,0,0,0.03); }
        .dpt-form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
        .dpt-label { font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; }
        .dpt-input, .dpt-select { height: 40px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13.5px; background: #f8fafc; width: 100%; box-sizing: border-box; }
        .dpt-input:focus, .dpt-select:focus { border-color: #006a4e; background: #ffffff; outline: none; box-shadow: 0 0 0 3px rgba(0,106,78,0.1); }

        /* Preview Routine Matrix Styles */
        .dpt-preview-wrapper { margin-top: 10px; }
        .dpt-exam-timeline-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .dpt-date-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .dpt-date-header {
            background: #006a4e;
            color: #ffffff;
            padding: 10px 14px;
            font-weight: 800;
            font-size: 13.5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dpt-date-slots-body {
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: #f8fafc;
        }
        .dpt-exam-slot-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #0284c7;
            border-radius: 8px;
            padding: 10px 12px;
        }
        .dpt-slot-badge-time {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11.5px;
            font-weight: 700;
            color: #0369a1;
            background: #e0f2fe;
            padding: 2px 8px;
            border-radius: 6px;
            margin-bottom: 6px;
        }
        .dpt-btn-print {
            height: 36px;
            padding: 0 16px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .dpt-btn-print:hover { background: #f8fafc; color: #006a4e; border-color: #006a4e; }

        @media print {
            .no-print, #wpadminbar, #adminmenumain, .afdp-top-nav-wrapper { display: none !important; }
            body, #wpcontent { background: #ffffff !important; margin: 0 !important; padding: 0 !important; }
            .dpt-preview-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
            .dpt-exam-timeline-grid { grid-template-columns: repeat(3, 1fr) !important; gap: 8px !important; }
            .dpt-date-card { border: 1px solid #000 !important; page-break-inside: avoid; }
            .dpt-date-header { background: #000 !important; color: #fff !important; -webkit-print-color-adjust: exact; }
            .dpt-exam-slot-item { border: 1px solid #ccc !important; }
        }
    </style>

    <?php if ( ! empty( $notice_msg ) ) : ?>
        <div class="no-print" style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:12px 16px; border-radius:8px; font-weight:700; margin-bottom:20px;">
            <?php echo esc_html( $notice_msg ); ?>
        </div>
    <?php endif; ?>

    <!-- TOP SECTION: FORM & SCHEDULER LIST -->
    <div class="dpt-routine-grid no-print">
        
        <!-- Left: Add Exam Slot Form -->
        <div class="dpt-card" style="height:fit-content;">
            <h3 style="margin:0 0 16px 0; font-size:16px; font-weight:800; color:#0f172a; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
                <span class="dashicons dashicons-calendar-alt" style="color:#006a4e;"></span>
                <?php esc_html_e( 'Schedule Exam Slot', 'ifsedu-sms' ); ?>
            </h3>

            <form method="POST" action="">
                <?php wp_nonce_field( 'exam_routine_action', 'educore_er_nonce' ); ?>

                <div class="dpt-form-group">
                    <label class="dpt-label"><?php esc_html_e( 'Examination Scheme', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="exam_id" class="dpt-select" required>
                        <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $exams as $ex ) : ?>
                            <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam_id, $ex->id ); ?>><?php echo esc_html( $ex->exam_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-label"><?php esc_html_e( 'Class / Section', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="class_id" id="er_class_select" class="dpt-select" required>
                        <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $classes as $cl ) : 
                            $sec = ! empty( $cl->section_name ) ? ' (' . $cl->section_name . ')' : '';
                        ?>
                            <option value="<?php echo intval( $cl->id ); ?>"><?php echo esc_html( $cl->class_name . $sec ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-label"><?php esc_html_e( 'Subject', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="subject_id" id="er_subject_select" class="dpt-select" required>
                        <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $subjects as $sub ) : ?>
                            <option value="<?php echo intval( $sub->id ); ?>" data-classid="<?php echo esc_attr( $sub->class_id ); ?>">
                                <?php echo esc_html( $sub->subject_name . ( $sub->subject_code ? ' [' . $sub->subject_code . ']' : '' ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-label"><?php esc_html_e( 'Exam Date', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="exam_date" class="dpt-input" value="<?php echo current_time( 'Y-m-d' ); ?>" required>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="dpt-form-group">
                        <label class="dpt-label"><?php esc_html_e( 'Start Time', 'ifsedu-sms' ); ?></label>
                        <input type="time" name="start_time" class="dpt-input" required>
                    </div>
                    <div class="dpt-form-group">
                        <label class="dpt-label"><?php esc_html_e( 'End Time', 'ifsedu-sms' ); ?></label>
                        <input type="time" name="end_time" class="dpt-input" required>
                    </div>
                </div>

                <div class="dpt-form-group">
                    <label class="dpt-label"><?php esc_html_e( 'Room No / Hall', 'ifsedu-sms' ); ?></label>
                    <input type="text" name="room_no" class="dpt-input" placeholder="e.g. Room 204">
                </div>

                <button type="submit" name="save_exam_slot" style="width:100%; height:42px; background:#006a4e; color:#fff; font-weight:700; border:none; border-radius:8px; cursor:pointer; box-shadow:0 4px 12px rgba(0,106,78,0.2);">
                    <span class="dashicons dashicons-saved" style="vertical-align:middle;"></span> <?php esc_html_e( 'Save Exam Slot', 'ifsedu-sms' ); ?>
                </button>
            </form>
        </div>

        <!-- Right: Timetable Data Table -->
        <div class="dpt-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #f1f5f9; padding-bottom:12px; flex-wrap:wrap; gap:10px;">
                <h3 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">
                    <span class="dashicons dashicons-list-view" style="color:#006a4e;"></span>
                    <?php esc_html_e( 'Scheduled Exam Slots', 'ifsedu-sms' ); ?>
                </h3>

                <form method="GET" action="" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <input type="hidden" name="page" value="school_management_system">
                    <input type="hidden" name="tab" value="exams">
                    <input type="hidden" name="sub" value="routine">
                    
                    <select name="filter_exam" style="height:34px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; font-weight:600;">
                        <option value=""><?php esc_html_e( '-- All Exams --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $exams as $ex ) : ?>
                            <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam_id, $ex->id ); ?>><?php echo esc_html( $ex->exam_name ); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="filter_class" style="height:34px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; font-weight:600;">
                        <option value=""><?php esc_html_e( '-- All Classes --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $classes as $cl ) : 
                            $sec = ! empty( $cl->section_name ) ? ' (' . $cl->section_name . ')' : '';
                        ?>
                            <option value="<?php echo intval( $cl->id ); ?>" <?php selected( $filter_class_id, $cl->id ); ?>><?php echo esc_html( $cl->class_name . $sec ); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="dpt-btn-print" style="height:34px; padding:0 12px; background:#006a4e; color:#ffffff; border-color:#006a4e;">
                        <?php esc_html_e( 'Filter', 'ifsedu-sms' ); ?>
                    </button>
                    
                    <?php if ( $filter_exam_id > 0 || $filter_class_id > 0 ) : ?>
                        <a href="<?php echo esc_url( $base_url ); ?>" class="dpt-btn-print" style="height:34px; padding:0 10px; text-decoration:none;">
                            <?php esc_html_e( 'Reset', 'ifsedu-sms' ); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:separate; border-spacing:0; font-size:13px;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e2e8f0; font-size:11px; text-transform:uppercase; color:#475569;"><?php esc_html_e( 'Date & Time', 'ifsedu-sms' ); ?></th>
                            <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e2e8f0; font-size:11px; text-transform:uppercase; color:#475569;"><?php esc_html_e( 'Exam', 'ifsedu-sms' ); ?></th>
                            <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e2e8f0; font-size:11px; text-transform:uppercase; color:#475569;"><?php esc_html_e( 'Class', 'ifsedu-sms' ); ?></th>
                            <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e2e8f0; font-size:11px; text-transform:uppercase; color:#475569;"><?php esc_html_e( 'Subject', 'ifsedu-sms' ); ?></th>
                            <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e2e8f0; font-size:11px; text-transform:uppercase; color:#475569;"><?php esc_html_e( 'Room', 'ifsedu-sms' ); ?></th>
                            <th style="padding:10px 12px; text-align:right; border-bottom:2px solid #e2e8f0; font-size:11px; text-transform:uppercase; color:#475569;"><?php esc_html_e( 'Action', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $schedules ) ) : foreach ( $schedules as $s ) : 
                            $del_url = wp_nonce_url( add_query_arg( array( 'action' => 'delete_slot', 'slot_id' => $s->id ), $base_url ), 'delete_slot_' . $s->id );
                        ?>
                            <tr>
                                <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9;">
                                    <strong style="color:#0f172a;"><?php echo esc_html( date_i18n( 'd M Y', strtotime( $s->exam_date ) ) ); ?></strong><br>
                                    <small style="color:#64748b;"><?php echo esc_html( date( 'g:i A', strtotime( $s->start_time ) ) . ' - ' . date( 'g:i A', strtotime( $s->end_time ) ) ); ?></small>
                                </td>
                                <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9;">
                                    <span style="font-weight:700; color:#006a4e;"><?php echo esc_html( $s->exam_name ); ?></span>
                                </td>
                                <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9;">
                                    <span style="background:#eff6ff; color:#2563eb; padding:2px 8px; border-radius:10px; font-weight:700; font-size:11.5px;">
                                        <?php echo esc_html( $s->class_name . ( $s->section_name ? ' (' . $s->section_name . ')' : '' ) ); ?>
                                    </span>
                                </td>
                                <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9;">
                                    <strong><?php echo esc_html( $s->subject_name ); ?></strong>
                                </td>
                                <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9;">
                                    <code><?php echo esc_html( $s->room_no ? $s->room_no : 'N/A' ); ?></code>
                                </td>
                                <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9; text-align:right;">
                                    <a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove this routine slot?', 'ifsedu-sms' ) ); ?>');" style="color:#dc2626; text-decoration:none; padding:4px 8px; border:1px solid #fecaca; border-radius:6px; background:#fef2f2;">
                                        <span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr>
                                <td colspan="6" style="padding:24px; text-align:center; color:#94a3b8;">
                                    <?php esc_html_e( 'No examination routine slots found for selected filters.', 'ifsedu-sms' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- BOTTOM SECTION: INTERACTIVE ROUTINE PREVIEW & PRINT MATRIX -->
    <div class="dpt-card dpt-preview-card dpt-preview-wrapper">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:14px; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
            <div>
                <h3 style="margin:0 0 4px 0; font-size:18px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-visibility" style="color:#006a4e; font-size:22px; width:22px; height:22px;"></span>
                    <?php esc_html_e( 'Examination Routine Preview & Notice Board', 'ifsedu-sms' ); ?>
                </h3>
                <small style="color:#64748b; font-size:12px; font-weight:600;">
                    <?php echo esc_html( $school_name ); ?> — <?php esc_html_e( 'Chronological Timetable Overview', 'ifsedu-sms' ); ?>
                </small>
            </div>

            <button type="button" onclick="window.print();" class="dpt-btn-print no-print">
                <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Exam Routine', 'ifsedu-sms' ); ?>
            </button>
        </div>

        <?php if ( ! empty( $preview_by_date ) ) : ?>
            <div class="dpt-exam-timeline-grid">
                <?php foreach ( $preview_by_date as $date_str => $day_slots ) : 
                    $day_name = date_i18n( 'l', strtotime( $date_str ) );
                    $formatted_date = date_i18n( 'd M, Y', strtotime( $date_str ) );
                ?>
                    <div class="dpt-date-card">
                        <!-- Date Header -->
                        <div class="dpt-date-header">
                            <span><?php echo esc_html( $formatted_date ); ?></span>
                            <span style="font-size:11px; opacity:0.9; text-transform:uppercase; font-weight:700;"><?php echo esc_html( $day_name ); ?></span>
                        </div>

                        <!-- Slots under this Date -->
                        <div class="dpt-date-slots-body">
                            <?php foreach ( $day_slots as $slot_item ) : ?>
                                <div class="dpt-exam-slot-item">
                                    <div class="dpt-slot-badge-time">
                                        <span class="dashicons dashicons-clock" style="font-size:12px; width:12px; height:12px;"></span>
                                        <span><?php echo esc_html( date( 'g:i A', strtotime( $slot_item->start_time ) ) . ' - ' . date( 'g:i A', strtotime( $slot_item->end_time ) ) ); ?></span>
                                    </div>

                                    <div style="font-size:14px; font-weight:800; color:#0f172a; margin-bottom:4px;">
                                        <?php echo esc_html( $slot_item->subject_name ); ?>
                                        <?php if ( ! empty( $slot_item->subject_code ) ) : ?>
                                            <span style="font-size:11px; font-weight:600; color:#64748b;">(<?php echo esc_html( $slot_item->subject_code ); ?>)</span>
                                        <?php endif; ?>
                                    </div>

                                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; color:#475569; font-weight:600; margin-top:6px; padding-top:6px; border-top:1px dashed #cbd5e1;">
                                        <span>Class: <strong><?php echo esc_html( $slot_item->class_name . ( $slot_item->section_name ? ' (' . $slot_item->section_name . ')' : '' ) ); ?></strong></span>
                                        <span>Room: <strong><?php echo esc_html( $slot_item->room_no ? $slot_item->room_no : 'N/A' ); ?></strong></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div style="text-align:center; padding:40px 20px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:12px; color:#94a3b8;">
                <span class="dashicons dashicons-calendar-alt" style="font-size:32px; width:32px; height:32px; opacity:0.5; margin-bottom:8px;"></span>
                <p style="margin:0; font-weight:600; font-size:13.5px;"><?php esc_html_e( 'No examination routine slots configured yet for preview.', 'ifsedu-sms' ); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Client-Side Class-Subject Linker -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const classSelect = document.getElementById('er_class_select');
        const subjectSelect = document.getElementById('er_subject_select');
        if (!classSelect || !subjectSelect) return;

        const allOptions = Array.from(subjectSelect.querySelectorAll('option')).slice(1);

        classSelect.addEventListener('change', function() {
            const selectedClassId = this.value;
            subjectSelect.innerHTML = '<option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-sms' ); ?></option>';

            allOptions.forEach(opt => {
                if (!selectedClassId || opt.getAttribute('data-classid') === selectedClassId) {
                    subjectSelect.appendChild(opt.cloneNode(true));
                }
            });
        });
    });
    </script>
    <?php
}