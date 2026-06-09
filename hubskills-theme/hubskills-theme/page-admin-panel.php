<?php
/**
 * Template Name: Admin Panel (Frontend)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! is_user_logged_in() || ! hs_is_hs_admin( get_current_user_id() ) ) {
    wp_redirect( home_url('/') );
    exit;
}

// Redirect to the WP admin HubSkills panel
wp_redirect( admin_url( 'admin.php?page=hubskills-lms' ) );
exit;
