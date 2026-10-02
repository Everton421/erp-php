<?php
declare(strict_types=1);

/**
 * Funções utilitárias globais: escape, CSRF, flash, formatação,
 * conversão (padrão brasileiro), configs e log de auditoria.
 */

/* =========================================================
 * ESCAPE / SANITIZAÇÃO
 * ========================================================= */
function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function sanear(?string $valor): string
{
    return trim((string)$valor);
}

/**
 * Sanear e converter para MAIÚSCULAS (UTF-8).
 * Campos de cadastro são gravados em maiúsculas.
 * ATENÇÃO: NÃO usar para e-mail, senha ou valores numéricos.
 */
function maiusculas(?string $valor): string
{
    $v = trim((string)$valor);
    return function_exists('mb_strtoupper') ? mb_strtoupper($v, 'UTF-8') : strtoupper($v);
}

function is_ajax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/* =========================================================
 * MINIATURA DE FOTO
 * ========================================================= */
function thumb_foto(?string $foto, int $tam = 38): string
{
    if (!$foto || !file_exists(BASE_PATH . '/' . $foto)) {
        return '';
    }
    return '<img src="' . e(url($foto)) . '" width="' . $tam . '" height="' . $tam
        . '" class="rounded object-fit-cover" alt="" style="flex-shrink:0">';
}

/* =========================================================
 * URLs / REDIRECIONAMENTO
 * ========================================================= */
function url(string $caminho = ''): string
{
    return BASE_URL . '/' . ltrim($caminho, '/');
}

/**
 * URL de asset local com cache-busting (?v=timestamp do arquivo).
 * Usar sempre em vez de url(ASSETS . ...) para o navegador nunca
 * servir uma versão antiga de JS/CSS após uma alteração.
 */
function url_asset(string $caminho): string
{
    $url = url($caminho);
    if (strpos($url, '?') !== false) {
        return $url;
    }
    $arquivo = BASE_PATH . '/' . ltrim($caminho, '/');
    $versao = is_file($arquivo) ? (string)filemtime($arquivo) : '1';
    return $url . '?v=' . $versao;
}

function redirecionar(string $caminho): void
{
    header('Location: ' . url($caminho));
    exit;
}

function voltar(): void
{
    $ref = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($ref === '') {
        ir_para_inicial();
    }
    $caminho = preg_replace('#^https?://[^/]+#i', '', $ref);
    $caminho = preg_replace('#^' . preg_quote(BASE_URL, '#') . '#i', '', $caminho);
    redirecionar(ltrim($caminho, '/'));
}

/**
 * Primeira tela que o usuário logado tem permissão de acessar.
 * A ordem vai do mais específico ao mais geral: perfis operacionais como
 * Produção caem na fila de preparo em vez do painel genérico, que ambos
 * por possuírem 'consumo_ver'. Com a produção desativada a fila sai da
 * lista, e o perfil cai no painel.
 * Retorna string vazia quando o usuário não tem nenhuma permissão.
 */
function pagina_inicial(): string
{
    $rotas = ['dashboard_ver' => 'dashboard/index.php'];
    if (consumo_producao_ativa()) {
        $rotas['cozinha_ver'] = 'consumo/producao/index.php';
    }
    $rotas += [
        'caixa_consumo_ver' => 'consumo/caixa/index.php',
        'comandas_ver'      => 'consumo/comandas/index.php',
        'consumo_ver'       => 'consumo/index.php',
    ];

    foreach ($rotas as $chave => $caminho) {
        if (tem_permissao($chave)) {
            return $caminho;
        }
    }

    return '';
}

/**
 * Leva o usuário à primeira tela permitida. Sem nenhuma permissão não há
 * página possível: encerra a sessão em vez de deixá-lo num redirect inválido.
 */
function ir_para_inicial(): void
{
    $caminho = pagina_inicial();
    if ($caminho === '') {
        flash('danger', 'Seu usuário não possui nenhuma permissão de acesso.');
        redirecionar('login/logout.php');
    }
    redirecionar($caminho);
}

/* =========================================================
 * CSRF
 * ========================================================= */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verificar_csrf(): bool
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function exigir_csrf(): void
{
    if (!verificar_csrf()) {
        if (is_ajax()) {
            json_resposta(false, 'Sessão expirada. Atualize a página e tente novamente.');
        }
        flash('danger', 'Sessão expirada. Atualize a página e tente novamente.');
        voltar();
    }
}

/* =========================================================
 * FLASH MESSAGES (SweetAlert2 / toasts)
 * ========================================================= */
function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flashes'][] = ['tipo' => $tipo, 'msg' => $mensagem];
}

function proximo_flash(): ?array
{
    $f = $_SESSION['flashes'][0] ?? null;
    if ($f !== null) {
        array_shift($_SESSION['flashes']);
    }
    return $f;
}

/* =========================================================
 * JOSN (AJAX)
 * ========================================================= */
function json_resposta(bool $ok, string $mensagem = '', $dados = null, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'msg' => $mensagem, 'dados' => $dados], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================================================
 * FORMATAÇÃO (padrão brasileiro)
 * ========================================================= */
function formatar_moeda($valor): string
{
    $casas = (int)obter_config('casas_decimais', '2');
    return obter_config('moeda_simbolo', 'R$') . ' ' . number_format((float)$valor, $casas, ',', '.');
}

function formatar_qtde($valor): string
{
    return number_format((float)$valor, 3, ',', '.');
}

function formatar_numero($valor, int $casas = 2): string
{
    return number_format((float)$valor, $casas, ',', '.');
}

function formatar_data($data): string
{
    if (!$data) {
        return '';
    }
    $ts = is_numeric($data) ? (int)$data : strtotime((string)$data);
    return $ts ? date('d/m/Y', $ts) : '';
}

function formatar_datahora($data): string
{
    if (!$data) {
        return '';
    }
    $ts = is_numeric($data) ? (int)$data : strtotime((string)$data);
    return $ts ? date('d/m/Y H:i', $ts) : '';
}

function hoje(): string
{
    return date('Y-m-d');
}

/* =========================================================
 * CONVERSÃO (BR -> banco | banco -> BR)
 * ========================================================= */

/**
 * "1.234,56" -> 1234.56 | "1234.56" -> 1234.56
 */
function parse_decimal($valor): float
{
    $v = (string)$valor;
    $v = trim(str_replace(['R$', ' '], '', $v));
    if ($v === '' || $v === '-' || $v === ',') {
        return 0.0;
    }
    if (str_contains($v, ',')) {
        $v = str_replace(['.', ','], ['', '.'], $v);
    }
    return (float)$v;
}

/**
 * "05/09/2026" -> "2026-09-05" | já aceita "2026-09-05"
 */
function parse_data(?string $data): ?string
{
    $d = trim((string)$data);
    if ($d === '') {
        return null;
    }
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $d)) {
        [$dia, $mes, $ano] = explode('/', $d);
        if (checkdate((int)$mes, (int)$dia, (int)$ano)) {
            return sprintf('%04d-%02d-%02d', (int)$ano, (int)$mes, (int)$dia);
        }
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return $d;
    }
    return null;
}

function apenas_numeros(?string $valor): string
{
    return (string)preg_replace('/\D/', '', (string)$valor);
}

function validar_cpf(?string $cpf): bool
{
    $c = apenas_numeros($cpf);
    if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($i = 0; $i < $t; $i++) {
            $d += (int)$c[$i] * (($t + 1) - $i);
        }
        $d = ((10 * $d) % 11) % 10;
        if ((int)$c[$t] !== $d) {
            return false;
        }
    }
    return true;
}

function validar_cnpj(?string $cnpj): bool
{
    $c = apenas_numeros($cnpj);
    if (strlen($c) !== 14 || preg_match('/^(\d)\1{13}$/', $c)) {
        return false;
    }
    $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $calc = static function (array $pesos) use ($c): int {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += (int)$c[$i] * $peso;
        }
        $resto = $soma % 11;
        return $resto < 2 ? 0 : 11 - $resto;
    };
    return $calc($pesos1) === (int)$c[12] && $calc($pesos2) === (int)$c[13];
}

function mascarar_cpf(string $cpf): string
{
    $c = str_pad(apenas_numeros($cpf), 11, '0', STR_PAD_LEFT);
    return vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($c));
}

function mascarar_cnpj(string $cnpj): string
{
    $c = str_pad(apenas_numeros($cnpj), 14, '0', STR_PAD_LEFT);
    return vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split($c));
}

/* =========================================================
 * CONFIGURAÇÕES DO SISTEMA (cache por request)
 * ========================================================= */
function _config_cache($resetar = false)
{
    static $configs = null;
    if ($resetar) {
        $configs = null;
        return;
    }
    if ($configs === null) {
        $configs = [];
        try {
            foreach (db()->query('SELECT chave, valor FROM configs') as $linha) {
                $configs[$linha['chave']] = $linha['valor'];
            }
        } catch (Throwable $e) {
            erro_banco($e, 'obter_config');
        }
    }
    return $configs;
}

function obter_config(string $chave, $padrao = null)
{
    $configs = _config_cache();
    return array_key_exists($chave, $configs) && $configs[$chave] !== null && $configs[$chave] !== ''
        ? $configs[$chave]
        : $padrao;
}

function limpar_cache_config(): void
{
    _config_cache(true);
}

function salvar_config(string $chave, string $valor): void
{
    db()->prepare(
        'INSERT INTO configs (chave, valor, atualizada_em) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizada_em = NOW()'
    )->execute([$chave, $valor]);
    limpar_cache_config();
}

/* =========================================================
 * GERAÇÃO DE NÚMERO DE DOCUMENTOS
 * ========================================================= */
function gerar_numero(string $tabela, string $coluna): string
{
    $pdo = db();
    $ano = date('Y');
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING($coluna, 7) AS UNSIGNED)), 0) + 1
                            FROM $tabela WHERE $coluna LIKE ?");
    $stmt->execute([$ano . '%']);
    $seq = (int)$stmt->fetchColumn();
    return $ano . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
}

/* =========================================================
 * LOOKUP GENÉRICO
 * ========================================================= */
function buscar_linha(string $tabela, int $id): ?array
{
    $tabela = preg_replace('/[^a-z_]/', '', $tabela);
    $stmt = db()->prepare("SELECT * FROM $tabela WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/* =========================================================
 * LOG DE AUDITORIA
 * ========================================================= */
function registrar_log(string $modulo, string $acao, $registroId = null, $antes = null, $depois = null): void
{
    static $ip = null;
    if ($ip === null) {
        $ip = substr((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }
    $usuario = usuario_atual();
    try {
        $stmt = db()->prepare(
            'INSERT INTO logs (usuario_id, data, ip, modulo, acao, registro_id, dados_anteriores, dados_novos)
             VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $usuario ? (int)$usuario['id'] : null,
            $ip,
            $modulo,
            substr($acao, 0, 100),
            $registroId !== null ? (int)$registroId : null,
            $antes !== null ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
            $depois !== null ? json_encode($depois, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Throwable $e) {
        erro_banco($e, 'registrar_log');
    }
}

/* =========================================================
 * FLUXO DE CAIXA
 * ========================================================= */
function registrar_fluxo(string $tipo, string $categoria, string $descricao, $valor, string $referencia = null): void
{
    $tipo = in_array($tipo, ['ENTRADA', 'SAIDA'], true) ? $tipo : 'SAIDA';
    $cat = in_array($categoria, ['VENDA', 'RECEBIMENTO', 'COMPRA', 'CONTA', 'DESPESA', 'OUTRO'], true)
        ? $categoria : 'OUTRO';
    $usuario = usuario_atual();
    $stmt = db()->prepare(
        'INSERT INTO fluxo_caixa (data_movimento, tipo, categoria, descricao, valor, referencia, usuario_id)
         VALUES (NOW(), ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$tipo, $cat, $descricao, round((float)$valor, 2), $referencia, $usuario ? (int)$usuario['id'] : null]);
}

/* =========================================================
 * BADGES DE STATUS
 * ========================================================= */
function badge_status(string $status): string
{
    $classes = [
        'PENDENTE' => 'warning', 'PAGO' => 'success', 'VENCIDO' => 'danger',
        'CANCELADO' => 'secondary', 'CANCELADA' => 'secondary', 'PARCIAL' => 'info',
        'FINALIZADA' => 'success', 'ORCAMENTO' => 'info',
        'FISICA' => 'primary', 'JURIDICA' => 'secondary',
        'ABERTA' => 'primary', 'FECHADA' => 'success',
        'LIVRE' => 'success', 'OCUPADA' => 'warning', 'RESERVADA' => 'info',
        'PREPARANDO' => 'info', 'PRONTO' => 'success', 'ENTREGUE' => 'secondary',
    ];
    $cls = $classes[$status] ?? 'secondary';
    return '<span class="badge bg-' . $cls . ' text-uppercase">' . e($status) . '</span>';
}

/* =========================================================
 * UFs (Brasil)
 * ========================================================= */
function lista_ufs(): array
{
    return ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
}

function select_uf(?string $selecionado = ''): string
{
    $html = '<option value="">UF</option>';
    foreach (lista_ufs() as $uf) {
        $html .= '<option value="' . $uf . '"' . ($selecionado === $uf ? ' selected' : '') . '>' . $uf . '</option>';
    }
    return $html;
}