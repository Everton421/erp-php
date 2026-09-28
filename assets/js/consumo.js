/* =========================================================
   Módulo de Consumo — JavaScript
   Depende de assets/js/app.js (helpers APP.*) e do layout padrão.
   ========================================================= */
const CONSUMO = window.CONSUMO = {};

CONSUMO.url = function (caminho) {
    return (APP.baseUrl || '/vendasapp') + '/' + caminho;
};

CONSUMO.post = function (url, dados, onOk, onErr) {
    return APP.ajax(url, dados, onOk, onErr);
};

CONSUMO.acao = function (arquivo, dados, onOk, onErr) {
    return APP.ajax(CONSUMO.url('consumo/' + arquivo), dados, onOk, onErr);
};

CONSUMO.posts = function (arquivo, dados) {
    return $.post(CONSUMO.url('consumo/' + arquivo), dados, null, 'json').fail(function () {
        APP.toast('error', 'Falha na comunicação com o servidor.');
    });
};

/* ------------------ Seletor de cardápio ------------------ */
CONSUMO.cardapio = {
    categorias: [],
    itens: [],

    montar: function ($alvo) {
        const self = this;
        if (!$alvo || !$alvo.length) return;
        if (self.itens.length) {
            self.render($alvo);
            return;
        }
        self.posts('dados.php?acao=cardapio', {}).done(function (res) {
            if (!res || !res.ok) {
                $alvo.html('<div class="text-center text-muted small py-3">Cardápio indisponível.</div>');
                return;
            }
            self.categorias = res.dados.categorias || [];
            self.itens = res.dados.itens || [];
            self.render($alvo);
        });
    },

    render: function ($alvo) {
        const self = this;
        if (!self.itens.length) {
            $alvo.html('<div class="text-center text-muted small py-3">Nenhum item ativo no cardápio.</div>');
            return;
        }
        const html = self.categorias.map(function (cat) {
            const itens = self.itens.filter(function (i) { return (i.categoria_id || 0) === (cat.id || 0); });
            if (!itens.length) return '';
            return '<div class="categoria-bloco"><h4>' + APP.esc(cat.nome) + '</h4><div class="cardapio-picker">'
                + itens.map(function (i) {
                    return '<button type="button" class="cardapio-btn js-add-item" data-item=\''
                        + APP.esc(JSON.stringify(i)) + '\'>'
                        + (cat.cor ? '<span class="c" style="background:' + APP.esc(cat.cor) + '"></span>' : '')
                        + '<span class="n">' + APP.esc(i.descricao) + '</span>'
                        + '<span class="p">' + APP.fmtMoeda(i.preco) + '</span></button>';
                }).join('')
                + '</div></div>';
        }).join('');
        $alvo.html(html || '<div class="text-center text-muted small py-3">Nenhum item ativo no cardápio.</div>');
    }
};

/* ------------------ Comanda: adição de itens ------------------ */
$(document).on('click', '.js-add-item', function () {
    let item = {};
    try {
        item = JSON.parse($(this).attr('data-item') || '{}');
    } catch (e) {
        return;
    }

    const $form = $('#formItemComanda');
    if (!$form.length) return;

    $form.find('[name="item_id"]').val(item.id);
    $form.find('[name="qtd"]').val(1);
    $form.trigger('submit');
});

/* ------------------ Comanda: impressão do cupom ------------------ */
$(document).on('click', '.js-imprimir', function (e) {
    e.preventDefault();
    const comandaId = $('[name="comanda_id"]').val() || $('[data-comanda]').data('comanda');
    window.open(CONSUMO.url('consumo/comandas/cupom.php?id=' + comandaId),
        '_blank', 'width=380,height=600');
});

/* ------------------ Cozinha (KDS) ------------------ */
CONSUMO.kds = {
    intervalo: null,
    telaCheia: false,
    audio: null,
    ultimosTons: {},
    limite: 15,
    critico: 25,

    audioContexto: function () {
        if (this.audio) return this.audio;
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return null;
        this.audio = new AC();
        return this.audio;
    },

    tocar: function (frequencia, duracao) {
        const ctx = this.audioContexto();
        if (!ctx) return;
        if (ctx.state === 'suspended') ctx.resume();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = frequencia;
        gain.gain.setValueAtTime(0.0001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + duracao);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + duracao);
    },

    bim: function () {
        this.tocar(880, 0.14);
        setTimeout(() => this.tocar(1180, 0.2), 170);
    },

    ligarSom: function () {
        const ctx = this.audioContexto();
        if (ctx && ctx.state === 'suspended') ctx.resume();
        APP.toast('success', 'Som de ' + ROTULOS.producao + ' ativado.');
    },

    aoCarregar: function (pedidos, prontosAntes) {
        if (!Array.isArray(pedidos)) return;
        const prontosAgora = [];
        pedidos.forEach(function (p) { if (p.status === 'PRONTO') prontosAgora.push(p.item_id); });
        if (typeof prontosAntes === 'undefined' || !this.ultimosTons.PRONTO) {
            this.ultimosTons.PRONTO = prontosAgora;
            return;
        }
        const novos = prontosAgora.filter((id) => this.ultimosTons.PRONTO.indexOf(id) === -1);
        this.ultimosTons.PRONTO = prontosAgora;
        if (novos.length) this.bim();
    },

    card: function (p) {
        const min = p.minutos || 0;
        const atraso = min > this.limite;
        const critico = min > this.critico;
        const tempoCls = critico ? 'critico' : (atraso ? 'atrasado' : '');

        const linhas = (p.itens || []).map(function (i) {
            return '<div class="item"><span class="qtd">' + APP.fmtNumero(i.qtd, 0) + 'x</span>'
                + '<span>' + APP.esc(i.descricao) + '</span></div>'
                + (i.observacao ? '<div class="obs"><i class="bi bi-info-circle"></i>'
                    + APP.esc(i.observacao) + '</div>' : '');
        }).join('');

        const acao = p.status === 'PENDENTE'
            ? '<button type="button" class="btn btn-sm btn-primary w-100 mt-2 js-kds-status"'
                + ' data-item="' + p.item_id + '" data-status="PREPARANDO">'
                + '<i class="bi bi-fire me-1"></i>Iniciar preparo</button>'
            : (p.status === 'PREPARANDO'
                ? '<button type="button" class="btn btn-sm btn-success w-100 mt-2 js-kds-status"'
                    + ' data-item="' + p.item_id + '" data-status="PRONTO">'
                    + '<i class="bi bi-check2-circle me-1"></i>Marcar pronto</button>'
                : '<button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2 js-kds-status"'
                    + ' data-item="' + p.item_id + '" data-status="ENTREGUE">'
                    + '<i class="bi bi-bag-check me-1"></i>Concluir entrega</button>');

        return '<div class="kds-pedido ' + p.status + (atraso ? ' ATRASADO' : '') + '">'
            + '<div class="cab">'
            + '<span class="mesa">' + APP.esc(p.mesa) + '</span>'
            + '<span class="tempo ' + tempoCls + '" data-minutos="' + min + '">'
            + '<i class="bi bi-clock me-1"></i>' + min + ' min</span>'
            + '</div>'
            + (p.comanda ? '<div class="garcom"><span>Comanda ' + APP.esc(p.comanda) + '</span></div>' : '')
            + linhas
            + '<div class="garcom"><span>' + APP.esc(p.garcom) + '</span></div>'
            + acao
            + '</div>';
    },

    render: function (res) {
        const $alvo = $('[data-kds-colunas]');
        if (!$alvo.length) return;
        const d = res.dados || {};
        const pend = d.PENDENTE || [];
        const prep = d.PREPARANDO || [];
        const pront = d.PRONTO || [];

        this.aoCarregar(pend.concat(prep, pront), d.ultimos_prontos);

        $('[data-kds-count="pendente"]').text(pend.length);
        $('[data-kds-count="preparando"]').text(prep.length);
        $('[data-kds-count="pronto"]').text(pront.length);
        $('[data-kds-count="atrasado"]').text(pend.concat(prep).filter((p) => (p.minutos || 0) > this.limite).length);

        const bloco = function (itens) {
            if (!itens.length) return '<div class="text-center text-muted small py-3">Nada por aqui.</div>';
            return itens.map((p) => this.card(p)).join('');
        };

        $('[data-kds-lista="PENDENTE"]').html(bloco(pend));
        $('[data-kds-lista="PREPARANDO"]').html(bloco(prep));
        $('[data-kds-lista="PRONTO"]').html(bloco(pront));
    },

    atualizar: function () {
        const self = this;
        $.getJSON(CONSUMO.url('consumo/dados.php') + '?acao=kds&t=' + Date.now(), null, function (res) {
            if (res && res.ok) self.render(res);
        });
    },

    iniciar: function (segundos) {
        const self = this;
        this.atualizar();
        this.intervalo = setInterval(function () { self.atualizar(); }, (segundos || 10) * 1000);
    },

    cronometros: function () {
        $('[data-minutos]').each(function () {
            const $el = $(this);
            const base = parseInt($el.data('minutos'), 10) || 0;
            const $pedido = $el.closest('.kds-pedido');
            $pedido.data('inicio', $pedido.data('inicio') || Date.now());
            const decorridos = base + Math.floor((Date.now() - $pedido.data('inicio')) / 60000);
            $el.find('i').remove();
            $el.append(document.createTextNode(decorridos + ' min'));
            if (decorridos > self.critico) $el.addClass('critico').removeClass('atrasado');
            else if (decorridos > self.limite) $el.addClass('atrasado').removeClass('critico');
        });
    },

    telaCheia: function (ligar) {
        this.telaCheia = ligar;
        $('body').toggleClass('kds-tela-cheia', ligar);
        const icone = ligar ? 'bi-fullscreen-exit' : 'bi-fullscreen';
        $('.js-kds-tela i').attr('class', 'bi ' + icone);
    }
};

$(document).on('click', '.js-kds-status', function (e) {
    e.preventDefault();
    const $btn = $(this);
    $btn.prop('disabled', true);
    CONSUMO.acao('cozinha/acao.php', {
        csrf_token: APP.csrf,
        item_id: $btn.data('item'),
        status: $btn.data('status')
    }, function () {
        CONSUMO.kds.atualizar();
    }, function (res) {
        APP.toast('error', (res && res.msg) || 'Não foi possível alterar o status.');
        $btn.prop('disabled', false);
    });
});

$(document).on('click', '.js-kds-tela', function () {
    CONSUMO.kds.telaCheia(!CONSUMO.kds.telaCheia);
});

$(document).on('click', '.js-kds-som', function () {
    CONSUMO.kds.ligarSom();
});

/* ------------------ Mesas: atualização ao vivo ------------------ */
CONSUMO.mesas = {
    intervalo: null,

    card: function (m) {
        const badge = m.status === 'LIVRE' ? 'success' : (m.status === 'OCUPADA' ? 'warning' : 'info');
        const ocupada = m.status === 'OCUPADA' && m.comanda_id;

        const acao = ocupada
            ? '<a href="' + CONSUMO.url('consumo/comandas/ver.php?id=' + m.comanda_id) + '"'
                + ' class="btn btn-sm btn-warning"><i class="bi bi-receipt-cutoff me-1"></i>Abrir</a>'
            : (m.status === 'RESERVADA'
                ? '<a href="' + CONSUMO.url('consumo/mesas/form.php?id=' + m.id) + '"'
                    + ' class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Editar</a>'
                : '<a href="' + CONSUMO.url('consumo/comandas/nova.php?mesa_id=' + m.id) + '"'
                    + ' class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Comanda</a>');

        const detalhe = ocupada
            ? '<div class="mesa-meta"><span><i class="bi bi-receipt me-1"></i>Comanda ' + APP.esc(m.comanda) + '</span></div>'
                + '<div class="mesa-meta"><span><i class="bi bi-person me-1"></i>' + APP.esc(m.garcom) + '</span>'
                + '<span><i class="bi bi-clock me-1"></i>' + APP.esc(m.tempo) + '</span></div>'
            : '';

        return '<div class="mesa-card ' + m.status + (m.ativo ? '' : ' INATIVA') + '" data-mesa-id="' + m.id + '">'
            + '<div class="d-flex justify-content-between align-items-start"><div>'
            + '<div class="mesa-numero">' + APP.esc(m.numero) + '</div>'
            + (m.nome ? '<div class="mesa-nome">' + APP.esc(m.nome) + '</div>' : '')
            + '</div><span class="badge text-bg-' + badge + ' text-uppercase">' + m.status + '</span></div>'
            + '<div class="mesa-meta"><span><i class="bi bi-people me-1"></i>' + (parseInt(m.capacidade, 10) || 0)
            + ' lugares</span>'
            + (m.ativo ? '' : '<span class="badge bg-secondary">Inativa</span>') + '</div>'
            + detalhe
            + '<div class="mesa-rodape"><span class="valor">' + (ocupada ? APP.fmtMoeda(m.total) : '&nbsp;') + '</span>'
            + acao + '</div></div>';
    },

    render: function (res) {
        const $alvo = $('[data-mesa-grid]');
        if (!$alvo.length) return;
        const mesas = (res.dados && res.dados.mesas) || [];
        if (!mesas.length) {
            $alvo.html('<div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5">'
                + '<i class="bi bi-grid-3x3-gap d-block fs-1 mb-2"></i>Nenhuma mesa encontrada.</div></div></div>');
            return;
        }
        $alvo.html(mesas.map((m) => this.card(m)).join(''));
    },

    atualizar: function () {
        const $grid = $('[data-mesa-grid]');
        const filtro = ($grid.data('status') || 'todos');
        const url = CONSUMO.url('consumo/dados.php') + '?acao=mesas&status=' + encodeURIComponent(filtro)
            + '&t=' + Date.now();
        $.getJSON(url, null, function (res) {
            if (res && res.ok) CONSUMO.mesas.render(res);
        });
    },

    iniciar: function (segundos) {
        this.atualizar();
        this.intervalo = setInterval(() => this.atualizar(), (segundos || 15) * 1000);
    }
};

$(document).on('click', '.js-liberar-mesa', function (e) {
    e.preventDefault();
    e.stopPropagation();
    const $btn = $(this);
    APP.swalConfirm('Liberar mesa?', 'A reserva será removida e a mesa ficará livre.', 'warning', 'Liberar')
        .then(function (r) {
            if (!r.isConfirmed) return;
            CONSUMO.acao('mesas/acao.php', {
                csrf_token: APP.csrf,
                mesa_id: $btn.data('mesa')
            }, function (res) {
                APP.toast('success', res.msg || 'Mesa liberada.');
                CONSUMO.mesas.atualizar();
            });
        });
});

/* ------------------ Caixa: pagamento multi-tenderer ------------------ */
$(document).on('click', '.js-add-pg', function () {
    const $linha = $(this).closest('.pg-linha');
    const $clone = $linha.clone();
    $clone.find('input').val('');
    $clone.find('[name="pg_valor"]').val('0.00');
    $linha.after($clone);
    recalcularPagamento();
});

$(document).on('click', '.js-remove-pg', function () {
    const $linhas = $('.pg-linha');
    if ($linhas.length <= 1) {
        APP.toast('warning', 'Informe ao menos uma forma de pagamento.');
        return;
    }
    $(this).closest('.pg-linha').remove();
    recalcularPagamento();
});

function recalcularPagamento() {
    const saldo = APP.paraNumero($('[name="comanda_saldo"]').val());
    let pago = 0;
    $('.pg-linha [name="pg_valor"]').each(function () {
        pago += APP.paraNumero($(this).val());
    });
    const falta = saldo - pago;
    const troco = pago > saldo ? pago - saldo : 0;

    $('[data-pg-pago]').text(APP.fmtMoeda(pago));
    $('[data-pg-troco]').text(APP.fmtMoeda(troco));
    $('[data-pg-falta]').text(APP.fmtMoeda(falta > 0 ? falta : 0));
    $('[data-pg-resumo]').text(APP.fmtMoeda(falta > 0 ? falta : 0));

    const $troco = $('[data-pg-troco]');
    const $falta = $('[data-pg-falta]');
    $troco.toggleClass('troco', troco > 0);
    $falta.toggleClass('falta', falta > 0);

    $('.js-pg-confirmar').prop('disabled', pago <= 0);
}

$(document).on('input change', '.pg-linha [name="pg_valor"]', function () {
    recalcularPagamento();
});

$(document).on('click', '.js-pg-troco-exato', function () {
    const saldo = APP.paraNumero($('[name="comanda_saldo"]').val());
    const $primeiraVazia = $('.pg-linha [name="pg_valor"]').filter(function () {
        return APP.paraNumero($(this).val()) <= 0;
    }).first();
    if (!$primeiraVazia.length) return;
    const pago = $('.pg-linha [name="pg_valor"]').toArray()
        .reduce((s, el) => s + APP.paraNumero($(el)), 0);
    $primeiraVazia.val(Math.max(0, saldo - pago).toFixed(2));
    recalcularPagamento();
});

$(document).on('click', '.js-pg-confirmar', function (e) {
    e.preventDefault();
    const $btn = $(this);
    const comandaId = $('[name="comanda_id"]').val();
    const pagamentos = [];

    $('.pg-linha').each(function () {
        const forma = $(this).find('[name="pg_forma"]').val();
        const valor = APP.paraNumero($(this).find('[name="pg_valor"]').val());
        const parcelas = parseInt($(this).find('[name="pg_parcelas"]').val(), 10) || 1;
        if (forma && valor > 0) pagamentos.push({ forma: forma, valor: valor, parcelas: parcelas });
    });

    if (!pagamentos.length) {
        APP.toast('warning', 'Informe ao menos um pagamento.');
        return;
    }

    $btn.prop('disabled', true);
    CONSUMO.acao('caixa/acao.php', {
        csrf_token: APP.csrf,
        comanda_id: comandaId,
        pagamentos: JSON.stringify(pagamentos)
    }, function (res) {
        Swal.fire({
            icon: 'success',
            title: 'Pagamento registrado',
            html: res.msg || 'Comanda quitada com sucesso.',
            confirmButtonText: 'Voltar ao caixa'
        }).then(function () {
            window.location.href = CONSUMO.url('consumo/caixa/index.php');
        });
    }, function (res) {
        $btn.prop('disabled', false);
    });
});

/* ------------------ Relatórios ------------------ */
CONSUMO.grafico = function (alvo, dados, opcoes) {
    const el = document.getElementById(alvo);
    if (!el || typeof Chart === 'undefined') return;
    const op = Object.assign({
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12 } }
        },
        scales: { y: { beginAtZero: true } }
    }, opcoes || {});

    if (Chart.getChart(el)) Chart.getChart(el).destroy();
    new Chart(el, Object.assign({ type: 'bar', data: dados }, op));
};

/* ------------------ Relógio da tela ------------------ */
CONSUMO.relogio = function (alvo) {
    const el = document.querySelector(alvo || '[data-relogio]');
    if (!el) return;
    const tick = function () {
        el.textContent = new Date().toLocaleTimeString('pt-BR');
    };
    tick();
    setInterval(tick, 1000);
};

/* ------------------ Inicialização ------------------ */
$(function () {
    CONSUMO.relogio();

    if ($('[data-kds-colunas]').length) {
        const seg = parseInt($('[data-kds-colunas]').data('intervalo'), 10) || 10;
        CONSUMO.kds.iniciar(seg);
        CONSUMO.kds.cronometros();
        setInterval(function () { CONSUMO.kds.cronometros(); }, 30000);
    }

    if ($('[data-mesa-grid]').length) {
        const seg = parseInt($('[data-mesa-grid]').data('intervalo'), 10) || 15;
        CONSUMO.mesas.iniciar(seg);
    }

    if ($('[data-cardapio-picker]').length) {
        CONSUMO.cardapio.montar($('[data-cardapio-picker]'));
    }

    if ($('[data-pg-pago]').length) {
        recalcularPagamento();
    }
});
