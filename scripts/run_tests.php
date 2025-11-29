<?php

/**
 * Script para executar testes automatizados
 * 
 * Uso: php scripts/run_tests.php [suite] [filter]
 * 
 * Exemplos:
 *   php scripts/run_tests.php                    # Todos os testes
 *   php scripts/run_tests.php Unit               # Apenas testes unitários
 *   php scripts/run_tests.php Integration        # Apenas testes de integração
 *   php scripts/run_tests.php Unit EmailService  # Teste específico
 */

$suite = $argv[1] ?? null;
$filter = $argv[2] ?? null;

$command = 'vendor/bin/phpunit';

if ($suite) {
    $command .= ' --testsuite ' . escapeshellarg($suite);
}

if ($filter) {
    $command .= ' --filter ' . escapeshellarg($filter);
}

$command .= ' --testdox';

echo "🧪 Executando testes automatizados...\n";
echo "Comando: {$command}\n\n";

passthru($command, $exitCode);

exit($exitCode);

