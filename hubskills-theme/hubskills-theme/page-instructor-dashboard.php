<?php
/**
 * Template Name: Instructor Dashboard
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$can_access_instructor_dashboard = false;
if ( is_user_logged_in() ) {
    $current_user_id = get_current_user_id();
    $can_access_instructor_dashboard =
        ( function_exists( 'hs_is_instructor' ) && hs_is_instructor( $current_user_id ) )
        || ( function_exists( 'hs_is_hs_admin' ) && hs_is_hs_admin( $current_user_id ) );
}

if ( ! $can_access_instructor_dashboard ) {
    wp_redirect( home_url('/') );
    exit;
}

$user_id  = get_current_user_id();
$user     = wp_get_current_user();
$stats    = hs_get_instructor_stats( $user_id );
$profile  = hs_get_instructor_profile( $user_id );
$courses  = hs_get_instructor_courses( $user_id );
$student_enrollments = function_exists( 'hs_get_user_enrollments' ) ? hs_get_user_enrollments( $user_id ) : ( get_user_meta( $user_id, 'hs_enrollments', true ) ?: [] );
$student_course_ids  = array_values( array_unique( array_filter( array_map( 'intval', array_keys( (array) $student_enrollments ) ) ) ) );
$student_courses     = $student_course_ids ? get_posts( [
    'post_type'      => 'hubskills_course',
    'post_status'    => 'publish',
    'post__in'       => $student_course_ids,
    'orderby'        => 'post__in',
    'posts_per_page' => -1,
] ) : [];
$tab      = sanitize_key( $_GET['tab'] ?? 'overview' );

get_header();
?>

            <!-- Page Header -->
            <div style="margin-bottom:32px">
                <h1 style="font-size:28px;font-weight:700;color:var(--text)"><?php _e('Painel do Instrutor','hubskills-lms'); ?></h1>
                <p style="color:var(--text-muted)"><?php printf(__('Olá, %s! Aqui está um resumo da sua atividade.','hubskills-lms'), esc_html($user->display_name)); ?></p>
            </div>

            <!-- Stats Cards -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:32px">
                <?php
                $stat_items = [
                    ['icon'=>'fas fa-book','value'=>$stats['courses'],'label'=>__('Cursos Criados','hubskills-lms'),'color'=>'#7C3AED'],
                    ['icon'=>'fas fa-users','value'=>$stats['students'],'label'=>__('Total de Alunos','hubskills-lms'),'color'=>'#06B6D4'],
                    ['icon'=>'fas fa-star','value'=>number_format($stats['rating'],1),'label'=>__('Avaliação Média','hubskills-lms'),'color'=>'#F59E0B'],
                    ['icon'=>'fas fa-dollar-sign','value'=>'R$ '.number_format($stats['earnings'],2,',','.'),'label'=>__('Ganhos Totais','hubskills-lms'),'color'=>'#10B981'],
                ];
                foreach ($stat_items as $si) : ?>
                <div class="glass-card" style="border-left:4px solid <?php echo esc_attr($si['color']); ?>">
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <div>
                            <div style="font-size:24px;font-weight:700;color:<?php echo esc_attr($si['color']); ?>"><?php echo esc_html($si['value']); ?></div>
                            <div style="color:var(--text-muted);font-size:14px"><?php echo esc_html($si['label']); ?></div>
                        </div>
                        <i class="<?php echo esc_attr($si['icon']); ?>" style="font-size:28px;color:<?php echo esc_attr($si['color']); ?>;opacity:.4"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Tabs -->
            <div style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid rgba(255,255,255,.1);padding-bottom:0">
                <?php
                $tabs = [
                    'overview'  => __('Visão Geral','hubskills-lms'),
                    'courses'   => __('Meus Cursos','hubskills-lms'),
                    'certificates' => __('Certificados','hubskills-lms'),
                    'profile'   => __('Meu Perfil','hubskills-lms'),
                    'earnings'  => __('Ganhos','hubskills-lms'),
                ];
                foreach ($tabs as $slug => $label) :
                    $active = ($tab === $slug) ? 'border-bottom:2px solid var(--primary);color:var(--primary);' : 'color:var(--text-muted);';
                ?>
                <a href="<?php echo esc_url(add_query_arg('tab',$slug)); ?>"
                   style="padding:10px 20px;font-weight:600;text-decoration:none;<?php echo esc_attr($active); ?>">
                    <?php echo esc_html($label); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Tab Content -->
            <?php if ($tab === 'overview') : ?>
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
                <div class="glass-card">
                    <h3><?php _e('Seus Cursos','hubskills-lms'); ?></h3>
                    <?php if ($courses) : ?>
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="border-bottom:1px solid rgba(255,255,255,.1)">
                                <th style="text-align:left;padding:8px;color:var(--text-muted)"><?php _e('Curso','hubskills-lms'); ?></th>
                                <th style="text-align:center;padding:8px;color:var(--text-muted)"><?php _e('Status','hubskills-lms'); ?></th>
                                <th style="text-align:right;padding:8px;color:var(--text-muted)"><?php _e('Alunos','hubskills-lms'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($courses,0,5) as $c) :
                            $students = function_exists( 'hs_lms_course_student_count' )
                                ? hs_lms_course_student_count( $c->ID )
                                : max( (int)get_post_meta($c->ID,'_hs_students',true), (int)get_post_meta($c->ID,'_hs_student_count',true) );
                            $smap = ['publish'=>'✅ '.__('Publicado','hubskills-lms'),'draft'=>'📝 '.__('Rascunho','hubskills-lms'),'pending'=>'⏳ '.__('Revisão','hubskills-lms')];
                            ?>
                            <tr style="border-bottom:1px solid rgba(255,255,255,.05)">
                                <td style="padding:10px 8px">
                                    <a href="<?php echo esc_url( home_url('/dashboard/') ); ?>" style="color:var(--text);text-decoration:none;font-weight:500">
                                        <?php echo esc_html($c->post_title); ?>
                                    </a>
                                </td>
                                <td style="padding:10px 8px;text-align:center;font-size:13px"><?php echo esc_html($smap[$c->post_status] ?? $c->post_status); ?></td>
                                <td style="padding:10px 8px;text-align:right"><?php echo (int) $students; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else : ?>
                    <div style="text-align:center;padding:32px;color:var(--text-muted)">
                        <p><?php _e('Você ainda não criou cursos.','hubskills-lms'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="glass-card">
                    <h3><?php _e('Perfil Público','hubskills-lms'); ?></h3>
                    <div style="text-align:center">
                        <img src="<?php echo esc_url(get_avatar_url($user_id,['size'=>80])); ?>" style="width:80px;height:80px;border-radius:50%;margin-bottom:12px">
                        <div style="font-weight:700"><?php echo esc_html($user->display_name); ?></div>
                        <div style="color:var(--text-muted);font-size:14px"><?php echo esc_html($profile['expertise'] ?? ''); ?></div>
                    </div>
                    <hr style="border-color:rgba(255,255,255,.1);margin:16px 0">
                    <div style="font-size:14px;color:var(--text-muted)">
                        <?php if ($profile['linkedin']) : ?><div><i class="fab fa-linkedin"></i> <a href="<?php echo esc_url($profile['linkedin']); ?>" target="_blank" style="color:var(--secondary)">LinkedIn</a></div><?php endif; ?>
                        <?php if ($profile['youtube']) : ?><div><i class="fab fa-youtube"></i> <a href="<?php echo esc_url($profile['youtube']); ?>" target="_blank" style="color:#ff0000">YouTube</a></div><?php endif; ?>
                        <?php if ($profile['website']) : ?><div><i class="fas fa-globe"></i> <a href="<?php echo esc_url($profile['website']); ?>" target="_blank" style="color:var(--accent)">Website</a></div><?php endif; ?>
                    </div>
                    <a href="<?php echo esc_url(add_query_arg('tab','profile')); ?>" class="btn btn-outline-light w-100 mt-3" style="font-size:13px"><?php _e('Editar Perfil','hubskills-lms'); ?></a>
                </div>
            </div>

            <?php elseif ($tab === 'courses') : ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                <h3><?php _e('Meus Cursos','hubskills-lms'); ?></h3>
                <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="btn btn-gradient btn-sm">
                    <i class="fas fa-plus me-1"></i><?php _e('Novo Curso','hubskills-lms'); ?>
                </a>
            </div>
            <?php if ($courses) : ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px">
                <?php foreach ($courses as $c) :
                    $price    = get_post_meta($c->ID,'_hs_price',true) ?: __('Grátis','hubskills-lms');
                    $students = function_exists( 'hs_lms_course_student_count' )
                        ? hs_lms_course_student_count( $c->ID )
                        : max( (int)get_post_meta($c->ID,'_hs_students',true), (int)get_post_meta($c->ID,'_hs_student_count',true) );
                    $is_enrolled = function_exists( 'hs_is_enrolled' ) && hs_is_enrolled( $user_id, $c->ID );
                    $status   = $c->post_status;
                    $scolor   = ['publish'=>'#10b981','draft'=>'#f59e0b','pending'=>'#06b6d4'][$status] ?? '#999';
                    ?>
                    <div class="glass-card">
                        <?php if (has_post_thumbnail($c->ID)) : ?>
                        <img src="<?php echo esc_url(get_the_post_thumbnail_url($c->ID,'medium')); ?>" style="width:100%;height:140px;object-fit:cover;border-radius:8px;margin-bottom:12px">
                        <?php endif; ?>
                        <div style="display:flex;justify-content:space-between;align-items:flex-start">
                            <h4 style="margin:0;font-size:16px"><?php echo esc_html($c->post_title); ?></h4>
                            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;margin-left:8px">
                                <span style="font-size:12px;color:<?php echo esc_attr($scolor); ?>;white-space:nowrap">● <?php echo esc_html(ucfirst($status)); ?></span>
                                <?php if ( $is_enrolled ) : ?>
                                <span style="font-size:11px;color:#10b981;font-weight:700;white-space:nowrap"><i class="fas fa-check-circle me-1"></i><?php _e('Matriculado','hubskills-lms'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-top:12px;font-size:13px;color:var(--text-muted)">
                            <span><i class="fas fa-users me-1"></i><?php echo (int) $students; ?> <?php _e('alunos','hubskills-lms'); ?></span>
                            <span style="color:var(--primary);font-weight:600"><?php echo esc_html($price); ?></span>
                        </div>
                        <div style="display:flex;gap:8px;margin-top:12px">
                            <a href="<?php echo esc_url( home_url('/dashboard/') ); ?>" class="btn btn-sm" style="flex:1;text-align:center;font-size:12px;padding:6px"><?php _e('Editar','hubskills-lms'); ?></a>
                            <a href="<?php echo esc_url(get_permalink($c->ID)); ?>" class="btn btn-sm" style="flex:1;text-align:center;font-size:12px;padding:6px" target="_blank"><?php echo $is_enrolled ? esc_html__( 'Estudar', 'hubskills-lms' ) : esc_html__( 'Ver', 'hubskills-lms' ); ?></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <div class="glass-card text-center" style="padding:48px">
                <div style="font-size:56px">📚</div>
                <h3><?php _e('Nenhum curso ainda','hubskills-lms'); ?></h3>
                <p style="color:var(--text-muted)"><?php _e('Crie seu primeiro curso e comece a ensinar!','hubskills-lms'); ?></p>
                <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="btn btn-gradient"><?php _e('Criar Primeiro Curso','hubskills-lms'); ?></a>
            </div>
            <?php endif; ?>

            <div style="display:flex;justify-content:space-between;align-items:center;margin:32px 0 16px">
                <h3><?php _e('Cursos em que estou matriculado','hubskills-lms'); ?></h3>
                <a href="<?php echo esc_url(home_url('/cursos/')); ?>" class="btn btn-sm" style="font-size:12px;padding:6px 10px"><?php _e('Ver catálogo','hubskills-lms'); ?></a>
            </div>
            <?php if ($student_courses) : ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px">
                <?php foreach ($student_courses as $student_course) :
                    $enroll_data = function_exists( 'hs_get_enrollment' ) ? hs_get_enrollment( $user_id, $student_course->ID ) : ( $student_enrollments[$student_course->ID] ?? [] );
                    $progress = (int)($enroll_data['progress'] ?? 0);
                    $status = $enroll_data['status'] ?? 'in_progress';
                    $icon = get_post_meta($student_course->ID,'_hs_icon',true) ?: '📚';
                    $cs = get_post_meta($student_course->ID,'_hs_color_start',true) ?: '#7C3AED';
                    $ce = get_post_meta($student_course->ID,'_hs_color_end',true) ?: '#06B6D4';
                ?>
                <div class="glass-card">
                    <div style="height:110px;border-radius:8px;background:linear-gradient(135deg,<?php echo esc_attr($cs); ?>,<?php echo esc_attr($ce); ?>);display:flex;align-items:center;justify-content:center;font-size:42px;margin-bottom:12px"><?php echo esc_html($icon); ?></div>
                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                        <h4 style="margin:0;font-size:16px"><?php echo esc_html($student_course->post_title); ?></h4>
                        <span style="font-size:11px;color:#10b981;font-weight:700;white-space:nowrap"><i class="fas fa-check-circle me-1"></i><?php _e('Matriculado','hubskills-lms'); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin:12px 0 8px;font-size:13px">
                        <span style="color:var(--text-muted)"><?php echo $status === 'completed' ? esc_html__('Concluído','hubskills-lms') : esc_html__('Em progresso','hubskills-lms'); ?></span>
                        <span style="color:var(--primary);font-weight:700"><?php echo (int)$progress; ?>%</span>
                    </div>
                    <div style="background:rgba(255,255,255,.1);border-radius:20px;height:6px;overflow:hidden;margin-bottom:12px">
                        <div style="width:<?php echo (int)$progress; ?>%;height:100%;background:var(--gradient-primary);border-radius:20px"></div>
                    </div>
                    <a href="<?php echo esc_url(get_permalink($student_course)); ?>" class="btn btn-sm btn-gradient w-100" style="font-size:13px"><?php echo $status === 'completed' ? esc_html__('Ver novamente','hubskills-lms') : esc_html__('Continuar','hubskills-lms'); ?></a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <div class="glass-card text-center" style="padding:28px;color:var(--text-muted)">
                <p style="margin-bottom:12px"><?php _e('Nenhuma matrícula de aluno encontrada para sua conta.','hubskills-lms'); ?></p>
                <a href="<?php echo esc_url(home_url('/cursos/')); ?>" class="btn btn-gradient btn-sm"><?php _e('Explorar cursos','hubskills-lms'); ?></a>
            </div>
            <?php endif; ?>

            <?php elseif ($tab === 'profile') : ?>
            <div class="glass-card" style="max-width:600px">
                <h3><?php _e('Editar Perfil de Instrutor','hubskills-lms'); ?></h3>
                <div id="hs-profile-msg" class="alert" style="display:none"></div>
                <form id="hs-instructor-profile-form">
                    <?php wp_nonce_field('hubskills_nonce','nonce'); ?>
                    <div class="mb-3">
                        <label class="hs-label"><?php _e('Tagline','hubskills-lms'); ?></label>
                        <input type="text" name="tagline" class="hs-input" value="<?php echo esc_attr($profile['tagline']??''); ?>" placeholder="<?php esc_attr_e('Ex: Especialista em React e Node.js','hubskills-lms'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="hs-label"><?php _e('Especialidade','hubskills-lms'); ?></label>
                        <input type="text" name="expertise" class="hs-input" value="<?php echo esc_attr($profile['expertise']??''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="hs-label"><?php _e('Bio','hubskills-lms'); ?></label>
                        <textarea name="bio" class="hs-textarea" rows="5"><?php echo esc_textarea($profile['bio']??''); ?></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        <div>
                            <label class="hs-label">LinkedIn</label>
                            <input type="url" name="linkedin" class="hs-input" value="<?php echo esc_attr($profile['linkedin']??''); ?>">
                        </div>
                        <div>
                            <label class="hs-label">YouTube</label>
                            <input type="url" name="youtube" class="hs-input" value="<?php echo esc_attr($profile['youtube']??''); ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="hs-label"><?php _e('Website','hubskills-lms'); ?></label>
                        <input type="url" name="website" class="hs-input" value="<?php echo esc_attr($profile['website']??''); ?>">
                    </div>
                    <button type="submit" class="btn btn-gradient mt-4">
                        <i class="fas fa-save me-2"></i><?php _e('Salvar Perfil','hubskills-lms'); ?>
                    </button>
                </form>
            </div>

            <?php elseif ($tab === 'certificates') : ?>
            <?php if ( function_exists( 'hs_lms_render_dashboard_tools' ) ) : ?>
                <?php echo hs_lms_render_dashboard_tools(); ?>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var certTab = document.querySelector('[data-hs-dashboard-tools] [data-hs-tab="certificates"]');
                    if (certTab) certTab.click();
                });
                </script>
            <?php else : ?>
                <div class="glass-card">
                    <h3><?php _e('Criador de certificados','hubskills-lms'); ?></h3>
                    <p style="color:var(--text-muted)"><?php _e('O modulo de certificados nao esta disponivel no momento.','hubskills-lms'); ?></p>
                </div>
            <?php endif; ?>

            <?php elseif ($tab === 'earnings') : ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
                <div class="glass-card">
                    <h4 style="color:var(--text-muted);margin:0 0 8px"><?php _e('Ganhos Totais','hubskills-lms'); ?></h4>
                    <div style="font-size:36px;font-weight:700;color:#10b981">R$ <?php echo number_format($stats['earnings'],2,',','.'); ?></div>
                    <div style="font-size:13px;color:var(--text-muted);margin-top:4px"><?php echo (int)get_user_meta($user_id,'hs_instructor_commission_rate',true)?:70; ?>% de comissão</div>
                </div>
                <div class="glass-card">
                    <h4 style="color:var(--text-muted);margin:0 0 8px"><?php _e('Vendas (Pedidos Pagos)','hubskills-lms'); ?></h4>
                    <?php
                    global $wpdb;
                    $sales = (int)$wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm
                         INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
                         WHERE pm.meta_key = '_hs_order_course_id'
                           AND pm.meta_value IN (" . implode(',',array_map('intval', wp_list_pluck($courses,'ID'))) . ")
                           AND pm2.meta_key = '_hs_order_status' AND pm2.meta_value = 'paid'",
                        ''
                    ));
                    ?>
                    <div style="font-size:36px;font-weight:700;color:#7C3AED"><?php echo (int) ( $sales ?: 0 ); ?></div>
                    <div style="font-size:13px;color:var(--text-muted);margin-top:4px"><?php _e('vendas realizadas','hubskills-lms'); ?></div>
                </div>
            </div>
            <div class="glass-card">
                <h3><?php _e('Pedidos Recentes','hubskills-lms'); ?></h3>
                <?php if (!empty($courses)) :
                    $course_ids = implode(',', array_map('intval', wp_list_pluck($courses,'ID')));
                    $orders = get_posts(['post_type'=>'hs_order','posts_per_page'=>10,'post_status'=>'publish',
                        'meta_query'=>[['key'=>'_hs_order_course_id','value'=>array_map('intval',wp_list_pluck($courses,'ID')),'compare'=>'IN']]
                    ]);
                    if ($orders) :
                        echo '<table style="width:100%;border-collapse:collapse">';
                        echo '<thead><tr style="border-bottom:1px solid rgba(255,255,255,.1)">';
                        foreach ([__('Aluno'), __('Curso'), __('Valor'), __('Status'), __('Data')] as $h) {
                            echo '<th style="padding:8px;text-align:left;color:var(--text-muted);font-size:13px">' . esc_html($h) . '</th>';
                        }
                        echo '</tr></thead><tbody>';
                        foreach ($orders as $o) {
                            $uid = (int)get_post_meta($o->ID,'_hs_order_user_id',true);
                            $cid = (int)get_post_meta($o->ID,'_hs_order_course_id',true);
                            $amt = (float)get_post_meta($o->ID,'_hs_order_amount',true);
                            $ost = get_post_meta($o->ID,'_hs_order_status',true);
                            $dat = get_post_meta($o->ID,'_hs_order_created_at',true);
                            $usr = get_userdata($uid);
                            $commission_rate = (int)(get_user_meta($user_id,'hs_instructor_commission_rate',true)?:70);
                            $my_cut = $amt * ($commission_rate/100);
                            $colors = ['paid'=>'#10b981','pending'=>'#f59e0b','refunded'=>'#06b6d4','failed'=>'#ef4444'];
                            $sc = $colors[$ost] ?? '#999';
                            echo '<tr style="border-bottom:1px solid rgba(255,255,255,.05)">';
                            echo '<td style="padding:8px">'.esc_html($usr?$usr->display_name:'#'.$uid).'</td>';
                            echo '<td style="padding:8px">'.esc_html(get_the_title($cid)).'</td>';
                            echo '<td style="padding:8px;color:#10b981;font-weight:600">R$ '.number_format($my_cut,2,',','.').'</td>';
                            echo '<td style="padding:8px"><span style="color:'.$sc.'">● '.esc_html(ucfirst($ost)).'</span></td>';
                            echo '<td style="padding:8px;font-size:12px;color:var(--text-muted)">'.esc_html($dat).'</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                    else :
                        echo '<p style="color:var(--text-muted)">'.__('Nenhum pedido ainda.','hubskills-lms').'</p>';
                    endif;
                endif;
                ?>
            </div>
            <?php endif; ?>

<script>
var form = document.getElementById('hs-instructor-profile-form');
if (form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var msg = document.getElementById('hs-profile-msg');
        var data = new FormData(this);
        data.append('action', 'hs_update_instructor_profile');
        fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {method:'POST',body:data})
            .then(function(r){return r.json();})
            .then(function(res) {
                msg.style.display = 'block';
                msg.className = res.success ? 'alert alert-success' : 'alert alert-danger';
                msg.textContent = res.data ? res.data.message : '<?php echo esc_js(__('Erro.','hubskills-lms')); ?>';
            });
    });
}
</script>
<?php get_footer(); ?>
