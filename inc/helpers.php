<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
  header("Location: $url");
  exit;
}

function flash_set(string $type, string $msg): void {
  $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_get_all(): array {
  $all = $_SESSION['flash'] ?? [];
  unset($_SESSION['flash']);
  return $all;
}

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['csrf'];
}

function csrf_field(): string {
  return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">';
}

function csrf_verify(): void {
  $t = $_POST['csrf'] ?? '';
  if (!$t || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
    http_response_code(400);
    die('CSRF inválido.');
  }
}
