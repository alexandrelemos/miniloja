<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $categoria_id = (int)($_POST['categoria_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $preco = (float)($_POST['preco'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);

        if ($categoria_id <= 0 || $nome === '' || $sku === '' || $preco <= 0) {
            flash_set('danger', 'Por favor, preencha todos os campos obrigatórios.');
            redirect('produtos.php');
        }

        $stmt = $db->prepare("INSERT INTO produtos (categoria_id, nome, sku, preco, stock) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issdi", $categoria_id, $nome, $sku, $preco, $stock);
        $stmt->execute();
        $stmt->close();

        flash_set('success', 'Produto criado com sucesso.');
        redirect('produtos.php');
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE produtos SET ativo = 1-ativo WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            flash_set('info', 'Estado do produto alterado.');
        }
        redirect('produtos.php');
    }
}

$cats = [];
$res = $db->query("SELECT id, nome FROM categorias ORDER BY nome");
while ($r = $res->fetch_assoc()) {
    $cats[] = $r;
}

$rows = [];
$res = $db->query("
    SELECT p.*, c.nome categoria
    FROM produtos p
    JOIN categorias c ON c.id=p.categoria_id
    ORDER BY p.id DESC
");
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}

require __DIR__ . '/inc/header.php';
?>

<h3 class="mb-3">Produtos</h3>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-body">
                <h5>Novo produto</h5>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">

                    <div class="mb-2">
                        <label class="form-label">Categoria</label>
                        <select class="form-select" name="categoria_id" required>
                            <option value="">--</option>
                            <?php foreach ($cats as $c) { ?>
                                <option value="<?= (int)$c['id'] ?>"><?= e($c['nome']) ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Nome</label>
                        <input class="form-control" name="nome" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">SKU</label>
                        <input class="form-control" name="sku" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Preço</label>
                        <input class="form-control" type="number" step="0.01" name="preco" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Stock</label>
                        <input class="form-control" type="number" name="stock" value="0" required>
                    </div>

                    <button class="btn btn-success w-100">Guardar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-body table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>SKU</th>
                            <th>Preço</th>
                            <th>Stock</th>
                            <th>Ativo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= (int)$r['id'] ?></td>
                                <td><?= e($r['nome']) ?></td>
                                <td><?= e($r['categoria']) ?></td>
                                <td><?= e($r['sku']) ?></td>
                                <td><?= number_format((float)$r['preco'], 2, ',', '.') ?> €</td>
                                <td><?= (int)$r['stock'] ?></td>
                                <td><?= (int)$r['ativo'] ? 'Sim' : 'Não' ?></td>
                                <td class="text-end">
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <button class="btn btn-sm btn-outline-light">Ativar/Desativar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<?php require __DIR__ . '/inc/footer.php'; ?>