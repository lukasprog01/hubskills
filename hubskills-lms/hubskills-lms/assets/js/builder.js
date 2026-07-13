/* HubSkills LMS - Builder visual de curriculo e quiz (wp-admin) */
(function($) {
    'use strict';

    function esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function uid() {
        return Date.now() + Math.floor(Math.random() * 1000);
    }

    /* ════════════════════════════════════════════════════════
       CURRICULUM BUILDER
       ════════════════════════════════════════════════════════ */
    var $cb = $('#hs-curriculum-builder');
    if ($cb.length) {
        var QUIZZES = [];
        var ASSETS  = [];
        try { QUIZZES = JSON.parse($cb.attr('data-quizzes') || '[]'); } catch (e) {}
        try { ASSETS  = JSON.parse($cb.attr('data-assets')  || '[]'); } catch (e) {}

        var $jsonField = $('#hs_curriculum');
        var curriculum = [];
        try { curriculum = JSON.parse($jsonField.val() || '[]'); } catch (e) { curriculum = []; }
        if (!Array.isArray(curriculum)) curriculum = [];

        var PROVIDERS = [
            { v: 'none',     l: 'Sem vídeo' },
            { v: 'secure',   l: 'Vídeo protegido (upload)' },
            { v: 'youtube',  l: 'YouTube' },
            { v: 'vimeo',    l: 'Vimeo' },
            { v: 'drm',      l: 'Player DRM (Panda, VdoCipher…)' },
            { v: 'external', l: 'URL externa' }
        ];
        var TYPES = [
            { v: 'video',    l: '🎬 Vídeo' },
            { v: 'text',     l: '📄 Texto' },
            { v: 'quiz',     l: '❓ Quiz' },
            { v: 'resource', l: '📎 Material' }
        ];

        function selectHtml(cls, options, current, placeholder) {
            var html = '<select class="' + cls + '">';
            if (placeholder) html += '<option value="">' + esc(placeholder) + '</option>';
            options.forEach(function(o) {
                html += '<option value="' + esc(o.v) + '"' + (String(o.v) === String(current) ? ' selected' : '') + '>' + esc(o.l) + '</option>';
            });
            return html + '</select>';
        }

        function lessonHtml(lesson) {
            var l = $.extend({
                id: uid(), title: '', duration: '', type: 'video', description: '',
                video_provider: 'none', video_url: '', secure_asset_id: '', quiz_id: 0
            }, lesson || {});

            var quizOpts  = QUIZZES.map(function(q) { return { v: q.id, l: q.title || ('Quiz #' + q.id) }; });
            var assetOpts = ASSETS.filter(function(a) { return a.type === 'video'; })
                                  .map(function(a) { return { v: a.id, l: a.name }; });

            return '<div class="hs-lesson" data-id="' + esc(l.id) + '">' +
                '<div class="hs-lesson-row">' +
                    '<span class="hs-drag dashicons dashicons-menu" title="Arrastar"></span>' +
                    '<input type="text" class="hs-l-title" placeholder="Título da aula" value="' + esc(l.title) + '">' +
                    '<input type="text" class="hs-l-duration" placeholder="10min" value="' + esc(l.duration) + '">' +
                    selectHtml('hs-l-type', TYPES, l.type) +
                    '<button type="button" class="button-link hs-l-expand" title="Detalhes"><span class="dashicons dashicons-arrow-down-alt2"></span></button>' +
                    '<button type="button" class="button-link hs-l-remove" title="Remover"><span class="dashicons dashicons-trash"></span></button>' +
                '</div>' +
                '<div class="hs-lesson-detail" style="display:none">' +
                    '<label>Descrição<textarea class="hs-l-description" rows="2">' + esc(l.description) + '</textarea></label>' +
                    '<div class="hs-detail-grid">' +
                        '<label>Fonte do vídeo' + selectHtml('hs-l-provider', PROVIDERS, l.video_provider) + '</label>' +
                        '<label class="hs-f-video-url">URL do vídeo<input type="url" class="hs-l-video-url" placeholder="https://..." value="' + esc(l.video_url) + '"></label>' +
                        '<label class="hs-f-secure-asset">Vídeo protegido' + selectHtml('hs-l-asset', assetOpts, l.secure_asset_id, assetOpts.length ? '— selecionar —' : 'Nenhum upload neste curso') + '</label>' +
                        '<label class="hs-f-quiz">Quiz vinculado' + selectHtml('hs-l-quiz', quizOpts, l.quiz_id || '', '— nenhum —') + '</label>' +
                    '</div>' +
                '</div>' +
            '</div>';
        }

        function sectionHtml(section) {
            var s = $.extend({ section: 'Novo módulo', lessons: [] }, section || {});
            var html = '<div class="hs-section">' +
                '<div class="hs-section-head">' +
                    '<span class="hs-drag dashicons dashicons-menu" title="Arrastar"></span>' +
                    '<input type="text" class="hs-s-title" value="' + esc(s.section) + '" placeholder="Nome do módulo">' +
                    '<span class="hs-s-count"></span>' +
                    '<button type="button" class="button hs-add-lesson">+ Aula</button>' +
                    '<button type="button" class="button-link hs-s-remove" title="Remover módulo"><span class="dashicons dashicons-trash"></span></button>' +
                '</div>' +
                '<div class="hs-lessons">';
            (s.lessons || []).forEach(function(l) { html += lessonHtml(l); });
            return html + '</div></div>';
        }

        function render() {
            $cb.empty();
            if (!curriculum.length) {
                $cb.append('<p class="hs-builder-empty">Nenhum módulo ainda. Clique em <strong>+ Adicionar módulo</strong> para começar.</p>');
            }
            curriculum.forEach(function(s) { $cb.append(sectionHtml(s)); });
            refreshVisibility();
            refreshCounts();
            initSortable();
            sync();
        }

        function refreshVisibility() {
            $cb.find('.hs-lesson').each(function() {
                var $l = $(this);
                var provider = $l.find('.hs-l-provider').val();
                var type = $l.find('.hs-l-type').val();
                $l.find('.hs-f-video-url').toggle(['youtube', 'vimeo', 'drm', 'external'].indexOf(provider) !== -1);
                $l.find('.hs-f-secure-asset').toggle(provider === 'secure');
                $l.find('.hs-f-quiz').toggle(type === 'quiz');
            });
        }

        function refreshCounts() {
            $cb.find('.hs-section').each(function() {
                var n = $(this).find('.hs-lesson').length;
                $(this).find('.hs-s-count').text(n + (n === 1 ? ' aula' : ' aulas'));
            });
        }

        function serialize() {
            var data = [];
            $cb.find('.hs-section').each(function() {
                var $s = $(this);
                var lessons = [];
                $s.find('.hs-lesson').each(function() {
                    var $l = $(this);
                    lessons.push({
                        id: parseInt($l.attr('data-id'), 10) || uid(),
                        title: $l.find('.hs-l-title').val(),
                        duration: $l.find('.hs-l-duration').val(),
                        type: $l.find('.hs-l-type').val(),
                        description: $l.find('.hs-l-description').val(),
                        video_provider: $l.find('.hs-l-provider').val(),
                        video_url: $l.find('.hs-l-video-url').val(),
                        secure_asset_id: $l.find('.hs-l-asset').val() || '',
                        quiz_id: parseInt($l.find('.hs-l-quiz').val(), 10) || 0
                    });
                });
                data.push({ section: $s.find('.hs-s-title').val(), lessons: lessons });
            });
            return data;
        }

        function sync() {
            $jsonField.val(JSON.stringify(serialize()));
        }

        function initSortable() {
            if (!$.fn.sortable) return;
            $cb.sortable({
                items: '> .hs-section',
                handle: '.hs-section-head .hs-drag',
                placeholder: 'hs-sort-placeholder',
                update: sync
            });
            $cb.find('.hs-lessons').sortable({
                items: '> .hs-lesson',
                handle: '.hs-drag',
                connectWith: '.hs-lessons',
                placeholder: 'hs-sort-placeholder',
                update: function() { refreshCounts(); sync(); }
            });
        }

        // Eventos
        $(document).on('click', '.hs-add-section', function() {
            $cb.find('.hs-builder-empty').remove();
            $cb.append(sectionHtml({ section: 'Módulo ' + ($cb.find('.hs-section').length + 1), lessons: [] }));
            refreshCounts(); initSortable(); sync();
        });

        $cb.on('click', '.hs-add-lesson', function() {
            var $lessons = $(this).closest('.hs-section').find('.hs-lessons');
            $lessons.append(lessonHtml(null));
            refreshVisibility(); refreshCounts(); initSortable(); sync();
        });

        $cb.on('click', '.hs-s-remove', function() {
            if (!confirm('Remover este módulo e todas as suas aulas?')) return;
            $(this).closest('.hs-section').remove();
            sync();
        });

        $cb.on('click', '.hs-l-remove', function() {
            if (!confirm('Remover esta aula? O progresso dos alunos nela será perdido.')) return;
            $(this).closest('.hs-lesson').remove();
            refreshCounts(); sync();
        });

        $cb.on('click', '.hs-l-expand', function() {
            var $detail = $(this).closest('.hs-lesson').find('.hs-lesson-detail');
            $detail.slideToggle(150);
            $(this).find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        });

        $cb.on('change input', 'input, select, textarea', function() {
            refreshVisibility(); sync();
        });

        // Sincroniza antes de salvar o post
        $('#post').on('submit', sync);

        // Modo avançado: re-renderiza o builder ao sair do JSON
        $jsonField.on('blur', function() {
            try {
                var parsed = JSON.parse($(this).val());
                if (Array.isArray(parsed)) { curriculum = parsed; render(); }
            } catch (e) {}
        });

        render();
    }

    /* ════════════════════════════════════════════════════════
       QUIZ BUILDER
       ════════════════════════════════════════════════════════ */
    var $qb = $('#hs-quiz-builder');
    if ($qb.length) {
        var $qField = $('#hs_quiz_questions');
        var questions = [];
        try { questions = JSON.parse($qField.val() || '[]'); } catch (e) { questions = []; }
        if (!Array.isArray(questions)) questions = [];

        function questionHtml(q, index) {
            q = $.extend({ id: uid(), text: '', options: ['', '', '', ''], correct: 0, explanation: '' }, q || {});
            var opts = (q.options && q.options.length) ? q.options : ['', '', '', ''];
            var html = '<div class="hs-question" data-id="' + esc(q.id) + '">' +
                '<div class="hs-question-head">' +
                    '<span class="hs-drag dashicons dashicons-menu" title="Arrastar"></span>' +
                    '<strong class="hs-q-number">Questão ' + (index + 1) + '</strong>' +
                    '<button type="button" class="button-link hs-q-remove" title="Remover"><span class="dashicons dashicons-trash"></span></button>' +
                '</div>' +
                '<input type="text" class="hs-q-text" placeholder="Enunciado da questão" value="' + esc(q.text) + '">' +
                '<div class="hs-q-options">';
            opts.forEach(function(opt, i) {
                html += '<label class="hs-q-option">' +
                    '<input type="radio" name="hs_correct_' + esc(q.id) + '"' + (parseInt(q.correct, 10) === i ? ' checked' : '') + ' title="Resposta correta">' +
                    '<input type="text" class="hs-q-opt-text" placeholder="Alternativa ' + String.fromCharCode(65 + i) + '" value="' + esc(opt) + '">' +
                '</label>';
            });
            html += '</div>' +
                '<label class="hs-q-explanation-label">Explicação (mostrada após responder)' +
                '<textarea class="hs-q-explanation" rows="2">' + esc(q.explanation) + '</textarea></label>' +
            '</div>';
            return html;
        }

        function renderQuiz() {
            $qb.empty();
            if (!questions.length) {
                $qb.append('<p class="hs-builder-empty">Nenhuma questão ainda. Clique em <strong>+ Adicionar questão</strong>.</p>');
            }
            questions.forEach(function(q, i) { $qb.append(questionHtml(q, i)); });
            initQuizSortable();
            syncQuiz();
        }

        function renumber() {
            $qb.find('.hs-question').each(function(i) {
                $(this).find('.hs-q-number').text('Questão ' + (i + 1));
            });
        }

        function serializeQuiz() {
            var data = [];
            $qb.find('.hs-question').each(function() {
                var $q = $(this);
                var options = [];
                var correct = 0;
                $q.find('.hs-q-option').each(function(i) {
                    options.push($(this).find('.hs-q-opt-text').val());
                    if ($(this).find('input[type=radio]').prop('checked')) correct = i;
                });
                data.push({
                    id: parseInt($q.attr('data-id'), 10) || uid(),
                    text: $q.find('.hs-q-text').val(),
                    options: options,
                    correct: correct,
                    explanation: $q.find('.hs-q-explanation').val()
                });
            });
            return data;
        }

        function syncQuiz() {
            $qField.val(JSON.stringify(serializeQuiz()));
        }

        function initQuizSortable() {
            if (!$.fn.sortable) return;
            $qb.sortable({
                items: '> .hs-question',
                handle: '.hs-drag',
                placeholder: 'hs-sort-placeholder',
                update: function() { renumber(); syncQuiz(); }
            });
        }

        $(document).on('click', '.hs-add-question', function() {
            $qb.find('.hs-builder-empty').remove();
            $qb.append(questionHtml(null, $qb.find('.hs-question').length));
            initQuizSortable(); syncQuiz();
        });

        $qb.on('click', '.hs-q-remove', function() {
            if (!confirm('Remover esta questão?')) return;
            $(this).closest('.hs-question').remove();
            renumber(); syncQuiz();
        });

        $qb.on('change input', 'input, textarea', syncQuiz);

        $('#post').on('submit', syncQuiz);

        $qField.on('blur', function() {
            try {
                var parsed = JSON.parse($(this).val());
                if (Array.isArray(parsed)) { questions = parsed; renderQuiz(); }
            } catch (e) {}
        });

        renderQuiz();
    }

    /* ── Toggle modo avançado (compartilhado) ── */
    $(document).on('click', '.hs-toggle-advanced', function() {
        $($(this).attr('data-target')).slideToggle(150);
    });

})(window.jQuery);
