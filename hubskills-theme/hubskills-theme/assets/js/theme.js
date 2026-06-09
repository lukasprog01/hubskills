/* HubSkills EAD — Theme JavaScript */

(function($) {
'use strict';

const HS = window.HubSkillsAjax || {};

// ── Init ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initAOS();
  initPublicNav();
  initTabsFallback();
  initSidebar();
  initCounters();
  initSwipers();
  initParticles();
  initGSAP();
  initEnrollButtons();
  initPaymentButtons();
  initCheckoutModal();
  initCompleteLessonBtn();
  initNotes();
  initToastContainer();
  initTopbarSearch();
  if (typeof VanillaTilt !== 'undefined') {
    VanillaTilt.init(document.querySelectorAll('[data-tilt]'), { max: 8, speed: 400, glare: false });
  }
});

// ── AOS ───────────────────────────────────────────────────
function initAOS() {
  if (typeof AOS !== 'undefined') {
    AOS.init({ duration: 700, once: true, offset: 60, easing: 'ease-out-cubic' });
    return;
  }

  document.querySelectorAll('[data-aos]').forEach(el => {
    el.classList.add('aos-animate');
    el.style.opacity = '1';
    el.style.transform = 'none';
  });
}

// ── Public nav fallback when Bootstrap JS is unavailable ──
function initPublicNav() {
  if (window.bootstrap && window.bootstrap.Collapse) return;

  document.querySelectorAll('[data-bs-toggle="collapse"][data-bs-target]').forEach(toggle => {
    const target = document.querySelector(toggle.getAttribute('data-bs-target'));
    if (!target) return;

    toggle.addEventListener('click', () => {
      const isOpen = target.classList.toggle('show');
      toggle.classList.toggle('collapsed', !isOpen);
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  });
}

// ── Tab fallback when Bootstrap JS is unavailable ─────────
function initTabsFallback() {
  if (window.bootstrap && window.bootstrap.Tab) return;

  document.querySelectorAll('[data-bs-toggle="tab"][data-bs-target]').forEach(tab => {
    tab.addEventListener('click', (event) => {
      event.preventDefault();

      const target = document.querySelector(tab.getAttribute('data-bs-target'));
      const tabList = tab.closest('[role="tablist"], .nav-tabs, .lesson-nav-tabs');
      const content = target?.closest('.tab-content');
      if (!target || !tabList || !content) return;

      tabList.querySelectorAll('[data-bs-toggle="tab"]').forEach(item => item.classList.remove('active'));
      content.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active', 'show');
      });

      tab.classList.add('active');
      target.classList.add('active', 'show');
    });
  });
}

// ── Sidebar Toggle (mobile) ───────────────────────────────
function initSidebar() {
  const toggle  = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (!toggle || !sidebar) return;

  const overlay = document.createElement('div');
  overlay.className = 'sidebar-overlay';
  document.body.appendChild(overlay);

  toggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
  });
  overlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
  });
}

// ── Animated Counters ─────────────────────────────────────
function initCounters() {
  const counters = document.querySelectorAll('.counter, [data-target]');
  if (!counters.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el     = entry.target;
      const target = parseInt(el.dataset.target, 10);
      if (isNaN(target)) return;
      animateCounter(el, target, 1500);
      observer.unobserve(el);
    });
  }, { threshold: 0.3 });

  counters.forEach(el => observer.observe(el));
}

function animateCounter(el, target, duration) {
  const start = performance.now();
  function step(now) {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);
    const ease = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.round(ease * target).toLocaleString('pt-BR');
    if (progress < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

// ── Swipers ───────────────────────────────────────────────
function initSwipers() {
  if (typeof Swiper === 'undefined') return;

  const coursesEl = document.querySelector('.courses-swiper');
  if (coursesEl) {
    new Swiper(coursesEl, {
      slidesPerView: 1,
      spaceBetween: 20,
      grabCursor: true,
      pagination: { el: '.courses-pagination', clickable: true },
      navigation: { prevEl: '.swiper-button-prev', nextEl: '.swiper-button-next' },
      breakpoints: {
        640:  { slidesPerView: 2 },
        1024: { slidesPerView: 3 },
      },
    });
  }

  const testEl = document.querySelector('.testimonials-swiper');
  if (testEl) {
    new Swiper(testEl, {
      slidesPerView: 1,
      spaceBetween: 20,
      grabCursor: true,
      autoplay: { delay: 5000, disableOnInteraction: false },
      pagination: { el: '.test-pagination', clickable: true },
      breakpoints: {
        768: { slidesPerView: 2 },
        1200: { slidesPerView: 3 },
      },
    });
  }
}

// ── Particles.js ─────────────────────────────────────────
function initParticles() {
  const el = document.getElementById('particles-js');
  if (!el || typeof particlesJS === 'undefined') return;
  particlesJS('particles-js', {
    particles: {
      number: { value: 50, density: { enable: true, value_area: 800 } },
      color: { value: ['#007BFF', '#0056B3', '#3399FF'] },
      shape: { type: 'circle' },
      opacity: { value: 0.3, random: true, anim: { enable: true, speed: 1, opacity_min: 0.1 } },
      size: { value: 3, random: true },
      line_linked: { enable: true, distance: 150, color: '#007BFF', opacity: 0.08, width: 1 },
      move: { enable: true, speed: 1.5, direction: 'none', out_mode: 'out' },
    },
    interactivity: {
      detect_on: 'canvas',
      events: { onhover: { enable: true, mode: 'repulse' }, onclick: { enable: true, mode: 'push' } },
      modes: { repulse: { distance: 80 }, push: { particles_nb: 3 } },
    },
    retina_detect: true,
  });
}

// ── GSAP Animations ───────────────────────────────────────
function initGSAP() {
  if (typeof gsap === 'undefined') return;
  if (typeof ScrollTrigger !== 'undefined') gsap.registerPlugin(ScrollTrigger);

  // Feature cards stagger
  const featureCards = document.querySelectorAll('.feature-card');
  if (featureCards.length) {
    gsap.fromTo(featureCards,
      { opacity: 0, y: 40 },
      {
        opacity: 1, y: 0,
        stagger: 0.1, duration: 0.7, ease: 'power2.out',
        scrollTrigger: { trigger: featureCards[0].closest('.features-section'), start: 'top 80%' }
      }
    );
  }

  // Hero title word animation
  const heroTitle = document.querySelector('.hero-title');
  if (heroTitle) {
    gsap.fromTo(heroTitle, { opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: .9, ease: 'power3.out', delay: .2 });
  }
}

// ── Enroll Button ─────────────────────────────────────────
function initEnrollButtons() {
  document.querySelectorAll('.enroll-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const courseId = this.dataset.course;
      const nonce    = this.dataset.nonce;
      const btnEl    = this;
      btnEl.disabled = true;
      btnEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Matriculando...';

      fetch(HS.ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'hs_enroll', course_id: courseId, nonce }),
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          const payload = data.data || {};
          showToast(payload.message || 'Matrícula realizada! Bom aprendizado!', 'success');
          btnEl.classList.add('btn-enrolled');
          btnEl.innerHTML = '<i class="fas fa-check-circle me-2"></i>Matriculado';
          setTimeout(() => {
            if (payload.course_url) {
              window.location.href = payload.course_url;
            } else {
              location.reload();
            }
          }, 900);
        } else {
          showToast(data.data?.message || 'Erro na matrícula.', 'error');
          btnEl.disabled = false;
          btnEl.innerHTML = '<i class="fas fa-user-plus me-2"></i>Matricular-se';
        }
      })
      .catch(() => {
        showToast('Erro de conexão.', 'error');
        btnEl.disabled = false;
        btnEl.innerHTML = '<i class="fas fa-lock-open me-2"></i>Matricular-se Gratuitamente';
      });
    });
  });
}

// ── Payment Button ────────────────────────────────────────
function initPaymentButtons() {
  document.querySelectorAll('.payment-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const courseId = this.dataset.course;
      const nonce    = this.dataset.nonce || HS.nonce;
      const original = this.innerHTML;
      this.disabled  = true;
      this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Preparando pagamento...';

      fetch(HS.ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'hs_initiate_payment', course_id: courseId, nonce }),
      })
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          throw new Error(data.data?.message || 'Erro ao iniciar pagamento.');
        }

        if (data.data.already_enrolled && data.data.course_url) {
          showToast(data.data.message || 'Você já está matriculado neste curso.', 'success');
          window.location.href = data.data.course_url;
          return;
        }

        const checkoutUrl = data.data.checkout_url || data.data.approve_url;
        if (checkoutUrl) {
          window.location.href = checkoutUrl;
          return;
        }

        if (data.data.payload) {
          openPixCheckout(data.data);
          return;
        }

        showToast('Pagamento iniciado.', 'success');
      })
      .catch(err => {
        showToast(err.message || 'Erro ao iniciar pagamento.', 'error');
      })
      .finally(() => {
        this.disabled = false;
        this.innerHTML = original;
      });
    });
  });
}

// ── Checkout Modal ───────────────────────────────────────
function initCheckoutModal() {
  if (document.getElementById('hsCheckoutModal')) return;

  const modal = document.createElement('div');
  modal.id = 'hsCheckoutModal';
  modal.className = 'hs-checkout-modal';
  modal.setAttribute('aria-hidden', 'true');
  modal.innerHTML = `
    <div class="hs-checkout-backdrop" data-checkout-close></div>
    <section class="hs-checkout-dialog" role="dialog" aria-modal="true" aria-labelledby="hsCheckoutTitle">
      <button type="button" class="hs-checkout-close" data-checkout-close aria-label="Fechar"><i class="fas fa-times"></i></button>
      <div class="hs-checkout-header">
        <span class="hs-checkout-kicker">Checkout seguro</span>
        <h2 id="hsCheckoutTitle">Pagamento via PIX</h2>
        <p>Finalize o pagamento no app do seu banco usando o código copia e cola.</p>
      </div>
      <div class="hs-checkout-body">
        <div class="hs-checkout-summary">
          <div class="hs-checkout-course">
            <span class="hs-checkout-icon"><i class="fas fa-graduation-cap"></i></span>
            <div>
              <small>Curso</small>
              <strong data-checkout-course>Curso</strong>
            </div>
          </div>
          <div class="hs-checkout-amount">
            <small>Valor</small>
            <strong data-checkout-amount>R$ 0,00</strong>
          </div>
          <div class="hs-checkout-status">
            <span class="hs-status-dot"></span>
            <span data-checkout-status>Aguardando pagamento</span>
          </div>
        </div>
        <div class="hs-pix-panel">
          <div class="hs-pix-qr-wrap">
            <img data-pix-qr alt="QR Code PIX" class="hs-pix-qr" referrerpolicy="no-referrer">
            <div class="hs-pix-qr-fallback"><i class="fas fa-qrcode"></i><span>Use o copia e cola</span></div>
          </div>
          <div class="hs-pix-details">
            <div class="hs-pix-row"><span>Beneficiário</span><strong data-pix-merchant>-</strong></div>
            <div class="hs-pix-row"><span>Cidade</span><strong data-pix-city>-</strong></div>
            <div class="hs-pix-row"><span>Identificador</span><strong data-pix-txid>-</strong></div>
          </div>
        </div>
        <label class="hs-pix-code-label" for="hsPixCode">PIX copia e cola</label>
        <textarea id="hsPixCode" class="hs-pix-code" data-pix-code readonly></textarea>
        <div class="hs-checkout-actions">
          <button type="button" class="btn btn-gradient" data-copy-pix><i class="fas fa-copy"></i> Copiar código PIX</button>
          <button type="button" class="btn btn-outline-primary" data-check-payment><i class="fas fa-rotate"></i> Verificar pagamento</button>
        </div>
        <div class="hs-checkout-help">
          <i class="fas fa-circle-info"></i>
          <span>Após o recebimento ser confirmado, sua matrícula será liberada automaticamente.</span>
        </div>
      </div>
    </section>
  `;
  document.body.appendChild(modal);

  modal.querySelectorAll('[data-checkout-close]').forEach(el => {
    el.addEventListener('click', closeCheckoutModal);
  });
  modal.querySelector('[data-copy-pix]')?.addEventListener('click', () => {
    copyText(modal.querySelector('[data-pix-code]')?.value || '').then(() => {
      showToast('Código PIX copiado.', 'success');
    });
  });
  modal.querySelector('[data-check-payment]')?.addEventListener('click', checkCurrentPaymentStatus);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('show')) closeCheckoutModal();
  });
}

function openPixCheckout(data) {
  initCheckoutModal();
  const modal = document.getElementById('hsCheckoutModal');
  if (!modal) return;

  modal.dataset.order = data.order_id || '';
  modal.dataset.courseUrl = data.course_url || '';
  setCheckoutText('[data-checkout-course]', data.course_title || 'Curso');
  setCheckoutText('[data-checkout-amount]', data.amount_display || (data.amount ? `R$ ${data.amount}` : 'R$ 0,00'));
  setCheckoutText('[data-checkout-status]', 'Aguardando pagamento');
  setCheckoutText('[data-pix-merchant]', data.merchant || '-');
  setCheckoutText('[data-pix-city]', data.city || '-');
  setCheckoutText('[data-pix-txid]', data.txid || `Pedido #${data.order_id || ''}`);

  const code = data.payload || '';
  const textarea = modal.querySelector('[data-pix-code]');
  if (textarea) textarea.value = code;

  const qr = modal.querySelector('[data-pix-qr]');
  if (qr && code) {
    qr.onerror = () => { qr.hidden = true; };
    qr.src = `https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=12&data=${encodeURIComponent(code)}`;
    qr.hidden = false;
  }

  document.body.classList.add('hs-checkout-open');
  modal.classList.add('show');
  modal.setAttribute('aria-hidden', 'false');
  setTimeout(() => modal.querySelector('[data-copy-pix]')?.focus(), 50);
}

function closeCheckoutModal() {
  const modal = document.getElementById('hsCheckoutModal');
  if (!modal) return;
  modal.classList.remove('show');
  modal.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('hs-checkout-open');
}

function setCheckoutText(selector, value) {
  const modal = document.getElementById('hsCheckoutModal');
  const el = modal?.querySelector(selector);
  if (el) el.textContent = value;
}

function copyText(text) {
  if (navigator.clipboard?.writeText) return navigator.clipboard.writeText(text);
  const tmp = document.createElement('textarea');
  tmp.value = text;
  tmp.setAttribute('readonly', '');
  tmp.style.position = 'fixed';
  tmp.style.opacity = '0';
  document.body.appendChild(tmp);
  tmp.select();
  document.execCommand('copy');
  tmp.remove();
  return Promise.resolve();
}

function checkCurrentPaymentStatus() {
  const modal = document.getElementById('hsCheckoutModal');
  const orderId = modal?.dataset.order;
  if (!orderId) return;

  const btn = modal.querySelector('[data-check-payment]');
  const original = btn?.innerHTML;
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
  }

  fetch(HS.ajaxurl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action: 'hs_check_payment_status', order_id: orderId, nonce: HS.nonce }),
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success) throw new Error(data.data?.message || 'Não foi possível verificar.');
    if (data.data.status === 'paid') {
      setCheckoutText('[data-checkout-status]', 'Pagamento confirmado');
      showToast('Pagamento confirmado. Matrícula liberada!', 'success');
      setTimeout(() => {
        window.location.href = modal.dataset.courseUrl || `${HS.siteurl || ''}/perfil/?tab=courses`;
      }, 900);
      return;
    }
    setCheckoutText('[data-checkout-status]', 'Aguardando confirmação');
    showToast('Pagamento ainda não confirmado.', 'info');
  })
  .catch(err => showToast(err.message || 'Erro ao verificar pagamento.', 'error'))
  .finally(() => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });
}

// ── Complete Lesson ───────────────────────────────────────
function initCompleteLessonBtn() {
  const btn = document.querySelector('.complete-lesson-btn');
  if (!btn) return;
  btn.addEventListener('click', function() {
    const courseId = this.dataset.course;
    const lessonId = this.dataset.lesson;
    const nonce    = this.dataset.nonce;
    this.disabled  = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Salvando...';

    fetch(HS.ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'hs_complete_lesson', course_id: courseId, lesson_id: lessonId, nonce }),
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        if (data.data?.completed && data.data?.certificate_url) {
          showToast('Curso concluído. Certificado emitido automaticamente!', 'success');
          setTimeout(() => { window.location.href = data.data.certificate_url; }, 1200);
        } else {
          showToast(`+${data.data.xp_gained} XP! Aula concluída! 🎯`, 'success');
          setTimeout(() => location.reload(), 1000);
        }
      } else {
        showToast(data.data?.message || 'Erro ao marcar aula.', 'error');
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-check me-1"></i>Marcar como Concluída';
      }
    });
  });
}

// ── Lesson Notes ─────────────────────────────────────────
function initNotes() {
  const textarea  = document.getElementById('lessonNotes');
  const saveBtn   = document.getElementById('saveNotes');
  if (!textarea) return;

  const playerEl = document.querySelector('[data-course]');
  const courseId = playerEl?.dataset.course;
  const lessonId = new URLSearchParams(location.search).get('lesson') || playerEl?.dataset.currentLesson || '0';
  const key      = `hs_notes_${courseId}_${lessonId}`;
  textarea.value = localStorage.getItem(key) || '';

  if (courseId && lessonId && HS.ajaxurl) {
    const params = new URLSearchParams({
      action: 'hs_get_notes',
      course_id: courseId,
      lesson_id: lessonId,
      nonce: HS.nonce,
    });
    fetch(`${HS.ajaxurl}?${params.toString()}`)
      .then(r => r.json())
      .then(data => {
        if (data.success && typeof data.data?.content === 'string') {
          textarea.value = data.data.content;
        }
      })
      .catch(() => {});
  }

  if (saveBtn) {
    saveBtn.addEventListener('click', () => {
      localStorage.setItem(key, textarea.value);
      if (!courseId || !lessonId || !HS.ajaxurl) {
        showToast('Anotações salvas!', 'success');
        return;
      }

      fetch(HS.ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'hs_save_notes',
          course_id: courseId,
          lesson_id: lessonId,
          content: textarea.value,
          nonce: HS.nonce,
        }),
      })
      .then(r => r.json())
      .then(data => {
        showToast(data.success ? 'Anotações salvas!' : (data.data?.message || 'Erro ao salvar anotações.'), data.success ? 'success' : 'error');
      })
      .catch(() => showToast('Anotações salvas neste navegador.', 'info'));
    });
  }
}

// ── Topbar Search ─────────────────────────────────────────
function initTopbarSearch() {
  const input = document.getElementById('topbarSearch');
  if (!input) return;
  let timer;
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      const q = input.value.trim();
      if (q) window.location.href = HS.siteurl + '/cursos/?course_search=' + encodeURIComponent(q);
    }
  });
}

// ── Toast Notifications ───────────────────────────────────
function initToastContainer() {
  if (!document.querySelector('.hs-toast-container')) {
    const c = document.createElement('div');
    c.className = 'hs-toast-container';
    document.body.appendChild(c);
  }
}

function showToast(message, type = 'info') {
  const icons = { success: 'check-circle', error: 'times-circle', info: 'info-circle' };
  const container = document.querySelector('.hs-toast-container');
  if (!container) return;
  const toast = document.createElement('div');
  toast.className = `hs-toast ${type}`;
  const icon = document.createElement('i');
  icon.className = `fas fa-${icons[type] || icons.info}`;
  const text = document.createElement('span');
  text.textContent = message;
  toast.append(icon, text);
  container.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; setTimeout(() => toast.remove(), 350); }, 3500);
}

window.showToast = showToast;

// ── Progress bar animate on load ─────────────────────────
document.querySelectorAll('.progress-bar[data-target]').forEach(bar => {
  setTimeout(() => bar.style.width = bar.dataset.target + '%', 300);
});

// ── Header Scroll Effect ─────────────────────────────────
const siteHeader = document.getElementById('siteHeader');
if (siteHeader) {
  window.addEventListener('scroll', () => {
    siteHeader.style.boxShadow = window.scrollY > 40
      ? '0 4px 20px rgba(0,0,0,.08)'
      : '0 2px 8px rgba(0,0,0,.04)';
  }, { passive: true });
}

})(window.jQuery || { fn: {} });
