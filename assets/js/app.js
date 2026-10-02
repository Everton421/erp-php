/* =========================================================
   Gestor Comercial - JavaScript global
   ========================================================= */
const APP = window.APP = {};

/* ------------------ Utilidades ------------------ */
APP.uuid = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 7);

APP.paraNumero = function (valor) {
    if (valor === null || valor === undefined) return 0;
    let v = String(valor).replace(/[^0-9,.-]/g, '');
    if (v === '') return 0;
    const temVirgula = v.includes(',');
    if (temVirgula) {
        v = v.replace(/\./g, '').replace(',', '.');
    }
    const n = parseFloat(v);
    return isNaN(n) ? 0 : n;
};

APP.fmtNumero = function (valor, casas = 2) {
    const n = Number(valor) || 0;
    return n.toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas });
};

APP.fmtMoeda = function (valor, simbolo, casas) {
    const n = Number(valor) || 0;
    const s = (simbolo || 'R$') + ' ';
    return s + n.toLocaleString('pt-BR', { minimumFractionDigits: casas || 2, maximumFractionDigits: casas || 2 });
};

/* ------------------ Sidebar ------------------ */
APP.initSidebar = function () {
    $('#btnSidebarMobile, #sidebarBackdrop').on('click', function () {
        $('#sidebar').toggleClass('aberta');
        $('#sidebarBackdrop').toggleClass('visivel');
    });
    $('#btnSidebarDesktop').on('click', function () {
        $('body').toggleClass('sb-colapsada');
    });
    $(window).on('resize', function () {
        if ($(window).width() >= 992) {
            $('#sidebar').removeClass('aberta');
            $('#sidebarBackdrop').removeClass('visivel');
        }
    });
};

/* ------------------ Máscaras ------------------ */
function mascaraAplicar(elem, tipo) {
    const v = elem.value;
    if (tipo === 'cpf') {
        elem.value = mcpf(v);
    } else if (tipo === 'cnpj') {
        elem.value = mcnpj(v);
    } else if (tipo === 'cpfCnpj') {
        const d = v.replace(/\D/g, '');
        elem.value = d.length <= 11 ? mcpf(d) : mcnpj(d);
    } else if (tipo === 'cep') {
        elem.value = mcep(v);
    } else if (tipo === 'telefone') {
        elem.value = mfone(v, false);
    } else if (tipo === 'celular') {
        elem.value = mfone(v, true);
    } else if (tipo === 'data') {
        elem.value = mdata(v);
    } else if (tipo === 'moeda' || tipo === 'qtde') {
        const casas = elem.dataset.decimais ? parseInt(elem.dataset.decimais) : (tipo === 'qtde' ? 3 : 2);
        elem.value = mmoeda(v, casas);
    } else if (tipo === 'int') {
        elem.value = v.replace(/\D/g, '');
    }
}
const mcpf = (v) => {
    let d = v.replace(/\D/g, '').slice(0, 11);
    d = d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    return d;
};
const mcnpj = (v) => {
    let d = v.replace(/\D/g, '').slice(0, 14);
    d = d.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
         .replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2');
    return d;
};
const mcep = (v) => {
    let d = v.replace(/\D/g, '').slice(0, 8);
    d = d.replace(/(\d{5})(\d)/, '$1-$2');
    return d;
};
const mfone = (v, celular) => {
    let d = v.replace(/\D/g, '').slice(0, 11);
    if (d.length <= 10) {
        d = d.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2');
    } else {
        d = d.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
    }
    return d;
};
const mdata = (v) => {
    let d = v.replace(/\D/g, '').slice(0, 8);
    d = d.replace(/(\d{2})(\d)/, '$1/$2').replace(/(\d{2})(\d)/, '$1/$2');
    return d;
};
const mmoeda = (v, casas) => {
    let d = v.replace(/\D/g, '').slice(0, 13);
    if (d === '') return '';
    const mult = Math.pow(10, casas);
    const n = parseInt(d, 10) / mult;
    return n.toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas });
};

/* ------------------ Conversão ao enviar formulário ------------------ */
APP.prepararEnvios = function () {
    $(document).on('submit', 'form.js-converte', function (e) {
        $(this).find('[data-moeda], [data-qtde]').each(function () {
            const casas = this.dataset.decimais ? parseInt(this.dataset.decimais) : (this.dataset.moeda ? 2 : 3);
            this.value = APP.paraNumero(this.value).toFixed(casas);
        });
    });
};

/* ------------------ DataTables (padrão pt-BR) ------------------ */
APP.initDataTables = function () {
    if (!window.jQuery || !window.jQuery.fn.DataTable) return;

    $.extend(true, $.fn.dataTable.defaults, {
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50, -1], [10, 15, 25, 50, 'Todos']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/pt-BR.json',
            zeroRecords: 'Nenhum registro encontrado.',
            emptyTable: 'Nenhum registro encontrado.'
        }
    });

    $('.table-geral').each(function () {
        const $t = $(this);
        if ($t.data('already')) return;
        $t.data('already', 1);
        const temExport = !$t.hasClass('sem-export');
        $t.DataTable({
            responsive: true,
            order: $t.data('ordem') !== undefined ? [[$t.data('ordem'), $t.data('ordem-dir') || 'asc']] : [],
            buttons: temExport ? ['excelHtml5', 'csvHtml5', 'print'] : [],
            dom: temExport
                ? "<'row align-items-center mb-2'<'col-12 col-md-6'l><'col-12 col-md-6 dt-f text-md-end'B f>>" +
                  "<'row'<'col-12'tr>>" +
                  "<'row align-items-center mt-3'<'col-12 col-md-6'i><'col-12 col-md-6'p>>"
                : "<'row align-items-center mb-2'<'col-12 col-md-6'l><'col-12 col-md-6 dt-f text-md-end'f>>" +
                  "<'row'<'col-12'tr>>" +
                  "<'row align-items-center mt-3'<'col-12 col-md-6'i><'col-12 col-md-6'p>>"
        });
    });
};

/* ------------------ SweetAlert personalizados ------------------ */
APP.swalConfirm = function (titulo, texto, icone = 'warning', botao = 'Sim') {
    return Swal.fire({
        title: titulo,
        html: texto,
        icon: icone,
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: botao,
        cancelButtonText: 'Cancelar'
    });
};

APP.toast = function (tipo, msg) {
    const cores = { success: '#00b894', error: '#ff4d6d', warning: '#f7a541', info: '#0ea5e9' };
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: tipo === 'error' ? 'error' : tipo,
        title: msg,
        showConfirmButton: false,
        timer: 2800,
        timerProgressBar: true,
        background: '#fff',
        iconColor: cores[tipo] || cores.info
    });
};

/* ------------------ Exclusão por botão (aciona o form pai) ------------------ */
APP.confirmarExcluir = function (btnSelector, mensagem, urlConfirm) {
    $(document).on('click', btnSelector, function (e) {
        e.preventDefault();
        const $btn = $(this);
        $btn.prop('disabled', true);
        APP.swalConfirm('Excluir registro?', mensagem || 'Esta ação não poderá ser desfeita.', 'warning', 'Excluir')
            .then((r) => {
                $btn.prop('disabled', false);
                if (r.isConfirmed) {
                    const $form = $btn.closest('form');
                    if ($form.length) {
                        $form.off('submit').trigger('submit');
                    } else if (urlConfirm) {
                        window.location.href = urlConfirm;
                    }
                }
            });
    });
};

/* ------------------ AJAX genérico ------------------ */
APP.ajax = function (url, dados, onOk, onErr) {
    return $.ajax({
        url: url,
        method: 'POST',
        data: dados,
        dataType: 'json',
        beforeSend: function (xhr) {
            if (APP.csrf) xhr.setRequestHeader('X-CSRF-Token', APP.csrf);
        }
    }).done(function (res) {
        if (res && res.ok) {
            if (onOk) onOk(res);
            else if (res.msg) APP.toast('success', res.msg);
        } else {
            if (onErr) onErr(res);
            else if (res && res.msg) APP.toast('error', res.msg);
            else APP.toast('error', 'Ocorreu um erro inesperado.');
        }
    }).fail(function (xhr) {
        // Respostas 4xx/5xx do sistema já carregam { ok:false, msg }.
        // Só tratamos como falha de rede quando não há JSON para extrair.
        let res = xhr.responseJSON;
        if (!res && typeof xhr.responseText === 'string' && xhr.responseText) {
            try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
        }
        const erro = (res && typeof res === 'object')
            ? res
            : { msg: 'Falha na comunicação com o servidor.' };
        if (onErr) onErr(erro);
        else APP.toast('error', erro.msg || 'Ocorreu um erro inesperado.');
    });
};

/* ------------------ Confirmar e enviar via POST (js-confirmar) ------------------ */
APP.jsConfirmar = function () {
    $(document).on('click', '.js-confirmar', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const titulo = $btn.data('titulo') || 'Confirmar ação';
        const texto = $btn.data('texto') || 'Esta ação pode alterar dados importantes. Deseja continuar?';
        APP.swalConfirm(titulo, texto).then((r) => {
            if (!r.isConfirmed) return;
            let dados = {};
            const post = $btn.data('post');
            if (post) {
                dados = (typeof post === 'object' && post !== null) ? post : (() => { try { return JSON.parse(post); } catch (err) { return {}; } })();
            }
            dados.csrf_token = APP.csrf || '';
            const $form = $('<form method="post" action="' + $btn.data('url') + '"></form>');
            Object.keys(dados).forEach((k) => {
                $form.append($('<input type="hidden">').attr('name', k).val(dados[k]));
            });
            $('body').append($form);
            $form.submit();
        });
    });
};

/* ------------------ Flash messages ------------------ */
APP.flashes = function () {
    const $el = $('#app-flashes');
    if (!$el.length) return;
    let list = [];
    try { list = JSON.parse($el.text() || '[]'); } catch (e) {}
    list.forEach((f) => {
        APP.toast(f.tipo === 'danger' ? 'error' : f.tipo, f.msg);
    });
};

/* ------------------ Busca global ------------------ */
APP.buscaGlobal = function () {
    const $input = $('#buscaGlobal');
    if (!$input.length) return;
    const $box = $('.busca-resultados');
    let timer = null;

    $input.on('input', function () {
        const q = $(this).val().trim();
        clearTimeout(timer);
        if (q.length < 2) { $box.removeClass('aberto').empty(); return; }
        timer = setTimeout(() => {
            $.getJSON(APP.baseUrl + '/api/busca.php', { q: q })
                .done(function (res) {
                    $box.empty();
                    (res.dados || []).slice(0, 12).forEach((i) => {
                        const icone = { produto: 'box-seam', cliente: 'person-badge', fornecedor: 'truck',
                                        comanda: 'receipt-cutoff' }[i.tipo] || 'link';
                        const sub = i.sub || '';
                        $box.append(
                            '<a class="br-item" href="' + APP.baseUrl + i.url + '">' +
                            '<span class="br-tipo">' + i.tipo + '</span>' +
                            '<span class="flex-grow-1"><b>' + APP.esc(i.label) + '</b>' +
                            (sub ? '<br><small class="text-muted">' + APP.esc(sub) + '</small>' : '') + '</span>' +
                            '<i class="bi bi-arrow-right-short"></i></a>'
                        );
                    });
                    $box.addClass('aberto');
                });
        }, 280);
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.busca-global').length) $box.removeClass('aberto');
    });
};

APP.esc = function (s) {
    return $('<div>').text(s || '').html();
};

/* ------------------ Status/valores em tabelas ------------------ */
APP.formatarColunas = function () {
    $('[data-moeda-exibir]').each(function () {
        $(this).text($.trim(APP.fmtMoeda(parseFloat($(this).text()))));
    });
    $('[data-qtde-exibir]').each(function () {
        $(this).text(parseFloat($(this).text()).toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 }));
    });
};

/* ------------------ Inputs com máscaras ------------------ */
APP.initMascaras = function () {
    $(document).on('input', '[data-mask]', function () {
        mascaraAplicar(this, $(this).data('mask'));
    });
};

/* ------------------ Autocomplete genérico ------------------ */
APP.autocompletar = function ($input, url, onSelect, render) {
    const $container = $input.closest('.auto-wrapper');
    const $box = $('<div class="autocomplete-box"></div>').appendTo($container);
    let timer = null;
    let buscaAtual = 0;

    $input.data('autocompleteBox', $box);

    $input.on('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const $item = $box.find('.auto-item').first();
        if (!$item.length || !$box.is(':visible')) return;
        e.preventDefault();
        $item.trigger('click');
    });

    $input.on('input', function () {
        const q = $(this).val().trim();
        const buscaId = ++buscaAtual;
        clearTimeout(timer);
        if (q.length < 2) { $box.hide(); return; }
        timer = setTimeout(() => {
            $.getJSON(url, { q: q }).done(function (res) {
                if (buscaId !== buscaAtual || $input.val().trim() !== q) return;
                $box.empty();
                (res.dados || []).forEach((i) => {
                    const t = render ? (render(i) || {}) : {};
                    const label = t.label || (i.rotulo || i.label || i.descricao || '');
                    const sub = t.sub || (i.sub || i.detalhe || '');
                    const extra = t.extra !== undefined ? t.extra : (i.extra || '');
                    const $a = $('<a class="auto-item" href="javascript:void(0)"></a>')
                        .html('<div class="auto-desc"><b>' + APP.esc(label) + '</b>' +
                              (sub ? '<small>' + APP.esc(sub) + '</small>' : '') + '</div>' +
                              (extra ? '<div class="auto-extra">' + APP.esc(extra) + '</div>' : ''))
                        .data('item', i)
                        .appendTo($box);
                    $a.on('click', function () {
                        onSelect($(this).data('item'));
                        $box.hide();
                    });
                });
                $box.toggle(!!(res.dados || []).length);
            });
        }, 250);
    });
    $input.on('focus', function () { if ($box.children().length) $box.show(); });
    $input.on('blur', function () { setTimeout(() => $box.hide(), 160); });
};

/* ------------------ Tema Escuro / Claro ------------------ */
APP.initTema = function () {
    const $btn = $('#btnTemaDark');
    const $ico = $('#icoTema');
    const aplicarTema = function (dark) {
        if (dark) {
            $('body').addClass('dark-mode');
            $('html').addClass('dark-mode');
            $ico.removeClass('bi-moon-stars').addClass('bi-sun-fill text-warning');
            localStorage.setItem('app_tema', 'dark');
        } else {
            $('body').removeClass('dark-mode');
            $('html').removeClass('dark-mode');
            $ico.removeClass('bi-sun-fill text-warning').addClass('bi-moon-stars');
            localStorage.setItem('app_tema', 'light');
        }
    };

    const salvo = localStorage.getItem('app_tema');
    if (salvo === 'dark' || (!salvo && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        aplicarTema(true);
    } else {
        aplicarTema(false);
    }

    $btn.on('click', function () {
        const isDark = $('body').hasClass('dark-mode');
        aplicarTema(!isDark);
    });

    APP.toggleTema = function () {
        aplicarTema(!$('body').hasClass('dark-mode'));
    };
};

/* ------------------ Atalhos de Teclado Globais ------------------ */
APP.initAtalhosTeclado = function () {
    $(document).on('keydown', function (e) {
        const tag = (e.target.tagName || '').toLowerCase();
        const emCampoTexto = ['input', 'textarea', 'select'].includes(tag) || e.target.isContentEditable;

        // F2: Nova venda
        if (e.key === 'F2') {
            e.preventDefault();
            window.location.href = APP.baseUrl + '/vendas/nova.php';
            return;
        }

        // F3: Abrir comanda (consumo)
        if (e.key === 'F3' && !emCampoTexto && (window.PERMISSOES || []).indexOf('comandas_criar') !== -1) {
            e.preventDefault();
            window.location.href = APP.baseUrl + '/consumo/comandas/nova.php';
            return;
        }

        // Ctrl + K: Focar busca global
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            $('#buscaGlobal').focus().select();
            return;
        }

        // Ctrl + J: Alternar modo escuro
        if ((e.ctrlKey || e.metaKey) && (e.key === 'j' || e.key === 'J')) {
            e.preventDefault();
            if (APP.toggleTema) APP.toggleTema();
            return;
        }

        // / : Focar busca quando não estiver digitando em campo de texto
        if (e.key === '/' && !emCampoTexto) {
            e.preventDefault();
            $('#buscaGlobal').focus().select();
            return;
        }

        // ? : Abrir modal de atalhos quando fora de campos de texto
        if (e.key === '?' && !emCampoTexto) {
            e.preventDefault();
            $('#modalAtalhos').modal('show');
            return;
        }

        // Esc: Fechar busca global se aberta
        if (e.key === 'Escape') {
            $('.busca-resultados').removeClass('aberto');
            $('.autocomplete-box').hide();
        }
    });
};

/* ------------------ Central de Notificações / Alertas ------------------ */
APP.carregarNotificacoes = function () {
    const $badge = $('#badgeNotif');
    const $total = $('#notifTotalBadge');
    const $corpo = $('#notifCorpo');
    if (!$badge.length) return;

    $.getJSON(APP.baseUrl + '/api/notificacoes.php')
        .done(function (res) {
            if (!res.sucesso || !res.dados) return;
            const total = res.dados.total || 0;
            const lista = res.dados.notificacoes || [];

            if (total > 0) {
                $badge.text(total > 99 ? '99+' : total).removeClass('d-none');
                $total.text(total);
                $corpo.empty();

                lista.forEach(function (n) {
                    const $item = $(
                        '<a class="notif-item" href="' + n.url + '">' +
                        '  <i class="bi ' + (n.icone || 'bi-info-circle') + ' notif-ico ' + (n.classe || 'text-primary') + '"></i>' +
                        '  <div class="flex-grow-1">' +
                        '    <div class="fw-semibold small">' + APP.esc(n.titulo) + '</div>' +
                        '    <div class="text-muted" style="font-size:0.78rem">' + APP.esc(n.descricao) + '</div>' +
                        '  </div>' +
                        '  <i class="bi bi-chevron-right text-muted small"></i>' +
                        '</a>'
                    );
                    $corpo.append($item);
                });
            } else {
                $badge.addClass('d-none');
                $total.text('0');
                $corpo.html('<div class="p-3 text-center text-muted small"><i class="bi bi-check2-circle text-success me-1"></i>Tudo em dia! Sem alertas pendentes.</div>');
            }
        })
        .fail(function () {
            $corpo.html('<div class="p-3 text-center text-muted small">Não foi possível carregar os alertas.</div>');
        });
};

/* ------------------ Inicialização ------------------ */
$(function () {
    if (window.CSRF_TOKEN) APP.csrf = window.CSRF_TOKEN;
    if (window.BASE_URL) APP.baseUrl = window.BASE_URL;

    APP.initTema();
    APP.initSidebar();
    APP.initMascaras();
    APP.prepararEnvios();
    APP.initDataTables();
    APP.flashes();
    APP.buscaGlobal();
    APP.jsConfirmar();
    APP.formatarColunas();
    APP.initAtalhosTeclado();
    APP.carregarNotificacoes();

    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
});