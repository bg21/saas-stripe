# 🔑 Como Configurar API Key e Base URL

## 📋 O que você precisa

1. **API Key** - Chave de autenticação do seu tenant
2. **Base URL** - URL onde o backend está rodando

---

## 🔑 Passo 1: Obter ou Criar API Key

### Opção A: Criar um Novo Tenant (Recomendado)

Execute o script que já existe no projeto:

```bash
# No terminal, dentro da pasta saas-stripe
cd saas-stripe
php scripts/setup_tenant.php
```

O script vai:
1. Pedir o nome do seu SaaS
2. Criar um tenant
3. Gerar uma API Key automaticamente
4. Mostrar a API Key na tela
5. Salvar em um arquivo `tenant_X_credentials.txt`

**Exemplo de saída:**
```
╔═══════════════════════════════════════════════════════════════╗
║          Setup de Tenant (SaaS) no Sistema de Pagamentos     ║
╚═══════════════════════════════════════════════════════════════╝

Digite o nome do seu SaaS: Meu SaaS App
✅ Tenant criado com sucesso!

═══════════════════════════════════════════════════════════
INFORMAÇÕES DO TENANT
═══════════════════════════════════════════════════════════
ID: 1
Nome: Meu SaaS App
Status: active
API Key: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2

⚠️  IMPORTANTE:
   - GUARDE ESTA API KEY EM LOCAL SEGURO!
   - Ela não será exibida novamente
```

**Copie a API Key mostrada!** Ela será algo como:
```
a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2
```

### Opção B: Verificar API Key Existente

Se você já tem um tenant, pode verificar no banco de dados:

```bash
# Conectar ao MySQL
mysql -u root -p saas_payments

# Consultar tenants
SELECT id, name, api_key, status FROM tenants;
```

Ou criar um script rápido:

```php
<?php
// verificar_api_key.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
Config::load();

use App\Models\Tenant;

$tenantModel = new Tenant();
$tenants = $tenantModel->findAll();

echo "Tenants cadastrados:\n\n";
foreach ($tenants as $tenant) {
    echo "ID: {$tenant['id']}\n";
    echo "Nome: {$tenant['name']}\n";
    echo "API Key: {$tenant['api_key']}\n";
    echo "Status: {$tenant['status']}\n";
    echo "---\n";
}
```

Execute:
```bash
php verificar_api_key.php
```

---

## 🌐 Passo 2: Configurar Base URL

A Base URL depende de **onde** o backend está rodando:

### Desenvolvimento Local

Se você está testando localmente:

```javascript
const API_CONFIG = {
    baseUrl: 'http://localhost:8080',  // ← URL local
    apiKey: 'sua_api_key_aqui'
};
```

**Como descobrir:**
1. Inicie o servidor PHP:
   ```bash
   cd saas-stripe
   php -S localhost:8080 -t public
   ```
2. A URL será: `http://localhost:8080`
3. Teste acessando: `http://localhost:8080/health`

### Produção

Se o backend está em produção:

```javascript
const API_CONFIG = {
    baseUrl: 'https://api.seudominio.com',  // ← URL de produção
    apiKey: 'sua_api_key_aqui'
};
```

**Exemplos de URLs de produção:**
- `https://api.seudominio.com`
- `https://pagamentos.seudominio.com`
- `https://backend.seudominio.com`

---

## ✅ Passo 3: Configurar no Front-End

### 1. Abra o arquivo `api-client.js` no seu projeto front-end

```bash
cd saas-stripe-frontend
# Abra api-client.js no editor
```

### 2. Edite a configuração

```javascript
const API_CONFIG = {
    // DESENVOLVIMENTO (local)
    baseUrl: 'http://localhost:8080',
    
    // PRODUÇÃO (descomente quando fizer deploy)
    // baseUrl: 'https://api.seudominio.com',
    
    // SUA API KEY (cole aqui a API Key que você obteve)
    apiKey: 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2'
};
```

### 3. Salve o arquivo

---

## 🧪 Passo 4: Testar a Configuração

### Teste 1: Verificar se o Backend está Rodando

```bash
# No terminal, teste a API
curl http://localhost:8080/health

# Deve retornar algo como:
# {"status":"ok","timestamp":"2025-01-16 10:00:00"}
```

### Teste 2: Testar com API Key

Crie um arquivo `test-api.html` no seu front-end:

```html
<!DOCTYPE html>
<html>
<head>
    <title>Teste API</title>
</head>
<body>
    <h1>Teste de API</h1>
    <button onclick="testAPI()">Testar API</button>
    <pre id="result"></pre>

    <script src="api-client.js"></script>
    <script>
        async function testAPI() {
            try {
                // Teste 1: Health Check (sem API Key)
                const healthResponse = await fetch('http://localhost:8080/health');
                const health = await healthResponse.json();
                console.log('Health:', health);

                // Teste 2: Listar Customers (com API Key)
                const customers = await api.listCustomers();
                console.log('Customers:', customers);
                
                document.getElementById('result').textContent = 
                    '✅ API funcionando!\n' + 
                    JSON.stringify(customers, null, 2);
            } catch (error) {
                console.error('Erro:', error);
                document.getElementById('result').textContent = 
                    '❌ Erro: ' + error.message;
            }
        }
    </script>
</body>
</html>
```

Abra no navegador e clique no botão "Testar API".

---

## 📝 Exemplo Completo de Configuração

### Desenvolvimento (Local)

```javascript
// api-client.js
const API_CONFIG = {
    baseUrl: 'http://localhost:8080',
    apiKey: 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2'
};
```

**Como usar:**
1. Backend rodando: `php -S localhost:8080 -t public` (terminal 1)
2. Front-end: Abra `index.html` no navegador ou use servidor simples
3. Teste: Acesse `http://localhost:8080/app/index.html` (se usar Opção 2)

### Produção

```javascript
// api-client.js
const API_CONFIG = {
    baseUrl: 'https://api.seudominio.com',
    apiKey: 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2'
};
```

**Como usar:**
1. Backend deployado em: `https://api.seudominio.com`
2. Front-end deployado em: `https://app.seudominio.com` (Netlify, Vercel, etc.)
3. Configure CORS no backend para aceitar requisições de `https://app.seudominio.com`

---

## 🔍 Como Descobrir sua URL Atual

### Se está usando XAMPP:

```javascript
// Se o backend está em:
// http://localhost/saas-stripe/public/

const API_CONFIG = {
    baseUrl: 'http://localhost/saas-stripe/public',
    apiKey: 'sua_api_key'
};
```

### Se está usando servidor PHP built-in:

```javascript
// Se você rodou: php -S localhost:8080 -t public

const API_CONFIG = {
    baseUrl: 'http://localhost:8080',
    apiKey: 'sua_api_key'
};
```

### Se está em produção:

```javascript
// Onde você fez deploy do backend
const API_CONFIG = {
    baseUrl: 'https://api.seudominio.com',  // ← URL do seu servidor
    apiKey: 'sua_api_key'
};
```

---

## ⚠️ Problemas Comuns

### Erro: "Token de autenticação não fornecido"

**Causa:** API Key não está sendo enviada corretamente.

**Solução:**
1. Verifique se a API Key está correta em `api-client.js`
2. Verifique se não tem espaços extras
3. Teste a API Key diretamente:
   ```bash
   curl -H "Authorization: Bearer sua_api_key" http://localhost:8080/v1/customers
   ```

### Erro: "CORS policy"

**Causa:** Backend não está permitindo requisições do front-end.

**Solução:**
1. Verifique se o CORS está configurado em `public/index.php`
2. Se front-end está em domínio diferente, adicione o domínio nas origens permitidas

### Erro: "Network Error" ou "Failed to fetch"

**Causa:** Backend não está rodando ou URL incorreta.

**Solução:**
1. Verifique se o backend está rodando
2. Teste a URL diretamente no navegador: `http://localhost:8080/health`
3. Verifique se a URL em `api-client.js` está correta

---

## ✅ Checklist Final

Antes de usar, verifique:

- [ ] ✅ API Key obtida/criada
- [ ] ✅ API Key configurada em `api-client.js`
- [ ] ✅ Base URL configurada corretamente
- [ ] ✅ Backend está rodando
- [ ] ✅ Teste de health check funciona
- [ ] ✅ Teste com API Key funciona

---

## 🎯 Resumo Rápido

1. **Obter API Key:**
   ```bash
   cd saas-stripe
   php scripts/setup_tenant.php
   # Copie a API Key mostrada
   ```

2. **Configurar:**
   ```javascript
   // Em saas-stripe-frontend/api-client.js
   const API_CONFIG = {
       baseUrl: 'http://localhost:8080',  // ← Sua URL
       apiKey: 'cole_aqui_sua_api_key'    // ← Sua API Key
   };
   ```

3. **Testar:**
   - Abra `index.html` no navegador
   - Verifique o console (F12) para erros
   - Teste criar um cliente

Pronto! 🚀

