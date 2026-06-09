<?php if ( function_exists( 'hubskills_uses_app_layout' ) && hubskills_uses_app_layout() ) : ?>
        </div><!-- .page-body -->
    </div><!-- .main-content -->
</div><!-- .app-wrapper -->

<?php else : ?>
<!-- ══════════════════ PUBLIC FOOTER ══════════════════ -->
<footer class="site-footer">
    <div class="container-xl">
        <div class="row g-4 footer-top">
            <div class="col-lg-4">
                <a class="footer-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                    <img class="footer-brand-logo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/hubskills-logo-footer.svg' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                </a>
                <p class="footer-desc"><?php echo esc_html( get_bloginfo('description') ?: 'Aprenda habilidades do futuro com os melhores profissionais do mercado.' ); ?></p>
                <div class="social-links">
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                    <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    <a href="#" aria-label="GitHub"><i class="fab fa-github"></i></a>
                    <a href="#" aria-label="Discord"><i class="fab fa-discord"></i></a>
                </div>
            </div>

            <div class="col-lg-2 col-6">
                <h5 class="footer-heading"><?php _e( 'Plataforma', 'hubskills' ); ?></h5>
                <ul class="footer-links">
                    <li><a href="<?php echo esc_url( home_url('/cursos/') ); ?>"><?php _e( 'Cursos', 'hubskills' ); ?></a></li>
                    <li><a href="<?php echo esc_url( home_url('/dashboard/') ); ?>"><?php _e( 'Dashboard', 'hubskills' ); ?></a></li>
                    <li><a href="<?php echo esc_url( home_url('/certificados/') ); ?>"><?php _e( 'Certificados', 'hubskills' ); ?></a></li>
                    <li><a href="<?php echo esc_url( home_url('/perfil/') ); ?>"><?php _e( 'Perfil', 'hubskills' ); ?></a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-6">
                <h5 class="footer-heading"><?php _e( 'Categorias', 'hubskills' ); ?></h5>
                <ul class="footer-links">
                    <li><a href="#"><?php _e( 'Desenvolvimento Web', 'hubskills' ); ?></a></li>
                    <li><a href="#"><?php _e( 'Design', 'hubskills' ); ?></a></li>
                    <li><a href="#"><?php _e( 'Data Science', 'hubskills' ); ?></a></li>
                    <li><a href="#"><?php _e( 'DevOps', 'hubskills' ); ?></a></li>
                </ul>
            </div>

            <div class="col-lg-4">
                <h5 class="footer-heading"><?php _e( 'Newsletter', 'hubskills' ); ?></h5>
                <p class="footer-desc"><?php _e( 'Receba novos cursos e dicas diretamente no seu e-mail.', 'hubskills' ); ?></p>
                <form class="footer-newsletter" onsubmit="return false;">
                    <input type="email" placeholder="<?php _e( 'seu@email.com', 'hubskills' ); ?>" required>
                    <button type="submit"><i class="fas fa-arrow-right"></i></button>
                </form>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. <?php _e( 'Todos os direitos reservados.', 'hubskills' ); ?></p>
            <div class="footer-bottom-links">
                <a href="#"><?php _e( 'Privacidade', 'hubskills' ); ?></a>
                <a href="#"><?php _e( 'Termos', 'hubskills' ); ?></a>
                <a href="#"><?php _e( 'Suporte', 'hubskills' ); ?></a>
            </div>
        </div>
    </div>
</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
