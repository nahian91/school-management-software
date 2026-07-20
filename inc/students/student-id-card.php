<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

/**
 * High-End Academic ID Card Engine & Layout Compiler
 * Custom Prefixes Applied: dpt-, afdp-
 * Standard CR80 Grid Metrics System Incorporated
 */
function educore_student_id_card_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Fetch classes dynamically from academic units table
    $academic_units = $wpdb->get_results( "SELECT * FROM {$table_units} ORDER BY unit_type DESC, class_name ASC" );

    // Filter values
    $selected_unit_id = isset( $_GET['academic_unit_id'] ) ? intval( $_GET['academic_unit_id'] ) : 0;
    
    $students = array();
    $selected_unit = null;

    if ( $selected_unit_id > 0 ) {
        $selected_unit = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_units} WHERE id = %d", $selected_unit_id ) );
        
        if ( $selected_unit ) {
            if ( $selected_unit->unit_type === 'College' ) {
                $query = $wpdb->prepare(
                    "SELECT * FROM {$table_students} WHERE status = 'Active' AND class_name = %s ORDER BY roll_no ASC",
                    $selected_unit->class_name
                );
            } else {
                $query = $wpdb->prepare(
                    "SELECT * FROM {$table_students} WHERE status = 'Active' AND class_name = %s AND section_name = %s ORDER BY roll_no ASC",
                    $selected_unit->class_name,
                    $selected_unit->section_name
                );
            }
            $students = $wpdb->get_results( $query );
        }
    }
    ?>

    <style>
        /* ==========================================================================
           1. MODERN ENGINE CONTAINER & BENTO LAYOUT (SCREEN VIEW)
           ========================================================================== */
        .dpt-id-engine-root {
            margin: 24px 20px 0 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
        }

        /* Bento-Style Form Card Styling */
        .afdp-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.03), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
            margin-bottom: 32px;
        }
        .afdp-bento-card h4 {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 24px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.5px;
        }
        .afdp-bento-card h4 .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
            color: #006a4e;
        }

        /* Form Structure Grid Wrapper */
        .dpt-form-grid-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            align-items: flex-end;
        }
        .dpt-input-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .dpt-input-block label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }
        .dpt-input-block select {
            width: 100%;
            height: 42px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 14px;
            font-size: 14px;
            color: #0f172a;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }
        .dpt-input-block select:focus {
            border-color: #006a4e;
            background: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
        }

        /* Action Controls */
        .dpt-action-block {
            display: flex;
            gap: 12px;
        }
        .dpt-btn {
            height: 42px;
            flex: 1;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .dpt-btn-primary {
            background: #006a4e;
            color: #ffffff;
        }
        .dpt-btn-primary:hover {
            background: #00523c;
            transform: translateY(-1px);
        }
        .dpt-btn-secondary {
            background: #10b981;
            color: #ffffff;
        }
        .dpt-btn-secondary:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        /* ==========================================================================
           2. STANDARD CR80 ID CARD DISPLAY MATRIX (SCREEN INTERFACE)
           ========================================================================== */
        .dpt-id-cards-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .id-card-wrapper {
            background: transparent;
            position: relative;
        }

        /* CR80 Standard Landscape Dimensions (3.375in x 2.125in Approx Ratio) */
        .id-card-box {
            width: 330px;
            height: 215px;
            border: 2px solid #0f172a;
            border-radius: 10px;
            background: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            font-family: sans-serif;
            box-sizing: border-box;
        }
        .id-card-header {
            background-color: #006a4e;
            color: #ffffff;
            padding: 8px;
            text-align: center;
        }
        .id-card-header h6 {
            margin: 0;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: -0.25px;
            line-height: 1.2;
        }
        .id-card-header small {
            font-size: 0.62rem;
            letter-spacing: 0.75px;
            opacity: 0.9;
            display: block;
            margin-top: 2px;
            font-weight: 600;
        }
        .id-card-body {
            padding: 10px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .id-photo-frame {
            width: 75px;
            height: 92px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            background: #f8fafc;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .id-photo-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .id-card-table {
            font-size: 0.76rem;
            width: 100%;
            border-collapse: collapse;
        }
        .id-card-table td {
            padding: 2px 0;
            vertical-align: top;
            border: none !important;
        }
        .id-card-table td strong {
            color: #0f172a;
        }
        .id-card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: #f1f5f9;
            padding: 5px 12px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.68rem;
            box-sizing: border-box;
        }
        .id-card-footer span strong {
            color: #ef4444;
        }

        /* Empty States Area */
        .afdp-empty-state {
            text-align: center;
            padding: 64px 24px;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            background: #ffffff;
            width: 100%;
        }
        .afdp-empty-state .dashicons {
            font-size: 40px;
            width: 40px;
            height: 40px;
            color: #94a3b8;
            margin-bottom: 12px;
        }
        .afdp-empty-state h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #64748b;
        }

        /* ==========================================================================
           3. HARDWARE PRINT ENGINE DIRECTIVES
           ========================================================================== */
        @media print {
            body * {
                visibility: hidden;
            }
            #educore-printable-id-area, 
            #educore-printable-id-area * {
                visibility: visible;
            }
            #educore-printable-id-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
            .dpt-id-cards-container {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 16px !important;
            }
            .id-card-wrapper {
                page-break-inside: avoid;
                break-inside: avoid;
                margin-bottom: 8px;
            }
            .id-card-box {
                border: 1px solid #000000 !important;
                box-shadow: none !important;
                background: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .id-card-header {
                background-color: #006a4e !important;
                color: #ffffff !important;
            }
            .id-card-footer {
                background-color: #f1f5f9 !important;
                border-top: 1px solid #e2e8f0 !important;
            }
        }
    </style>

    <div class="dpt-id-engine-root">
        
        <!-- Bento Filter Controller -->
        <div class="afdp-bento-card no-print">
            <h4>
                <span class="dashicons dashicons-id-alt"></span> Student ID Card Generator
            </h4>
            <form method="GET" action="" class="dpt-form-grid-wrapper">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="students">
                <input type="hidden" name="sub" value="id_card">

                <div class="dpt-input-block">
                    <label>Select Configured Academic Unit <span style="color:#ef4444;">*</span></label>
                    <select name="academic_unit_id" required>
                        <option value="">-- Choose Academic Class/Department --</option>
                        <?php foreach ( $academic_units as $unit ) : ?>
                            <option value="<?php echo intval( $unit->id ); ?>" <?php selected( $selected_unit_id, $unit->id ); ?>>
                                [<?php echo esc_html( $unit->unit_type ); ?>] 
                                <?php echo $unit->unit_type === 'College' ? 'Dept: ' . esc_html( $unit->dept_name ) . ' (' . esc_html( $unit->class_name ) . ')' : 'Class: ' . esc_html( $unit->class_name ) . ' - Sec: ' . esc_html( $unit->section_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dpt-action-block">
                    <button type="submit" class="dpt-btn dpt-btn-primary">
                        <span class="dashicons dashicons-filter"></span> Fetch Records
                    </button>
                    <?php if ( ! empty( $students ) ) : ?>
                        <button type="button" onclick="window.print();" class="dpt-btn dpt-btn-secondary">
                            <span class="dashicons dashicons-printer"></span> Print ID Cards
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Printable Canvas Target Output Area -->
        <?php if ( $selected_unit_id > 0 ) : ?>
            <div id="educore-printable-id-area">
                <?php if ( ! empty( $students ) ) : ?>
                    <div class="dpt-id-cards-container">
                        <?php foreach ( $students as $student ) : ?>
                            <div class="id-card-wrapper">
                                <div class="id-card-box">
                                    
                                    <!-- Card Branding Header -->
                                    <div class="id-card-header">
                                        <h6><?php echo esc_html( get_bloginfo('name') ); ?></h6>
                                        <small>STUDENT IDENTITY CARD</small>
                                    </div>
                                    
                                    <!-- Card Content Body Structure -->
                                    <div class="id-card-body">
                                        <div class="id-photo-frame">
                                            <?php if ( ! empty( $student->photo_url ) ) : ?>
                                                <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="Student Photo">
                                            <?php else : ?>
                                                <div style="font-size:0.58rem; color:#94a3b8; text-align:center; font-weight:700; letter-spacing:0.5px; line-height:1.2;">NO PHOTO<br>AVAILABLE</div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <table class="id-card-table">
                                            <tr>
                                                <td style="color:#64748b; width:32%;">ID No:</td>
                                                <td><strong><?php echo esc_html( $student->student_id ); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b;">Name:</td>
                                                <td><strong style="text-transform: uppercase;"><?php echo esc_html( $student->full_name ); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b;">Class/Year:</td>
                                                <td><?php echo esc_html( $student->class_name ); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b;">Sec/Dept:</td>
                                                <td><?php echo esc_html( $student->section_name ? $student->section_name : 'N/A' ); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b;">Roll No:</td>
                                                <td><?php echo esc_html( $student->roll_no ); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b;">Mobile:</td>
                                                <td><?php echo esc_html( $student->guardian_phone ); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    
                                    <!-- Card Verification Footer -->
                                    <div class="id-card-footer">
                                        <span>Blood: <strong><?php echo esc_html( $student->blood_group ? $student->blood_group : 'N/A' ); ?></strong></span>
                                        <span style="font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.25px; font-size: 0.62rem; border-top: 1px dashed #94a3b8; padding-top: 2px;">Principal</span>
                                    </div>
                                    
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="afdp-empty-state no-print">
                        <span class="dashicons dashicons-warning"></span>
                        <h5>No active student records matched this target academic group configuration.</h5>
                    </div>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="afdp-empty-state no-print" style="border-style: dashed; background: transparent;">
                <span class="dashicons dashicons-info"></span>
                <h5>Select an Academic Target configuration parameter above to compile student ID cards.</h5>
            </div>
        <?php endif; ?>
    </div>
    <?php
}