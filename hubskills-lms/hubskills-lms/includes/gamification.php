<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── XP ────────────────────────────────────────────────────

/**
 * Award XP to a user. Returns new total.
 */
function hs_award_xp( int $user_id, int $amount ) {
    $current = (int) get_user_meta( $user_id, 'hs_xp', true );
    $new_xp  = $current + $amount;
    update_user_meta( $user_id, 'hs_xp', $new_xp );

    // Check level up
    $old_level = hs_get_level( $current );
    $new_level = hs_get_level( $new_xp );
    if ( $new_level > $old_level ) {
        do_action( 'hs_level_up', $user_id, $new_level, $old_level );
    }

    do_action( 'hs_after_award_xp', $user_id, $amount, $new_xp );
    return $new_xp;
}

/**
 * Calculate level from XP. Formula: level = floor(xp/500)+1
 */
function hs_get_level( int $xp ) {
    return max( 1, (int) floor( $xp / 500 ) + 1 );
}

/**
 * Get XP needed to reach next level.
 */
function hs_get_xp_to_next_level( int $xp ) {
    $level   = hs_get_level( $xp );
    $xp_next = $level * 500;
    $xp_prev = ( $level - 1 ) * 500;
    $percent = $xp_next > $xp_prev ? round( ( ( $xp - $xp_prev ) / ( $xp_next - $xp_prev ) ) * 100 ) : 100;
    return [ 'level' => $level, 'xp_next' => $xp_next, 'xp_prev' => $xp_prev, 'percent' => $percent ];
}

// ── Badges ────────────────────────────────────────────────

const HS_BADGES = [
    'first_lesson'  => [ 'name' => 'Primeira Aula',    'icon' => '🎯', 'color' => '#7C3AED' ],
    'quiz_master'   => [ 'name' => 'Mestre Quiz',      'icon' => '🧠', 'color' => '#F59E0B' ],
    'week_streak'   => [ 'name' => '7 Dias Seguidos',  'icon' => '🔥', 'color' => '#EF4444' ],
    'night_owl'     => [ 'name' => 'Coruja Noturna',   'icon' => '🦉', 'color' => '#06B6D4' ],
    'fast_learner'  => [ 'name' => 'Aprendiz Rápido',  'icon' => '⚡', 'color' => '#F472B6' ],
    'perfectionist' => [ 'name' => 'Perfeccionista',   'icon' => '💎', 'color' => '#10B981' ],
    'graduate'      => [ 'name' => 'Graduado',          'icon' => '🎓', 'color' => '#8B5CF6' ],
];

/**
 * Award a badge to a user.
 */
function hs_award_badge( int $user_id, string $badge_id ) {
    if ( ! isset( HS_BADGES[ $badge_id ] ) ) return false;

    $badges = (array)( get_user_meta( $user_id, 'hs_badges', true ) ?: [] );
    if ( in_array( $badge_id, $badges, true ) ) return false;

    $badges[] = $badge_id;
    update_user_meta( $user_id, 'hs_badges', $badges );

    $badge = HS_BADGES[ $badge_id ];
    hs_log_activity( $user_id, 'badge', sprintf(
        __( 'Conquistou o badge <strong>%s %s</strong>!', 'hubskills-lms' ),
        esc_html( $badge['icon'] ),
        esc_html( $badge['name'] )
    ) );

    do_action( 'hs_badge_awarded', $user_id, $badge_id );
    return true;
}

/**
 * Check if user has a badge.
 */
function hs_has_badge( int $user_id, string $badge_id ) {
    $badges = (array)( get_user_meta( $user_id, 'hs_badges', true ) ?: [] );
    return in_array( $badge_id, $badges, true );
}

// ── Streak ────────────────────────────────────────────────

/**
 * Update daily learning streak.
 */
function hs_update_streak( int $user_id ) {
    $last_active = get_user_meta( $user_id, 'hs_last_active', true );
    $today       = current_time( 'Y-m-d' );
    $yesterday   = date( 'Y-m-d', strtotime( '-1 day', current_time( 'timestamp' ) ) );
    $streak      = (int) get_user_meta( $user_id, 'hs_streak', true );

    if ( $last_active === $today ) {
        // Already updated today
    } elseif ( $last_active === $yesterday ) {
        // Consecutive day
        $streak++;
        update_user_meta( $user_id, 'hs_streak', $streak );
        if ( $streak >= 7 ) hs_award_badge( $user_id, 'week_streak' );
    } else {
        // Streak broken
        update_user_meta( $user_id, 'hs_streak', 1 );
    }

    update_user_meta( $user_id, 'hs_last_active', $today );
}

// ── Activity Log ──────────────────────────────────────────

/**
 * Log a user activity.
 */
function hs_log_activity( int $user_id, string $type, string $text ) {
    $activity = (array)( get_user_meta( $user_id, 'hs_activity', true ) ?: [] );
    $colors   = [ 'lesson' => '#7C3AED', 'quiz' => '#F59E0B', 'badge' => '#F472B6', 'enroll' => '#06B6D4', 'cert' => '#10B981' ];

    array_unshift( $activity, [
        'type'  => $type,
        'text'  => $text,
        'time'  => esc_html( date_i18n( 'd/m/Y H:i', current_time('timestamp') ) ),
        'color' => $colors[ $type ] ?? '#7C3AED',
    ] );

    // Keep only last 20 activities
    if ( count( $activity ) > 20 ) array_splice( $activity, 20 );

    update_user_meta( $user_id, 'hs_activity', $activity );
}

/**
 * Get user stats summary.
 */
function hs_get_user_stats( int $user_id ) {
    $enrollments = get_user_meta( $user_id, 'hs_enrollments', true ) ?: [];
    $total       = count( $enrollments );
    $completed   = 0;
    $minutes     = 0;
    foreach ( $enrollments as $data ) {
        if ( ($data['status'] ?? '') === 'completed' ) $completed++;
        $minutes += (int)($data['minutes'] ?? 0);
    }
    $xp    = (int) get_user_meta( $user_id, 'hs_xp', true );
    $level = hs_get_level( $xp );
    return [
        'enrolled'   => $total,
        'completed'  => $completed,
        'in_progress'=> $total - $completed,
        'certificates'=> count( (array)(get_user_meta($user_id, 'hs_certificates', true) ?: []) ),
        'badges'     => count( (array)(get_user_meta($user_id, 'hs_badges', true) ?: []) ),
        'xp'         => $xp,
        'level'      => $level,
        'streak'     => (int) get_user_meta( $user_id, 'hs_streak', true ),
        'hours'      => round( $minutes / 60 ),
    ];
}
