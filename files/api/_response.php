<?php
declare(strict_types=1);

/**
 * Standardized API responses
 */

/**
 * Send success response
 */
function api_success(array $data = [], string $message = 'Success', int $code = 200): never {
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send error response
 */
function api_error(string $message = 'Error', int $code = 400, array $errors = []): never {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'errors' => $errors,
        'timestamp' => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Validate required fields
 */
function validate_required(array $data, array $fields): array {
    $errors = [];
    foreach ($fields as $field => $label) {
        if (empty($data[$field])) {
            $errors[$field] = "{$label} is required";
        }
    }
    if (!empty($errors)) {
        api_error('Validation failed', 422, $errors);
    }
    return $data;
}

/**
 * Validate NEN (10 digits)
 */
function validate_nen(string $value): bool {
    $value = preg_replace('/\s+/', '', trim($value)) ?? '';
    return preg_match('/^\d{10}$/', $value) === 1 && (int)$value > 0;
}

/**
 * Validate CIN (12 digits)
 */
function validate_cin(string $value): bool {
    $value = preg_replace('/\s+/', '', trim($value)) ?? '';
    return preg_match('/^\d{12}$/', $value) === 1 && (int)$value > 0;
}

/**
 * Validate email
 */
function validate_email(string $value): bool {
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 */
function validate_password(string $value): array {
    $errors = [];
    if (strlen($value) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    if (!preg_match('/[A-Z]/', $value)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    if (!preg_match('/[a-z]/', $value)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    if (!preg_match('/[0-9]/', $value)) {
        $errors[] = 'Password must contain at least one digit';
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $value)) {
        $errors[] = 'Password must contain at least one special character';
    }
    return $errors;
}

/**
 * Check if age is at least 18
 */
function is_at_least_18_years_old(string $birthDate): bool {
    $date = DateTime::createFromFormat('!Y-m-d', $birthDate);
    if (!$date || $date->format('Y-m-d') !== $birthDate) {
        return false;
    }
    $today = new DateTime('today');
    return $date <= $today->modify('-18 years');
}

/**
 * Normalize identifier (remove whitespace)
 */
function normalize_identifier(string $value): string {
    return preg_replace('/\s+/', '', trim($value)) ?? '';
}
