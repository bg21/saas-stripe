-- Exemplo de seed para criar um tenant de teste
-- Execute este SQL após criar o banco de dados

USE saas_payments;

-- Insere um tenant de exemplo
-- NOTA: A API key será gerada automaticamente quando você criar o tenant via código
-- Ou você pode gerar uma manualmente (64 caracteres hexadecimais)
INSERT INTO tenants (name, api_key, status) 
VALUES (
    'Tenant de Teste',
    'sua_api_key_64_caracteres_hexadecimais_aqui',
    'active'
);

-- Exemplo de usuário (opcional)
-- INSERT INTO users (tenant_id, email, password_hash, name, status)
-- VALUES (
--     1,
--     'admin@example.com',
--     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
--     'Admin User',
--     'active'
-- );

