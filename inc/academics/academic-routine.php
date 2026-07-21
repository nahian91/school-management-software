<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit; 
}

global $wpdb;
$table_routine  = $wpdb->prefix . 'sms_routine';
$table_units    = $wpdb->prefix . 'sms_academic_units';
$table_subjects = $wpdb->prefix . 'sms_subjects';

// Dynamic Base URL preservation from current URI without action state params
$current_uri = remove_query_arg( array( 'action', 'id', '_wpnonce', 'status' ), $_SERVER['REQUEST_URI'] );
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
    
    $class_id   = absint( $_POST['class_id'] );
    $subject_id = absint( $_POST['subject_id'] );
    $day_name   = sanitize_text_field( $_POST['day_name'] );
    $start_time = sanitize_text_field( $_POST['start_time'] );
    $end_time   = sanitize_text_field( $_POST['end_time'] );
    $room_no    = sanitize_text_field( $_POST['room_no'] );

    if ( $class_id > 0 && $subject_id > 0 && ! empty( $day_name ) ) {
        $inserted = $wpdb->insert( 
            $table_routine, 
            array(
                'class_id'   => $class_id,
                'subject_id' => $subject_id,
                'day_name'   => $day_name,
                'start_time' => $start_time,
                'end_time'   => $end_time,
                'room_no'    => $room_no,
            ), 
            array( '%d', '%d', '%s', '%s', '%s', '%s' )
        );

        if ( $inserted ) {
            if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                IFSEdu_School_Management_System::log_activity( "Added new class routine for {$day_name}" );
            }
            
            $redirect_target = add_query_arg( array( 'status' => 'success' ), $base_url );

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
}

// Fetch Classes with Natural Numeric Sorting
$classes = $wpdb->get_results( 
    "SELECT id, class_name, section_name FROM {$table_units} 
     ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" 
);

if ( ! empty( $classes ) ) {
    usort( $classes, function( $a, $b ) {
        $res = strnatcasecmp( $a->class_name, $b->class_name );
        if ( $res === 0 ) {
            return strnatcasecmp( $a->section_name, $b->section_name );
        }
        return $res;
    });
}

$subjects = $wpdb->get_results( "SELECT id, subject_name FROM {$table_subjects} ORDER BY subject_name ASC" );
$days     = array( 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' );
?>

<style>
    /* ==========================================================================
       ACADEMIC ROUTINE - NEO-BENTO ARCHITECTURE
       ========================================================================== */
    .dpt-routine-container {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        color: #0f172a;
        display: flex;
        flex-direction: column;
        gap: 24px;
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

    /* Form Layout System */
    .dpt-routine-form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr) repeat(2, 120px) 1fr 140px;
        gap: 12px;
        align-items: end;
    }

    @media (max-width: 1200px) {
        .dpt-routine-form-grid {
            grid-template-columns: repeat(3, 1fr);
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
        background: #f8fafc;
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
        background: #ffffff;
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

    /* Datatable UI Architecture */
    .dpt-count-pill {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
        font-size: 12px;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 20px;
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

    .dpt-square-btn .dashicons {
        font-size: 15px;
        width: 15px;
        height: 15px;
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
        margin-bottom: 16px;
    }
</style>

<div class="dpt-routine-container">

    <!-- Status Notifications -->
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
    <?php endif; ?>

    <!-- Add New Routine Bento Box -->
    <div class="dpt-bento-card">
        <div class="afdp-card-header">
            <h5 class="afdp-card-title">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php esc_html_e( 'Configure New Class Routine', 'ifsedu-sms' ); ?>
            </h5>
        </div>
        
        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'routine_action', 'routine_nonce' ); ?>
            
            <div class="dpt-routine-form-grid">
                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'Target Class', 'ifsedu-sms' ); ?></label>
                    <select name="class_id" class="dpt-field-select" required>
                        <option value=""><?php esc_html_e( 'Select Class...', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $classes as $c ) : 
                            $c_label = ! empty( $c->section_name ) ? $c->class_name . ' (' . $c->section_name . ')' : $c->class_name;
                        ?>
                            <option value="<?php echo esc_attr( $c->id ); ?>"><?php echo esc_html( $c_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'Academic Subject', 'ifsedu-sms' ); ?></label>
                    <select name="subject_id" class="dpt-field-select" required>
                        <option value=""><?php esc_html_e( 'Select Subject...', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $subjects as $s ) : ?>
                            <option value="<?php echo esc_attr( $s->id ); ?>"><?php echo esc_html( $s->subject_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'Day', 'ifsedu-sms' ); ?></label>
                    <select name="day_name" class="dpt-field-select" required>
                        <?php foreach ( $days as $d ) : ?>
                            <option value="<?php echo esc_attr( $d ); ?>"><?php echo esc_html( $d ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'Start Time', 'ifsedu-sms' ); ?></label>
                    <input type="time" name="start_time" class="dpt-field-input" required>
                </div>

                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'End Time', 'ifsedu-sms' ); ?></label>
                    <input type="time" name="end_time" class="dpt-field-input" required>
                </div>

                <div class="dpt-input-wrapper">
                    <label class="dpt-form-label"><?php esc_html_e( 'Room No', 'ifsedu-sms' ); ?></label>
                    <input type="text" name="room_no" class="dpt-field-input" placeholder="e.g. 101">
                </div>

                <div class="dpt-input-wrapper">
                    <button type="submit" name="save_routine" class="dpt-btn-save">
                        <span class="dashicons dashicons-plus-alt2" style="font-size:16px; width:16px; height:16px;"></span>
                        <?php esc_html_e( 'Save Slot', 'ifsedu-sms' ); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Weekly Routine Directory Table -->
    <div class="dpt-bento-card">
        <div class="afdp-card-header">
            <h5 class="afdp-card-title">
                <span class="dashicons dashicons-clock"></span>
                <?php esc_html_e( 'Weekly Class Schedule Directory', 'ifsedu-sms' ); ?>
            </h5>
            <span class="dpt-count-pill">
                <?php echo esc_html( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_routine}" ) ); ?> <?php esc_html_e( 'Slots Configured', 'ifsedu-sms' ); ?>
            </span>
        </div>

        <div class="dpt-responsive-datatable">
            <table class="dpt-architecture-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Day', 'ifsedu-sms' ); ?></th>
                        <th><?php esc_html_e( 'Class', 'ifsedu-sms' ); ?></th>
                        <th><?php esc_html_e( 'Subject', 'ifsedu-sms' ); ?></th>
                        <th><?php esc_html_e( 'Timeline', 'ifsedu-sms' ); ?></th>
                        <th><?php esc_html_e( 'Room No', 'ifsedu-sms' ); ?></th>
                        <th style="text-align: right; width: 60px;"><?php esc_html_e( 'Action', 'ifsedu-sms' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $routines = $wpdb->get_results( "SELECT r.*, u.class_name, u.section_name, s.subject_name FROM {$table_routine} r 
                                                   JOIN {$table_units} u ON r.class_id = u.id 
                                                   JOIN {$table_subjects} s ON r.subject_id = s.id 
                                                   ORDER BY FIELD(day_name, 'Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'), CAST(u.class_name AS UNSIGNED) ASC, r.start_time ASC" );
                    
                    if ( ! empty( $routines ) ) : 
                        foreach ( $routines as $r ) : 
                            $delete_url = wp_nonce_url( 
                                add_query_arg( array( 'action' => 'delete_routine', 'id' => $r->id ), $base_url ), 
                                'delete_routine_' . $r->id 
                            );

                            $class_display = $r->class_name;
                            if ( ! empty( $r->section_name ) ) {
                                $class_display .= ' (' . $r->section_name . ')';
                            }
                    ?>
                    <tr>
                        <td style="font-weight: 800; color: #006a4e;"><?php echo esc_html( $r->day_name ); ?></td>
                        <td style="font-weight: 600; color: #0f172a;"><?php echo esc_html( $class_display ); ?></td>
                        <td style="font-weight: 600; color: #334155;"><?php echo esc_html( $r->subject_name ); ?></td>
                        <td>
                            <span class="dpt-timeline-badge">
                                <span class="dashicons dashicons-clock" style="font-size:12px; width:12px; height:12px;"></span>
                                <?php echo esc_html( date( 'g:i A', strtotime( $r->start_time ) ) . ' - ' . date( 'g:i A', strtotime( $r->end_time ) ) ); ?>
                            </span>
                        </td>
                        <td><span class="dpt-room-code"><?php echo esc_html( $r->room_no ? $r->room_no : 'N/A' ); ?></span></td>
                        <td style="text-align: right;">
                            <a href="<?php echo esc_url( $delete_url ); ?>" class="dpt-square-btn" title="<?php esc_attr_e( 'Delete Routine Slot', 'ifsedu-sms' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this routine slot?', 'ifsedu-sms' ) ); ?>');">
                                <span class="dashicons dashicons-trash"></span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; else : ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                            <?php esc_html_e( 'No routine slots configured yet.', 'ifsedu-sms' ); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>