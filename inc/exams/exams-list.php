<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Academic Examinations Directory List View
 * File: inc/exams/exam-list.php
 * Custom Prefixes Applied: dpt-, afdp-
 */
function educore_exam_list_view() {
    global $wpdb;
    
    $table_exams = $wpdb->prefix . 'sms_exams';

    // Strict Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage examination schemes.', 'ifsedu-sms' ) );
    }

    // Dynamic Base URL
    $current_uri = remove_query_arg( array( 'action', 'id', '_wpnonce', 'status' ), $_SERVER['REQUEST_URI'] );
    $base_url    = esc_url_raw( $current_uri );
    $add_url     = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=add' );

    // Handle Delete Exam Action
    $get_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
    $get_id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

    if ( 'delete' === $get_action && $get_id > 0 ) {
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_exam_' . $get_id ) ) {
            $wpdb->delete( $table_exams, array( 'id' => $get_id ), array( '%d' ) );

            if ( class_exists( 'IFSEdu_School_Management_System' ) ) {
                IFSEdu_School_Management_System::log_activity( "Deleted exam ID: " . $get_id );
            }

            $redirect_target = add_query_arg( array( 'status' => 'deleted' ), $base_url );
            echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_target ) . '";</script>';
            exit;
        }
    }

    // Fetch All Exams
    $exams = $wpdb->get_results( "SELECT * FROM {$table_exams} ORDER BY id DESC" );
    $status_msg = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
    ?>

    <style>
        .dpt-exams-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .afdp-header-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .afdp-header-block h2 {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .afdp-header-block h2 .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
            color: #006a4e;
        }

        .afdp-status-banner {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            padding: 14px 18px;
            color: #065f46;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dpt-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-card-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dpt-btn-primary {
            height: 40px;
            padding: 0 20px;
            background: #006a4e;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
            cursor: pointer;
        }

        .dpt-btn-primary:hover {
            background: #00523c;
            color: #ffffff;
        }

        .dpt-table-responsive {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .dpt-exams-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
            font-size: 13.5px;
        }

        .dpt-exams-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .dpt-exams-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .dpt-exams-table tr:last-child td {
            border-bottom: none;
        }

        .dpt-exams-table tr:hover td {
            background: #f8fafc;
        }

        .afdp-badge {
            font-size: 11px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .afdp-badge-class { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
        .afdp-badge-upcoming { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .afdp-badge-ongoing { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .afdp-badge-completed { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }

        .afdp-action-btn-svg {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #475569;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .afdp-action-btn-svg.edit:hover {
            border-color: #006a4e;
            color: #ffffff;
            background: #006a4e;
        }

        .afdp-action-btn-svg.delete:hover {
            border-color: #dc2626;
            color: #ffffff;
            background: #dc2626;
        }
    </style>

    <div class="dpt-exams-root">
        
        <!-- Status Alert Notification Bar -->
        <?php if ( ! empty( $status_msg ) ) : ?>
            <div class="afdp-status-banner">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php 
                    if ( 'success' === $status_msg ) {
                        esc_html_e( 'New examination scheme created successfully.', 'ifsedu-sms' );
                    } elseif ( 'updated' === $status_msg ) {
                        esc_html_e( 'Examination details updated successfully.', 'ifsedu-sms' );
                    } elseif ( 'deleted' === $status_msg ) {
                        esc_html_e( 'Examination record removed successfully.', 'ifsedu-sms' );
                    }
                ?>
            </div>
        <?php endif; ?>

        <!-- Full-Width Examination Table Bento Card -->
        <div class="dpt-bento-card">
            <h4 class="afdp-card-title">
                <span><?php esc_html_e( 'All Configured Examination Schemes', 'ifsedu-sms' ); ?></span>
                <span style="font-size:12px; font-weight:600; color:#64748b; background:#f1f5f9; padding:3px 10px; border-radius:12px;">
                    <?php echo count( $exams ); ?> <?php esc_html_e( 'Exams Found', 'ifsedu-sms' ); ?>
                </span>
            </h4>
            
            <div class="dpt-table-responsive">
                <table class="dpt-exams-table educore-datatable">
                    <thead>
                        <tr>
                            <th style="width: 30%;"><?php esc_html_e( 'Exam Name', 'ifsedu-sms' ); ?></th>
                            <th style="width: 25%;"><?php esc_html_e( 'Target Class / Tier', 'ifsedu-sms' ); ?></th>
                            <th style="width: 20%;"><?php esc_html_e( 'Schedule Period', 'ifsedu-sms' ); ?></th>
                            <th style="width: 15%;"><?php esc_html_e( 'Status', 'ifsedu-sms' ); ?></th>
                            <th style="width: 10%; text-align: right;"><?php esc_html_e( 'Actions', 'ifsedu-sms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $exams ) ) : foreach ( $exams as $exam ) : 
                            $exam_id  = intval( $exam->id );
                            $edit_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=add&action=edit&id=' . $exam_id );
                            $del_url  = wp_nonce_url( add_query_arg( array( 'action' => 'delete', 'id' => $exam_id ), $base_url ), 'delete_exam_' . $exam_id );
                        ?>
                        <tr>
                            <td>
                                <strong style="font-size: 14px; color:#0f172a;"><?php echo esc_html( $exam->exam_name ); ?></strong>
                            </td>
                            <td>
                                <span class="afdp-badge afdp-badge-class"><?php echo esc_html( $exam->class_name ); ?></span>
                            </td>
                            <td>
                                <span style="color: #475569; font-weight: 600; font-size: 12.5px;">
                                    <?php echo esc_html( date_i18n( 'd M Y', strtotime( $exam->start_date ) ) ); ?> - <?php echo esc_html( date_i18n( 'd M Y', strtotime( $exam->end_date ) ) ); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                    $badge_class = 'afdp-badge-upcoming';
                                    if ( 'Completed' === $exam->status ) {
                                        $badge_class = 'afdp-badge-completed';
                                    } elseif ( 'Ongoing' === $exam->status ) {
                                        $badge_class = 'afdp-badge-ongoing';
                                    }
                                ?>
                                <span class="afdp-badge <?php echo esc_attr( $badge_class ); ?>">
                                    <?php echo esc_html( $exam->status ); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px; justify-content: flex-end;">
                                    <a href="<?php echo esc_url( $edit_url ); ?>" class="afdp-action-btn-svg edit" title="<?php esc_attr_e( 'Edit Exam', 'ifsedu-sms' ); ?>">
                                        <span class="dashicons dashicons-edit" style="font-size:16px; width:16px; height:16px;"></span>
                                    </a>
                                    <a href="<?php echo esc_url( $del_url ); ?>" class="afdp-action-btn-svg delete" title="<?php esc_attr_e( 'Delete Exam', 'ifsedu-sms' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this exam scheme permanently?', 'ifsedu-sms' ) ); ?>');">
                                        <span class="dashicons dashicons-trash" style="font-size:16px; width:16px; height:16px;"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else : ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 30px; color:#94a3b8;">
                                <?php esc_html_e( 'No examination schemes created yet.', 'ifsedu-sms' ); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if ($.fn.DataTable) {
            $('.educore-datatable').DataTable({ 
                "pageLength": 15,
                "ordering": false,
                "responsive": true,
                "language": {
                    "search": "<?php echo esc_js( __( 'Search Schemes:', 'ifsedu-sms' ) ); ?>",
                    "lengthMenu": "<?php echo esc_js( __( 'Show _MENU_ entries', 'ifsedu-sms' ) ); ?>"
                }
            });
        }
    });
    </script>
    <?php
}