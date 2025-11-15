<?php

use Phinx\Seed\AbstractSeed;

/**
 * Seed inicial - Dados de exemplo para desenvolvimento
 * 
 * Este seed cria um tenant de teste para facilitar o desenvolvimento.
 * 
 * NOTA: A API key deve ser gerada manualmente ou via código.
 * Use o método generateApiKey() do modelo Tenant para gerar uma chave segura.
 */
class InitialSeed extends AbstractSeed
{
    /**
     * Run Method.
     */
    public function run(): void
    {
        // Gera uma API key de exemplo (64 caracteres hexadecimais)
        // Em produção, use o método generateApiKey() do modelo Tenant
        $apiKey = bin2hex(random_bytes(32)); // 64 caracteres hexadecimais

        $data = [
            [
                'name' => 'Tenant de Teste',
                'api_key' => $apiKey,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->table('tenants')->insert($data)->saveData();

        // Exibe a API key gerada para facilitar o uso
        echo "\n✅ Tenant de teste criado!\n";
        echo "API Key: {$apiKey}\n";
        echo "Use esta chave no header: Authorization: Bearer {$apiKey}\n\n";
    }
}

