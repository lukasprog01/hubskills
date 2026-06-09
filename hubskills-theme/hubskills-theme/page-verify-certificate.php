<?php
/**
 * Template Name: Verificar Certificado
 */
get_header(); ?>

<div class="hs-public-page">
    <div class="container-xl">
        <div class="certificate-wrap">
            <div class="section-header" data-aos="fade-up">
                <h2><?php _e( 'Verificar Certificado', 'hubskills' ); ?></h2>
                <p class="section-subtitle"><?php _e( 'Digite o código do certificado para verificar sua autenticidade.', 'hubskills' ); ?></p>
            </div>

            <div class="card-plain" style="max-width:500px;margin:0 auto" data-aos="fade-up">
                <div class="card-plain-body" style="padding:1.5rem">
                    <form id="hs-cert-verify-form">
                        <div class="mb-3">
                            <label for="cert_id" class="form-label" style="font-weight:600;color:var(--text-primary)">
                                <?php _e( 'Código do Certificado', 'hubskills' ); ?>
                            </label>
                            <input type="text" id="cert_id" class="form-control" placeholder="HS-XXXXXXXXXXXX"
                                   value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['cert_id'] ?? '' ) ) ); ?>"
                                   style="border:1px solid var(--border);border-radius:var(--radius-sm);padding:.6rem .9rem;font-family:monospace;font-size:1rem;width:100%"
                                   required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%">
                            <i class="fas fa-search"></i> <?php _e( 'Verificar', 'hubskills' ); ?>
                        </button>
                    </form>

                    <div id="hs-cert-result" style="display:none;margin-top:1.5rem"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function hsCertEscapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, function(char) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
}

document.getElementById('hs-cert-verify-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const certId = document.getElementById('cert_id').value.trim();
    const result = document.getElementById('hs-cert-result');

    if (!certId) {
        result.style.display = 'block';
        result.innerHTML = '<div class="alert alert-danger">' + '<?php _e( 'Digite um código de certificado.', 'hubskills' ); ?>' + '</div>';
        return;
    }

    result.innerHTML = '<p style="text-align:center"><i class="fas fa-spinner fa-spin"></i> <?php _e( 'Verificando...', 'hubskills' ); ?></p>';
    result.style.display = 'block';

    fetch('<?php echo esc_url_raw( rest_url( 'hubskills/v1/verify-certificate/' ) ); ?>' + encodeURIComponent(certId))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const cert = data.data || {};
                const extra = [
                    cert.workload ? `<?php _e( 'Carga horária:', 'hubskills' ); ?> ${hsCertEscapeHTML(cert.workload)}` : '',
                    cert.grade ? `<?php _e( 'Aproveitamento:', 'hubskills' ); ?> ${hsCertEscapeHTML(cert.grade)}` : '',
                    cert.instructor ? `<?php _e( 'Instrutor:', 'hubskills' ); ?> ${hsCertEscapeHTML(cert.instructor)}` : '',
                    cert.issued_at ? `<?php _e( 'Emitido em:', 'hubskills' ); ?> ${hsCertEscapeHTML(cert.issued_at)}` : ''
                ].filter(Boolean);
                result.innerHTML = `
                    <div style="background:#f0fff4;border:1px solid #10B981;border-radius:12px;padding:1.5rem;text-align:center">
                        <div style="font-size:3rem;margin-bottom:.5rem">&#10004;</div>
                        <h3 style="color:#10B981;margin-bottom:.5rem"><?php _e( 'Certificado Válido', 'hubskills' ); ?></h3>
                        <p style="color:var(--text-secondary);margin-bottom:.5rem">
                            <strong>${hsCertEscapeHTML(cert.student)}</strong>
                        </p>
                        <p style="color:var(--text-secondary);margin-bottom:.5rem">
                            <?php _e( 'Concluiu o curso:', 'hubskills' ); ?>
                            <strong>${hsCertEscapeHTML(cert.course)}</strong>
                        </p>
                        <p style="color:var(--text-muted);font-size:.85rem">
                            <?php _e( 'Data de conclusão:', 'hubskills' ); ?> ${hsCertEscapeHTML(cert.date)}
                        </p>
                        ${extra.length ? `<div style="display:grid;gap:4px;margin:.75rem 0;color:var(--text-muted);font-size:.85rem">${extra.map(item => `<span>${item}</span>`).join('')}</div>` : ''}
                        <p style="color:var(--text-muted);font-size:.8rem;font-family:monospace">
                            ID: ${hsCertEscapeHTML(cert.cert_id)}
                        </p>
                    </div>
                `;
            } else {
                const message = data.data?.message || '<?php _e( 'O código informado não corresponde a nenhum certificado emitido.', 'hubskills' ); ?>';
                result.innerHTML = `
                    <div style="background:#fff5f5;border:1px solid #EF4444;border-radius:12px;padding:1.5rem;text-align:center">
                        <div style="font-size:3rem;margin-bottom:.5rem">&#10008;</div>
                        <h3 style="color:#EF4444;margin-bottom:.5rem"><?php _e( 'Certificado não encontrado', 'hubskills' ); ?></h3>
                        <p style="color:var(--text-muted)">${hsCertEscapeHTML(message)}</p>
                    </div>
                `;
            }
        })
        .catch(() => {
            result.innerHTML = '<div class="alert alert-danger"><?php _e( 'Erro de conexão. Tente novamente.', 'hubskills' ); ?></div>';
        });
});

if (document.getElementById('cert_id').value.trim()) {
    document.getElementById('hs-cert-verify-form').dispatchEvent(new Event('submit'));
}
</script>

<?php get_footer(); ?>
