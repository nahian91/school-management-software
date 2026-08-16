<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Enterprise Core Students Directory & Interactive DataTables Workspace
 * Database Scope: sms_students & sms_academic_units
 * File: students-list-view.php
 */
function educore_students_list_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access the student directory.', 'ifsedu-sms' ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // 1. WP Enqueue DataTables Assets
    wp_enqueue_style( 'datatables-cdn', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', array(), '1.13.6' );
    wp_enqueue_script( 'datatables-cdn-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array( 'jquery' ), '1.13.6', true );

    // 2. Fetch Active Student Records (Latest Added/Edited First: ORDER BY id DESC)
    $students_records = $wpdb->get_results( 
        "SELECT * FROM {$table_students} WHERE status = 'Active' ORDER BY id DESC" 
    );

    // 3. Fetch Classes & Sections Map with Natural Numeric Sorting (1, 2, 3 ... 10)
    $raw_units = $wpdb->get_results( "SELECT class_name, section_name, dept_name FROM {$table_units} WHERE class_name != ''" );
    $class_section_map = array();
    $available_classes  = array();

    if ( ! empty( $raw_units ) ) {
        foreach ( $raw_units as $unit ) {
            $c_name = trim( $unit->class_name );
            if ( ! isset( $class_section_map[ $c_name ] ) ) {
                $class_section_map[ $c_name ] = array();
                $available_classes[] = $c_name;
            }
            if ( ! empty( $unit->section_name ) ) {
                $class_section_map[ $c_name ][] = trim( $unit->section_name );
            }
            if ( ! empty( $unit->dept_name ) ) {
                $class_section_map[ $c_name ][] = trim( $unit->dept_name );
            }
        }

        foreach ( $class_section_map as $c_name => $secs ) {
            $class_section_map[ $c_name ] = array_values( array_unique( array_filter( $secs ) ) );
            usort( $class_section_map[ $c_name ], 'strnatcasecmp' );
        }

        $available_classes = array_values( array_unique( $available_classes ) );
        usort( $available_classes, 'strnatcasecmp' );
    }
    ?>

    <style>
        .educore-dt-container {
            background: #ffffff;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
            margin-top: 20px;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
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
            gap: 14px;
            flex-wrap: wrap;
        }

        .educore-filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .educore-select-element {
            padding: 0 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13.5px;
            color: #0f172a;
            min-width: 170px;
            background-color: #f8fafc;
            height: 38px;
            transition: all 0.2s ease;
        }

        .educore-select-element:focus {
            border-color: #006a4e;
            background-color: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.12);
        }

        .educore-select-element:disabled {
            background-color: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        /* Avatar Thumbnail & Student Name */
        .educore-avatar-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .educore-avatar-img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
            background: #f1f5f9;
            flex-shrink: 0;
        }

        .educore-avatar-fallback {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #e6f4f1;
            color: #006a4e;
            font-weight: 800;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid #a7f3d0;
        }

        /* DataTables Core Overrides */
        table.dataTable {
            border-collapse: separate !important;
            border-spacing: 0 !important;
            margin-top: 15px !important;
            margin-bottom: 15px !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            overflow: hidden;
        }

        table.dataTable thead th {
            background: #f8fafc !important;
            color: #475569 !important;
            font-weight: 700 !important;
            padding: 14px 16px !important;
            border-bottom: 1px solid #e2e8f0 !important;
            font-size: 12px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }

        table.dataTable tbody td {
            padding: 12px 16px !important;
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
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-block;
        }
        .gender-male { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .gender-female { background: #fce7f3; color: #be185d; border: 1px solid #fbcfe8; }

        /* Action Buttons */
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
            gap: 4px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .educore-btn-action svg { width: 14px; height: 14px; fill: currentColor; }

        .educore-btn-view { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
        .educore-btn-view:hover { background: #2563eb; color: #ffffff; }

        .educore-btn-edit { background: #f0fdf4; color: #006a4e; border-color: #bbf7d0; }
        .educore-btn-edit:hover { background: #006a4e; color: #ffffff; }

        .educore-btn-delete { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .educore-btn-delete:hover { background: #dc2626; color: #ffffff; }

        /* DataTables Controls */
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            padding: 6px 12px !important;
            background-color: #f8fafc !important;
            margin-left: 8px !important;
            width: 220px !important;
            height: 38px !important;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            background-color: #ffffff !important;
            border-color: #006a4e !important;
            outline: none;
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
            color: #475569 !important;
            font-weight: 600;
            font-size: 13px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 6px 12px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            background: #ffffff !important;
            color: #475569 !important;
            font-weight: 600 !important;
            font-size: 13px !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #006a4e !important;
            color: #ffffff !important;
            border-color: #006a4e !important;
        }
    </style>

    <div class="educore-dt-container">

        <!-- Dynamic Success / Update Notice Alerts -->
        <?php if ( isset( $_GET['msg'] ) || isset( $_GET['status'] ) ) : 
            $status_msg = isset( $_GET['msg'] ) ? sanitize_text_field( $_GET['msg'] ) : sanitize_text_field( $_GET['status'] );
        ?>
            <?php if ( $status_msg === 'success' ) : ?>
                <div class="notice notice-success is-dismissible" style="padding: 12px 16px; margin: 0 0 20px 0; background: #ecfdf5; border-left: 4px solid #006a4e; color: #065f46; border-radius: 8px; font-weight: 600;">
                    <p style="margin: 0;"><span class="dashicons dashicons-yes-alt" style="color: #006a4e; vertical-align: middle; margin-right: 5px;"></span> <?php esc_html_e( 'শিক্ষার্থীর তথ্য সফলভাবে সংরক্ষণ করা হয়েছে।', 'ifsedu-sms' ); ?></p>
                </div>
            <?php elseif ( $status_msg === 'updated' ) : ?>
                <div class="notice notice-success is-dismissible" style="padding: 12px 16px; margin: 0 0 20px 0; background: #eff6ff; border-left: 4px solid #2563eb; color: #1e40af; border-radius: 8px; font-weight: 600;">
                    <p style="margin: 0;"><span class="dashicons dashicons-saved" style="color: #2563eb; vertical-align: middle; margin-right: 5px;"></span> <?php esc_html_e( 'শিক্ষার্থীর প্রোফাইল সফলভাবে হালনাগাদ (Update) করা হয়েছে।', 'ifsedu-sms' ); ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Filter & Search Toolbar -->
        <div class="educore-dt-toolbar">
            <div class="educore-dt-filter-box">
                <div class="educore-filter-group">
                    <label for="educoreClassCustomFilter" style="font-weight: 700; color: #475569; font-size: 13px; white-space: nowrap;">
                        <span class="dashicons dashicons-filter" style="font-size: 18px; vertical-align: middle; margin-right: 4px;"></span>
                        <?php esc_html_e( 'Filter Class:', 'ifsedu-sms' ); ?>
                    </label>
                    <select id="educoreClassCustomFilter" class="educore-select-element">
                        <option value=""><?php esc_html_e( 'Show All Classes', 'ifsedu-sms' ); ?></option>
                        <?php foreach ( $available_classes as $class_name ) : ?>
                            <option value="<?php echo esc_attr( $class_name ); ?>"><?php echo esc_html( $class_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="educore-filter-group">
                    <label for="educoreSectionCustomFilter" style="font-weight: 700; color: #475569; font-size: 13px; white-space: nowrap;">
                        <?php esc_html_e( 'Section:', 'ifsedu-sms' ); ?>
                    </label>
                    <select id="educoreSectionCustomFilter" class="educore-select-element" disabled>
                        <option value=""><?php esc_html_e( 'Select Class First', 'ifsedu-sms' ); ?></option>
                    </select>
                </div>
            </div>

            <div id="educoreDtSearchTarget"></div>
        </div>

        <!-- Main DataTable -->
        <table id="educoreStudentsMainTable" class="display stripe hover cell-border" style="width:100%">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Student ID', 'ifsedu-sms' ); ?></th>
                    <th><?php esc_html_e( 'Student Name', 'ifsedu-sms' ); ?></th>
                    <th><?php esc_html_e( 'Academic Class', 'ifsedu-sms' ); ?></th>
                    <th><?php esc_html_e( 'Roll No', 'ifsedu-sms' ); ?></th>
                    <th><?php esc_html_e( 'Gender', 'ifsedu-sms' ); ?></th>
                    <th><?php esc_html_e( 'Guardian Contact', 'ifsedu-sms' ); ?></th>
                    <th style="text-align: right; white-space: nowrap;"><?php esc_html_e( 'Actions', 'ifsedu-sms' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $students_records ) ) : foreach ( $students_records as $student ) : 
                    $view_url      = admin_url( 'admin.php?page=school_management_system&tab=students&sub=view&id=' . absint( $student->id ) );
                    $edit_url      = admin_url( 'admin.php?page=school_management_system&tab=students&sub=edit&id=' . absint( $student->id ) );
                    $delete_url    = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=delete&id=' . absint( $student->id ) ), 'delete_student_' . $student->id );
                    $gender_style  = ( strtolower( trim( $student->gender ) ) === 'male' ) ? 'gender-male' : 'gender-female';
                    $phone_display = ! empty( $student->student_phone ) ? $student->student_phone : $student->guardian_phone;
                    $first_letter  = mb_substr( $student->full_name, 0, 1 );
                ?>
                    <tr data-class="<?php echo esc_attr( trim( $student->class_name ) ); ?>" data-section="<?php echo esc_attr( trim( $student->section_name ) ); ?>" data-id="<?php echo esc_attr( $student->id ); ?>">
                        <td><code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; border:1px solid #cbd5e1; font-weight:700; color:#0f172a;"><?php echo esc_html( $student->student_id ); ?></code></td>
                        <td>
                            <div class="educore-avatar-cell">
                                <?php if ( ! empty( $student->photo_url ) ) : ?>
                                    <img src="<?php echo esc_url( $student->photo_url ); ?>" class="educore-avatar-img" alt="Avatar">
                                <?php else : ?>
                                    <div class="educore-avatar-fallback"><?php echo esc_html( strtoupper( $first_letter ) ); ?></div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight: 700; color: #0f172a;"><?php echo esc_html( $student->full_name ); ?></div>
                                </div>
                            </div>
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
                            <div style="font-weight: 600; color:#1e293b;"><?php echo esc_html( $student->guardian_name ? $student->guardian_name : $student->father_name ); ?></div>
                            <div style="font-size: 12px; color: #64748b;"><span class="dashicons dashicons-phone" style="font-size: 12px; width:12px; height:12px; vertical-align:middle;"></span> <?php echo esc_html( $phone_display ); ?></div>
                        </td>
                        <td style="text-align: right;">
                            <div class="educore-row-actions">
                                <a href="<?php echo esc_url( $view_url ); ?>" class="educore-btn-action educore-btn-view" title="<?php esc_attr_e( 'View Profile', 'ifsedu-sms' ); ?>">
                                    <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                    Profile
                                </a>

                                <a href="<?php echo esc_url( $edit_url ); ?>" class="educore-btn-action educore-btn-edit" title="<?php esc_attr_e( 'Edit Record', 'ifsedu-sms' ); ?>">
                                    <svg viewBox="0 0 24 24"><path d="M3 17.25V21h4.75L17.81 9.94l-4.75-4.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 4.75 4.75 1.83-1.83z"/></svg>
                                    Edit
                                </a>

                                <a href="<?php echo esc_url( $delete_url ); ?>" class="educore-btn-action educore-btn-delete" title="<?php esc_attr_e( 'Delete Record', 'ifsedu-sms' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to completely delete this student file?', 'ifsedu-sms' ) ); ?>');">
                                    <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                    Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <div id="educoreDtFooterTarget" class="educore-dt-footer-layout"></div>
    </div>

    <!-- DataTables Integration Scripts -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if ($.fn.DataTable) {

            var classSectionMap = <?php echo wp_json_encode( $class_section_map ); ?>;

            // Custom Filtering Logic
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'educoreStudentsMainTable') return true;

                    var rowNode         = settings.aoData[dataIndex].nTr;
                    var rowClass        = $.trim($(rowNode).attr('data-class') || '');
                    var rowSection      = $.trim($(rowNode).attr('data-section') || '');
                    var selectedClass   = $.trim($('#educoreClassCustomFilter').val() || '');
                    var selectedSection = $.trim($('#educoreSectionCustomFilter').val() || '');

                    if (selectedClass !== '' && rowClass !== selectedClass) return false;
                    if (selectedSection !== '' && rowSection !== selectedSection) return false;
                    return true;
                }
            );

            // Instantiate DataTable
            var tableInstance = $('#educoreStudentsMainTable').DataTable({
                "pageLength": 20,
                "lengthMenu": [10, 20, 50, 100],
                "ordering": true,
                "order": [], // Preserves server-side ORDER BY id DESC
                "responsive": true,
                "dom": 'f t <"educore-dt-footer-internal"lip>',
                "language": {
                    "search": "",
                    "searchPlaceholder": "Search student name, ID, roll..."
                }
            });

            // Dynamic Section Filter Population based on Academic Units Mapping
            $('#educoreClassCustomFilter').on('change', function() {
                var selectedClass = $.trim($(this).val());
                var sectionFilter = $('#educoreSectionCustomFilter');

                sectionFilter.empty();

                if (selectedClass !== '' && classSectionMap[selectedClass] && classSectionMap[selectedClass].length > 0) {
                    sectionFilter.append('<option value="">All Sections</option>');
                    $.each(classSectionMap[selectedClass], function(index, secName) {
                        sectionFilter.append('<option value="' + secName + '">' + secName + '</option>');
                    });
                    sectionFilter.prop('disabled', false);
                } else if (selectedClass !== '') {
                    sectionFilter.append('<option value="">No Sections Available</option>');
                    sectionFilter.prop('disabled', true);
                } else {
                    sectionFilter.append('<option value="">Select Class First</option>');
                    sectionFilter.prop('disabled', true);
                }

                sectionFilter.val('');
                tableInstance.draw();
            });

            $('#educoreSectionCustomFilter').on('change', function() {
                tableInstance.draw();
            });

            // Move DataTables elements into custom container targets
            $('.dataTables_filter').appendTo('#educoreDtSearchTarget');
            $('.educore-dt-footer-internal').appendTo('#educoreDtFooterTarget');
        }
    });
    </script>
    <?php
}