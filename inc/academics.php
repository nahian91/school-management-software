<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function educore_academics_tab() {
    // Handle Form Submissions
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['educore_academic_nonce'] ) && wp_verify_nonce( $_POST['educore_academic_nonce'], 'academic_setup_action' ) ) {
        
        // Add New Class
        if ( isset( $_POST['add_class'] ) && ! empty( trim( $_POST['new_class'] ) ) ) {
            $new_class = sanitize_text_field( trim( $_POST['new_class'] ) );
            $classes   = get_option( 'educore_classes', array() );
            if ( ! in_array( $new_class, $classes ) ) {
                $classes[] = $new_class;
                update_option( 'educore_classes', $classes );
                echo '<div class="alert alert-success">Class added successfully.</div>';
            } else {
                echo '<div class="alert alert-warning">This class already exists.</div>';
            }
        }

        // Add New Section
        if ( isset( $_POST['add_section'] ) && ! empty( trim( $_POST['new_section'] ) ) ) {
            $new_section = sanitize_text_field( trim( $_POST['new_section'] ) );
            $sections    = get_option( 'educore_sections', array() );
            if ( ! in_array( $new_section, $sections ) ) {
                $sections[] = $new_section;
                update_option( 'educore_sections', $sections );
                echo '<div class="alert alert-success">Section added successfully.</div>';
            } else {
                echo '<div class="alert alert-warning">This section already exists.</div>';
            }
        }
    }

    // Handle Deletions (GET request)
    if ( isset( $_GET['action'] ) && isset( $_GET['item'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_academic_item' ) ) {
        $item_to_delete = sanitize_text_field( $_GET['item'] );
        
        if ( $_GET['action'] === 'delete_class' ) {
            $classes = get_option( 'educore_classes', array() );
            if ( ( $key = array_search( $item_to_delete, $classes ) ) !== false ) {
                unset( $classes[$key] );
                update_option( 'educore_classes', array_values( $classes ) ); // Reindex and save
                echo '<div class="alert alert-success">Class deleted successfully.</div>';
            }
        }

        if ( $_GET['action'] === 'delete_section' ) {
            $sections = get_option( 'educore_sections', array() );
            if ( ( $key = array_search( $item_to_delete, $sections ) ) !== false ) {
                unset( $sections[$key] );
                update_option( 'educore_sections', array_values( $sections ) ); // Reindex and save
                echo '<div class="alert alert-success">Section deleted successfully.</div>';
            }
        }
    }

    // Fetch Current Data
    $saved_classes  = get_option( 'educore_classes', array() );
    $saved_sections = get_option( 'educore_sections', array() );
    ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><span class="dashicons dashicons-welcome-learn-more"></span> Academic Setup</h2>
    </div>

    <div class="row">
        <!-- Classes Management Column -->
        <div class="col-md-6 mb-4">
            <div class="bg-white p-4 rounded shadow-sm border h-100">
                <h4 class="border-bottom pb-2 mb-3 text-primary">Manage Classes</h4>
                
                <form method="POST" action="" class="mb-4 d-flex gap-2">
                    <?php wp_nonce_field( 'academic_setup_action', 'educore_academic_nonce' ); ?>
                    <input type="text" name="new_class" class="form-control" placeholder="e.g. Class 6, Class 10" required>
                    <button type="submit" name="add_class" class="btn btn-success px-4" style="background-color: #10b981; border: none;">Add</button>
                </form>

                <table class="table table-bordered table-striped align-middle">
                    <thead style="background-color: #f8fafc;">
                        <tr>
                            <th>Class Name</th>
                            <th style="width: 80px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $saved_classes ) ) : ?>
                            <?php foreach ( $saved_classes as $class_name ) : 
                                $delete_url = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=academics&action=delete_class&item=' . urlencode( $class_name ) ), 'delete_academic_item' );
                            ?>
                                <tr>
                                    <td class="fw-bold"><?php echo esc_html( $class_name ); ?></td>
                                    <td class="text-center">
                                        <a href="<?php echo esc_url( $delete_url ); ?>" class="text-danger" onclick="return confirm('Delete this class?');" title="Delete">
                                            <span class="dashicons dashicons-trash"></span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="2" class="text-muted text-center">No classes added yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sections Management Column -->
        <div class="col-md-6 mb-4">
            <div class="bg-white p-4 rounded shadow-sm border h-100">
                <h4 class="border-bottom pb-2 mb-3 text-primary">Manage Sections</h4>
                
                <form method="POST" action="" class="mb-4 d-flex gap-2">
                    <?php wp_nonce_field( 'academic_setup_action', 'educore_academic_nonce_sec' ); ?>
                    <input type="text" name="new_section" class="form-control" placeholder="e.g. A, B, Science, Arts" required>
                    <button type="submit" name="add_section" class="btn btn-success px-4" style="background-color: #10b981; border: none;">Add</button>
                </form>

                <table class="table table-bordered table-striped align-middle">
                    <thead style="background-color: #f8fafc;">
                        <tr>
                            <th>Section Name</th>
                            <th style="width: 80px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $saved_sections ) ) : ?>
                            <?php foreach ( $saved_sections as $section_name ) : 
                                $delete_url = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=academics&action=delete_section&item=' . urlencode( $section_name ) ), 'delete_academic_item' );
                            ?>
                                <tr>
                                    <td class="fw-bold"><?php echo esc_html( $section_name ); ?></td>
                                    <td class="text-center">
                                        <a href="<?php echo esc_url( $delete_url ); ?>" class="text-danger" onclick="return confirm('Delete this section?');" title="Delete">
                                            <span class="dashicons dashicons-trash"></span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="2" class="text-muted text-center">No sections added yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
?>