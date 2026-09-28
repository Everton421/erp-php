# AGENTS.md — Gestor Comercial (vendasapp)

ERP/gestor comercial em PHP 8 + MySQL/MariaDB, 100% responsivo, procedural por módulo.

## Stack e ambiente
- PHP 8 (linha de comando 8.3.x; Apache do XAMPP 8.0.x — manter compatível).
- MySQL/MariaDB **remoto** — credenciais/config em `config/config.php` (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`). **NÃO commitar a senha em git.**
- Frontend: Bootstrap 5, jQuery, DataTables, SweetAlert2, Chart.js. Assets locais em `assets/`, libs via CDN.
- Base: `http://localhost/vendasapp/` (BASE_URL auto-detectada via DOCUMENT_ROOT).

## Estrutura
- Cada módulo segue o padrão: `index.php` (listagem), `form.php` ou modal, `salvar.php` (POST), `excluir.php`/ações POST. `dashboard/` tem `index.php` + `dados.php` (APIs JSON para Chart.js).
- `config/config.php` (sessão, erros, BASE_URL, núcleo), `config/database.php` (`db()` singleton, `erro_banco()`), `config/permissoes.php`, `config/auth.php`.
- `includes/`: `functions.php` (helpers), `header.php`, `sidebar.php`, `footer.php`.
- `api/`: `produtos.php`, `clientes.php`, `busca.php` (GET com `?q=`), `cep.php` (POST, ViaCEP), `notificacoes.php`.
- `consumo/`: módulo de consumo por comandas (painel, cardápio, mesas, comandas, produção, caixa, relatórios). `consumo/dados.php` é a API JSON (`?acao=cardapio|kds|mesas`). O nome exibido é configurável em Configurações (`consumo_nome`, padrão "Consumo"), lido por `rotulo_consumo()`.
- `restaurante/index.php`: shim de compatibilidade que redireciona `/restaurante/...` → `/consumo/...` (preserva query string). Pode ser removido quando nenhum link salvo apontar para a pasta antiga.
- `sql/banco.sql`: schema completo + seeds (admin/admin123, perfis 1–6, ~56 permissões).
- `sql/consumo.sql`: 6 tabelas do módulo + 19 permissões + perfis `Atendente`/`Produção`/`Caixa` + `configs.consumo_nome` (idempotente).
- `sql/consumo_seed.sql`: categorias/itens/mesas/usuários de exemplo (idempotente, opcional).
- `sql/consumo_migracao.sql`: para bancos já importados na versão "restaurante" — renomeia módulo, 4 chaves de permissão, logs, perfis e usuários do seed (idempotente).
- Banco `vendasapp`, utf8mb4. Filtros de data: `yyyy-mm-dd`.
- Colunas legadas podem estar em `latin1_swedish_ci`: em SQL que compara tabela legada com literal utf8mb4, envolva os dois lados em `CONVERT(x USING utf8mb4)` (erro 1267).

## Regras de código
- **Prepared statements PDO obrigatórios** (`PDO::ATTR_EMULATE_PREPARES = false`). Contar placeholders antes de `execute()` — erros `HY093` já ocorreram por parâmetro a mais será com stack trace em `erro_banco()`.
- **CSRF obrigatório**: token via `csrf_token()`/`csrf_field()`; validar com `exigir_csrf()` (aceita `X-CSRF-Token` ou `_POST['csrf_token']`).
- **Permissões**: `tem_permissao()`/`exigir_permissao()` aceitam **apenas string** (não array); admin (`is_admin=1`) ignora checks. Aplicar sempre que necessário.
- **Auditoria**: `registrar_log($modulo, $acao, $registroId, $antes, $depois)` em toda criação/edição/exclusão e ações sensíveis.
- **Cadastro em MAIÚSCULAS**: campos de cadastro salvos em maiúsculas via helper `maiusculas()` (UTF-8, usa `mb_strtoupper` com fallback). **Nunca** usar em e-mail/senha/valores numéricos.
- **Moeda**: campos monetários com `data-moeda` (sem máscara ao vivo; convertido no submit por `js-converte` → "1234.56"); `data-mask` para máscaras vivas (moeda, int, cep, cpfCnpj, celular). `parse_decimal()` trata ambos. View: `formatar_moeda()`, `formatar_qtde()`, `formatar_data()`.
- **Ações de confirmação**: botões com `js-confirmar` (cria form hidden POST com CSRF). Handlers globais em `assets/js/app.js` (`APP.autocompletar`, `APP.jsConfirmar`, `APP.ajax`, `js-converte`).
- Transações: `$pdo->beginTransaction()` com `registrar_fluxo()` dentro (fluxos participam do commit/rollback). Referências de fluxo: `VENDA#id`, `COMPRA#id`, `RECEBER#id`, `PAGAR#id`.
- Cancelamento de venda/compra bloqueado se houver `valor_pago > 0`; estorno reverte estoque e fluxo e marca parcelas/contas como CANCELADO.
- Nunca adicionar comentários ao código além dos necessários; seguir o estilo existente.

## Módulo de Consumo
- Regras de domínio em `includes/consumo.php` (`comanda_buscar`, `comanda_recalcular`, `comanda_itens_pendentes`, `mesa_liberar`, `cardapio_itens`, `item_transicao_valida`, `formatar_tempo_decorrido`, `rotulo_consumo`, …). Usar os helpers em vez de SQL novo.
- Cardápio é **independente** de `produtos`/estoque; `comanda_itens` guarda a descrição/preço como snapshot.
- Status: mesa `LIVRE|OCUPADA|RESERVADA`; comanda `ABERTA|FECHADA|CANCELADA`; pagamento `PENDENTE|PARCIAL|PAGO`; item `PENDENTE|PREPARANDO|PRONTO|ENTREGUE|CANCELADO`.
- Regras obrigatórias: sem dupla ocupação de mesa; comanda só abre em mesa livre; não fecha com itens `PENDENTE`/`PREPARANDO`; transição de item validada por `item_transicao_valida()`; cancelar comanda apaga pagamentos e o recebível pendente.
- Financeiro: documento ASCII `COMANDA-{numero}` e referência de fluxo `COMANDA#{id}`; reusa `formas_pagamento`, `fluxo_caixa` e `contas_receber`. **Troco não é receita** — `caixa/acao.php` trava a comanda com `FOR UPDATE` e aplica cada forma só até o saldo; o excedente vira `comandas.troco`.
- Assets `assets/css/consumo.css` e `assets/js/consumo.js` são carregados **apenas** em páginas `consumo/` (condicional no header/footer, via `$paginaConsumo`). O namespace JS é `CONSUMO`. Atalhos: `F3` abre comanda (exige `comandas_criar`, checado via `window.PERMISSOES`).
- Nome exibido: sempre usar `rotulo_consumo()` (nunca "Restaurante" hardcoded) em menu, títulos, rótulos de permissão e cupom. Default "Consumo", editável em Configurações.
- Coluna `comandas.garcom_id` e aliases `garcom_nome`/`garcom_usuario` foram mantidos por estabilidade de schema; a persona é exibida como "Atendente".
- Permissões: `consumo_ver`, `cardapio_ver`, `cardapio_editar`, `mesas_ver`, `mesas_editar`, `mesas_excluir`, `comandas_ver`, `comandas_ver_todas`, `comandas_criar`, `comandas_item`, `comandas_fechar`, `comandas_cancelar`, `comandas_desconto`, `comandas_imprimir`, `cozinha_ver`, `cozinha_alterar_status`, `caixa_consumo_ver`, `caixa_consumo_pagar`, `consumo_relatorios`.

## Validação
- Sintaxe PHP: `php -l <arquivo>` (todos os arquivos devem passar).
- JS: `node --check assets/js/app.js` e `node --check assets/js/consumo.js`.
- SQL: importar `sql/consumo.sql` (e opcionalmente `sql/consumo_seed.sql`) duas vezes num banco temporário e conferir que as contagens não mudam; validar as queries novas com `SELECT` antes de fechar a página.
- Migração: num banco com a versão antiga, importar `sql/consumo_migracao.sql` e conferir `permissoes.modulo='consumo'`, as 4 chaves novas, `logs.modulo` e os perfis `Atendente`/`Produção`.
- Evitar `GROUP BY <alias>`: em agregação pura o `GROUP BY` é desnecessário e colide com aliases (`Can't group on 'qtd'`).
- Teste funcional: navegar em `http://localhost/vendasapp/` (login: admin/admin123).
- Verificar placeholders ao tocar em SQL com muitos parâmetros.