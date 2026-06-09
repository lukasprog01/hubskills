<?php
/**
 * Template Name: Cursos
 * Accessible to guests — no login required.
 */
get_header();

$paged    = (int) get_query_var( 'paged', 1 );
$search   = sanitize_text_field( $_GET['course_search'] ?? '' );
$category = sanitize_text_field( $_GET['category'] ?? '' );
$level    = sanitize_text_field( $_GET['level'] ?? '' );

$args = [
    'post_type'      => 'hubskills_course',
    'posts_per_page' => 9,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
];

if ( $search ) $args['s'] = $search;

if ( $category ) {
    $args['tax_query'] = [[ 'taxonomy' => 'course_category', 'field' => 'slug', 'terms' => $category ]];
}
if ( $level ) {
    $args['meta_query'] = [[ 'key' => '_hs_level', 'value' => $level, 'compare' => '=' ]];
}

$query       = new WP_Query( $args );
$user_id     = get_current_user_id();
$enrollments = $user_id ? ( get_user_meta( $user_id, 'hs_enrollments', true ) ?: [] ) : [];
$categories  = get_terms( [ 'taxonomy' => 'course_category', 'hide_empty' => false ] );
$filter_args = [];
if ( $search ) {
    $filter_args['course_search'] = $search;
}
if ( $level ) {
    $filter_args['level'] = $level;
}
?>

<div class="hs-public-page courses-public-page">
    <div class="container-xl">

        <div class="courses-hero" data-aos="fade-down">
            <div class="courses-hero-copy">
                <span class="courses-eyebrow"><?php _e( 'Todos os cursos', 'hubskills' ); ?></span>
                <h1><?php _e( 'Encontre o curso certo para sua próxima habilidade', 'hubskills' ); ?></h1>
                <p><?php _e( 'Busque por tema, filtre por nível e avance no seu ritmo com conteúdos organizados para aprendizado prático.', 'hubskills' ); ?></p>
            </div>
            <div class="courses-hero-stat">
                <strong><?php echo number_format_i18n( $query->found_posts ); ?></strong>
                <span>
                    <?php
                    echo esc_html(
                        _n( 'curso disponível', 'cursos disponíveis', $query->found_posts, 'hubskills' )
                    );
                    ?>
                </span>
            </div>
        </div>

        <div class="course-search-panel" data-aos="fade-up">
            <form method="GET" class="course-search-form">
                <?php if ( $category ) : ?>
                <input type="hidden" name="category" value="<?php echo esc_attr( $category ); ?>">
                <?php endif; ?>

                <label class="course-search-main">
                    <span class="course-search-icon"><i class="fas fa-search"></i></span>
                    <input type="text" name="course_search" value="<?php echo esc_attr( $search ); ?>"
                           placeholder="<?php esc_attr_e( 'Buscar por curso, tema ou habilidade', 'hubskills' ); ?>">
                </label>

                <label class="course-level-field">
                    <span><?php _e( 'Nível', 'hubskills' ); ?></span>
                    <select name="level">
                        <option value=""><?php _e( 'Todos', 'hubskills' ); ?></option>
                        <option value="Iniciante"     <?php selected( $level, 'Iniciante' ); ?>><?php _e( 'Iniciante', 'hubskills' ); ?></option>
                        <option value="Intermediário" <?php selected( $level, 'Intermediário' ); ?>><?php _e( 'Intermediário', 'hubskills' ); ?></option>
                        <option value="Avançado"      <?php selected( $level, 'Avançado' ); ?>><?php _e( 'Avançado', 'hubskills' ); ?></option>
                    </select>
                </label>

                <button type="submit" class="btn btn-gradient course-search-submit">
                    <i class="fas fa-magnifying-glass"></i><?php _e( 'Buscar', 'hubskills' ); ?>
                </button>
            </form>

            <div class="course-filter-row">
                <span class="course-filter-label"><?php _e( 'Categorias', 'hubskills' ); ?></span>
                <div class="filter-chips">
                    <a href="<?php echo esc_url( add_query_arg( $filter_args, get_permalink() ) ); ?>"
                       class="filter-chip <?php echo ! $category ? 'active' : ''; ?>">
                        <?php _e( 'Todos', 'hubskills' ); ?>
                    </a>
                    <?php if ( ! is_wp_error( $categories ) ) foreach ( $categories as $cat ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array_merge( $filter_args, [ 'category' => $cat->slug ] ), get_permalink() ) ); ?>"
                       class="filter-chip <?php echo $category === $cat->slug ? 'active' : ''; ?>">
                        <?php echo esc_html( $cat->name ); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ( ! is_user_logged_in() ) : ?>
            <div class="course-register-note">
                <i class="fas fa-circle-info"></i>
                <span><?php _e( 'Cadastre-se grátis para se matricular e acompanhar seu progresso.', 'hubskills' ); ?></span>
                <a href="<?php echo esc_url( wp_registration_url() ); ?>"><?php _e( 'Criar conta', 'hubskills' ); ?></a>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Course Grid ───────────────────────────────── -->
        <?php if ( $query->have_posts() ) : ?>
        <div class="courses-grid" id="coursesGrid">
            <?php while ( $query->have_posts() ) : $query->the_post();
                $enroll_data = function_exists( 'hs_get_enrollment' ) ? hs_get_enrollment( $user_id, get_the_ID() ) : ( $enrollments[ get_the_ID() ] ?? [] );
                $enrolled = ! empty( $enroll_data );
                $progress = (int)( $enroll_data['progress'] ?? 0 );
                echo hubskills_course_card( $post, $enrolled, $progress ); // phpcs:ignore
            endwhile; wp_reset_postdata(); ?>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrap" data-aos="fade-up">
            <?php
            echo paginate_links( [
                'total'     => $query->max_num_pages,
                'current'   => $paged,
                'prev_text' => '<i class="fas fa-chevron-left"></i>',
                'next_text' => '<i class="fas fa-chevron-right"></i>',
                'class'     => 'hs-pagination',
            ] );
            ?>
        </div>

        <?php else : ?>
        <div class="empty-state py-5 text-center">
            <div class="empty-icon"><i class="fas fa-search"></i></div>
            <h3><?php _e( 'Nenhum curso encontrado', 'hubskills' ); ?></h3>
            <p><?php _e( 'Tente uma busca diferente ou explore todas as categorias.', 'hubskills' ); ?></p>
            <a href="<?php echo esc_url( get_permalink() ); ?>" class="btn btn-gradient">
                <?php _e( 'Ver todos os cursos', 'hubskills' ); ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- ── Guest CTA Banner ──────────────────────────── -->
        <?php if ( ! is_user_logged_in() ) : ?>
        <div class="hs-guest-cta" data-aos="fade-up">
            <div class="hs-guest-cta-inner">
                <div>
                    <h3><?php _e( 'Pronto para começar a aprender?', 'hubskills' ); ?></h3>
                    <p><?php _e( 'Crie sua conta grátis e acesse centenas de cursos com certificado.', 'hubskills' ); ?></p>
                </div>
                <div class="hs-guest-cta-btns">
                    <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="btn btn-gradient">
                        <i class="fas fa-user-plus me-2"></i><?php _e( 'Cadastrar Grátis', 'hubskills' ); ?>
                    </a>
                    <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="btn btn-glass">
                        <?php _e( 'Entrar', 'hubskills' ); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.container-xl -->
</div><!-- /.hs-public-page -->

<?php get_footer(); ?>
