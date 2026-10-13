<?php
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void {
    $expected=$_SESSION['_csrf']??null;$given=$_POST['csrf']??null;
    if (!is_string($expected)||$expected===''||!is_string($given)||!hash_equals($expected,$given)) {
        http_response_code(419); exit(e(t('csrf_expired')));
    }
}
