<?php
declare(strict_types=1);

function auth_role(): string
{
    $auth = (array)($_SESSION['auth'] ?? []);
    $role = (string)($auth['role'] ?? '');
    return $role;
}

function auth_is_admin(): bool
{
    return auth_role() === 'admin';
}

function auth_is_electeur(): bool
{
    return auth_role() === 'electeur';
}

function auth_user_name(): string
{
    $auth = (array)($_SESSION['auth'] ?? []);
    $name = (string)($auth['name'] ?? '');
    return $name !== '' ? $name : 'Invité';
}

function auth_user_id(): int
{
    $auth = (array)($_SESSION['auth'] ?? []);
    return (int)($auth['id_user'] ?? 0);
}

function require_admin(): void
{
    if (!auth_is_admin()) {
        header('Location: login.php');
        exit;
    }
}

function require_electeur(): void
{
    if (!auth_is_electeur() || auth_user_id() <= 0) {
        // If the session is malformed (no user ID), force re-login.
        header('Location: ../inscription/login.php');
        exit;
    }
}

function login_admin(string $username): void
{
    $_SESSION['auth'] = [
        'role' => 'admin',
        'name' => $username,
    ];
}

function login_electeur(int $idUser, string $name): void
{
    $_SESSION['auth'] = [
        'role' => 'electeur',
        'id_user' => $idUser,
        'name' => $name,
    ];
}

function logout_auth(): void
{
    unset($_SESSION['auth']);
}

function admin_expected_user(): string
{
    return 'admin';
}

function admin_expected_pass(): string
{
    return 'admin123';
}

