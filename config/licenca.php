<?php
declare(strict_types=1);

/**
 * Licença de uso: avaliação por 30 dias e liberação permanente após chave.
 * O código de instalação é gerado na primeira execução; a chave é derivada
 * dele via HMAC-SHA256 (LICENCA_SECRETO). Gerador em ferramentas/gerar_licenca.php.
 */

define('LICENCA_DIAS_TRIAL', 30);
define('LICENCA_SECRETO', 'gc-vendasapp-9f3a8c1b-42d7-4e0f-8a55-b1c2d3e4f5a6');

function licenca_instalacao(): string
{
    $inst = (string)obter_config('licenca_instalacao', '');
    if ($inst === '') {
        $int = bin2hex(random_bytes(16));
        $inst = sprintf(
            '%s-%s-%s-%s-%s',
            substr($int, 0, 8),
            substr($int, 8, 4),
            substr($int, 12, 4),
            substr($int, 16, 4),
            substr($int, 20, 12)
        );
        salvar_config('licenca_instalacao', $inst);
    }
    return $inst;
}

function licenca_bootstrap(): void
{
    $em = (string)obter_config('licenca_instalado_em', '');
    if ($em === '') {
        salvar_config('licenca_instalado_em', date('Y-m-d H:i:s'));
    }
    licenca_instalacao();
}

function licenca_esperada(?string $instalacao = null): string
{
    $id = $instalacao ?? licenca_instalacao();
    $base = strtoupper(substr(hash_hmac('sha256', $id, LICENCA_SECRETO), 0, 24));
    $blocos = str_split($base, 4);
    return 'GC1-' . implode('-', $blocos);
}

function licenca_validar(string $chave, ?string $instalacao = null): bool
{
    $chave = strtoupper((string)preg_replace('/[^a-zA-Z0-9]/', '', trim($chave)));
    $esperada = strtoupper((string)preg_replace('/[^a-zA-Z0-9]/', '', licenca_esperada($instalacao)));
    return $chave !== '' && $esperada !== '' && hash_equals($esperada, $chave);
}

function licenca_dias_restantes(): int
{
    $em = (string)obter_config('licenca_instalado_em', '');
    if ($em === '') {
        return LICENCA_DIAS_TRIAL;
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $em);
    if (!$dt) {
        return LICENCA_DIAS_TRIAL;
    }
    $dias = (int)$dt->diff(new DateTime('now'))->format('%r%a');
    return LICENCA_DIAS_TRIAL - $dias;
}

function licenca_status(): string
{
    $chave = (string)obter_config('licenca_chave', '');
    if ($chave !== '' && licenca_validar($chave)) {
        return 'ativa';
    }
    return licenca_dias_restantes() >= 0 ? 'trial' : 'expirada';
}

function licenca_ativa(): bool
{
    return licenca_status() === 'ativa';
}

function licenca_gate(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (licenca_ativa()) {
        return;
    }
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script, '/licenca/') !== false) {
        return;
    }
    if (licenca_status() === 'expirada') {
        redirecionar('licenca/index.php');
    }
}