/* HubSkills LMS - Admin JavaScript */
(function($) {
    'use strict';

    if (typeof HSADMIN === 'undefined') return;

    var ajaxurl = HSADMIN.ajaxurl;
    var nonce   = HSADMIN.nonce;

    function hsAdminAjax(action, data, callback) {
        $.post(ajaxurl, $.extend({ action: action, nonce: nonce }, data), function(res) {
            if (res.success) {
                if (typeof callback === 'function') callback(res);
                else location.reload();
            } else {
                alert((res.data && res.data.message) ? res.data.message : 'Erro ao processar.');
            }
        });
    }

    // ── Approve Instructor ────────────────────────────────────────────────────
    $(document).on('click', '.hs-approve-instructor', function() {
        var uid = $(this).data('uid');
        if (!confirm('Aprovar este instrutor?')) return;
        hsAdminAjax('hs_admin_approve_instructor', { user_id: uid });
    });

    // ── Reject Instructor ─────────────────────────────────────────────────────
    $(document).on('click', '.hs-reject-instructor', function() {
        var uid    = $(this).data('uid');
        var reason = prompt('Motivo da rejeição (opcional):') || '';
        if (reason === null) return;
        hsAdminAjax('hs_admin_reject_instructor', { user_id: uid, reason: reason });
    });

    // ── Suspend User ──────────────────────────────────────────────────────────
    $(document).on('click', '.hs-suspend-user', function() {
        var uid    = $(this).data('uid');
        var reason = prompt('Motivo da suspensão:');
        if (!reason) return;
        hsAdminAjax('hs_admin_suspend_user', { user_id: uid, reason: reason });
    });

    // ── Unsuspend User ────────────────────────────────────────────────────────
    $(document).on('click', '.hs-unsuspend-user', function() {
        var uid = $(this).data('uid');
        if (!confirm('Reativar esta conta?')) return;
        hsAdminAjax('hs_admin_unsuspend_user', { user_id: uid });
    });

    // ── Reset XP ──────────────────────────────────────────────────────────────
    $(document).on('click', '.hs-reset-xp', function() {
        var uid  = $(this).data('uid');
        var name = $(this).closest('tr').find('strong').first().text();
        if (!confirm('Zerar XP de ' + name + '? Esta ação não pode ser desfeita.')) return;
        hsAdminAjax('hs_admin_reset_xp', { user_id: uid });
    });

    // ── Refund Order ──────────────────────────────────────────────────────────
    $(document).on('click', '.hs-refund-order', function() {
        var oid = $(this).data('oid');
        if (!confirm('Reembolsar pedido #' + oid + '? O aluno será des-matriculado.')) return;
        hsAdminAjax('hs_admin_refund_order', { order_id: oid });
    });

    $(document).on('click', '.hs-mark-order-paid', function() {
        var oid = $(this).data('oid');
        if (!confirm('Confirmar pagamento do pedido #' + oid + ' e liberar a matrícula?')) return;
        hsAdminAjax('hs_admin_mark_order_paid', { order_id: oid });
    });

    // ── Refund Request Review ─────────────────────────────────────────────────
    $(document).on('click', '.hs-approve-refund', function() {
        var oid = $(this).data('oid');
        if (!confirm('Aprovar o reembolso do pedido #' + oid + '? O aluno será des-matriculado e notificado por e-mail.')) return;
        var note = prompt('Observação para o aluno (opcional):') || '';
        hsAdminAjax('hs_admin_review_refund', { order_id: oid, decision: 'approve', note: note });
    });

    $(document).on('click', '.hs-deny-refund', function() {
        var oid  = $(this).data('oid');
        var note = prompt('Motivo da negativa (será enviado ao aluno):');
        if (note === null) return;
        if (!note.trim()) { alert('Informe o motivo da negativa.'); return; }
        hsAdminAjax('hs_admin_review_refund', { order_id: oid, decision: 'deny', note: note });
    });

    // ── Unblock IP ────────────────────────────────────────────────────────────
    $(document).on('click', '#hs-unblock-btn', function() {
        var ip = $('#hs-unblock-ip').val().trim();
        if (!ip) { alert('Digite um IP.'); return; }
        hsAdminAjax('hs_admin_unblock_ip', { ip: ip }, function(res) {
            alert(res.data.message);
        });
    });

    // ── Commission Update ─────────────────────────────────────────────────────
    $(document).on('change', '.hs-commission-input', function() {
        var uid        = $(this).data('uid');
        var commission = $(this).val();
        hsAdminAjax('hs_admin_save_commission', { user_id: uid, commission: commission }, function(res) {
            alert(res.data.message);
        });
    });

    // ── Gateway Radio Highlight ───────────────────────────────────────────────
    $(document).on('change', 'input[name="active_gateway"]', function() {
        $('input[name="active_gateway"]').each(function() {
            var $label = $(this).closest('label');
            if ($(this).is(':checked')) {
                $label.css({ 'border-color': '#7C3AED', 'background': '#f5f0ff' });
            } else {
                $label.css({ 'border-color': '#ddd', 'background': '#fff' });
            }
        });
    });

    // ── Password field toggle (double-click) ──────────────────────────────────
    $(document).on('dblclick', 'input[type="password"]', function() {
        var type = $(this).attr('type') === 'password' ? 'text' : 'password';
        $(this).attr('type', type);
    });

    // ── JSON editor: prettify on focus ────────────────────────────────────────
    $(document).on('focus', '#hs_curriculum, #hs_quiz_questions', function() {
        try {
            var parsed = JSON.parse($(this).val());
            $(this).val(JSON.stringify(parsed, null, 2));
        } catch(e) {}
    });

    $(document).on('blur', '#hs_curriculum, #hs_quiz_questions', function() {
        try {
            var parsed = JSON.parse($(this).val());
            $(this).val(JSON.stringify(parsed));
            $(this).css('border-color', '');
        } catch(e) {
            $(this).css('border-color', '#ef4444');
        }
    });

    // ── Assinatura digital: seletor da biblioteca de mídia ────────────────────
    $(document).on('click', '.hs-sig-upload', function(e) {
        e.preventDefault();
        if (typeof wp === 'undefined' || !wp.media) { alert('Biblioteca de mídia indisponível.'); return; }
        var $field = $(this).closest('.hs-sig-field');
        var frame = wp.media({
            title: 'Escolher imagem de assinatura',
            library: { type: 'image' },
            multiple: false,
            button: { text: 'Usar esta imagem' }
        });
        frame.on('select', function() {
            var att = frame.state().get('selection').first().toJSON();
            var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
            $field.find('.hs-sig-id').val(att.id);
            $field.find('.hs-sig-preview img').attr('src', url);
            $field.find('.hs-sig-preview').show();
            $field.find('.hs-sig-remove').show();
        });
        frame.open();
    });

    $(document).on('click', '.hs-sig-remove', function(e) {
        e.preventDefault();
        var $field = $(this).closest('.hs-sig-field');
        $field.find('.hs-sig-id').val(0);
        $field.find('.hs-sig-preview').hide();
        $(this).hide();
    });

    // ── Auto-dismiss success notices ──────────────────────────────────────────
    setTimeout(function() {
        $('.notice-success').fadeOut(600);
    }, 4000);

})(window.jQuery);
