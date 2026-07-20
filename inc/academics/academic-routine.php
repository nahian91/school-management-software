<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$table_routine  = $wpdb->prefix . 'sms_routine';
$table_units    = $wpdb->prefix . 'sms_academic_units';
$table_subjects = $wpdb->prefix . 'sms_subjects';

// Handle Routine Submission
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_routine'] ) ) {
    check_admin_referer( 'routine_action', 'routine_nonce' );
    
    $wpdb->insert( $table_routine, array(
        'class_id'   => intval($_POST['class_id']),
        'subject_id' => intval($_POST['subject_id']),
        'day_name'   => sanitize_text_field($_POST['day_name']),
        'start_time' => sanitize_text_field($_POST['start_time']),
        'end_time'   => sanitize_text_field($_POST['end_time']),
        'room_no'    => sanitize_text_field($_POST['room_no']),
    ), array('%d', '%d', '%s', '%s', '%s', '%s'));

    if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
        IFSEdu_School_Management_System::log_activity( "Added new class routine for " . sanitize_text_field($_POST['day_name']) );
    }
}

$classes  = $wpdb->get_results("SELECT id, class_name FROM {$table_units}");
$subjects = $wpdb->get_results("SELECT id, subject_name FROM {$table_subjects}");
$days     = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>

<div class="dpt-bento-box">
    <h5 class="dpt-bento-subheading">
        <span class="dashicons dashicons-calendar-alt" style="margin-right:8px; vertical-align:middle;"></span>Add New Routine
    </h5>
    
    <form method="POST">
        <?php wp_nonce_field( 'routine_action', 'routine_nonce' ); ?>
        
        <div class="dpt-field-matrix-grid" style="align-items: flex-end;">
            <div class="dpt-input-wrapper">
                <label class="dpt-form-label">Class</label>
                <select name="class_id" class="dpt-field-select" required>
                    <?php foreach($classes as $c) echo "<option value='$c->id'>$c->class_name</option>"; ?>
                </select>
            </div>
            <div class="dpt-input-wrapper">
                <label class="dpt-form-label">Subject</label>
                <select name="subject_id" class="dpt-field-select" required>
                    <?php foreach($subjects as $s) echo "<option value='$s->id'>$s->subject_name</option>"; ?>
                </select>
            </div>
            <div class="dpt-input-wrapper">
                <label class="dpt-form-label">Day</label>
                <select name="day_name" class="dpt-field-select">
                    <?php foreach($days as $d) echo "<option value='$d'>$d</option>"; ?>
                </select>
            </div>
            <div class="dpt-input-wrapper">
                <label class="dpt-form-label">Start Time</label>
                <input type="time" name="start_time" class="dpt-field-input" required>
            </div>
            <div class="dpt-input-wrapper">
                <label class="dpt-form-label">End Time</label>
                <input type="time" name="end_time" class="dpt-field-input" required>
            </div>
            <div class="dpt-input-wrapper">
                <label class="dpt-form-label">Room No</label>
                <input type="text" name="room_no" class="dpt-field-input" placeholder="e.g. 101">
            </div>
            <div class="dpt-input-wrapper" style="max-width: 120px;">
                <button type="submit" name="save_routine" class="dpt-btn-action-trigger" style="width:100%;">Save</button>
            </div>
        </div>
    </form>
</div>

<div class="dpt-bento-box">
    <div class="afdp-header-bar">
        <h5 class="dpt-bento-subheading" style="margin-bottom:0;">Weekly Class Routine</h5>
        <span class="dpt-count-pill"><?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$table_routine}"); ?> Slots Configured</span>
    </div>

    <div class="dpt-responsive-datatable">
        <table class="dpt-architecture-table">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Timeline</th>
                    <th>Room</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $routines = $wpdb->get_results("SELECT r.*, u.class_name, s.subject_name FROM {$table_routine} r 
                                               JOIN {$table_units} u ON r.class_id = u.id 
                                               JOIN {$table_subjects} s ON r.subject_id = s.id 
                                               ORDER BY FIELD(day_name, 'Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'), start_time ASC");
                
                if($routines): foreach($routines as $r): ?>
                <tr>
                    <td style="font-weight: 700; color: #006a4e;"><?php echo esc_html($r->day_name); ?></td>
                    <td><?php echo esc_html($r->class_name); ?></td>
                    <td style="font-weight: 600;"><?php echo esc_html($r->subject_name); ?></td>
                    <td>
                        <span class="dpt-tier-badge dpt-badge-school">
                            <?php echo date('h:i A', strtotime($r->start_time)) . ' - ' . date('h:i A', strtotime($r->end_time)); ?>
                        </span>
                    </td>
                    <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?php echo esc_html($r->room_no); ?></code></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" style="text-align:center; padding:30px; color:#64748b;">No routine data configured.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>