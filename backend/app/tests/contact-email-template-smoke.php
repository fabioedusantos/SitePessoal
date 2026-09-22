<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Service/ContactEmailTemplate.php';

use App\Service\ContactEmailTemplate;

function assertCase(bool $condition, string $name): void
{
    if (!$condition) {
        fwrite(STDERR, "FALHOU: {$name}\n");
        exit(1);
    }
    echo "OK: {$name}\n";
}

$template = new ContactEmailTemplate();
$owner = $template->owner('<Admin>', 'teste@example.com', 'Olá & Teste', "Mensagem <script>alert(1)</script>\nOutra linha");
$confirmation = $template->confirmation('Visitante <teste>');
assertCase(str_contains($owner, 'FBSantos') && str_contains($confirmation, 'FBSantos'), 'Identidade nos dois templates');
assertCase(str_contains($owner, '&lt;script&gt;') && !str_contains($owner, '<script>'), 'HTML do visitante escapado');
assertCase(str_contains($owner, '<br') && str_contains($owner, 'Outra linha'), 'Quebras de linha mantidas');
assertCase(str_contains($confirmation, 'Visitante &lt;teste&gt;'), 'Nome do visitante escapado');
assertCase(str_contains($owner, 'max-width:620px') && str_contains($confirmation, 'max-width:620px'), 'Layout padronizado e responsivo');
