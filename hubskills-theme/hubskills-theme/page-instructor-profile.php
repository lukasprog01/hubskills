<?php
/**
 * Template Name: Instructor Public Profile
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Resolve instructor from ?instructor=123 or current user
$instructor_id = isset($_GET['instructor']) ? (int)$_GET['instructor'] : 0;
if ( ! $instructor_id && is_user_logged_in() && hs_is_instructor(get_current_user_id()) ) {
    $instructor_id = get_current_user_id();
}

if ( ! $instructor_id || ! hs_is_instructor($instructor_id) ) {
    wp_redirect( home_url('/cursos/') );
    exit;
}

$profile  = hs_get_instructor_profile( $instructor_id );
$stats    = hs_get_instructor_stats( $instructor_id );
$courses  = get_posts([
    'post_type'      => 'hubskills_course',
    'posts_per_page' => 12,
    'post_status'    => 'publish',
    'meta_query'     => [['key'=>'_hs_instructor_id','value'=>$instructor_id]],
]);

get_header();
?>
<div class="hs-page-wrapper" style="min-height:100vh;padding:80px 0">
    <div class="container">

        <!-- Instructor Hero -->
        <div class="hs-card" style="padding:0;overflow:hidden;margin-bottom:32px">
            <div style="background:linear-gradient(135deg,var(--primary),var(--secondary));padding:48px 40px;position:relative">
                <div class="hs-instructor-hero-row">
                    <img src="<?php echo esc_url($profile['avatar']); ?>"
                         style="width:100px;height:100px;border-radius:50%;border:4px solid rgba(255,255,255,.3);flex-shrink:0">
                    <div style="color:#fff">
                        <div style="font-size:13px;opacity:.8;text-transform:uppercase;letter-spacing:1px"><?php _e('Instrutor','hubskills-lms'); ?></div>
                        <h1 style="font-size:32px;margin:4px 0;font-weight:700"><?php echo esc_html($profile['display_name']); ?></h1>
                        <?php if ($profile['tagline'] ?? '') : ?>
                        <div style="font-size:16px;opacity:.9"><?php echo esc_html($profile['tagline']); ?></div>
                        <?php endif; ?>
                        <?php if ($profile['expertise']) : ?>
                        <div style="margin-top:8px;font-size:14px;opacity:.8"><i class="fas fa-graduation-cap me-1"></i><?php echo esc_html($profile['expertise']); ?></div>
                        <?php endif; ?>
                        <div style="display:flex;gap:16px;margin-top:16px;flex-wrap:wrap">
                            <?php if ($profile['linkedin']) : ?>
                            <a href="<?php echo esc_url($profile['linkedin']); ?>" target="_blank" style="color:#fff;opacity:.8;font-size:20px"><i class="fab fa-linkedin"></i></a>
                            <?php endif; ?>
                            <?php if ($profile['youtube']) : ?>
                            <a href="<?php echo esc_url($profile['youtube']); ?>" target="_blank" style="color:#fff;opacity:.8;font-size:20px"><i class="fab fa-youtube"></i></a>
                            <?php endif; ?>
                            <?php if ($profile['website']) : ?>
                            <a href="<?php echo esc_url($profile['website']); ?>" target="_blank" style="color:#fff;opacity:.8;font-size:20px"><i class="fas fa-globe"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="hs-instructor-metrics">
                <?php
                $meta = [
                    ['fas fa-book','#7C3AED',$stats['courses'],__('Cursos','hubskills-lms')],
                    ['fas fa-users','#06B6D4',$stats['students'],__('Alunos','hubskills-lms')],
                    ['fas fa-star','#F59E0B',number_format($stats['rating'],1),__('Avaliação','hubskills-lms')],
                ];
                foreach ($meta as $m) : ?>
                <div style="display:flex;align-items:center;gap:10px">
                    <i class="<?php echo esc_attr($m[0]); ?>" style="font-size:22px;color:<?php echo esc_attr($m[1]); ?>"></i>
                    <div>
                        <div style="font-size:20px;font-weight:700;color:#1a1a1a"><?php echo esc_html($m[2]); ?></div>
                        <div style="font-size:12px;color:#666"><?php echo esc_html($m[3]); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="hs-profile-layout">
            <div>
                <!-- Bio -->
                <?php if ($profile['bio']) : ?>
                <div class="hs-card" style="margin-bottom:24px">
                    <h3><?php _e('Sobre o Instrutor','hubskills-lms'); ?></h3>
                    <div style="color:#555;line-height:1.7"><?php echo nl2br(esc_html($profile['bio'])); ?></div>
                </div>
                <?php endif; ?>

                <!-- Courses -->
                <div class="hs-card">
                    <h3><?php _e('Cursos','hubskills-lms'); ?></h3>
                    <?php if ($courses) : ?>
                    <div class="hs-course-mini-grid">
                        <?php
                        $user_id     = get_current_user_id();
                        $enrollments = $user_id ? (array)(get_user_meta($user_id,'hs_enrollments',true)?:[]) : [];
                        foreach ($courses as $c) :
                            $enroll_data = function_exists( 'hs_get_enrollment' ) ? hs_get_enrollment( $user_id, $c->ID ) : ( $enrollments[$c->ID] ?? [] );
                            $enrolled = ! empty( $enroll_data );
                            $progress = (int)($enroll_data['progress']??0);
                            echo hubskills_course_card($c, $enrolled, $progress);
                        endforeach;
                        ?>
                    </div>
                    <?php else : ?>
                    <p style="color:#999"><?php _e('Nenhum curso publicado.','hubskills-lms'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <?php if (!empty($profile['areas'])) : ?>
                <div class="hs-card" style="margin-bottom:20px">
                    <h4><?php _e('Áreas de Ensino','hubskills-lms'); ?></h4>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">
                        <?php foreach ($profile['areas'] as $area) : ?>
                        <span style="background:rgba(124,58,237,.1);color:#7C3AED;padding:4px 12px;border-radius:20px;font-size:13px"><?php echo esc_html($area); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="hs-card">
                    <h4><?php _e('Informações','hubskills-lms'); ?></h4>
                    <div style="font-size:14px;color:#555;line-height:2">
                        <div><i class="fas fa-calendar me-2" style="color:#7C3AED;width:16px"></i><?php _e('Instrutor desde','hubskills-lms'); ?> <?php echo esc_html(date_i18n('d/m/Y', strtotime($profile['joined']))); ?></div>
                        <div><i class="fas fa-book me-2" style="color:#06B6D4;width:16px"></i><?php echo (int) $stats['courses']; ?> <?php _e('cursos criados','hubskills-lms'); ?></div>
                        <div><i class="fas fa-users me-2" style="color:#F472B6;width:16px"></i><?php echo (int) $stats['students']; ?> <?php _e('alunos ensinados','hubskills-lms'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php get_footer(); ?>
