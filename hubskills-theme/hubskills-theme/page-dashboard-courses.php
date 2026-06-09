<?php
/**
 * Template Name: Cursos (Dashboard)
 * Catálogo de cursos dentro do dashboard — requer login.
 * A página pública /cursos/ continua disponível para visitantes.
 */
if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}
get_header();

$user_id     = get_current_user_id();
$enrollments = get_user_meta( $user_id, 'hs_enrollments', true ) ?: [];
$enrolled_ids = array_map( 'intval', array_keys( (array) $enrollments ) );

$paged    = max( 1, (int) ( $_GET['pg'] ?? 1 ) );
$search   = sanitize_text_field( $_GET['course_search'] ?? '' );
$category = sanitize_text_field( $_GET['category'] ?? '' );
$level    = sanitize_text_field( $_GET['level'] ?? '' );

// ── Meus cursos (matriculados) ──
$my_courses = $enrolled_ids ? get_posts( [
    'post_type'      => 'hubskills_course',
    'post__in'       => $enrolled_ids,
    'posts_per_page' => -1,
    'orderby'        => 'post__in',
] ) : [];

// ── Catálogo (todos publicados, com filtros) ──
$args = [
    'post_type'      => 'hubskills_course',
    'posts_per_page' => 9,
    'paged'          => $paged,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
];
if ( $search ) $args['s'] = $search;
if ( $category ) $args['tax_query'] = [[ 'taxonomy' => 'course_category', 'field' => 'slug', 'terms' => $category ]];
if ( $level ) $args['meta_query'] = [[ 'key' => '_hs_level', 'value' => $level, 'compare' => '=' ]];

$query      = new WP_Query( $args );
$categories = get_terms( [ 'taxonomy' => 'course_category', 'hide_empty' => false ] );

$filter_args = [];
if ( $search ) $filter_args['course_search'] = $search;
if ( $level )  $filter_args['level'] = $level;
?>

<div class="courses-app-page" data-page="dashboard-courses">

    <!-- ── Meus cursos ─────────────────────────────────── -->
    <div class="dash-section-head" data-aos="fade-up">
        <div>
            <h2 style="margin:0"><i class="fas fa-book-open me-2" style="color:var(--primary)"></i><?php _e( 'Meus cursos', 'hubskills' ); ?></h2>
            <p style="color:var(--text-muted);margin:4px 0 0;font-size:14px"><?php _e( 'Continue de onde parou.', 'hubskills' ); ?></p>
        </div>
        <span class="hs-count-pill"><?php echo count( $my_courses ); ?> <?php echo esc_html( _n( 'curso', 'cursos', count( $my_courses ), 'hubskills' ) ); ?></span>
    </div>

    <?php if ( $my_courses ) : ?>
    <div class="courses-grid" style="margin-bottom:48px">
        <?php foreach ( $my_courses as $post ) : setup_postdata( $post );
            $enroll_data = function_exists( 'hs_get_enrollment' ) ? hs_get_enrollment( $user_id, $post->ID ) : ( $enrollments[ $post->ID ] ?? [] );
            echo hubskills_course_card( $post, true, (int) ( $enroll_data['progress'] ?? 0 ) ); // phpcs:ignore
        endforeach; wp_reset_postdata(); ?>
    </div>
    <?php else : ?>
    <div class="glass-card text-center" style="padding:32px;margin-bottom:48px" data-aos="fade-up">
        <div style="font-size:42px">🎓</div>
        <h4><?php _e( 'Você ainda não está matriculado em nenhum curso', 'hubskills' ); ?></h4>
        <p style="color:var(--text-muted);margin-bottom:0"><?php _e( 'Explore o catálogo abaixo e comece agora mesmo!', 'hubskills' ); ?></p>
    </div>
    <?php endif; ?>

    <!-- ── Explorar catálogo ───────────────────────────── -->
    <div class="dash-section-head" data-aos="fade-up">
        <div>
            <h2 style="margin:0"><i class="fas fa-compass me-2" style="color:var(--primary)"></i><?php _e( 'Explorar cursos', 'hubskills' ); ?></h2>
            <p style="color:var(--text-muted);margin:4px 0 0;font-size:14px">
                <?php printf( esc_html( _n( '%s curso disponível', '%s cursos disponíveis', $query->found_posts, 'hubskills' ) ), number_format_i18n( $query->found_posts ) ); ?>
            </p>
        </div>
    </div>

    <div class="course-search-panel" data-aos="fade-up" style="margin-bottom:24px">
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

        <?php if ( ! is_wp_error( $categories ) && $categories ) : ?>
        <div class="course-filter-row">
            <span class="course-filter-label"><?php _e( 'Categorias', 'hubskills' ); ?></span>
            <div class="filter-chips">
                <a href="<?php echo esc_url( add_query_arg( $filter_args, get_permalink() ) ); ?>"
                   class="filter-chip <?php echo ! $category ? 'active' : ''; ?>"><?php _e( 'Todos', 'hubskills' ); ?></a>
                <?php foreach ( $categories as $cat ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $filter_args, [ 'category' => $cat->slug ] ), get_permalink() ) ); ?>"
                   class="filter-chip <?php echo $category === $cat->slug ? 'active' : ''; ?>"><?php echo esc_html( $cat->name ); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ( $query->have_posts() ) : ?>
    <div class="courses-grid" id="coursesGrid">
        <?php while ( $query->have_posts() ) : $query->the_post();
            $enroll_data = function_exists( 'hs_get_enrollment' ) ? hs_get_enrollment( $user_id, get_the_ID() ) : ( $enrollments[ get_the_ID() ] ?? [] );
            echo hubskills_course_card( $post, ! empty( $enroll_data ), (int) ( $enroll_data['progress'] ?? 0 ) ); // phpcs:ignore
        endwhile; wp_reset_postdata(); ?>
    </div>

    <?php if ( $query->max_num_pages > 1 ) : ?>
    <div class="pagination-wrap" data-aos="fade-up">
        <?php for ( $i = 1; $i <= $query->max_num_pages; $i++ ) : ?>
            <a href="<?php echo esc_url( add_query_arg( array_merge( $filter_args, $category ? [ 'category' => $category ] : [], [ 'pg' => $i ] ), get_permalink() ) ); ?>"
               class="page-numbers <?php echo $i === $paged ? 'current' : ''; ?>"><?php echo (int) $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <div class="empty-state py-5 text-center">
        <div class="empty-icon"><i class="fas fa-search"></i></div>
        <h3><?php _e( 'Nenhum curso encontrado', 'hubskills' ); ?></h3>
        <p><?php _e( 'Tente uma busca diferente ou explore todas as categorias.', 'hubskills' ); ?></p>
        <a href="<?php echo esc_url( get_permalink() ); ?>" class="btn btn-gradient"><?php _e( 'Limpar filtros', 'hubskills' ); ?></a>
    </div>
    <?php endif; ?>

</div>

<?php get_footer(); ?>
