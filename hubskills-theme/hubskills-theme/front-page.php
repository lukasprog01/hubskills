<?php
/**
 * Template Name: Home Page
 */
get_header();

$asset_url = trailingslashit( get_template_directory_uri() ) . 'assets/images/';
?>

<section class="hero-section" data-page="home">
    <div class="container-xl hero-container">
        <div class="hero-copy" data-aos="fade-up">
            <p class="hero-pretitle"><?php _e( 'HubSkills, aprendizagem quando e onde você quiser', 'hubskills' ); ?></p>
            <h1 class="hero-title">
                <span aria-hidden="true">🚀</span>
                <?php _e( 'Desenvolvimento alinhado à sua rotina!', 'hubskills' ); ?>
            </h1>
            <p class="hero-desc">
                <?php _e( 'Transformando conhecimento digital em mudanças reais.', 'hubskills' ); ?>
            </p>
            <div class="hero-cta">
                <?php if ( is_user_logged_in() ) :
                    $hs_dash_btn = function_exists( 'hubskills_user_dashboard_button' )
                        ? hubskills_user_dashboard_button()
                        : [ 'url' => home_url('/dashboard/'), 'label' => __( 'Ir ao Dashboard', 'hubskills' ), 'icon' => 'fas fa-th-large' ];
                ?>
                    <a href="<?php echo esc_url( $hs_dash_btn['url'] ); ?>" class="btn btn-yellow btn-lg">
                        <i class="<?php echo esc_attr( $hs_dash_btn['icon'] ); ?> me-2"></i><?php echo esc_html( $hs_dash_btn['label'] ); ?>
                    </a>
                <?php else : ?>
                    <a href="<?php echo esc_url( home_url( '/cursos/' ) ); ?>" class="btn btn-yellow btn-lg">
                        <?php _e( 'Começar Cursos Agora', 'hubskills' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="categories-section">
    <div class="container-xl">
        <div class="row g-4 categories-row">
            <?php
            $categories = [
                [
                    'image' => $asset_url . 'category-safety.jpg',
                    'title' => __( 'Segurança do Trabalho', 'hubskills' ),
                    'desc'  => __( 'Conheça práticas essenciais para proteger colaboradores e garantir ambientes seguros.', 'hubskills' ),
                    'link'  => home_url( '/cursos/?category=seguranca-do-trabalho' ),
                ],
                [
                    'image' => $asset_url . 'category-management.jpg',
                    'title' => __( 'Gestão Pedagógica', 'hubskills' ),
                    'desc'  => __( 'Explore estratégias educacionais para liderar equipes e transformar o aprendizado.', 'hubskills' ),
                    'link'  => home_url( '/cursos/?category=gestao-pedagogica' ),
                ],
                [
                    'image' => $asset_url . 'category-health.jpg',
                    'title' => __( 'Saúde', 'hubskills' ),
                    'desc'  => __( 'Aprenda sobre bem-estar físico e mental para promover qualidade de vida no trabalho.', 'hubskills' ),
                    'link'  => home_url( '/cursos/?category=saude' ),
                ],
            ];
            foreach ( $categories as $i => $cat ) :
                ?>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="<?php echo (int) $i * 100; ?>">
                    <article class="category-card">
                        <img class="category-image" src="<?php echo esc_url( $cat['image'] ); ?>" alt="<?php echo esc_attr( $cat['title'] ); ?>">
                        <div class="category-content">
                            <h3><?php echo esc_html( $cat['title'] ); ?></h3>
                            <p><?php echo esc_html( $cat['desc'] ); ?></p>
                            <a href="<?php echo esc_url( $cat['link'] ); ?>" class="btn btn-primary btn-sm category-btn">
                                <?php _e( 'Ver Categoria', 'hubskills' ); ?>
                            </a>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="benefits-section">
    <div class="container-xl benefits-container">
        <div class="section-header" data-aos="fade-up">
            <h2><?php _e( 'Por que escolher a', 'hubskills' ); ?> <span>Hubskills.com.br</span>?</h2>
        </div>

        <div class="benefits-grid">
            <?php
            $benefits = [
                [
                    'icon'  => 'fas fa-headset',
                    'tone'  => 'benefit-icon--support',
                    'title' => __( 'Suporte Humanizado', 'hubskills' ),
                    'desc'  => __( 'Equipe pronta para te acompanhar de perto, com respostas rápidas e foco na sua jornada de aprendizado.', 'hubskills' ),
                ],
                [
                    'icon'  => 'fas fa-check',
                    'tone'  => 'benefit-icon--cert',
                    'title' => __( 'Certificação Reconhecida', 'hubskills' ),
                    'desc'  => __( 'Ao concluir cada curso, você recebe certificados válidos que valorizam seu perfil profissional.', 'hubskills' ),
                ],
            ];
            foreach ( $benefits as $benefit ) :
                ?>
                <article class="benefit-card" data-aos="fade-up">
                    <div class="benefit-icon <?php echo esc_attr( $benefit['tone'] ); ?>">
                        <i class="<?php echo esc_attr( $benefit['icon'] ); ?>"></i>
                    </div>
                    <div class="benefit-content">
                        <h3><?php echo esc_html( $benefit['title'] ); ?></h3>
                        <p><?php echo esc_html( $benefit['desc'] ); ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="courses-section" id="courses">
    <div class="container-xl">
        <div class="section-header" data-aos="fade-up">
            <h2><?php _e( 'Cursos em', 'hubskills' ); ?> <span style="color:var(--primary)"><?php _e( 'Destaque', 'hubskills' ); ?></span></h2>
            <p class="section-subtitle"><?php _e( 'Os cursos mais populares escolhidos pelos nossos alunos.', 'hubskills' ); ?></p>
        </div>

        <?php
        $featured_courses = new WP_Query(
            [
                'post_type'      => 'hubskills_course',
                'posts_per_page' => 6,
                'meta_key'       => '_hs_featured',
                'meta_value'     => '1',
                'orderby'        => 'meta_value_num',
                'order'          => 'DESC',
            ]
        );
        if ( ! $featured_courses->have_posts() ) {
            $featured_courses = new WP_Query(
                [
                    'post_type'      => 'hubskills_course',
                    'posts_per_page' => 6,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                ]
            );
        }
        ?>

        <?php if ( $featured_courses->have_posts() && function_exists( 'hubskills_course_card' ) ) : ?>
            <div class="swiper courses-swiper mt-4">
                <div class="swiper-wrapper">
                    <?php while ( $featured_courses->have_posts() ) : $featured_courses->the_post(); ?>
                        <div class="swiper-slide">
                            <?php echo hubskills_course_card( $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
                <div class="swiper-pagination courses-pagination"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>
        <?php else : wp_reset_postdata(); ?>
            <div class="empty-state">
                <i class="fas fa-graduation-cap"></i>
                <p><?php _e( 'Cadastre os primeiros cursos para exibi-los aqui.', 'hubskills' ); ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="testimonials-section">
    <div class="container-xl">
        <div class="section-header" data-aos="fade-up">
            <h2><?php _e( 'O que nossos alunos', 'hubskills' ); ?> <span style="color:var(--primary)"><?php _e( 'dizem', 'hubskills' ); ?></span></h2>
        </div>

        <div class="swiper testimonials-swiper mt-4">
            <div class="swiper-wrapper">
                <?php
                $testimonials = [
                    [ 'name' => 'Ana Silva', 'role' => 'Desenvolvedora Frontend', 'text' => 'A plataforma transformou minha carreira. Os cursos são muito práticos e os certificados abriram portas que eu nem imaginava.', 'rating' => 5, 'initials' => 'AS', 'color' => '#007BFF' ],
                    [ 'name' => 'Carlos Mendes', 'role' => 'Full Stack Developer', 'text' => 'Conteúdo excelente e muito bem organizado. O sistema de gamificação me manteve motivado durante toda a jornada.', 'rating' => 5, 'initials' => 'CM', 'color' => '#0056B3' ],
                    [ 'name' => 'Marina Costa', 'role' => 'UX Designer', 'text' => 'Fiz 5 cursos aqui e todos superaram minhas expectativas. A qualidade das videoaulas é de altíssimo nível.', 'rating' => 5, 'initials' => 'MC', 'color' => '#3399FF' ],
                ];
                foreach ( $testimonials as $t ) :
                    ?>
                    <div class="swiper-slide">
                        <article class="testimonial-card">
                            <div class="test-stars">
                                <?php for ( $i = 1; $i <= 5; $i++ ) echo $i <= $t['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; // phpcs:ignore ?>
                            </div>
                            <p class="test-text">"<?php echo esc_html( $t['text'] ); ?>"</p>
                            <div class="test-author">
                                <div class="test-avatar" style="background: <?php echo esc_attr( $t['color'] ); ?>12; color: <?php echo esc_attr( $t['color'] ); ?>">
                                    <?php echo esc_html( $t['initials'] ); ?>
                                </div>
                                <div>
                                    <div class="test-name"><?php echo esc_html( $t['name'] ); ?></div>
                                    <div class="test-role"><?php echo esc_html( $t['role'] ); ?></div>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination test-pagination"></div>
        </div>
    </div>
</section>

<section class="cta-section">
    <div class="container-xl">
        <div data-aos="zoom-in">
            <h2 class="cta-title"><?php _e( 'Pronto para começar sua jornada?', 'hubskills' ); ?></h2>
            <p class="cta-subtitle"><?php _e( 'Explore os cursos disponíveis e avance no seu ritmo.', 'hubskills' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/cursos/' ) ); ?>" class="btn btn-yellow btn-lg">
                <?php _e( 'Explorar Cursos', 'hubskills' ); ?>
            </a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
