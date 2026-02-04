<?php

/*
    NOTA IMPORTANTE:
    Para que este projeto funcione corretamente, é necessário executar esta instrução SQL na base de dados.
    Isto adiciona a coluna 'canceled_at' à tabela 'encomendas', permitindo registar a data e hora em que uma encomenda foi cancelada.

    ALTER TABLE encomendas
    ADD COLUMN canceled_at DATETIME NULL,
    ADD INDEX idx_enc_canceled (canceled_at);
*/

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0)
    redirect('encomendas.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    // ===== CANCELAR ENCOMENDA =====
    if ($action === 'cancel') {
        try {
            $db->begin_transaction();

            // 1) lock na encomenda
            $stmt = $db->prepare("SELECT status FROM encomendas WHERE id=? FOR UPDATE");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $enc = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$enc) throw new Exception("Encomenda não existe.");

            if ($enc['status'] === 'CANCELADA') {
                throw new Exception("Esta encomenda já está cancelada.");
            }

            if ($enc['status'] === 'PAGA') {
                throw new Exception("Encomenda PAGA: já não é possível cancelar.");
            }

            // 2) bloquear itens (e obter quantidades)
            $stmt = $db->prepare("SELECT produto_id, qty FROM encomenda_itens WHERE encomenda_id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $itens = [];
            while ($r = $res->fetch_assoc())
                $itens[] = $r;
            $stmt->close();

            if (!$itens)
                throw new Exception("Sem itens para repor stock.");

            // 3) repor stock
            $stmtUpd = $db->prepare("UPDATE produtos SET stock = stock + ? WHERE id=?");
            foreach ($itens as $i) {
                $qty = (int)$i['qty'];
                $pid = (int)$i['produto_id'];
                $stmtUpd->bind_param("ii", $qty, $pid);
                $stmtUpd->execute();
            }
            $stmtUpd->close();

            // 4) marcar encomenda cancelada
            $stmt = $db->prepare("UPDATE encomendas SET status='CANCELADA', canceled_at=NOW() WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $db->commit();
            flash_set('warning', 'Encomenda cancelada e stock reposto (transação OK).');
            redirect("encomenda_ver.php?id=$id");
        } catch (Throwable $e) {
            $db->rollback();
            error_log('CANCEL ERROR: ' . $e->getMessage());
            flash_set('danger', 'Erro ao cancelar: ' . $e->getMessage());
            redirect("encomenda_ver.php?id=$id");
        }
    }

    // ===== PAGAMENTO  =====
    if ($action === 'pay') {
        $metodo = $_POST['metodo'] ?? 'DINHEIRO';
        $valor  = (float)($_POST['valor'] ?? 0);

        if ($valor <= 0) {
            flash_set('danger', 'Valor inválido.');
            redirect("encomenda_ver.php?id=$id");
        }

        try {
            $db->begin_transaction();

            // 1) lock na encomenda (para consistência)
            $stmt = $db->prepare("SELECT total, status FROM encomendas WHERE id=? FOR UPDATE");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $enc = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$enc) throw new Exception("Encomenda não existe.");

            if ($enc['status'] === 'CANCELADA')
                throw new Exception("Encomenda cancelada: não podes registar pagamentos.");
            if ($enc['status'] === 'PAGA')
                throw new Exception("Encomenda já está paga.");

            $total = (float)$enc['total'];

            // 2) lock na "agregação" de pagamentos desta encomenda
            // Nota: SELECT ... FOR UPDATE não bloqueia "linhas agregadas".
            // Truque simples: bloqueia todas as linhas de pagamentos existentes da encomenda.
            $stmt = $db->prepare("SELECT id FROM pagamentos WHERE encomenda_id=? FOR UPDATE");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // 3) calcular já pago
            $stmt = $db->prepare("SELECT COALESCE(SUM(valor),0) s FROM pagamentos WHERE encomenda_id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $pago = (float)$stmt->get_result()->fetch_assoc()['s'];
            $stmt->close();

            $restante = $total - $pago;

            // tolerância para floats (2 casas decimais)
            $restante = round($restante, 2);
            $valor = round($valor, 2);

            if ($restante <= 0) {
                // já está liquidada, marca como paga por segurança
                $stmt = $db->prepare("UPDATE encomendas SET status='PAGA' WHERE id=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();

                throw new Exception("Esta encomenda já está liquidada.");
            }

            if ($valor > $restante) {
                throw new Exception("Pagamento acima do permitido. Restante a pagar: " . number_format($restante, 2, ',', '.') . " €");
            }

            // 4) inserir pagamento (agora é seguro)
            $stmt = $db->prepare("INSERT INTO pagamentos (encomenda_id, metodo, valor) VALUES (?,?,?)");
            $stmt->bind_param("isd", $id, $metodo, $valor);
            $stmt->execute();
            $stmt->close();

            // 5) atualizar status se ficou liquidada
            $novo_restante = round($restante - $valor, 2);
            if ($novo_restante <= 0) {
                $stmt = $db->prepare("UPDATE encomendas SET status='PAGA' WHERE id=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
            }

            $db->commit();
            flash_set('success', 'Pagamento registado.');
            redirect("encomenda_ver.php?id=$id");
        } catch (Throwable $e) {
            $db->rollback();
            flash_set('danger', 'Erro: ' . $e->getMessage());
            redirect("encomenda_ver.php?id=$id");
        }
    }
}

// cabeçalho encomenda
$stmt = $db->prepare("
    SELECT e.id, e.status, e.total, e.data_encomenda, e.notas, c.nome cliente
    FROM encomendas e
    JOIN clientes c ON c.id = e.cliente_id
    WHERE e.id=?;
");
$stmt->bind_param("i", $id);
$stmt->execute();
$enc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$enc)
    redirect('encomendas.php');

$total = (float)$enc['total'];

// itens
$itens = [];
$stmt = $db->prepare("
    SELECT i.qty, i.unit_price, i.line_total, p.nome produto
    FROM encomenda_itens i
    JOIN produtos p ON p.id = i.produto_id
    WHERE i.encomenda_id = ?
    ORDER BY i.id
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc())
    $itens[] = $r;
$stmt->close();

// pagamentos
$pagamentos = [];
$stmt = $db->prepare("
    SELECT id, metodo, valor, pago_em
    FROM pagamentos
    WHERE encomenda_id = ?
    ORDER BY id DESC
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc())
    $pagamentos[] = $r;
$stmt->close();

// total pago
$stmt = $db->prepare("
    SELECT COALESCE(SUM(valor), 0) s
    FROM pagamentos
    WHERE encomenda_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$pago = (float)$stmt->get_result()->fetch_assoc()['s'];
$stmt->close();

require __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Encomenda #<?= (int)$enc['id'] ?></h3>
    <a class="btn btn-btn-outline-light" href="encomendas.php">Voltar</a>
</div>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-body">
                <div><span class="text-muted">Cliente:</span> <?= e($enc['cliente']) ?></div>
                <div><span class="text-muted">Data:</span> <?= e($enc['data_encomenda']) ?></div>
                <div><span class="text-muted">Status:</span> <span class="badge bg-info"><?= e($enc['status']) ?></span></div>
                <div class="mt-2"><span class="text-muted">Total:</span> <b><?= number_format($total, 2, ',', '.') ?> €</b></div>
                <div><span class="text-muted">Pago:</span> <b><?= number_format($pago, 2, ',', '.') ?> €</b></div>
                <?php if (!empty($enc['notas'])) { ?>
                    <div class="mt-2"><span class="text-muted">Notas:</span> <?= e($enc['notas']) ?></div>
                <?php } ?>
                <?php if ($enc['status'] !== 'CANCELADA' && $enc['status'] !== 'PAGA'): ?>
                    <form method="post" class="mt-3" onsubmit="return confirm('Cancelar encomenda e repor stock?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <button class="btn btn-outline-danger w-100">Cancelar encomenda (repor stock)</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($enc['status'] !== 'CANCELADA' && $enc['status'] !== 'PAGA'): ?>

            <div class="card shadow mt-3">
                <div class="card-body">
                    <h5>Registar pagamento</h5>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="pay">
                        <div class="mb-2">
                            <label class="form-label">Método</label>
                            <select class="form-select" name="metodo">
                                <option>DINHEIRO</option>
                                <option>MULTIBANCO</option>
                                <option>MBWAY</option>
                                <option>TRANSFERÊNCIA</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Valor</label>
                            <input class="form-control" name="valor" type="number" step="0.01" required>
                        </div>
                        <button class="btn btn-success w-100">Guardar</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary mt-3 mb-0">
                <?php if ($enc['status'] === 'CANCELADA'): ?>
                    Encomenda cancelada: pagamentos desativados.
                <?php else: ?>
                    Encomenda paga: não é possível registar novos pagamentos.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-7">
        <div class="card shadow">
            <div class="card-body table-responsive">
                <h5>Itens</h5>
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Qty</th>
                            <th>Preço</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $i) { ?>
                            <tr>
                                <td><?= e($i['produto']) ?></td>
                                <td><?= (int)$i['qty'] ?></td>
                                <td><?= number_format((float)$i['unit_price'], 2, ',', '.') ?> €</td>
                                <td><?= number_format((float)$i['line_total'], 2, ',', '.') ?> €</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card shadow mt-3">
            <div class="card-body table-responsive">
                <h5>Pagamentos</h5>
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Método</th>
                            <th>Valor</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagamentos as $p) { ?>
                            <tr>
                                <td><?= (int)$p['id'] ?></td>
                                <td><?= e($p['metodo']) ?></td>
                                <td><?= number_format((float)$p['valor'], 2, ',', '.') ?> €</td>
                                <td><?= e($p['pago_em']) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/inc/footer.php';
?>