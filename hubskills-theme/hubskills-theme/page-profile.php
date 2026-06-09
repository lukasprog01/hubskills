<?php
/**
 * Template Name: Perfil
 */
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }
get_header();
$user    = wp_get_current_user();
$user_id = $user->ID;
$xp      = (int) get_user_meta($user_id, 'hs_xp', true);
$level   = max(1, floor($xp/500)+1);
$streak  = (int) get_user_meta($user_id, 'hs_streak', true);
$badges  = (array)(get_user_meta($user_id, 'hs_badges', true) ?: []);
$enrollments = get_user_meta($user_id, 'hs_enrollments', true) ?: [];
$xp_next = $level * 500;
$xp_prev = ($level-1)*500;
$xp_pct  = $xp_next > $xp_prev ? round((($xp-$xp_prev)/($xp_next-$xp_prev))*100) : 100;
?>

<div class="profile-page" data-aos="fade-up">
    <div class="profile-hero">
        <div class="profile-cover" style="background: var(--gradient-brand)"></div>
        <div class="profile-info">
            <div class="profile-avatar-wrap">
                <img src="<?php echo esc_url(get_avatar_url($user_id, ['size'=>120])); ?>" alt="avatar" class="profile-avatar">
                <div class="profile-level-badge">Nv. <?php echo (int)$level; ?></div>
            </div>
            <div class="profile-details">
                <h1><?php echo esc_html($user->display_name); ?></h1>
                <p class="profile-email"><?php echo esc_html($user->user_email); ?></p>
                <div class="profile-meta">
                    <span><i class="fas fa-calendar"></i> <?php _e('Membro desde', 'hubskills'); ?> <?php echo date_i18n('M Y', strtotime($user->user_registered)); ?></span>
                    <span><i class="fas fa-book"></i> <?php echo count($enrollments); ?> <?php _e('cursos', 'hubskills'); ?></span>
                    <span><i class="fas fa-medal"></i> <?php echo count($badges); ?> <?php _e('conquistas', 'hubskills'); ?></span>
                </div>
            </div>
            <a href="<?php echo esc_url( home_url('/perfil/?tab=settings') ); ?>" class="btn btn-ghost btn-sm">
                <i class="fas fa-edit me-1"></i><?php _e('Editar Perfil', 'hubskills'); ?>
            </a>
        </div>
    </div>

    <!-- XP Bar -->
    <div class="xp-progress-card" data-aos="fade-up" data-aos-delay="100">
        <div class="xp-info">
            <span class="xp-label"><?php _e('Nível', 'hubskills'); ?> <?php echo (int)$level; ?></span>
            <span class="xp-value"><?php echo number_format($xp,0,',','.'); ?> / <?php echo number_format($xp_next,0,',','.'); ?> XP</span>
        </div>
        <div class="progress xp-bar">
            <div class="progress-bar" style="width: <?php echo (int)$xp_pct; ?>%" data-target="<?php echo (int)$xp_pct; ?>"></div>
        </div>
        <div class="xp-stats-row">
            <div class="xps-item"><i class="fas fa-bolt text-purple"></i><span><?php echo number_format($xp,0,',','.'); ?> XP Total</span></div>
            <div class="xps-item"><i class="fas fa-fire text-red"></i><span><?php echo (int)$streak; ?> <?php _e('dias de sequência', 'hubskills'); ?></span></div>
            <div class="xps-item"><i class="fas fa-medal text-yellow"></i><span><?php echo count($badges); ?> <?php _e('conquistas', 'hubskills'); ?></span></div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <!-- Courses -->
        <div class="col-lg-8">
            <div class="card-glass">
                <div class="card-glass-header"><h3><?php _e('Histórico de Cursos', 'hubskills'); ?></h3></div>
                <?php if ( empty($enrollments) ) : ?>
                <div class="empty-state py-4 text-center">
                    <i class="fas fa-graduation-cap"></i>
                    <p><?php _e('Nenhum curso ainda.', 'hubskills'); ?></p>
                    <a href="<?php echo esc_url(home_url('/cursos/')); ?>" class="btn btn-gradient btn-sm"><?php _e('Explorar Cursos', 'hubskills'); ?></a>
                </div>
                <?php else :
                    $enrolled_posts = get_posts(['post_type'=>'hubskills_course','post__in'=>array_keys($enrollments),'numberposts'=>-1]);
                    foreach ($enrolled_posts as $ep) :
                        $d = function_exists( 'hs_get_enrollment' ) ? hs_get_enrollment( $user_id, $ep->ID ) : ( $enrollments[$ep->ID] ?? [] );
                        $prog = (int)($d['progress'] ?? 0);
                        $status = $d['status'] ?? 'in_progress';
                        $icon = get_post_meta($ep->ID,'_hs_icon',true) ?: '📚';
                        $cs = get_post_meta($ep->ID,'_hs_color_start',true) ?: '#7C3AED';
                        $ce = get_post_meta($ep->ID,'_hs_color_end',true) ?: '#06B6D4';
                ?>
                <div class="enrolled-item">
                    <div class="enrolled-thumb" style="background: linear-gradient(135deg, <?php echo esc_attr($cs); ?>, <?php echo esc_attr($ce); ?>)"><?php echo esc_html($icon); ?></div>
                    <div class="enrolled-info flex-grow-1">
                        <h4><?php echo esc_html(get_the_title($ep)); ?></h4>
                        <div class="progress mt-1"><div class="progress-bar" style="width:<?php echo $prog;?>%"></div></div>
                        <small class="text-muted"><?php echo $prog; ?>% <?php _e('concluído', 'hubskills'); ?></small>
                    </div>
                    <?php if ($status==='completed') : ?>
                    <span class="badge badge-success"><?php _e('Concluído', 'hubskills'); ?></span>
                    <?php else : ?>
                    <a href="<?php echo esc_url(get_permalink($ep)); ?>" class="btn btn-sm btn-gradient"><?php _e('Continuar', 'hubskills'); ?></a>
                    <?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Badges -->
        <div class="col-lg-4">
            <div class="card-glass">
                <div class="card-glass-header"><h3><?php _e('Conquistas', 'hubskills'); ?></h3></div>
                <div class="badges-grid">
                    <?php
                    $all_badges = [
                        ['id'=>'first_lesson','icon'=>'🎯','name'=>__('Primeira Aula','hubskills'),'color'=>'#7C3AED'],
                        ['id'=>'quiz_master','icon'=>'🧠','name'=>__('Mestre Quiz','hubskills'),'color'=>'#F59E0B'],
                        ['id'=>'week_streak','icon'=>'🔥','name'=>__('7 Dias Seguidos','hubskills'),'color'=>'#EF4444'],
                        ['id'=>'night_owl','icon'=>'🦉','name'=>__('Coruja Noturna','hubskills'),'color'=>'#06B6D4'],
                        ['id'=>'fast_learner','icon'=>'⚡','name'=>__('Aprendiz Rápido','hubskills'),'color'=>'#F472B6'],
                        ['id'=>'perfectionist','icon'=>'💎','name'=>__('Perfeccionista','hubskills'),'color'=>'#10B981'],
                        ['id'=>'graduate','icon'=>'🎓','name'=>__('Graduado','hubskills'),'color'=>'#8B5CF6'],
                    ];
                    foreach ($all_badges as $b) :
                        $earned = in_array($b['id'], $badges, true);
                    ?>
                    <div class="badge-item <?php echo $earned ? 'badge-earned' : 'badge-locked'; ?>" title="<?php echo esc_attr($b['name']); ?>">
                        <div class="badge-icon" style="<?php echo $earned ? 'background:'.$b['color'].'22;border-color:'.$b['color'].'44' : ''; ?>">
                            <?php echo $earned ? esc_html($b['icon']) : '<i class="fas fa-lock"></i>'; ?>
                        </div>
                        <span><?php echo esc_html($b['name']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ( function_exists( 'hs_lms_render_my_orders' ) ) : ?>
    <div class="row mt-4" data-aos="fade-up">
        <div class="col-12">
            <?php echo hs_lms_render_my_orders(); ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
