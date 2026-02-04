<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $tel = trim($_POST['telefone'] ?? '');

        if ($nome === '') {
            flash_set('danger', 'O nome é obrigatório.');
            redirect('clientes.php');
        }

        $emailOrNull = ($email === '') ? null : $email;
        $telOrNull = ($tel === '') ? null : $tel;

        $stmt = $db->prepare("INSERT INTO clientes (nome, email, telefone) VALUES (?,?,?)");
        $stmt->bind_param("sss", $nome, $emailOrNull, $telOrNull);
        $stmt->execute();
        $stmt->close();

        flash_set('success', 'Cliente criado com sucesso.');
        redirect('clientes.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM clientes WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            flash_set('warning', 'Cliente apagado com sucesso.');
        }
        redirect('clientes.php');
    }
}

$rows = [];
$res = $db->query("SELECT * FROM clientes ORDER BY id DESC;");
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}

require __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Clientes</h3>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-body">
                <h5>Novo cliente</h5>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-2">
                        <label class="form-label">Nome</label>
                        <input class="form-control" name="nome" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone</label>
                        <input class="form-control" name="telefone">
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
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r) { ?>
                            <tr>
                                <td><?= (int)$r['id'] ?></td>
                                <td><?= e($r['nome']) ?></td>
                                <td><?= e($r['email'] ?? '') ?></td>
                                <td><?= e($r['telefone'] ?? '') ?></td>
                                <td class="text-end">
                                    <form method="post" class="d-inline" onsubmit="return confirm('Apagar cliente?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">Apagar</button>
                                    </form>
                                </td>
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