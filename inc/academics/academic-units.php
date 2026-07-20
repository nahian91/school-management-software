<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$table_units = $wpdb->prefix . 'sms_academic_units';

$is_edit  = isset( $_GET['action'] ) && $_GET['action'] === 'edit_unit' && isset( $_GET['id'] );
$edit_id  = $is_edit ? intval( $_GET['id'] ) : 0;
$edit_row = $is_edit ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_units} WHERE id = %d", $edit_id ) ) : null;

// Handle Form Submit (Add/Edit)
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_class_row'] ) ) {
    if ( wp_verify_nonce( $_POST['class_setup_nonce'], 'class_setup_action' ) ) {
        $class_name = sanitize_text_field( trim( $_POST['class_name'] ) );
        $row_id     = isset( $_POST['row_id'] ) ? intval( $_POST['row_id'] ) : 0;

        if ( ! empty( $class_name ) ) {
            $dup_query = "SELECT id FROM {$table_units} WHERE class_name = %s";
            $dup_params = array( $class_name );
            if ( $row_id > 0 ) {
                $dup_query .= " AND id != %d";
                $dup_params[] = $row_id;
            }
            
            if ( ! $wpdb->get_var( $wpdb->prepare( $dup_query, $dup_params ) ) ) {
                if ( $row_id > 0 ) {
                    $wpdb->update( $table_units, array( 'class_name' => $class_name ), array( 'id' => $row_id ), array( '%s' ), array( '%d' ) );
                    echo '<script>window.location.href="'.esc_url(add_query_arg(['subtab'=>'units','status'=>'updated'], $base_url)).'";</script>'; exit;
                } else {
                    $wpdb->insert( $table_units, array( 'class_name' => $class_name ), array( '%s' ) );
                    echo '<script>window.location.href="'.esc_url(add_query_arg(['subtab'=>'units','status'=>'success'], $base_url)).'";</script>'; exit;
                }
            } else {
                echo '<div class="afdp-alert-node afdp-alert-warning">This Class already exists.</div>';
            }
        }
    }
}

// Handle Delete
if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_unit' && isset( $_GET['id'] ) ) {
    if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_unit_action_' . intval( $_GET['id'] ) ) ) {
        $wpdb->delete( $table_units, array( 'id' => intval( $_GET['id'] ) ), array( '%d' ) );
        echo '<script>window.location.href="'.esc_url(add_query_arg(['subtab'=>'units','status'=>'deleted'], $base_url)).'";</script>'; exit;
    }
}

$classes = $wpdb->get_results( "SELECT * FROM {$table_units} ORDER BY class_name ASC" );
?>

<div class="dpt-bento-box">
    <h5 class="dpt-bento-subheading"><?php echo $is_edit ? 'Edit Class' : 'Add New Class'; ?></h5>
    <form method="POST" action="">
        <?php wp_nonce_field( 'class_setup_action', 'class_setup_nonce' ); ?>
        <input type="hidden" name="row_id" value="<?php echo $edit_id; ?>">
        
        <div style="display:flex; gap:16px; align-items:flex-end; max-width:600px;">
            <div style="flex:1;">
                <label class="dpt-form-label">Class Name <span style="color:#dc2626;">*</span></label>
                <input type="text" name="class_name" class="dpt-field-input" placeholder="e.g. Class 1, Class 9 - Science" value="<?php echo $edit_row ? esc_attr($edit_row->class_name) : ''; ?>" required>
            </div>
            <div>
                <button type="submit" name="save_class_row" class="dpt-btn-action-trigger">
                    <span class="dashicons <?php echo $is_edit ? 'dashicons-edit' : 'dashicons-plus-alt2'; ?>"></span> 
                    <?php echo $is_edit ? 'Update Class' : 'Add Class'; ?>
                </button>
            </div>
        </div>
    </form>
</div>

<div class="dpt-bento-box">
    <h5 class="dpt-bento-subheading">Configured Classes</h5>
    <div class="dpt-responsive-datatable">
        <table class="dpt-architecture-table">
            <thead>
                <tr>
                    <th style="width: 80%;">Class Name</th>
                    <th style="width: 20%; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $classes ) ) : foreach ( $classes as $cls ) : ?>
                    <tr>
                        <td style="font-weight: 700; color: #0f172a;"><?php echo esc_html( $cls->class_name ); ?></td>
                        <td style="text-align: right;">
                            <a href="<?php echo esc_url( add_query_arg( ['subtab'=>'units', 'action'=>'edit_unit', 'id'=>$cls->id], $base_url ) ); ?>" class="dpt-square-btn dpt-square-btn-edit"><span class="dashicons dashicons-edit"></span></a>
                            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( ['subtab'=>'units', 'action'=>'delete_unit', 'id'=>$cls->id], $base_url ), 'delete_unit_action_' . $cls->id ) ); ?>" class="dpt-square-btn dpt-square-btn-delete" onclick="return confirm('Delete this class?');"><span class="dashicons dashicons-trash"></span></a>
                        </td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="2" style="text-align:center; padding: 20px;">No classes added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>