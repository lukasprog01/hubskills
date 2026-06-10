<?php
/**
 * Template Name: Student Profile Enhanced
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}

$user_id  = get_current_user_id();
$user     = wp_get_current_user();
if ( function_exists( 'hs_sync_user_paid_access_enrollments' ) ) {
    hs_sync_user_paid_access_enrollments( $user_id );
}
$enrollments = function_exists( 'hs_get_user_enrollments' ) ? hs_get_user_enrollments( $user_id ) : [];
$stats    = hs_get_user_stats( $user_id );
$xp_data  = hs_get_xp_to_next_level( $stats['xp'] );
$tab      = sanitize_key( $_GET['tab'] ?? 'overview' );

get_header();
?>

            <!-- Profile Hero -->
            <div class="glass-card" style="padding:32px;margin-bottom:32px;background:linear-gradient(135deg,rgba(124,58,237,.2),rgba(6,182,212,.1))">
                <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
                    <div style="position:relative">
                        <img src="<?php echo esc_url(get_avatar_url($user_id,['size'=>90])); ?>"
                             style="width:90px;height:90px;border-radius:50%;border:3px solid var(--primary)">
                        <div style="position:absolute;bottom:0;right:0;background:var(--primary);color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700"><?php echo (int) $stats['level']; ?></div>
                    </div>
                    <div style="flex:1">
                        <h2 style="margin:0;font-weight:700"><?php echo esc_html($user->display_name); ?></h2>
                        <div style="color:var(--text-muted);font-size:14px;margin-bottom:12px"><?php echo esc_html($user->user_email); ?></div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span style="font-size:13px;color:var(--text-muted)"><?php _e('Nível','hubskills-lms'); ?> <?php echo (int) $stats['level']; ?></span>
                            <div style="flex:1;max-width:200px;background:rgba(255,255,255,.1);border-radius:20px;height:8px;overflow:hidden">
                                <div style="width:<?php echo (int) $xp_data['percent']; ?>%;height:100%;background:var(--gradient-primary);border-radius:20px;transition:.6s"></div>
                            </div>
                            <span style="font-size:13px;color:var(--primary);font-weight:600"><?php echo (int) $stats['xp']; ?> XP</span>
                        </div>
                    </div>
                    <div style="display:flex;gap:16px;flex-wrap:wrap">
                        <?php
                        $quick_stats = [
                            ['🔥', $stats['streak'], __('Dias','hubskills-lms')],
                            ['📚', $stats['enrolled'], __('Cursos','hubskills-lms')],
                            ['🏆', $stats['badges'], __('Badges','hubskills-lms')],
                            ['🎓', $stats['certificates'], __('Certs.','hubskills-lms')],
                        ];
                        foreach ($quick_stats as $qs) : ?>
                        <div style="text-align:center;padding:12px 16px;background:rgba(255,255,255,.05);border-radius:12px">
                            <div style="font-size:20px"><?php echo esc_html($qs[0]); ?></div>
                            <div style="font-size:20px;font-weight:700"><?php echo esc_html($qs[1]); ?></div>
                            <div style="font-size:11px;color:var(--text-muted)"><?php echo esc_html($qs[2]); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div style="display:flex;gap:0;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:24px;overflow-x:auto">
                <?php
                $tabs = [
                    'overview'     => ['📊', __('Visão Geral','hubskills-lms')],
                    'courses'      => ['📚', __('Meus Cursos','hubskills-lms')],
                    'badges'       => ['🏆', __('Badges','hubskills-lms')],
                    'certificates' => ['🎓', __('Certificados','hubskills-lms')],
                    'activity'     => ['📋', __('Atividade','hubskills-lms')],
                    'settings'     => ['⚙️', __('Configurações','hubskills-lms')],
                ];
                foreach ($tabs as $slug => $td) :
                    $active = ($tab === $slug);
                    $style  = $active ? 'border-bottom:2px solid var(--primary);color:var(--primary);' : 'color:var(--text-muted);';
                ?>
                <a href="<?php echo esc_url(add_query_arg('tab',$slug)); ?>"
                   style="padding:12px 20px;text-decoration:none;white-space:nowrap;font-size:14px;font-weight:500;<?php echo esc_attr($style); ?>">
                    <?php echo esc_html($td[0].' '.$td[1]); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Tab: Overview -->
            <?php if ($tab === 'overview') :
                $activity    = (array)(get_user_meta($user_id,'hs_activity',true)?:[]);
                $weekly      = (array)(get_user_meta($user_id,'hs_weekly_minutes',true)?:array_fill(0,7,0));
            ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
                <div class="glass-card">
                    <h4><?php _e('Cursos Em Progresso','hubskills-lms'); ?></h4>
                    <?php
                    $in_progress = array_filter($enrollments,function($d){ return is_array($d) && ($d['status']??'')!=='completed'; });
                    if ($in_progress) :
                        foreach (array_slice($in_progress,0,5,true) as $cid => $data) :
                            $progress = (int)($data['progress']??0);
                            $course   = get_post((int)$cid);
                            if (!$course || $course->post_type !== 'hubskills_course') continue;
                            $title    = get_the_title($course);
                            echo '<div style="margin-bottom:16px">';
                            echo '<div style="display:flex;justify-content:space-between;margin-bottom:6px;gap:12px"><a href="'.esc_url(get_permalink($course)).'" style="font-weight:500;color:var(--text);text-decoration:none">'.esc_html($title).'</a><span style="color:var(--primary);font-weight:700">'.$progress.'%</span></div>';
                            echo '<div style="background:rgba(255,255,255,.1);border-radius:20px;height:6px;overflow:hidden"><div style="width:'.$progress.'%;height:100%;background:var(--gradient-primary);border-radius:20px"></div></div>';
                            echo '</div>';
                        endforeach;
                    else :
                        echo '<p style="color:var(--text-muted)">'.__('Nenhum curso em progresso.','hubskills-lms').'</p>';
                    endif;
                    ?>
                </div>
                <div class="glass-card">
                    <h4><?php _e('Atividade Recente','hubskills-lms'); ?></h4>
                    <?php if ($activity) : ?>
                    <div style="max-height:280px;overflow-y:auto">
                        <?php foreach (array_slice($activity,0,8) as $act) : ?>
                        <div style="display:flex;gap:12px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">
                            <div style="width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr($act['color']); ?>;margin-top:5px;flex-shrink:0"></div>
                            <div>
                                <div style="font-size:13px" class="activity-text"><?php echo wp_kses_post($act['text']); ?></div>
                                <div style="font-size:11px;color:var(--text-muted)"><?php echo esc_html($act['time']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else : ?>
                    <p style="color:var(--text-muted)"><?php _e('Nenhuma atividade ainda.','hubskills-lms'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab: Courses -->
            <?php elseif ($tab === 'courses') :
                $filter = sanitize_key($_GET['filter']??'all');
            ?>
            <div style="display:flex;gap:8px;margin-bottom:20px">
                <?php foreach (['all'=>__('Todos','hubskills-lms'),'in_progress'=>__('Em Progresso','hubskills-lms'),'completed'=>__('Concluídos','hubskills-lms')] as $f=>$fl) : ?>
                <a href="<?php echo esc_url(add_query_arg(['tab'=>'courses','filter'=>$f])); ?>"
                   class="btn btn-sm <?php echo $filter===$f?'btn-gradient':''; ?>" style="<?php echo $filter!==$f?'background:rgba(255,255,255,.05);':''; ?>">
                    <?php echo esc_html($fl); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if ($enrollments) : ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px">
                <?php foreach ($enrollments as $cid => $data) :
                    if ( ! is_array( $data ) ) continue;
                    $status   = $data['status'] ?? 'in_progress';
                    $progress = (int)($data['progress']??0);
                    if ($filter === 'completed' && $status !== 'completed') continue;
                    if ($filter === 'in_progress' && $status === 'completed') continue;
                    $course   = get_post((int)$cid);
                    if (!$course || $course->post_type !== 'hubskills_course') continue;
                    ?>
                    <div class="glass-card">
                        <?php if (has_post_thumbnail($course)) : ?>
                        <a href="<?php echo esc_url(get_permalink($course)); ?>">
                            <img src="<?php echo esc_url(get_the_post_thumbnail_url($course,'medium')); ?>" style="width:100%;height:130px;object-fit:cover;border-radius:8px;margin-bottom:12px">
                        </a>
                        <?php else :
                            $icon = get_post_meta($course->ID,'_hs_icon',true) ?: '📚';
                            $cs = get_post_meta($course->ID,'_hs_color_start',true) ?: '#7C3AED';
                            $ce = get_post_meta($course->ID,'_hs_color_end',true) ?: '#06B6D4';
                        ?>
                        <a href="<?php echo esc_url(get_permalink($course)); ?>" style="height:130px;border-radius:8px;background:linear-gradient(135deg,<?php echo esc_attr($cs); ?>,<?php echo esc_attr($ce); ?>);display:flex;align-items:center;justify-content:center;font-size:44px;margin-bottom:12px;text-decoration:none"><?php echo esc_html($icon); ?></a>
                        <?php endif; ?>
                        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:8px">
                            <h4 style="margin:0"><a href="<?php echo esc_url(get_permalink($course)); ?>" style="color:var(--text);text-decoration:none"><?php echo esc_html($course->post_title); ?></a></h4>
                            <span style="font-size:11px;color:#10b981;font-weight:700;white-space:nowrap"><i class="fas fa-check-circle me-1"></i><?php _e('Matriculado','hubskills-lms'); ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px">
                            <span style="color:var(--text-muted)"><?php echo $status==='completed'?'✅ '.__('Concluído','hubskills-lms'):'📖 '.__('Em Progresso','hubskills-lms'); ?></span>
                            <span style="color:var(--primary);font-weight:700"><?php echo (int) $progress; ?>%</span>
                        </div>
                        <div style="background:rgba(255,255,255,.1);border-radius:20px;height:6px;overflow:hidden;margin-bottom:12px">
                            <div style="width:<?php echo (int) $progress; ?>%;height:100%;background:var(--gradient-primary);border-radius:20px"></div>
                        </div>
                        <a href="<?php echo esc_url(get_permalink($course)); ?>" class="btn btn-sm btn-gradient w-100" style="font-size:13px"><?php echo $status==='completed'?__('Ver Novamente','hubskills-lms'):__('Continuar','hubskills-lms'); ?></a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <div class="glass-card text-center" style="padding:48px">
                <div style="font-size:56px">📚</div>
                <h3><?php _e('Nenhum curso ainda','hubskills-lms'); ?></h3>
                <a href="<?php echo esc_url(home_url('/cursos/')); ?>" class="btn btn-gradient"><?php _e('Explorar Cursos','hubskills-lms'); ?></a>
            </div>
            <?php endif; ?>

            <!-- Tab: Badges -->
            <?php elseif ($tab === 'badges') :
                $user_badges = (array)(get_user_meta($user_id,'hs_badges',true)?:[]);
                $all_badges  = HS_BADGES;
            ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:20px">
                <?php foreach ($all_badges as $badge_id => $badge) :
                    $earned = in_array($badge_id, $user_badges, true);
                ?>
                <div class="glass-card text-center" style="padding:28px;<?php echo !$earned?'opacity:.4':''; ?>">
                    <div style="font-size:48px;margin-bottom:8px"><?php echo esc_html($badge['icon']); ?></div>
                    <div style="font-weight:700;color:<?php echo esc_attr($badge['color']); ?>"><?php echo esc_html($badge['name']); ?></div>
                    <?php if ($earned) : ?>
                    <div style="font-size:12px;color:#10b981;margin-top:6px">✅ <?php _e('Conquistado','hubskills-lms'); ?></div>
                    <?php else : ?>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:6px">🔒 <?php _e('Bloqueado','hubskills-lms'); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Tab: Certificates -->
            <?php elseif ($tab === 'certificates') :
                $certs = (array)(get_user_meta($user_id,'hs_certificates',true)?:[]);
            ?>
            <?php if ($certs) : ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px">
                <?php foreach ($certs as $cid => $cert_data) : ?>
                <div class="glass-card" style="border:1px solid rgba(124,58,237,.3)">
                    <div style="text-align:center;padding:24px 16px">
                        <div style="font-size:48px">🎓</div>
                        <h4 style="color:var(--primary)"><?php echo esc_html(get_the_title($cid)); ?></h4>
                        <div style="font-size:13px;color:var(--text-muted)"><?php _e('Concluído em','hubskills-lms'); ?> <?php echo esc_html(date_i18n('d/m/Y',strtotime($cert_data['date']??''))); ?></div>
                        <div style="margin-top:12px">
                            <a href="<?php echo esc_url(add_query_arg(['cert'=>hs_certificate_id($user_id,$cid)],home_url('/certificado/'))); ?>" class="btn btn-sm btn-gradient"><?php _e('Ver Certificado','hubskills-lms'); ?></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <div class="glass-card text-center" style="padding:48px">
                <div style="font-size:56px">🎓</div>
                <h3><?php _e('Nenhum certificado ainda','hubskills-lms'); ?></h3>
                <p style="color:var(--text-muted)"><?php _e('Conclua um curso para obter seu certificado.','hubskills-lms'); ?></p>
            </div>
            <?php endif; ?>

            <!-- Tab: Activity -->
            <?php elseif ($tab === 'activity') :
                $activity = (array)(get_user_meta($user_id,'hs_activity',true)?:[]);
            ?>
            <div class="glass-card" style="max-width:700px">
                <h4><?php _e('Histórico de Atividades','hubskills-lms'); ?></h4>
                <?php if ($activity) : ?>
                <div style="position:relative;padding-left:24px">
                    <div style="position:absolute;left:7px;top:0;bottom:0;width:2px;background:rgba(255,255,255,.1)"></div>
                    <?php foreach ($activity as $act) : ?>
                    <div style="position:relative;padding:12px 0 12px 20px;border-bottom:1px solid rgba(255,255,255,.05)">
                        <div style="position:absolute;left:-5px;top:18px;width:12px;height:12px;border-radius:50%;background:<?php echo esc_attr($act['color']); ?>"></div>
                        <div><?php echo wp_kses_post($act['text']); ?></div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px"><?php echo esc_html($act['time']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <p style="color:var(--text-muted)"><?php _e('Nenhuma atividade registrada.','hubskills-lms'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Tab: Settings -->
            <?php elseif ($tab === 'settings') : ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:800px">
                <div class="glass-card">
                    <h4><?php _e('Informações da Conta','hubskills-lms'); ?></h4>
                    <form id="hs-profile-form" style="display:flex;flex-direction:column;gap:12px;font-size:14px">
                        <label style="display:flex;flex-direction:column;gap:4px;color:var(--text-muted)"><?php _e('Nome','hubskills-lms'); ?>
                            <input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" required minlength="2" style="padding:8px 12px;border-radius:8px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.05);color:var(--text)">
                        </label>
                        <label style="display:flex;flex-direction:column;gap:4px;color:var(--text-muted)"><?php _e('E-mail','hubskills-lms'); ?>
                            <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required style="padding:8px 12px;border-radius:8px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.05);color:var(--text)">
                        </label>
                        <label style="display:flex;flex-direction:column;gap:4px;color:var(--text-muted)"><?php _e('Nova senha (opcional)','hubskills-lms'); ?>
                            <input type="password" name="new_password" minlength="8" autocomplete="new-password" placeholder="<?php esc_attr_e('Deixe em branco para manter','hubskills-lms'); ?>" style="padding:8px 12px;border-radius:8px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.05);color:var(--text)">
                        </label>
                        <label style="display:flex;flex-direction:column;gap:4px;color:var(--text-muted)"><?php _e('Confirmar nova senha','hubskills-lms'); ?>
                            <input type="password" name="new_password_confirm" autocomplete="new-password" style="padding:8px 12px;border-radius:8px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.05);color:var(--text)">
                        </label>
                        <div style="font-size:12px;color:var(--text-muted)"><?php _e('Cadastro:','hubskills-lms'); ?> <?php echo esc_html(date_i18n('d/m/Y',strtotime($user->user_registered))); ?></div>
                        <button type="submit" class="btn btn-gradient btn-sm" style="align-self:flex-start"><?php _e('Salvar alterações','hubskills-lms'); ?></button>
                        <div id="hs-profile-msg" style="font-size:13px;display:none"></div>
                    </form>
                    <script>
                    document.getElementById('hs-profile-form').addEventListener('submit', function(e) {
                        e.preventDefault();
                        var form = this, msg = document.getElementById('hs-profile-msg');
                        var data = new FormData(form);
                        data.append('action', 'hs_update_profile');
                        data.append('nonce', '<?php echo esc_js( wp_create_nonce('hubskills_nonce') ); ?>');
                        form.querySelector('button[type=submit]').disabled = true;
                        fetch('<?php echo esc_js( admin_url('admin-ajax.php') ); ?>', { method: 'POST', credentials: 'same-origin', body: data })
                            .then(function(r){ return r.json(); })
                            .then(function(res) {
                                msg.style.display = 'block';
                                msg.style.color = res.success ? '#10b981' : '#ef4444';
                                msg.textContent = (res.data && res.data.message) ? res.data.message : 'Erro ao salvar.';
                                form.querySelector('button[type=submit]').disabled = false;
                                if (res.success) setTimeout(function(){ location.reload(); }, 1200);
                            })
                            .catch(function() {
                                msg.style.display = 'block'; msg.style.color = '#ef4444'; msg.textContent = 'Erro de conexão.';
                                form.querySelector('button[type=submit]').disabled = false;
                            });
                    });
                    </script>
                </div>
                <div class="glass-card" style="border:1px solid rgba(239,68,68,.3)">
                    <h4 style="color:#ef4444"><?php _e('Zona de Perigo','hubskills-lms'); ?></h4>
                    <p style="color:var(--text-muted);font-size:13px"><?php _e('Para cancelar sua conta ou remover seus dados, entre em contato com o suporte.','hubskills-lms'); ?></p>
                    <a href="mailto:<?php echo esc_attr(get_option('hs_contact_email',get_option('admin_email'))); ?>" class="btn btn-sm" style="background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.3);font-size:13px"><?php _e('Contatar Suporte','hubskills-lms'); ?></a>
                </div>
            </div>
            <?php endif; ?>

<?php get_footer(); ?>
