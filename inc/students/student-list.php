<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Render the Core Students Datatable powered by DataTables.js Engine
 * Database Scope: sms_students
 * File: students-list-view.php
 */
function educore_students_list_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';

    // ১. ওয়ার্ডপ্রেস স্ট্যান্ডার্ড অনুযায়ী রানটাইমে ডাটাটেবিল অ্যাসেটস ইনজেক্ট করা
    wp_enqueue_style( 'datatables-cdn', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', array(), '1.13.6' );
    wp_enqueue_script( 'datatables-cdn-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array( 'jquery' ), '1.13.6', true );

    // ২. ডাটাবেজ থেকে সরাসরি সমস্ত অ্যাক্টিভ স্টুডেন্টদের ডাটা লোড (Natural Numeric Roll Sorting)
    $students_records = $wpdb->get_results( 
        "SELECT * FROM {$table_students} WHERE status = 'Active' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC, CAST(roll_no AS UNSIGNED) ASC, roll_no ASC" 
    );

    // ৩. এক্সট্রাক্ট ক্লাস ফর ড্রপডাউন এক্সটার্নাল ফিল্টারিং
    $available_classes = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$table_students} WHERE status = 'Active' AND class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    ?>

    <style>
        /* Modern Bento & Glassmorphism Framework for DataTables */
        .educore-dt-container {
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
            margin-top: 20px;
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
            border-radius: 6px;
            font-size: 14px;
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
        
        /* DataTables Core Overrides to Match Elite UI Theme */
        table.dataTable {
            border-collapse: collapse !important;
            margin-top: 15px !important;
            margin-bottom: 15px !important;
            border: 1px solid #e2e8f0 !important;
        }
        table.dataTable thead th {
            background: #f8fafc !important;
            color: #475569 !important;
            font-weight: 600 !important;
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
            font-size: 14px !important;
            vertical-align: middle !important;
        }
        table.dataTable tbody tr:hover td {
            background-color: #f8fafc !important;
        }
        
        /* Custom UI Badges & Action Typography */
        .educore-badge-gender {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .gender-male { background: #e0f2fe; color: #0369a1; }
        .gender-female { background: #fce7f3; color: #be185d; }
        
        .educore-row-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        .educore-link-action {
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            transition: color 0.15s ease;
        }
        .action-view { color: #3b82f6; }
        .action-view:hover { color: #2563eb; }
        .action-edit { color: #10b981; }
        .action-edit:hover { color: #059669; }
        .action-delete { color: #ef4444; }
        .action-delete:hover { color: #dc2626; }

        /* DataTables Control Elements Styling */
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 6px 12px !important;
            background-color: #fff !important;
            margin-left: 8px !important;
            width: 260px !important;
            height: 38px !important;
        }
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 4px 8px !important;
            height: 38px !important;
            background-color: #fff !important;
        }

        /* Fixed Footer Layout for Pagination & Info Controls */
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
            font-weight: 500;
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
            font-weight: 500 !important;
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
        
        <!-- External Advanced Bento Toolbar Grid -->
        <div class="educore-dt-toolbar">
            <div class="educore-dt-filter-box">
                <!-- Class Custom Select Unit -->
                <div class="educore-filter-group">
                    <label for="educoreClassCustomFilter" style="font-weight: 600; color: #475569; font-size: 14px; white-space: nowrap;">
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

                <!-- Dependent Dynamic Section Select Unit -->
                <div class="educore-filter-group">
                    <label for="educoreSectionCustomFilter" style="font-weight: 600; color: #475569; font-size: 14px; white-space: nowrap;">
                        <?php esc_html_e( 'Section:', 'educore' ); ?>
                    </label>
                    <select id="educoreSectionCustomFilter" class="educore-select-element" disabled>
                        <option value=""><?php esc_html_e( 'Select Class First', 'educore' ); ?></option>
                    </select>
                </div>
            </div>
            <div id="educoreDtSearchTarget"></div>
        </div>

        <!-- DOM Data Table Target -->
        <table id="educoreStudentsMainTable" class="display stripe hover cell-border" style="width:100%">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Student ID', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Full Name', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Academic Class', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Roll No', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Gender', 'educore' ); ?></th>
                    <th><?php esc_html_e( 'Guardian Contacts', 'educore' ); ?></th>
                    <th style="text-align: right; white-space: nowrap;"><?php esc_html_e( 'System Actions', 'educore' ); ?></th>
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
                            <td class="fw-bold"><code><?php echo esc_html( $student->student_id ); ?></code></td>
                            <td>
                                <div style="font-weight: 600; color: #0f172a;"><?php echo esc_html( $student->full_name ); ?></div>
                                <?php if ( ! empty( $student->name_bn ) ) : ?>
                                    <small style="color: #94a3b8; font-size: 11px;"><?php echo esc_html( $student->name_bn ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?php echo esc_html( $student->class_name ); ?></div>
                                <small style="color: #64748b; font-size: 11px;">Section: <?php echo esc_html( $student->section_name ? $student->section_name : 'N/A' ); ?></small>
                            </td>
                            <td style="font-weight: 700; color: #334155;" data-order="<?php echo esc_attr( intval( $student->roll_no ) ); ?>">
                                #<?php echo esc_html( $student->roll_no ); ?>
                            </td>
                            <td>
                                <span class="educore-badge-gender <?php echo esc_attr( $gender_style ); ?>">
                                    <?php echo esc_html( ucfirst( $student->gender ) ); ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?php echo esc_html( $student->guardian_name ); ?></div>
                                <div style="font-size: 12px; color: #64748b;"><span class="dashicons dashicons-phone" style="font-size: 12px; width:12px; height:12px; vertical-align:middle;"></span> <?php echo esc_html( $phone_display ); ?></div>
                            </td>
                            <td style="text-align: right;">
                                <div class="educore-row-actions">
                                    <a href="<?php echo esc_url( $view_url ); ?>" class="educore-link-action action-view">
                                        <span class="dashicons dashicons-visibility me-1" style="font-size: 16px;"></span> Profile
                                    </a>
                                    <span style="color: #cbd5e1;">|</span>
                                    <a href="<?php echo esc_url( $edit_url ); ?>" class="educore-link-action action-edit">
                                        <span class="dashicons dashicons-edit me-1" style="font-size: 16px;"></span> Edit
                                    </a>
                                    <span style="color: #cbd5e1;">|</span>
                                    <a href="<?php echo esc_url( $delete_url ); ?>" class="educore-link-action action-delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to completely drop this student file?', 'educore' ) ); ?>');">
                                        <span class="dashicons dashicons-trash me-1" style="font-size: 16px;"></span> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Target Render Element for Re-positioned Footer Pagination Controls -->
        <div id="educoreDtFooterTarget" class="educore-dt-footer-layout"></div>
    </div>

    <!-- DataTables Engine Instantiation Handler Script -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if ($.fn.DataTable) {

            // ১. কাস্টম ফিল্টার রেজিস্টার আগে করা হচ্ছে (ইনিশিয়ালাইজেশনের পূর্বে)
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

            // ২. ডাটাটেবিল ইন্সট্যান্স তৈরি
            var tableInstance = $('#educoreStudentsMainTable').DataTable({
                "pageLength": 20,
                "lengthMenu": [10, 20, 50, 100],
                "ordering": true,
                "order": [[2, "asc"], [3, "asc"]],
                "responsive": true,
                "dom": 'f t <"educore-dt-footer-internal"lip>',
                "language": {
                    "search": "",
                    "searchPlaceholder": "Search anything..."
                }
            });

            // ৩. ক্লাস ফিল্টার চেইঞ্জ হ্যান্ডলার ও ডায়নামিক সেকশন পপুলেশন
            $('#educoreClassCustomFilter').on('change', function() {
                var selectedClass = $.trim($(this).val());
                var sectionFilter = $('#educoreSectionCustomFilter');
                
                sectionFilter.val('').empty().append('<option value="">All Sections</option>');

                if (selectedClass !== '') {
                    var uniqueSections = [];

                    // API দিয়ে সব রো স্ক্যান করা
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
                
                // রি-ড্র ডাটাটেবিল
                tableInstance.draw();
            });

            // ৪. সেকশন ফিল্টার ট্র্যাকার
            $('#educoreSectionCustomFilter').on('change', function() {
                tableInstance.draw();
            });

            // ৫. UI Element Placement Adjustments
            $('.dataTables_filter').appendTo('#educoreDtSearchTarget');
            $('.educore-dt-footer-internal').appendTo('#educoreDtFooterTarget');
        }
    });
    </script>
    <?php
}