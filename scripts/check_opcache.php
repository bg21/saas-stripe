<?php
/**
 * Script para verificar status do OpCache
 * Execute: php scripts/check_opcache.php
 */

echo "=== OpCache Status ===\n\n";

if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    if ($status) {
        echo "✅ OpCache está ATIVO\n\n";
        
        echo "Memória:\n";
        echo "  - Usada: " . round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
        echo "  - Livre: " . round($status['memory_usage']['free_memory'] / 1024 / 1024, 2) . " MB\n";
        echo "  - Total: " . round(($status['memory_usage']['used_memory'] + $status['memory_usage']['free_memory']) / 1024 / 1024, 2) . " MB\n\n";
        
        echo "Estatísticas:\n";
        echo "  - Scripts cacheados: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
        echo "  - Hit rate: " . round($status['opcache_statistics']['opcache_hit_rate'], 2) . "%\n";
        echo "  - Misses: " . $status['opcache_statistics']['misses'] . "\n";
        echo "  - Hits: " . $status['opcache_statistics']['hits'] . "\n\n";
        
        if ($status['opcache_statistics']['opcache_hit_rate'] < 90) {
            echo "⚠️  AVISO: Hit rate abaixo de 90%. Considere aumentar opcache.memory_consumption\n";
        }
    } else {
        echo "❌ OpCache está DESATIVADO\n";
        echo "   Configure opcache.enable=1 no php.ini\n";
    }
} else {
    echo "❌ OpCache não está instalado\n";
    echo "   Instale a extensão opcache do PHP\n";
}

echo "\n=== Configuração Recomendada (php.ini) ===\n";
echo "opcache.enable=1\n";
echo "opcache.memory_consumption=256\n";
echo "opcache.interned_strings_buffer=16\n";
echo "opcache.max_accelerated_files=20000\n";
echo "opcache.validate_timestamps=0  # Em produção\n";
echo "opcache.revalidate_freq=0\n";
echo "opcache.fast_shutdown=1\n";

