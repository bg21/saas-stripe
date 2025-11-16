# 📁 Estrutura de Pastas - Front-End

## 🎯 Opções de Estrutura

Como o front-end é **separado** do backend, você tem 3 opções principais:

---

## 📂 Opção 1: Front-End Separado (RECOMENDADO)

### Estrutura Recomendada para Produção

```
saas-stripe/                    # Backend (API)
├── App/                        # Código PHP do backend
├── public/                     # Ponto de entrada da API
│   └── index.php              # API REST (FlightPHP)
├── config/
├── vendor/
└── ...

meu-frontend/                   # Front-End Separado (projeto diferente)
├── index.html
├── success.html
├── dashboard.html
├── api-client.js
├── main.js
├── success.js
├── dashboard.js
└── assets/
    ├── css/
    └── js/
```

**Vantagens:**
- ✅ Separação completa de responsabilidades
- ✅ Pode usar qualquer servidor (Nginx, Apache, Netlify, Vercel, etc.)
- ✅ Deploy independente
- ✅ Escalabilidade independente
- ✅ Pode usar qualquer tecnologia front-end

**Como funciona:**
- Backend roda em: `https://api.seudominio.com`
- Front-end roda em: `https://app.seudominio.com` ou `https://seudominio.com`
- Front-end faz requisições HTTP para a API

---

## 📂 Opção 2: Front-End dentro de `public/` (Mesmo Servidor)

### Estrutura

```
saas-stripe/
├── App/                        # Backend PHP
├── public/                     # Pasta pública (web root)
│   ├── index.php              # API REST (FlightPHP)
│   │
│   └── app/                    # Front-End (HTML/JS/CSS)
│       ├── index.html
│       ├── success.html
│       ├── dashboard.html
│       ├── api-client.js
│       ├── main.js
│       ├── success.js
│       ├── dashboard.js
│       └── assets/
│           ├── css/
│           └── js/
├── config/
└── ...
```

**Configuração do `public/index.php`:**

```php
<?php
// Se a requisição for para arquivos estáticos (HTML, JS, CSS), servir diretamente
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Se for arquivo estático na pasta /app, servir diretamente
if (preg_match('/^\/app\//', $requestUri)) {
    $filePath = __DIR__ . $requestUri;
    if (file_exists($filePath) && is_file($filePath)) {
        // Determinar content-type
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $contentTypes = [
            'html' => 'text/html',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json'
        ];
        header('Content-Type: ' . ($contentTypes[$ext] ?? 'text/plain'));
        readfile($filePath);
        exit;
    }
}

// Caso contrário, processar como API
require_once __DIR__ . '/../vendor/autoload.php';
// ... resto do código da API
```

**Acesso:**
- API: `http://localhost:8080/v1/customers`
- Front-end: `http://localhost:8080/app/index.html`

**Vantagens:**
- ✅ Tudo no mesmo servidor
- ✅ Mesmo domínio (sem problemas de CORS)
- ✅ Deploy simples

**Desvantagens:**
- ⚠️ Mistura front-end com backend
- ⚠️ Menos flexível para escalar

---

## 📂 Opção 3: Front-End em Subpasta da Raiz

### Estrutura

```
saas-stripe/
├── App/                        # Backend PHP
├── public/                     # API REST
│   └── index.php
├── frontend/                   # Front-End
│   ├── index.html
│   ├── success.html
│   ├── dashboard.html
│   ├── api-client.js
│   ├── main.js
│   ├── success.js
│   ├── dashboard.js
│   └── assets/
├── config/
└── ...
```

**Configuração do servidor (`.htaccess` para Apache):**

```apache
# Se for requisição para /frontend/, servir arquivos estáticos
RewriteEngine On
RewriteCond %{REQUEST_URI} ^/frontend/
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^frontend/(.*)$ frontend/$1 [L]

# Se não for arquivo, processar como API
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ public/index.php [QSA,L]
```

**Acesso:**
- API: `http://localhost:8080/v1/customers`
- Front-end: `http://localhost:8080/frontend/index.html`

---

## 🎯 Recomendação Final

### Para Desenvolvimento/Testes:
**Use Opção 2** (dentro de `public/app/`)

```
public/
├── index.php          # API
└── app/                 # Front-end
    ├── index.html
    ├── success.html
    ├── dashboard.html
    └── *.js
```

### Para Produção:
**Use Opção 1** (projetos separados)

```
Backend:  https://api.seudominio.com
Frontend: https://app.seudominio.com
```

---

## 📝 Implementação Prática

### Criando a Estrutura Recomendada (Opção 2)

1. **Criar pasta dentro de `public/`:**

```bash
mkdir public/app
```

2. **Mover arquivos dos exemplos:**

```bash
# Copiar arquivos de docs/exemplos para public/app
cp docs/exemplos/*.html public/app/
cp docs/exemplos/*.js public/app/
```

3. **Atualizar `public/index.php` para servir arquivos estáticos:**

Adicione no início do arquivo (antes do código da API):

```php
<?php
// Servir arquivos estáticos da pasta /app
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('/^\/app\//', $requestUri)) {
    $filePath = __DIR__ . $requestUri;
    
    // Verificar se arquivo existe e é seguro
    if (file_exists($filePath) && is_file($filePath) && strpos(realpath($filePath), realpath(__DIR__)) === 0) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'html' => 'text/html; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon'
        ];
        
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'text/plain'));
        header('Cache-Control: public, max-age=3600');
        readfile($filePath);
        exit;
    }
    
    // Arquivo não encontrado
    http_response_code(404);
    exit;
}

// Continuar com código da API...
require_once __DIR__ . '/../vendor/autoload.php';
// ... resto do código
```

4. **Atualizar URLs no `api-client.js`:**

```javascript
const API_CONFIG = {
    // Se estiver no mesmo servidor, usar caminho relativo
    baseUrl: window.location.origin, // ou 'http://localhost:8080'
    apiKey: 'sua_api_key_aqui'
};
```

5. **Acessar:**

- Front-end: `http://localhost:8080/app/index.html`
- API: `http://localhost:8080/v1/customers`

---

## 🔧 Configuração para Nginx (Produção)

Se usar Nginx em produção:

```nginx
server {
    listen 80;
    server_name seudominio.com;
    
    # Front-end
    location /app {
        alias /caminho/para/saas-stripe/public/app;
        try_files $uri $uri/ =404;
    }
    
    # API
    location / {
        try_files $uri $uri/ /index.php?$query_string;
        root /caminho/para/saas-stripe/public;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    }
}
```

---

## 📂 Estrutura Final Recomendada

### Para Desenvolvimento:

```
saas-stripe/
├── App/                    # Backend PHP
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   └── Middleware/
├── public/                 # Web root
│   ├── index.php          # API REST
│   └── app/               # Front-end
│       ├── index.html
│       ├── success.html
│       ├── dashboard.html
│       ├── api-client.js
│       ├── main.js
│       ├── success.js
│       ├── dashboard.js
│       └── assets/        # (opcional)
│           ├── css/
│           └── images/
├── config/
├── vendor/
└── docs/
    └── exemplos/          # Exemplos de referência
```

### Para Produção:

```
Backend (saas-stripe):
├── App/
├── public/
│   └── index.php          # Apenas API
└── ...

Frontend (projeto separado):
├── index.html
├── success.html
├── dashboard.html
├── api-client.js
└── ...
```

---

## ✅ Resumo

| Opção | Onde Fica | Quando Usar |
|-------|-----------|-------------|
| **1. Separado** | Projeto diferente | ✅ Produção (recomendado) |
| **2. `public/app/`** | Dentro de `public/` | ✅ Desenvolvimento/Testes |
| **3. `frontend/`** | Pasta na raiz | ⚠️ Menos comum |

**Recomendação:** Use **Opção 2** para desenvolvimento e **Opção 1** para produção.

