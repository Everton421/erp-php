<?php
require_once __DIR__ . '/../config/config.php';

exigir_login();
if (!tem_permissao('clientes_criar') && !tem_permissao('fornecedores_criar')) {
    json_resposta(false, 'Sem permissão para consultar CEP.', null, 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_resposta(false, 'Método não permitido.', null, 405);
}
exigir_csrf();

$cep = preg_replace('/\D/', '', (string)($_POST['cep'] ?? ''));
if (strlen($cep) !== 8) {
    json_resposta(false, 'CEP inválido.', null, 422);
}

$url = 'https://viacep.com.br/ws/' . $cep . '/json/';
$dados = null;

if (function_exists('curl_init') && $ch = curl_init()) {
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $resposta = curl_exec($ch);
    curl_close($ch);
    $dados = $resposta ? json_decode($resposta, true) : null;
}

if (!is_array($dados) && ini_get('allow_url_fopen')) {
    $resposta = @file_get_contents($url);
    $dados = $resposta ? json_decode($resposta, true) : null;
}

if (!is_array($dados) || !isset($dados['cep'])) {
    json_resposta(false, 'Não foi possível consultar o CEP. Verifique o número e tente novamente.', null, 502);
}
if (!empty($dados['erro'])) {
    json_resposta(false, 'CEP não encontrado.', null, 404);
}

json_resposta(true, '', [
    'cep' => $dados['cep'] ?? '',
    'logradouro' => $dados['logradouro'] ?? '',
    'bairro' => $dados['bairro'] ?? '',
    'cidade' => $dados['localidade'] ?? '',
    'uf' => $dados['uf'] ?? '',
]);