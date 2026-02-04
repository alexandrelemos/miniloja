<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require_login();

$clientes = [];
$res = $db->query("SELECT id,nome FROM clientes ORDER BY nome");
while ($r = $res->fetch_assoc()) {
    $clientes[] = $r;
}

$produtos = [];
$res = $db->query("SELECT id, nome, preco, stock FROM produtos WHERE ativo=1 ORDER BY nome");
while ($r = $res->fetch_assoc()) {
    $produtos[] = $r;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    $notas = trim($_POST['notas'] ?? '');

    $pids = $_POST['produto_id'] ?? [];
    $qtds = $_POST['qtd'] ?? [];

    $items = [];
    for ($i = 0; $i < count($pids); $i++) {
        $pid = (int)$pids[$i];
        $q = (int)$qtds[$i];
        if ($pid > 0 && $q > 0)
            $items[] = ['pid' => $pid, 'qtd' => $q];
    }

    if ($cliente_id <= 0 || count($items) === 0) {
        flash_set('danger', "Escolhe o cliente e pelo menos 1 item.");
        redirect('encomenda_nova.php');
    }

    try {
        $db->begin_transaction();

        $notasOrNull = ($notas === '') ? null : $notas;

        // 1) Criar encomenda
        $stmtEnc = $db->prepare("INSERT INTO encomendas (cliente_id, notas) VALUES (?, ?)");
        $stmtEnc->bind_param("is", $cliente_id, $notasOrNull);
        $stmtEnc->execute();
        $stmtEnc->close();

        $encomenda_id = (int)$db->insert_id;

        // 2) statements reutilizáveis
        $stmtSel = $db->prepare("SELECT preco, stock FROM produtos WHERE id=? AND ativo=1 FOR UPDATE");
        $stmtIns = $db->prepare("
            INSERT INTO encomenda_itens (encomenda_id, produto_id, qty, unit_price, line_total) 
            VALUES (?,?,?,?,?)
        ");
        $stmtUpd = $db->prepare("UPDATE produtos SET stock = stock - ? WHERE id=?");

        $total = 0.0;

        foreach ($items as $it) {
            $pid = $it['pid'];
            $qty = $it['qtd'];

            // lock + ler preço/stock
            $stmtSel->bind_param("i", $pid);
            $stmtSel->execute();
            $row = $stmtSel->get_result()->fetch_assoc();

            if (!$row)
                throw new Exception("Produto inválido (id=$pid).");

            $unit_price = (float)$row['preco'];
            $stock = (int)$row['stock'];

            if ($stock < $qty)
                throw new Exception("Stock insuficiente para o produto id=$pid (stock=$stock, pedido=$qty).");

            $line_total = $unit_price * $qty;
            $total += $line_total;

            // inserir linha
            $stmtIns->bind_param("iiidd", $encomenda_id, $pid, $qty, $unit_price, $line_total);
            $stmtIns->execute();

            // abater stock
            $stmtUpd->bind_param("ii", $qty, $pid);
            $stmtUpd->execute();
        }

        $stmtSel->close();
        $stmtIns->close();
        $stmtUpd->close();

        // 3) Atualizar total da encomenda
        $stmtTot = $db->prepare("UPDATE encomendas SET total = ? WHERE id=?");
        $stmtTot->bind_param("di", $total, $encomenda_id);
        $stmtTot->execute();
        $stmtTot->close();

        $db->commit();

        flash_set('success', "Encomenda criada (transação concluída).");
        //redirect('encomenda_ver.php?id=' . $encomenda_id);
        redirect('encomendas.php');
    } catch (Throwable $e) {
        $db->rollback();
        flash_set('danger', "Erro: " . $e->getMessage());
        redirect('encomenda_nova.php');
    }
}

require __DIR__ . '/inc/header.php';
?>

<h3 class="mb-3">Nova encomenda</h3>

<div class="card shadow">
    <div class="card-body">
        <form method="post">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Cliente</label>
                    <select class="form-select" name="cliente_id" required>
                        <option value="">--</option>
                        <?php foreach ($clientes as $c) { ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['nome']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notas</label>
                    <input class="form-control" name="notas">
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Itens</h5>
                <button type="button" class="btn btn-outline-light btn-sm" data-add-row>+ Adicionar linha</button>
            </div>

            <div class="table-responsive mt-3">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th style="width: 140px;">Quantidade</th>
                            <th style="width: 80px;"></th>
                        </tr>
                    </thead>
                    <tbody id="items-body">
                        <tr>
                            <td>
                                <select class="form-select" name="produto_id[]">
                                    <option value="">--</option>
                                    <?php foreach ($produtos as $p) { ?>
                                        <option value="<?= (int)$p['id'] ?>">
                                            <?= e($p['nome']) ?> (<?= number_format((float)$p['preco'], 2, ',', '.') ?>€ | stock <?= (int)$p['stock'] ?>)
                                        </option>
                                    <?php } ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" min="1" class="form-control" value="1" name="qtd[]">
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm" data-remove-row>Remover</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <template id="item-template">
                <tr>
                    <td>
                        <select class="form-select" name="produto_id[]">
                            <option value="">--</option>
                            <?php foreach ($produtos as $p) { ?>
                                <option value="<?= (int)$p['id'] ?>">
                                    <?= e($p['nome']) ?> (<?= number_format((float)$p['preco'], 2, ',', '.') ?>€ | stock <?= (int)$p['stock'] ?>)
                                </option>
                            <?php } ?>
                        </select>
                    </td>
                    <td>
                        <input type="number" min="1" class="form-control" value="1" name="qtd[]">
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-row>Remover</button>
                    </td>
                </tr>
            </template>

            <button type="submit" class="btn btn-success w-100 mt-3">Criar encomenda</button>
        </form>
    </div>
</div>

<?php
require __DIR__ . '/inc/footer.php';
?>