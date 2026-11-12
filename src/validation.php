<?php

declare(strict_types=1);

function validateRequest(array $input): array
{
    $fields = [];
    $errors = [];
    foreach (['request_uuid', 'name', 'phone', 'email', 'comment'] as $field) {
        $value = array_key_exists($field, $input) ? $input[$field] : '';
        if (!is_string($value)) {
            $errors[$field] = 'Введите текстовое значение.';
            continue;
        }
        $fields[$field] = trim($value);
    }

    if (!isset($errors['request_uuid'])) {
        $fields['request_uuid'] = strtolower($fields['request_uuid']);
        if (!preg_match('/\A[\da-f]{8}-[\da-f]{4}-4[\da-f]{3}-[89ab][\da-f]{3}-[\da-f]{12}\z/', $fields['request_uuid'])) {
            $errors['request_uuid'] = 'Не удалось распознать номер заявки.';
        }
    }
    if (!isset($errors['name'])) {
        $length = mb_strlen($fields['name'], 'UTF-8');
        if ($length < 2 || $length > 150 || preg_match('/[\p{Cc}]/u', $fields['name'])) {
            $errors['name'] = 'Введите имя длиной от 2 до 150 символов.';
        }
    }
    if (!isset($errors['phone'])) {
        $phone = $fields['phone'];
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($phone) > 30 || !preg_match('/\A\+?[0-9(). -]+\z/', $phone)
            || strlen($digits) < 7 || strlen($digits) > 15) {
            $errors['phone'] = 'Введите номер телефона с 7–15 цифрами.';
        } else {
            $fields['phone'] = str_starts_with($phone, '+') ? '+' . $digits : $digits;
        }
    }
    if (!isset($errors['email']) && (strlen($fields['email']) > 254
        || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL))) {
        $errors['email'] = 'Введите email в формате name@example.ru.';
    }
    if (!isset($errors['comment']) && mb_strlen($fields['comment'], 'UTF-8') > 5000) {
        $errors['comment'] = 'Сократите комментарий до 5 000 символов.';
    }
    if (($fields['comment'] ?? '') === '') {
        $fields['comment'] = null;
    }
    return [$fields, $errors];
}
