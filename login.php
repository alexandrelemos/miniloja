<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php'; // sessão, redirect, flash, csrf

// já autenticado? vai para a página inicial
if (!empty($_SESSION['user']))
    redirect('index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    $stmt = $db->prepare("SELECT id, username, password_hash, role FROM utilizadores WHERE username=?");
    $stmt->bind_param('s', $u);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($user && password_verify($p, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ];
        flash_set('success', 'Login feito.');
        redirect('index.php');
    }

    flash_set('danger', 'Credenciais inválidas.');
    redirect('login.php');
}

// só daqui para baixo é que imprime HTML
require __DIR__ . '/inc/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-body">
                <h4 class="mb-3">Entrar</h4>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Utilizador</label>
                        <input class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input class="form-control" type="password" name="password" required>
                    </div>
                    <button class="btn btn-primary w-100">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>