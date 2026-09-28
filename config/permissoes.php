<?php
declare(strict_types=1);

/**
 * Definição estática das permissões do sistema.
 * Precisa manter sincronia com a tabela `permissoes` do banco.
 */
function lista_permissoes(): array
{
    $rotulo = static function (): string {
        return function_exists('rotulo_consumo') ? rotulo_consumo() : 'Consumo';
    };

    return [
        'dashboard' => [
            'dashboard_ver' => 'Visualizar dashboard',
        ],
        'clientes' => [
            'clientes_ver'     => 'Clientes - Visualizar',
            'clientes_criar'   => 'Clientes - Inserir',
            'clientes_editar'  => 'Clientes - Editar',
            'clientes_excluir' => 'Clientes - Excluir',
        ],
        'fornecedores' => [
            'fornecedores_ver'     => 'Fornecedores - Visualizar',
            'fornecedores_criar'   => 'Fornecedores - Inserir',
            'fornecedores_editar'  => 'Fornecedores - Editar',
            'fornecedores_excluir' => 'Fornecedores - Excluir',
        ],
        'produtos' => [
            'produtos_ver'     => 'Produtos - Visualizar',
            'produtos_criar'   => 'Produtos - Inserir',
            'produtos_editar'  => 'Produtos - Editar',
            'produtos_excluir' => 'Produtos - Excluir',
        ],
        'cadastros' => [
            'categorias_ver'     => 'Categorias - Visualizar',
            'categorias_criar'   => 'Categorias - Inserir',
            'categorias_editar'  => 'Categorias - Editar',
            'categorias_excluir' => 'Categorias - Excluir',
            'marcas_ver'         => 'Marcas - Visualizar',
            'marcas_criar'       => 'Marcas - Inserir',
            'marcas_editar'      => 'Marcas - Editar',
            'marcas_excluir'     => 'Marcas - Excluir',
            'unidades_ver'       => 'Unidades - Visualizar',
            'unidades_criar'     => 'Unidades - Inserir',
            'unidades_editar'    => 'Unidades - Editar',
            'unidades_excluir'   => 'Unidades - Excluir',
        ],
        'estoque' => [
            'estoque_ver'     => 'Estoque - Visualizar',
            'estoque_entrada' => 'Estoque - Entrada',
            'estoque_saida'   => 'Estoque - Saída',
            'estoque_ajuste'  => 'Estoque - Ajuste',
        ],
        'vendas' => [
            'vendas_ver'            => 'Vendas - Visualizar',
            'vendas_criar'          => 'Vendas - Criar',
            'vendas_editar'         => 'Vendas - Editar',
            'vendas_cancelar'       => 'Vendas - Cancelar',
            'vendas_alterar_preco'  => 'Vendas - Alterar preço',
            'vendas_desconto'       => 'Vendas - Aplicar desconto',
        ],
        'compras' => [
            'compras_ver'     => 'Compras - Visualizar',
            'compras_criar'   => 'Compras - Criar',
            'compras_cancelar'=> 'Compras - Cancelar',
        ],
        'financeiro' => [
            'contas_receber_ver'    => 'Contas a receber - Visualizar',
            'contas_receber_baixar' => 'Contas a receber - Baixar',
            'contas_receber_editar' => 'Contas a receber - Editar',
            'contas_receber_excluir'=> 'Contas a receber - Excluir',
            'contas_pagar_ver'      => 'Contas a pagar - Visualizar',
            'contas_pagar_criar'    => 'Contas a pagar - Inserir',
            'contas_pagar_baixar'   => 'Contas a pagar - Baixar',
            'contas_pagar_editar'   => 'Contas a pagar - Editar',
            'contas_pagar_excluir'  => 'Contas a pagar - Excluir',
            'caixa_ver'             => 'Fluxo de caixa - Visualizar',
        ],
        'relatorios' => [
            'relatorios_ver' => 'Relatórios - Visualizar',
        ],
        'consumo' => [
            'consumo_ver'            => $rotulo() . ' - Visualizar painel',
            'cardapio_ver'           => 'Cardápio - Visualizar',
            'cardapio_editar'        => 'Cardápio - Criar/editar',
            'mesas_ver'              => 'Mesas - Visualizar',
            'mesas_editar'           => 'Mesas - Criar/editar',
            'mesas_excluir'          => 'Mesas - Excluir',
            'comandas_ver'           => 'Comandas - Visualizar',
            'comandas_ver_todas'     => 'Comandas - Ver todas (não só as próprias)',
            'comandas_criar'         => 'Comandas - Abrir',
            'comandas_item'          => 'Comandas - Adicionar/remover itens',
            'comandas_fechar'        => 'Comandas - Fechar',
            'comandas_cancelar'      => 'Comandas - Cancelar',
            'comandas_desconto'      => 'Comandas - Aplicar desconto/acréscimo',
            'comandas_imprimir'      => 'Comandas - Imprimir cupom',
            'cozinha_ver'            => 'Produção - Visualizar pedidos',
            'cozinha_alterar_status' => 'Produção - Alterar status',
            'caixa_consumo_ver'      => 'Caixa do ' . $rotulo() . ' - Visualizar',
            'caixa_consumo_pagar'    => 'Caixa do ' . $rotulo() . ' - Receber pagamento',
            'consumo_relatorios'     => $rotulo() . ' - Relatórios',
        ],
        'usuarios' => [
            'usuarios_ver'     => 'Usuários - Visualizar',
            'usuarios_criar'   => 'Usuários - Inserir',
            'usuarios_editar'  => 'Usuários - Editar',
            'usuarios_excluir' => 'Usuários - Excluir',
        ],
        'config' => [
            'config_ver'   => 'Configurações - Visualizar',
            'config_editar'=> 'Configurações - Editar',
        ],
        'logs' => [
            'logs_ver' => 'Logs/Auditoria - Visualizar',
        ],
    ];
}

function rotulo_modulo(string $modulo): string
{
    return [
        'dashboard' => 'Dashboard',
        'clientes' => 'Clientes',
        'fornecedores' => 'Fornecedores',
        'produtos' => 'Produtos',
        'cadastros' => 'Cadastros',
        'estoque' => 'Estoque',
        'vendas' => 'Vendas',
        'compras' => 'Compras',
        'financeiro' => 'Financeiro',
        'relatorios' => 'Relatórios',
        // 'restaurante' permanece só como rótulo legado de auditoria,
        // para logs antigos antes de sql/consumo_migracao.sql.
        'restaurante' => 'Restaurante',
        'consumo' => function_exists('rotulo_consumo') ? rotulo_consumo() : 'Consumo',
        'usuarios' => 'Usuários',
        'config' => 'Configurações',
        'logs' => 'Auditoria',
    ][$modulo] ?? $modulo;
}

/**
 * Verifica se uma permissão existe na lista estática.
 */
function permissao_existe(string $chave): bool
{
    foreach (lista_permissoes() as $perms) {
        if (array_key_exists($chave, $perms)) {
            return true;
        }
    }
    return false;
}