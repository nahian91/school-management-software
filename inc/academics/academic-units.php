<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$table_units = $wpdb->prefix . 'sms_academic_units';

// Build Base URL safely from current request URI without state query params
$current_uri = remove_query_arg( array( 'action', 'id', '_wpnonce', 'status' ), $_SERVER['REQUEST_URI'] );
$base_url    = esc_url_raw( $current_uri );

// 1. Handle Delete Action (Intercept Before Page Renders)
if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_unit' && isset( $_GET['id'] ) ) {
    $delete_id = intval( $_GET['id'] );
    
    if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_unit_action_' . $delete_id ) ) {
        $wpdb->delete( $table_units, array( 'id' => $delete_id ), array( '%d' ) );
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

// 2. State Setup for Edit Mode
$is_edit  = isset( $_GET['action'] ) && $_GET['action'] === 'edit_unit' && isset( $_GET['id'] );
$edit_id  = $is_edit ? intval( $_GET['id'] ) : 0;
$edit_row = $is_edit ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_units} WHERE id = %d", $edit_id ) ) : null;

// 3. Handle Form Submit (Add/Edit)
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_class_row'] ) ) {
    if ( isset( $_POST['class_setup_nonce'] ) && wp_verify_nonce( $_POST['class_setup_nonce'], 'class_setup_action' ) ) {
        $class_name   = sanitize_text_field( trim( $_POST['class_name'] ) );
        $section_name = sanitize_text_field( trim( $_POST['section_name'] ) );
        $row_id       = isset( $_POST['row_id'] ) ? intval( $_POST['row_id'] ) : 0;

        if ( ! empty( $class_name ) ) {
            // Check Duplicate for Class + Section Combination
            $dup_query  = "SELECT id FROM {$table_units} WHERE class_name = %s AND section_name = %s";
            $dup_params = array( $class_name, $section_name );

            if ( $row_id > 0 ) {
                $dup_query  .= " AND id != %d";
                $dup_params[] = $row_id;
            }
            
            if ( ! $wpdb->get_var( $wpdb->prepare( $dup_query, $dup_params ) ) ) {
                $data = array(
                    'class_name'   => $class_name,
                    'section_name' => $section_name,
                );
                $format = array( '%s', '%s' );

                if ( $row_id > 0 ) {
                    $wpdb->update( $table_units, $data, array( 'id' => $row_id ), $format, array( '%d' ) );
                    $redirect_target = add_query_arg( array( 'status' => 'updated' ), $base_url );
                } else {
                    $wpdb->insert( $table_units, $data, $format );
                    $redirect_target = add_query_arg( array( 'status' => 'success' ), $base_url );
                }

                if ( function_exists( 'educore_safe_redirect_helper' ) ) {
                    educore_safe_redirect_helper( $redirect_target );
                } elseif ( function_exists( 'educore_safe_redirect' ) ) {
                    educore_safe_redirect( $redirect_target );
                } else {
                    echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_target ) . '";</script>';
                }
                exit;
            } else {
                echo '<div class="afdp-alert-node afdp-alert-warning" style="padding:12px 16px; background:#fef3c7; color:#92400e; border-radius:8px; margin-bottom:16px;">This Class and Section combination already exists.</div>';
            }
        }
    }
}

// 4. Natural Numeric Sorting Fetch Strategy (1, 2, 3... 10, 11, 12)
$classes = $wpdb->get_results( 
    "SELECT * FROM {$table_units} 
     ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" 
);

// Perfect Natural Sorting fallback for mixed strings (e.g. "Class 1", "Class 10")
if ( ! empty( $classes ) ) {
    usort( $classes, function( $a, $b ) {
        $res = strnatcasecmp( $a->class_name, $b->class_name );
        if ( $res === 0 ) {
            return strnatcasecmp( $a->section_name, $b->section_name );
        }
        return $res;
    });
}
?>

<div class="dpt-bento-box" style="background:#fff; padding:24px; border-radius:12px; border:1px solid #e2e8f0; margin-bottom:24px;">
    <h5 class="dpt-bento-subheading" style="font-size:16px; font-weight:700; color:#0f172a; margin-top:0; margin-bottom:16px;"><?php echo $is_edit ? 'Edit Academic Unit' : 'Add Academic Unit (Class & Section)'; ?></h5>
    <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
        <?php wp_nonce_field( 'class_setup_action', 'class_setup_nonce' ); ?>
        <input type="hidden" name="row_id" value="<?php echo esc_attr( $edit_id ); ?>">
        
        <div style="display:flex; gap:16px; align-items:flex-end; max-width:800px; flex-wrap:wrap;">
            <div style="flex:1; min-width:220px;">
                <label class="dpt-form-label" style="display:block; font-size:13px; font-weight:600; color:#475569; margin-bottom:6px;">Class Name <span style="color:#dc2626;">*</span></label>
                <input type="text" name="class_name" class="dpt-field-input" placeholder="e.g. 1, 2, Class 9" value="<?php echo $edit_row ? esc_attr( $edit_row->class_name ) : ''; ?>" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; height:38px;" required>
            </div>

            <div style="flex:1; min-width:220px;">
                <label class="dpt-form-label" style="display:block; font-size:13px; font-weight:600; color:#475569; margin-bottom:6px;">Section Name</label>
                <input type="text" name="section_name" class="dpt-field-input" placeholder="e.g. Section A, Science, Rose" value="<?php echo $edit_row ? esc_attr( $edit_row->section_name ) : ''; ?>" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; height:38px;">
            </div>

            <div>
                <button type="submit" name="save_class_row" class="dpt-btn-action-trigger" style="background:#22c55e; color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; height:38px; display:inline-flex; align-items:center; gap:6px;">
                    <span class="dashicons <?php echo $is_edit ? 'dashicons-edit' : 'dashicons-plus-alt2'; ?>" style="font-size:18px; width:18px; height:18px;"></span> 
                    <?php echo $is_edit ? 'Update Unit' : 'Add Unit'; ?>
                </button>
                <?php if ( $is_edit ) : ?>
                    <a href="<?php echo esc_url( $base_url ); ?>" style="display:inline-flex; align-items:center; padding:8px 12px; font-size:13px; color:#64748b; text-decoration:none; margin-left:8px;">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="dpt-bento-box" style="background:#fff; padding:24px; border-radius:12px; border:1px solid #e2e8f0;">
    <h5 class="dpt-bento-subheading" style="font-size:16px; font-weight:700; color:#0f172a; margin-top:0; margin-bottom:16px;">Configured Academic Units</h5>
    <div class="dpt-responsive-datatable">
        <table class="dpt-architecture-table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc; text-align:left;">
                    <th style="width: 40%; padding:12px 16px; border-bottom:2px solid #e2e8f0; color:#475569; font-size:12px; text-transform:uppercase;">Class Name</th>
                    <th style="width: 40%; padding:12px 16px; border-bottom:2px solid #e2e8f0; color:#475569; font-size:12px; text-transform:uppercase;">Section Name</th>
                    <th style="width: 20%; text-align: right; padding:12px 16px; border-bottom:2px solid #e2e8f0; color:#475569; font-size:12px; text-transform:uppercase;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $classes ) ) : foreach ( $classes as $cls ) : 
                    $edit_link   = add_query_arg( array( 'action' => 'edit_unit', 'id' => $cls->id ), $base_url );
                    $delete_link = wp_nonce_url( add_query_arg( array( 'action' => 'delete_unit', 'id' => $cls->id ), $base_url ), 'delete_unit_action_' . $cls->id );
                ?>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="font-weight: 700; color: #0f172a; padding:12px 16px;"><?php echo esc_html( $cls->class_name ); ?></td>
                        <td style="color: #334155; padding:12px 16px;">
                            <?php if ( ! empty( $cls->section_name ) ) : ?>
                                <span style="background:#f1f5f9; padding:4px 10px; border-radius:4px; font-weight:600; font-size:12px; color:#475569;"><?php echo esc_html( $cls->section_name ); ?></span>
                            <?php else : ?>
                                <span style="color:#94a3b8; font-style:italic;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; padding:12px 16px;">
                            <a href="<?php echo esc_url( $edit_link ); ?>" class="dpt-square-btn dpt-square-btn-edit" style="color:#3b82f6; text-decoration:none; margin-right:8px;"><span class="dashicons dashicons-edit"></span></a>
                            <a href="<?php echo esc_url( $delete_link ); ?>" class="dpt-square-btn dpt-square-btn-delete" style="color:#ef4444; text-decoration:none;" onclick="return confirm('Delete this academic unit?');"><span class="dashicons dashicons-trash"></span></a>
                        </td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="3" style="text-align:center; padding: 20px; color:#64748b;">No academic units configured yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>