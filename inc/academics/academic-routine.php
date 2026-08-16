<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit; 
}

global $wpdb;
$table_routine          = $wpdb->prefix . 'sms_routine';
$table_units            = $wpdb->prefix . 'sms_academic_units';
$table_subjects         = $wpdb->prefix . 'sms_subjects';
$table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';
$table_staff            = $wpdb->prefix . 'sms_staff';

// Ensure shift column exists in sms_routine table
$column_check = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_routine}` LIKE 'shift'" );
if ( empty( $column_check ) ) {
    $wpdb->query( "ALTER TABLE `{$table_routine}` ADD `shift` varchar(50) DEFAULT 'No Shift' NOT NULL AFTER `day_name`" );
}

// Dynamic Base URL preservation
$current_uri = remove_query_arg( array( 'action', 'id', '_wpnonce', 'status', 'msg' ), $_SERVER['REQUEST_URI'] );
$base_url    = esc_url_raw( $current_uri );

// Handle Routine Deletion
if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_routine' && isset( $_GET['id'] ) ) {
    $delete_id = absint( $_GET['id'] );
    $_nonce    = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';

    if ( $delete_id > 0 && wp_verify_nonce( $_nonce, 'delete_routine_' . $delete_id ) ) {
        $wpdb->delete( $table_routine, array( 'id' => $delete_id ), array( '%d' ) );
        
        if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
            IFSEdu_School_Management_System::log_activity( "Deleted class routine slot ID #{$delete_id}" );
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

// Handle Routine Submission
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_routine'] ) ) {
    check_admin_referer( 'routine_action', 'routine_nonce' );
    
    $class_unit_id  = isset( $_POST['section_id'] ) ? absint( $_POST['section_id'] ) : 0;
    $class_name_val = isset( $_POST['class_id'] ) ? sanitize_text_field( wp_unslash( $_POST['class_id'] ) ) : '';
    $subject_id     = isset( $_POST['subject_id'] ) ? absint( $_POST['subject_id'] ) : 0;
    $day_name       = isset( $_POST['day_name'] ) ? sanitize_text_field( wp_unslash( $_POST['day_name'] ) ) : '';
    $shift          = isset( $_POST['shift'] ) ? sanitize_text_field( wp_unslash( $_POST['shift'] ) ) : 'No Shift';
    
    // Time formatting for MySQL
    $raw_start      = isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '';
    $raw_end        = isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '';
    $start_time     = $raw_start ? date( 'H:i:s', strtotime( $raw_start ) ) : '00:00:00';
    $end_time       = $raw_end ? date( 'H:i:s', strtotime( $raw_end ) ) : '00:00:00';

    $room_no        = isset( $_POST['room_no'] ) ? sanitize_text_field( wp_unslash( $_POST['room_no'] ) ) : '';

    $final_class_id = $class_unit_id > 0 ? $class_unit_id : 0;

    // Fallback if no specific section unit was chosen
    if ( $final_class_id === 0 && ! empty( $class_name_val ) ) {
        $unit_match = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table_units} WHERE class_name = %s LIMIT 1", $class_name_val ) );
        if ( $unit_match ) {
            $final_class_id = $unit_match->id;
        }
    }

    if ( $final_class_id > 0 && $subject_id > 0 && ! empty( $day_name ) ) {
        $inserted = $wpdb->insert( 
            $table_routine, 
            array(
                'class_id'   => $final_class_id,
                'subject_id' => $subject_id,
                'day_name'   => $day_name,
                'shift'      => $shift,
                'start_time' => $start_time,
                'end_time'   => $end_time,
                'room_no'    => $room_no,
            ), 
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $inserted ) {
            if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                IFSEdu_School_Management_System::log_activity( "Added new class routine slot for {$day_name} ({$shift})" );
            }
            
            $clean_url = remove_query_arg( array( 'filter_class', 'filter_section', 'filter_shift' ), $base_url );
            $redirect_target = add_query_arg( array( 'status' => 'success' ), $clean_url );

            if ( function_exists( 'educore_safe_redirect_helper' ) ) {
                educore_safe_redirect_helper( $redirect_target );
            } elseif ( function_exists( 'educore_safe_redirect' ) ) {
                educore_safe_redirect( $redirect_target );
            } else {
                echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_target ) . '";</script>';
            }
            exit;
        } else {
            $db_error = $wpdb->last_error;
            $redirect_target = add_query_arg( array( 'status' => 'db_error', 'msg' => urlencode( $db_error ) ), $base_url );
            echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_target ) . '";</script>';
            exit;
        }
    } else {
        $redirect_target = add_query_arg( array( 'status' => 'validation_error' ), $base_url );
        echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_target ) . '";</script>';
        exit;
    }
}

// 1. Fetch Distinct Classes from Class Setup (sms_academic_units)
$raw_classes = $wpdb->get_col( 
    "SELECT DISTINCT class_name FROM {$table_units} 
     WHERE class_name IS NOT NULL AND class_name != '' 
     ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" 
);

$classes = array();
if ( ! empty( $raw_classes ) ) {
    $classes = array_values( array_unique( $raw_classes ) );
    usort( $classes, 'strnatcasecmp' );
}

// 2. Fetch All Academic Units (Classes & Sections) from Class Setup
$all_units = $wpdb->get_results( 
    "SELECT id, class_name, section_name, dept_name 
     FROM {$table_units} 
     WHERE class_name != '' 
     ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" 
);

// 3. Fetch Subjects mapped with Class/Unit Setup
$subjects = $wpdb->get_results( "
    SELECT s.id, s.subject_name, s.subject_code, s.class_id, u.class_name, u.section_name 
    FROM {$table_subjects} s 
    LEFT JOIN {$table_units} u ON s.class_id = u.id 
    ORDER BY s.subject_name ASC
" );

$days = array( 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' );

// Preview Filter Params
$filter_class = isset( $_GET['filter_class'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_class'] ) ) : '';
$filter_sec   = isset( $_GET['filter_section'] ) ? absint( $_GET['filter_section'] ) : 0;
$filter_shift = isset( $_GET['filter_shift'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_shift'] ) ) : '';

// Fetch Routines with Assigned Teacher Resolution
$query = "SELECT r.*, u.class_name, u.section_name, s.subject_name, st.full_name as teacher_name, st.designation 
          FROM {$table_routine} r 
          LEFT JOIN {$table_units} u ON r.class_id = u.id 
          LEFT JOIN {$table_subjects} s ON r.subject_id = s.id
          LEFT JOIN {$table_teacher_subjects} ts ON (ts.subject_id = r.subject_id AND ts.class_id = r.class_id)
          LEFT JOIN {$table_staff} st ON ts.teacher_id = st.id";

$where = array();
if ( ! empty( $filter_class ) ) {
    $where[] = $wpdb->prepare( "u.class_name = %s", $filter_class );
}
if ( $filter_sec > 0 ) {
    $where[] = $wpdb->prepare( "r.class_id = %d", $filter_sec );
}
if ( ! empty( $filter_shift ) ) {
    $where[] = $wpdb->prepare( "r.shift = %s", $filter_shift );
}

if ( ! empty( $where ) ) {
    $query .= " WHERE " . implode( " AND ", $where );
}

$query .= " ORDER BY FIELD(r.day_name, 'Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'), CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, r.start_time ASC";

$routines = $wpdb->get_results( $query );

if ( ! empty( $routines ) ) {
    usort( $routines, function( $a, $b ) use ( $days ) {
        $dayIndexA = array_search( $a->day_name, $days, true );
        $dayIndexB = array_search( $b->day_name, $days, true );

        if ( $dayIndexA !== $dayIndexB ) {
            return $dayIndexA <=> $dayIndexB;
        }

        $classRes = strnatcasecmp( $a->class_name ?? '', $b->class_name ?? '' );
        if ( $classRes !== 0 ) {
            return $classRes;
        }

        return strcmp( $a->start_time, $b->start_time );
    });
}

// Map routines by Day for weekly matrix preview
$matrix_routine = array();
if ( ! empty( $routines ) ) {
    foreach ( $routines as $rt ) {
        $matrix_routine[$rt->day_name][] = $rt;
    }
}
?>

<style>
    .dpt-routine-container {
        font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #0f172a;
        display: flex;
        flex-direction: column;
        gap: 24px;
        margin-top: 15px;
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

    .dpt-filter-bar {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
        background: #f8fafc;
        padding: 12px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
    }

    .dpt-routine-form-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr) 130px repeat(2, 110px) 1fr 130px;
        gap: 12px;
        align-items: end;
    }

    @media (max-width: 1500px) {
        .dpt-routine-form-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    @media (max-width: 768px) {
        .dpt-routine-form-grid {
            grid-template-columns: 1fr;
        }
    }

    .dpt-input-wrapper {
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
        padding: 9px 12px;
        background: #ffffff;
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
        box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
    }

    .dpt-btn-save {
        width: 100%;
        padding: 10px;
        background: #006a4e;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        font-size: 13.5px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        box-shadow: 0 2px 8px rgba(0, 106, 78, 0.2);
    }

    .dpt-btn-save:hover {
        background: #00523c;
        transform: translateY(-1px);
    }

    .dpt-btn-secondary {
        padding: 9px 16px;
        background: #0284c7;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .dpt-count-pill {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
        font-size: 12px;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 20px;
    }

    /* Timetable Preview Matrix */
    .dpt-weekly-matrix {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
    }

    .dpt-day-column {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }

    .dpt-day-header {
        background: #006a4e;
        color: #ffffff;
        padding: 10px 14px;
        font-weight: 800;
        font-size: 14px;
        text-align: center;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    .dpt-day-slots {
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        min-height: 100px;
    }

    .dpt-slot-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-left: 4px solid #0284c7;
        border-radius: 8px;
        padding: 10px 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        position: relative;
    }

    .dpt-slot-time {
        font-size: 11px;
        font-weight: 800;
        color: #0369a1;
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 4px;
    }

    .dpt-slot-subject {
        font-size: 13.5px;
        font-weight: 700;
        color: #0f172a;
    }

    .dpt-slot-meta {
        font-size: 11.5px;
        color: #64748b;
        margin-top: 4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .dpt-shift-badge {
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
    }

    .dpt-shift-morning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .dpt-shift-day     { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
    .dpt-shift-none    { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

    .dpt-slot-teacher {
        font-size: 11px;
        font-weight: 700;
        color: #006a4e;
        margin-top: 5px;
        padding-top: 4px;
        border-top: 1px dashed #e2e8f0;
        display: flex;
        align-items: center;
        gap: 4px;
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

    .dpt-section-badge {
        display: inline-block;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
    }

    .dpt-timeline-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #f0f9ff;
        color: #0369a1;
        border: 1px solid #bae6fd;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
    }

    .dpt-room-code {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        padding: 2px 8px;
        border-radius: 6px;
        font-family: monospace;
        font-size: 12px;
        font-weight: 700;
    }

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
    
    .afdp-alert-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #dc2626;
        padding: 12px 16px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 13.5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    @media print {
        #wpadminbar, #adminmenumain, #adminmenuback, #adminmenuwrap, #wpfooter, .no-print {
            display: none !important;
        }
        #wpcontent { margin-left: 0 !important; padding: 0 !important; }
        .dpt-bento-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
        .dpt-weekly-matrix { grid-template-columns: repeat(7, 1fr) !important; gap: 4px !important; }
        .dpt-day-column { border: 1px solid #000 !important; }
        .dpt-day-header { background: #000 !important; color: #fff !important; font-size: 10px !important; padding: 4px !important; }
        .dpt-slot-card { border: 1px solid #666 !important; padding: 4px !important; }
        .dpt-slot-subject { font-size: 10px !important; }
        .dpt-slot-time, .dpt-slot-meta, .dpt-slot-teacher { font-size: 8px !important; }
    }
</style>

<div class="dpt-routine-container">

    <!-- Notifications -->
    <?php if ( isset( $_GET['status'] ) && $_GET['status'] === 'success' ) : ?>
        <div class="afdp-alert-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e( 'New class routine slot saved successfully.', 'ifsedu-sms' ); ?>
        </div>
    <?php elseif ( isset( $_GET['status'] ) && $_GET['status'] === 'deleted' ) : ?>
        <div class="afdp-alert-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e( 'Routine slot deleted successfully.', 'ifsedu-sms' ); ?>
        </div>
    <?php elseif ( isset( $_GET['status'] ) && $_GET['status'] === 'db_error' ) : ?>
        <div class="afdp-alert-error">
            <span class="dashicons dashicons-warning"></span>
            <div>
                <strong><?php esc_html_e( 'Database Error:', 'ifsedu-sms' ); ?></strong>
                <?php esc_html_e( 'Could not save the routine slot.', 'ifsedu-sms' ); ?>
                <?php if ( ! empty( $_GET['msg'] ) ) : ?>
                    <br><span style="font-size: 12px; opacity: 0.8; font-family: monospace;"><?php echo esc_html( urldecode( $_GET['msg'] ) ); ?></span>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ( isset( $_GET['status'] ) && $_GET['status'] === 'validation_error' ) : ?>
        <div class="afdp-alert-error">
            <span class="dashicons dashicons-warning"></span>
            <?php esc_html_e( 'Validation Error: Please ensure you have selected a Target Class and an Academic Subject.', 'ifsedu-sms' ); ?>
        </div>
    <?php endif; ?>

    <!-- 1. Add New Routine Bento Box (Loaded from Class Setup) -->
    <div class="dpt-bento-card no-print">
        <div class="afdp-card-header">
            <h5 class="afdp-card-title">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php esc_html_e( 'Configure New Class Routine Slot', 'ifsedu-sms' ); ?>
            </h5>
            <span style="font-size:12px; font-weight:700; color:#006a4e; background:#ecfdf5; padding:4px 12px; border-radius:20px; border:1px solid #a7f3d0;">
                <span class="dashicons dashicons-admin-settings" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                <?php esc_html_e( 'Class Setup Linked', 'ifsedu-sms' ); ?>
            </span>
        </div>
        
        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'routine_action', 'routine_nonce' ); ?>
            
            <div class="dpt-routine-form-grid">
                <!-- Target Class (Loaded dynamically from sms_academic_units) -->
                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'Target Class', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="class_id" id="dpt_class_select" class="dpt-field-select" required>
                        <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $classes as $cls_name ) : ?>
                            <option value="<?php echo esc_attr( $cls_name ); ?>"><?php echo esc_html( $cls_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Section Selection (Populated dynamically from sms_academic_units) -->
                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'Section', 'ifsedu-sms' ); ?></label>
                    <select name="section_id" id="dpt_section_select" class="dpt-field-select">
                        <option value=""><?php esc_html_e( '-- Choose Section --', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>

                <!-- Academic Shift -->
                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'Shift', 'ifsedu-sms' ); ?></label>
                    <select name="shift" class="dpt-field-select">
                        <option value="No Shift"><?php esc_html_e( 'No Shift', 'ifsedu-sms' ); ?></option>
                        <option value="Morning Shift"><?php esc_html_e( 'Morning Shift', 'ifsedu-sms' ); ?></option>
                        <option value="Day Shift"><?php esc_html_e( 'Day Shift', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>

                <!-- Subject Selection (Populated dynamically based on Class/Unit) -->
                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'Academic Subject', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="subject_id" id="dpt_subject_select" class="dpt-field-select" required>
                        <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>

                <!-- Day Selection -->
                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'Day', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="day_name" class="dpt-field-select" required>
                        <?php foreach ( $days as $d ) : ?>
                            <option value="<?php echo esc_attr( $d ); ?>"><?php echo esc_html( $d ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Start Time -->
                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'Start Time', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="time" name="start_time" class="dpt-field-input" required>
                </div>

                <!-- End Time -->
                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'End Time', 'ifsedu-sms' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="time" name="end_time" class="dpt-field-input" required>
                </div>

                <!-- Room No -->
                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'Room No', 'ifsedu-sms' ); ?></label>
                    <input type="text" name="room_no" class="dpt-field-input" placeholder="e.g. 101">
                </div>

                <!-- Submit Button -->
                <div class="dpt-input-wrapper">
                    <button type="submit" name="save_routine" class="dpt-btn-save">
                        <span class="dashicons dashicons-plus-alt2" style="font-size:16px; width:16px; height:16px;"></span>
                        <?php esc_html_e( 'Save Slot', 'ifsedu-sms' ); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- 2. Interactive Weekly Matrix Preview -->
    <div class="dpt-bento-card">
        <div class="afdp-card-header">
            <h5 class="afdp-card-title">
                <span class="dashicons dashicons-visibility"></span>
                <?php esc_html_e( 'Weekly Timetable Preview', 'ifsedu-sms' ); ?>
            </h5>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="" class="dpt-filter-bar no-print">
            <?php 
                foreach ( $_GET as $key => $val ) {
                    if ( ! in_array( $key, array( 'filter_class', 'filter_section', 'filter_shift' ), true ) ) {
                        echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '">';
                    }
                }
            ?>
            <div style="font-weight: 700; font-size: 13px; color: #475569;"><?php esc_html_e( 'Filter Schedule:', 'ifsedu-sms' ); ?></div>
            
            <!-- Filter Class (Loaded from Class Setup) -->
            <select name="filter_class" id="dpt_filter_class" class="dpt-field-select" style="width: auto; height: 36px;">
                <option value=""><?php esc_html_e( '-- All Classes --', 'ifsedu-sms' ); ?></option>
                <?php foreach ( $classes as $cls_name ) : ?>
                    <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                        <?php echo esc_html( $cls_name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Filter Section (Loaded from Class Setup) -->
            <select name="filter_section" id="dpt_filter_section" class="dpt-field-select" style="width: auto; height: 36px;">
                <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-sms' ); ?></option>
            </select>

            <!-- Filter Shift -->
            <select name="filter_shift" class="dpt-field-select" style="width: auto; height: 36px;">
                <option value=""><?php esc_html_e( '-- All Shifts --', 'ifsedu-sms' ); ?></option>
                <option value="No Shift" <?php selected( $filter_shift, 'No Shift' ); ?>><?php esc_html_e( 'No Shift', 'ifsedu-sms' ); ?></option>
                <option value="Morning Shift" <?php selected( $filter_shift, 'Morning Shift' ); ?>><?php esc_html_e( 'Morning Shift', 'ifsedu-sms' ); ?></option>
                <option value="Day Shift" <?php selected( $filter_shift, 'Day Shift' ); ?>><?php esc_html_e( 'Day Shift', 'ifsedu-sms' ); ?></option>
            </select>

            <button type="submit" class="dpt-btn-secondary" style="height: 36px; padding: 0 14px;">
                <span class="dashicons dashicons-filter" style="font-size:14px; width:14px; height:14px;"></span>
                <?php esc_html_e( 'Apply Filter', 'ifsedu-sms' ); ?>
            </button>

            <?php if ( ! empty( $filter_class ) || $filter_sec > 0 || ! empty( $filter_shift ) ) : ?>
                <a href="<?php echo esc_url( $base_url ); ?>" class="dpt-square-btn" style="width: auto; padding: 0 10px; height: 34px;" title="<?php esc_attr_e( 'Clear Filters', 'ifsedu-sms' ); ?>">
                    <?php esc_html_e( 'Reset Filter', 'ifsedu-sms' ); ?>
                </a>
            <?php endif; ?>
        </form>

        <!-- Weekly Matrix Layout Grid -->
        <div class="dpt-weekly-matrix">
            <?php foreach ( $days as $day ) : ?>
                <div class="dpt-day-column">
                    <div class="dpt-day-header"><?php echo esc_html( $day ); ?></div>
                    <div class="dpt-day-slots">
                        <?php if ( ! empty( $matrix_routine[$day] ) ) : ?>
                            <?php foreach ( $matrix_routine[$day] as $slot ) : 
                                $shift_val = ! empty( $slot->shift ) ? $slot->shift : 'No Shift';
                                $shift_badge_class = 'dpt-shift-none';
                                if ( $shift_val === 'Morning Shift' ) {
                                    $shift_badge_class = 'dpt-shift-morning';
                                } elseif ( $shift_val === 'Day Shift' ) {
                                    $shift_badge_class = 'dpt-shift-day';
                                }
                            ?>
                                <div class="dpt-slot-card">
                                    <div class="dpt-slot-time">
                                        <span class="dashicons dashicons-clock" style="font-size:11px; width:11px; height:11px;"></span>
                                        <span><?php echo esc_html( date( 'g:i A', strtotime( $slot->start_time ) ) . ' - ' . date( 'g:i A', strtotime( $slot->end_time ) ) ); ?></span>
                                    </div>
                                    <div class="dpt-slot-subject"><?php echo esc_html( $slot->subject_name ); ?></div>
                                    <div class="dpt-slot-meta">
                                        <span>
                                            Cls: <strong><?php echo esc_html( $slot->class_name ); ?></strong> <?php echo ! empty( $slot->section_name ) ? '(' . esc_html( $slot->section_name ) . ')' : ''; ?>
                                            <?php if ( $shift_val !== 'No Shift' ) : ?>
                                                <span class="dpt-shift-badge <?php echo esc_attr( $shift_badge_class ); ?>"><?php echo esc_html( $shift_val ); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span>Rm: <strong><?php echo esc_html( $slot->room_no ? $slot->room_no : 'N/A' ); ?></strong></span>
                                    </div>
                                    <?php if ( ! empty( $slot->teacher_name ) ) : ?>
                                        <div class="dpt-slot-teacher">
                                            <span class="dashicons dashicons-businessman" style="font-size:12px; width:12px; height:12px;"></span>
                                            <span><?php echo esc_html( $slot->teacher_name ); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div style="text-align: center; color: #cbd5e1; font-size: 12px; padding: 20px 0; font-weight: 600;">
                                <?php esc_html_e( 'No Classes', 'ifsedu-sms' ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- Dynamic Script for Class Setup Cascading Resolution -->
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    const unitsMap = <?php echo json_encode( $all_units ); ?>;
    const subjectsMap = <?php echo json_encode( $subjects ); ?>;
    const currentFilterSection = <?php echo json_encode( $filter_sec ); ?>;

    // Populate dynamic sections strictly from Class Setup (sms_academic_units)
    function populateSections(classSelectElem, sectionSelectElem, selectedSecId = '') {
        const selectedClass = classSelectElem.value;
        sectionSelectElem.innerHTML = '<option value=""><?php esc_html_e( '-- Choose Section --', 'ifsedu-sms' ); ?></option>';

        if (!selectedClass) return;

        const filtered = unitsMap.filter(item => item.class_name === selectedClass);

        filtered.forEach(unit => {
            if (unit.section_name) {
                const opt = document.createElement('option');
                opt.value = unit.id;
                opt.textContent = unit.section_name;
                if (String(unit.id) === String(selectedSecId)) {
                    opt.selected = true;
                }
                sectionSelectElem.appendChild(opt);
            }
        });
    }

    // Populate dynamic subjects based on Class and Section Setup
    function populateSubjects(classSelectElem, sectionSelectElem, subjectSelectElem) {
        const selectedClass = classSelectElem.value;
        const selectedUnitId = sectionSelectElem ? sectionSelectElem.value : '';

        subjectSelectElem.innerHTML = '<option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-sms' ); ?></option>';

        if (!selectedClass) return;

        let filteredSubjects = [];

        if (selectedUnitId) {
            filteredSubjects = subjectsMap.filter(item => String(item.class_id) === String(selectedUnitId));
            if (filteredSubjects.length === 0) {
                filteredSubjects = subjectsMap.filter(item => item.class_name === selectedClass);
            }
        } else {
            filteredSubjects = subjectsMap.filter(item => item.class_name === selectedClass);
        }

        const seen = new Set();
        filteredSubjects.forEach(subject => {
            if (!seen.has(subject.id)) {
                seen.add(subject.id);
                const opt = document.createElement('option');
                opt.value = subject.id;
                opt.textContent = subject.subject_name + (subject.subject_code ? ' [' + subject.subject_code + ']' : '');
                subjectSelectElem.appendChild(opt);
            }
        });
    }

    // 1. Creation Form Setup
    const formClassSelect = document.getElementById('dpt_class_select');
    const formSecSelect   = document.getElementById('dpt_section_select');
    const formSubjectSelect = document.getElementById('dpt_subject_select');
    
    if (formClassSelect && formSecSelect && formSubjectSelect) {
        formClassSelect.addEventListener('change', function() {
            populateSections(formClassSelect, formSecSelect);
            populateSubjects(formClassSelect, formSecSelect, formSubjectSelect);
        });

        formSecSelect.addEventListener('change', function() {
            populateSubjects(formClassSelect, formSecSelect, formSubjectSelect);
        });
    }

    // 2. Filter Bar Setup
    const filterClassSelect = document.getElementById('dpt_filter_class');
    const filterSecSelect   = document.getElementById('dpt_filter_section');
    if (filterClassSelect && filterSecSelect) {
        populateSections(filterClassSelect, filterSecSelect, currentFilterSection);

        filterClassSelect.addEventListener('change', function() {
            populateSections(filterClassSelect, filterSecSelect);
        });
    }
});
</script>