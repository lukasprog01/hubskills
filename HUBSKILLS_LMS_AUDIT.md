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

## Ainda falta para producao

- Configurar HTTPS real, dominio final, SMTP transacional e backups.
- Configurar e testar credenciais reais de Stripe/PayPal/Mercado Pago/PagSeguro/PIX.
- Validar webhooks em ambiente publico com logs e retentativas.
- Criar conteudo real, categorias/termos e menu final no WordPress ativo.
- (Opcional) Estornar automaticamente no gateway ao aprovar reembolso - hoje o estorno financeiro e manual no painel do gateway.
