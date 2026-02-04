<?php
require_once __DIR__ . '/helpers.php';

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        redirect('login.php');
    }
}
