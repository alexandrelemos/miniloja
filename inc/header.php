<?php
require_once __DIR__ . '/helpers.php';
$flash = flash_get_all();
?>

<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MiniLoja</title>

    <!-- Bootswatch Slate (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootswatch/5.3.8/slate/bootstrap.min.css">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">MiniLoja</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="clientes.php">Clientes</a></li>
                    <li class="nav-item"><a class="nav-link" href="produtos.php">Produtos</a></li>
                    <li class="nav-item"><a class="nav-link" href="encomendas.php">Encomendas</a></li>
                    <li class="nav-item"><a class="nav-link" href="relatorios.php">Relatórios</a></li>
                </ul>
                <div class="d-flex">
                    <?php if (!empty($_SESSION['user'])): ?>
                        <span class="navbar-text me-3">👤 <?= e($_SESSION['user']['username']) ?></span>
                        <a class="btn btn-outline-light btn-sm" href="logout.php">Sair</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="container my-4">
        <?php foreach ($flash as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
        <?php endforeach; ?>