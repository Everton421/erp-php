<?php
declare(strict_types=1);

/**
 * Fecha o layout: área de conteúdo, scripts globais e modal de senha.
 */

$flashes = [];
while (($f = proximo_flash()) !== null) {
    $flashes[] = $f;
}
?>
        </main>
    </div>

    <!-- Modal alterar senha -->
    <div class="modal fade" id="modalSenha" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formSenha" class="js-converte">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-key me-2"></i>Alterar senha</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="senhaAtual">Senha atual</label>
                            <input type="password" class="form-control" id="senhaAtual" name="senha_atual" required autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="senhaNova">Nova senha</label>
                            <input type="password" class="form-control" id="senhaNova" name="senha_nova" required minlength="6" autocomplete="new-password">
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="senhaNova2">Confirmar nova senha</label>
                            <input type="password" class="form-control" id="senhaNova2" name="senha_nova2" required minlength="6" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnSenha"><i class="bi bi-check-lg me-1"></i>Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
        window.BASE_URL   = <?= json_encode(BASE_URL) ?>;
        window.PERMISSOES = <?= json_encode(array_values(permissao_carregadas())) ?>;
        window.ROTULOS    = <?= json_encode([
            'consumo'  => rotulo_consumo(),
            'producao' => rotulo_producao(),
        ], JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <?php if (!empty($paginaConsumo)): ?>
    <script src="<?= url(ASSETS . '/js/consumo.js') ?>"></script>
    <?php endif; ?>
    <script>
        $(function () {
            $('#formSenha').on('submit', function (e) {
                e.preventDefault();
                const dados = $(this).serializeArray().reduce((a, f) => { a[f.name] = f.value; return a; }, {});
                if (dados.senha_nova !== dados.senha_nova2) {
                    return APP.toast('error', 'As senhas não conferem.');
                }
                $('#btnSenha').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
                APP.ajax(APP.baseUrl + '/login/senha.php', dados, function () {
                    $('#modalSenha').modal('hide');
                    $('#formSenha')[0].reset();
                    $('#btnSenha').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Salvar');
                    APP.toast('success', 'Senha alterada com sucesso!');
                }, function (res) {
                    $('#btnSenha').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Salvar');
                    APP.toast('error', res && res.msg ? res.msg : 'Não foi possível alterar a senha.');
                });
            });
        });
    </script>
    <div id="app-flashes" class="oculto"><?= e(json_encode($flashes, JSON_UNESCAPED_UNICODE)) ?></div>
</body>
</html>