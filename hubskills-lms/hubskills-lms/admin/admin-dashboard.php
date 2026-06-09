<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Admin Menu ──────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'hs_register_admin_menu' );
function hs_register_admin_menu() {
    add_menu_page(
        __( 'HubSkills LMS', 'hubskills-lms' ),
        __( 'HubSkills LMS', 'hubskills-lms' ),
        'manage_options',
        'hubskills-lms',
        'hs_admin_page_dashboard',
        'dashicons-welcome-learn-more',
        30
    );

    // First submenu = same slug as parent (replaces default "HubSkills LMS" link)
    add_submenu_page( 'hubskills-lms', __( 'Painel Geral', 'hubskills-lms' ), '📊 ' . __( 'Painel Geral', 'hubskills-lms' ), 'manage_options', 'hubskills-lms', 'hs_admin_page_dashboard' );

    $subpages = [
        [ 'hubskills-students',    '👨‍🎓 ' . __( 'Alunos', 'hubskills-lms' ),         'hs_admin_page_students'    ],
        [ 'hubskills-instructors', '🎓 ' . __( 'Instrutores', 'hubskills-lms' ),      'hs_admin_page_instructors' ],
        [ 'hubskills-courses',     '📚 ' . __( 'Cursos', 'hubskills-lms' ),           'hs_admin_page_courses'     ],
        [ 'hubskills-orders',      '📦 ' . __( 'Pedidos', 'hubskills-lms' ),          'hs_admin_page_orders'      ],
        [ 'hubskills-payments',    '💳 ' . __( 'Gateways', 'hubskills-lms' ),         'hs_admin_page_payments'    ],
        [ 'hubskills-security',    '🔒 ' . __( 'Segurança', 'hubskills-lms' ),        'hs_admin_page_security'    ],
        [ 'hubskills-audit',       '📋 ' . __( 'Auditoria', 'hubskills-lms' ),        'hs_admin_page_audit'       ],
        [ 'hubskills-reports',     '📈 ' . __( 'Relatórios', 'hubskills-lms' ),       'hs_admin_reports_page'     ],
        [ 'hubskills-settings',    '⚙️ ' . __( 'Configurações', 'hubskills-lms' ),    'hs_admin_page_settings'    ],
    ];

    foreach ( $subpages as $sub ) {
        add_submenu_page( 'hubskills-lms', strip_tags( $sub[1] ), $sub[1], 'manage_options', $sub[0], $sub[2] );
    }
}

// ── Admin enqueue scripts ───────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', 'hs_admin_enqueue_panel' );
function hs_admin_enqueue_panel( $hook ) {
    $hs_pages = [
        'toplevel_page_hubskills-lms',
        'hubskills-lms_page_hubskills-students',
        'hubskills-lms_page_hubskills-instructors',
        'hubskills-lms_page_hubskills-courses',
        'hubskills-lms_page_hubskills-orders',
        'hubskills-lms_page_hubskills-payments',
        'hubskills-lms_page_hubskills-security',
        'hubskills-lms_page_hubskills-audit',
        'hubskills-lms_page_hubskills-reports',
        'hubskills-lms_page_hubskills-settings',
        'post.php',
        'post-new.php',
    ];

    if ( ! in_array( $hook, $hs_pages, true ) ) return;

    wp_enqueue_style( 'hs-admin-panel', HS_LMS_URL . 'assets/css/panel.css', [], HS_LMS_VERSION );
    wp_enqueue_style( 'hs-lms-admin',   HS_LMS_URL . 'assets/css/admin.css',  [], HS_LMS_VERSION );
    wp_enqueue_script( 'hs-lms-admin',  HS_LMS_URL . 'assets/js/admin.js', [ 'jquery', 'wp-util' ], HS_LMS_VERSION, true );
    wp_localize_script( 'hs-lms-admin', 'HSADMIN', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'hs_admin_nonce' ),
    ] );
}

// ── Helper: admin header ────────────────────────────────────────────────────

function hs_admin_header( string $title, string $subtitle = '' ) {
    ?>
    <div class="hs-admin-header">
        <h1 class="hs-admin-title"><?php echo esc_html( $title ); ?></h1>
        <?php if ( $subtitle ) : ?>
            <p class="hs-admin-subtitle"><?php echo esc_html( $subtitle ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

// ── Page: Overview Dashboard ────────────────────────────────────────────────

function hs_admin_page_dashboard() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'Acesso negado.', 'hubskills-lms' ) );

    $total_students    = count( get_users( [ 'role' => 'hs_student', 'count_total' => false ] ) );
    $total_instructors = count( get_users( [ 'role' => 'hs_instructor', 'count_total' => false ] ) );
    $total_courses     = wp_count_posts( 'hubskills_course' )->publish ?? 0;

    global $wpdb;
    $total_revenue = (float)( $wpdb->get_var(
        "SELECT SUM(meta_value) FROM {$wpdb->postmeta}
         INNER JOIN {$wpdb->postmeta} pm2 ON {$wpdb->postmeta}.post_id = pm2.post_id
         WHERE {$wpdb->postmeta}.meta_key = '_hs_order_amount'
           AND pm2.meta_key = '_hs_order_status' AND pm2.meta_value = 'paid'"
    ) ?: 0 );

    $pending_apps = count( hs_get_instructor_applications( 'pending' ) );
    ?>
    <div class="wrap hs-admin-wrap">
        <?php hs_admin_header( __( 'HubSkills LMS — Painel', 'hubskills-lms' ), __( 'Visão geral da plataforma', 'hubskills-lms' ) ); ?>

        <?php if ( $pending_apps > 0 ) : ?>
        <div class="notice notice-warning">
            <p><?php printf( __( '%d candidatura(s) de instrutor aguardando aprovação.', 'hubskills-lms' ), $pending_apps ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=hubskills-instructors&tab=applications' ) ); ?>"><?php _e( 'Ver agora', 'hubskills-lms' ); ?></a></p>
        </div>
        <?php endif; ?>

        <div class="hs-stat-cards" style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin:24px 0">
            <?php
            $stats = [
                [ 'icon' => '👨‍🎓', 'value' => $total_students,    'label' => __( 'Alunos', 'hubskills-lms' ), 'color' => '#7C3AED' ],
                [ 'icon' => '🎓', 'value' => $total_instructors, 'label' => __( 'Instrutores', 'hubskills-lms' ), 'color' => '#06B6D4' ],
                [ 'icon' => '📚', 'value' => $total_courses,     'label' => __( 'Cursos Publicados', 'hubskills-lms' ), 'color' => '#F472B6' ],
                [ 'icon' => '💰', 'value' => 'R$ ' . number_format( $total_revenue, 2, ',', '.' ), 'label' => __( 'Receita Total', 'hubskills-lms' ), 'color' => '#10B981' ],
            ];
            foreach ( $stats as $s ) : ?>
            <div style="background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.1);border-left:4px solid <?php echo esc_attr( $s['color'] ); ?>">
                <div style="font-size:32px"><?php echo esc_html( $s['icon'] ); ?></div>
                <div style="font-size:28px;font-weight:700;color:<?php echo esc_attr( $s['color'] ); ?>"><?php echo esc_html( $s['value'] ); ?></div>
                <div style="color:#666;margin-top:4px"><?php echo esc_html( $s['label'] ); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <div style="background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.1)">
                <h3><?php _e( 'Atalhos Rápidos', 'hubskills-lms' ); ?></h3>
                <ul style="list-style:none;padding:0;margin:0">
                    <?php
                    $links = [
                        [ admin_url('admin.php?page=hubskills-students'),    __( '👨‍🎓 Gerenciar Alunos', 'hubskills-lms' ) ],
                        [ admin_url('admin.php?page=hubskills-instructors'), __( '🎓 Gerenciar Instrutores', 'hubskills-lms' ) ],
                        [ admin_url('admin.php?page=hubskills-orders'),      __( '📦 Ver Pedidos', 'hubskills-lms' ) ],
                        [ admin_url('admin.php?page=hubskills-payments'),    __( '💳 Configurar Gateways', 'hubskills-lms' ) ],
                        [ admin_url('admin.php?page=hubskills-security'),    __( '🔒 Segurança', 'hubskills-lms' ) ],
                        [ admin_url('post-new.php?post_type=hubskills_course'), __( '➕ Novo Curso', 'hubskills-lms' ) ],
                    ];
                    foreach ( $links as $l ) :
                        echo '<li style="margin:8px 0"><a href="' . esc_url( $l[0] ) . '" style="color:#7C3AED;text-decoration:none">' . esc_html( $l[1] ) . '</a></li>';
                    endforeach;
                    ?>
                </ul>
            </div>
            <div style="background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.1)">
                <h3><?php _e( 'Log de Atividade Recente', 'hubskills-lms' ); ?></h3>
                <?php
                $logs = hs_get_audit_log( 10 );
                if ( $logs ) :
                    echo '<ul style="list-style:none;padding:0;margin:0;font-size:13px">';
                    foreach ( $logs as $log ) :
                        echo '<li style="padding:6px 0;border-bottom:1px solid #f0f0f0">';
                        echo '<strong>' . esc_html( $log['display_name'] ?: 'Sistema' ) . '</strong> ';
                        echo esc_html( $log['description'] ) . '<br>';
                        echo '<small style="color:#999">' . esc_html( $log['created_at'] ) . '</small>';
                        echo '</li>';
                    endforeach;
                    echo '</ul>';
                else :
                    echo '<p style="color:#999">' . __( 'Nenhuma atividade registrada.', 'hubskills-lms' ) . '</p>';
                endif;
                ?>
            </div>
        </div>
    </div>
    <?php
}

// ── Page: Students ──────────────────────────────────────────────────────────

function hs_admin_page_students() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $search   = sanitize_text_field( $_GET['s'] ?? '' );
    $per_page = 20;
    $page     = max( 1, (int)( $_GET['paged'] ?? 1 ) );
    $offset   = ( $page - 1 ) * $per_page;

    $args = [
        'role'   => 'hs_student',
        'number' => $per_page,
        'offset' => $offset,
    ];
    if ( $search ) {
        $args['search']         = '*' . $search . '*';
        $args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
    }

    $users = get_users( $args );
    ?>
    <div class="wrap hs-admin-wrap">
        <?php hs_admin_header( __( 'Gerenciar Alunos', 'hubskills-lms' ) ); ?>
        <form method="get">
            <input type="hidden" name="page" value="hubskills-students">
            <p class="search-box">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar aluno...', 'hubskills-lms' ); ?>">
                <button type="submit" class="button"><?php _e( 'Buscar', 'hubskills-lms' ); ?></button>
                <a href="<?php echo esc_url( admin_url('admin-ajax.php?action=hs_export_students_csv&nonce=' . wp_create_nonce('hs_admin_nonce')) ); ?>" class="button" style="float:right"><?php _e( 'Exportar CSV', 'hubskills-lms' ); ?></a>
            </p>
        </form>
        <table class="wp-list-table widefat fixed striped" style="margin-top:16px">
            <thead>
                <tr>
                    <th><?php _e( 'Aluno', 'hubskills-lms' ); ?></th>
                    <th><?php _e( 'Email', 'hubskills-lms' ); ?></th>
                    <th><?php _e( 'Cursos', 'hubskills-lms' ); ?></th>
                    <th><?php _e( 'XP', 'hubskills-lms' ); ?></th>
                    <th><?php _e( 'Status', 'hubskills-lms' ); ?></th>
                    <th><?php _e( 'Ações', 'hubskills-lms' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( $users ) : foreach ( $users as $u ) :
                $enrollments = (array)( get_user_meta( $u->ID, 'hs_enrollments', true ) ?: [] );
                $xp          = (int) get_user_meta( $u->ID, 'hs_xp', true );
                $status      = get_user_meta( $u->ID, 'hs_account_status', true ) ?: 'active';
                $status_html = $status === 'suspended'
                    ? '<span style="color:#ef4444">● ' . __('Suspenso','hubskills-lms') . '</span>'
                    : '<span style="color:#10b981">● ' . __('Ativo','hubskills-lms') . '</span>';
                ?>
                <tr>
                    <td>
                        <?php echo get_avatar( $u->ID, 32, '', '', [ 'class' => '' ] ); ?>
                        <strong><?php echo esc_html( $u->display_name ); ?></strong>
                    </td>
                    <td><?php echo esc_html( $u->user_email ); ?></td>
                    <td><?php echo count( $enrollments ); ?></td>
                    <td><?php echo number_format( $xp ); ?></td>
                    <td><?php echo $status_html; ?></td>
                    <td>
                        <?php if ( $status === 'suspended' ) : ?>
                        <button class="button button-small hs-unsuspend-user" data-uid="<?php echo (int)$u->ID; ?>"><?php _e('Reativar','hubskills-lms'); ?></button>
                        <?php else : ?>
                        <button class="button button-small hs-suspend-user" data-uid="<?php echo (int)$u->ID; ?>"><?php _e('Suspender','hubskills-lms'); ?></button>
                        <?php endif; ?>
                        <button class="button button-small hs-reset-xp" data-uid="<?php echo (int)$u->ID; ?>"><?php _e('Reset XP','hubskills-lms'); ?></button>
                    </td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="6"><?php _e( 'Nenhum aluno encontrado.', 'hubskills-lms' ); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// ── Page: Instructors ───────────────────────────────────────────────────────

function hs_admin_page_instructors() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $tab = sanitize_key( $_GET['tab'] ?? 'instructors' );
    ?>
    <div class="wrap hs-admin-wrap">
        <?php hs_admin_header( __( 'Gerenciar Instrutores', 'hubskills-lms' ) ); ?>
        <nav class="nav-tab-wrapper" style="margin-bottom:20px">
            <a href="<?php echo esc_url( add_query_arg('tab','instructors') ); ?>" class="nav-tab <?php echo $tab==='instructors'?'nav-tab-active':''; ?>"><?php _e('Instrutores Ativos','hubskills-lms'); ?></a>
            <a href="<?php echo esc_url( add_query_arg('tab','applications') ); ?>" class="nav-tab <?php echo $tab==='applications'?'nav-tab-active':''; ?>">
                <?php _e('Candidaturas','hubskills-lms'); ?>
                <?php $pending = count(hs_get_instructor_applications('pending')); if($pending): echo ' <span class="awaiting-mod">'.$pending.'</span>'; endif; ?>
            </a>
        </nav>

        <?php if ( $tab === 'applications' ) : ?>
            <?php $apps = hs_get_instructor_applications( 'all' ); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Candidato','hubskills-lms'); ?></th>
                        <th><?php _e('Especialidade','hubskills-lms'); ?></th>
                        <th><?php _e('Data','hubskills-lms'); ?></th>
                        <th><?php _e('Status','hubskills-lms'); ?></th>
                        <th><?php _e('Ações','hubskills-lms'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( $apps ) : foreach ( $apps as $app ) :
                    $colors = ['pending'=>'#f59e0b','approved'=>'#10b981','rejected'=>'#ef4444'];
                    $color  = $colors[ $app['status'] ] ?? '#999';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($app['display_name']); ?></strong><br>
                            <small><?php echo esc_html($app['email']); ?></small>
                        </td>
                        <td><?php echo esc_html($app['expertise']); ?></td>
                        <td><?php echo esc_html($app['submitted']); ?></td>
                        <td><span style="color:<?php echo esc_attr($color); ?>;font-weight:600">● <?php echo esc_html(ucfirst($app['status'])); ?></span></td>
                        <td>
                            <?php if ( $app['status'] === 'pending' ) : ?>
                            <button class="button button-primary hs-approve-instructor" data-uid="<?php echo (int)$app['user_id']; ?>"><?php _e('Aprovar','hubskills-lms'); ?></button>
                            <button class="button hs-reject-instructor" data-uid="<?php echo (int)$app['user_id']; ?>"><?php _e('Rejeitar','hubskills-lms'); ?></button>
                            <?php elseif ($app['status']==='approved') : ?>
                            <span style="color:#10b981">✓ <?php _e('Aprovado','hubskills-lms'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="5"><?php _e('Nenhuma candidatura encontrada.','hubskills-lms'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>

        <?php else : ?>
            <?php $instructors = hs_get_all_instructors(); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Instrutor','hubskills-lms'); ?></th>
                        <th><?php _e('Cursos','hubskills-lms'); ?></th>
                        <th><?php _e('Alunos','hubskills-lms'); ?></th>
                        <th><?php _e('Comissão','hubskills-lms'); ?></th>
                        <th><?php _e('Status','hubskills-lms'); ?></th>
                        <th><?php _e('Ações','hubskills-lms'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( $instructors ) : foreach ( $instructors as $inst ) : ?>
                    <tr>
                        <td>
                            <img src="<?php echo esc_url($inst['avatar']); ?>" width="32" height="32" style="border-radius:50%;vertical-align:middle;margin-right:8px">
                            <strong><?php echo esc_html($inst['display_name']); ?></strong>
                        </td>
                        <td><?php echo (int)$inst['courses']; ?></td>
                        <td><?php echo (int)$inst['students']; ?></td>
                        <td>
                            <input type="number" min="1" max="100" value="<?php echo (int)$inst['commission']; ?>"
                                   class="hs-commission-input" data-uid="<?php echo (int)$inst['user_id']; ?>" style="width:60px">%
                        </td>
                        <td>
                            <?php echo $inst['status']==='suspended'
                                ? '<span style="color:#ef4444">● Suspenso</span>'
                                : '<span style="color:#10b981">● Ativo</span>'; ?>
                        </td>
                        <td>
                            <?php if ($inst['status']==='suspended') : ?>
                            <button class="button button-small hs-unsuspend-user" data-uid="<?php echo (int)$inst['user_id']; ?>"><?php _e('Reativar','hubskills-lms'); ?></button>
                            <?php else : ?>
                            <button class="button button-small hs-suspend-user" data-uid="<?php echo (int)$inst['user_id']; ?>"><?php _e('Suspender','hubskills-lms'); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="6"><?php _e('Nenhum instrutor encontrado.','hubskills-lms'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// ── Page: Courses ───────────────────────────────────────────────────────────

function hs_admin_page_courses() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
    ?>
    <div class="wrap hs-admin-wrap">
        <?php hs_admin_header( __( 'Gerenciar Cursos', 'hubskills-lms' ) ); ?>
        <?php
        $courses = get_posts([
            'post_type'      => 'hubskills_course',
            'posts_per_page' => 30,
            'post_status'    => [ 'publish', 'draft', 'pending' ],
        ]);
        ?>
        <p><a href="<?php echo esc_url(admin_url('post-new.php?post_type=hubskills_course')); ?>" class="button button-primary"><?php _e('+ Novo Curso','hubskills-lms'); ?></a></p>
        <table class="wp-list-table widefat fixed striped" style="margin-top:16px">
            <thead>
                <tr>
                    <th><?php _e('Curso','hubskills-lms'); ?></th>
                    <th><?php _e('Instrutor','hubskills-lms'); ?></th>
                    <th><?php _e('Preço','hubskills-lms'); ?></th>
                    <th><?php _e('Alunos','hubskills-lms'); ?></th>
                    <th><?php _e('Status','hubskills-lms'); ?></th>
                    <th><?php _e('Ações','hubskills-lms'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( $courses ) : foreach ( $courses as $course ) :
                $instructor_id = (int) get_post_meta( $course->ID, '_hs_instructor_id', true );
                $instructor    = $instructor_id ? get_userdata($instructor_id) : null;
                $price         = get_post_meta( $course->ID, '_hs_price', true ) ?: __('Grátis','hubskills-lms');
                $students      = function_exists( 'hs_course_student_count' )
                    ? hs_course_student_count( (int) $course->ID )
                    : max( (int) get_post_meta( $course->ID, '_hs_students', true ), (int) get_post_meta( $course->ID, '_hs_student_count', true ) );
                $statuses      = ['publish'=>'Publicado','draft'=>'Rascunho','pending'=>'Revisão'];
                ?>
                <tr>
                    <td><strong><?php echo esc_html($course->post_title); ?></strong></td>
                    <td><?php echo $instructor ? esc_html($instructor->display_name) : '—'; ?></td>
                    <td><?php echo esc_html($price); ?></td>
                    <td><?php echo $students; ?></td>
                    <td><?php echo esc_html($statuses[$course->post_status] ?? $course->post_status); ?></td>
                    <td>
                        <a href="<?php echo esc_url(get_edit_post_link($course->ID)); ?>" class="button button-small"><?php _e('Editar','hubskills-lms'); ?></a>
                        <a href="<?php echo esc_url(get_permalink($course->ID)); ?>" class="button button-small" target="_blank"><?php _e('Ver','hubskills-lms'); ?></a>
                    </td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="6"><?php _e('Nenhum curso encontrado.','hubskills-lms'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// ── Page: Orders ────────────────────────────────────────────────────────────

function hs_admin_page_orders() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $status_filter = sanitize_key( $_GET['status'] ?? '' );
    ?>
    <div class="wrap hs-admin-wrap">
        <?php hs_admin_header( __( 'Pedidos', 'hubskills-lms' ) ); ?>
        <ul class="subsubsub">
            <?php
            $statuses = [''=>__('Todos','hubskills-lms'),'paid'=>__('Pagos','hubskills-lms'),'pending'=>__('Pendente','hubskills-lms'),'refunded'=>__('Reembolsado','hubskills-lms'),'failed'=>__('Falhou','hubskills-lms')];
            foreach ($statuses as $s => $label) {
                $url = esc_url(add_query_arg(['status'=>$s]));
                $cls = ($status_filter === $s) ? 'current' : '';
                echo "<li><a href='{$url}' class='{$cls}'>{$label}</a> | </li>";
            }
            ?>
        </ul>
        <?php
        $meta_query = [['key'=>'_hs_order_amount']];
        if ($status_filter) {
            $meta_query[] = ['key'=>'_hs_order_status','value'=>$status_filter];
        }

        $orders = get_posts([
            'post_type'      => 'hs_order',
            'posts_per_page' => 30,
            'post_status'    => 'publish',
            'meta_query'     => $meta_query,
        ]);
        ?>
        <table class="wp-list-table widefat fixed striped" style="margin-top:16px">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php _e('Aluno','hubskills-lms'); ?></th>
                    <th><?php _e('Curso','hubskills-lms'); ?></th>
                    <th><?php _e('Valor','hubskills-lms'); ?></th>
                    <th><?php _e('Gateway','hubskills-lms'); ?></th>
                    <th><?php _e('Status','hubskills-lms'); ?></th>
                    <th><?php _e('Data','hubskills-lms'); ?></th>
                    <th><?php _e('Ações','hubskills-lms'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($orders) : foreach ($orders as $order) :
                $uid     = (int) get_post_meta($order->ID, '_hs_order_user_id', true);
                $cid     = (int) get_post_meta($order->ID, '_hs_order_course_id', true);
                $amount  = (float) get_post_meta($order->ID, '_hs_order_amount', true);
                $gateway = get_post_meta($order->ID, '_hs_order_gateway', true);
                $ostatus = get_post_meta($order->ID, '_hs_order_status', true);
                $created = get_post_meta($order->ID, '_hs_order_created_at', true);
                $user    = get_userdata($uid);
                $colors  = ['paid'=>'#10b981','pending'=>'#f59e0b','refunded'=>'#06b6d4','failed'=>'#ef4444'];
                $scolor  = $colors[$ostatus] ?? '#999';
                ?>
                <tr>
                    <td>#<?php echo (int)$order->ID; ?></td>
                    <td><?php echo $user ? esc_html($user->display_name) : '#'.$uid; ?></td>
                    <td><?php echo esc_html(get_the_title($cid)); ?></td>
                    <td>R$ <?php echo number_format($amount, 2, ',', '.'); ?></td>
                    <td><?php echo esc_html(strtoupper($gateway)); ?></td>
                    <td><span style="color:<?php echo esc_attr($scolor); ?>;font-weight:600">● <?php echo esc_html(ucfirst($ostatus)); ?></span></td>
                    <td><?php echo esc_html($created); ?></td>
                    <td>
                        <?php if ($ostatus === 'pending') : ?>
                        <button class="button button-small button-primary hs-mark-order-paid" data-oid="<?php echo (int)$order->ID; ?>"><?php _e('Marcar pago','hubskills-lms'); ?></button>
                        <?php endif; ?>
                        <?php if ($ostatus === 'paid') : ?>
                        <button class="button button-small hs-refund-order" data-oid="<?php echo (int)$order->ID; ?>"><?php _e('Reembolsar','hubskills-lms'); ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="8"><?php _e('Nenhum pedido encontrado.','hubskills-lms'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// ── Page: Payment Gateways ──────────────────────────────────────────────────

function hs_admin_page_payments() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $active = hs_get_active_gateway();
    $gateways = [ 'stripe', 'paypal', 'mercadopago', 'pagseguro', 'pix' ];
    $gateway_labels = [
        'stripe'      => '💳 Stripe',
        'paypal'      => '🅿 PayPal',
        'mercadopago' => '💙 Mercado Pago',
        'pagseguro'   => '🟦 PagSeguro',
        'pix'         => '💚 PIX',
    ];

    if ( isset($_POST['hs_save_gateway']) && check_admin_referer('hs_save_gateway') ) {
        $new_gateway = sanitize_key( $_POST['active_gateway'] ?? '' );
        if ( in_array($new_gateway, $gateways, true) ) {
            update_option('hs_active_payment_gateway', $new_gateway);
        }

        foreach ($gateways as $gw) {
            $config = [];
            if (!empty($_POST['gw'][$gw]) && is_array($_POST['gw'][$gw])) {
                foreach ($_POST['gw'][$gw] as $k => $v) {
                    $config[sanitize_key($k)] = sanitize_text_field( wp_unslash( $v ) );
                }
            }
            if ( function_exists( 'hs_prepare_gateway_config_for_storage' ) ) {
                $config = hs_prepare_gateway_config_for_storage( $gw, $config );
            }
            update_option('hs_payment_gateway_' . $gw, $config);
        }

        update_option('hs_currency', sanitize_text_field($_POST['hs_currency'] ?? 'BRL'));
        update_option('hs_payment_success_url', esc_url_raw($_POST['hs_payment_success_url'] ?? ''));
        update_option('hs_payment_cancel_url', esc_url_raw($_POST['hs_payment_cancel_url'] ?? ''));

        echo '<div class="notice notice-success"><p>' . __('Configurações salvas!','hubskills-lms') . '</p></div>';
        $active = hs_get_active_gateway();
    }
    ?>
    <div class="wrap hs-admin-wrap">
        <?php hs_admin_header(__('Gateways de Pagamento','hubskills-lms'), __('Configure os métodos de pagamento aceitos na plataforma','hubskills-lms')); ?>

        <form method="post">
            <?php wp_nonce_field('hs_save_gateway'); ?>

            <h3><?php _e('Gateway Ativo','hubskills-lms'); ?></h3>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px">
                <?php foreach ($gateway_labels as $slug => $label) : ?>
                <label style="display:flex;align-items:center;gap:8px;padding:12px 20px;border:2px solid <?php echo ($active===$slug)?'#7C3AED':'#ddd'; ?>;border-radius:8px;cursor:pointer;background:<?php echo ($active===$slug)?'#f5f0ff':'#fff'; ?>">
                    <input type="radio" name="active_gateway" value="<?php echo esc_attr($slug); ?>" <?php checked($active,$slug); ?>>
                    <?php echo esc_html($label); ?>
                </label>
                <?php endforeach; ?>
            </div>

            <table class="form-table">
                <tr><th><?php _e('Moeda','hubskills-lms'); ?></th>
                    <td><input type="text" name="hs_currency" value="<?php echo esc_attr(get_option('hs_currency','BRL')); ?>" class="regular-text"></td>
                </tr>
                <tr><th><?php _e('URL de Sucesso','hubskills-lms'); ?></th>
                    <td><input type="url" name="hs_payment_success_url" value="<?php echo esc_attr(get_option('hs_payment_success_url',home_url('/dashboard/'))); ?>" class="regular-text"></td>
                </tr>
                <tr><th><?php _e('URL de Cancelamento','hubskills-lms'); ?></th>
                    <td><input type="url" name="hs_payment_cancel_url" value="<?php echo esc_attr(get_option('hs_payment_cancel_url',home_url('/cursos/'))); ?>" class="regular-text"></td>
                </tr>
            </table>

            <hr>
            <h3>Stripe</h3>
            <?php $sc = hs_get_gateway_config('stripe'); ?>
            <table class="form-table">
                <tr><th><?php _e('Chave Pública','hubskills-lms'); ?></th><td><input type="text" name="gw[stripe][publishable_key]" value="<?php echo esc_attr($sc['publishable_key']??''); ?>" class="regular-text" placeholder="pk_..."></td></tr>
                <tr><th><?php _e('Chave Secreta','hubskills-lms'); ?></th><td><input type="password" name="gw[stripe][secret_key]" value="<?php echo esc_attr($sc['secret_key']??''); ?>" class="regular-text" placeholder="sk_..."></td></tr>
                <tr><th><?php _e('Webhook Secret','hubskills-lms'); ?></th><td><input type="password" name="gw[stripe][webhook_secret]" value="<?php echo esc_attr($sc['webhook_secret']??''); ?>" class="regular-text" placeholder="whsec_..."></td></tr>
                <tr><th><?php _e('Webhook URL','hubskills-lms'); ?></th><td><code><?php echo esc_html(rest_url('hubskills/v1/webhooks/stripe')); ?></code></td></tr>
            </table>

            <hr>
            <h3>PayPal</h3>
            <?php $pc = hs_get_gateway_config('paypal'); ?>
            <table class="form-table">
                <tr><th><?php _e('Client ID','hubskills-lms'); ?></th><td><input type="text" name="gw[paypal][client_id]" value="<?php echo esc_attr($pc['client_id']??''); ?>" class="regular-text"></td></tr>
                <tr><th><?php _e('Client Secret','hubskills-lms'); ?></th><td><input type="password" name="gw[paypal][client_secret]" value="<?php echo esc_attr($pc['client_secret']??''); ?>" class="regular-text"></td></tr>
                <tr><th><?php _e('Sandbox','hubskills-lms'); ?></th><td><label><input type="checkbox" name="gw[paypal][sandbox]" value="1" <?php checked(!empty($pc['sandbox'])); ?>> <?php _e('Usar modo sandbox','hubskills-lms'); ?></label></td></tr>
            </table>

            <hr>
            <h3>Mercado Pago</h3>
            <?php $mc = hs_get_gateway_config('mercadopago'); ?>
            <table class="form-table">
                <tr><th><?php _e('Access Token','hubskills-lms'); ?></th><td><input type="password" name="gw[mercadopago][access_token]" value="<?php echo esc_attr($mc['access_token']??''); ?>" class="regular-text" placeholder="APP_USR-..."></td></tr>
                <tr><th><?php _e('Public Key','hubskills-lms'); ?></th><td><input type="text" name="gw[mercadopago][public_key]" value="<?php echo esc_attr($mc['public_key']??''); ?>" class="regular-text"></td></tr>
                <tr><th><?php _e('Sandbox','hubskills-lms'); ?></th><td><label><input type="checkbox" name="gw[mercadopago][sandbox]" value="1" <?php checked(!empty($mc['sandbox'])); ?>> <?php _e('Usar modo sandbox','hubskills-lms'); ?></label></td></tr>
                <tr><th><?php _e('Webhook URL','hubskills-lms'); ?></th><td><code><?php echo esc_html(rest_url('hubskills/v1/webhooks/mercadopago')); ?></code></td></tr>
            </table>

            <hr>
            <h3>PagSeguro</h3>
            <?php $psc = hs_get_gateway_config('pagseguro'); ?>
            <table class="form-table">
                <tr><th><?php _e('Email','hubskills-lms'); ?></th><td><input type="email" name="gw[pagseguro][email]" value="<?php echo esc_attr($psc['email']??''); ?>" class="regular-text"></td></tr>
                <tr><th><?php _e('Token','hubskills-lms'); ?></th><td><input type="password" name="gw[pagseguro][token]" value="<?php echo esc_attr($psc['token']??''); ?>" class="regular-text"></td></tr>
                <tr><th><?php _e('Sandbox','hubskills-lms'); ?></th><td><label><input type="checkbox" name="gw[pagseguro][sandbox]" value="1" <?php checked(!empty($psc['sandbox'])); ?>> <?php _e('Usar modo sandbox','hubskills-lms'); ?></label></td></tr>
                <tr><th><?php _e('Webhook URL','hubskills-lms'); ?></th><td><code><?php echo esc_html(rest_url('hubskills/v1/webhooks/pagseguro')); ?></code></td></tr>
            </table>

            <hr>
            <h3>PIX</h3>
            <?php $pixc = hs_get_gateway_config('pix'); ?>
            <table class="form-table">
                <tr><th><?php _e('Chave PIX','hubskills-lms'); ?></th><td><input type="text" name="gw[pix][pix_key]" value="<?php echo esc_attr($pixc['pix_key']??''); ?>" class="regular-text" placeholder="CPF, CNPJ, email, telefone ou chave aleatória"></td></tr>
                <tr><th><?php _e('Nome do Beneficiário','hubskills-lms'); ?></th><td><input type="text" name="gw[pix][merchant_name]" value="<?php echo esc_attr($pixc['merchant_name']??''); ?>" class="regular-text" maxlength="25"></td></tr>
                <tr><th><?php _e('Cidade','hubskills-lms'); ?></th><td><input type="text" name="gw[pix][city]" value="<?php echo esc_attr($pixc['city']??get_option('hs_city','Brasil')); ?>" class="regular-text" maxlength="15"></td></tr>
            </table>

            <p><input type="submit" name="hs_save_gateway" class="button button-primary" value="<?php esc_attr_e('Salvar Configurações','hubskills-lms'); ?>"></p>
        </form>
    </div>
    <?php
}

// ── Page: Security ──────────────────────────────────────────────────────────

function hs_admin_page_security() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    global $wpdb;
    $table     = $wpdb->prefix . 'hs_audit_log';
    $fails_key = 'hs_login_failures';
    ?>
    <div class="wrap hs-admin-wrap">
        <?php hs_admin_header(__('Segurança','hubskills-lms'), __('Monitoramento de segurança e bloqueios','hubskills-lms')); ?>

        <h3><?php _e('Configurações de Segurança','hubskills-lms'); ?></h3>
        <?php if (isset($_POST['hs_save_security']) && check_admin_referer('hs_save_security')) : ?>
            <?php
            update_option('hs_max_login_attempts', (int)($_POST['max_login_attempts']??5));
            update_option('hs_lockout_duration', (int)($_POST['lockout_duration']??60));
            echo '<div class="notice notice-success"><p>' . __('Salvo!','hubskills-lms') . '</p></div>';
            ?>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('hs_save_security'); ?>
            <table class="form-table">
                <tr>
                    <th><?php _e('Máx. tentativas de login','hubskills-lms'); ?></th>
                    <td><input type="number" name="max_login_attempts" min="3" max="20" value="<?php echo (int)get_option('hs_max_login_attempts',5); ?>"> <?php _e('tentativas antes de bloquear','hubskills-lms'); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Duração do bloqueio','hubskills-lms'); ?></th>
                    <td><input type="number" name="lockout_duration" min="5" max="1440" value="<?php echo (int)get_option('hs_lockout_duration',60); ?>"> <?php _e('minutos','hubskills-lms'); ?></td>
                </tr>
            </table>
            <input type="submit" name="hs_save_security" class="button button-primary" value="<?php esc_attr_e('Salvar','hubskills-lms'); ?>">
        </form>

        <hr>
        <h3><?php _e('Desbloquear IP','hubskills-lms'); ?></h3>
        <div style="display:flex;gap:8px;align-items:center">
            <input type="text" id="hs-unblock-ip" placeholder="<?php esc_attr_e('Digite o IP...','hubskills-lms'); ?>" class="regular-text">
            <button class="button button-primary" id="hs-unblock-btn"><?php _e('Desbloquear','hubskills-lms'); ?></button>
        </div>
        <p style="color:#999;font-size:13px"><?php _e('Remove o bloqueio de IP de um visitante.','hubskills-lms'); ?></p>

        <hr>
        <h3><?php _e('Usuários Suspensos','hubskills-lms'); ?></h3>
        <?php
        $suspended = get_users(['meta_key'=>'hs_account_status','meta_value'=>'suspended','number'=>20]);
        if ($suspended) :
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>'.__('Usuário').'</th><th>'.__('Motivo').'</th><th>'.__('Data').'</th><th>'.__('Ações').'</th></tr></thead>';
            echo '<tbody>';
            foreach ($suspended as $u) {
                $reason = get_user_meta($u->ID,'hs_suspend_reason',true);
                $date   = get_user_meta($u->ID,'hs_suspended_at',true);
                echo '<tr>';
                echo '<td>'.esc_html($u->display_name).'</td>';
                echo '<td>'.esc_html($reason).'</td>';
                echo '<td>'.esc_html($date).'</td>';
                echo '<td><button class="button button-small hs-unsuspend-user" data-uid="'.(int)$u->ID.'">'.__('Reativar').'</button></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        else :
            echo '<p style="color:#999">'.__('Nenhum usuário suspenso.','hubskills-lms').'</p>';
        endif;
        ?>
    </div>
    <?php
}

// ── Page: Audit Log ─────────────────────────────────────────────────────────

function hs_admin_page_audit() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $action_filter = sanitize_key( $_GET['action_filter'] ?? '' );
    $logs = hs_get_audit_log( 50, 0, $action_filter );
    ?>
    <div class="wrap hs-admin-wrap">
        <?php hs_admin_header(__('Log de Auditoria','hubskills-lms'), __('Registro completo de todas as ações administrativas','hubskills-lms')); ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('ID','hubskills-lms'); ?></th>
                    <th><?php _e('Usuário','hubskills-lms'); ?></th>
                    <th><?php _e('Ação','hubskills-lms'); ?></th>
                    <th><?php _e('Descrição','hubskills-lms'); ?></th>
                    <th><?php _e('IP','hubskills-lms'); ?></th>
                    <th><?php _e('Data/Hora','hubskills-lms'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs) : foreach ($logs as $log) : ?>
                <tr>
                    <td><?php echo (int)$log['id']; ?></td>
                    <td><?php echo esc_html($log['display_name'] ?: 'Sistema'); ?></td>
                    <td><code><?php echo esc_html($log['action']); ?></code></td>
                    <td><?php echo esc_html($log['description']); ?></td>
                    <td><?php echo esc_html($log['ip_address']); ?></td>
                    <td><?php echo esc_html($log['created_at']); ?></td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="6"><?php _e('Nenhum registro encontrado.','hubskills-lms'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// ── Page: General Settings ──────────────────────────────────────────────────

function hs_admin_page_settings() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    if ( isset($_POST['hs_save_settings']) && check_admin_referer('hs_save_settings') ) {
        $fields = [
            'hs_platform_name'   => 'sanitize_text_field',
            'hs_xp_per_lesson'   => 'intval',
            'hs_xp_per_quiz'     => 'intval',
            'hs_xp_per_course'   => 'intval',
            'hs_quiz_threshold'  => 'intval',
            'hs_instructor_commission' => 'intval',
            'hs_city'            => 'sanitize_text_field',
            'hs_contact_email'   => 'sanitize_email',
        ];
        foreach ($fields as $key => $cb) {
            if (isset($_POST[$key])) {
                update_option($key, call_user_func($cb, $_POST[$key]));
            }
        }
        echo '<div class="notice notice-success"><p>'.__('Configurações salvas!','hubskills-lms').'</p></div>';
    }
    ?>
    <div class="wrap hs-admin-wrap">
        <?php hs_admin_header(__('Configurações Gerais','hubskills-lms')); ?>
        <form method="post">
            <?php wp_nonce_field('hs_save_settings'); ?>
            <table class="form-table">
                <tr><th><?php _e('Nome da Plataforma','hubskills-lms'); ?></th>
                    <td><input type="text" name="hs_platform_name" value="<?php echo esc_attr(get_option('hs_platform_name','HubSkills')); ?>" class="regular-text"></td></tr>
                <tr><th><?php _e('Email de Contato','hubskills-lms'); ?></th>
                    <td><input type="email" name="hs_contact_email" value="<?php echo esc_attr(get_option('hs_contact_email',get_option('admin_email'))); ?>" class="regular-text"></td></tr>
                <tr><th><?php _e('Cidade (para PIX)','hubskills-lms'); ?></th>
                    <td><input type="text" name="hs_city" value="<?php echo esc_attr(get_option('hs_city','Brasil')); ?>" class="regular-text" maxlength="15"></td></tr>
                <tr><th colspan="2"><hr><h3><?php _e('Gamificação','hubskills-lms'); ?></h3></th></tr>
                <tr><th><?php _e('XP por Aula','hubskills-lms'); ?></th>
                    <td><input type="number" name="hs_xp_per_lesson" value="<?php echo (int)get_option('hs_xp_per_lesson',50); ?>" min="0"></td></tr>
                <tr><th><?php _e('XP por Quiz Aprovado','hubskills-lms'); ?></th>
                    <td><input type="number" name="hs_xp_per_quiz" value="<?php echo (int)get_option('hs_xp_per_quiz',200); ?>" min="0"></td></tr>
                <tr><th><?php _e('XP por Curso Completo','hubskills-lms'); ?></th>
                    <td><input type="number" name="hs_xp_per_course" value="<?php echo (int)get_option('hs_xp_per_course',500); ?>" min="0"></td></tr>
                <tr><th><?php _e('Nota mínima Quiz (%)','hubskills-lms'); ?></th>
                    <td><input type="number" name="hs_quiz_threshold" value="<?php echo (int)get_option('hs_quiz_threshold',70); ?>" min="0" max="100"></td></tr>
                <tr><th colspan="2"><hr><h3><?php _e('Instrutores','hubskills-lms'); ?></h3></th></tr>
                <tr><th><?php _e('Comissão Padrão do Instrutor (%)','hubskills-lms'); ?></th>
                    <td><input type="number" name="hs_instructor_commission" value="<?php echo (int)get_option('hs_instructor_commission',70); ?>" min="1" max="99">
                    <p class="description"><?php _e('A plataforma ficará com o restante.','hubskills-lms'); ?></p></td></tr>
            </table>
            <p><input type="submit" name="hs_save_settings" class="button button-primary" value="<?php esc_attr_e('Salvar Configurações','hubskills-lms'); ?>"></p>
        </form>
    </div>
    <?php
}
