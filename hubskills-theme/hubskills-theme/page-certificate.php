<?php
/**
 * Template Name: Certificados
 */
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }
get_header();
$user_id  = get_current_user_id();
$user     = wp_get_current_user();
$certs    = (array)(get_user_meta($user_id, 'hs_certificates', true) ?: []);
$show_id  = (int)($_GET['course'] ?? 0);
$show_cert = $show_id && in_array($show_id, $certs, false);
?>

<div class="certificates-page" data-page="certificates">
    <div class="section-header mb-4" data-aos="fade-down">
        <h1><?php _e('Meus', 'hubskills'); ?> <span class="gradient-text"><?php _e('Certificados', 'hubskills'); ?></span></h1>
        <p><?php echo sprintf(__('%d certificado(s) emitido(s)', 'hubskills'), count($certs)); ?></p>
    </div>

    <?php if ( $show_cert ) :
        $course = get_post($show_id);
        $details = function_exists( 'hs_certificate_details' )
            ? hs_certificate_details( $user_id, $show_id )
            : [
                'template'         => 'modern',
                'student_name'     => $user->display_name,
                'student_email'    => $user->user_email,
                'student_document' => '',
                'course_title'     => get_the_title( $show_id ),
                'course_workload'  => get_post_meta( $show_id, '_hs_duration', true ),
                'completion_date'  => get_user_meta($user_id, 'hs_cert_date_'.$show_id, true) ?: current_time('d/m/Y'),
                'issue_date'       => current_time('d/m/Y'),
                'instructor_name'  => get_post_meta( $show_id, '_hs_instructor', true ),
                'issuer_name'      => get_bloginfo( 'name' ) ?: 'HubSkills',
                'issuer_title'     => __( 'Diretor Academico', 'hubskills' ),
                'location'         => '',
                'grade'            => '',
                'note'             => '',
                'cert_id'          => 'HS-' . strtoupper(substr(md5($user_id.$show_id), 0, 12)),
            ];
    ?>
    <!-- Certificate Display -->
    <div class="certificate-wrap" data-aos="zoom-in" id="certificate">
        <div class="certificate certificate--pro certificate--template-<?php echo esc_attr( $details['template'] ?? 'modern' ); ?>"
             data-certificate-export
             data-template="<?php echo esc_attr( $details['template'] ?? 'modern' ); ?>"
             data-student-name="<?php echo esc_attr( $details['student_name'] ); ?>"
             data-student-email="<?php echo esc_attr( $details['student_email'] ); ?>"
             data-student-document="<?php echo esc_attr( $details['student_document'] ); ?>"
             data-course-title="<?php echo esc_attr( $details['course_title'] ); ?>"
             data-course-workload="<?php echo esc_attr( $details['course_workload'] ); ?>"
             data-completion-date="<?php echo esc_attr( $details['completion_date'] ); ?>"
             data-issue-date="<?php echo esc_attr( $details['issue_date'] ); ?>"
             data-instructor-name="<?php echo esc_attr( $details['instructor_name'] ); ?>"
             data-issuer-name="<?php echo esc_attr( $details['issuer_name'] ); ?>"
             data-issuer-title="<?php echo esc_attr( $details['issuer_title'] ); ?>"
             data-location="<?php echo esc_attr( $details['location'] ); ?>"
             data-grade="<?php echo esc_attr( $details['grade'] ); ?>"
             data-note="<?php echo esc_attr( $details['note'] ); ?>"
             data-cert-id="<?php echo esc_attr( $details['cert_id'] ); ?>">
            <div class="cert-bg-top"></div>
            <div class="cert-bg-bottom"></div>
            <div class="cert-border"></div>
            <div class="cert-content">
                <div class="cert-header">
                    <div class="cert-brand">
                        <div class="cert-brand-icon">H</div>
                        <span class="cert-brand-name">Hub<span style="color:#06B6D4">Skills</span></span>
                    </div>
                    <div class="cert-badge"><i class="fas fa-medal"></i></div>
                </div>
                <div class="cert-body">
                    <p class="cert-subtitle"><?php _e('Certificamos que', 'hubskills'); ?></p>
                    <h2 class="cert-name"><?php echo esc_html( $details['student_name'] ); ?></h2>
                    <p class="cert-text"><?php _e('concluiu com êxito o curso', 'hubskills'); ?></p>
                    <h3 class="cert-course"><?php echo esc_html( $details['course_title'] ); ?></h3>
                    <div class="cert-meta-grid">
                        <span><strong><?php _e( 'Conclusao', 'hubskills' ); ?></strong><?php echo esc_html( $details['completion_date'] ); ?></span>
                        <span><strong><?php _e( 'Emissao', 'hubskills' ); ?></strong><?php echo esc_html( $details['issue_date'] ); ?></span>
                        <?php if ( $details['course_workload'] ) : ?><span><strong><?php _e( 'Carga horaria', 'hubskills' ); ?></strong><?php echo esc_html( $details['course_workload'] ); ?></span><?php endif; ?>
                        <?php if ( $details['grade'] ) : ?><span><strong><?php _e( 'Aproveitamento', 'hubskills' ); ?></strong><?php echo esc_html( $details['grade'] ); ?></span><?php endif; ?>
                        <?php if ( $details['student_document'] ) : ?><span><strong><?php _e( 'Documento', 'hubskills' ); ?></strong><?php echo esc_html( $details['student_document'] ); ?></span><?php endif; ?>
                        <?php if ( $details['location'] ) : ?><span><strong><?php _e( 'Local', 'hubskills' ); ?></strong><?php echo esc_html( $details['location'] ); ?></span><?php endif; ?>
                    </div>
                    <?php if ( $details['note'] ) : ?><p class="cert-note"><?php echo esc_html( $details['note'] ); ?></p><?php endif; ?>
                    <div class="cert-id">ID: <?php echo esc_html( $details['cert_id'] ); ?></div>
                </div>
                <div class="cert-footer">
                    <div class="cert-sig">
                        <div class="cert-sig-line"></div>
                        <strong><?php echo esc_html( $details['instructor_name'] ?: __( 'Instrutor do Curso', 'hubskills' ) ); ?></strong>
                        <span><?php _e('Instrutor do Curso', 'hubskills'); ?></span>
                    </div>
                    <div class="cert-seal">
                        <i class="fas fa-award"></i>
                    </div>
                    <div class="cert-sig">
                        <div class="cert-sig-line"></div>
                        <strong><?php echo esc_html( $details['issuer_name'] ?: 'HubSkills' ); ?></strong>
                        <span><?php echo esc_html( $details['issuer_title'] ?: __( 'Diretor Academico', 'hubskills' ) ); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="cert-actions text-center mt-4" data-aos="fade-up">
        <button type="button" data-certificate-print data-cert-target="[data-certificate-export]" class="btn btn-gradient me-2">
            <i class="fas fa-print me-2"></i><?php _e('Imprimir / Salvar PDF', 'hubskills'); ?>
        </button>
        <button type="button" data-certificate-download data-cert-target="[data-certificate-export]" class="btn btn-primary me-2">
            <i class="fas fa-image me-2"></i><?php _e('Baixar imagem', 'hubskills'); ?>
        </button>
        <a href="<?php echo esc_url(get_permalink()); ?>" class="btn btn-ghost">
            <i class="fas fa-arrow-left me-2"></i><?php _e('Voltar', 'hubskills'); ?>
        </a>
    </div>

    <?php elseif ( empty($certs) ) : ?>
    <div class="empty-state py-5 text-center">
        <div class="empty-icon"><i class="fas fa-certificate"></i></div>
        <h3><?php _e('Nenhum certificado ainda', 'hubskills'); ?></h3>
        <p><?php _e('Conclua um curso para ganhar seu primeiro certificado.', 'hubskills'); ?></p>
        <a href="<?php echo esc_url(home_url('/cursos/')); ?>" class="btn btn-gradient"><?php _e('Ver Cursos', 'hubskills'); ?></a>
    </div>

    <?php else : ?>
    <div class="row g-4">
        <?php
        $cert_posts = get_posts(['post_type'=>'hubskills_course','post__in'=>$certs,'numberposts'=>-1]);
        foreach ($cert_posts as $cp) :
            $cert_url = add_query_arg('course', $cp->ID, get_permalink());
            $cs = get_post_meta($cp->ID,'_hs_color_start',true) ?: '#7C3AED';
            $ce = get_post_meta($cp->ID,'_hs_color_end',true) ?: '#06B6D4';
            $icon = get_post_meta($cp->ID,'_hs_icon',true) ?: '📚';
            $date = get_user_meta($user_id,'hs_cert_date_'.$cp->ID,true) ?: '';
        ?>
        <div class="col-md-6 col-lg-4" data-aos="fade-up">
            <div class="cert-card">
                <div class="cert-card-thumb" style="background: linear-gradient(135deg, <?php echo esc_attr($cs); ?>, <?php echo esc_attr($ce); ?>)">
                    <span class="cert-card-icon"><?php echo esc_html($icon); ?></span>
                    <div class="cert-card-badge"><i class="fas fa-certificate"></i></div>
                </div>
                <div class="cert-card-info">
                    <h4><?php echo esc_html(get_the_title($cp)); ?></h4>
                    <?php if ($date) : ?><p><?php printf(__('Concluído em %s', 'hubskills'), esc_html($date)); ?></p><?php endif; ?>
                    <a href="<?php echo esc_url($cert_url); ?>" class="btn btn-gradient btn-sm w-100 mt-2">
                        <i class="fas fa-eye me-1"></i><?php _e('Ver Certificado', 'hubskills'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
