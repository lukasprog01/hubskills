/* HubSkills LMS — Plugin JavaScript */

(function() {
'use strict';

const API = window.HSLMS || {};

document.addEventListener('DOMContentLoaded', initLmsDashboardTools);
document.addEventListener('DOMContentLoaded', initVideoWatermarks);
document.addEventListener('DOMContentLoaded', initCertificateActions);

// ── Load Inline Quizzes ([hubskills_quiz] shortcode) ──────
document.querySelectorAll('.hs-quiz-container[data-quiz]').forEach(container => {
    const quizId = container.dataset.quiz;
    loadQuiz(container, quizId);
});

async function loadQuiz(container, quizId) {
    try {
        const params = new URLSearchParams({ action: 'hs_get_quiz', quiz_id: quizId, nonce: API.nonce || '' });
        const resp = await fetch(`${API.ajaxurl}?${params.toString()}`);
        const data = await resp.json();
        if (!data.success) { container.innerHTML = `<p>Erro ao carregar quiz.</p>`; return; }
        renderQuizIntro(container, data.data);
    } catch(e) {
        container.innerHTML = '<p>Erro de conexão.</p>';
    }
}

// ── Quiz Engine ───────────────────────────────────────────
function renderQuizIntro(container, quiz) {
    container.innerHTML = `
        <div class="hs-quiz-intro">
            <div class="quiz-icon">🧠</div>
            <h2>${escHtml(quiz.title)}</h2>
            <div class="quiz-meta">
                <span><i class="fas fa-question-circle"></i>${quiz.count} questões</span>
                <span><i class="fas fa-clock"></i>${formatTime(quiz.time_limit || 600)}</span>
                <span><i class="fas fa-trophy"></i>Nota mínima: 70%</span>
            </div>
            <button class="btn btn-gradient btn-lg mt-3 hs-start-quiz" style="border-radius:999px">
                <i class="fas fa-play me-2"></i>Iniciar Quiz
            </button>
        </div>`;

    container.querySelector('.hs-start-quiz').addEventListener('click', () => {
        startQuiz(container, quiz);
    });
}

function startQuiz(container, quiz) {
    let current  = 0;
    let answers  = {};
    let timeLeft = quiz.time_limit || 600;
    let timer    = null;

    function render() {
        const q   = quiz.questions[current];
        const pct = Math.round(((current) / quiz.questions.length) * 100);
        const letters = ['A','B','C','D'];

        container.innerHTML = `
            <div class="hs-quiz-active">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                    <div class="hs-quiz-progress" style="flex:1;margin-bottom:0;margin-right:16px">
                        <div class="qp-bar"><div class="qp-fill" style="width:${pct}%"></div></div>
                        <div class="qp-text">Questão ${current+1} de ${quiz.questions.length}</div>
                    </div>
                    <div class="hs-quiz-timer" id="quizTimer"><i class="fas fa-clock"></i>${formatTime(timeLeft)}</div>
                </div>
                <div class="hs-question-card">
                    <p class="hs-question-text">${current+1}. ${escHtml(q.text)}</p>
                    <div class="hs-options">
                        ${(q.options||[]).map((opt,i) => `
                        <div class="hs-option ${answers[current]===i?'selected':''}" data-opt="${i}">
                            <div class="option-letter">${letters[i]}</div>
                            <div class="option-text">${escHtml(opt)}</div>
                        </div>`).join('')}
                    </div>
                </div>
                <div style="display:flex;justify-content:space-between;margin-top:24px;gap:12px">
                    <button class="btn btn-ghost btn-prev" ${current===0?'disabled':''} style="border-radius:999px">
                        <i class="fas fa-arrow-left me-2"></i>Anterior
                    </button>
                    <button class="btn btn-gradient btn-next" style="border-radius:999px">
                        ${current === quiz.questions.length-1 ? 'Finalizar <i class="fas fa-check ms-2"></i>' : 'Próxima <i class="fas fa-arrow-right ms-2"></i>'}
                    </button>
                </div>
            </div>`;

        // Option selection
        container.querySelectorAll('.hs-option').forEach(opt => {
            opt.addEventListener('click', () => {
                container.querySelectorAll('.hs-option').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                answers[current] = parseInt(opt.dataset.opt);
            });
        });

        container.querySelector('.btn-prev')?.addEventListener('click', () => {
            if (current > 0) { current--; render(); }
        });
        container.querySelector('.btn-next')?.addEventListener('click', () => {
            if (current < quiz.questions.length - 1) { current++; render(); }
            else { clearInterval(timer); submitQuiz(); }
        });
    }

    // Timer
    timer = setInterval(() => {
        timeLeft--;
        const el = document.getElementById('quizTimer');
        if (el) {
            el.textContent = formatTime(timeLeft);
            if (timeLeft < 60) el.classList.add('danger');
        }
        if (timeLeft <= 0) { clearInterval(timer); submitQuiz(); }
    }, 1000);

    async function submitQuiz() {
        const body = new URLSearchParams({
            action: 'hs_submit_quiz',
            nonce:  API.nonce,
            quiz_id: quizId,
        });
        Object.entries(answers).forEach(([k,v]) => body.append(`answers[${k}]`, v));

        container.innerHTML = '<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Calculando resultado...</p></div>';

        try {
            const resp = await fetch(API.ajaxurl, { method:'POST', body });
            const data = await resp.json();
            if (data.success) renderResult(container, data.data, quiz);
            else renderResult(container, { percentage:0, passed:false, score:0, total:quiz.questions.length, xp_gained:0 }, quiz);
        } catch(e) {
            container.innerHTML = '<p>Erro ao enviar quiz. Tente novamente.</p>';
        }
    }

    const quizId = quiz.id;
    render();
}

function renderResult(container, result, quiz) {
    const pct     = result.percentage || 0;
    const passed  = result.passed;
    const emoji   = passed ? '🎉' : '😞';
    const title   = passed ? 'Parabéns! Você passou!' : 'Quase lá! Tente novamente.';
    const cls     = passed ? 'pass' : 'fail';

    container.innerHTML = `
        <div class="hs-quiz-result">
            <div class="result-score-circle ${cls}">
                ${pct}<span>%</span>
            </div>
            <span class="result-badge">${emoji}</span>
            <h3 class="result-title">${title}</h3>
            <p class="result-sub">Você acertou ${result.score} de ${result.total} questões.</p>
            ${result.xp_gained ? `<div class="result-xp"><i class="fas fa-bolt"></i>+${result.xp_gained} XP ganhos!</div>` : ''}
            <div style="display:flex;gap:12px;justify-content:center;margin-top:8px">
                ${result.certificate_url ? `<a class="btn btn-gradient btn-sm" href="${escAttr(result.certificate_url)}" style="border-radius:999px"><i class="fas fa-certificate me-1"></i>Ver certificado</a>` : ''}
                <button class="btn btn-ghost btn-sm retry-quiz" style="border-radius:999px">
                    <i class="fas fa-redo me-1"></i>Tentar Novamente
                </button>
            </div>
        </div>`;

    if (passed) launchConfetti();

    container.querySelector('.retry-quiz').addEventListener('click', () => {
        renderQuizIntro(container, quiz);
    });
}

// ── Confetti ─────────────────────────────────────────────
function launchConfetti() {
    const canvas = document.createElement('canvas');
    canvas.className = 'hs-confetti-canvas';
    document.body.appendChild(canvas);
    const ctx = canvas.getContext('2d');
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;

    const particles = Array.from({length:100}, () => ({
        x: Math.random() * canvas.width, y: -20,
        w: Math.random()*10+6, h: Math.random()*6+4,
        color: ['#7C3AED','#06B6D4','#F472B6','#10B981','#F59E0B'][Math.floor(Math.random()*5)],
        vx: (Math.random()-0.5)*6, vy: Math.random()*4+3,
        rot: Math.random()*360, vr: (Math.random()-0.5)*8,
        alpha: 1
    }));

    let frame;
    function draw() {
        ctx.clearRect(0,0,canvas.width,canvas.height);
        let alive = false;
        particles.forEach(p => {
            p.x += p.vx; p.y += p.vy; p.rot += p.vr;
            if (p.y > canvas.height * 0.7) p.alpha -= 0.02;
            if (p.alpha > 0) {
                alive = true;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot * Math.PI/180);
                ctx.globalAlpha = p.alpha;
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.w/2, -p.h/2, p.w, p.h);
                ctx.restore();
            }
        });
        if (alive) frame = requestAnimationFrame(draw);
        else { canvas.remove(); cancelAnimationFrame(frame); }
    }
    draw();
    setTimeout(() => { particles.forEach(p => p.alpha = 0); }, 3500);
}

// ── Dashboard course builder ──────────────────────────────
function initLmsDashboardTools() {
    const root = document.querySelector('[data-hs-dashboard-tools]');
    if (!root) return;

    root.querySelectorAll('[data-hs-tab]').forEach(btn => {
        btn.addEventListener('click', () => setTab(root, btn.dataset.hsTab));
    });
    root.querySelector('[data-hs-open-builder]')?.addEventListener('click', () => setTab(root, 'builder'));

    initCourseWizard(root);
    initCertificateForm(root);
}

function initVideoWatermarks() {
    const players = document.querySelectorAll('.hs-secure-player[data-watermark], .hs-embed-player[data-watermark]');
    if (!players.length) return;

    const positions = [
        ['50%', '42%'],
        ['28%', '34%'],
        ['72%', '58%'],
        ['38%', '68%'],
        ['66%', '30%'],
    ];

    players.forEach(player => {
        let index = 0;
        const move = () => {
            const pos = positions[index % positions.length];
            player.style.setProperty('--hs-watermark-x', pos[0]);
            player.style.setProperty('--hs-watermark-y', pos[1]);
            index += 1;
        };
        move();
        window.setInterval(move, 7000);
    });
}

function setTab(root, tab) {
    root.querySelectorAll('[data-hs-tab]').forEach(btn => btn.classList.toggle('active', btn.dataset.hsTab === tab));
    root.querySelectorAll('[data-hs-panel]').forEach(panel => panel.classList.toggle('active', panel.dataset.hsPanel === tab));
}

function initCourseWizard(root) {
    const form = root.querySelector('[data-hs-course-wizard]');
    if (!form) return;

    const sectionsWrap = form.querySelector('[data-course-sections]');
    const resourcesWrap = form.querySelector('[data-resource-list]');
    const notice = form.querySelector('[data-form-notice]');
    const assetIds = new Set();
    let currentStep = 0;
    let sectionIndex = 0;

    const showNotice = (message, type = 'info') => {
        notice.hidden = false;
        notice.className = `hs-form-notice ${type}`;
        notice.textContent = message;
    };
    const clearNotice = () => {
        notice.hidden = true;
        notice.textContent = '';
    };

    function renderStep() {
        form.querySelectorAll('[data-step]').forEach(step => step.classList.toggle('active', Number(step.dataset.step) === currentStep));
        form.querySelectorAll('[data-step-dot]').forEach(dot => dot.classList.toggle('active', Number(dot.dataset.stepDot) === currentStep));
        form.querySelector('[data-wizard-prev]').disabled = currentStep === 0;
        form.querySelector('[data-wizard-next]').hidden = currentStep === 3;
        form.querySelector('[data-save-course]').hidden = currentStep !== 3;
    }

    function addSection() {
        sectionIndex += 1;
        const section = document.createElement('div');
        section.className = 'hs-course-section-card';
        section.innerHTML = `
            <div class="hs-section-title-row">
                <label>Nome do modulo<input type="text" data-section-title value="Modulo ${sectionIndex}"></label>
                <button type="button" class="hs-ghost-btn" data-add-lesson><i class="fas fa-plus"></i>Aula</button>
            </div>
            <div class="hs-lessons-list" data-lessons-list></div>`;
        sectionsWrap.appendChild(section);
        section.querySelector('[data-add-lesson]').addEventListener('click', () => addLesson(section));
        addLesson(section);
    }

    function addLesson(section) {
        const lesson = document.createElement('div');
        lesson.className = 'hs-lesson-builder';
        lesson.dataset.assetId = '';
        lesson.innerHTML = `
            <div class="hs-lesson-main">
                <label>Titulo da aula<input type="text" data-lesson-title value="Nova aula"></label>
                <label>Duracao<input type="text" data-lesson-duration placeholder="15min"></label>
                <label>Tipo<select data-lesson-type><option value="video">Video</option><option value="quiz">Quiz</option><option value="project">Projeto</option></select></label>
                <label>Origem do video<select data-video-provider><option value="secure">Upload protegido</option><option value="youtube">YouTube</option><option value="vimeo">Vimeo</option><option value="external">URL externa</option><option value="none">Sem video</option></select></label>
            </div>
            <div class="hs-lesson-media">
                <label class="hs-video-url">URL do video<input type="url" data-video-url placeholder="https://youtube.com/..."></label>
                <label class="hs-upload-btn"><i class="fas fa-cloud-upload-alt"></i>Enviar video<input type="file" data-video-upload accept=".mp4,.m4v,.webm,.mov"></label>
                <span class="hs-upload-state" data-upload-state>Nenhum video enviado</span>
            </div>
            <label class="hs-field-wide">Descricao da aula<textarea rows="2" data-lesson-description></textarea></label>`;
        section.querySelector('[data-lessons-list]').appendChild(lesson);

        const provider = lesson.querySelector('[data-video-provider]');
        const upload = lesson.querySelector('[data-video-upload]');
        const urlField = lesson.querySelector('.hs-video-url');
        const state = lesson.querySelector('[data-upload-state]');
        const syncProvider = () => {
            const secure = provider.value === 'secure';
            upload.closest('.hs-upload-btn').style.display = secure ? 'inline-flex' : 'none';
            urlField.style.display = secure || provider.value === 'none' ? 'none' : 'grid';
        };
        provider.addEventListener('change', syncProvider);
        syncProvider();

        upload.addEventListener('change', async () => {
            if (!upload.files.length) return;
            state.textContent = 'Enviando video...';
            try {
                const asset = await uploadAsset(upload.files[0]);
                lesson.dataset.assetId = asset.id;
                assetIds.add(asset.id);
                state.textContent = asset.name;
            } catch (err) {
                state.textContent = 'Falha no envio';
                notify(err.message || 'Falha no envio do video.', 'error');
            }
        });
    }

    async function uploadAsset(file) {
        const fd = new FormData();
        fd.append('action', 'hs_lms_upload_asset');
        fd.append('nonce', API.nonce || '');
        fd.append('file', file);
        const response = await fetch(API.ajaxurl, { method: 'POST', body: fd });
        const data = await response.json();
        if (!data.success) throw new Error(data.data?.message || 'Upload nao concluido.');
        return data.data;
    }

    function addResource(asset) {
        const item = document.createElement('div');
        item.className = 'hs-resource-item';
        item.dataset.assetId = asset.id || '';
        item.dataset.mime = asset.mime || '';
        item.innerHTML = `<i class="fas fa-file"></i><input type="text" data-resource-name value="${escAttr(asset.name || 'Material')}"><span>${escHtml(asset.mime || '')}</span>`;
        resourcesWrap.appendChild(item);
    }

    form.querySelector('[data-add-section]')?.addEventListener('click', addSection);
    form.querySelector('[data-wizard-prev]')?.addEventListener('click', () => { currentStep = Math.max(0, currentStep - 1); renderStep(); });
    form.querySelector('[data-wizard-next]')?.addEventListener('click', () => { currentStep = Math.min(3, currentStep + 1); renderStep(); });
    form.querySelector('[data-resource-upload]')?.addEventListener('change', async event => {
        for (const file of Array.from(event.target.files || [])) {
            try {
                const asset = await uploadAsset(file);
                assetIds.add(asset.id);
                addResource(asset);
                notify('Material enviado.', 'success');
            } catch (err) {
                notify(err.message || 'Falha no envio do material.', 'error');
            }
        }
        event.target.value = '';
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        clearNotice();
        const saveBtn = form.querySelector('[data-save-course]');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Salvando...';

        const curriculum = Array.from(sectionsWrap.querySelectorAll('.hs-course-section-card')).map(section => ({
            section: section.querySelector('[data-section-title]')?.value || 'Modulo',
            lessons: Array.from(section.querySelectorAll('.hs-lesson-builder')).map((lesson, index) => ({
                id: Date.now() + index,
                title: lesson.querySelector('[data-lesson-title]')?.value || 'Nova aula',
                duration: lesson.querySelector('[data-lesson-duration]')?.value || '',
                type: lesson.querySelector('[data-lesson-type]')?.value || 'video',
                description: lesson.querySelector('[data-lesson-description]')?.value || '',
                video_provider: lesson.querySelector('[data-video-provider]')?.value || 'none',
                video_url: lesson.querySelector('[data-video-url]')?.value || '',
                secure_asset_id: lesson.dataset.assetId || '',
            })),
        }));

        const resources = Array.from(resourcesWrap.querySelectorAll('.hs-resource-item')).map(item => ({
            name: item.querySelector('[data-resource-name]')?.value || 'Material',
            asset_id: item.dataset.assetId || '',
            mime: item.dataset.mime || '',
            url: '',
        }));

        const fd = new FormData(form);
        fd.append('action', 'hs_lms_save_course');
        fd.append('nonce', API.nonce || '');
        fd.append('curriculum_json', JSON.stringify(curriculum));
        fd.append('resources_json', JSON.stringify(resources));
        fd.append('asset_ids_json', JSON.stringify(Array.from(assetIds)));

        try {
            const response = await fetch(API.ajaxurl, { method: 'POST', body: fd });
            const data = await response.json();
            if (!data.success) throw new Error(data.data?.message || 'Nao foi possivel salvar.');
            showNotice(data.data.message || 'Curso salvo.', 'success');
            notify('Curso salvo com sucesso.', 'success');
            if (data.data.permalink) setTimeout(() => { window.location.href = data.data.permalink; }, 900);
        } catch (err) {
            showNotice(err.message || 'Erro ao salvar curso.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i>Salvar curso';
        }
    });

    addSection();
    renderStep();
}

function initCertificateForm(root) {
    const form = root.querySelector('[data-hs-cert-form]');
    if (!form) return;
    const courseSelect = form.querySelector('[name="course_id"]');
    const studentSelect = form.querySelector('[name="student_id"]');
    const notice = form.querySelector('[data-cert-notice]') || root.querySelector('[data-cert-notice]');
    const preview = form.querySelector('[data-cert-preview]');
    const options = Array.from(studentSelect.querySelectorAll('option'));
    let issuedCertId = '';

    const input = name => form.querySelector(`[name="${name}"]`);
    const setText = (selector, value) => {
        const el = preview?.querySelector(selector);
        if (el) el.textContent = value || '-';
    };
    const selectedOption = select => select?.options[select.selectedIndex] || null;

    function filterStudents() {
        const course = courseSelect.value;
        let visibleCount = 0;
        options.forEach(opt => {
            if (!opt.value) return;
            const visible = !!course && (opt.dataset.course === course || opt.dataset.course === 'all');
            opt.hidden = !visible;
            if (visible) visibleCount += 1;
        });
        if (!studentSelect.value || selectedOption(studentSelect)?.hidden) {
            studentSelect.value = '';
        }
        studentSelect.disabled = !course || visibleCount === 0;
    }

    function syncCourseFields() {
        const opt = selectedOption(courseSelect);
        input('course_title').value = opt?.dataset.title || '';
        input('course_workload').value = opt?.dataset.workload || '';
        input('instructor_name').value = opt?.dataset.instructor || '';
        input('template').value = opt?.dataset.template || 'modern';
        input('issuer_name').value = opt?.dataset.issuer || input('issuer_name').value || 'HubSkills';
        input('issuer_title').value = opt?.dataset.issuerTitle || input('issuer_title').value || 'Diretor Academico';
        input('location').value = opt?.dataset.location || '';
        input('note').value = opt?.dataset.note || '';
        const autoToggle = input('auto_issue');
        if (autoToggle) autoToggle.checked = (opt?.dataset.autoIssue ?? '1') !== '0';
        setSignature('instructor', opt?.dataset.instructorSig || 0, opt?.dataset.instructorSigUrl || '');
        setSignature('issuer', opt?.dataset.issuerSig || 0, opt?.dataset.issuerSigUrl || '');
    }

    function sigBox(role) {
        return form.querySelector(`[data-sig-role="${role}"]`);
    }

    function setSignature(role, id, url) {
        const box = sigBox(role);
        if (!box) return;
        const idField = box.querySelector('[data-sig-id]');
        const preview = box.querySelector('[data-sig-preview]');
        const clearBtn = box.querySelector('[data-sig-clear]');
        idField.value = parseInt(id, 10) || 0;
        if (url) {
            preview.src = url;
            preview.hidden = false;
            clearBtn.hidden = false;
        } else {
            preview.src = '';
            preview.hidden = true;
            clearBtn.hidden = true;
        }
    }

    function initSignatureUploaders() {
        form.querySelectorAll('[data-sig-role]').forEach(box => {
            const fileInput = box.querySelector('[data-sig-file]');
            const status = box.querySelector('[data-sig-status]');
            box.querySelector('[data-sig-pick]')?.addEventListener('click', () => fileInput.click());
            box.querySelector('[data-sig-clear]')?.addEventListener('click', () => setSignature(box.dataset.sigRole, 0, ''));
            fileInput?.addEventListener('change', async () => {
                const file = fileInput.files?.[0];
                if (!file) return;
                if (file.size > 2 * 1024 * 1024) { status.textContent = 'Imagem acima de 2MB.'; return; }
                status.textContent = 'Enviando...';
                const fd = new FormData();
                fd.append('action', 'hs_lms_upload_signature');
                fd.append('nonce', API.nonce || '');
                fd.append('signature', file);
                try {
                    const response = await fetch(API.ajaxurl, { method: 'POST', body: fd });
                    const data = await response.json();
                    if (!data.success) throw new Error(data.data?.message || 'Erro no upload.');
                    setSignature(box.dataset.sigRole, data.data.attachment_id, data.data.url);
                    status.textContent = '';
                    notify('Assinatura enviada.', 'success');
                } catch (err) {
                    status.textContent = err.message || 'Erro no upload.';
                } finally {
                    fileInput.value = '';
                }
            });
        });
    }
    initSignatureUploaders();

    function syncStudentFields() {
        const opt = selectedOption(studentSelect);
        input('student_name').value = opt?.dataset.name || '';
        input('student_email').value = opt?.dataset.email || '';
        const docField = input('student_document');
        if (docField) docField.value = opt?.dataset.document || '';
    }

    function getCertificateValues() {
        return {
            studentName: input('student_name')?.value || '',
            studentEmail: input('student_email')?.value || '',
            studentDocument: input('student_document')?.value || '',
            template: input('template')?.value || 'modern',
            courseTitle: input('course_title')?.value || '',
            courseWorkload: input('course_workload')?.value || '',
            completionDate: input('completion_date')?.value || '',
            issueDate: input('issue_date')?.value || '',
            instructorName: input('instructor_name')?.value || '',
            issuerName: input('issuer_name')?.value || '',
            issuerTitle: input('issuer_title')?.value || '',
            location: input('location')?.value || '',
            grade: input('grade')?.value || '',
            note: input('note')?.value || '',
            certId: issuedCertId || preview?.dataset.certId || '',
        };
    }

    function updatePreview() {
        if (!preview) return;
        const data = getCertificateValues();
        Object.entries(data).forEach(([key, value]) => {
            preview.dataset[key] = value || '';
        });
        preview.classList.remove('template-classic', 'template-modern', 'template-executive');
        preview.classList.add(`template-${data.template || 'modern'}`);
        setText('[data-preview-student]', data.studentName || 'Nome do aluno');
        setText('[data-preview-course]', data.courseTitle || 'Nome do curso');
        setText('[data-preview-completion]', data.completionDate || '-');
        setText('[data-preview-workload]', data.courseWorkload || '-');
        setText('[data-preview-grade]', data.grade || '-');
        setText('[data-preview-note]', data.note);
        setText('[data-preview-instructor]', data.instructorName || 'Instrutor do Curso');
        setText('[data-preview-issuer]', data.issuerName || 'HubSkills');
        setText('[data-preview-issuer-title]', data.issuerTitle || 'Diretor Academico');
        const idEl = preview.querySelector('.hs-cert-preview-id');
        if (idEl) idEl.textContent = data.certId ? `ID: ${data.certId}` : 'ID apos emissao';
    }

    function setNotice(message, type = 'info') {
        if (!notice) return;
        notice.hidden = false;
        notice.className = `hs-form-notice ${type}`;
        notice.innerHTML = message;
    }

    courseSelect.addEventListener('change', () => {
        issuedCertId = '';
        syncCourseFields();
        filterStudents();
        syncStudentFields();
        updatePreview();
    });
    studentSelect.addEventListener('change', () => {
        issuedCertId = '';
        syncStudentFields();
        updatePreview();
    });
    form.querySelectorAll('input, textarea, select').forEach(el => {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });
    filterStudents();
    syncCourseFields();
    syncStudentFields();
    updatePreview();

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (notice) notice.hidden = true;
        const fd = new FormData(form);
        fd.append('action', 'hs_lms_issue_certificate');
        fd.append('nonce', API.nonce || '');
        const submit = form.querySelector('[type="submit"]');
        if (submit) {
            submit.disabled = true;
            submit.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Emitindo...';
        }
        try {
            const response = await fetch(API.ajaxurl, { method: 'POST', body: fd });
            const data = await response.json();
            if (!data.success) throw new Error(data.data?.message || 'Erro ao emitir.');
            issuedCertId = data.data?.cert_id || '';
            if (preview) preview.dataset.certId = issuedCertId;
            updatePreview();
            const certLink = data.data?.cert_url ? ` <a href="${escAttr(data.data.cert_url)}" target="_blank" rel="noopener">Abrir certificado</a>` : '';
            setNotice(`${escHtml(data.data.message || 'Certificado emitido.')} ID: <strong>${escHtml(issuedCertId)}</strong>${certLink}`, 'success');
            notify('Certificado emitido.', 'success');
        } catch (err) {
            setNotice(escHtml(err.message || 'Erro ao emitir certificado.'), 'error');
        } finally {
            if (submit) {
                submit.disabled = false;
                submit.innerHTML = '<i class="fas fa-award"></i>Emitir certificado';
            }
        }
    });

    form.querySelector('[data-save-cert-settings]')?.addEventListener('click', async event => {
        event.preventDefault();
        if (notice) notice.hidden = true;
        const courseId = courseSelect.value;
        if (!courseId) {
            setNotice('Selecione um curso antes de salvar o modelo.', 'error');
            return;
        }

        const button = event.currentTarget;
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Salvando...';

        const fd = new FormData(form);
        fd.append('action', 'hs_lms_save_certificate_settings');
        fd.append('nonce', API.nonce || '');
        const autoToggle = input('auto_issue');
        if (autoToggle) fd.set('auto_issue', autoToggle.checked ? '1' : '0');

        try {
            const response = await fetch(API.ajaxurl, { method: 'POST', body: fd });
            const data = await response.json();
            if (!data.success) throw new Error(data.data?.message || 'Erro ao salvar.');

            const opt = selectedOption(courseSelect);
            const settings = data.data?.settings || {};
            if (opt) {
                opt.dataset.autoIssue = settings.auto_issue || (input('auto_issue')?.checked ? '1' : '0');
                opt.dataset.instructorSig = String(settings.instructor_signature_id ?? sigBox('instructor')?.querySelector('[data-sig-id]')?.value ?? 0);
                opt.dataset.instructorSigUrl = sigBox('instructor')?.querySelector('[data-sig-preview]')?.src || '';
                opt.dataset.issuerSig = String(settings.issuer_signature_id ?? sigBox('issuer')?.querySelector('[data-sig-id]')?.value ?? 0);
                opt.dataset.issuerSigUrl = sigBox('issuer')?.querySelector('[data-sig-preview]')?.src || '';
                opt.dataset.template = settings.template || input('template').value || 'modern';
                opt.dataset.workload = settings.course_workload || input('course_workload').value || '';
                opt.dataset.instructor = settings.instructor_name || input('instructor_name').value || '';
                opt.dataset.issuer = settings.issuer_name || input('issuer_name').value || '';
                opt.dataset.issuerTitle = settings.issuer_title || input('issuer_title').value || '';
                opt.dataset.location = settings.location || input('location').value || '';
                opt.dataset.note = settings.note || input('note').value || '';
            }
            setNotice(escHtml(data.data?.message || 'Modelo automatico do curso salvo.'), 'success');
            notify('Modelo automatico salvo.', 'success');
        } catch (err) {
            setNotice(escHtml(err.message || 'Erro ao salvar modelo.'), 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });
}

function initCertificateActions() {
    document.querySelectorAll('[data-certificate-download]').forEach(button => {
        button.addEventListener('click', () => {
            const source = findCertificateSource(button);
            if (source) downloadCertificateImage(source);
        });
    });
    document.querySelectorAll('[data-certificate-print]').forEach(button => {
        button.addEventListener('click', () => {
            const source = findCertificateSource(button);
            if (source) printCertificate(source);
        });
    });
}

function findCertificateSource(button) {
    const target = button.dataset.certTarget || button.dataset.certificateTarget || '';
    if (target) {
        const scoped = button.closest('form, .certificates-page, .hs-lms-dashboard-tools') || document;
        return scoped.querySelector(target) || document.querySelector(target);
    }
    return document.querySelector('[data-certificate-export]') || document.querySelector('[data-cert-preview]');
}

function getCertificateData(source) {
    const text = selector => source.querySelector(selector)?.textContent?.trim() || '';
    const data = source.dataset || {};
    return {
        studentName: data.studentName || text('[data-preview-student], .cert-name'),
        studentEmail: data.studentEmail || '',
        studentDocument: data.studentDocument || '',
        template: data.template || 'modern',
        courseTitle: data.courseTitle || text('[data-preview-course], .cert-course'),
        courseWorkload: data.courseWorkload || text('[data-preview-workload]'),
        completionDate: data.completionDate || text('[data-preview-completion], [data-cert-completion]'),
        issueDate: data.issueDate || '',
        instructorName: data.instructorName || text('[data-preview-instructor]'),
        issuerName: data.issuerName || text('[data-preview-issuer]'),
        issuerTitle: data.issuerTitle || text('[data-preview-issuer-title]'),
        location: data.location || '',
        grade: data.grade || text('[data-preview-grade]'),
        note: data.note || text('[data-preview-note], .cert-note'),
        certId: data.certId || text('.hs-cert-preview-id, .cert-id').replace(/^ID:\s*/i, ''),
    };
}

function printCertificate(source) {
    const data = getCertificateData(source);
    const win = window.open('', '_blank');
    if (!win) {
        window.print();
        return;
    }
    win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${escHtml(data.certId || 'Certificado')}</title>${certificatePrintStyle(data)}</head><body>${certificatePrintMarkup(data)}<script>window.onload=function(){setTimeout(function(){window.print();},120);};<\/script></body></html>`);
    win.document.close();
}

function certificatePalette(template) {
    const palettes = {
        classic: { primary: '#0f6cbf', secondary: '#12a6c8', soft: '#eef7ff' },
        modern: { primary: '#0b7f9c', secondary: '#7c3aed', soft: '#edfafa' },
        executive: { primary: '#9a6a12', secondary: '#172033', soft: '#fff8e6' },
    };
    return palettes[template] || palettes.modern;
}

function certificatePrintStyle(data = {}) {
    const palette = certificatePalette(data.template);
    return `<style>
        @page{size:A4 landscape;margin:10mm}
        body{margin:0;background:#eef3f8;font-family:Arial,Helvetica,sans-serif;color:#162033}
        .print-cert{width:100%;min-height:680px;box-sizing:border-box;background:#fff;border:10px solid ${palette.primary};padding:54px;position:relative;overflow:hidden}
        .print-cert:before{content:"";position:absolute;inset:22px;border:1px solid #b9d8f3;pointer-events:none}
        .print-head{display:flex;justify-content:space-between;align-items:center;color:${palette.primary};font-weight:800;text-transform:uppercase;letter-spacing:.08em}
        .print-brand{font-size:30px;color:#172033;text-transform:none;letter-spacing:0}.print-brand span{color:${palette.primary}}
        .print-body{text-align:center;padding:52px 40px 36px}.print-body p{font-size:20px;color:#667085;margin:10px 0}.print-body h1{font-size:48px;line-height:1.1;margin:12px 0;color:#111827}.print-body h2{font-size:30px;line-height:1.25;color:${palette.primary};margin:12px 0}
        .print-meta{display:flex;justify-content:center;gap:18px;flex-wrap:wrap;margin-top:24px}.print-meta span{border:1px solid #d9e6f2;border-radius:999px;padding:8px 14px;color:#344054;font-weight:700}
        .print-note{max-width:760px;margin:24px auto 0;color:#475467;font-size:16px}
        .print-foot{display:flex;justify-content:space-between;gap:28px;margin-top:54px}.print-sign{flex:1;text-align:center;color:#667085}.print-line{height:1px;background:#9fb5ca;margin:0 auto 10px;max-width:260px}.print-sign strong{display:block;color:#172033}.print-id{position:absolute;left:54px;bottom:30px;font-family:Consolas,monospace;color:#667085}
    </style>`;
}

function certificatePrintMarkup(data) {
    return `<article class="print-cert">
        <div class="print-head"><div class="print-brand">Hub<span>Skills</span></div><div>Certificado</div></div>
        <div class="print-body">
            <p>Certificamos que</p>
            <h1>${escHtml(data.studentName || 'Nome do aluno')}</h1>
            <p>concluiu com exito o curso</p>
            <h2>${escHtml(data.courseTitle || 'Nome do curso')}</h2>
            <div class="print-meta">
                <span>Conclusao: ${escHtml(data.completionDate || '-')}</span>
                <span>Carga horaria: ${escHtml(data.courseWorkload || '-')}</span>
                <span>Aproveitamento: ${escHtml(data.grade || '-')}</span>
            </div>
            ${data.note ? `<div class="print-note">${escHtml(data.note)}</div>` : ''}
        </div>
        <div class="print-foot">
            <div class="print-sign"><div class="print-line"></div><strong>${escHtml(data.instructorName || 'Instrutor do Curso')}</strong><span>Instrutor</span></div>
            <div class="print-sign"><div class="print-line"></div><strong>${escHtml(data.issuerName || 'HubSkills')}</strong><span>${escHtml(data.issuerTitle || 'Diretor Academico')}</span></div>
        </div>
        <div class="print-id">ID: ${escHtml(data.certId || 'Apos emissao')}</div>
    </article>`;
}

function downloadCertificateImage(source) {
    const data = getCertificateData(source);
    const palette = certificatePalette(data.template);
    const canvas = document.createElement('canvas');
    canvas.width = 1600;
    canvas.height = 1100;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = '#f2f6fb';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(80, 80, 1440, 940);
    ctx.strokeStyle = palette.primary;
    ctx.lineWidth = 18;
    ctx.strokeRect(96, 96, 1408, 908);
    ctx.strokeStyle = '#b9d8f3';
    ctx.lineWidth = 3;
    ctx.strokeRect(138, 138, 1324, 824);

    const gradient = ctx.createLinearGradient(80, 80, 1520, 80);
    gradient.addColorStop(0, palette.primary);
    gradient.addColorStop(1, palette.secondary);
    ctx.fillStyle = gradient;
    ctx.fillRect(96, 96, 1408, 20);

    ctx.textAlign = 'left';
    ctx.fillStyle = '#172033';
    ctx.font = '700 48px Arial';
    ctx.fillText('Hub', 160, 205);
    ctx.fillStyle = palette.primary;
    ctx.fillText('Skills', 252, 205);
    ctx.textAlign = 'right';
    ctx.fillStyle = palette.primary;
    ctx.font = '800 30px Arial';
    ctx.fillText('CERTIFICADO', 1440, 205);

    ctx.textAlign = 'center';
    ctx.fillStyle = '#667085';
    ctx.font = '400 34px Arial';
    ctx.fillText('Certificamos que', 800, 330);
    ctx.fillStyle = '#111827';
    ctx.font = '800 70px Arial';
    wrapCanvasText(ctx, data.studentName || 'Nome do aluno', 800, 420, 1180, 78);
    ctx.fillStyle = '#667085';
    ctx.font = '400 32px Arial';
    ctx.fillText('concluiu com exito o curso', 800, 540);
    ctx.fillStyle = '#0f6cbf';
    ctx.font = '800 46px Arial';
    wrapCanvasText(ctx, data.courseTitle || 'Nome do curso', 800, 615, 1160, 54);

    ctx.font = '700 25px Arial';
    drawPill(ctx, `Conclusao: ${data.completionDate || '-'}`, 390, 735);
    drawPill(ctx, `Carga horaria: ${data.courseWorkload || '-'}`, 800, 735);
    drawPill(ctx, `Aproveitamento: ${data.grade || '-'}`, 1210, 735);

    if (data.note) {
        ctx.fillStyle = '#475467';
        ctx.font = '400 24px Arial';
        wrapCanvasText(ctx, data.note, 800, 820, 920, 32);
    }

    drawSignature(ctx, 420, 930, data.instructorName || 'Instrutor do Curso', 'Instrutor');
    drawSignature(ctx, 1180, 930, data.issuerName || 'HubSkills', data.issuerTitle || 'Diretor Academico');

    ctx.textAlign = 'left';
    ctx.fillStyle = '#667085';
    ctx.font = '22px Consolas, monospace';
    ctx.fillText(`ID: ${data.certId || 'Apos emissao'}`, 160, 975);

    const link = document.createElement('a');
    const filename = (data.studentName || data.courseTitle || 'certificado').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'certificado';
    link.download = `${filename}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}

function drawPill(ctx, text, x, y) {
    const width = 340;
    const height = 54;
    ctx.fillStyle = '#f7fbff';
    ctx.strokeStyle = '#d9e6f2';
    ctx.lineWidth = 2;
    roundRect(ctx, x - width / 2, y - height / 2, width, height, 26);
    ctx.fill();
    ctx.stroke();
    ctx.fillStyle = '#344054';
    ctx.textAlign = 'center';
    ctx.fillText(text, x, y + 8);
}

function drawSignature(ctx, x, y, name, title) {
    ctx.strokeStyle = '#9fb5ca';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(x - 180, y - 28);
    ctx.lineTo(x + 180, y - 28);
    ctx.stroke();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#172033';
    ctx.font = '700 26px Arial';
    ctx.fillText(name, x, y + 8);
    ctx.fillStyle = '#667085';
    ctx.font = '400 21px Arial';
    ctx.fillText(title, x, y + 38);
}

function wrapCanvasText(ctx, text, x, y, maxWidth, lineHeight) {
    const words = String(text || '').split(/\s+/);
    let line = '';
    for (let i = 0; i < words.length; i++) {
        const testLine = line ? `${line} ${words[i]}` : words[i];
        if (ctx.measureText(testLine).width > maxWidth && line) {
            ctx.fillText(line, x, y);
            line = words[i];
            y += lineHeight;
        } else {
            line = testLine;
        }
    }
    ctx.fillText(line, x, y);
}

function roundRect(ctx, x, y, width, height, radius) {
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.lineTo(x + width - radius, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
    ctx.lineTo(x + width, y + height - radius);
    ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
    ctx.lineTo(x + radius, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
    ctx.lineTo(x, y + radius);
    ctx.quadraticCurveTo(x, y, x + radius, y);
    ctx.closePath();
}

function notify(message, type = 'info') {
    if (window.showToast) window.showToast(message, type);
}

// ── Helpers ───────────────────────────────────────────────
function formatTime(s) {
    const m = Math.floor(s/60), sec = s%60;
    return `${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
}
function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(str) {
    return escHtml(str).replace(/'/g,'&#039;');
}

})();

/* ── Reembolso: solicitação pelo aluno ───────────────────── */
(function() {
    'use strict';
    if (typeof HSLMS === 'undefined') return;

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.hs-request-refund');
        if (!btn) return;

        var orderId = btn.getAttribute('data-order');
        var reason = prompt('Por que você deseja o reembolso? (mínimo 10 caracteres)');
        if (reason === null) return;
        if (reason.trim().length < 10) { alert('Descreva o motivo com pelo menos 10 caracteres.'); return; }

        btn.disabled = true;
        var data = new URLSearchParams({
            action: 'hs_request_refund',
            nonce: HSLMS.nonce,
            order_id: orderId,
            reason: reason.trim()
        });

        fetch(HSLMS.ajaxurl, { method: 'POST', credentials: 'same-origin', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                alert(res.data && res.data.message ? res.data.message : (res.success ? 'Solicitação enviada!' : 'Erro ao enviar.'));
                if (res.success) location.reload();
                else btn.disabled = false;
            })
            .catch(function() { alert('Erro de conexão.'); btn.disabled = false; });
    });
})();
