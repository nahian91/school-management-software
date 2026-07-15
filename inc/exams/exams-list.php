<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function educore_exams_list_view() {
    global $wpdb;
    $table_exams = $wpdb->prefix . 'sms_exams';
    
    // Save New Exam
    if ( isset( $_POST['educore_add_exam'] ) && wp_verify_nonce( $_POST['educore_exam_nonce'], 'add_exam_action' ) ) {
        $data = array(
            'exam_name'  => sanitize_text_field( $_POST['exam_name'] ),
            'class_name' => sanitize_text_field( $_POST['class_name'] ),
            'start_date' => sanitize_text_field( $_POST['start_date'] ),
            'end_date'   => sanitize_text_field( $_POST['end_date'] ),
            'status'     => sanitize_text_field( $_POST['status'] )
        );
        $wpdb->insert( $table_exams, $data );
        echo '<div class="alert alert-success">New exam created successfully.</div>';
    }

    // Delete Exam
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
        if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_exam_' . $_GET['id'] ) ) {
            $wpdb->delete( $table_exams, array( 'id' => intval( $_GET['id'] ) ) );
            echo '<div class="alert alert-success">Exam deleted successfully.</div>';
        }
    }

    $exams = $wpdb->get_results( "SELECT * FROM $table_exams ORDER BY id DESC" );
    $saved_classes = get_option( 'educore_classes', array() );
    $marks_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=marks' );
    ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><span class="dashicons dashicons-welcome-write-blog"></span> Exams & Results Setup</h2>
        <a href="<?php echo esc_url( $marks_url ); ?>" class="btn btn-primary" style="background-color: #3b82f6; border: none;">
            Enter Marks &rarr;
        </a>
    </div>

    <div class="row">
        <!-- Add Exam Form -->
        <div class="col-md-4 mb-4">
            <div class="bg-white p-4 rounded shadow-sm border">
                <h4 class="border-bottom pb-2 mb-3 text-primary">Create New Exam</h4>
                <form method="POST" action="">
                    <?php wp_nonce_field( 'add_exam_action', 'educore_exam_nonce' ); ?>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Exam Name</label>
                        <input type="text" name="exam_name" class="form-control" placeholder="e.g. Half-Yearly Exam" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Class</label>
                        <select name="class_name" class="form-control" required>
                            <option value="All Classes">All Classes</option>
                            <?php foreach ( $saved_classes as $cls ) : ?>
                                <option value="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $cls ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">End Date</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Status</label>
                        <select name="status" class="form-control">
                            <option value="Upcoming">Upcoming</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <button type="submit" name="educore_add_exam" class="btn btn-success w-100" style="background-color: #10b981; border: none;">Save Exam</button>
                </form>
            </div>
        </div>

        <!-- Exam List Table -->
        <div class="col-md-8 mb-4">
            <div class="bg-white p-4 rounded shadow-sm border">
                <table class="table table-striped table-hover educore-datatable">
                    <thead style="background-color: #f8fafc;">
                        <tr>
                            <th>Exam Name</th>
                            <th>Class</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $exams ) : foreach ( $exams as $exam ) : 
                            $del_url = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=exams&action=delete&id=' . $exam->id ), 'delete_exam_' . $exam->id );
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $exam->exam_name ); ?></strong></td>
                            <td><?php echo esc_html( $exam->class_name ); ?></td>
                            <td>
                                <small><?php echo date('d M', strtotime($exam->start_date)); ?> - <?php echo date('d M Y', strtotime($exam->end_date)); ?></small>
                            </td>
                            <td>
                                <span class="badge <?php echo $exam->status === 'Completed' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo esc_html( $exam->status ); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( $del_url ); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this exam?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('.educore-datatable').DataTable({ "pageLength": 10 });
    });
    </script>
    <?php
}
?>