<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('relatorios_ver');

$filtroModulo = $_GET['modulo'] ?? '';
$filtroDe = parse_data($_GET['de'] ?? '');
$filtroAte = parse_data($_GET['ate'] ?? '');

$sql = "SELECT l.*, u.nome AS usuario_nome
          FROM logs l
          LEFT JOIN usuarios u ON u.id = l.usuario_id
         WHERE 1=1";
$params = [];
if ($filtroModulo !== '') {
    $sql .= ' AND l.modulo = ?';
    $params[] = $filtroModulo;
}
if ($filtroDe) {
    $sql .= ' AND DATE(l.data) >= ?';
    $params[] = $filtroDe;
}
if ($filtroAte) {
    $sql .= ' AND DATE(l.data) <= ?';
    $params[] = $filtroAte;
}
$sql .= ' ORDER BY l.data DESC, l.id DESC LIMIT 3000';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$modulos = db()->query('SELECT DISTINCT modulo FROM logs WHERE modulo IS NOT NULL ORDER BY modulo')->fetchAll();

$tituloPagina = 'Logs de auditoria';
include INC . 'header.php';
?>
<div class="page-header print-hide">
    <div>
        <h1><i class="bi bi-journal-check me-2"></i>Logs de auditoria</h1>
        <span class="subtitulo"><?= count($logs) ?> registro(s)</span>
    </div>
    <button class="btn btn-soft" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
</div>

<div class="card mb-3 print-hide">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Módulo</label>
                <select class="form-select form-select-sm" name="modulo">
                    <option value="">Todos</option>
                    <?php foreach ($modulos as $m): ?>
                    <option value="<?= e($m['modulo']) ?>" <?= $filtroModulo === $m['modulo'] ? 'selected' : '' ?>><?= e($m['modulo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">De</label>
                <input type="date" class="form-control form-control-sm" name="de" value="<?= e($filtroDe ?? '') ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Até</label>
                <input type="date" class="form-control form-control-sm" name="ate" value="<?= e($filtroAte ?? '') ?>">
            </div>
            <div class="col-12 col-md-auto">
                <button class="btn btn-sm btn-soft w-100"><i class="bi bi-funnel me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral" data-ordem="0" data-ordem-dir="desc">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Usuário</th>
                        <th>Módulo</th>
                        <th>Ação</th>
                        <th class="text-end">Registro</th>
                        <th>IP</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?= formatar_datahora($l['data']) ?></td>
                        <td><?= e($l['usuario_nome'] ?? '-') ?></td>
                        <td><span class="badge bg-light text-muted text-uppercase"><?= e($l['modulo'] ?? '-') ?></span></td>
                        <td class="small"><?= e($l['acao']) ?></td>
                        <td class="text-end"><?= $l['registro_id'] !== null ? (int)$l['registro_id'] : '-' ?></td>
                        <td class="small text-muted"><?= e($l['ip'] ?? '-') ?></td>
                        <td class="small text-muted" style="max-width:280px">
                            <?php
                            $legenda = '';
                            if ($l['dados_anteriores']) {
                                $legenda .= 'Antes: ' . e($l['dados_anteriores']) . ' ';
                            }
                            if ($l['dados_novos']) {
                                $legenda .= 'Depois: ' . e($l['dados_novos']);
                            }
                            echo $legenda !== '' ? $legenda : '-';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include INC . 'footer.php'; ?>