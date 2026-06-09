<?php get_header(); ?>

<div class="container-xl py-5">
    <?php if ( have_posts() ) : ?>
    <div class="row g-4">
        <?php while ( have_posts() ) : the_post(); ?>
        <div class="col-md-6 col-lg-4">
            <div class="card-glass">
                <?php if ( has_post_thumbnail() ) : ?>
                <div class="card-thumb"><?php the_post_thumbnail('course-thumb'); ?></div>
                <?php endif; ?>
                <div class="p-4">
                    <h2 class="h5"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <p><?php the_excerpt(); ?></p>
                    <a href="<?php the_permalink(); ?>" class="btn btn-gradient btn-sm"><?php _e('Ler mais', 'hubskills'); ?></a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php the_posts_pagination(); ?>
    <?php else : ?>
    <p class="text-muted text-center py-5"><?php _e('Nenhum conteúdo encontrado.', 'hubskills'); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
