<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

function post_value(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function redirect_back(string $status): void
{
    $next = post_value('_next');
    if ($next === '') {
        $next = '/#contact';
    }

    $parts = parse_url($next);
    $path = $parts['path'] ?? '/';
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['status'] = $status;

    $location = $path . '?' . http_build_query($query);
    if (!empty($parts['fragment'])) {
        $location .= '#' . $parts['fragment'];
    }

    header('Location: ' . $location);
    $separator = str_contains($next, '?') ? '&' : '?';
    header('Location: ' . $next . $separator . 'status=' . $status);
    exit;
}

// Honeypot: pokud je vyplněné, pravděpodobně bot.
if (post_value('_gotcha') !== '') {
    redirect_back('ok');
}

$name = post_value('name');
$phone = post_value('phone');
$email = post_value('email');
$service = post_value('service');
$address = post_value('address');
$message = post_value('message');
$gdprConsent = post_value('gdpr_consent');
$notRobot = post_value('not_robot');
$subject = post_value('_subject');

if ($subject === '') {
    $subject = 'Nová poptávka z pipefix.cz';
}

if ($name === '' || $phone === '' || $email === '' || $address === '' || $service === '') {
    redirect_back('missing');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_back('invalid_email');
}

if (!in_array($service, ['Kamerová kontrola + návrh opravy', 'Vyhodnocení vašeho záznamu + nacenění opravy'], true)) {
    redirect_back('invalid_service');
}

if ($gdprConsent !== '1') {
    redirect_back('missing_consent');
}

if ($notRobot !== '1') {
    redirect_back('missing_not_robot');
}

$to = 'info@pipefix.cz';
$safeSubject = substr($subject, 0, 180);
$safeSubject = mb_substr($subject, 0, 180);

$body = implode("\n", [
    "Nová poptávka z webu pipefix.cz",
    "----------------------------------------",
    "Jméno: {$name}",
    "Telefon: {$phone}",
    "E-mail: {$email}",
    "Služba: {$service}",
    "Adresa: {$address}",
    "Popis problému:",
    $message,
]);

$headers = [
    'From: PipeFix Web <info@pipefix.cz>',
    'From: PipeFix Web <no-reply@pipefix.cz>',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
];

$sent = mail($to, $safeSubject, $body, implode("\r\n", $headers));

if ($sent) {
    redirect_back('ok');
}

redirect_back('error');
