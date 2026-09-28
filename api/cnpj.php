<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

exigir_login();
if (!tem_permissao('clientes_criar') && !tem_permissao('clientes_editar') && !tem_permissao('fornecedores_criar') && !tem_permissao('fornecedores_editar')) {
    json_resposta(false, 'Sem permissão para consultar CNPJ.', null, 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_resposta(false, 'Método não permitido.', null, 405);
}
exigir_csrf();

$cnpj = preg_replace('/\D/', '', (string)($_POST['cnpj'] ?? ''));
if (strlen($cnpj) !== 14) {
    json_resposta(false, 'CNPJ inválido. Informe os 14 dígitos.', null, 422);
}

// 1. Tentar BrasilAPI
$url = 'https://brasilapi.com.br/api/cnpj/v1/' . $cnpj;
$dados = null;

if (function_exists('curl_init') && $ch = curl_init()) {
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'GestorComercial/1.0',
    ]);
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200 && $resposta) {
        $dados = json_decode($resposta, true);
    }
}

if (!is_array($dados) && ini_get('allow_url_fopen')) {
    $ctx = stream_context_create(['http' => ['timeout' => 6, 'user_agent' => 'GestorComercial/1.0']]);
    $resposta = @file_get_contents($url, false, $ctx);
    if ($resposta) {
        $dados = json_decode($resposta, true);
    }
}

// Tratamento retorno BrasilAPI
if (is_array($dados) && !empty($dados['razao_social'])) {
    $cepFormatado = preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', (string)($dados['cep'] ?? ''));
    json_resposta(true, 'CNPJ localizado com sucesso.', [
        'razao_social' => (string)($dados['razao_social'] ?? ''),
        'nome_fantasia' => (string)($dados['nome_fantasia'] ?? ''),
        'cnpj' => $cnpj,
        'cep' => $cepFormatado,
        'logradouro' => (string)($dados['logradouro'] ?? ''),
        'numero' => (string)($dados['numero'] ?? ''),
        'complemento' => (string)($dados['complemento'] ?? ''),
        'bairro' => (string)($dados['bairro'] ?? ''),
        'cidade' => (string)($dados['municipio'] ?? ''),
        'uf' => (string)($dados['uf'] ?? ''),
        'telefone' => (string)($dados['ddd_telefone_1'] ?? ''),
        'email' => (string)($dados['email'] ?? ''),
    ]);
}

// 2. Fallback ReceitaWS se a BrasilAPI não responder
$urlReceita = 'https://www.receitaws.com.br/v1/cnpj/' . $cnpj;
$dadosReceita = null;

if (function_exists('curl_init') && $ch = curl_init()) {
    curl_setopt_array($ch, [
        CURLOPT_URL => $urlReceita,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'GestorComercial/1.0',
    ]);
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200 && $resposta) {
        $dadosReceita = json_decode($resposta, true);
    }
}

if (is_array($dadosReceita) && ($dadosReceita['status'] ?? '') === 'OK') {
    $cepLimpo = preg_replace('/\D/', '', (string)($dadosReceita['cep'] ?? ''));
    $cepFormatado = preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', $cepLimpo);
    json_resposta(true, 'CNPJ localizado com sucesso.', [
        'razao_social' => (string)($dadosReceita['nome'] ?? ''),
        'nome_fantasia' => (string)($dadosReceita['fantasia'] ?? ''),
        'cnpj' => $cnpj,
        'cep' => $cepFormatado,
        'logradouro' => (string)($dadosReceita['logradouro'] ?? ''),
        'numero' => (string)($dadosReceita['numero'] ?? ''),
        'complemento' => (string)($dadosReceita['complemento'] ?? ''),
        'bairro' => (string)($dadosReceita['bairro'] ?? ''),
        'cidade' => (string)($dadosReceita['municipio'] ?? ''),
        'uf' => (string)($dadosReceita['uf'] ?? ''),
        'telefone' => (string)($dadosReceita['telefone'] ?? ''),
        'email' => (string)($dadosReceita['email'] ?? ''),
    ]);
}

json_resposta(false, 'Não foi possível localizar os dados deste CNPJ na Receita Federal.', null, 404);
