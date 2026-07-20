<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

/**
 * High-End Academic Examinations Sub-Navigation Engine & Router Matrix
 * Custom Prefixes Applied: dpt-, afdp-
 * Standardized Typography and Modern Bento Element Alignment
 */
function educore_exams_tab() {
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( $_GET['sub'] ) : 'list';

    // Construct URLs for top submenu links
    $all_exams_url = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=list' );
    $marks_url     = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=marks' );
    $report_url    = admin_url( 'admin.php?page=school_management_system&tab=exams&sub=report' );
    ?>

    <style>
        /* ==========================================================================
           1. ELITE NAV BAR SYSTEM CORE STYLE LAYERING
           ========================================================================== */
        .dpt-exams-nav-root {
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
            .dpt-exams-nav-root {
                margin: 0 !important;
            }
        }
    </style>

    <div class="dpt-exams-nav-root">
        
        <!-- Top Sub-Navigation Menu Bar (Bento Frame Layer) -->
        <div class="afdp-top-nav-wrapper no-print">
            <div class="dpt-nav-button-group">
                <a href="<?php echo esc_url( $all_exams_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'list' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-welcome-write-blog"></span> All Examinations
                </a>
                
                <a href="<?php echo esc_url( $marks_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'marks' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-edit"></span> Marks Entry Matrix
                </a>
                
                <a href="<?php echo esc_url( $report_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'report' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-clipboard"></span> Progress & Tabulation Sheet
                </a>
            </div>

            <?php if ( in_array( $sub_tab, array( 'edit', 'view' ), true ) ) : ?>
                <div>
                    <span class="afdp-context-badge">
                        <span class="dashicons dashicons-edit" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php echo ucfirst( $sub_tab ); ?>ing Exam Scheme
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- System Routing Execution Core -->
        <div class="dpt-module-viewport-container">
            <?php
            switch ( $sub_tab ) {
                case 'marks':
                    if ( function_exists( 'educore_exams_marks_view' ) ) {
                        educore_exams_marks_view();
                    } else {
                        echo '<div class="afdp-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> Marks Entry Matrix module is initializing. Define <code>educore_exams_marks_view()</code>.</div>';
                    }
                    break;

                case 'report':
                    if ( function_exists( 'educore_exams_report_view' ) ) {
                        educore_exams_report_view();
                    } else {
                        echo '<div class="afdp-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> Progress & Tabulation Sheet module is initializing. Define <code>educore_exams_report_view()</code>.</div>';
                    }
                    break;

                case 'list':
                default:
                    if ( function_exists( 'educore_exams_list_view' ) ) {
                        educore_exams_list_view();
                    } else {
                        echo '<div class="afdp-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> Exams List View module is initializing. Define <code>educore_exams_list_view()</code>.</div>';
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}