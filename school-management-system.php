<?php
/**
 * Plugin Name: IFSEdu - School Management System
 * Description: Standalone, high-performance management system for Schools featuring student admissions, attendance, fees, exams, and HR.
 * Version:     1.1.0
 * Author:      DevNahian
 * Text Domain: ifsedu-sms
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * 1. Constants & Path Definitions
 */
define( 'EDUCORE_VERSION', '1.1.0' );
define( 'EDUCORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'EDUCORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Core Plugin Class Engine (OOP Architecture)
 */
final class IFSEdu_School_Management_System {

    private static $instance = null;

    /**
     * Singleton Pattern implementation
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_modular_dependencies();
        $this->init_hooks();
    }

    /**
     * Include Modular Sub-Files Securely
     */
    private function load_modular_dependencies() {
        $files = array(
            'dashboard', 'students', 'attendance', 'fees', 
            'exams', 'staff', 'academics', 'communication', 
            'reports', 'frontend-bridge', 'settings', 'notices', 'accounting'
        );

        foreach ( $files as $file ) {
            $path = EDUCORE_PATH . 'inc/' . $file . '.php';
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }
    }

    /**
     * Initialize Action & Filter Core Register Hook Engine
     */
    private function init_hooks() {
        // Activation & Setup Routine
        register_activation_hook( __FILE__, array( $this, 'execute_database_migration' ) );

        // Enqueue Assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_head', array( $this, 'inject_dashboard_white_label_layout' ) );

        // Menu Structure
        add_action( 'admin_menu', array( $this, 'mount_core_erp_menu' ) );

        // Login / Routing Adjustments
        add_action( 'wp_logout', array( $this, 'handle_secure_logout_redirection' ) );
        add_action( 'login_enqueue_scripts', array( $this, 'apply_white_label_login_styles' ) );
        
        // Form Triggers & Verification Hook Integration
        add_filter( 'login_headerurl', array( $this, 'get_login_logo_url' ) );
        add_filter( 'login_headertext', array( $this, 'get_login_logo_title' ) );
        add_action( 'login_form', array( $this, 'display_mathematical_captcha' ) );
        add_filter( 'authenticate', array( $this, 'validate_mathematical_captcha' ), 25, 3 );
    }

    /**
     * Role-Based Access Control Core (RBAC Engine)
     */
    public static function has_access( $allowed_roles = array() ) {
        if ( empty( $allowed_roles ) ) {
            return true;
        }
        
        $current_user = wp_get_current_user();
        if ( ! $current_user || ! $current_user->exists() ) {
            return false;
        }

        // Bypassing check if super-admin framework matching found
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

    /**
     * Styles & Dynamic Assets Loading Processor
     */
    public function enqueue_admin_assets( $hook ) {
        // Enqueue if it's our top level page or any corresponding dynamic submenus mapped to it
        if ( strpos( $hook, 'school_management_system' ) === false ) {
            return;
        }

        // Stylesheets Registration
        wp_enqueue_style( 'bootstrap', EDUCORE_URL . 'assets/css/bootstrap.min.css', array(), EDUCORE_VERSION );
        wp_enqueue_style( 'datatables', EDUCORE_URL . 'assets/css/jquery.dataTables.min.css', array(), EDUCORE_VERSION );
        wp_enqueue_style( 'main-style', EDUCORE_URL . 'assets/css/style.css', array(), EDUCORE_VERSION );
        wp_enqueue_style( 'educore-admin-style', EDUCORE_URL . 'assets/css/admin-style.css', array(), EDUCORE_VERSION );

        // JavaScript Injections
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'bootstrap', EDUCORE_URL . 'assets/js/bootstrap.bundle.min.js', array( 'jquery' ), EDUCORE_VERSION, true );
        wp_enqueue_script( 'datatables', EDUCORE_URL . 'assets/js/jquery.dataTables.min.js', array( 'jquery' ), EDUCORE_VERSION, true );
        wp_enqueue_script( 'datepicker', EDUCORE_URL . 'assets/js/bootstrap-datepicker.js', array( 'jquery' ), EDUCORE_VERSION, true );
        wp_enqueue_script( 'educore-main', EDUCORE_URL . 'assets/js/main.js', array( 'jquery' ), EDUCORE_VERSION, true );
    }
/**
 * Global Database Migration & Update Engine (Strict dbDelta Compliant)
 */
public function execute_database_migration() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Schema Model 1: Students Base (Expanded for Multi-Step Form)
    $table_students = $wpdb->prefix . 'sms_students';
    $sql_students = "CREATE TABLE $table_students (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        student_id varchar(50) NOT NULL,
        full_name varchar(255) NOT NULL,
        name_bn varchar(255) DEFAULT '' NOT NULL,
        class_name varchar(50) NOT NULL,
        section_name varchar(50) DEFAULT '' NOT NULL,
        roll_no int(11) NOT NULL,
        admission_date date DEFAULT '1970-01-01' NOT NULL,
        birth_reg_no varchar(50) DEFAULT '' NOT NULL,
        dob date DEFAULT '1970-01-01' NOT NULL,
        birth_place varchar(100) DEFAULT '' NOT NULL,
        gender varchar(20) DEFAULT 'Male' NOT NULL,
        blood_group varchar(10) DEFAULT '' NOT NULL,
        religion varchar(50) DEFAULT '' NOT NULL,
        nationality varchar(50) DEFAULT 'Bangladeshi' NOT NULL,
        student_email varchar(100) DEFAULT '' NOT NULL,
        student_phone varchar(50) DEFAULT '' NOT NULL,
        quota varchar(50) DEFAULT 'General' NOT NULL,
        father_name varchar(255) DEFAULT '' NOT NULL,
        father_name_bn varchar(255) DEFAULT '' NOT NULL,
        father_nid varchar(50) DEFAULT '' NOT NULL,
        father_phone varchar(50) DEFAULT '' NOT NULL,
        father_profession varchar(100) DEFAULT '' NOT NULL,
        mother_name varchar(255) DEFAULT '' NOT NULL,
        mother_name_bn varchar(255) DEFAULT '' NOT NULL,
        mother_nid varchar(50) DEFAULT '' NOT NULL,
        mother_phone varchar(50) DEFAULT '' NOT NULL,
        mother_profession varchar(100) DEFAULT '' NOT NULL,
        guardian_name varchar(255) NOT NULL,
        guardian_phone varchar(50) NOT NULL,
        guardian_relation varchar(50) DEFAULT '' NOT NULL,
        guardian_nid varchar(50) DEFAULT '' NOT NULL,
        guardian_income varchar(50) DEFAULT '' NOT NULL,
        prev_school_name varchar(255) DEFAULT '' NOT NULL,
        prev_eiin varchar(50) DEFAULT '' NOT NULL,
        prev_class varchar(50) DEFAULT '' NOT NULL,
        prev_gpa varchar(20) DEFAULT '' NOT NULL,
        address text NOT NULL,
        permanent_address text NOT NULL,
        residential_status varchar(50) DEFAULT '' NOT NULL,
        co_curricular text NOT NULL,
        photo_url varchar(255) DEFAULT '' NOT NULL,
        status varchar(30) DEFAULT 'Active' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY student_id (student_id)
    ) $charset_collate;";
    dbDelta( $sql_students );

    // Schema Model 2: Staff Matrix (Full 35-Column Architecture)
    $table_staff = $wpdb->prefix . 'sms_staff';
    $sql_staff = "CREATE TABLE $table_staff (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        wp_user_id bigint(20) DEFAULT NULL,
        full_name varchar(255) NOT NULL,
        name_bn varchar(255) DEFAULT '' NOT NULL,
        father_name varchar(255) DEFAULT '' NOT NULL,
        mother_name varchar(255) DEFAULT '' NOT NULL,
        designation varchar(100) NOT NULL,
        staff_type varchar(50) DEFAULT '' NOT NULL,
        pay_grade varchar(50) DEFAULT '' NOT NULL,
        index_no varchar(50) DEFAULT '' NOT NULL,
        nid_no varchar(50) DEFAULT '' NOT NULL,
        dob date DEFAULT '1970-01-01' NOT NULL,
        gender varchar(20) DEFAULT 'Male' NOT NULL,
        phone varchar(50) NOT NULL,
        whatsapp_no varchar(50) DEFAULT '' NOT NULL,
        email varchar(100) NOT NULL,
        blood_group varchar(10) DEFAULT '' NOT NULL,
        quota_type varchar(50) DEFAULT 'General' NOT NULL,
        joining_date date DEFAULT '1970-01-01' NOT NULL,
        salary decimal(10,2) DEFAULT '0.00' NOT NULL,
        subject_expert varchar(255) DEFAULT '' NOT NULL,
        highest_degree varchar(255) DEFAULT '' NOT NULL,
        emergency_name varchar(255) DEFAULT '' NOT NULL,
        emergency_phone varchar(50) DEFAULT '' NOT NULL,
        emergency_relation varchar(50) DEFAULT '' NOT NULL,
        bank_name varchar(255) DEFAULT '' NOT NULL,
        bank_acc_no varchar(100) DEFAULT '' NOT NULL,
        bank_routing varchar(50) DEFAULT '' NOT NULL,
        address text NOT NULL,
        permanent_address text NOT NULL,
        linkedin_url varchar(255) DEFAULT '' NOT NULL,
        facebook_url varchar(255) DEFAULT '' NOT NULL,
        website_url varchar(255) DEFAULT '' NOT NULL,
        profile_image varchar(255) DEFAULT '' NOT NULL,
        status varchar(30) DEFAULT 'Active' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_staff );

    // Schema Model 3: Daily Attendance Logs
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $sql_attendance = "CREATE TABLE $table_attendance (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        student_id bigint(20) NOT NULL,
        attendance_date date NOT NULL,
        status varchar(20) DEFAULT 'Present' NOT NULL,
        remarks text NOT NULL,
        recorded_by bigint(20) NOT NULL,
        PRIMARY KEY  (id),
        KEY student_date_idx (student_id, attendance_date)
    ) $charset_collate;";
    dbDelta( $sql_attendance );

    // Schema Model 4: Accountancy Ledger Fees
    $table_fees = $wpdb->prefix . 'sms_fees';
    $sql_fees = "CREATE TABLE $table_fees (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        invoice_id varchar(50) NOT NULL,
        student_id bigint(20) NOT NULL,
        fee_month varchar(20) NOT NULL,
        fee_year varchar(10) NOT NULL,
        fee_type varchar(50) DEFAULT 'Tuition Fee' NOT NULL,
        amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        late_fine decimal(10,2) DEFAULT '0.00' NOT NULL,
        discount decimal(10,2) DEFAULT '0.00' NOT NULL,
        net_payable decimal(10,2) DEFAULT '0.00' NOT NULL,
        paid_amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        due_amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        payment_status varchar(20) DEFAULT 'Unpaid' NOT NULL,
        payment_method varchar(30) DEFAULT 'Cash' NOT NULL,
        transaction_id varchar(100) DEFAULT '' NOT NULL,
        remarks text NOT NULL,
        payment_date datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        collected_by bigint(20) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY invoice_id (invoice_id)
    ) $charset_collate;";
    dbDelta( $sql_fees );

    // Schema Model 5: Examination Setup Scheme
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

    // Schema Model 6: Academic Marksheets Archive
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

    // Schema Model 7: Security Audit Core Ledger
    $table_audit = $wpdb->prefix . 'sms_audit_logs';
    $sql_audit = "CREATE TABLE $table_audit (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        user_role varchar(50) NOT NULL,
        action_performed text NOT NULL,
        ip_address varchar(45) NOT NULL,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_audit );

    // Schema Model 8: Academic Units Architecture
    $table_academic_units = $wpdb->prefix . 'sms_academic_units';
    $sql_academic_units = "CREATE TABLE $table_academic_units (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        unit_type varchar(50) NOT NULL,
        class_name varchar(100) NOT NULL,
        section_name varchar(100) DEFAULT '' NOT NULL,
        dept_name varchar(100) DEFAULT '' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_academic_units );

    // Schema Model 9: Academic Subjects
    $table_subjects = $wpdb->prefix . 'sms_subjects';
    $sql_subjects = "CREATE TABLE $table_subjects (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        class_id bigint(20) NOT NULL,
        subject_name varchar(150) NOT NULL,
        subject_code varchar(50) DEFAULT '' NOT NULL,
        subject_type varchar(20) DEFAULT 'Mandatory' NOT NULL,
        PRIMARY KEY  (id),
        KEY class_id_idx (class_id)
    ) $charset_collate;";
    dbDelta( $sql_subjects );

    // Schema Model 10: Class Routine Management
    $table_routine = $wpdb->prefix . 'sms_routine';
    $sql_routine = "CREATE TABLE $table_routine (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        class_id bigint(20) NOT NULL,
        subject_id bigint(20) NOT NULL,
        day_name varchar(20) NOT NULL,
        start_time time NOT NULL,
        end_time time NOT NULL,
        room_no varchar(20) DEFAULT '' NOT NULL,
        PRIMARY KEY  (id),
        KEY class_id_idx (class_id),
        KEY subject_id_idx (subject_id)
    ) $charset_collate;";
    dbDelta( $sql_routine );

    // Schema Model 11: Notice & Events Table
    $table_notices = $wpdb->prefix . 'sms_notices';
    $sql_notices = "CREATE TABLE $table_notices (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        notice_type varchar(50) DEFAULT 'Notice' NOT NULL,
        priority varchar(20) DEFAULT 'Normal' NOT NULL,
        target_audience varchar(50) DEFAULT 'All' NOT NULL,
        description text NOT NULL,
        event_date date DEFAULT NULL,
        attachment_url varchar(255) DEFAULT '' NOT NULL,
        featured_image varchar(255) DEFAULT '' NOT NULL,
        created_by bigint(20) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        status varchar(20) DEFAULT 'Published' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_notices );

    // Schema Model 12: Photo Albums Table
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $sql_albums = "CREATE TABLE $table_albums (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        category varchar(100) DEFAULT 'General' NOT NULL,
        event_date date DEFAULT NULL,
        description text NOT NULL,
        cover_image varchar(255) DEFAULT '' NOT NULL,
        status varchar(20) DEFAULT 'Published' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_albums );

    // Schema Model 13: Album Photos Table
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';
    $sql_photos = "CREATE TABLE $table_photos (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        album_id bigint(20) NOT NULL,
        image_url varchar(255) NOT NULL,
        caption varchar(255) DEFAULT '' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY album_id_idx (album_id)
    ) $charset_collate;";
    dbDelta( $sql_photos );

    // Schema Model 20: Institutional General Accounting Ledger
    $table_accounting = $wpdb->prefix . 'sms_accounting';
    $sql_accounting = "CREATE TABLE $table_accounting (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        voucher_no varchar(50) NOT NULL,
        entry_type varchar(20) DEFAULT 'Income' NOT NULL,
        category_name varchar(100) NOT NULL,
        title varchar(255) NOT NULL,
        amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        payment_method varchar(50) DEFAULT 'Cash' NOT NULL,
        entry_date date NOT NULL,
        note text NOT NULL,
        created_by bigint(20) NOT NULL,
        PRIMARY KEY  (id),
        KEY entry_type_date_idx (entry_type, entry_date),
        KEY entry_date_idx (entry_date)
    ) $charset_collate;";
    dbDelta( $sql_accounting );

    // Update Version Flag
    update_option( 'educore_db_version', '1.0.0' );
}

    /**
     * Security Action & Event Logging Engine
     */
    public static function log_activity( $action_description ) {
        global $wpdb;
        $current_user = wp_get_current_user();
        $user_id   = $current_user->exists() ? $current_user->ID : 0;
        $user_role = $current_user->exists() ? implode( ', ', $current_user->roles ) : 'guest';
        
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
                'user_role'        => $user_role,
                'action_performed' => sanitize_text_field( $action_description ),
                'ip_address'       => $ip_address,
                'timestamp'        => current_time( 'mysql' )
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Data map engine providing uniform data arrays for both sidebars and WP hooks
     */
private function get_tabs_config() {
        return array(
            'dashboard' => array(
                'label' => __( 'Dashboard', 'ifsedu-sms' ),
                'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 544 512"><path d="M528 0H16C7.2 0 0 7.2 0 16v480c0 8.8 7.2 16 16 16h512c8.8 0 16-7.2 16-16V16c0-8.8-7.2-16-16-16zM272 248v-88c0-4.4 3.6-8 8-8h184c4.4 0 8 3.6 8 8v88c0 4.4-3.6 8-8 8H280c-4.4 0-8-3.6-8-8zm0 176v-88c0-4.4 3.6-8 8-8h184c4.4 0 8 3.6 8 8v88c0 4.4-3.6 8-8 8H280c-4.4 0-8-3.6-8-8zM72 152c0-4.4 3.6-8 8-8h112c4.4 0 8 3.6 8 8v208c0 4.4-3.6 8-8 8H80c-4.4 0-8-3.6-8-8V152z"/></svg>',
                'roles' => array()
            ),
            'students' => array(
                'label' => __( 'Students', 'ifsedu-sms' ),
                'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path d="M320 32c-8.1 0-16.1 1.4-23.7 4.1L15.8 137.4C6.3 140.9 0 149.9 0 160s6.3 19.1 15.8 22.6l57.9 20.9C57.3 229.3 48 259.8 48 291.9v28.1c0 28.4-10.8 57.7-22.3 80.8-6.5 13-13.9 25.8-22.5 37.6-4.1 5.6-3.8 13.3 .9 18.6s12.5 5.5 18.6 1c43.6-32.3 75.3-78.8 89.6-132.3L320 380c103.5 0 197.5-44.5 259.5-114.7l44.6-16.1c9.5-3.5 15.8-12.5 15.8-22.6s-6.3-19.1-15.8-22.6L343.7 36.1C336.1 33.4 328.1 32 320 32zM128 408c0 35.3 86 72 192 72s192-36.7 192-72L496.7 262.6C454.4 316.5 390 348 320 348S185.6 316.5 143.3 262.6L128 408z"/></svg>',
                'roles' => array( 'administrator', 'teacher' )
            ),
            'attendance' => array(
                'label' => __( 'Attendance', 'ifsedu-sms' ),
                'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M152 24c0-13.3-10.7-24-24-24s-24 10.7-24 24V64H64C28.7 64 0 92.7 0 128v16 48V448c0 35.3 28.7 64 64 64H384c35.3 0 64-28.7 64-64V192 144 128c0-35.3-28.7-64-64-64H344V24c0-13.3-10.7-24-24-24s-24 10.7-24 24V64H152V24zM48 192h352v256c0 8.8-7.2 16-16 16H64c-8.8 0-16-7.2-16-16V192zm278.6 57.4c-12.5-12.5-32.8-12.5-45.3 0L192 338.7l-57.4-57.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l80 80c12.5 12.5 32.8 12.5 45.3 0l112-112c12.5-12.5 12.5-32.8 0-45.3z"/></svg>',
                'roles' => array( 'administrator', 'teacher' )
            ),
            'fees' => array(
                'label' => __( 'Fee Collection', 'ifsedu-sms' ),
                'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M64 64C28.7 64 0 92.7 0 128v256c0 35.3 28.7 64 64 64H512c35.3 0 64-28.7 64-64V128c0-35.3-28.7-64-64-64H64zm64 320H64V320c35.3 0 64 28.7 64 64zM64 192V128h64c0 35.3-28.7 64-64 64zM448 384c0-35.3 28.7-64 64-64v64H448zm64-192c-35.3 0-64-28.7-64-64h64v64zM288 160a96 96 0 1 1 0 192 96 96 0 1 1 0-192z"/></svg>',
                'roles' => array( 'administrator', 'accountant' )
            ),
            'accounting' => array(
                'label' => __( 'Accounting & Income', 'ifsedu-sms' ),
                'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M64 32C28.7 32 0 60.7 0 96V416c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V96c0-35.3-28.7-64-64-64H64zM336 96c17.7 0 32 14.3 32 32s-14.3 32-32 32s-32-14.3-32-32s14.3-32 32-32zm0 128c17.7 0 32 14.3 32 32s-14.3 32-32 32s-32-14.3-32-32s14.3-32 32-32zM128 288h96c13.3 0 24 10.7 24 24s-10.7 24-24 24H128c-13.3 0-24-10.7-24-24s10.7-24 24-24zm0-96h96c13.3 0 24 10.7 24 24s-10.7 24-24 24H128c-13.3 0-24-10.7-24-24s10.7-24 24-24zm0-96h96c13.3 0 24 10.7 24 24s-10.7 24-24 24H128c-13.3 0-24-10.7-24-24s10.7-24 24-24z"/></svg>',
                'roles' => array( 'administrator', 'accountant' )
            ),
            'exams' => array(
                'label' => __( 'Exams & Results', 'ifsedu-sms' ),
                'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M152.1 38.2c9.9 8.9 10.7 24 1.8 33.9l-72 80c-4.8 5.3-11.2 8.1-18.1 7.8s-13.1-3.6-17.5-9L14.4 115.1c-8.2-10-6.8-24.8 3.2-33s24.8-6.8 33 3.2l16 19.5 51.5-57.3c8.9-9.9 24-10.7 33.9-1.8zM416 128H256c-17.7 0-32-14.3-32-32s14.3-32 32-32H416c17.7 0 32 14.3 32 32s-14.3 32-32 32zM152.1 198.2c9.9 8.9 10.7 24 1.8 33.9l-72 80c-4.8 5.3-11.2 8.1-18.1 7.8s-13.1-3.6-17.5-9L14.4 275.1c-8.2-10-6.8-24.8 3.2-33s24.8-6.8 33 3.2l16 19.5 51.5-57.3c8.9-9.9 24-10.7 33.9-1.8zM416 288H256c-17.7 0-32-14.3-32-32s14.3-32 32-32H416c17.7 0 32 14.3 32 32s-14.3 32-32 32zM152.1 358.2c9.9 8.9 10.7 24 1.8 33.9l-72 80c-4.8 5.3-11.2 8.1-18.1 7.8s-13.1-3.6-17.5-9L14.4 435.1c-8.2-10-6.8-24.8 3.2-33s24.8-6.8 33 3.2l16 19.5 51.5-57.3c8.9-9.9 24-10.7 33.9-1.8zM416 448H256c-17.7 0-32-14.3-32-32s14.3-32 32-32H416c17.7 0 32 14.3 32 32s-14.3 32-32 32z"/></svg>',
                'roles' => array( 'administrator', 'teacher' )
            ),
            'staff' => array(
                'label' => __( 'Teachers & Staff', 'ifsedu-sms' ),
                'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H322.8c-3.1-8.8-3.7-18.4-1.4-27.8l15-60.1c2.8-11.3 8.6-21.5 16.8-29.7l40.3-40.3c-32.1-31-75.7-50.1-123.9-50.1H178.3zm435.5-68.3c-15.6-15.6-40.9-15.6-56.6 0l-29.4 29.4 71 71 29.4-29.4c15.6-15.6 15.6-40.9 0-56.6l-14.4-14.4zM375.9 417c-4.1 4.1-7 9.2-8.4 14.9l-15 60.1c-1.4 5.5 .2 11.2 4.2 15.2s9.7 5.6 15.2 4.2l60.1-15c5.6-1.4 10.8-4.3 14.9-8.4L576.1 358.7l-71-71L375.9 417z"/></svg>',
                'roles' => array( 'administrator' )
            ),
            'academics' => array(
                'label' => __( 'Academic Setup', 'ifsedu-sms' ),
                'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M96 0C43 0 0 43 0 96V416c0 53 43 96 96 96H384h32c17.7 0 32-14.3 32-32s-14.3-32-32-32V384c17.7 0 32-14.3 32-32V32c0-17.7-14.3-32-32-32H384 96zm0 384H352v64H96c-17.7 0-32-14.3-32-32s14.3-32 32-32zm32-240c0-8.8 7.2-16 16-16H336c8.8 0 16 7.2 16 16s-7.2 16-16 16H144c-8.8 0-16-7.2-16-16zm16 48H336c8.8 0 16 7.2 16 16s-7.2 16-16 16H144c-8.8 0-16-7.2-16-16s7.2-16 16-16z"/></svg>',
                'roles' => array( 'administrator' )
            ),
            'notice' => array(
                'label' => __( 'Notices & Events', 'ifsedu-sms' ),
                'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M160 368c26.5 0 48 21.5 48 48v16l72.5-54.4c8.3-6.2 18.4-9.6 28.8-9.6H448c8.8 0 16-7.2 16-16V64c0-8.8-7.2-16-16-16H64c-8.8 0-16 7.2-16 16V352c0 8.8 7.2 16 16 16h96zm48 124l-.2 .2-5.1 3.8-17.1 12.8c-4.8 3.6-11.3 4.2-16.8 1.5s-8.8-8.2-8.8-14.3V474.7v-4.5V416H160c-53 0-96-43-96-96V64C64 11 107-32 160-32H448c53 0 96 43 96 96V352c0 53-43 96-96 96H309.3L208 504z"/></svg>',
                'roles' => array( 'administrator' )
            ),
            'reports' => array(
                'label' => __( 'Reports', 'ifsedu-sms' ),
                'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M336 0H48C21.5 0 0 21.5 0 48v416c0 26.5 21.5 48 48 48h288c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48zM144 432H96v-48h48v48zm0-96H96v-48h48v48zm0-96H96v-48h48v48zm144 192H176v-48h112v48zm0-96H176v-48h112v48zm0-96H176v-48h112v48zm0-112H96V80h192v48z"/></svg>',
                'roles' => array( 'administrator', 'accountant' )
            ),
            'settings' => array(
                'label' => __( 'Settings', 'ifsedu-sms' ),
                'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M487.4 315.7l-42.6-24.6c2.3-14.2 3.5-28.7 3.5-43.1s-1.2-28.9-3.5-43.1l42.6-24.6c11.5-6.6 15.4-21.3 8.7-32.8L447.5 61.2c-6.6-11.5-21.3-15.4-32.8-8.7L372 77.1c-22.1-14.8-46.7-26.3-72.9-33.8L292.8 12C291.1 5.2 285 0 278.1 0h-44.2c-6.9 0-13 5.2-14.7 12L213 43.3c-26.2 7.5-50.8 19-72.9 33.8l-42.7-24.6c-11.5-6.7-26.2-2.8-32.8 8.7L16.1 147.3c-6.7 11.5-2.8 26.2 8.7 32.8l42.6 24.6c-2.3 14.2-3.5 28.7-3.5 43.1s1.2 28.9 3.5 43.1l-42.6 24.6c-11.5 6.6-15.4 21.3-8.7 32.8l48.6 84.3c6.6 11.5 21.3 15.4 32.8 8.7l42.7-24.6c22.1 14.8 46.7 26.3 72.9 33.8L219.2 500c1.7 6.8 7.8 12 14.7 12h44.2c6.9 0 13-5.2 14.7-12l6.3-31.3c26.2-7.5 50.8-19 72.9-33.8l42.7 24.6c11.5 6.7 26.2 2.8 32.8-8.7l48.6-84.3c6.7-11.5 2.8-26.2-8.7-32.9zM256 336c-44.2 0-80-35.8-80-80s35.8-80 80-80 80 35.8 80 80-35.8 80-80 80z"/></svg>',
                'roles' => array( 'administrator' )
            ),
            'logout' => array(
                'label' => __( 'Log Out', 'ifsedu-sms' ),
                'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M160 96c17.7 0 32-14.3 32-32s-14.3-32-32-32H96C43 32 0 75 0 128v256c0 53 43 96 96 96h64c17.7 0 32-14.3 32-32s-14.3-32-32-32H96c-17.7 0-32-14.3-32-32V128c0-17.7 14.3-32 32-32h64zm273 135L313 111c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l123 123H192c-17.7 0-32 14.3-32 32s14.3 32 32 32h198.7L267.7 401.7c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l120-120c12.5-12.5 12.5-32.8 0-45.3z"/></svg>',
                'roles' => array()
            ),
        );
    }

    /**
     * Mount Core Dashboard Admin Navigation Routing Nodes
     */
    public function mount_core_erp_menu() {
        // Main ERP Dashboard entry point
        add_menu_page(
            __( 'EduCore - School Management System', 'ifsedu-sms' ),
            __( 'School ERP', 'ifsedu-sms' ),
            'read', 
            'school_management_system',
            array( $this, 'render_dynamic_router_interface' ), 
            'dashicons-welcome-learn-more',
            20
        );

        $tabs = $this->get_tabs_config();

        // Dynamically append sub-menus under our core tree to sync WP admin nodes natively
        foreach ( $tabs as $slug => $config ) {
            // Skip logout action link as a standalone physical submenu framework layout page
            if ( 'logout' === $slug ) {
                continue;
            }

            // Enforce custom internal capability filter based on configurations
            $cap = 'read';
            if ( in_array( 'administrator', $config['roles'], true ) ) {
                $cap = 'manage_options';
            }

            add_submenu_page(
                'school_management_system',
                $config['label'] . ' - ' . __( 'School ERP', 'ifsedu-sms' ),
                $config['label'],
                $cap,
                'school_management_system&tab=' . $slug,
                array( $this, 'render_dynamic_router_interface' )
            );
        }
    }

    /**
     * Component Render Router Module Interface
     */
    public function render_dynamic_router_interface() {
        $all_tabs = $this->get_tabs_config();

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
        if ( ! array_key_exists( $active_tab, $all_tabs ) ) {
            $active_tab = 'dashboard';
        }

        if ( ! self::has_access( $all_tabs[ $active_tab ]['roles'] ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Access Denied: You do not possess the required privilege level for this module.', 'ifsedu-sms' ) . '</p></div>';
            return;
        }

        $is_print_mode = ( isset( $_GET['action'] ) && 'print' === $_GET['action'] );

        // Pull contextual user configurations
        global $wpdb;
        $user_id       = get_current_user_id();
        $display_name  = '';
        $designation   = '';
        $custom_avatar = '';

        $staff_row = $wpdb->get_row( $wpdb->prepare( "SELECT full_name, designation, profile_image FROM {$wpdb->prefix}sms_staff WHERE wp_user_id = %d", $user_id ) );
        if ( $staff_row ) {
            $display_name  = $staff_row->full_name;
            $designation   = $staff_row->designation;
            $custom_avatar = $staff_row->profile_image;
        }

        if ( empty( $display_name ) ) {
            $current_user = wp_get_current_user();
            $display_name = $current_user->display_name ? $current_user->display_name : __( 'Staff Member', 'ifsedu-sms' );
        }
        if ( empty( $designation ) ) {
            $designation = __( 'School Faculty', 'ifsedu-sms' );
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
                            if ( ! self::has_access( $config['roles'] ) ) {
                                continue; 
                            }
                            $active_class = ( $active_tab === $slug ) ? 'active' : '';
                            $target_url   = ( 'logout' === $slug ) ? wp_logout_url( admin_url( 'admin.php?page=school_management_system' ) ) : admin_url( 'admin.php?page=school_management_system&tab=' . $slug );
                            ?>
                            <li class="<?php echo esc_attr( 'tab-' . $slug ); ?>">
                                <a class="<?php echo esc_attr( $active_class ); ?>" href="<?php echo esc_url( $target_url ); ?>">
                                    <?php echo $config['svg']; // Escaping skipped for trustful core localized SVG vectors ?>
                                    <span><?php echo esc_html( $config['label'] ); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="educore-right-box">
                <?php
                // Dynamic Component Controller Trigger
                $callback = 'educore_' . $active_tab . '_tab';
                if ( function_exists( $callback ) ) {
                    call_user_func( $callback );
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Dashboard Shell Custom Interceptor Injection Setup
     */
    public function inject_dashboard_white_label_layout() {
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'school_management_system' ) !== false ) {
            ?>
            <style>
    /* ==========================================================================
       1. GLOBAL WP-ADMIN OVERRIDES & RESET
       ========================================================================== */
    #wpadminbar, 
    #adminmenu, 
    #adminmenuback, 
    #adminmenuwrap, 
    #wpfooter { 
        display: none !important; 
    }

    #wpcontent, 
    #wpbody-content { 
        margin-left: 0 !important; 
        padding: 0 !important; 
        width: 100% !important; 
    }

    body.wp-admin { 
        background: #f8fafc; 
        overflow-x: hidden; 
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    /* System Root Wrapper */
    .school-management-system { 
        display: flex; 
        position: relative; 
        min-height: 100vh; 
        width: 100%;
    }

    /* ==========================================================================
       2. FIXED SIDEBAR CONTAINER
       ========================================================================== */
    .educore-sidebar-container { 
        width: 250px; 
        flex-shrink: 0; 
        background: #ffffff; 
        border-right: 1px solid #e2e8f0; 
        position: sticky;
        top: 0;
        height: 100vh; /* Pinned full viewport height */
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
        z-index: 99;
    }

    /* Pinned Profile Header (Does not scroll away) */
    .educore-author-profile { 
        width: 100%;
        display: flex; 
        align-items: center; 
        gap: 14px; 
        padding: 20px 18px; 
        border-bottom: 1px solid #e2e8f0; 
        box-sizing: border-box;
        flex-shrink: 0; /* Keeps profile fixed at top */
        background: #ffffff;
    }

    .educore-author-profile .profile-avatar img { 
        width: 52px !important; 
        height: 52px; 
        border-radius: 50%; 
        border: 2.5px solid #10b981; 
        object-fit: cover;
    }

    .educore-author-profile .profile-meta {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .educore-author-profile .profile-name { 
        margin: 0; 
        font-size: 15px; 
        font-weight: 800; 
        color: #0f172a;
        line-height: 1.2;
    }

    .educore-author-profile .profile-designation { 
        font-size: 12px; 
        font-weight: 600; 
        color: #64748b;
    }

    /* ==========================================================================
       3. SCROLLABLE NAVIGATION MENU TABS
       ========================================================================== */
    .educore-left-tabs { 
        width: 100%;
        margin: 0; 
        padding: 12px 10px;
        list-style: none; 
        flex: 1; /* Fills remaining sidebar vertical space */
        overflow-y: auto; /* Enables independent vertical scroll */
        display: flex;
        flex-direction: column;
        gap: 4px;
        box-sizing: border-box;
    }

    .educore-left-tabs li {
        margin: 0;
    }

    .educore-left-tabs li a { 
        display: flex; 
        align-items: center; 
        gap: 12px;
        padding: 11px 16px; 
        color: #475569; 
        text-decoration: none; 
        font-weight: 700; 
        font-size: 14px; 
        border-radius: 10px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
        white-space: nowrap;
    }

    .educore-left-tabs li a svg { 
        width: 18px; 
        height: 18px; 
        fill: #64748b; 
        transition: fill 0.2s ease; 
        flex-shrink: 0; 
    }

    /* Hover States */
    .educore-left-tabs li a:hover { 
        background: #f0fdf4; 
        color: #065f46; 
    }

    .educore-left-tabs li a:hover svg { 
        fill: #10b981; 
    }

    /* Active State (EduCore Primary Emerald Accent) */
    .educore-left-tabs li a.active { 
        background: #10b981; 
        color: #ffffff; 
        font-weight: 700; 
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
    }

    .educore-left-tabs li a.active svg { 
        fill: #ffffff; 
    }

    /* Special Logout Styling */
    .educore-left-tabs li.tab-logout a:hover {
        background: #fef2f2;
        color: #dc2626;
    }

    .educore-left-tabs li.tab-logout a:hover svg {
        fill: #dc2626;
    }

    /* ==========================================================================
       4. ULTRA-SMOOTH MODERN SCROLLBAR ENGINE
       ========================================================================== */
    /* Firefox Support */
    .educore-left-tabs {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
        scroll-behavior: smooth;
    }

    /* Webkit Engine (Chrome, Edge, Safari, Opera) */
    .educore-left-tabs::-webkit-scrollbar {
        width: 6px; /* Sleek thickness */
    }

    .educore-left-tabs::-webkit-scrollbar-track {
        background: transparent;
        margin: 6px 0; /* Breathing gap at top and bottom */
    }

    .educore-left-tabs::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 20px; /* Smooth pill shape */
        border: 2px solid transparent; /* Smooth inset effect */
        background-clip: content-box;
        transition: background-color 0.25s ease;
    }

    .educore-left-tabs::-webkit-scrollbar-thumb:hover {
        background-color: #10b981; /* Emerald highlight on hover */
    }

    .educore-left-tabs::-webkit-scrollbar-thumb:active {
        background-color: #059669;
    }

    /* ==========================================================================
       5. MAIN WORKSPACE CONTENT CONTAINER
       ========================================================================== */
    .educore-right-box { 
        flex: 1; 
        background: #f8fafc; 
        padding: 32px 36px; 
        min-width: 0; /* Prevents flex children blowout */
        box-sizing: border-box;
    }

    /* Print View Clean-up */
    @media print {
        .educore-sidebar-container, 
        .no-print { 
            display: none !important; 
        }
        .educore-right-box { 
            padding: 0 !important; 
            background: #ffffff !important; 
        }
    }
</style>
            <?php
        }
    }

    /**
     * Terminate Sessions and Redirect Safely
     */
    public function handle_secure_logout_redirection() {
        wp_safe_redirect( home_url() );
        exit;
    }

    /**
     * White-Label Branding Overrides for the Login Form Panel
     */
    public function apply_white_label_login_styles() {
        $custom_logo_url = plugin_dir_url( __FILE__ ) . 'assets/img/school-logo.png';
        ?>
        <style type="text/css">
            #login h1 a, .login h1 a {
                background-image: url('<?php echo esc_url( $custom_logo_url ); ?>') !important;
                height: 80px !important;
                width: 100% !important;
                background-size: contain !important;
                background-position: center !important;
                margin-bottom: 25px !important;
                border: 1px solid #ddd;
                background-color: #fff;
            }
            .wp-core-ui .button-group.button-large .button, .wp-core-ui .button.button-large { background-color: #10b981 !important; }
            body.login { background: #f0fdf4 !important; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; }
            #login { padding: 6% 0 0 !important; width: 360px !important; }
            .login form { background: #ffffff !important; border: 1px solid #e1e8ed !important; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.1) !important; border-radius: 8px !important; padding: 30px !important; }
            .login label { color: #4a5568 !important; font-weight: 500 !important; }
            .login input[type="text"], .login input[type="password"] { border: 1px solid #cbd5e1 !important; border-radius: 6px !important; padding: 8px 12px !important; background: #f8fafc !important; box-shadow: none !important; }
            .wp-core-ui .button-primary { background: #10b981 !important; border: none !important; border-radius: 6px !important; box-shadow: none !important; font-weight: 600 !important; height: 40px !important; width: 100% !important; margin-top: 15px !important; }
            .wp-core-ui .button-primary:hover { background: #059669 !important; }
            .login #backtoblog, .login #nav, .privacy-policy-page-link { display: none !important; }
            .educore-captcha-container { margin: 15px 0; }
            .educore-captcha-label { display: block; margin-bottom: 5px; font-weight: bold; }
        </style>
        <?php
    }

    public function get_login_logo_url() {
        return home_url();
    }

    public function get_login_logo_title() {
        return get_bloginfo( 'name' );
    }

    /**
     * Display Mathematical Security Verification
     */
    public function display_mathematical_captcha() {
        $num1 = rand( 1, 9 );
        $num2 = rand( 1, 9 );
        $captcha_token = md5( uniqid( rand(), true ) );
        set_transient( 'educore_captcha_' . $captcha_token, ( $num1 + $num2 ), 300 );
        ?>
        <div class="educore-captcha-container">
            <label class="educore-captcha-label" for="educore_captcha_answer"><?php esc_html_e( 'Security Verification', 'ifsedu-sms' ); ?></label>
            <p style="margin: 0 0 8px 0; color: #718096; font-size: 13px;">
                <?php printf( esc_html__( 'Please solve: %1$d + %2$d = ?', 'ifsedu-sms' ), $num1, $num2 ); ?>
            </p>
            <input type="text" name="educore_captcha_answer" id="educore_captcha_answer" class="input" value="" size="4" autocomplete="off" required />
            <input type="hidden" name="educore_captcha_token" value="<?php echo esc_attr( $captcha_token ); ?>" />
        </div>
        <?php
    }

    /**
     * Validate Captcha Computations on Log In Process
     */
    public function validate_mathematical_captcha( $user, $username, $password ) {
        // Bypass if it's already an error or if it isn't an interactive POST request submission
        if ( is_wp_error( $user ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['log'] ) ) { 
            return $user; 
        }

        // Processing variables safely
        $user_answer = isset( $_POST['educore_captcha_answer'] ) ? sanitize_text_field( $_POST['educore_captcha_answer'] ) : '';
        $token       = isset( $_POST['educore_captcha_token'] ) ? sanitize_text_field( $_POST['educore_captcha_token'] ) : '';
        
        $correct_answer = get_transient( 'educore_captcha_' . $token );
        delete_transient( 'educore_captcha_' . $token );

        if ( false === $correct_answer || intval( $user_answer ) !== intval( $correct_answer ) ) {
            return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: Incorrect security verification answer.', 'ifsedu-sms' ) );
        }
        return $user;
    }
}

// Fire up the Engine Instantiation Loop
IFSEdu_School_Management_System::get_instance();