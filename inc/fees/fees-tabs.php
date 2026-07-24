<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

/**
 * High-End Academic Financial Fees Sub-Navigation Engine & Router Matrix
 * File: fees-tab.php
 * Custom Prefixes Applied: dpt-, afdp-
 * Architecture: Bento Layout Viewports with Integrated Hardware Print Lockdown
 */
function educore_fees_tab() {
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_text_field( wp_unslash( $_GET['sub'] ) ) : 'list';

    // Construct URLs for top submenu links
    $all_fees_url = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=list' );
    $collect_url  = admin_url( 'admin.php?page=school_management_system&tab=fees&sub=collect' );
    ?>

    <style>
        /* ==========================================================================
           1. ELITE NAV BAR SYSTEM CORE STYLE LAYERING
           ========================================================================== */
        .dpt-fees-nav-root {
            margin: 20px 20px 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #0f172a;
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

        /* Dynamic Financial Visual Pill Context Badge */
        .afdp-context-badge {
            background: #fef3c7;
            color: #92400e;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            letter-spacing: 0.25px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #fde68a;
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
            .dpt-fees-nav-root {
                margin: 0 !important;
            }
        }
    </style>

    <div class="dpt-fees-nav-root">
        
        <!-- Top Sub-Navigation Menu Bar (Bento Frame Layer) -->
        <div class="afdp-top-nav-wrapper no-print">
            <div class="dpt-nav-button-group">
                <a href="<?php echo esc_url( $all_fees_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'list' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php esc_html_e( 'All Fee Invoices', 'ifsedu-sms' ); ?>
                </a>
                
                <a href="<?php echo esc_url( $collect_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'collect' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( '+ Collect Student Fee', 'ifsedu-sms' ); ?>
                </a>
            </div>

            <?php if ( $sub_tab === 'print' ) : ?>
                <div>
                    <span class="afdp-context-badge">
                        <span class="dashicons dashicons-printer" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php esc_html_e( 'Printing Invoice Receipt', 'ifsedu-sms' ); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- System Financial Routing Execution Core -->
        <div class="dpt-module-viewport-container">
            <?php
            switch ( $sub_tab ) {
                case 'collect':
                    if ( function_exists( 'educore_fees_collect_view' ) ) {
                        educore_fees_collect_view();
                    } else {
                        echo '<div class="afdp-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> ' . esc_html__( 'Fee Collection module is initializing. Define educore_fees_collect_view().', 'ifsedu-sms' ) . '</div>';
                    }
                    break;

                case 'print':
                    if ( function_exists( 'educore_fees_invoice_print_view' ) ) {
                        educore_fees_invoice_print_view();
                    } else {
                        echo '<div class="afdp-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> ' . esc_html__( 'Invoice Print module is initializing. Define educore_fees_invoice_print_view().', 'ifsedu-sms' ) . '</div>';
                    }
                    break;

                case 'list':
                default:
                    if ( function_exists( 'educore_fees_list_view' ) ) {
                        educore_fees_list_view();
                    } else {
                        echo '<div class="afdp-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> ' . esc_html__( 'Fees List View module is initializing. Define educore_fees_list_view().', 'ifsedu-sms' ) . '</div>';
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}