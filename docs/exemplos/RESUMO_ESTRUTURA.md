# 📁 Resumo da Estrutura de Pastas

## 🎯 Estrutura Final Recomendada

```
saas-stripe/                          # Projeto Backend (API)
│
├── App/                              # Código PHP do Backend
│   ├── Controllers/                  # Controllers da API
│   ├── Models/                       # Models (ActiveRecord)
│   ├── Services/                     # Serviços (Stripe, Payment, etc.)
│   ├── Middleware/                   # Middlewares (Auth, Rate Limit, etc.)
│   └── Utils/                        # Utilitários
│
├── public/                           # ⭐ Pasta Web Root (ponto de entrada)
│   ├── index.php                     # API REST (FlightPHP)
│   │                                 #    ↓ Também serve arquivos de /app/
│   │
│   └── app/                          # ⭐ FRONT-END AQUI
│       ├── index.html                # Página principal
│       ├── success.html              # Página de sucesso
│       ├── dashboard.html            # Dashboard
│       ├── api-client.js             # Cliente da API
│       ├── main.js                   # Lógica principal
│       ├── success.js                 # Lógica da página de sucesso
│       ├── dashboard.js               # Lógica do dashboard
│       └── README.md                 # Documentação
│
├── config/                           # Configurações (.env)
├── vendor/                           # Dependências Composer
├── docs/                             # Documentação
│   └── exemplos/                     # Exemplos de referência
│       ├── index.html
│       ├── *.js
│       └── ESTRUTURA_PASTAS.md       # Este guia
│
├── scripts/                           # Scripts utilitários
│   ├── setup_tenant.php
│   └── setup_frontend.php            # ⭐ Script para configurar front-end
│
└── ...
```

## 🚀 Como Configurar

### Passo 1: Executar Script de Setup

```bash
php scripts/setup_frontend.php
```

Este script vai:
- ✅ Criar a pasta `public/app/`
- ✅ Copiar todos os arquivos de `docs/exemplos/` para `public/app/`
- ✅ Criar arquivos de configuração

### Passo 2: Configurar API Key

Edite `public/app/api-client.js`:

```javascript
const API_CONFIG = {
    baseUrl: 'http://localhost:8080',  // URL da API
    apiKey: 'sua_api_key_aqui'          // Sua API Key
};
```

### Passo 3: Acessar

```bash
# Iniciar servidor
php -S localhost:8080 -t public

# Acessar:
# Front-end: http://localhost:8080/app/index.html
# API:        http://localhost:8080/v1/customers
```

## 📍 Onde Cada Arquivo Fica

| Arquivo | Localização | Descrição |
|---------|------------|-----------|
| **Backend PHP** | `App/` | Código do servidor |
| **API Entry** | `public/index.php` | Ponto de entrada da API |
| **Front-End HTML** | `public/app/*.html` | Páginas do front-end |
| **Front-End JS** | `public/app/*.js` | JavaScript do front-end |
| **Exemplos** | `docs/exemplos/` | Apenas para referência |

## 🔄 Fluxo de Requisições

```
Usuário acessa: http://localhost:8080/app/index.html
                ↓
        public/index.php verifica:
        - É /app/*? → Serve arquivo estático
        - É /v1/*? → Processa como API
                ↓
        Front-end carrega e faz requisições para:
        http://localhost:8080/v1/customers
                ↓
        public/index.php processa como API
```

## ✅ Vantagens desta Estrutura

1. ✅ **Tudo no mesmo servidor** - Fácil para desenvolvimento
2. ✅ **Mesmo domínio** - Sem problemas de CORS
3. ✅ **Separação clara** - Front-end em `public/app/`, API em `public/index.php`
4. ✅ **Fácil deploy** - Tudo junto, mas organizado
5. ✅ **Pronto para produção** - Pode separar depois se necessário

## 🎯 Resposta Direta

**Pergunta:** Os arquivos ficam dentro de `App/` ou `public/`?

**Resposta:** 
- ❌ **NÃO** dentro de `App/` (App é só código PHP backend)
- ✅ **SIM** dentro de `public/app/` (pasta pública, acessível via web)

**Estrutura:**
```
public/
├── index.php      ← API (backend)
└── app/           ← Front-end (HTML/JS/CSS)
    ├── index.html
    └── *.js
```

## 📝 Notas Importantes

1. **`public/index.php`** já foi atualizado para servir arquivos de `/app/`
2. **Execute `php scripts/setup_frontend.php`** para configurar automaticamente
3. **`docs/exemplos/`** são apenas exemplos de referência
4. **`public/app/`** é onde o front-end realmente fica e funciona

## 🚀 Pronto!

Após executar `php scripts/setup_frontend.php`, tudo estará configurado e funcionando! 🎉

