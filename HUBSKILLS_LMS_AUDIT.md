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

## Ainda falta para producao

- Configurar HTTPS real, dominio final, SMTP transacional e backups.
- Configurar e testar credenciais reais de Stripe/PayPal/Mercado Pago/PagSeguro/PIX.
- Validar webhooks em ambiente publico com logs e retentativas.
- Definir politica de reembolso e workflow de solicitacao para alunos.
- Criar builder visual de curriculo/quizzes; hoje o admin ainda depende de JSON em metabox.
- Proteger arquivos e videos privados; URLs diretas de video ainda precisam de estrategia de media privada/CDN.
- Criar testes automatizados para matricula, pagamento, progresso, certificados, quizzes e permissoes.
- Revisar LGPD: politica de privacidade, consentimento, exportacao/exclusao de dados e retencao.
- Trocar dependencias de CDN por assets locais ou configurar CSP/SRI quando o ambiente final exigir mais controle.
- Criar conteudo real, categorias/termos e menu final no WordPress ativo.
