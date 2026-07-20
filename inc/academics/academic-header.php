<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Process Status Messages
$message_text = '';
if ( isset( $_GET['status'] ) ) {
    if ( $_GET['status'] === 'success' ) {
        $message_text = __( 'Class added successfully.', 'educore' );
    } elseif ( $_GET['status'] === 'updated' ) {
        $message_text = __( 'Class updated successfully.', 'educore' );
    } elseif ( $_GET['status'] === 'deleted' ) {
        $message_text = __( 'Record deleted successfully.', 'educore' );
    } elseif ( $_GET['status'] === 'subjects_added' ) {
        $count = isset( $_GET['count'] ) ? intval( $_GET['count'] ) : 0;
        $message_text = sprintf( __( 'Successfully added %d subject(s).', 'educore' ), $count );
    }
}
?>

<!-- Global Academic Dashboard Styles -->
<style>
    .dpt-academics-root { margin: 20px 20px 24px 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    .afdp-header-frame { margin-bottom: 24px; }
    .afdp-header-frame h2 { font-size: 22px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; display: flex; align-items: center; gap: 10px; }
    .afdp-header-frame h2 .dashicons { font-size: 26px; width: 26px; height: 26px; color: #006a4e; }
    .afdp-header-frame p { margin: 0; font-size: 13px; color: #64748b; font-weight: 500; }

    .afdp-tab-nav { display: flex; gap: 12px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
    .afdp-tab-link { text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 600; color: #64748b; transition: all 0.2s ease; }
    .afdp-tab-link:hover { color: #0f172a; background: #f1f5f9; }
    .afdp-tab-link.active { background: #006a4e; color: #ffffff; box-shadow: 0 2px 4px rgba(0, 106, 78, 0.2); }

    .dpt-bento-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); margin-bottom: 24px; }
    .dpt-bento-subheading { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0 0 18px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }

    .afdp-alert-node { border-radius: 10px; padding: 14px 18px; font-weight: 600; font-size: 13.5px; margin-bottom: 20px; }
    .afdp-alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    .afdp-alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

    .dpt-field-input, .dpt-field-select { height: 42px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 14px; font-size: 13.5px; width: 100%; background: #f8fafc; }
    .dpt-field-input:focus, .dpt-field-select:focus { border-color: #006a4e; background: #fff; box-shadow: 0 0 0 3px rgba(0,106,78,0.1); outline: none; }
    .dpt-form-label { font-size: 12.5px; font-weight: 700; color: #475569; margin-bottom: 6px; display: block; }
    
    .dpt-btn-action-trigger { height: 42px; background: #006a4e; color: #fff; font-weight: 700; border-radius: 8px; padding: 0 20px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;}
    .dpt-btn-action-trigger:hover { background: #00523c; }

    .dpt-responsive-datatable { width: 100%; overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 10px; }
    .dpt-architecture-table { width: 100%; border-collapse: collapse; text-align: left; }
    .dpt-architecture-table th { background: #f8fafc; color: #475569; font-weight: 700; font-size: 12.5px; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
    .dpt-architecture-table td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; font-size: 13.5px; color: #334155; }
    
    .dpt-square-btn { width: 32px; height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; color: #64748b; transition: 0.2s; }
    .dpt-square-btn-edit:hover { border-color: #2563eb; color: #2563eb; background: #eff6ff; }
    .dpt-square-btn-delete:hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }
</style>

<div class="dpt-academics-root">
    
    <div class="afdp-header-frame">
        <h2><span class="dashicons dashicons-welcome-learn-more"></span> Academic Operations</h2>
        <p>Manage unified classes, curriculum subjects, and routine setups.</p>
    </div>

    <!-- Sub-Tab Navigation -->
    <div class="afdp-tab-nav">
        <a href="<?php echo esc_url( add_query_arg( 'subtab', 'units', $base_url ) ); ?>" class="afdp-tab-link <?php echo $current_subtab === 'units' ? 'active' : ''; ?>">
            Classes Setup
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'subtab', 'subjects', $base_url ) ); ?>" class="afdp-tab-link <?php echo $current_subtab === 'subjects' ? 'active' : ''; ?>">
            Class Wise Subjects
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'subtab', 'routine', $base_url ) ); ?>" class="afdp-tab-link <?php echo $current_subtab === 'routine' ? 'active' : ''; ?>">
            Class Routine
        </a>
    </div>

    <!-- Feedback Notice -->
    <?php if ( ! empty( $message_text ) ) : ?>
        <div class="afdp-alert-node afdp-alert-success">
            <strong>Success:</strong> <?php echo esc_html( $message_text ); ?>
        </div>
    <?php endif; ?>