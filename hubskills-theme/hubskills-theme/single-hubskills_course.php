<?php
/**
 * Single Course Player Template
 */
if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}
get_header();

$user_id     = get_current_user_id();
$course_id   = get_the_ID();
$enrollments = get_user_meta( $user_id, 'hs_enrollments', true ) ?: [];
$enroll_data = function_exists( 'hs_get_enrollment' ) ? hs_get_enrollment( $user_id, $course_id ) : ( $enrollments[$course_id] ?? [] );
$is_user_enrolled = ! empty( $enroll_data );
$can_manage_course = function_exists( 'hs_lms_can_manage_course' ) && hs_lms_can_manage_course( $course_id, $user_id );
$is_enrolled = $is_user_enrolled || $can_manage_course;
$completed_l = (array)( $enroll_data['completed_lessons'] ?? [] );
$progress    = (int)($enroll_data['progress'] ?? 0);
$curriculum  = get_post_meta( $course_id, '_hs_curriculum', true ) ?: [];
$instructor  = get_post_meta( $course_id, '_hs_instructor', true );
$duration    = get_post_meta( $course_id, '_hs_duration', true );
$level       = get_post_meta( $course_id, '_hs_level', true );
$icon        = get_post_meta( $course_id, '_hs_icon', true ) ?: '📚';
$color_start = get_post_meta( $course_id, '_hs_color_start', true ) ?: '#7C3AED';
$color_end   = get_post_meta( $course_id, '_hs_color_end', true ) ?: '#06B6D4';
$rating      = (float) get_post_meta( $course_id, '_hs_rating', true );
$is_paid     = function_exists( 'hs_is_course_paid' ) && hs_is_course_paid( $course_id );
$price       = get_post_meta( $course_id, '_hs_price', true );

if ( ! $is_user_enrolled && $is_paid && function_exists( 'hs_user_has_access' ) && hs_user_has_access( $user_id, $course_id ) && function_exists( 'hs_enroll_user' ) ) {
    hs_enroll_user( $user_id, $course_id, 'payment' );
    $enroll_data = function_exists( 'hs_get_enrollment' ) ? hs_get_enrollment( $user_id, $course_id ) : [];
    $is_user_enrolled = ! empty( $enroll_data );
    $is_enrolled = $is_user_enrolled || $can_manage_course;
    $completed_l = (array)( $enroll_data['completed_lessons'] ?? [] );
    $progress    = (int)($enroll_data['progress'] ?? 0);
}

// Get first lesson if available
$first_lesson_url = '';
if ( ! empty($curriculum[0]['lessons'][0]['video_url'] ?? '') ) {
    $first_lesson_url = $curriculum[0]['lessons'][0]['video_url'];
}
$current_lesson_id  = (int)( $_GET['lesson'] ?? ( $curriculum[0]['lessons'][0]['id'] ?? 0 ) );
$current_lesson     = null;
$current_section    = null;
foreach ( $curriculum as $section ) {
    foreach ( $section['lessons'] ?? [] as $lesson ) {
        if ( (int)$lesson['id'] === $current_lesson_id ) {
            $current_lesson  = $lesson;
            $current_section = $section;
            break 2;
        }
    }
}
if ( ! $current_lesson && isset($curriculum[0]['lessons'][0]) ) {
    $current_lesson  = $curriculum[0]['lessons'][0];
    $current_section = $curriculum[0];
}
$video_url = $current_lesson['video_url'] ?? '';
?>

<div class="player-page" data-page="player" data-course="<?php echo (int)$course_id; ?>" data-current-lesson="<?php echo (int)$current_lesson_id; ?>">

    <!-- Breadcrumb -->
    <div class="player-breadcrumb">
        <a href="<?php echo esc_url( home_url('/cursos/') ); ?>">
            <i class="fas fa-graduation-cap"></i> <?php _e('Cursos', 'hubskills'); ?>
        </a>
        <i class="fas fa-chevron-right"></i>
        <span><?php the_title(); ?></span>
        <?php if ( $current_lesson ) : ?>
        <i class="fas fa-chevron-right"></i>
        <span><?php echo esc_html($current_lesson['title'] ?? ''); ?></span>
        <?php endif; ?>
    </div>

    <div class="player-layout">

        <!-- ─── Left: Video + Content ─── -->
        <div class="player-main">

            <?php if ( ! $is_enrolled ) : ?>
            <!-- Enroll Prompt -->
            <div class="enroll-prompt">
                <div class="ep-thumb" style="background: linear-gradient(135deg, <?php echo esc_attr($color_start); ?>, <?php echo esc_attr($color_end); ?>)">
                    <span class="ep-icon"><?php echo esc_html($icon); ?></span>
                </div>
                <div class="ep-content">
                    <h2><?php the_title(); ?></h2>
                    <p><?php echo esc_html($instructor); ?></p>
                    <div class="ep-meta">
                        <?php if ( $duration ) : ?><span><i class="fas fa-clock"></i> <?php echo esc_html($duration); ?></span><?php endif; ?>
                        <?php if ( $level ) : ?><span><i class="fas fa-signal"></i> <?php echo esc_html($level); ?></span><?php endif; ?>
                    </div>
                    <div class="ep-description"><?php the_content(); ?></div>
                    <?php if ( $is_paid ) : ?>
                    <button class="btn btn-gradient btn-lg payment-btn" data-course="<?php echo (int)$course_id; ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('hubskills_nonce')); ?>">
                        <i class="fas fa-credit-card me-2"></i><?php printf( esc_html__( 'Comprar Curso %s', 'hubskills' ), esc_html( $price ) ); ?>
                    </button>
                    <?php else : ?>
                    <button class="btn btn-gradient btn-lg enroll-btn" data-course="<?php echo (int)$course_id; ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('hubskills_nonce')); ?>">
                        <i class="fas fa-lock-open me-2"></i><?php _e('Matricular-se Gratuitamente', 'hubskills'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php else : ?>
            <!-- Video Player -->
            <div class="video-player-wrap">
                <?php
                $media_html = function_exists( 'hs_lms_render_lesson_media' ) && $current_lesson
                    ? hs_lms_render_lesson_media( $course_id, (array) $current_lesson )
                    : '';
                if ( $media_html ) :
                    echo $media_html;
                elseif ( $video_url ) :
                    // Fallback (plugin sem render de mídia): rotear arquivos locais pelo proxy protegido
                    $fallback_url = function_exists( 'hs_lms_protected_media_url' )
                        ? hs_lms_protected_media_url( $course_id, $video_url )
                        : $video_url;
                ?>
                <video id="courseVideo" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto" width="100%" height="480" data-setup='{}'>
                    <source src="<?php echo esc_url($fallback_url); ?>" type="video/mp4">
                    <p class="vjs-no-js"><?php _e('Para ver este vídeo, habilite JavaScript.', 'hubskills'); ?></p>
                </video>
                <?php else : ?>
                <div class="video-placeholder" style="background: linear-gradient(135deg, <?php echo esc_attr($color_start); ?>, <?php echo esc_attr($color_end); ?>)">
                    <span><?php echo esc_html($icon); ?></span>
                    <p><?php _e('Vídeo não disponível.', 'hubskills'); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Lesson Info -->
            <div class="lesson-info-bar">
                <div class="lib-title">
                    <h2><?php echo esc_html($current_lesson['title'] ?? get_the_title()); ?></h2>
                    <span class="lib-section"><?php echo esc_html($current_section['section'] ?? ''); ?></span>
                </div>
                <div class="lib-actions">
                    <?php $lesson_id = (int)($current_lesson['id'] ?? 0); ?>
                    <?php if ( ! $is_user_enrolled && $can_manage_course ) : ?>
                    <span class="btn btn-success-outline btn-sm"><i class="fas fa-eye me-1"></i><?php _e('Pré-visualização', 'hubskills'); ?></span>
                    <?php elseif ( in_array($lesson_id, $completed_l, true) ) : ?>
                    <span class="btn btn-success-outline btn-sm"><i class="fas fa-check-circle me-1"></i><?php _e('Concluída', 'hubskills'); ?></span>
                    <?php else : ?>
                    <button class="btn btn-gradient btn-sm complete-lesson-btn"
                        data-course="<?php echo (int)$course_id; ?>"
                        data-lesson="<?php echo (int)$lesson_id; ?>"
                        data-nonce="<?php echo esc_attr(wp_create_nonce('hubskills_nonce')); ?>">
                        <i class="fas fa-check me-1"></i><?php _e('Marcar como Concluída', 'hubskills'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabs -->
            <div class="lesson-tabs">
                <ul class="nav nav-tabs lesson-nav-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview"><?php _e('Visão Geral', 'hubskills'); ?></button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-notes"><?php _e('Anotações', 'hubskills'); ?></button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-resources"><?php _e('Recursos', 'hubskills'); ?></button></li>
                </ul>
                <div class="tab-content lesson-tab-content">
                    <div class="tab-pane fade show active" id="tab-overview">
                        <div class="tab-body">
                            <?php the_content(); ?>
                            <?php if ( empty(get_the_content()) ) : ?>
                            <p class="text-muted"><?php _e('Assista a aula e explore os recursos disponíveis.', 'hubskills'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-notes">
                        <div class="tab-body">
                            <textarea class="notes-textarea" id="lessonNotes" placeholder="<?php _e('Suas anotações...', 'hubskills'); ?>"></textarea>
                            <button class="btn btn-gradient btn-sm mt-2" id="saveNotes">
                                <i class="fas fa-save me-1"></i><?php _e('Salvar', 'hubskills'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-resources">
                        <div class="tab-body">
                            <?php
                            $resources = get_post_meta($course_id, '_hs_resources', true) ?: [];
                            if (empty($resources)) : ?>
                            <p class="text-muted"><?php _e('Nenhum recurso disponível para esta aula.', 'hubskills'); ?></p>
                            <?php else : foreach ($resources as $res) :
                                $resource_url = function_exists( 'hs_lms_get_resource_url' ) ? hs_lms_get_resource_url( $course_id, (array) $res ) : ( $res['url'] ?? '' );
                                if ( ! $resource_url ) continue;
                            ?>
                            <a href="<?php echo esc_url($resource_url); ?>" class="resource-link" target="_blank" rel="noopener">
                                <i class="fas fa-file-download"></i>
                                <span><?php echo esc_html($res['name']); ?></span>
                            </a>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div><!-- .player-main -->

        <!-- ─── Right: Curriculum ─── -->
        <div class="player-sidebar">
            <div class="curriculum-header">
                <h3><?php _e('Conteúdo do Curso', 'hubskills'); ?></h3>
                <?php if ( $is_enrolled ) : ?>
                <div class="curriculum-progress">
                    <div class="progress">
                        <div class="progress-bar" style="width: <?php echo (int)$progress; ?>%"></div>
                    </div>
                    <span><?php echo (int)$progress; ?>% <?php _e('concluído', 'hubskills'); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="curriculum-list" id="curriculum">
                <?php foreach ($curriculum as $si => $section) : ?>
                <div class="curriculum-section">
                    <button class="section-toggle <?php echo $si === 0 ? '' : 'collapsed'; ?>" data-bs-toggle="collapse" data-bs-target="#section-<?php echo (int)$si; ?>">
                        <span><?php echo esc_html($section['section']); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="section-lessons <?php echo $si === 0 ? 'show' : 'collapse'; ?>" id="section-<?php echo (int)$si; ?>">
                        <?php foreach ($section['lessons'] ?? [] as $lesson) :
                            $lid       = (int)$lesson['id'];
                            $is_done   = in_array($lid, $completed_l, true);
                            $is_active = isset($current_lesson['id']) && (int)$current_lesson['id'] === $lid;
                            $lesson_url = add_query_arg('lesson', $lid, get_permalink());
                            $type_icons = [ 'video' => 'play-circle', 'quiz' => 'tasks', 'project' => 'code' ];
                            $type_icon  = $type_icons[$lesson['type'] ?? 'video'] ?? 'play-circle';
                        ?>
                        <a href="<?php echo esc_url($lesson_url); ?>"
                            class="lesson-item <?php echo $is_active ? 'active' : ''; ?> <?php echo $is_done ? 'done' : ''; ?>"
                            <?php if (!$is_enrolled) echo 'onclick="event.preventDefault()"'; ?>>
                            <div class="li-icon">
                                <?php if ($is_done) : ?>
                                <i class="fas fa-check-circle text-success"></i>
                                <?php else : ?>
                                <i class="fas fa-<?php echo esc_attr($type_icon); ?>"></i>
                                <?php endif; ?>
                            </div>
                            <div class="li-info">
                                <span class="li-title"><?php echo esc_html($lesson['title']); ?></span>
                                <span class="li-duration"><?php echo esc_html($lesson['duration'] ?? ''); ?></span>
                            </div>
                            <?php if (!$is_enrolled) : ?>
                            <i class="fas fa-lock li-lock"></i>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if ( empty($curriculum) ) : ?>
                <p class="text-muted p-4"><?php _e('Conteúdo em breve.', 'hubskills'); ?></p>
                <?php endif; ?>
            </div>
        </div><!-- .player-sidebar -->

    </div><!-- .player-layout -->
</div><!-- .player-page -->

<?php get_footer(); ?>
