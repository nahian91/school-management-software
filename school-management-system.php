<?php
/**
 * Plugin Name: EduCore - School Management System
 * Description: Standalone, high-performance management system for Schools featuring student admissions, attendance, fees, exams, and HR.
 * Version:     1.0.0
 * Author:      DevNahian
 * Text Domain: educore-sms
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/*--------------------------------------------------------------
# 1. Constants & Definitions
--------------------------------------------------------------*/
if ( ! defined( 'EDUCORE_VERSION' ) ) {
    define( 'EDUCORE_VERSION', '1.0.0' );
}
if ( ! defined( 'EDUCORE_PATH' ) ) {
    define( 'EDUCORE_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'EDUCORE_URL' ) ) {
    define( 'EDUCORE_URL', plugin_dir_url( __FILE__ ) );
}

/*--------------------------------------------------------------
# 2. Role-Based Access Control (RBAC) Utility
--------------------------------------------------------------*/
function educore_has_access( $allowed_roles = array() ) {
    if ( empty( $allowed_roles ) ) {
        return true;
    }
    
    $current_user = wp_get_current_user();
    if ( ! $current_user || ! $current_user->exists() ) {
        return false;
    }

    // Super Admin explicitly bypasses all checks
    if ( in_array( 'administrator', $current_user->roles, true ) || current_user_can( 'manage_options' ) ) {
        return true;
    }

    foreach ( $allowed_roles as $role ) {
        if ( in_array( $role, $current_user->roles, true ) ) {
            return true;
        }
    }
    
    return false;
}

/*--------------------------------------------------------------
# 3. Scripts & Styles Enqueue
--------------------------------------------------------------*/
function educore_admin_enqueue_assets( $hook ) {
    if ( $hook !== 'toplevel_page_school_management_system' ) {
        return;
    }

    $plugin_uri = EDUCORE_URL;

    /* =====================
       Styles
    ===================== */
    wp_enqueue_style( 'bootstrap', $plugin_uri . 'assets/css/bootstrap.min.css', array(), EDUCORE_VERSION );
    wp_enqueue_style( 'datatables', $plugin_uri . 'assets/css/jquery.dataTables.min.css', array(), EDUCORE_VERSION );
    wp_enqueue_style( 'main-style', $plugin_uri . 'assets/css/style.css', array(), EDUCORE_VERSION );
    wp_enqueue_style( 'educore-admin-style', $plugin_uri . 'assets/css/admin-style.css', array(), EDUCORE_VERSION );

    /* =====================
       Scripts
    ===================== */
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'bootstrap', $plugin_uri . 'assets/js/bootstrap.bundle.min.js', array('jquery'), EDUCORE_VERSION, true );
    wp_enqueue_script( 'datatables', $plugin_uri . 'assets/js/jquery.dataTables.min.js', array('jquery'), EDUCORE_VERSION, true );
    wp_enqueue_script( 'datepicker', $plugin_uri . 'assets/js/bootstrap-datepicker.js', array('jquery'), EDUCORE_VERSION, true );
    wp_enqueue_script( 'educore-main', $plugin_uri . 'assets/js/main.js', array('jquery'), EDUCORE_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'educore_admin_enqueue_assets' );

/*--------------------------------------------------------------
# 4. Include Modular Sub-Files
--------------------------------------------------------------*/
require_once EDUCORE_PATH . 'inc/dashboard.php';
require_once EDUCORE_PATH . 'inc/students.php';
require_once EDUCORE_PATH . 'inc/attendance.php';
require_once EDUCORE_PATH . 'inc/fees.php';
require_once EDUCORE_PATH . 'inc/exams.php';
require_once EDUCORE_PATH . 'inc/staff.php';
require_once EDUCORE_PATH . 'inc/academics.php';
require_once EDUCORE_PATH . 'inc/communication.php';
require_once EDUCORE_PATH . 'inc/reports.php';
require_once EDUCORE_PATH . 'inc/settings.php';

/*--------------------------------------------------------------
# 5. Database Table Creation (Strict dbDelta Compliant)
--------------------------------------------------------------*/
function educore_create_system_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // 1. Students Table
    $table_students = $wpdb->prefix . 'sms_students';
    $sql_students = "CREATE TABLE $table_students (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        student_id varchar(50) NOT NULL,
        full_name varchar(255) NOT NULL,
        class_name varchar(50) NOT NULL,
        section_name varchar(50) NOT NULL,
        roll_no int(11) NOT NULL,
        dob date DEFAULT '1970-01-01' NOT NULL,
        gender varchar(20) DEFAULT 'Male' NOT NULL,
        blood_group varchar(10) DEFAULT '' NOT NULL,
        guardian_name varchar(255) NOT NULL,
        guardian_phone varchar(50) NOT NULL,
        address text NOT NULL,
        admission_date date DEFAULT '1970-01-01' NOT NULL,
        photo_url varchar(255) DEFAULT '' NOT NULL,
        status varchar(30) DEFAULT 'Active' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY student_id (student_id)
    ) $charset_collate;";
    dbDelta( $sql_students );

    // 2. Staff & Teachers Table
    $table_staff = $wpdb->prefix . 'sms_staff';
    $sql_staff = "CREATE TABLE $table_staff (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        wp_user_id bigint(20) DEFAULT NULL,
        full_name varchar(255) NOT NULL,
        designation varchar(100) NOT NULL,
        phone varchar(50) NOT NULL,
        email varchar(100) NOT NULL,
        joining_date date DEFAULT '1970-01-01' NOT NULL,
        salary decimal(10,2) DEFAULT '0.00' NOT NULL,
        profile_image varchar(255) DEFAULT '' NOT NULL,
        status varchar(30) DEFAULT 'Active' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_staff );

    // 3. Attendance Table
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $sql_attendance = "CREATE TABLE $table_attendance (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        student_id bigint(20) NOT NULL,
        attendance_date date NOT NULL,
        status varchar(20) DEFAULT 'Present' NOT NULL,
        remarks text DEFAULT '' NOT NULL,
        recorded_by bigint(20) NOT NULL,
        PRIMARY KEY  (id),
        KEY student_date_idx (student_id, attendance_date)
    ) $charset_collate;";
    dbDelta( $sql_attendance );

    // 4. Monthly Fee Collection Table
    $table_fees = $wpdb->prefix . 'sms_fees';
    $sql_fees = "CREATE TABLE $table_fees (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        invoice_id varchar(50) NOT NULL,
        student_id bigint(20) NOT NULL,
        fee_month varchar(20) NOT NULL,
        fee_year varchar(10) NOT NULL,
        fee_type varchar(50) DEFAULT 'Tuition Fee' NOT NULL,
        amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        discount decimal(10,2) DEFAULT '0.00' NOT NULL,
        net_payable decimal(10,2) DEFAULT '0.00' NOT NULL,
        paid_amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        due_amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        payment_status varchar(20) DEFAULT 'Unpaid' NOT NULL,
        payment_method varchar(30) DEFAULT 'Cash' NOT NULL,
        payment_date datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        collected_by bigint(20) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY invoice_id (invoice_id)
    ) $charset_collate;";
    dbDelta( $sql_fees );

    // 5. Exam Setup Table
    $table_exams = $wpdb->prefix . 'sms_exams';
    $sql_exams = "CREATE TABLE $table_exams (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        exam_name varchar(255) NOT NULL,
        class_name varchar(50) NOT NULL,
        start_date date NOT NULL,
        end_date date NOT NULL,
        status varchar(30) DEFAULT 'Upcoming' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_exams );

    // 6. Exam Results Table
    $table_results = $wpdb->prefix . 'sms_results';
    $sql_results = "CREATE TABLE $table_results (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        exam_id bigint(20) NOT NULL,
        student_id bigint(20) NOT NULL,
        subject_name varchar(100) NOT NULL,
        total_marks decimal(5,2) DEFAULT '100.00' NOT NULL,
        obtained_marks decimal(5,2) DEFAULT '0.00' NOT NULL,
        grade varchar(10) DEFAULT '' NOT NULL,
        gpa decimal(4,2) DEFAULT '0.00' NOT NULL,
        evaluated_by bigint(20) NOT NULL,
        PRIMARY KEY  (id),
        KEY exam_student_idx (exam_id, student_id)
    ) $charset_collate;";
    dbDelta( $sql_results );

    // 7. Security Audit Trail Logging
    $table_audit = $wpdb->prefix . 'sms_audit_logs';
    $sql_audit = "CREATE TABLE $table_audit (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        user_role varchar(50) NOT NULL,
        action_performed text NOT NULL,
        ip_address varchar(45) NOT NULL,
        timestamp datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_audit );
}
register_activation_hook( __FILE__, 'educore_create_system_tables' );

/*--------------------------------------------------------------
# 6. Global Security Audit Logger Engine
--------------------------------------------------------------*/
function educore_log_activity( $action_description ) {
    global $wpdb;
    $current_user = wp_get_current_user();
    $user_id = $current_user->exists() ? $current_user->ID : 0;
    $user_roles = $current_user->exists() ? implode( ', ', $current_user->roles ) : 'guest';
    
    $ip_address = '0.0.0.0';
    if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $raw_ip = wp_unslash( $_SERVER['REMOTE_ADDR'] );
        if ( filter_var( $raw_ip, FILTER_VALIDATE_IP ) ) {
            $ip_address = $raw_ip;
        }
    }

    $wpdb->insert(
        $wpdb->prefix . 'sms_audit_logs',
        array(
            'user_id'          => $user_id,
            'user_role'        => $user_roles,
            'action_performed' => sanitize_text_field( $action_description ),
            'ip_address'       => $ip_address,
            'timestamp'        => current_time( 'mysql' )
        ),
        array( '%d', '%s', '%s', '%s', '%s' )
    );
}

/*--------------------------------------------------------------
# 7. Admin Menu Core Mounting
--------------------------------------------------------------*/
add_action( 'admin_menu', function() {
    add_menu_page(
        'EduCore - School Management System',
        'School ERP',
        'read', 
        'school_management_system',
        'educore_main_router_page', 
        'dashicons-welcome-learn-more',
        20
    );
});

/*--------------------------------------------------------------
# 8. Main Dynamic Tab Router Engine
--------------------------------------------------------------*/
function educore_main_router_page() {
    $all_tabs = array(
        'dashboard' => array(
            'label' => 'Dashboard',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 544 512"><path d="M528 0H16C7.2 0 0 7.2 0 16v480c0 8.8 7.2 16 16 16h512c8.8 0 16-7.2 16-16V16c0-8.8-7.2-16-16-16zM272 248v-88c0-4.4 3.6-8 8-8h184c4.4 0 8 3.6 8 8v88c0 4.4-3.6 8-8 8H280c-4.4 0-8-3.6-8-8zm0 176v-88c0-4.4 3.6-8 8-8h184c4.4 0 8 3.6 8 8v88c0 4.4-3.6 8-8 8H280c-4.4 0-8-3.6-8-8zM72 152c0-4.4 3.6-8 8-8h112c4.4 0 8 3.6 8 8v208c0 4.4-3.6 8-8 8H80c-4.4 0-8-3.6-8-8V152z"/></svg>',
            'roles' => array() // Empty means all logged in users who can see the page
        ),
        'students' => array(
            'label' => 'Students',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path d="M320 32c-8.1 0-16.1 1.4-23.7 4.1L15.8 137.4C6.3 140.9 0 149.9 0 160s6.3 19.1 15.8 22.6l57.9 20.9C57.3 229.3 48 259.8 48 291.9v28.1c0 28.4-10.8 57.7-22.3 80.8-6.5 13-13.9 25.8-22.5 37.6-4.1 5.6-3.8 13.3 .9 18.6s12.5 5.5 18.6 1c43.6-32.3 75.3-78.8 89.6-132.3L320 380c103.5 0 197.5-44.5 259.5-114.7l44.6-16.1c9.5-3.5 15.8-12.5 15.8-22.6s-6.3-19.1-15.8-22.6L343.7 36.1C336.1 33.4 328.1 32 320 32zM128 408c0 35.3 86 72 192 72s192-36.7 192-72L496.7 262.6C454.4 316.5 390 348 320 348S185.6 316.5 143.3 262.6L128 408z"/></svg>',
            'roles' => array('administrator', 'teacher')
        ),
        'attendance' => array(
            'label' => 'Attendance',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M152 24c0-13.3-10.7-24-24-24s-24 10.7-24 24V64H64C28.7 64 0 92.7 0 128v16 48V448c0 35.3 28.7 64 64 64H384c35.3 0 64-28.7 64-64V192 144 128c0-35.3-28.7-64-64-64H344V24c0-13.3-10.7-24-24-24s-24 10.7-24 24V64H152V24zM48 192h352v256c0 8.8-7.2 16-16 16H64c-8.8 0-16-7.2-16-16V192zm278.6 57.4c-12.5-12.5-32.8-12.5-45.3 0L192 338.7l-57.4-57.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l80 80c12.5 12.5 32.8 12.5 45.3 0l112-112c12.5-12.5 12.5-32.8 0-45.3z"/></svg>',
            'roles' => array('administrator', 'teacher')
        ),
        'fees' => array(
            'label' => 'Fee Collection',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M64 64C28.7 64 0 92.7 0 128v256c0 35.3 28.7 64 64 64H512c35.3 0 64-28.7 64-64V128c0-35.3-28.7-64-64-64H64zm64 320H64V320c35.3 0 64 28.7 64 64zM64 192V128h64c0 35.3-28.7 64-64 64zM448 384c0-35.3 28.7-64 64-64v64H448zm64-192c-35.3 0-64-28.7-64-64h64v64zM288 160a96 96 0 1 1 0 192 96 96 0 1 1 0-192z"/></svg>',
            'roles' => array('administrator', 'accountant')
        ),
        'exams' => array(
            'label' => 'Exams & Results',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M152.1 38.2c9.9 8.9 10.7 24 1.8 33.9l-72 80c-4.8 5.3-11.2 8.1-18.1 7.8s-13.1-3.6-17.5-9L14.4 115.1c-8.2-10-6.8-24.8 3.2-33s24.8-6.8 33 3.2l16 19.5 51.5-57.3c8.9-9.9 24-10.7 33.9-1.8zM416 128H256c-17.7 0-32-14.3-32-32s14.3-32 32-32H416c17.7 0 32 14.3 32 32s-14.3 32-32 32zM152.1 198.2c9.9 8.9 10.7 24 1.8 33.9l-72 80c-4.8 5.3-11.2 8.1-18.1 7.8s-13.1-3.6-17.5-9L14.4 275.1c-8.2-10-6.8-24.8 3.2-33s24.8-6.8 33 3.2l16 19.5 51.5-57.3c8.9-9.9 24-10.7 33.9-1.8zM416 288H256c-17.7 0-32-14.3-32-32s14.3-32 32-32H416c17.7 0 32 14.3 32 32s-14.3 32-32 32zM152.1 358.2c9.9 8.9 10.7 24 1.8 33.9l-72 80c-4.8 5.3-11.2 8.1-18.1 7.8s-13.1-3.6-17.5-9L14.4 435.1c-8.2-10-6.8-24.8 3.2-33s24.8-6.8 33 3.2l16 19.5 51.5-57.3c8.9-9.9 24-10.7 33.9-1.8zM416 448H256c-17.7 0-32-14.3-32-32s14.3-32 32-32H416c17.7 0 32 14.3 32 32s-14.3 32-32 32z"/></svg>',
            'roles' => array('administrator', 'teacher')
        ),
        'staff' => array(
            'label' => 'Teachers & Staff',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H322.8c-3.1-8.8-3.7-18.4-1.4-27.8l15-60.1c2.8-11.3 8.6-21.5 16.8-29.7l40.3-40.3c-32.1-31-75.7-50.1-123.9-50.1H178.3zm435.5-68.3c-15.6-15.6-40.9-15.6-56.6 0l-29.4 29.4 71 71 29.4-29.4c15.6-15.6 15.6-40.9 0-56.6l-14.4-14.4zM375.9 417c-4.1 4.1-7 9.2-8.4 14.9l-15 60.1c-1.4 5.5 .2 11.2 4.2 15.2s9.7 5.6 15.2 4.2l60.1-15c5.6-1.4 10.8-4.3 14.9-8.4L576.1 358.7l-71-71L375.9 417z"/></svg>',
            'roles' => array('administrator')
        ),
        'academics' => array(
            'label' => 'Academic Setup',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M96 0C43 0 0 43 0 96V416c0 53 43 96 96 96H384h32c17.7 0 32-14.3 32-32s-14.3-32-32-32V384c17.7 0 32-14.3 32-32V32c0-17.7-14.3-32-32-32H384 96zm0 384H352v64H96c-17.7 0-32-14.3-32-32s14.3-32 32-32zm32-240c0-8.8 7.2-16 16-16H336c8.8 0 16 7.2 16 16s-7.2 16-16 16H144c-8.8 0-16-7.2-16-16zm16 48H336c8.8 0 16 7.2 16 16s-7.2 16-16 16H144c-8.8 0-16-7.2-16-16s7.2-16 16-16z"/></svg>',
            'roles' => array('administrator')
        ),
        'communication' => array(
            'label' => 'SMS & Notice',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M160 368c26.5 0 48 21.5 48 48v16l72.5-54.4c8.3-6.2 18.4-9.6 28.8-9.6H448c8.8 0 16-7.2 16-16V64c0-8.8-7.2-16-16-16H64c-8.8 0-16 7.2-16 16V352c0 8.8 7.2 16 16 16h96zm48 124l-.2 .2-5.1 3.8-17.1 12.8c-4.8 3.6-11.3 4.2-16.8 1.5s-8.8-8.2-8.8-14.3V474.7v-4.5V416H160c-53 0-96-43-96-96V64C64 11 107-32 160-32H448c53 0 96 43 96 96V352c0 53-43 96-96 96H309.3L208 504z"/></svg>',
            'roles' => array('administrator')
        ),
        'reports' => array(
            'label' => 'Reports',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M336 0H48C21.5 0 0 21.5 0 48v416c0 26.5 21.5 48 48 48h288c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48zM144 432H96v-48h48v48zm0-96H96v-48h48v48zm0-96H96v-48h48v48zm144 192H176v-48h112v48zm0-96H176v-48h112v48zm0-96H176v-48h112v48zm0-112H96V80h192v48z"/></svg>',
            'roles' => array('administrator', 'accountant')
        ),
        'settings' => array(
            'label' => 'Settings',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M487.4 315.7l-42.6-24.6c2.3-14.2 3.5-28.7 3.5-43.1s-1.2-28.9-3.5-43.1l42.6-24.6c11.5-6.6 15.4-21.3 8.7-32.8L447.5 61.2c-6.6-11.5-21.3-15.4-32.8-8.7L372 77.1c-22.1-14.8-46.7-26.3-72.9-33.8L292.8 12C291.1 5.2 285 0 278.1 0h-44.2c-6.9 0-13 5.2-14.7 12L213 43.3c-26.2 7.5-50.8 19-72.9 33.8l-42.7-24.6c-11.5-6.7-26.2-2.8-32.8 8.7L16.1 147.3c-6.7 11.5-2.8 26.2 8.7 32.8l42.6 24.6c-2.3 14.2-3.5 28.7-3.5 43.1s1.2 28.9 3.5 43.1l-42.6 24.6c-11.5 6.6-15.4 21.3-8.7 32.8l48.6 84.3c6.6 11.5 21.3 15.4 32.8 8.7l42.7-24.6c22.1 14.8 46.7 26.3 72.9 33.8L219.2 500c1.7 6.8 7.8 12 14.7 12h44.2c6.9 0 13-5.2 14.7-12l6.3-31.3c26.2-7.5 50.8-19 72.9-33.8l42.7 24.6c11.5 6.7 26.2 2.8 32.8-8.7l48.6-84.3c6.7-11.5 2.8-26.2-8.7-32.9zM256 336c-44.2 0-80-35.8-80-80s35.8-80 80-80 80 35.8 80 80-35.8 80-80 80z"/></svg>',
            'roles' => array('administrator')
        ),
        'logout' => array(
            'label' => 'Log Out',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M160 96c17.7 0 32-14.3 32-32s-14.3-32-32-32H96C43 32 0 75 0 128v256c0 53 43 96 96 96h64c17.7 0 32-14.3 32-32s-14.3-32-32-32H96c-17.7 0-32-14.3-32-32V128c0-17.7 14.3-32 32-32h64zm273 135L313 111c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l123 123H192c-17.7 0-32 14.3-32 32s14.3 32 32 32h198.7L267.7 401.7c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l120-120c12.5-12.5 12.5-32.8 0-45.3z"/></svg>',
            'roles' => array()
        ),
    );

    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
    
    if ( ! array_key_exists( $active_tab, $all_tabs ) ) {
        $active_tab = 'dashboard';
    }

    if ( ! educore_has_access( $all_tabs[ $active_tab ]['roles'] ) ) {
        echo '<div class="notice notice-error"><p>Access Denied: You do not possess the required privilege level for this module.</p></div>';
        return;
    }

    $is_print_mode = ( isset( $_GET['action'] ) && $_GET['action'] === 'print' );

    // Custom data extraction logic mapping for the user context
    global $wpdb;
    $user_id       = get_current_user_id();
    $display_name  = '';
    $designation   = '';
    $custom_avatar = '';

    // Fetch information from Staff module storage table
    $staff_row = $wpdb->get_row( $wpdb->prepare( "SELECT full_name, designation, profile_image FROM {$wpdb->prefix}sms_staff WHERE wp_user_id = %d", $user_id ) );
    
    if ( $staff_row ) {
        $display_name  = $staff_row->full_name;
        $designation   = $staff_row->designation;
        $custom_avatar = $staff_row->profile_image;
    }

    // Secondary core structural fallback defaults
    if ( empty( $display_name ) ) {
        $current_user = wp_get_current_user();
        $display_name = $current_user->display_name ? $current_user->display_name : 'Staff Member';
    }
    if ( empty( $designation ) ) {
        $designation = 'School Faculty';
    }
    ?>

    <div id="educore-wrapper" class="school-management-system <?php echo $is_print_mode ? 'educore-print' : ''; ?>">
        
        <?php if ( ! $is_print_mode ) : ?>
            <div class="educore-sidebar-container">
                
                <div class="educore-author-profile">
                    <div class="profile-avatar">
                        <?php 
                        if ( ! empty( $custom_avatar ) ) {
                            echo '<img src="' . esc_url( $custom_avatar ) . '" alt="' . esc_attr( $display_name ) . '" width="64" height="64" style="border-radius: 50%; object-fit: cover;" />';
                        } else {
                            echo get_avatar( $user_id, 64, '', '', array( 'class' => 'avatar-round' ) ); 
                        }
                        ?>
                    </div>
                    <div class="profile-meta">
                        <h4 class="profile-name"><?php echo esc_html( $display_name ); ?></h4>
                        <span class="profile-designation"><?php echo esc_html( $designation ); ?></span>
                    </div>
                </div>

                <ul class="educore-left-tabs">
                    <?php 
                    foreach ( $all_tabs as $slug => $config ) : 
                        if ( ! educore_has_access( $config['roles'] ) ) {
                            continue; 
                        }
                        $active_class = ( $active_tab === $slug ) ? 'active' : '';
                        
                        if ( $slug === 'logout' ) {
                            $target_url = wp_logout_url( admin_url( 'admin.php?page=school_management_system' ) );
                        } else {
                            $target_url = admin_url( 'admin.php?page=school_management_system&tab=' . $slug );
                        }
                        ?>
                        <li class="<?php echo esc_attr( 'tab-' . $slug ); ?>">
                            <a class="<?php echo esc_attr( $active_class ); ?>" href="<?php echo esc_url( $target_url ); ?>">
                                <?php echo $config['svg']; ?>
                                <span><?php echo esc_html( $config['label'] ); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="educore-right-box">
            <?php
            switch ( $active_tab ) {
                case 'dashboard':
                    if ( function_exists( 'educore_dashboard_tab' ) ) { educore_dashboard_tab(); }
                    break;
                case 'students':
                    if ( function_exists( 'educore_students_tab' ) ) { educore_students_tab(); }
                    break;
                case 'attendance':
                    if ( function_exists( 'educore_attendance_tab' ) ) { educore_attendance_tab(); }
                    break;
                case 'fees':
                    if ( function_exists( 'educore_fees_tab' ) ) { educore_fees_tab(); }
                    break;
                case 'exams':
                    if ( function_exists( 'educore_exams_tab' ) ) { educore_exams_tab(); }
                    break;
                case 'staff':
                    if ( function_exists( 'educore_staff_tab' ) ) { educore_staff_tab(); }
                    break;
                case 'reports':
                    if ( function_exists( 'educore_reports_tab' ) ) { educore_reports_tab(); }
                    break;
                    case 'academics':
                    if ( function_exists( 'educore_academics_tab' ) ) { educore_academics_tab(); }
                    break;
                case 'communication':
                    if ( function_exists( 'educore_communication_tab' ) ) { educore_communication_tab(); }
                    break;
                case 'settings':
                    if ( function_exists( 'educore_settings_tab' ) ) { educore_settings_tab(); }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}

/*--------------------------------------------------------------
# 9. Head CSS Layout Injection
--------------------------------------------------------------*/
add_action( 'admin_head', function() {
    $screen = get_current_screen();

    if ( $screen && $screen->id === 'toplevel_page_school_management_system' ) {
        echo '<style>
            #wpadminbar, 
            #adminmenu, #adminmenuback, #adminmenuwrap, 
            #wpfooter { display: none !important; }
            
            #wpcontent, #wpbody-content { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
            
            body.wp-admin { background: #f1f1f1; overflow-x: hidden; }

            .school-management-system {
                display: flex;
                position: relative;
                min-height: 100vh;
            }

            .educore-left-tabs, .educore-author-profile {
                width: 240px;
                margin: 0;
                list-style: none;
                flex-shrink: 0;
                background: #fff;
                border-right: 1px solid #e2e8f0;
            }

            .educore-author-profile {
                display: flex;
                align-items: center;
                gap: 15px;
                justify-content: center;
                padding: 20px;
                border-bottom: 1px solid #ddd;
            }
            .educore-author-profile img {
                width: 60px !important;
                height: 60px;
                border-radius: 50%;
                border: 3px solid #10b981; /* School green theme */
            }
            .educore-author-profile .profile-name {
                margin-bottom: 0;
                font-size: 14px;
                font-weight: 700
            }
            .profile-meta span {
                font-size: 13px;
                font-weight: 500;
            }
            .educore-left-tabs li a {
                display: flex;
                align-items: center;
                padding: 10px 24px;
                color: #333;
                text-decoration: none;
                font-weight: 600;
                font-size: 15px;
                transition: all 0.2s ease;
            }

            .educore-left-tabs li a svg {
                width: 18px;
                height: 18px;
                margin-right: 14px;
                fill: #64748b;
                transition: all 0.2s ease;
                flex-shrink: 0;
            }

            .educore-left-tabs li a:hover {
                background: #f8fafc;
                color: #1e293b;
            }

            .educore-left-tabs li a:hover svg {
                fill: #10b981;
            }

            .educore-left-tabs li a.active {
                background: #10b981;
                color: #fff;
                font-weight: 600;
            }

            .educore-left-tabs li a.active svg {
                fill: #fff;
            }

            .educore-right-box {
                flex-grow: 1;
                background: #f8fafc;
                padding: 30px;
            }

            .educore-print .educore-left-tabs {
                display: none !important;
            }
        </style>';
    }
});

/**
 * 1. ROOT HOMEPAGE TO WP-ADMIN REDIRECT
 */
function educore_root_to_admin_redirect() {
    if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
        return;
    }

    $home_path   = trim( parse_url( home_url(), PHP_URL_PATH ), '/' );
    $request_uri = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

    if ( $request_uri === $home_path ) {
        wp_safe_redirect( admin_url(), 302 );
        exit;
    }
}
add_action( 'init', 'educore_root_to_admin_redirect', 5 );

/**
 * 2. POST-LOGIN DASHBOARD REDIRECT
 */
function educore_custom_login_redirect( $redirect_to, $request, $user ) {
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        return admin_url( 'admin.php?page=school_management_system' );
    }
    return $redirect_to;
}
add_filter( 'login_redirect', 'educore_custom_login_redirect', 10, 3 );


/**
 * 3. LOGOUT ROUTING OVERRIDE
 */
function educore_custom_logout_routing() {
    wp_safe_redirect( home_url() );
    exit;
}
add_action( 'wp_logout', 'educore_custom_logout_routing' );


/**
 * 4. CUSTOM WHITE-LABEL STYLES
 */
function educore_custom_login_styles() {
    $custom_logo_url = plugin_dir_url( __FILE__ ) . 'assets/img/school-logo.png';
    ?>
    <style type="text/css">
        #login h1 a, .login h1 a {
            background-image: url('<?php echo $custom_logo_url; ?>') !important;
            height: 80px !important;
            width: 100% !important;
            background-size: contain !important;
            background-position: center !important;
            margin-bottom: 25px !important;
            border: 1px solid #ddd;
            background-color: #fff;
        }
        .wp-core-ui .button-group.button-large .button, .wp-core-ui .button.button-large {
            background-color: #10b981 !important;
        }

        body.login {
            background: #f0fdf4 !important; /* light green tint */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        }

        #login { padding: 6% 0 0 !important; width: 360px !important; }

        .login form {
            background: #ffffff !important;
            border: 1px solid #e1e8ed !important;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.1) !important;
            border-radius: 8px !important;
            padding: 30px !important;
        }

        .login label { color: #4a5568 !important; font-weight: 500 !important; }

        .login input[type="text"], .login input[type="password"] {
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 8px 12px !important;
            background: #f8fafc !important;
            box-shadow: none !important;
        }

        .wp-core-ui .button-primary {
            background: #10b981 !important;
            border: none !important;
            border-radius: 6px !important;
            box-shadow: none !important;
            font-weight: 600 !important;
            height: 40px !important;
            width: 100% !important;
            margin-top: 15px !important;
        }

        .wp-core-ui .button-primary:hover { background: #059669 !important; }

        .login #backtoblog, .login #nav, .privacy-policy-page-link {
            display: none !important;
        }
        
        .educore-captcha-container { margin: 15px 0; }
        .educore-captcha-label { display: block; margin-bottom: 5px; font-weight: bold; }
    </style>
    <?php
}
add_action( 'login_enqueue_scripts', 'educore_custom_login_styles' );

function educore_login_logo_url() { return home_url(); }
add_filter( 'login_headerurl', 'educore_login_logo_url' );

function educore_login_logo_title() { return get_bloginfo( 'name' ); }
add_filter( 'login_headertext', 'educore_login_logo_title' );


/**
 * 5. MATHEMATICAL CAPTCHA ENGINE
 */
function educore_display_login_captcha() {
    $num1 = rand(1, 9);
    $num2 = rand(1, 9);
    $captcha_token = md5( uniqid( rand(), true ) );
    set_transient( 'educore_captcha_' . $captcha_token, ($num1 + $num2), 300 );
    ?>
    <div class="educore-captcha-container">
        <label class="educore-captcha-label" for="educore_captcha_answer">Security Verification</label>
        <p style="margin: 0 0 8px 0; color: #718096; font-size: 13px;">
            Please solve: <strong><?php echo $num1; ?> + <?php echo $num2; ?> = ?</strong>
        </p>
        <input type="text" name="educore_captcha_answer" id="educore_captcha_answer" class="input" value="" size="4" autocomplete="off" required />
        <input type="hidden" name="educore_captcha_token" value="<?php echo esc_attr( $captcha_token ); ?>" />
    </div>
    <?php
}
add_action( 'login_form', 'educore_display_login_captcha' );

function educore_validate_login_captcha( $user, $username, $password ) {
    if ( is_wp_error( $user ) ) { return $user; }

    $user_answer = isset( $_POST['educore_captcha_answer'] ) ? sanitize_text_field( $_POST['educore_captcha_answer'] ) : '';
    $token       = isset( $_POST['educore_captcha_token'] ) ? sanitize_text_field( $_POST['educore_captcha_token'] ) : '';
    
    $correct_answer = get_transient( 'educore_captcha_' . $token );
    delete_transient( 'educore_captcha_' . $token );

    if ( $correct_answer === false || intval( $user_answer ) !== intval( $correct_answer ) ) {
        return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: Incorrect security verification answer.' ) );
    }
    return $user;
}
add_filter( 'authenticate', 'educore_validate_login_captcha', 25, 3 );