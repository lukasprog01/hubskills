<?php
/**
 * Template Name: Dashboard
 */
if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}
get_header();

$user    = wp_get_current_user();
$user_id = $user->ID;
$xp      = (int) get_user_meta( $user_id, 'hs_xp', true );
$level   = max( 1, floor( $xp / 500 ) + 1 );
$streak  = (int) get_user_meta( $user_id, 'hs_streak', true );
$xp_next = $level * 500;
$xp_prev = ( $level - 1 ) * 500;
$xp_pct  = $xp_next > $xp_prev ? round( ( ( $xp - $xp_prev ) / ( $xp_next - $xp_prev ) ) * 100 ) : 100;

$hour = (int) current_time('G');
if ( $hour < 12 ) $greeting = __( 'Bom dia', 'hubskills' );
elseif ( $hour < 18 ) $greeting = __( 'Boa tarde', 'hubskills' );
else $greeting = __( 'Boa noite', 'hubskills' );

// Stats
$enrollments  = get_user_meta( $user_id, 'hs_enrollments', true ) ?: [];
$total_enroll = count( $enrollments );
$completed    = 0;
$total_minutes = 0;
foreach ( $enrollments as $course_id => $data ) {
    if ( isset($data['status']) && $data['status'] === 'completed' ) $completed++;
    $total_minutes += (int) ($data['minutes'] ?? 0);
}
$certs = get_user_meta( $user_id, 'hs_certificates', true ) ?: [];
$badges = get_user_meta( $user_id, 'hs_badges', true ) ?: [];

// Recent enrolled courses
$enrolled_posts = [];
if ( ! empty($enrollments) ) {
    $enrolled_posts = get_posts([
        'post_type'   => 'hubskills_course',
        'post__in'    => array_keys( $enrollments ),
        'orderby'     => 'post__in',
        'numberposts' => 5,
    ]);
}

// Activity log
$activity = get_user_meta( $user_id, 'hs_activity', true ) ?: [];
?>

<!-- Welcome Banner -->
<div class="welcome-banner" data-aos="fade-down">
    <div class="welcome-info">
        <h1><?php echo esc_html($greeting) . ', ' . esc_html($user->display_name); ?>! 👋</h1>
        <p><?php _e('Continue sua jornada de aprendizado. Você está indo muito bem!', 'hubskills'); ?></p>
    </div>
    <div class="welcome-stats">
        <div class="ws-item">
            <div class="ws-icon ws-icon--xp"><i class="fas fa-bolt"></i></div>
            <div class="ws-data">
                <span class="ws-value"><?php echo number_format($xp, 0, ',', '.'); ?> XP</span>
                <span class="ws-label"><?php _e('Pontos', 'hubskills'); ?></span>
            </div>
        </div>
        <div class="ws-item">
            <div class="ws-icon ws-icon--level"><i class="fas fa-layer-group"></i></div>
            <div class="ws-data">
                <span class="ws-value"><?php _e('Nível', 'hubskills'); ?> <?php echo (int)$level; ?></span>
                <span class="ws-label"><?php echo (int)$xp_pct; ?>% <?php _e('para o próximo', 'hubskills'); ?></span>
            </div>
        </div>
        <div class="ws-item">
            <div class="ws-icon ws-icon--streak"><i class="fas fa-fire"></i></div>
            <div class="ws-data">
                <span class="ws-value"><?php echo (int)$streak; ?> <?php _e('dias', 'hubskills'); ?></span>
                <span class="ws-label"><?php _e('Sequência', 'hubskills'); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid" data-aos="fade-up" data-aos-delay="100">
    <div class="stat-card">
        <div class="stat-icon stat-icon--blue"><i class="fas fa-graduation-cap"></i></div>
        <div class="stat-info">
            <span class="stat-value counter" data-target="<?php echo (int)$total_enroll; ?>">0</span>
            <span class="stat-label"><?php _e('Matriculados', 'hubskills'); ?></span>
        </div>
        <div class="stat-trend stat-trend--up"><i class="fas fa-arrow-up"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <span class="stat-value counter" data-target="<?php echo (int)$completed; ?>">0</span>
            <span class="stat-label"><?php _e('Concluídos', 'hubskills'); ?></span>
        </div>
        <div class="stat-trend stat-trend--up"><i class="fas fa-arrow-up"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--yellow"><i class="fas fa-certificate"></i></div>
        <div class="stat-info">
            <span class="stat-value counter" data-target="<?php echo count($certs); ?>">0</span>
            <span class="stat-label"><?php _e('Certificados', 'hubskills'); ?></span>
        </div>
        <div class="stat-trend stat-trend--up"><i class="fas fa-arrow-up"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--purple"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <span class="stat-value counter" data-target="<?php echo (int)round($total_minutes/60); ?>">0</span>
            <span class="stat-label"><?php _e('Horas estudadas', 'hubskills'); ?></span>
        </div>
        <div class="stat-trend stat-trend--up"><i class="fas fa-arrow-up"></i></div>
    </div>
</div>

<?php
if ( function_exists( 'hs_lms_render_dashboard_tools' ) ) {
    echo hs_lms_render_dashboard_tools();
}
?>

<!-- Main Grid -->
<div class="dashboard-grid" data-aos="fade-up" data-aos-delay="200">

    <!-- Left Column -->
    <div class="dashboard-left">

        <!-- Enrolled Courses -->
        <div class="card-glass mb-4">
            <div class="card-glass-header">
                <h3><?php _e('Meus Cursos', 'hubskills'); ?></h3>
                <a href="<?php echo esc_url( home_url('/cursos/') ); ?>" class="link-accent"><?php _e('Ver todos', 'hubskills'); ?></a>
            </div>
            <div class="enrolled-courses">
                <?php if ( empty($enrolled_posts) ) : ?>
                <div class="empty-state">
                    <i class="fas fa-graduation-cap"></i>
                    <p><?php _e('Nenhum curso ainda. Que tal começar agora?', 'hubskills'); ?></p>
                    <a href="<?php echo esc_url( home_url('/cursos/') ); ?>" class="btn btn-gradient btn-sm">
                        <?php _e('Explorar Cursos', 'hubskills'); ?>
                    </a>
                </div>
                <?php else : foreach ($enrolled_posts as $ep) :
                    $enroll_data = function_exists( 'hs_get_enrollment' ) ? hs_get_enrollment( $user_id, $ep->ID ) : ( $enrollments[$ep->ID] ?? [] );
                    $progress    = (int) ($enroll_data['progress'] ?? 0);
                    $status      = $enroll_data['status'] ?? 'in_progress';
                    $instructor  = get_post_meta($ep->ID, '_hs_instructor', true);
                    $icon        = get_post_meta($ep->ID, '_hs_icon', true) ?: '📚';
                    $color_start = get_post_meta($ep->ID, '_hs_color_start', true) ?: '#7C3AED';
                    $color_end   = get_post_meta($ep->ID, '_hs_color_end', true) ?: '#06B6D4';
                ?>
                <div class="enrolled-item">
                    <div class="enrolled-thumb" style="background: linear-gradient(135deg, <?php echo esc_attr($color_start); ?>, <?php echo esc_attr($color_end); ?>)">
                        <?php echo esc_html($icon); ?>
                    </div>
                    <div class="enrolled-info">
                        <h4><?php echo esc_html(get_the_title($ep)); ?></h4>
                        <p><?php echo esc_html($instructor); ?></p>
                        <div class="enrolled-progress">
                            <div class="progress">
                                <div class="progress-bar" style="width: <?php echo (int)$progress; ?>%"></div>
                            </div>
                            <span><?php echo (int)$progress; ?>%</span>
                        </div>
                    </div>
                    <div class="enrolled-status">
                        <?php if ($status === 'completed') : ?>
                        <span class="badge badge-success"><?php _e('Concluído', 'hubskills'); ?></span>
                        <?php else : ?>
                        <a href="<?php echo esc_url(get_permalink($ep)); ?>" class="btn btn-sm btn-gradient">
                            <?php _e('Continuar', 'hubskills'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card-glass h-100">
                    <div class="card-glass-header"><h3><?php _e('Status dos Cursos', 'hubskills'); ?></h3></div>
                    <div class="chart-wrap">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card-glass h-100">
                    <div class="card-glass-header"><h3><?php _e('Minutos por Semana', 'hubskills'); ?></h3></div>
                    <div class="chart-wrap">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- .dashboard-left -->

    <!-- Right Column -->
    <div class="dashboard-right">

        <!-- Activity -->
        <div class="card-glass mb-4">
            <div class="card-glass-header"><h3><?php _e('Atividade Recente', 'hubskills'); ?></h3></div>
            <div class="activity-list">
                <?php if ( empty($activity) ) : ?>
                <p class="text-muted text-center py-3"><?php _e('Nenhuma atividade ainda.', 'hubskills'); ?></p>
                <?php else : foreach ( array_slice(array_reverse($activity), 0, 6) as $act ) :
                    $colors = [ 'lesson' => '#7C3AED', 'quiz' => '#F59E0B', 'badge' => '#F472B6', 'enroll' => '#06B6D4', 'cert' => '#10B981' ];
                    $icons  = [ 'lesson' => 'play-circle', 'quiz' => 'tasks', 'badge' => 'medal', 'enroll' => 'graduation-cap', 'cert' => 'certificate' ];
                    $type   = $act['type'] ?? 'lesson';
                    $color  = $colors[$type] ?? '#7C3AED';
                    $ico    = $icons[$type] ?? 'circle';
                ?>
                <div class="activity-item">
                    <div class="activity-dot" style="background: <?php echo esc_attr($color); ?>22; color: <?php echo esc_attr($color); ?>">
                        <i class="fas fa-<?php echo esc_attr($ico); ?>"></i>
                    </div>
                    <div class="activity-info">
                        <p><?php echo wp_kses_post($act['text'] ?? ''); ?></p>
                        <span><?php echo esc_html($act['time'] ?? ''); ?></span>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Badges -->
        <div class="card-glass">
            <div class="card-glass-header"><h3><?php _e('Conquistas', 'hubskills'); ?></h3></div>
            <div class="badges-grid">
                <?php
                $all_badges = [
                    [ 'id' => 'first_lesson', 'icon' => '🎯', 'name' => __('Primeira Aula', 'hubskills'), 'color' => '#7C3AED' ],
                    [ 'id' => 'quiz_master',  'icon' => '🧠', 'name' => __('Mestre Quiz', 'hubskills'), 'color' => '#F59E0B' ],
                    [ 'id' => 'week_streak',  'icon' => '🔥', 'name' => __('7 Dias Seguidos', 'hubskills'), 'color' => '#EF4444' ],
                    [ 'id' => 'night_owl',    'icon' => '🦉', 'name' => __('Coruja Noturna', 'hubskills'), 'color' => '#06B6D4' ],
                    [ 'id' => 'fast_learner', 'icon' => '⚡', 'name' => __('Aprendiz Rápido', 'hubskills'), 'color' => '#F472B6' ],
                    [ 'id' => 'perfectionist','icon' => '💎', 'name' => __('Perfeccionista', 'hubskills'), 'color' => '#10B981' ],
                    [ 'id' => 'graduate',     'icon' => '🎓', 'name' => __('Graduado', 'hubskills'), 'color' => '#8B5CF6' ],
                ];
                foreach ($all_badges as $badge) :
                    $earned = in_array($badge['id'], (array)$badges, true);
                ?>
                <div class="badge-item <?php echo $earned ? 'badge-earned' : 'badge-locked'; ?>" title="<?php echo esc_attr($badge['name']); ?>">
                    <div class="badge-icon" style="<?php echo $earned ? 'background: '.$badge['color'].'22; border-color: '.$badge['color'].'44;' : ''; ?>">
                        <?php echo $earned ? esc_html($badge['icon']) : '<i class="fas fa-lock"></i>'; ?>
                    </div>
                    <span><?php echo esc_html($badge['name']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div><!-- .dashboard-right -->
</div><!-- .dashboard-grid -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx1 = document.getElementById('statusChart');
    const ctx2 = document.getElementById('weeklyChart');
    const completed = <?php echo (int)$completed; ?>;
    const total = <?php echo (int)$total_enroll; ?>;
    const inProgress = total - completed;

    if (typeof Chart === 'undefined') {
        return;
    }

    if (ctx1) {
        new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: ['<?php _e("Concluídos", "hubskills"); ?>', '<?php _e("Em andamento", "hubskills"); ?>'],
                datasets: [{ data: [completed, inProgress], backgroundColor: ['#10B981', '#7C3AED'], borderWidth: 0, hoverOffset: 6 }]
            },
            options: { plugins: { legend: { labels: { color: '#94A3B8', font: { family: 'Inter' } } } }, cutout: '65%' }
        });
    }

    const weekly = <?php
        $weekly = get_user_meta($user_id, 'hs_weekly_minutes', true) ?: [0,0,0,0,0,0,0];
        echo json_encode(array_values((array)$weekly));
    ?>;
    if (ctx2) {
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: ['<?php _e("Seg","hubskills"); ?>','<?php _e("Ter","hubskills"); ?>','<?php _e("Qua","hubskills"); ?>','<?php _e("Qui","hubskills"); ?>','<?php _e("Sex","hubskills"); ?>','<?php _e("Sáb","hubskills"); ?>','<?php _e("Dom","hubskills"); ?>'],
                datasets: [{ data: weekly, backgroundColor: 'rgba(124,58,237,0.6)', borderRadius: 6, hoverBackgroundColor: '#7C3AED' }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: '#64748B' }, grid: { display: false } },
                    y: { ticks: { color: '#64748B' }, grid: { color: 'rgba(255,255,255,0.04)' } }
                }
            }
        });
    }
});
</script>

<?php get_footer(); ?>
