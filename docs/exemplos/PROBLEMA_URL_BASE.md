# Problema: Checkout só funciona com /index.html

## Problema Identificado

Quando você acessa:
- ✅ `http://localhost/saas-stripe-frontend/index.html` → Funciona
- ❌ `http://localhost/saas-stripe-frontend/` → Não funciona

## Causa

O código estava usando `window.location.origin` para construir as URLs de sucesso e cancelamento:

```javascript
// ❌ Código antigo (incorreto)
const successUrl = `${window.location.origin}/success.html?session_id={CHECKOUT_SESSION_ID}`;
const cancelUrl = `${window.location.origin}/index.html`;
```

**Problema:** `window.location.origin` retorna apenas `http://localhost`, sem incluir o caminho do diretório (`/saas-stripe-frontend`).

### Exemplo do que acontecia:

| URL Acessada | `window.location.origin` | URL Gerada | Status |
|--------------|-------------------------|------------|--------|
| `http://localhost/saas-stripe-frontend/` | `http://localhost` | `http://localhost/success.html` | ❌ Errado |
| `http://localhost/saas-stripe-frontend/index.html` | `http://localhost` | `http://localhost/success.html` | ❌ Errado (mas funcionava por acaso) |

## Solução

Criamos uma função `getBaseUrl()` que detecta automaticamente o caminho base correto:

```javascript
/**
 * Obtém a URL base do site (com o caminho do diretório)
 * Funciona tanto com / quanto com /index.html
 */
function getBaseUrl() {
    const origin = window.location.origin;
    const pathname = window.location.pathname;
    
    // Remove o nome do arquivo se existir (ex: /index.html)
    const basePath = pathname.replace(/\/[^/]*$/, '') || '';
    
    return origin + basePath;
}
```

### Como funciona:

| URL Acessada | `pathname` | `basePath` | `baseUrl` | URL Gerada |
|--------------|------------|------------|-----------|------------|
| `http://localhost/saas-stripe-frontend/` | `/saas-stripe-frontend/` | `/saas-stripe-frontend` | `http://localhost/saas-stripe-frontend` | ✅ Correto |
| `http://localhost/saas-stripe-frontend/index.html` | `/saas-stripe-frontend/index.html` | `/saas-stripe-frontend` | `http://localhost/saas-stripe-frontend` | ✅ Correto |

## Código Atualizado

```javascript
// ✅ Código novo (correto)
const baseUrl = getBaseUrl();
const successUrl = `${baseUrl}/success.html?session_id={CHECKOUT_SESSION_ID}`;
const cancelUrl = `${baseUrl}/index.html`;
```

## Teste

Agora você pode acessar de qualquer forma:
- ✅ `http://localhost/saas-stripe-frontend/`
- ✅ `http://localhost/saas-stripe-frontend/index.html`
- ✅ `http://localhost/saas-stripe-frontend/index.html#qualquer-coisa`

Todos devem funcionar corretamente!

## Debug

Se quiser verificar as URLs geradas, abra o console (F12) e procure por:

```
URLs do checkout: {
    currentUrl: "http://localhost/saas-stripe-frontend/",
    origin: "http://localhost",
    pathname: "/saas-stripe-frontend/",
    baseUrl: "http://localhost/saas-stripe-frontend",
    successUrl: "http://localhost/saas-stripe-frontend/success.html?session_id={CHECKOUT_SESSION_ID}",
    cancelUrl: "http://localhost/saas-stripe-frontend/index.html"
}
```

