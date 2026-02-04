<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require_login();

$rows = [];
$res = $db->query("
    SELECT e.id, e.status, e.total, e.data_encomenda, c.nome cliente
    FROM encomendas e
    JOIN clientes c ON c.id = e.cliente_id
    ORDER BY e.id DESC
");
while ($r = $res->fetch_assoc())
    $rows[] = $r;

require __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Encomendas</h3>
    <a class="btn btn-success" href="encomenda_nova.php">Nova encomenda</a>
</div>

<div class="card shadow">
    <div class="card-body table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Data</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r) { ?>
                    <tr>
                        <td>#<?= $r['id'] ?></td>
                        <td><?= e($r['cliente']) ?></td>
                        <td><?= e($r['data_encomenda']) ?></td>
                        <td><?= e($r['status']) ?></td>
                        <td><?= number_format((float)$r['total'], 2, ',', '.') ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-light" href="encomenda_ver.php?id=<?= (int)$r['id'] ?>">Ver</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require __DIR__ . '/inc/footer.php';
?>