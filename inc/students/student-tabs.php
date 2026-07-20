<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

/**
 * High-End Academic Students Sub-Navigation Engine & Router Matrix
 * Custom Prefixes Applied: dpt-, afdp-
 * Standardized Typography and Modern Bento Element Alignment
 */
function educore_students_tab() {
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( $_GET['sub'] ) : 'list';
    
    // Construct URLs for top submenu links
    $all_students_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );
    $add_student_url  = admin_url( 'admin.php?page=school_management_system&tab=students&sub=add' );
    $id_card_url      = admin_url( 'admin.php?page=school_management_system&tab=students&sub=id_card' );
    $admit_card_url   = admin_url( 'admin.php?page=school_management_system&tab=students&sub=admit_card' );
    ?>

    <style>
        /* ==========================================================================
           1. ELITE NAV BAR SYSTEM CORE STYLE LAYERING
           ========================================================================== */
        .dpt-students-nav-root {
            margin: 20px 20px 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        /* Modern Bento Top Header Frame Block */
        .afdp-top-nav-wrapper {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }

        .dpt-nav-button-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Tactical Kinetic Button Design Matrix */
        .dpt-nav-link {
            height: 38px;
            padding: 0 16px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }

        /* Active Nav Pill State */
        .dpt-nav-link-active {
            background: #006a4e;
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.15);
        }
        .dpt-nav-link-active:hover {
            background: #00523c;
            color: #ffffff;
        }

        /* Default Inactive Nav Pill State */
        .dpt-nav-link-inactive {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #475569;
        }
        .dpt-nav-link-inactive:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #0f172a;
            transform: translateY(-0.5px);
        }

        .dpt-nav-link .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
            display: inline-block;
            line-height: 1;
        }

        /* Dynamic Visual Pill Context Badge */
        .afdp-context-badge {
            background: #e0f2fe;
            color: #0369a1;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            letter-spacing: 0.25px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #bae6fd;
        }

        /* Container Core Structure Layer */
        .dpt-module-viewport-container {
            width: 100%;
        }

        /* Fallback Notice Interface */
        .afdp-notice-card {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 16px 20px;
            border-radius: 0 8px 8px 0;
            color: #15803d;
            font-size: 14px;
            font-weight: 500;
            margin-top: 10px;
        }
        .afdp-notice-card code {
            background: rgba(16, 185, 129, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            color: #065f46;
            font-weight: 600;
        }

        /* ==========================================================================
           2. HARDWARE PRINT METRICS INCLUSIONS
           ========================================================================== */
        @media print {
            .no-print, 
            .afdp-top-nav-wrapper {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .dpt-students-nav-root {
                margin: 0 !important;
            }
        }
    </style>

    <div class="dpt-students-nav-root">
        
        <!-- Top Sub-Navigation Menu Bar (Bento Frame Layer) -->
        <div class="afdp-top-nav-wrapper no-print">
            <div class="dpt-nav-button-group">
                <a href="<?php echo esc_url( $all_students_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'list' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-groups"></span> All Students
                </a>
                
                <a href="<?php echo esc_url( $add_student_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'add' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-plus-alt2"></span> + Add New Student
                </a>
                
                <a href="<?php echo esc_url( $id_card_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'id_card' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-id-alt"></span> Student ID Cards
                </a>
                
                <a href="<?php echo esc_url( $admit_card_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'admit_card' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-tickets-alt"></span> Admit Cards
                </a>
            </div>

            <?php if ( in_array( $sub_tab, array( 'edit', 'view' ), true ) ) : ?>
                <div>
                    <span class="afdp-context-badge">
                        <span class="dashicons dashicons-edit" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php echo ucfirst( $sub_tab ); ?>ing Student Record
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- System Routing Execution Core -->
        <div class="dpt-module-viewport-container">
            <?php
            switch ( $sub_tab ) {
                case 'add':
                case 'edit':
                    if ( function_exists( 'educore_student_add_edit_view' ) ) {
                        educore_student_add_edit_view();
                    }
                    break;

                case 'view':
                    if ( function_exists( 'educore_student_profile_view' ) ) {
                        educore_student_profile_view();
                    }
                    break;

                case 'id_card':
                    if ( function_exists( 'educore_student_id_card_view' ) ) {
                        educore_student_id_card_view();
                    } else {
                        echo '<div class="afdp-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> Student ID Card Generator module is initializing. Define <code>educore_student_id_card_view()</code>.</div>';
                    }
                    break;

                case 'admit_card':
                    if ( function_exists( 'educore_student_admit_card_view' ) ) {
                        educore_student_admit_card_view();
                    } else {
                        echo '<div class="afdp-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> Admit Card Generator module is initializing. Define <code>educore_student_admit_card_view()</code>.</div>';
                    }
                    break;

                case 'delete':
                    if ( function_exists( 'educore_student_delete_action' ) ) {
                        educore_student_delete_action();
                    }
                    break;

                case 'list':
                default:
                    if ( function_exists( 'educore_students_list_view' ) ) {
                        educore_students_list_view();
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}