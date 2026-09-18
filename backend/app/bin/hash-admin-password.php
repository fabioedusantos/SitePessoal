<?php

declare(strict_types=1);

function readHidden(string $prompt): string
{
    fwrite(STDOUT, $prompt);

    if (PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec')) {
        $state = trim((string) shell_exec('stty -g 2>/dev/null'));
        if ($state !== '') {
            shell_exec('stty -echo 2>/dev/null');
            try {
                $value = trim((string) fgets(STDIN));
            } finally {
                shell_exec('stty ' . escapeshellarg($state) . ' 2>/dev/null');
                fwrite(STDOUT, PHP_EOL);
            }

            return $value;
        }
    }

    // Fallback para ambientes sem suporte a entrada oculta.
    return trim((string) readline());
}

$password = readHidden('Nova senha do painel: ');
if (strlen($password) < 12) {
    fwrite(STDERR, "A senha deve possuir pelo menos 12 caracteres.\n");
    exit(1);
}

$confirmation = readHidden('Confirme a senha: ');
if (!hash_equals($password, $confirmation)) {
    fwrite(STDERR, "As senhas informadas não conferem.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
if ($hash === false) {
    fwrite(STDERR, "Não foi possível gerar o hash da senha.\n");
    exit(1);
}

fwrite(STDOUT, "\nCopie esta linha para o .env:\n");
fwrite(STDOUT, "ADMIN_PASSWORD_HASH='" . $hash . "'\n");
