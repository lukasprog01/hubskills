<?php
/**
 * HubSkills LMS – Roles & Capabilities
 *
 * Registers custom WordPress roles for the LMS:
 *   hs_student    – default for every new registrant
 *   hs_instructor – can create and manage own courses
 *   hs_admin      – full LMS management (not WP super-admin)
 *
 * @package HubSkills_LMS
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Role definitions ──────────────────────────────────────────────────────────

/**
 * Capability map used when creating roles.
 *
 * @return array<string, array<string, bool>>
 */
function hs_get_role_caps(): array {
    return [
        'hs_student' => [
            'read'                   => true,
            'hs_view_courses'        => true,
            'hs_access_dashboard'    => true,
        ],
        'hs_instructor' => [
            'read'                   => true,
            'upload_files'           => true,
            'hs_view_courses'        => true,
            'hs_access_dashboard'    => true,
            'hs_create_courses'      => true,
            'hs_edit_own_courses'    => true,
            'hs_delete_own_courses'  => true,
            'hs_view_own_students'   => true,
            'hs_view_own_earnings'   => true,
            'hs_publish_courses'     => true,
            'edit_hubskills_courses' => true,
            'edit_hubskills_course'  => true,
            'read_hubskills_course'  => true,
            'delete_hubskills_course' => true,
            'delete_hubskills_courses' => true,
            'edit_published_hubskills_courses' => true,
            'delete_published_hubskills_courses' => true,
            'publish_hubskills_courses' => true,
            'edit_hubskills_quizzes' => true,
            'edit_hubskills_quiz'    => true,
            'read_hubskills_quiz'    => true,
            'delete_hubskills_quiz'  => true,
            'delete_hubskills_quizzes' => true,
            'edit_published_hubskills_quizzes' => true,
            'delete_published_hubskills_quizzes' => true,
            'publish_hubskills_quizzes' => true,
        ],
        'hs_admin' => [
            'read'                   => true,
            'upload_files'           => true,
            'hs_view_courses'        => true,
            'hs_access_dashboard'    => true,
            'hs_create_courses'      => true,
            'hs_edit_own_courses'    => true,
            'hs_delete_own_courses'  => true,
            'hs_edit_any_course'     => true,
            'hs_delete_any_course'   => true,
            'hs_publish_courses'     => true,
            'hs_view_own_students'   => true,
            'hs_view_own_earnings'   => true,
            'hs_view_all_students'   => true,
            'hs_view_all_earnings'   => true,
            'hs_manage_instructors'  => true,
            'hs_manage_orders'       => true,
            'hs_manage_settings'     => true,
            'hs_manage_coupons'      => true,
            'hs_approve_instructors' => true,
            'hs_view_reports'        => true,
            'edit_hubskills_courses' => true,
            'edit_hubskills_course'  => true,
            'read_hubskills_course'  => true,
            'delete_hubskills_course' => true,
            'delete_hubskills_courses' => true,
            'edit_published_hubskills_courses' => true,
            'delete_published_hubskills_courses' => true,
            'publish_hubskills_courses' => true,
            'edit_others_hubskills_courses' => true,
            'delete_others_hubskills_courses' => true,
            'read_private_hubskills_courses' => true,
            'edit_private_hubskills_courses' => true,
            'delete_private_hubskills_courses' => true,
            'edit_hubskills_quizzes' => true,
            'edit_hubskills_quiz'    => true,
            'read_hubskills_quiz'    => true,
            'delete_hubskills_quiz'  => true,
            'delete_hubskills_quizzes' => true,
            'edit_published_hubskills_quizzes' => true,
            'delete_published_hubskills_quizzes' => true,
            'publish_hubskills_quizzes' => true,
            'edit_others_hubskills_quizzes' => true,
            'delete_others_hubskills_quizzes' => true,
            'read_private_hubskills_quizzes' => true,
            'edit_private_hubskills_quizzes' => true,
            'delete_private_hubskills_quizzes' => true,
        ],
    ];
}

// ── Activation / Deactivation ─────────────────────────────────────────────────

/**
 * Register all HubSkills LMS roles.
 * Called on plugin activation.
 */
function hs_register_roles(): void {
    $roles = hs_get_role_caps();

    add_role(
        'hs_student',
        __( 'HS Student', 'hubskills-lms' ),
        $roles['hs_student']
    );

    add_role(
        'hs_instructor',
        __( 'HS Instructor', 'hubskills-lms' ),
        $roles['hs_instructor']
    );

    add_role(
        'hs_admin',
        __( 'HS Admin', 'hubskills-lms' ),
        $roles['hs_admin']
    );

    hs_sync_role_caps();
}

function hs_sync_role_caps(): void {
    $roles = hs_get_role_caps();
    foreach ( $roles as $role_name => $caps ) {
        $role = get_role( $role_name );
        if ( ! $role ) {
            continue;
        }

        foreach ( $caps as $cap => $grant ) {
            $grant ? $role->add_cap( $cap ) : $role->remove_cap( $cap );
        }
    }

    $admin = get_role( 'administrator' );
    if ( $admin ) {
        foreach ( $roles['hs_admin'] as $cap => $grant ) {
            if ( $grant ) {
                $admin->add_cap( $cap );
            }
        }
    }
}

add_action( 'admin_init', 'hs_maybe_sync_role_caps' );
function hs_maybe_sync_role_caps(): void {
    $version = defined( 'HS_LMS_VERSION' ) ? HS_LMS_VERSION : '1.0.0';
    if ( get_option( 'hs_roles_version' ) === $version ) {
        return;
    }

    $missing_role = false;
    foreach ( array_keys( hs_get_role_caps() ) as $role_name ) {
        if ( ! get_role( $role_name ) ) {
            $missing_role = true;
            break;
        }
    }

    $missing_role ? hs_register_roles() : hs_sync_role_caps();
    update_option( 'hs_roles_version', $version, false );
}

/**
 * Remove all HubSkills LMS roles.
 * Called on plugin deactivation.
 */
function hs_remove_roles(): void {
    remove_role( 'hs_student' );
    remove_role( 'hs_instructor' );
    remove_role( 'hs_admin' );
}

// Hook to activation / deactivation via main plugin file actions.
add_action( 'hs_lms_activate',   'hs_register_roles' );
add_action( 'hs_lms_deactivate', 'hs_remove_roles' );

// ── Auto-assign hs_student on registration ────────────────────────────────────

add_action( 'user_register', 'hs_assign_student_role_on_register', 10, 1 );
/**
 * Assign the hs_student role to every newly registered user.
 *
 * @param int $user_id
 */
function hs_assign_student_role_on_register( int $user_id ): void {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return;
    }

    // Do not override if role already set (e.g. an admin-created account).
    $existing_roles = (array) $user->roles;
    if ( ! empty( $existing_roles ) && $existing_roles !== [ 'subscriber' ] ) {
        return;
    }

    $user->set_role( 'hs_student' );
    update_user_meta( $user_id, 'hs_role', 'hs_student' );
}

// ── Role query helpers ────────────────────────────────────────────────────────

/**
 * Return the primary HubSkills role of a user, or false if none.
 *
 * Priority: hs_admin > hs_instructor > hs_student.
 *
 * @param int $user_id
 * @return string|false  'hs_admin'|'hs_instructor'|'hs_student'|false
 */
function hs_get_user_role( int $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return false;
    }

    $roles = (array) $user->roles;

    foreach ( [ 'hs_admin', 'hs_instructor', 'hs_student' ] as $hs_role ) {
        if ( in_array( $hs_role, $roles, true ) ) {
            return $hs_role;
        }
    }

    return false;
}

/**
 * Check whether a user holds the hs_instructor role.
 *
 * @param int $user_id
 * @return bool
 */
function hs_is_instructor( int $user_id ): bool {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return false;
    }

    return in_array( 'hs_instructor', (array) $user->roles, true );
}

/**
 * Check whether a user is an LMS admin.
 *
 * Returns true for both the hs_admin role and WordPress administrators.
 *
 * @param int $user_id
 * @return bool
 */
function hs_is_hs_admin( int $user_id ): bool {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return false;
    }

    $roles = (array) $user->roles;

    return in_array( 'hs_admin', $roles, true )
        || in_array( 'administrator', $roles, true );
}

// ── Role promotion / demotion ─────────────────────────────────────────────────

/**
 * Promote a user to hs_instructor.
 *
 * Removes any existing hs_student role, grants instructor capabilities, and
 * writes user meta so other parts of the plugin can query instructor data.
 *
 * @param int $user_id
 * @return bool  True on success, false if user does not exist.
 */
function hs_promote_to_instructor( int $user_id ): bool {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return false;
    }

    // Remove subscriber / student before adding instructor.
    $user->remove_role( 'subscriber' );
    $user->remove_role( 'hs_student' );
    $user->add_role( 'hs_instructor' );

    // Persist role snapshot in meta for fast lookups.
    update_user_meta( $user_id, 'hs_role', 'hs_instructor' );
    update_user_meta( $user_id, 'hs_instructor_since', current_time( 'mysql' ) );

    // Set default commission rate if not already configured.
    if ( ! get_user_meta( $user_id, 'hs_instructor_commission_rate', true ) ) {
        update_user_meta( $user_id, 'hs_instructor_commission_rate', 70 );
    }

    do_action( 'hs_after_promote_instructor', $user_id );

    return true;
}

/**
 * Revert an instructor back to the student role.
 *
 * @param int $user_id
 * @return bool
 */
function hs_demote_from_instructor( int $user_id ): bool {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return false;
    }

    $user->remove_role( 'hs_instructor' );
    $user->add_role( 'hs_student' );

    update_user_meta( $user_id, 'hs_role', 'hs_student' );
    delete_user_meta( $user_id, 'hs_instructor_since' );

    do_action( 'hs_after_demote_instructor', $user_id );

    return true;
}

// ── Capability check shortcut ─────────────────────────────────────────────────

/**
 * Check whether a user has a specific HubSkills capability.
 *
 * Wraps user_can() so callers do not need to know the underlying WP API.
 *
 * @param int    $user_id
 * @param string $cap
 * @return bool
 */
function hs_user_can( int $user_id, string $cap ): bool {
    return user_can( $user_id, $cap );
}
