<?php
header('Content-Type: text/plain; charset=utf-8');

$variables = [
    'CHACHA_SMTP_HOST',
    'CHACHA_SMTP_PORT',
    'CHACHA_SMTP_USER',
    'CHACHA_SMTP_PASSWORD',
    'CHACHA_SMTP_FROM'
];

foreach ($variables as $variable) {
    $valeur = getenv($variable);

    echo $variable . ' : ';

    if ($valeur === false || $valeur === '') {
        echo "ABSENTE\n";
    } else {
        echo "CONFIGUREE\n";
    }
}