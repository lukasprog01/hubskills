<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php if ( function_exists( 'hubskills_uses_app_layout' ) && hubskills_uses_app_layout() ) : ?>

<!-- ══════════════════ DASHBOARD LAYOUT ══════════════════ -->
<div class="app-wrapper">

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="<?php echo home_url('/'); ?>" class="brand">
                <?php if ( has_custom_logo() ) : the_custom_logo(); else : ?>
                <div class="brand-icon">H</div>
                <span class="brand-name">Hub<span class="brand-accent">Skills</span></span>
                <?php endif; ?>
            </a>
        </div>

        <?php if ( is_user_logged_in() ) :
            $user = wp_get_current_user();
            $xp = (int) get_user_meta( $user->ID, 'hs_xp', true );
            $level = max( 1, floor( $xp / 500 ) + 1 );
            $avatar = get_avatar_url( $user->ID, [ 'size' => 48 ] );
        ?>
        <div class="sidebar-user">
            <img src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($user->display_name); ?>" class="sidebar-avatar">
            <div class="sidebar-user-info">
                <span class="sidebar-username"><?php echo esc_html($user->display_name); ?></span>
                <span class="sidebar-role">Nível <?php echo (int)$level; ?> &middot; <?php echo number_format($xp, 0, ',', '.'); ?> XP</span>
            </div>
        </div>
        <?php endif; ?>

        <nav class="sidebar-nav">
            <div class="nav-section-label"><?php _e( 'Principal', 'hubskills' ); ?></div>
            <ul>
                <li class="<?php echo is_page_template('page-dashboard.php') ? 'active' : ''; ?>">
                    <a href="<?php echo esc_url( home_url('/dashboard/') ); ?>">
                        <i class="fas fa-th-large"></i><span><?php _e( 'Dashboard', 'hubskills' ); ?></span>
                    </a>
                </li>
                <li class="<?php echo is_page_template(['page-courses.php','page-dashboard-courses.php']) ? 'active' : ''; ?>">
                    <a href="<?php echo esc_url( home_url( is_user_logged_in() ? '/meus-cursos/' : '/cursos/' ) ); ?>">
                        <i class="fas fa-graduation-cap"></i><span><?php _e( 'Cursos', 'hubskills' ); ?></span>
                    </a>
                </li>
                <li class="<?php echo is_page_template('page-profile.php') ? 'active' : ''; ?>">
                    <a href="<?php echo esc_url( home_url('/perfil/') ); ?>">
                        <i class="fas fa-user"></i><span><?php _e( 'Meu Perfil', 'hubskills' ); ?></span>
                    </a>
                </li>
                <li class="<?php echo is_page_template('page-certificate.php') ? 'active' : ''; ?>">
                    <a href="<?php echo esc_url( home_url('/certificados/') ); ?>">
                        <i class="fas fa-certificate"></i><span><?php _e( 'Certificados', 'hubskills' ); ?></span>
                </a>
                </li>
            </ul>

            <div class="nav-section-label" style="margin-top: 24px"><?php _e( 'Configurações', 'hubskills' ); ?></div>
            <ul>
                <li>
                    <a href="<?php echo esc_url( home_url('/perfil/?tab=settings') ); ?>">
                        <i class="fas fa-cog"></i><span><?php _e( 'Configurações', 'hubskills' ); ?></span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url( wp_logout_url( home_url('/') ) ); ?>">
                        <i class="fas fa-sign-out-alt"></i><span><?php _e( 'Sair', 'hubskills' ); ?></span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Top Bar -->
        <header class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <span></span><span></span><span></span>
            </button>
            <div class="topbar-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="<?php _e( 'Buscar cursos...', 'hubskills' ); ?>" id="topbarSearch">
            </div>
            <div class="topbar-actions">
                <button class="topbar-btn" aria-label="Notificações">
                    <i class="fas fa-bell"></i>
                    <span class="badge-dot"></span>
                </button>
                <?php if ( is_user_logged_in() ) :
                    $user = wp_get_current_user();
                ?>
                <div class="topbar-user">
                    <img src="<?php echo esc_url( get_avatar_url($user->ID, ['size'=>36]) ); ?>" alt="avatar">
                    <span><?php echo esc_html( $user->display_name ); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </header>

        <div class="page-body">

<?php else : ?>
<!-- ══════════════════ PROMO BANNER ══════════════════ -->
<div class="promo-banner">
    <span class="promo-brand"><?php _e( 'Hubforms:', 'hubskills' ); ?></span>
    <?php _e( 'Seu checklist Digital com vantagens exclusivas para membros Hubskills.', 'hubskills' ); ?>
    <a href="<?php echo esc_url( home_url('/cursos/') ); ?>"><?php _e( 'Clique aqui', 'hubskills' ); ?></a>
</div>

<!-- ══════════════════ PUBLIC HEADER ══════════════════ -->
<header class="site-header" id="siteHeader">
    <div class="container-xl">
        <nav class="navbar navbar-expand-lg navbar-light">
            <a class="navbar-brand" href="<?php echo home_url('/'); ?>">
                <?php if ( has_custom_logo() ) : the_custom_logo(); else : ?>
                <img class="brand-logo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/hubskills-logo.svg' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                <?php endif; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <?php wp_nav_menu( [
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'navbar-nav mx-auto',
                    'fallback_cb'    => 'hubskills_default_nav',
                ] ); ?>

                <div class="header-cta">
                    <?php if ( is_user_logged_in() ) :
                        $hs_dash_btn = function_exists( 'hubskills_user_dashboard_button' )
                            ? hubskills_user_dashboard_button()
                            : [ 'url' => home_url('/dashboard/'), 'label' => __( 'Dashboard', 'hubskills' ), 'icon' => 'fas fa-th-large' ];
                    ?>
                    <a href="<?php echo esc_url( $hs_dash_btn['url'] ); ?>" class="btn btn-primary btn-sm">
                        <i class="<?php echo esc_attr( $hs_dash_btn['icon'] ); ?>"></i> <span><?php echo esc_html( $hs_dash_btn['label'] ); ?></span>
                    </a>
                    <a href="<?php echo esc_url( wp_logout_url( home_url('/') ) ); ?>" class="btn btn-outline-secondary btn-sm">
                        <?php _e( 'Sair', 'hubskills' ); ?>
                    </a>
                    <?php else : ?>
                    <a href="<?php echo esc_url( wp_login_url( home_url('/dashboard/') ) ); ?>" class="btn btn-student btn-sm">
                        <i class="fas fa-user-graduate"></i> <?php _e( 'Área do Aluno', 'hubskills' ); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </div>
</header>
<?php endif; ?>
