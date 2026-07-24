<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Render the Core Students Datatable powered by DataTables.js Engine
 * Database Scope: sms_students & sms_academic_units
 * File: students-list-view.php
 */
function educore_students_list_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // 1. WP Enqueue DataTables Assets
    wp_enqueue_style( 'datatables-cdn', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', array(), '1.13.6' );
    wp_enqueue_script( 'datatables-cdn-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array( 'jquery' ), '1.13.6', true );

    // 2. Fetch Active Student Records (Natural Numeric Roll Sorting)
    $students_records = $wpdb->get_results( 
        "SELECT * FROM {$table_students} WHERE status = 'Active' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC, CAST(roll_no AS UNSIGNED) ASC, roll_no ASC" 
    );

    // 3. Fetch ALL Classes directly from Academic Units Table with Natural Numeric Sorting
    $raw_classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM {$table_units} WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    $available_classes = array();

    if ( ! empty( $raw_classes ) ) {
        usort( $raw_classes, function( $a, $b ) {
            return strnatcasecmp( $a->class_name, $b->class_name );
        });
        foreach ( $raw_classes as $cls_obj ) {
            $available_classes[] = $cls_obj->class_name;
        }
    }
    ?>

    <style>
        /* Modern Bento Framework for DataTables */
        .educore-dt-container {
            background: #ffffff;
            padding: 24px;
            border-radius: 14px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            border: 1px solid #e2e8f0;
            margin-top: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .educore-dt-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .educore-dt-filter-box {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .educore-filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .educore-select-element {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13.5px;
            color: #334155;
            min-width: 180px;
            background-color: #fff;
            height: 38px;
        }
        .educore-select-element:disabled {
            background-color: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }
        
        /* DataTables Core Overrides */
        table.dataTable {
            border-collapse: collapse !important;
            margin-top: 15px !important;
            margin-bottom: 15px !important;
            border: 1px solid #e2e8f0 !important;
        }
        table.dataTable thead th {
            background: #f8fafc !important;
            color: #475569 !important;
            font-weight: 700 !important;
            padding: 14px 16px !important;
            border-bottom: 2px solid #e2e8f0 !important;
            font-size: 12px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }
        table.dataTable tbody td {
            padding: 14px 16px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            color: #334155 !important;
            font-size: 13.5px !important;
            vertical-align: middle !important;
        }
        table.dataTable tbody tr:hover td {
            background-color: #f8fafc !important;
        }
        
        /* Gender Badges */
        .educore-badge-gender {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-block;
        }
        .gender-male { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .gender-female { background: #fce7f3; color: #be185d; border: 1px solid #fbcfe8; }
        
        /* Modern SVG Action Buttons */
        .educore-row-actions {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            justify-content: flex-end;
        }

        .educore-btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            line-height: 1;
            cursor: pointer;
        }

        .educore-btn-action svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
            flex-shrink: 0;
        }

        /* View Profile Button */
        .educore-btn-view {
            background-color: #eff6ff;
            color: #2563eb;
            border-color: #bfdbfe;
        }
        .educore-btn-view:hover {
            background-color: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
        }

        /* Edit Button */
        .educore-btn-edit {
            background-color: #f0fdf4;
            color: #006a4e;
            border-color: #bbf7d0;
        }
        .educore-btn-edit:hover {
            background-color: #006a4e;
            color: #ffffff;
            border-color: #006a4e;
            box-shadow: 0 2px 6px rgba(0, 106, 78, 0.25);
        }

        /* Delete Button */
        .educore-btn-delete {
            background-color: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }
        .educore-btn-delete:hover {
            background-color: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);
        }

        /* DataTables Controls Styling */
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            padding: 6px 12px !important;
            background-color: #fff !important;
            margin-left: 8px !important;
            width: 240px !important;
            height: 38px !important;
        }
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            padding: 4px 8px !important;
            height: 38px !important;
            background-color: #fff !important;
        }

        .educore-dt-footer-layout {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 16px;
        }
        .dataTables_wrapper .dataTables_info {
            padding-top: 0 !important;
            color: #475569 !important;
            font-weight: 600;
            font-size: 13px;
        }
        .dataTables_wrapper .dataTables_paginate {
            padding-top: 0 !important;
            display: flex;
            gap: 4px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 6px 12px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            background: #ffffff !important;
            color: #475569 !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            transition: all 0.2s ease;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #006a4e !important;
            color: #fff !important;
            border: 1px solid #006a4e !important;
            border-radius: 6px !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f1f5f9 !important;
            color: #1e293b !important;
            border-color: #cbd5e1 !important;
        }
    </style>

    <div class="educore-dt-container">
        
        <!-- Filter Toolbar -->
        <div class="educore-dt-toolbar">
            <div class="educore-dt-filter-box">
                <!-- Class Filter (Loaded from Academic Units) -->
                <div class="educore-filter-group">
                    <label for="educoreClassCustomFilter" style="font-weight: 700; color: #475569; font-size: 13px; white-space: nowrap;">
                        <span class="dashicons dashicons-filter" style="font-size: 18px; vertical-align: middle; margin-right: 4px;"></span>
                        <?php esc_html_e( 'Filter Class:', 'educore' ); ?>
                    </label>
                    <select id="educoreClassCustomFilter" class="educore-select-element">
                        <option value=""><?php esc_html_e( 'Show All Classes', 'educore' ); ?></option>
                        <?php if ( ! empty( $available_classes ) ) : ?>
                            <?php foreach ( $available_classes as $class_name ) : ?>
                                <option value="<?php echo esc_attr( $class_name ); ?>"><?php echo esc_html( $class_name ); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Section Filter -->
                <div class="educore-filter-group">
                    <label for="educoreSectionCustomFilter" style="font-weight: 700; color: #475569; font-size: 13px; white-space: nowrap;">
                        <?php esc_html_e( 'Section:', 'educore' ); ?>
                    </label>
                    <select id="educoreSectionCustomFilter" class="educore-select-element" disabled>
                        <option value=""><?php esc_html_e( 'Select Class First', 'educore' ); ?></option>
                    </select>
                </div>
            </div>
            <div id="educoreDtSearchTarget"></div>
        </div>

        <!-- Main Students DataTable -->
        <table id="educoreStudentsMainTable" class="display stripe hover cell-border" style="width:100%">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Student ID', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Full Name', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Academic Class', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Roll No', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Gender', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Guardian Contacts', 'educore' ); ?></th>
                    <th style="text-align: right; white-space: nowrap;"><?php esc_html_e( 'Actions', 'educore' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $students_records ) ) : ?>
                    <?php foreach ( $students_records as $student ) : 
                        $view_url     = admin_url( 'admin.php?page=school_management_system&tab=students&sub=view&id=' . absint( $student->id ) );
                        $edit_url     = admin_url( 'admin.php?page=school_management_system&tab=students&sub=edit&id=' . absint( $student->id ) );
                        $delete_url   = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=delete&id=' . absint( $student->id ) ), 'delete_student_' . $student->id );
                        $gender_style = ( strtolower( trim( $student->gender ) ) === 'male' ) ? 'gender-male' : 'gender-female';
                        $phone_display= ! empty( $student->student_phone ) ? $student->student_phone : $student->guardian_phone;
                    ?>
                        <tr data-class="<?php echo esc_attr( trim( $student->class_name ) ); ?>" data-section="<?php echo esc_attr( trim( $student->section_name ) ); ?>">
                            <td class="fw-bold"><code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; border:1px solid #cbd5e1; font-weight:700; color:#0f172a;"><?php echo esc_html( $student->student_id ); ?></code></td>
                            <td>
                                <div style="font-weight: 700; color: #0f172a;"><?php echo esc_html( $student->full_name ); ?></div>
                                <?php if ( ! empty( $student->name_bn ) ) : ?>
                                    <small style="color: #64748b; font-size: 11.5px;"><?php echo esc_html( $student->name_bn ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 600; color:#006a4e;"><?php echo esc_html( $student->class_name ); ?></div>
                                <small style="color: #64748b; font-size: 11.5px;">Section: <?php echo esc_html( $student->section_name ? $student->section_name : 'N/A' ); ?></small>
                            </td>
                            <td style="font-weight: 800; color: #334155;" data-order="<?php echo esc_attr( intval( $student->roll_no ) ); ?>">
                                #<?php echo esc_html( $student->roll_no ); ?>
                            </td>
                            <td>
                                <span class="educore-badge-gender <?php echo esc_attr( $gender_style ); ?>">
                                    <?php echo esc_html( ucfirst( $student->gender ) ); ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color:#1e293b;"><?php echo esc_html( $student->guardian_name ); ?></div>
                                <div style="font-size: 12px; color: #64748b;"><span class="dashicons dashicons-phone" style="font-size: 12px; width:12px; height:12px; vertical-align:middle;"></span> <?php echo esc_html( $phone_display ); ?></div>
                            </td>
                            <td style="text-align: right;">
                                <div class="educore-row-actions">
                                    <!-- View Profile SVG Button -->
                                    <a href="<?php echo esc_url( $view_url ); ?>" class="educore-btn-action educore-btn-view" title="<?php esc_attr_e( 'View Profile', 'educore' ); ?>">
                                        <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                        Profile
                                    </a>

                                    <!-- Edit SVG Button -->
                                    <a href="<?php echo esc_url( $edit_url ); ?>" class="educore-btn-action educore-btn-edit" title="<?php esc_attr_e( 'Edit Record', 'educore' ); ?>">
                                        <svg viewBox="0 0 24 24"><path d="M3 17.25V21h4.75L17.81 9.94l-4.75-4.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 4.75 4.75 1.83-1.83z"/></svg>
                                        Edit
                                    </a>

                                    <!-- Delete SVG Button -->
                                    <a href="<?php echo esc_url( $delete_url ); ?>" class="educore-btn-action educore-btn-delete" title="<?php esc_attr_e( 'Delete Record', 'educore' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to completely drop this student file?', 'educore' ) ); ?>');">
                                        <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                        Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Target Element for Footer Controls -->
        <div id="educoreDtFooterTarget" class="educore-dt-footer-layout"></div>
    </div>

    <!-- Script Layer -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if ($.fn.DataTable) {

            // 1. Custom DataTables Search Filter Push
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'educoreStudentsMainTable') {
                        return true;
                    }

                    var rowNode = settings.aoData[dataIndex].nTr;
                    var rowClass = $.trim($(rowNode).attr('data-class') || '');
                    var rowSection = $.trim($(rowNode).attr('data-section') || '');
                    
                    var selectedClass = $.trim($('#educoreClassCustomFilter').val() || '');
                    var selectedSection = $.trim($('#educoreSectionCustomFilter').val() || '');
                    
                    if (selectedClass !== '' && rowClass !== selectedClass) {
                        return false;
                    }
                    if (selectedSection !== '' && rowSection !== selectedSection) {
                        return false;
                    }
                    return true;
                }
            );

            // 2. Instantiate DataTable
            var tableInstance = $('#educoreStudentsMainTable').DataTable({
                "pageLength": 20,
                "lengthMenu": [10, 20, 50, 100],
                "ordering": true,
                "order": [[2, "asc"], [3, "asc"]],
                "responsive": true,
                "dom": 'f t <"educore-dt-footer-internal"lip>',
                "language": {
                    "search": "",
                    "searchPlaceholder": "Search student name, ID, roll..."
                }
            });

            // 3. Class Filter Change Handler & Dynamic Section Population
            $('#educoreClassCustomFilter').on('change', function() {
                var selectedClass = $.trim($(this).val());
                var sectionFilter = $('#educoreSectionCustomFilter');
                
                sectionFilter.val('').empty().append('<option value="">All Sections</option>');

                if (selectedClass !== '') {
                    var uniqueSections = [];

                    tableInstance.rows().every(function() {
                        var node = this.node();
                        var rowClass = $.trim($(node).attr('data-class') || '');
                        var rowSection = $.trim($(node).attr('data-section') || '');

                        if (rowClass === selectedClass && rowSection !== '' && $.inArray(rowSection, uniqueSections) === -1) {
                            uniqueSections.push(rowSection);
                        }
                    });

                    if (uniqueSections.length > 0) {
                        uniqueSections.sort();
                        $.each(uniqueSections, function(index, value) {
                            sectionFilter.append('<option value="' + value + '">' + value + '</option>');
                        });
                        sectionFilter.prop('disabled', false);
                    } else {
                        sectionFilter.prop('disabled', true);
                    }
                } else {
                    sectionFilter.empty().append('<option value="">Select Class First</option>').prop('disabled', true);
                }
                
                tableInstance.draw();
            });

            // 4. Section Filter Change Handler
            $('#educoreSectionCustomFilter').on('change', function() {
                tableInstance.draw();
            });

            // 5. DOM Placement Adjustments
            $('.dataTables_filter').appendTo('#educoreDtSearchTarget');
            $('.educore-dt-footer-internal').appendTo('#educoreDtFooterTarget');
        }
    });
    </script>
    <?php
}