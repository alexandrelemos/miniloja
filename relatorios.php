<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require_login();

// filtro datas (default: últimos 30 dias)
$to = $_GET['to'] ?? date('Y-m-d');
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-29 days'));

$reDate = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($reDate, $from)) $from = date('Y-m-d', strtotime('-29 days'));
if (!preg_match($reDate, $to)) $to = date('Y-m-d');

function fetch_one(mysqli $db, string $sql, string $types = "", array $params = [])
{
    if ($types === "") return $db->query($sql)->fetch_assoc();
    $st = $db->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row;
}

// KPIs
$kpi_clientes = (int)fetch_one($db, "SELECT COUNT(*) c FROM clientes")['c'];
$kpi_produtos = (int)fetch_one($db, "SELECT COUNT(*) c FROM produtos")['c'];

$kpi_pagas = fetch_one(
    $db,
    "SELECT COUNT(*) n, COALESCE(SUM(total),0) s
   FROM encomendas
   WHERE status='PAGA' AND data_encomenda BETWEEN ? AND ?",
    "ss",
    [$from, $to]
);
$pagas_n = (int)$kpi_pagas['n'];
$pagas_s = (float)$kpi_pagas['s'];
$ticket_medio = $pagas_n ? ($pagas_s / $pagas_n) : 0;

// Vendas por dia (linha)
$labels = [];
$valores = [];
$contagens = [];

$st = $db->prepare("
  SELECT data_encomenda dia, COUNT(*) encomendas, SUM(total) total
  FROM encomendas
  WHERE status='PAGA' AND data_encomenda BETWEEN ? AND ?
  GROUP BY dia
  ORDER BY dia
");
$st->bind_param("ss", $from, $to);
$st->execute();
$res = $st->get_result();
while ($r = $res->fetch_assoc()) {
    $labels[] = $r['dia'];
    $contagens[] = (int)$r['encomendas'];
    $valores[] = (float)$r['total'];
}
$st->close();

// Top produtos (barra)
$top_prod_labels = [];
$top_prod_valores = [];
$top_prod_table = [];

$st = $db->prepare("
  SELECT p.nome produto, SUM(i.qty) unidades, SUM(i.line_total) valor
  FROM encomenda_itens i
  JOIN encomendas e ON e.id=i.encomenda_id
  JOIN produtos p ON p.id=i.produto_id
  WHERE e.status='PAGA' AND e.data_encomenda BETWEEN ? AND ?
  GROUP BY p.id, p.nome
  ORDER BY valor DESC
  LIMIT 10
");
$st->bind_param("ss", $from, $to);
$st->execute();
$res = $st->get_result();
while ($r = $res->fetch_assoc()) {
    $top_prod_table[] = $r;
    $top_prod_labels[] = $r['produto'];
    $top_prod_valores[] = (float)$r['valor'];
}
$st->close();

// Top clientes (tabela)
$top_cli = [];
$st = $db->prepare("
  SELECT c.nome cliente, COUNT(*) encomendas, SUM(e.total) total
  FROM encomendas e
  JOIN clientes c ON c.id=e.cliente_id
  WHERE e.status='PAGA' AND e.data_encomenda BETWEEN ? AND ?
  GROUP BY c.id, c.nome
  ORDER BY total DESC
  LIMIT 10
");
$st->bind_param("ss", $from, $to);
$st->execute();
$res = $st->get_result();
while ($r = $res->fetch_assoc()) $top_cli[] = $r;
$st->close();

require __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Relatórios</h3>

    <form class="d-flex gap-2" method="get">
        <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
        <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
        <button class="btn btn-outline-light">Aplicar</button>
    </form>
</div>

<div class="row g-3">
    <div class="col-md-3">
        <div class="card shadow">
            <div class="card-body">
                <div class="text-muted">Clientes</div>
                <div class="fs-2"><?= $kpi_clientes ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow">
            <div class="card-body">
                <div class="text-muted">Produtos</div>
                <div class="fs-2"><?= $kpi_produtos ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow">
            <div class="card-body">
                <div class="text-muted">Encomendas pagas</div>
                <div class="fs-2"><?= $pagas_n ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow">
            <div class="card-body">
                <div class="text-muted">Receita (PAGA)</div>
                <div class="fs-2"><?= number_format($pagas_s, 2, ',', '.') ?> €</div>
                <div class="text-muted small">Ticket médio: <?= number_format($ticket_medio, 2, ',', '.') ?> €</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-7">
        <div class="card shadow">
            <div class="card-body">
                <h5>Receita por dia</h5>
                <canvas id="chartVendas"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-body">
                <h5>Top 10 produtos (valor)</h5>
                <canvas id="chartTopProdutos"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-body table-responsive">
                <h5>Top produtos</h5>
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Unidades</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_prod_table as $r): ?>
                            <tr>
                                <td><?= e($r['produto']) ?></td>
                                <td><?= (int)$r['unidades'] ?></td>
                                <td><?= number_format((float)$r['valor'], 2, ',', '.') ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-body table-responsive">
                <h5>Top clientes</h5>
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Encomendas</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_cli as $r): ?>
                            <tr>
                                <td><?= e($r['cliente']) ?></td>
                                <td><?= (int)$r['encomendas'] ?></td>
                                <td><?= number_format((float)$r['total'], 2, ',', '.') ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
    const valores = <?= json_encode($valores, JSON_UNESCAPED_UNICODE) ?>;

    new Chart(document.getElementById('chartVendas'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Receita (€)',
                data: valores
            }]
        }
    });

    const topLabels = <?= json_encode($top_prod_labels, JSON_UNESCAPED_UNICODE) ?>;
    const topValores = <?= json_encode($top_prod_valores, JSON_UNESCAPED_UNICODE) ?>;

    new Chart(document.getElementById('chartTopProdutos'), {
        type: 'bar',
        data: {
            labels: topLabels,
            datasets: [{
                label: 'Valor (€)',
                data: topValores
            }]
        }
    });
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>