<?php
/**
 * Template Name: Instructor Register
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}

$user_id     = get_current_user_id();
$application = get_user_meta( $user_id, 'hs_instructor_application', true );
$app_status  = is_array( $application ) ? ( $application['status'] ?? '' ) : '';

get_header();
?>
<div class="hs-page-wrapper" style="min-height:100vh;padding:80px 0">
    <div class="container" style="max-width:720px;margin:0 auto;padding:0 24px">

        <?php if ( $app_status === 'approved' ) : ?>
        <div class="hs-card text-center" style="padding:48px">
            <div style="font-size:64px">🎓</div>
            <h2 style="color:var(--primary)"><?php _e( 'Você já é instrutor!', 'hubskills-lms' ); ?></h2>
            <p><?php _e( 'Acesse seu painel de instrutor para gerenciar seus cursos.', 'hubskills-lms' ); ?></p>
            <a href="<?php echo esc_url( home_url('/painel-instrutor/') ); ?>" class="btn btn-gradient mt-3"><?php _e( 'Ir ao Painel', 'hubskills-lms' ); ?></a>
        </div>

        <?php elseif ( $app_status === 'pending' ) : ?>
        <div class="hs-card text-center" style="padding:48px">
            <div style="font-size:64px">⏳</div>
            <h2 style="color:var(--secondary)"><?php _e( 'Candidatura em análise', 'hubskills-lms' ); ?></h2>
            <p><?php _e( 'Sua candidatura foi recebida e está sendo analisada pela equipe HubSkills. Você receberá um email com a resposta em breve.', 'hubskills-lms' ); ?></p>
            <p style="color:#999;font-size:14px"><?php _e( 'Enviado em:', 'hubskills-lms' ); ?> <?php echo esc_html( $application['submitted'] ?? '' ); ?></p>
        </div>

        <?php elseif ( $app_status === 'rejected' ) : ?>
        <div class="hs-card" style="padding:48px">
            <div class="text-center" style="font-size:64px">😔</div>
            <h2 class="text-center"><?php _e( 'Candidatura não aprovada', 'hubskills-lms' ); ?></h2>
            <?php if ( ! empty( $application['reject_reason'] ) ) : ?>
            <div style="background:rgba(239,68,68,.1);border:1px solid #ef4444;border-radius:8px;padding:16px;margin:16px 0">
                <strong><?php _e( 'Motivo:', 'hubskills-lms' ); ?></strong> <?php echo esc_html( $application['reject_reason'] ); ?>
            </div>
            <?php endif; ?>
            <p><?php _e( 'Você pode se candidatar novamente. Revise as informações e tente novamente.', 'hubskills-lms' ); ?></p>
            <button class="btn btn-gradient" id="hs-retry-btn" onclick="document.getElementById('hs-retry-btn').style.display='none';document.getElementById('hs-apply-form').style.display='block'"><?php _e( 'Candidatar-se Novamente', 'hubskills-lms' ); ?></button>
            <div id="hs-apply-form" style="display:none">
                <?php hs_render_instructor_apply_form(); ?>
            </div>
        </div>

        <?php else : ?>
        <div class="hs-card" style="padding:48px">
            <div class="text-center mb-4">
                <div style="font-size:56px">🎓</div>
                <h1 style="color:var(--primary);margin:8px 0"><?php _e( 'Seja um Instrutor', 'hubskills-lms' ); ?></h1>
                <p style="color:#888"><?php _e( 'Compartilhe seu conhecimento e monetize seu conteúdo na HubSkills.', 'hubskills-lms' ); ?></p>
            </div>

            <div class="hs-benefits-grid">
                <?php
                $benefits = [
                    [ '💰', __('70% de comissão','hubskills-lms'), __('Receba a maior parte de cada venda','hubskills-lms') ],
                    [ '📊', __('Painel Completo','hubskills-lms'), __('Analíticos, alunos e finanças','hubskills-lms') ],
                    [ '🌍', __('Alcance Global','hubskills-lms'), __('Ensine milhares de alunos','hubskills-lms') ],
                ];
                foreach ($benefits as $b) : ?>
                <div class="hs-benefit-mini">
                    <div style="font-size:32px"><?php echo esc_html($b[0]); ?></div>
                    <div style="font-weight:600;margin:8px 0"><?php echo esc_html($b[1]); ?></div>
                    <div style="font-size:13px;color:#888"><?php echo esc_html($b[2]); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php hs_render_instructor_apply_form(); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
<?php

function hs_render_instructor_apply_form() {
    $user = wp_get_current_user();
    $areas_options = [ 'Tecnologia', 'Design', 'Marketing', 'Negócios', 'Idiomas', 'Saúde', 'Educação', 'Arte', 'Culinária', 'Esportes', 'Desenvolvimento Pessoal', 'Outro' ];
    ?>
    <div id="hs-apply-success" style="display:none;text-align:center;padding:32px">
        <div style="font-size:56px">🎉</div>
        <h3 style="color:#10b981"><?php _e('Candidatura enviada!','hubskills-lms'); ?></h3>
        <p><?php _e('Nossa equipe analisará em até 3 dias úteis.','hubskills-lms'); ?></p>
    </div>

    <form id="hs-instructor-apply-form">
        <?php wp_nonce_field('hubskills_nonce','nonce'); ?>

        <div class="mb-3">
            <label class="hs-label"><?php _e('Nome completo','hubskills-lms'); ?> *</label>
            <input type="text" name="name" class="hs-input" value="<?php echo esc_attr($user->display_name); ?>" required>
        </div>

        <div class="mb-3">
            <label class="hs-label"><?php _e('Especialidade principal','hubskills-lms'); ?> *</label>
            <input type="text" name="expertise" class="hs-input" placeholder="<?php esc_attr_e('Ex: Desenvolvimento Web, Design UX...','hubskills-lms'); ?>" required>
        </div>

        <div class="mb-3">
            <label class="hs-label"><?php _e('Bio / Apresentação','hubskills-lms'); ?> *</label>
            <textarea name="bio" class="hs-textarea" rows="4" placeholder="<?php esc_attr_e('Conte sobre você, sua trajetória e por que deseja ensinar...','hubskills-lms'); ?>" required></textarea>
        </div>

        <div class="mb-3">
            <label class="hs-label"><?php _e('Experiência profissional','hubskills-lms'); ?> *</label>
            <textarea name="experience" class="hs-textarea" rows="3" placeholder="<?php esc_attr_e('Descreva sua experiência na área...','hubskills-lms'); ?>" required></textarea>
        </div>

        <div class="mb-3">
            <label class="hs-label"><?php _e('Áreas de ensino','hubskills-lms'); ?></label>
            <div class="hs-checkbox-grid">
                <?php foreach ($areas_options as $area) : ?>
                <label class="hs-checkbox-item">
                    <input type="checkbox" name="areas[]" value="<?php echo esc_attr($area); ?>">
                    <?php echo esc_html($area); ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="hs-form-grid-2">
            <div>
                <label class="hs-label">LinkedIn</label>
                <input type="url" name="linkedin" class="hs-input" placeholder="https://linkedin.com/in/...">
            </div>
            <div>
                <label class="hs-label">YouTube / Canal</label>
                <input type="url" name="youtube" class="hs-input" placeholder="https://youtube.com/@...">
            </div>
        </div>

        <div class="mb-3">
            <label class="hs-label"><?php _e('Site / Portfólio','hubskills-lms'); ?></label>
            <input type="url" name="website" class="hs-input" placeholder="https://...">
        </div>

        <div id="hs-apply-error" class="alert alert-danger" style="display:none"></div>

        <button type="submit" class="btn btn-gradient w-100">
            <i class="fas fa-paper-plane me-2"></i><?php _e('Enviar Candidatura','hubskills-lms'); ?>
        </button>
    </form>

    <script>
    document.getElementById('hs-instructor-apply-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = this.querySelector('[type=submit]');
        btn.disabled = true;
        btn.textContent = '<?php echo esc_js(__('Enviando...','hubskills-lms')); ?>';

        var data = new FormData(this);
        data.append('action', 'hs_apply_instructor');

        fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', { method:'POST', body: data })
            .then(function(r){ return r.json(); })
            .then(function(res) {
                if (res.success) {
                    document.getElementById('hs-instructor-apply-form').style.display = 'none';
                    document.getElementById('hs-apply-success').style.display = 'block';
                } else {
                    document.getElementById('hs-apply-error').style.display = 'block';
                    document.getElementById('hs-apply-error').textContent = res.data ? res.data.message : '<?php echo esc_js(__('Erro ao enviar.','hubskills-lms')); ?>';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i><?php echo esc_js(__('Enviar Candidatura','hubskills-lms')); ?>';
                }
            });
    });
    </script>
    <?php
}
