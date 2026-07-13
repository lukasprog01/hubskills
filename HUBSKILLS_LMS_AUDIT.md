# Auditoria HubSkills LMS

## Corrigido nesta rodada

- Home do tema ajustada para seguir `hubskills_preview_p1.png`: banner azul, cabecalho claro, hero com imagem, categorias com fotos e beneficios antes dos demais blocos.
- Assets visuais locais criados em `hubskills-theme/hubskills-theme/assets/images`.
- Bug critico de ativacao corrigido: o plugin chamava `hs_create_roles()`, funcao inexistente.
- Permissoes de instrutor e administrador sincronizadas para gerenciar cursos e quizzes sem depender de capacidades genericas de posts.
- Matricula direta em curso pago bloqueada; cursos pagos passam pelo fluxo de pagamento.
- Parsing de preco corrigido para valores como `R$ 97,00`.
- Reembolso via AJAX restrito a administradores.
- Progresso de aula protegido contra IDs falsos; a aula precisa existir no curriculo e estar liberada.
- Quizzes deixam de ser publicos via AJAX e passam a exigir login/matricula.
- Anotacoes de aula passam a validar matricula e aula antes de salvar ou ler do servidor.
- Avaliacoes restritas a alunos matriculados.
- REST API com limite de paginacao e protecao contra cursos nao publicados.
- Segredos de gateway passam a ser criptografados antes de salvar.
- Rate limit de login passa a usar configuracoes do painel e deixa log persistente de IPs bloqueados.
- `xmlrpc.php` desativado via filtro.
- Chaves/salts do `wp-config.php` substituidas por valores unicos locais.

## Corrigido na rodada de 09/06/2026

- Builder visual de curriculo/quizzes no wp-admin (drag-and-drop de modulos, aulas e questoes; JSON segue disponivel no modo avancado; sanitizacao centralizada no save).
- Midia local protegida: URLs de arquivos em uploads trocadas por URLs assinadas (HMAC + expiracao 6h) servidas por proxy que valida login, assinatura e matricula, com allowlist de extensoes e HTTP Range. Aulas legadas sem video_provider inferem o provider pela URL.
- Workflow de reembolso: aluno solicita em "Meus pedidos" no perfil (janela configuravel, padrao 7 dias - CDC art. 49); admin aprova/nega na pagina de Pedidos; notificacoes por e-mail e audit log.
- LGPD: consentimento obrigatorio no cadastro (data + versao da politica), exportador e apagador integrados as ferramentas nativas do WP, retencao documentada (pedidos/certificados), texto sugerido de politica de privacidade.
- Suite de testes automatizados: 39 testes em tests/ (rodar com `php tests/run-tests.php`) cobrindo preco, sanitizacao de curriculo/quiz, midia assinada, path traversal, elegibilidade de reembolso e embeds.
- CDNs eliminados: 15 bibliotecas + Google Fonts (28 woff2) servidas localmente de assets/vendor.

## Corrigido na rodada de 10/06/2026

- Conflito do hook AJAX `hs_request_refund` resolvido: handler legado removido de payments.php; vale apenas o fluxo de refunds.php (solicitacao do aluno + aprovacao do admin, janela CDC).
- Sistema de licencas das Extensoes implementado: chave por modulo (formato HSLMS-MODULO-SERIAL-CHECK com checksum sha256), ativacao/desativacao na propria tela com nonce + manage_options, audit log, options `hs_lms_licenses`/`hs_lms_active_extensions` gravadas de verdade, helper `hs_lms_is_extension_active()` para os modulos e filtro `hs_lms_validate_license` para validacao remota futura. Gerador de chaves para a loja: `hs_lms_generate_license_key()`. Testes em tests/test-license.php (18 casos).
- Internacionalizacao: pasta languages/ criada com .pot (502 strings) e traducoes completas .po/.mo para en_US, en_GB, es_ES, es_MX, es_AR, es_CO e es_CL (mercados mais provaveis de compra; pt_BR/pt_PT ja atendidos pelas strings-fonte). Scripts de build em tests/extract-strings.php, tests/build-langs.php e tests/verify-mo.php.

- Suporte a players de video com DRM: novo provider `drm` aceita links de Panda Video, VdoCipher, Bunny Stream, Cloudflare Stream, Wistia, SproutVideo e api.video (allowlist por dominio/subdominio, extensivel via filtro `hs_lms_drm_embed_hosts`). Iframe com `allow="encrypted-media"` (EME/Widevine/FairPlay) e `referrerpolicy="origin"` (restricao de dominio dos players). Opcao disponivel no builder do wp-admin e no painel do instrutor; aulas legadas com URL desses hosts sao inferidas automaticamente. 10 testes novos na suite (49 no total).

## Implementado na rodada de 12/06/2026 (v1.1.0)

- API de aulas por IA (includes/ai-lessons-api.php): token individual por tutor gerado/revogado no proprio perfil (hash sha256 no banco, exibido uma unica vez), rotas REST `hubskills/v1/ai/*` (me, courses, criar/editar aula). Modo seguro: so cursos com `_hs_instructor_id` do dono do token, lista branca de campos, nunca publica/apaga, rate limit 30 escritas/h, auditoria de toda escrita, sanitizacao via `hs_lms_sanitize_curriculum()`.
- Backup automatico AWS S3 (includes/backup-aws.php): assinatura SigV4 nativa sobre `wp_remote_request` (sem SDK; compativel com B2/Spaces/MinIO via endpoint custom), dump SQL via `$wpdb` + settings do LMS em zip protegido, agendamento inteligente (diario 3h, so envia se o fingerprint do conteudo mudou), retencao automatica no bucket (ListObjectsV2 + DELETE), tela admin com testar conexao/backup agora/historico.
- Exportacao WeWeb (includes/export-weweb.php): pacote zip com colecoes JSON normalizadas (courses+curriculo, quizzes, users+meta LMS, enrollments achatadas, orders, settings), dump SQL opcional, hashes de senha opcionais, manifest e guia LEIA-ME-WEWEB.md (caminhos Xano/Supabase/transicao headless). Endpoints REST paginados `hubskills/v1/export/{collection}` com chave `X-HS-Export-Key` para o WeWeb consumir o site ao vivo.
- Protecao de codigo (includes/code-guard.php): manifesto sha256 de todos os arquivos do plugin (exceto languages/), verificacao diaria via cron + sob demanda, aviso no admin + e-mail (1x/dia) + audit log em violacao, bloqueio do editor de plugins/temas via `map_meta_cap` (equivale a DISALLOW_FILE_EDIT), rebuild automatico do manifesto na troca de versao, tela de status com diff.
- Versao unificada: header e constante agora em 1.1.0 (resolvida a divergencia 1.0.0/1.0.1 que estava pendente).

## Pendente (adiado a pedido)

- Tela de Configuracoes sem efeito: `hs_get_option()` nunca e chamada; XP do quiz (200/50) e nota minima 70 seguem fixos no codigo.

## Ainda falta para producao

- Configurar HTTPS real, dominio final, SMTP transacional e backups.
- Configurar e testar credenciais reais de Stripe/PayPal/Mercado Pago/PagSeguro/PIX.
- Validar webhooks em ambiente publico com logs e retentativas.
- Criar conteudo real, categorias/termos e menu final no WordPress ativo.
- (Opcional) Estornar automaticamente no gateway ao aprovar reembolso - hoje o estorno financeiro e manual no painel do gateway.
