'use strict';

const form = document.querySelector('#request-form');
const submit = document.querySelector('#send-request');
const submitLabel = submit.firstElementChild;
const errorNotice = document.querySelector('#form-error');
const success = document.querySelector('#form-success');
const fields = ['name', 'phone', 'email', 'comment'];
let pending = null;

function showFieldError(field, message) {
    const input = form.elements[field];
    const feedback = document.querySelector('#' + field + '-error');
    feedback.textContent = message;
    feedback.hidden = !message;
    if (message) {
        input.setAttribute('aria-invalid', 'true');
    } else {
        input.removeAttribute('aria-invalid');
    }
}

function displayError(message) {
    errorNotice.textContent = message;
    errorNotice.hidden = !message;
}

for (const field of fields) {
    form.elements[field].addEventListener('input', () => showFieldError(field, ''));
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submit.disabled) return;
    displayError('');

    const data = Object.fromEntries(fields.map((field) => [field, form.elements[field].value.trim()]));
    const errors = {};
    if ([...data.name].length < 2) errors.name = 'Введите имя длиной от двух символов.';
    const digits = data.phone.replace(/\D/g, '');
    if (!/^\+?[\d(). -]+$/.test(data.phone) || digits.length < 7 || digits.length > 15) {
        errors.phone = 'Введите номер телефона с 7–15 цифрами.';
    }
    if (!data.email || form.elements.email.validity.typeMismatch) {
        errors.email = 'Введите email в формате name@example.ru.';
    }
    for (const field of fields) showFieldError(field, errors[field] || '');
    const invalid = Object.keys(errors)[0];
    if (invalid) {
        form.elements[invalid].focus();
        return;
    }

    const fingerprint = JSON.stringify(data);
    if (pending?.fingerprint !== fingerprint) {
        pending = { fingerprint, uuid: crypto.randomUUID() };
    }
    submit.disabled = true;
    submitLabel.textContent = 'Отправляем…';
    form.setAttribute('aria-busy', 'true');
    for (const field of fields) form.elements[field].readOnly = true;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 35000);
    try {
        const response = await fetch('/api/requests', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...data, request_uuid: pending.uuid }),
            signal: controller.signal,
        });
        const result = await response.json();
        if (response.ok) {
            form.hidden = true;
            success.hidden = false;
            success.focus();
            return;
        }
        for (const field of fields) {
            if (result.errors?.[field]) showFieldError(field, result.errors[field]);
        }
        displayError(result.message || 'Попробуйте отправить заявку ещё раз.');
        form.querySelector('[aria-invalid="true"]')?.focus();
    } catch {
        displayError('Не удалось получить ответ. Проверьте соединение и повторите отправку.');
    } finally {
        clearTimeout(timer);
        submit.disabled = false;
        submitLabel.textContent = 'Отправить заявку';
        form.removeAttribute('aria-busy');
        for (const field of fields) form.elements[field].readOnly = false;
    }
});

document.querySelector('#new-request').addEventListener('click', () => {
    form.reset();
    pending = null;
    success.hidden = true;
    form.hidden = false;
    form.elements.name.focus();
});
