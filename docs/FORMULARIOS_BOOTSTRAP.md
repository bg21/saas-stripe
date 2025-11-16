# Documentação Completa de Formulários - Bootstrap

Este documento detalha todos os formulários necessários no front-end, com estrutura HTML usando Bootstrap 5, campos específicos, validações e exemplos de código.

---

## 📋 Índice de Formulários

1. [Formulário de Dados do Cliente (Público)](#1-formulário-de-dados-do-cliente-público)
2. [Formulário de Login](#2-formulário-de-login)
3. [Formulário de Criar Cliente](#3-formulário-de-criar-cliente)
4. [Formulário de Editar Cliente](#4-formulário-de-editar-cliente)
5. [Formulário de Criar Assinatura](#5-formulário-de-criar-assinatura)
6. [Formulário de Editar Assinatura](#6-formulário-de-editar-assinatura)
7. [Formulário de Criar Usuário](#7-formulário-de-criar-usuário)
8. [Formulário de Editar Usuário](#8-formulário-de-editar-usuário)
9. [Formulário de Criar Produto](#9-formulário-de-criar-produto)
10. [Formulário de Editar Produto](#10-formulário-de-editar-produto)
11. [Formulário de Criar Preço](#11-formulário-de-criar-preço)
12. [Formulário de Editar Preço](#12-formulário-de-editar-preço)
13. [Formulário de Criar Cupom](#13-formulário-de-criar-cupom)
14. [Formulário de Criar Código Promocional](#14-formulário-de-criar-código-promocional)
15. [Formulário de Criar Reembolso](#15-formulário-de-criar-reembolso)
16. [Formulário de Adicionar Evidências em Disputa](#16-formulário-de-adicionar-evidências-em-disputa)
17. [Formulário de Atualizar Método de Pagamento](#17-formulário-de-atualizar-método-de-pagamento)
18. [Formulário de Criar Invoice Item](#18-formulário-de-criar-invoice-item)
19. [Formulário de Criar Tax Rate](#19-formulário-de-criar-tax-rate)
20. [Formulário de Criar Subscription Item](#20-formulário-de-criar-subscription-item)

---

## 1. Formulário de Dados do Cliente (Público)

### 📄 Descrição
Formulário simples para coletar dados básicos do cliente antes do checkout. Usado na página pública de seleção de planos.

### 🎯 Rota da API
**POST `/v1/customers`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `name` | text | ✅ Sim | Min: 2 caracteres | Nome completo do cliente |
| `email` | email | ✅ Sim | Email válido | Email do cliente |

### 📝 Estrutura HTML (Bootstrap 5)

```html
<form id="customerForm" class="needs-validation" novalidate>
    <div class="mb-3">
        <label for="customerName" class="form-label">
            Nome Completo <span class="text-danger">*</span>
        </label>
        <input 
            type="text" 
            class="form-control" 
            id="customerName" 
            name="name"
            required 
            minlength="2"
            placeholder="Digite seu nome completo"
        >
        <div class="invalid-feedback">
            Por favor, insira um nome válido (mínimo 2 caracteres).
        </div>
    </div>

    <div class="mb-3">
        <label for="customerEmail" class="form-label">
            Email <span class="text-danger">*</span>
        </label>
        <input 
            type="email" 
            class="form-control" 
            id="customerEmail" 
            name="email"
            required 
            placeholder="seu@email.com"
        >
        <div class="invalid-feedback">
            Por favor, insira um email válido.
        </div>
    </div>

    <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary btn-lg">
            Continuar para Pagamento
        </button>
        <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
            Voltar para Planos
        </button>
    </div>
</form>
```

### 🔍 Validação JavaScript

```javascript
(function() {
    'use strict';
    const form = document.getElementById('customerForm');
    
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        } else {
            event.preventDefault();
            submitCustomerForm();
        }
        
        form.classList.add('was-validated');
    }, false);
})();

function submitCustomerForm() {
    const formData = {
        name: document.getElementById('customerName').value.trim(),
        email: document.getElementById('customerEmail').value.trim(),
        metadata: {
            source: 'website',
            plan_selected: localStorage.getItem('selectedPlanId')
        }
    };

    // Mostrar loading
    const submitBtn = document.querySelector('#customerForm button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';

    fetch(`${API_CONFIG.baseUrl}/v1/customers`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${API_CONFIG.apiKey}`
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Salvar customer no localStorage
            localStorage.setItem('customer', JSON.stringify(data.data));
            // Redirecionar para checkout
            window.location.href = '/checkout.html';
        } else {
            throw new Error(data.error || 'Erro ao criar cliente');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao criar conta: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}
```

### ⚠️ Tratamento de Erros

- **Email já existe**: Mostrar mensagem "Este email já está cadastrado. Deseja continuar?"
- **Campos inválidos**: Validação HTML5 + mensagens customizadas
- **Erro de API**: Mostrar alerta com mensagem de erro

---

## 2. Formulário de Login

### 📄 Descrição
Formulário de autenticação para usuários administrativos do sistema.

### 🎯 Rota da API
**POST `/v1/auth/login`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `email` | email | ✅ Sim | Email válido | Email do usuário |
| `password` | password | ✅ Sim | Min: 6 caracteres | Senha do usuário |
| `tenant_id` | number | ✅ Sim | Número inteiro | ID do tenant |

### 📝 Estrutura HTML (Bootstrap 5)

```html
<form id="loginForm" class="needs-validation" novalidate>
    <div class="mb-3">
        <label for="loginEmail" class="form-label">
            Email <span class="text-danger">*</span>
        </label>
        <input 
            type="email" 
            class="form-control" 
            id="loginEmail" 
            name="email"
            required 
            placeholder="usuario@exemplo.com"
            autocomplete="email"
        >
        <div class="invalid-feedback">
            Por favor, insira um email válido.
        </div>
    </div>

    <div class="mb-3">
        <label for="loginPassword" class="form-label">
            Senha <span class="text-danger">*</span>
        </label>
        <input 
            type="password" 
            class="form-control" 
            id="loginPassword" 
            name="password"
            required 
            minlength="6"
            placeholder="Digite sua senha"
            autocomplete="current-password"
        >
        <div class="invalid-feedback">
            A senha deve ter no mínimo 6 caracteres.
        </div>
    </div>

    <div class="mb-3">
        <label for="loginTenantId" class="form-label">
            Tenant ID <span class="text-danger">*</span>
        </label>
        <input 
            type="number" 
            class="form-control" 
            id="loginTenantId" 
            name="tenant_id"
            required 
            min="1"
            placeholder="1"
        >
        <div class="form-text">
            ID do seu tenant (fornecido pelo administrador)
        </div>
        <div class="invalid-feedback">
            Por favor, insira um Tenant ID válido.
        </div>
    </div>

    <div class="mb-3 form-check">
        <input 
            type="checkbox" 
            class="form-check-input" 
            id="rememberMe"
            name="remember_me"
        >
        <label class="form-check-label" for="rememberMe">
            Lembrar-me
        </label>
    </div>

    <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary btn-lg">
            Entrar
        </button>
    </div>

    <div id="loginError" class="alert alert-danger mt-3 d-none" role="alert"></div>
</form>
```

### 🔍 Validação JavaScript

```javascript
(function() {
    'use strict';
    const form = document.getElementById('loginForm');
    
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        } else {
            event.preventDefault();
            submitLoginForm();
        }
        
        form.classList.add('was-validated');
    }, false);
})();

function submitLoginForm() {
    const formData = {
        email: document.getElementById('loginEmail').value.trim(),
        password: document.getElementById('loginPassword').value,
        tenant_id: parseInt(document.getElementById('loginTenantId').value)
    };

    const submitBtn = document.querySelector('#loginForm button[type="submit"]');
    const errorDiv = document.getElementById('loginError');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Entrando...';
    errorDiv.classList.add('d-none');

    fetch(`${API_CONFIG.baseUrl}/v1/auth/login`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Salvar token e dados do usuário
            localStorage.setItem('authToken', data.data.token);
            localStorage.setItem('user', JSON.stringify(data.data.user));
            localStorage.setItem('tenant', JSON.stringify(data.data.tenant));
            
            // Redirecionar para dashboard
            window.location.href = '/dashboard.html';
        } else {
            throw new Error(data.error || 'Erro ao fazer login');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        errorDiv.textContent = error.message || 'Email ou senha incorretos';
        errorDiv.classList.remove('d-none');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}
```

---

## 3. Formulário de Criar Cliente

### 📄 Descrição
Formulário administrativo para criar novos clientes no sistema.

### 🎯 Rota da API
**POST `/v1/customers`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `email` | email | ✅ Sim | Email válido | Email do cliente |
| `name` | text | ✅ Sim | Min: 2 caracteres | Nome do cliente |
| `metadata` | object | ❌ Não | JSON válido | Metadados adicionais (opcional) |

### 📝 Estrutura HTML (Bootstrap 5 - Modal)

```html
<!-- Botão para abrir modal -->
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCustomerModal">
    <i class="bi bi-plus-circle me-2"></i>Criar Cliente
</button>

<!-- Modal -->
<div class="modal fade" id="createCustomerModal" tabindex="-1" aria-labelledby="createCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createCustomerModalLabel">Criar Novo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createCustomerForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="createCustomerName" class="form-label">
                            Nome <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="createCustomerName" 
                            name="name"
                            required 
                            minlength="2"
                            placeholder="Nome do cliente"
                        >
                        <div class="invalid-feedback">
                            Por favor, insira um nome válido.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createCustomerEmail" class="form-label">
                            Email <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="email" 
                            class="form-control" 
                            id="createCustomerEmail" 
                            name="email"
                            required 
                            placeholder="cliente@exemplo.com"
                        >
                        <div class="invalid-feedback">
                            Por favor, insira um email válido.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createCustomerMetadata" class="form-label">
                            Metadados (JSON) <small class="text-muted">(Opcional)</small>
                        </label>
                        <textarea 
                            class="form-control" 
                            id="createCustomerMetadata" 
                            name="metadata"
                            rows="3"
                            placeholder='{"source": "admin", "notes": "Cliente VIP"}'
                        ></textarea>
                        <div class="form-text">
                            Metadados adicionais em formato JSON
                        </div>
                    </div>

                    <div id="createCustomerError" class="alert alert-danger d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                        Criar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

### 🔍 Validação JavaScript

```javascript
document.getElementById('createCustomerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const formData = {
        name: document.getElementById('createCustomerName').value.trim(),
        email: document.getElementById('createCustomerEmail').value.trim()
    };

    // Processar metadata se fornecido
    const metadataText = document.getElementById('createCustomerMetadata').value.trim();
    if (metadataText) {
        try {
            formData.metadata = JSON.parse(metadataText);
        } catch (error) {
            alert('Erro: Metadados devem estar em formato JSON válido');
            return;
        }
    }

    const submitBtn = this.querySelector('button[type="submit"]');
    const spinner = submitBtn.querySelector('.spinner-border');
    const errorDiv = document.getElementById('createCustomerError');
    
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    errorDiv.classList.add('d-none');

    fetch(`${API_CONFIG.baseUrl}/v1/customers`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${getAuthToken()}`
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('createCustomerModal'));
            modal.hide();
            
            // Recarregar lista de clientes
            loadCustomers();
            
            // Mostrar toast de sucesso
            showToast('Cliente criado com sucesso!', 'success');
        } else {
            throw new Error(data.error || 'Erro ao criar cliente');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        errorDiv.textContent = error.message;
        errorDiv.classList.remove('d-none');
    })
    .finally(() => {
        submitBtn.disabled = false;
        spinner.classList.add('d-none');
    });
});
```

---

## 4. Formulário de Editar Cliente

### 📄 Descrição
Formulário para editar dados de um cliente existente.

### 🎯 Rota da API
**PUT `/v1/customers/:id`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `email` | email | ✅ Sim | Email válido | Email do cliente |
| `name` | text | ✅ Sim | Min: 2 caracteres | Nome do cliente |
| `metadata` | object | ❌ Não | JSON válido | Metadados adicionais |

### 📝 Estrutura HTML (Bootstrap 5)

```html
<form id="editCustomerForm" class="needs-validation" novalidate>
    <input type="hidden" id="editCustomerId" name="customer_id">
    
    <div class="mb-3">
        <label for="editCustomerName" class="form-label">
            Nome <span class="text-danger">*</span>
        </label>
        <input 
            type="text" 
            class="form-control" 
            id="editCustomerName" 
            name="name"
            required 
            minlength="2"
        >
        <div class="invalid-feedback">
            Por favor, insira um nome válido.
        </div>
    </div>

    <div class="mb-3">
        <label for="editCustomerEmail" class="form-label">
            Email <span class="text-danger">*</span>
        </label>
        <input 
            type="email" 
            class="form-control" 
            id="editCustomerEmail" 
            name="email"
            required 
        >
        <div class="invalid-feedback">
            Por favor, insira um email válido.
        </div>
    </div>

    <div class="mb-3">
        <label for="editCustomerMetadata" class="form-label">
            Metadados (JSON) <small class="text-muted">(Opcional)</small>
        </label>
        <textarea 
            class="form-control font-monospace" 
            id="editCustomerMetadata" 
            name="metadata"
            rows="4"
        ></textarea>
        <div class="form-text">
            Metadados em formato JSON
        </div>
    </div>

    <div id="editCustomerError" class="alert alert-danger d-none mb-3" role="alert"></div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
            Salvar Alterações
        </button>
        <button type="button" class="btn btn-secondary" onclick="loadCustomerDetails()">
            Cancelar
        </button>
    </div>
</form>
```

### 🔍 Função para Carregar Dados

```javascript
function loadCustomerForEdit(customerId) {
    fetch(`${API_CONFIG.baseUrl}/v1/customers/${customerId}`, {
        headers: {
            'Authorization': `Bearer ${getAuthToken()}`
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const customer = data.data;
            
            document.getElementById('editCustomerId').value = customer.id;
            document.getElementById('editCustomerName').value = customer.name || '';
            document.getElementById('editCustomerEmail').value = customer.email || '';
            
            if (customer.metadata) {
                document.getElementById('editCustomerMetadata').value = 
                    JSON.stringify(customer.metadata, null, 2);
            }
        }
    })
    .catch(error => {
        console.error('Erro ao carregar cliente:', error);
        alert('Erro ao carregar dados do cliente');
    });
}

// Submissão do formulário
document.getElementById('editCustomerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const customerId = document.getElementById('editCustomerId').value;
    const formData = {
        name: document.getElementById('editCustomerName').value.trim(),
        email: document.getElementById('editCustomerEmail').value.trim()
    };

    const metadataText = document.getElementById('editCustomerMetadata').value.trim();
    if (metadataText) {
        try {
            formData.metadata = JSON.parse(metadataText);
        } catch (error) {
            alert('Erro: Metadados devem estar em formato JSON válido');
            return;
        }
    }

    const submitBtn = this.querySelector('button[type="submit"]');
    const spinner = submitBtn.querySelector('.spinner-border');
    const errorDiv = document.getElementById('editCustomerError');
    
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    errorDiv.classList.add('d-none');

    fetch(`${API_CONFIG.baseUrl}/v1/customers/${customerId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${getAuthToken()}`
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Cliente atualizado com sucesso!', 'success');
            loadCustomerDetails(customerId);
        } else {
            throw new Error(data.error || 'Erro ao atualizar cliente');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        errorDiv.textContent = error.message;
        errorDiv.classList.remove('d-none');
    })
    .finally(() => {
        submitBtn.disabled = false;
        spinner.classList.add('d-none');
    });
});
```

---

## 5. Formulário de Criar Assinatura

### 📄 Descrição
Formulário para criar uma nova assinatura para um cliente.

### 🎯 Rota da API
**POST `/v1/subscriptions`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `customer_id` | number | ✅ Sim | Número inteiro | ID do cliente no banco |
| `price_id` | text | ✅ Sim | String (price_xxx) | ID do preço no Stripe |
| `trial_period_days` | number | ❌ Não | Inteiro positivo | Dias de trial (opcional) |
| `payment_behavior` | select | ❌ Não | Enum | Comportamento de pagamento |
| `metadata` | object | ❌ Não | JSON válido | Metadados adicionais |

### 📝 Estrutura HTML (Bootstrap 5 - Modal)

```html
<div class="modal fade" id="createSubscriptionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Criar Nova Assinatura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createSubscriptionForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="createSubscriptionCustomer" class="form-label">
                            Cliente <span class="text-danger">*</span>
                        </label>
                        <select 
                            class="form-select" 
                            id="createSubscriptionCustomer" 
                            name="customer_id"
                            required
                        >
                            <option value="">Selecione um cliente</option>
                            <!-- Opções carregadas via JavaScript -->
                        </select>
                        <div class="invalid-feedback">
                            Por favor, selecione um cliente.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createSubscriptionPrice" class="form-label">
                            Plano/Preço <span class="text-danger">*</span>
                        </label>
                        <select 
                            class="form-select" 
                            id="createSubscriptionPrice" 
                            name="price_id"
                            required
                        >
                            <option value="">Selecione um plano</option>
                            <!-- Opções carregadas via JavaScript -->
                        </select>
                        <div class="invalid-feedback">
                            Por favor, selecione um plano.
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="createSubscriptionTrial" class="form-label">
                                Período de Trial (dias) <small class="text-muted">(Opcional)</small>
                            </label>
                            <input 
                                type="number" 
                                class="form-control" 
                                id="createSubscriptionTrial" 
                                name="trial_period_days"
                                min="0"
                                placeholder="0"
                            >
                            <div class="form-text">
                                Deixe em branco ou 0 para não ter trial
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="createSubscriptionPaymentBehavior" class="form-label">
                                Comportamento de Pagamento <small class="text-muted">(Opcional)</small>
                            </label>
                            <select 
                                class="form-select" 
                                id="createSubscriptionPaymentBehavior" 
                                name="payment_behavior"
                            >
                                <option value="">Padrão</option>
                                <option value="default_incomplete">Incompleto por padrão</option>
                                <option value="error_if_incomplete">Erro se incompleto</option>
                                <option value="pending_if_incomplete">Pendente se incompleto</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createSubscriptionMetadata" class="form-label">
                            Metadados (JSON) <small class="text-muted">(Opcional)</small>
                        </label>
                        <textarea 
                            class="form-control font-monospace" 
                            id="createSubscriptionMetadata" 
                            name="metadata"
                            rows="3"
                            placeholder='{"source": "admin", "notes": "Assinatura criada manualmente"}'
                        ></textarea>
                    </div>

                    <div id="createSubscriptionError" class="alert alert-danger d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                        Criar Assinatura
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

### 🔍 JavaScript para Carregar Opções

```javascript
// Carregar clientes e preços ao abrir modal
document.getElementById('createSubscriptionModal').addEventListener('show.bs.modal', function() {
    loadCustomersForSelect();
    loadPricesForSelect();
});

function loadCustomersForSelect() {
    fetch(`${API_CONFIG.baseUrl}/v1/customers`, {
        headers: {
            'Authorization': `Bearer ${getAuthToken()}`
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('createSubscriptionCustomer');
            select.innerHTML = '<option value="">Selecione um cliente</option>';
            
            data.data.forEach(customer => {
                const option = document.createElement('option');
                option.value = customer.id;
                option.textContent = `${customer.name} (${customer.email})`;
                select.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Erro ao carregar clientes:', error));
}

function loadPricesForSelect() {
    fetch(`${API_CONFIG.baseUrl}/v1/prices?active=true`, {
        headers: {
            'Authorization': `Bearer ${getAuthToken()}`
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('createSubscriptionPrice');
            select.innerHTML = '<option value="">Selecione um plano</option>';
            
            data.data.forEach(price => {
                const option = document.createElement('option');
                option.value = price.id;
                const amount = (price.unit_amount / 100).toLocaleString('pt-BR', {
                    style: 'currency',
                    currency: price.currency.toUpperCase()
                });
                const interval = price.recurring?.interval || 'one-time';
                option.textContent = `${price.product?.name || 'Produto'} - ${amount}/${interval}`;
                select.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Erro ao carregar preços:', error));
}

// Submissão do formulário
document.getElementById('createSubscriptionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const formData = {
        customer_id: parseInt(document.getElementById('createSubscriptionCustomer').value),
        price_id: document.getElementById('createSubscriptionPrice').value
    };

    const trialDays = document.getElementById('createSubscriptionTrial').value;
    if (trialDays && parseInt(trialDays) > 0) {
        formData.trial_period_days = parseInt(trialDays);
    }

    const paymentBehavior = document.getElementById('createSubscriptionPaymentBehavior').value;
    if (paymentBehavior) {
        formData.payment_behavior = paymentBehavior;
    }

    const metadataText = document.getElementById('createSubscriptionMetadata').value.trim();
    if (metadataText) {
        try {
            formData.metadata = JSON.parse(metadataText);
        } catch (error) {
            alert('Erro: Metadados devem estar em formato JSON válido');
            return;
        }
    }

    const submitBtn = this.querySelector('button[type="submit"]');
    const spinner = submitBtn.querySelector('.spinner-border');
    const errorDiv = document.getElementById('createSubscriptionError');
    
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    errorDiv.classList.add('d-none');

    fetch(`${API_CONFIG.baseUrl}/v1/subscriptions`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${getAuthToken()}`
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('createSubscriptionModal'));
            modal.hide();
            showToast('Assinatura criada com sucesso!', 'success');
            loadSubscriptions();
        } else {
            throw new Error(data.error || 'Erro ao criar assinatura');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        errorDiv.textContent = error.message;
        errorDiv.classList.remove('d-none');
    })
    .finally(() => {
        submitBtn.disabled = false;
        spinner.classList.add('d-none');
    });
});
```

---

## 6. Formulário de Editar Assinatura

### 📄 Descrição
Formulário para atualizar uma assinatura existente (mudar plano, cancelar no final do período, etc.).

### 🎯 Rota da API
**PUT `/v1/subscriptions/:id`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `price_id` | text | ❌ Não | String (price_xxx) | Novo preço/plano |
| `cancel_at_period_end` | checkbox | ❌ Não | Boolean | Cancelar no final do período |
| `metadata` | object | ❌ Não | JSON válido | Metadados adicionais |

### 📝 Estrutura HTML (Bootstrap 5)

```html
<form id="editSubscriptionForm" class="needs-validation" novalidate>
    <input type="hidden" id="editSubscriptionId" name="subscription_id">
    
    <div class="mb-3">
        <label for="editSubscriptionPrice" class="form-label">
            Alterar Plano/Preço <small class="text-muted">(Opcional)</small>
        </label>
        <select 
            class="form-select" 
            id="editSubscriptionPrice" 
            name="price_id"
        >
            <option value="">Manter plano atual</option>
            <!-- Opções carregadas via JavaScript -->
        </select>
        <div class="form-text">
            Selecione um novo plano para alterar a assinatura
        </div>
    </div>

    <div class="mb-3">
        <div class="form-check form-switch">
            <input 
                class="form-check-input" 
                type="checkbox" 
                id="editSubscriptionCancelAtPeriodEnd" 
                name="cancel_at_period_end"
            >
            <label class="form-check-label" for="editSubscriptionCancelAtPeriodEnd">
                Cancelar no final do período atual
            </label>
        </div>
        <div class="form-text">
            Se marcado, a assinatura será cancelada ao final do período atual, mas continuará ativa até lá.
        </div>
    </div>

    <div class="mb-3">
        <label for="editSubscriptionMetadata" class="form-label">
            Metadados (JSON) <small class="text-muted">(Opcional)</small>
        </label>
        <textarea 
            class="form-control font-monospace" 
            id="editSubscriptionMetadata" 
            name="metadata"
            rows="4"
        ></textarea>
    </div>

    <div id="editSubscriptionError" class="alert alert-danger d-none mb-3" role="alert"></div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
            Salvar Alterações
        </button>
        <button type="button" class="btn btn-secondary" onclick="loadSubscriptionDetails()">
            Cancelar
        </button>
    </div>
</form>
```

---

## 7. Formulário de Criar Usuário

### 📄 Descrição
Formulário administrativo para criar novos usuários do sistema.

### 🎯 Rota da API
**POST `/v1/users`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `email` | email | ✅ Sim | Email válido | Email do usuário |
| `password` | password | ✅ Sim | Min: 6 caracteres | Senha do usuário |
| `name` | text | ✅ Sim | Min: 2 caracteres | Nome do usuário |
| `role` | select | ✅ Sim | Enum (admin, editor, viewer) | Role do usuário |
| `status` | select | ❌ Não | Enum (active, inactive) | Status (padrão: active) |

### 📝 Estrutura HTML (Bootstrap 5 - Modal)

```html
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Criar Novo Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createUserForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="createUserName" class="form-label">
                            Nome <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="createUserName" 
                            name="name"
                            required 
                            minlength="2"
                            placeholder="Nome completo"
                        >
                        <div class="invalid-feedback">
                            Por favor, insira um nome válido.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createUserEmail" class="form-label">
                            Email <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="email" 
                            class="form-control" 
                            id="createUserEmail" 
                            name="email"
                            required 
                            placeholder="usuario@exemplo.com"
                        >
                        <div class="invalid-feedback">
                            Por favor, insira um email válido.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createUserPassword" class="form-label">
                            Senha <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="createUserPassword" 
                            name="password"
                            required 
                            minlength="6"
                            placeholder="Mínimo 6 caracteres"
                        >
                        <div class="invalid-feedback">
                            A senha deve ter no mínimo 6 caracteres.
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="createUserRole" class="form-label">
                                Role <span class="text-danger">*</span>
                            </label>
                            <select 
                                class="form-select" 
                                id="createUserRole" 
                                name="role"
                                required
                            >
                                <option value="">Selecione um role</option>
                                <option value="admin">Administrador</option>
                                <option value="editor">Editor</option>
                                <option value="viewer">Visualizador</option>
                            </select>
                            <div class="invalid-feedback">
                                Por favor, selecione um role.
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="createUserStatus" class="form-label">
                                Status
                            </label>
                            <select 
                                class="form-select" 
                                id="createUserStatus" 
                                name="status"
                            >
                                <option value="active" selected>Ativo</option>
                                <option value="inactive">Inativo</option>
                            </select>
                        </div>
                    </div>

                    <div id="createUserError" class="alert alert-danger d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                        Criar Usuário
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

---

## 8. Formulário de Editar Usuário

### 📄 Descrição
Formulário para editar dados de um usuário existente (sem alterar senha).

### 🎯 Rota da API
**PUT `/v1/users/:id`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `name` | text | ✅ Sim | Min: 2 caracteres | Nome do usuário |
| `email` | email | ✅ Sim | Email válido | Email do usuário |
| `status` | select | ❌ Não | Enum (active, inactive) | Status do usuário |

**Nota:** Para alterar senha, criar endpoint separado. Para alterar role, usar `PUT /v1/users/:id/role`.

### 📝 Estrutura HTML (Bootstrap 5)

```html
<form id="editUserForm" class="needs-validation" novalidate>
    <input type="hidden" id="editUserId" name="user_id">
    
    <div class="mb-3">
        <label for="editUserName" class="form-label">
            Nome <span class="text-danger">*</span>
        </label>
        <input 
            type="text" 
            class="form-control" 
            id="editUserName" 
            name="name"
            required 
            minlength="2"
        >
        <div class="invalid-feedback">
            Por favor, insira um nome válido.
        </div>
    </div>

    <div class="mb-3">
        <label for="editUserEmail" class="form-label">
            Email <span class="text-danger">*</span>
        </label>
        <input 
            type="email" 
            class="form-control" 
            id="editUserEmail" 
            name="email"
            required 
        >
        <div class="invalid-feedback">
            Por favor, insira um email válido.
        </div>
    </div>

    <div class="mb-3">
        <label for="editUserStatus" class="form-label">
            Status
        </label>
        <select 
            class="form-select" 
            id="editUserStatus" 
            name="status"
        >
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Alterar Role</label>
        <div class="d-flex gap-2">
            <select class="form-select" id="editUserRole">
                <option value="">Selecione um role</option>
                <option value="admin">Administrador</option>
                <option value="editor">Editor</option>
                <option value="viewer">Visualizador</option>
            </select>
            <button type="button" class="btn btn-outline-primary" onclick="updateUserRole()">
                Atualizar Role
            </button>
        </div>
        <div class="form-text">
            Use o botão acima para alterar o role do usuário
        </div>
    </div>

    <div id="editUserError" class="alert alert-danger d-none mb-3" role="alert"></div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        <button type="button" class="btn btn-secondary" onclick="loadUserDetails()">Cancelar</button>
    </div>
</form>
```

---

## 9. Formulário de Criar Produto

### 📄 Descrição
Formulário para criar um novo produto no Stripe.

### 🎯 Rota da API
**POST `/v1/products`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `name` | text | ✅ Sim | Min: 1 caractere | Nome do produto |
| `description` | textarea | ❌ Não | Texto | Descrição do produto |
| `metadata` | object | ❌ Não | JSON válido | Metadados adicionais |

### 📝 Estrutura HTML (Bootstrap 5 - Modal)

```html
<div class="modal fade" id="createProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Criar Novo Produto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createProductForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="createProductName" class="form-label">
                            Nome do Produto <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="createProductName" 
                            name="name"
                            required 
                            placeholder="Ex: Plano Premium"
                        >
                        <div class="invalid-feedback">
                            Por favor, insira um nome para o produto.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createProductDescription" class="form-label">
                            Descrição <small class="text-muted">(Opcional)</small>
                        </label>
                        <textarea 
                            class="form-control" 
                            id="createProductDescription" 
                            name="description"
                            rows="3"
                            placeholder="Descreva o produto..."
                        ></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="createProductMetadata" class="form-label">
                            Metadados (JSON) <small class="text-muted">(Opcional)</small>
                        </label>
                        <textarea 
                            class="form-control font-monospace" 
                            id="createProductMetadata" 
                            name="metadata"
                            rows="3"
                            placeholder='{"category": "premium", "features": "all"}'
                        ></textarea>
                    </div>

                    <div id="createProductError" class="alert alert-danger d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Produto</button>
                </div>
            </form>
        </div>
    </div>
</div>
```

---

## 10. Formulário de Editar Produto

### 📄 Descrição
Formulário para atualizar um produto existente.

### 🎯 Rota da API
**PUT `/v1/products/:id`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `name` | text | ❌ Não | Min: 1 caractere | Nome do produto |
| `description` | textarea | ❌ Não | Texto | Descrição do produto |
| `metadata` | object | ❌ Não | JSON válido | Metadados adicionais |

**Nota:** Todos os campos são opcionais. Apenas os campos preenchidos serão atualizados.

---

## 11. Formulário de Criar Preço

### 📄 Descrição
Formulário para criar um novo preço associado a um produto.

### 🎯 Rota da API
**POST `/v1/prices`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `product` | select | ✅ Sim | String (prod_xxx) | ID do produto |
| `unit_amount` | number | ✅ Sim | Inteiro positivo | Valor em centavos |
| `currency` | select | ✅ Sim | String (brl, usd, etc) | Moeda |
| `recurring.interval` | select | ✅ Sim* | Enum (month, year) | Intervalo de cobrança |
| `recurring.interval_count` | number | ❌ Não | Inteiro positivo | Contagem de intervalos |
| `metadata` | object | ❌ Não | JSON válido | Metadados adicionais |

*Obrigatório se for assinatura recorrente.

### 📝 Estrutura HTML (Bootstrap 5 - Modal)

```html
<div class="modal fade" id="createPriceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Criar Novo Preço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createPriceForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="createPriceProduct" class="form-label">
                            Produto <span class="text-danger">*</span>
                        </label>
                        <select 
                            class="form-select" 
                            id="createPriceProduct" 
                            name="product"
                            required
                        >
                            <option value="">Selecione um produto</option>
                            <!-- Carregado via JavaScript -->
                        </select>
                        <div class="invalid-feedback">
                            Por favor, selecione um produto.
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="createPriceAmount" class="form-label">
                                Valor (em centavos) <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="number" 
                                class="form-control" 
                                id="createPriceAmount" 
                                name="unit_amount"
                                required 
                                min="1"
                                placeholder="2999"
                                step="1"
                            >
                            <div class="form-text">
                                Ex: 2999 = R$ 29,99
                            </div>
                            <div class="invalid-feedback">
                                Por favor, insira um valor válido.
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="createPriceCurrency" class="form-label">
                                Moeda <span class="text-danger">*</span>
                            </label>
                            <select 
                                class="form-select" 
                                id="createPriceCurrency" 
                                name="currency"
                                required
                            >
                                <option value="brl">BRL (Real Brasileiro)</option>
                                <option value="usd">USD (Dólar Americano)</option>
                                <option value="eur">EUR (Euro)</option>
                            </select>
                            <div class="invalid-feedback">
                                Por favor, selecione uma moeda.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tipo de Cobrança</label>
                        <div class="form-check">
                            <input 
                                class="form-check-input" 
                                type="radio" 
                                name="billingType" 
                                id="billingTypeOneTime" 
                                value="one_time"
                                checked
                                onchange="toggleRecurringFields()"
                            >
                            <label class="form-check-label" for="billingTypeOneTime">
                                Pagamento Único
                            </label>
                        </div>
                        <div class="form-check">
                            <input 
                                class="form-check-input" 
                                type="radio" 
                                name="billingType" 
                                id="billingTypeRecurring" 
                                value="recurring"
                                onchange="toggleRecurringFields()"
                            >
                            <label class="form-check-label" for="billingTypeRecurring">
                                Assinatura Recorrente
                            </label>
                        </div>
                    </div>

                    <div id="recurringFields" class="d-none">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="createPriceInterval" class="form-label">
                                    Intervalo <span class="text-danger">*</span>
                                </label>
                                <select 
                                    class="form-select" 
                                    id="createPriceInterval" 
                                    name="recurring_interval"
                                >
                                    <option value="month">Mensal</option>
                                    <option value="year">Anual</option>
                                    <option value="week">Semanal</option>
                                    <option value="day">Diário</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="createPriceIntervalCount" class="form-label">
                                    Contagem de Intervalos <small class="text-muted">(Opcional)</small>
                                </label>
                                <input 
                                    type="number" 
                                    class="form-control" 
                                    id="createPriceIntervalCount" 
                                    name="recurring_interval_count"
                                    min="1"
                                    value="1"
                                    placeholder="1"
                                >
                                <div class="form-text">
                                    Ex: 3 = a cada 3 meses/anos
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createPriceMetadata" class="form-label">
                            Metadados (JSON) <small class="text-muted">(Opcional)</small>
                        </label>
                        <textarea 
                            class="form-control font-monospace" 
                            id="createPriceMetadata" 
                            name="metadata"
                            rows="3"
                        ></textarea>
                    </div>

                    <div id="createPriceError" class="alert alert-danger d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Preço</button>
                </div>
            </form>
        </div>
    </div>
</div>
```

### 🔍 JavaScript para Toggle de Campos Recorrentes

```javascript
function toggleRecurringFields() {
    const billingType = document.querySelector('input[name="billingType"]:checked').value;
    const recurringFields = document.getElementById('recurringFields');
    const intervalField = document.getElementById('createPriceInterval');
    
    if (billingType === 'recurring') {
        recurringFields.classList.remove('d-none');
        intervalField.setAttribute('required', 'required');
    } else {
        recurringFields.classList.add('d-none');
        intervalField.removeAttribute('required');
    }
}

// Submissão do formulário
document.getElementById('createPriceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const formData = {
        product: document.getElementById('createPriceProduct').value,
        unit_amount: parseInt(document.getElementById('createPriceAmount').value),
        currency: document.getElementById('createPriceCurrency').value
    };

    const billingType = document.querySelector('input[name="billingType"]:checked').value;
    if (billingType === 'recurring') {
        formData.recurring = {
            interval: document.getElementById('createPriceInterval').value
        };
        
        const intervalCount = document.getElementById('createPriceIntervalCount').value;
        if (intervalCount && parseInt(intervalCount) > 1) {
            formData.recurring.interval_count = parseInt(intervalCount);
        }
    }

    const metadataText = document.getElementById('createPriceMetadata').value.trim();
    if (metadataText) {
        try {
            formData.metadata = JSON.parse(metadataText);
        } catch (error) {
            alert('Erro: Metadados devem estar em formato JSON válido');
            return;
        }
    }

    // Enviar para API...
    submitCreatePrice(formData);
});
```

---

## 12. Formulário de Editar Preço

### 📄 Descrição
Formulário para atualizar metadata de um preço (preços do Stripe não podem ter outros campos alterados).

### 🎯 Rota da API
**PUT `/v1/prices/:id`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `metadata` | object | ❌ Não | JSON válido | Apenas metadata pode ser atualizado |

**Nota:** No Stripe, preços não podem ser editados após criação (exceto metadata). Para alterar, é necessário criar um novo preço.

---

## 13. Formulário de Criar Cupom

### 📄 Descrição
Formulário para criar um cupom de desconto.

### 🎯 Rota da API
**POST `/v1/coupons`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `id` | text | ✅ Sim | String única | ID do cupom (ex: "desconto10") |
| `percent_off` | number | ✅ Sim* | 1-100 | Percentual de desconto |
| `amount_off` | number | ✅ Sim* | Inteiro positivo | Valor fixo de desconto (centavos) |
| `currency` | select | ❌ Não | String | Moeda (se usar amount_off) |
| `duration` | select | ✅ Sim | Enum | Duração do desconto |
| `duration_in_months` | number | ❌ Não | Inteiro positivo | Meses (se duration = repeating) |
| `max_redemptions` | number | ❌ Não | Inteiro positivo | Máximo de usos |
| `redeem_by` | date | ❌ Não | Data | Data de expiração |
| `metadata` | object | ❌ Não | JSON válido | Metadados adicionais |

*Um dos dois (percent_off ou amount_off) é obrigatório.

### 📝 Estrutura HTML (Bootstrap 5 - Modal)

```html
<div class="modal fade" id="createCouponModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Criar Novo Cupom</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createCouponForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="createCouponId" class="form-label">
                            ID do Cupom <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control font-monospace" 
                            id="createCouponId" 
                            name="id"
                            required 
                            pattern="[a-z0-9_]+"
                            placeholder="desconto10"
                        >
                        <div class="form-text">
                            Apenas letras minúsculas, números e underscore. Ex: desconto10, promo_2024
                        </div>
                        <div class="invalid-feedback">
                            ID inválido. Use apenas letras minúsculas, números e underscore.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tipo de Desconto <span class="text-danger">*</span></label>
                        <div class="form-check">
                            <input 
                                class="form-check-input" 
                                type="radio" 
                                name="discountType" 
                                id="discountTypePercent" 
                                value="percent"
                                checked
                                onchange="toggleDiscountFields()"
                            >
                            <label class="form-check-label" for="discountTypePercent">
                                Percentual (%)
                            </label>
                        </div>
                        <div class="form-check">
                            <input 
                                class="form-check-input" 
                                type="radio" 
                                name="discountType" 
                                id="discountTypeAmount" 
                                value="amount"
                                onchange="toggleDiscountFields()"
                            >
                            <label class="form-check-label" for="discountTypeAmount">
                                Valor Fixo
                            </label>
                        </div>
                    </div>

                    <div id="percentDiscountFields">
                        <div class="mb-3">
                            <label for="createCouponPercentOff" class="form-label">
                                Percentual de Desconto <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input 
                                    type="number" 
                                    class="form-control" 
                                    id="createCouponPercentOff" 
                                    name="percent_off"
                                    min="1"
                                    max="100"
                                    placeholder="10"
                                >
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="invalid-feedback">
                                Por favor, insira um percentual entre 1 e 100.
                            </div>
                        </div>
                    </div>

                    <div id="amountDiscountFields" class="d-none">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="createCouponAmountOff" class="form-label">
                                    Valor do Desconto (em centavos) <span class="text-danger">*</span>
                                </label>
                                <input 
                                    type="number" 
                                    class="form-control" 
                                    id="createCouponAmountOff" 
                                    name="amount_off"
                                    min="1"
                                    placeholder="1000"
                                >
                                <div class="form-text">
                                    Ex: 1000 = R$ 10,00 de desconto
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="createCouponCurrency" class="form-label">
                                    Moeda <span class="text-danger">*</span>
                                </label>
                                <select 
                                    class="form-select" 
                                    id="createCouponCurrency" 
                                    name="currency"
                                >
                                    <option value="brl">BRL</option>
                                    <option value="usd">USD</option>
                                    <option value="eur">EUR</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createCouponDuration" class="form-label">
                            Duração <span class="text-danger">*</span>
                        </label>
                        <select 
                            class="form-select" 
                            id="createCouponDuration" 
                            name="duration"
                            required
                            onchange="toggleDurationFields()"
                        >
                            <option value="once">Uma vez</option>
                            <option value="repeating">Repetir por X meses</option>
                            <option value="forever">Para sempre</option>
                        </select>
                        <div class="invalid-feedback">
                            Por favor, selecione uma duração.
                        </div>
                    </div>

                    <div id="durationMonthsFields" class="d-none mb-3">
                        <label for="createCouponDurationMonths" class="form-label">
                            Número de Meses <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="number" 
                            class="form-control" 
                            id="createCouponDurationMonths" 
                            name="duration_in_months"
                            min="1"
                            placeholder="3"
                        >
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="createCouponMaxRedemptions" class="form-label">
                                Máximo de Usos <small class="text-muted">(Opcional)</small>
                            </label>
                            <input 
                                type="number" 
                                class="form-control" 
                                id="createCouponMaxRedemptions" 
                                name="max_redemptions"
                                min="1"
                                placeholder="Ilimitado"
                            >
                            <div class="form-text">
                                Deixe em branco para uso ilimitado
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="createCouponRedeemBy" class="form-label">
                                Data de Expiração <small class="text-muted">(Opcional)</small>
                            </label>
                            <input 
                                type="date" 
                                class="form-control" 
                                id="createCouponRedeemBy" 
                                name="redeem_by"
                            >
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createCouponMetadata" class="form-label">
                            Metadados (JSON) <small class="text-muted">(Opcional)</small>
                        </label>
                        <textarea 
                            class="form-control font-monospace" 
                            id="createCouponMetadata" 
                            name="metadata"
                            rows="3"
                        ></textarea>
                    </div>

                    <div id="createCouponError" class="alert alert-danger d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Cupom</button>
                </div>
            </form>
        </div>
    </div>
</div>
```

### 🔍 JavaScript para Toggle de Campos

```javascript
function toggleDiscountFields() {
    const discountType = document.querySelector('input[name="discountType"]:checked').value;
    const percentFields = document.getElementById('percentDiscountFields');
    const amountFields = document.getElementById('amountDiscountFields');
    const percentInput = document.getElementById('createCouponPercentOff');
    const amountInput = document.getElementById('createCouponAmountOff');
    const currencySelect = document.getElementById('createCouponCurrency');

    if (discountType === 'percent') {
        percentFields.classList.remove('d-none');
        amountFields.classList.add('d-none');
        percentInput.setAttribute('required', 'required');
        amountInput.removeAttribute('required');
        currencySelect.removeAttribute('required');
    } else {
        percentFields.classList.add('d-none');
        amountFields.classList.remove('d-none');
        percentInput.removeAttribute('required');
        amountInput.setAttribute('required', 'required');
        currencySelect.setAttribute('required', 'required');
    }
}

function toggleDurationFields() {
    const duration = document.getElementById('createCouponDuration').value;
    const durationMonthsFields = document.getElementById('durationMonthsFields');
    const durationMonthsInput = document.getElementById('createCouponDurationMonths');

    if (duration === 'repeating') {
        durationMonthsFields.classList.remove('d-none');
        durationMonthsInput.setAttribute('required', 'required');
    } else {
        durationMonthsFields.classList.add('d-none');
        durationMonthsInput.removeAttribute('required');
    }
}
```

---

## 14. Formulário de Criar Código Promocional

### 📄 Descrição
Formulário para criar um código promocional baseado em um cupom.

### 🎯 Rota da API
**POST `/v1/promotion-codes`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `coupon` | select | ✅ Sim | String (cupom ID) | ID do cupom |
| `code` | text | ❌ Não | String única | Código promocional (gerado se vazio) |
| `active` | checkbox | ❌ Não | Boolean | Ativo (padrão: true) |
| `max_redemptions` | number | ❌ Não | Inteiro positivo | Máximo de usos |
| `expires_at` | datetime | ❌ Não | Data/hora | Data de expiração |
| `metadata` | object | ❌ Não | JSON válido | Metadados adicionais |

---

## 15. Formulário de Criar Reembolso

### 📄 Descrição
Formulário para criar um reembolso de uma cobrança.

### 🎯 Rota da API
**POST `/v1/refunds`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `charge_id` | text | ✅ Sim | String (ch_xxx) | ID da cobrança |
| `amount` | number | ❌ Não | Inteiro positivo | Valor em centavos (se vazio, reembolsa total) |
| `reason` | select | ❌ Não | Enum | Motivo do reembolso |
| `metadata` | object | ❌ Não | JSON válido | Metadados adicionais |

### 📝 Estrutura HTML (Bootstrap 5 - Modal)

```html
<div class="modal fade" id="createRefundModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Criar Reembolso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createRefundForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="createRefundChargeId" class="form-label">
                            ID da Cobrança <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control font-monospace" 
                            id="createRefundChargeId" 
                            name="charge_id"
                            required 
                            pattern="ch_[a-zA-Z0-9]+"
                            placeholder="ch_xxxxx"
                        >
                        <div class="invalid-feedback">
                            Por favor, insira um ID de cobrança válido.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createRefundAmount" class="form-label">
                            Valor do Reembolso (em centavos) <small class="text-muted">(Opcional)</small>
                        </label>
                        <input 
                            type="number" 
                            class="form-control" 
                            id="createRefundAmount" 
                            name="amount"
                            min="1"
                            placeholder="Deixe em branco para reembolso total"
                        >
                        <div class="form-text">
                            Deixe em branco para reembolsar o valor total da cobrança
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createRefundReason" class="form-label">
                            Motivo do Reembolso <small class="text-muted">(Opcional)</small>
                        </label>
                        <select 
                            class="form-select" 
                            id="createRefundReason" 
                            name="reason"
                        >
                            <option value="">Selecione um motivo</option>
                            <option value="duplicate">Duplicado</option>
                            <option value="fraudulent">Fraudulento</option>
                            <option value="requested_by_customer">Solicitado pelo cliente</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="createRefundMetadata" class="form-label">
                            Metadados (JSON) <small class="text-muted">(Opcional)</small>
                        </label>
                        <textarea 
                            class="form-control font-monospace" 
                            id="createRefundMetadata" 
                            name="metadata"
                            rows="3"
                        ></textarea>
                    </div>

                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> Reembolsos são irreversíveis. Certifique-se de que deseja prosseguir.
                    </div>

                    <div id="createRefundError" class="alert alert-danger d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Reembolso</button>
                </div>
            </form>
        </div>
    </div>
</div>
```

---

## 16. Formulário de Adicionar Evidências em Disputa

### 📄 Descrição
Formulário para adicionar evidências em uma disputa/chargeback.

### 🎯 Rota da API
**PUT `/v1/disputes/:id`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `evidence.customer_communication` | textarea | ❌ Não | Texto | Comunicação com cliente |
| `evidence.uncategorized_file` | file | ❌ Não | Arquivo | Arquivo de evidência |
| `evidence.uncategorized_text` | textarea | ❌ Não | Texto | Texto de evidência |

---

## 17. Formulário de Atualizar Método de Pagamento

### 📄 Descrição
Formulário para atualizar informações de um método de pagamento.

### 🎯 Rota da API
**PUT `/v1/customers/:id/payment-methods/:pm_id`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `billing_details.name` | text | ❌ Não | Texto | Nome no cartão |
| `billing_details.email` | email | ❌ Não | Email válido | Email |
| `billing_details.phone` | tel | ❌ Não | Telefone | Telefone |
| `billing_details.address.line1` | text | ❌ Não | Texto | Endereço linha 1 |
| `billing_details.address.line2` | text | ❌ Não | Texto | Endereço linha 2 |
| `billing_details.address.city` | text | ❌ Não | Texto | Cidade |
| `billing_details.address.state` | text | ❌ Não | Texto | Estado |
| `billing_details.address.postal_code` | text | ❌ Não | Texto | CEP |
| `billing_details.address.country` | select | ❌ Não | String | País |

---

## 18. Formulário de Criar Invoice Item

### 📄 Descrição
Formulário para criar um item de fatura adicional.

### 🎯 Rota da API
**POST `/v1/invoice-items`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `customer_id` | number | ✅ Sim | Inteiro | ID do cliente |
| `amount` | number | ✅ Sim | Inteiro positivo | Valor em centavos |
| `currency` | select | ✅ Sim | String | Moeda |
| `description` | textarea | ❌ Não | Texto | Descrição do item |
| `metadata` | object | ❌ Não | JSON válido | Metadados |

---

## 19. Formulário de Criar Tax Rate

### 📄 Descrição
Formulário para criar uma taxa de imposto.

### 🎯 Rota da API
**POST `/v1/tax-rates`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `display_name` | text | ✅ Sim | Texto | Nome da taxa |
| `description` | textarea | ❌ Não | Texto | Descrição |
| `percentage` | number | ✅ Sim | 0-100 | Percentual da taxa |
| `inclusive` | checkbox | ❌ Não | Boolean | Se é inclusivo |
| `metadata` | object | ❌ Não | JSON válido | Metadados |

---

## 20. Formulário de Criar Subscription Item

### 📄 Descrição
Formulário para adicionar um item a uma assinatura existente.

### 🎯 Rota da API
**POST `/v1/subscriptions/:subscription_id/items`**

### 📋 Campos do Formulário

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `price_id` | select | ✅ Sim | String (price_xxx) | ID do preço |
| `quantity` | number | ❌ Não | Inteiro positivo | Quantidade (padrão: 1) |

---

## 📝 Notas Gerais sobre Formulários

### Validação HTML5 + Bootstrap

Todos os formulários devem usar:
- `class="needs-validation"` no `<form>`
- `novalidate` no `<form>` para desabilitar validação nativa
- `required` nos campos obrigatórios
- `class="invalid-feedback"` para mensagens de erro
- `class="was-validated"` após primeira submissão

### Padrões de Design

- **Campos obrigatórios**: Marcar com `<span class="text-danger">*</span>`
- **Campos opcionais**: Marcar com `<small class="text-muted">(Opcional)</small>`
- **Help text**: Usar `<div class="form-text">` para dicas
- **Loading states**: Usar `spinner-border` nos botões durante submissão
- **Error messages**: Usar `alert alert-danger` para erros

### Funções Auxiliares Recomendadas

```javascript
// Obter token de autenticação
function getAuthToken() {
    return localStorage.getItem('authToken') || API_CONFIG.apiKey;
}

// Mostrar toast de notificação
function showToast(message, type = 'success') {
    // Implementar com Bootstrap Toast ou biblioteca de notificações
}

// Formatar valor monetário
function formatCurrency(amount, currency = 'BRL') {
    return (amount / 100).toLocaleString('pt-BR', {
        style: 'currency',
        currency: currency.toUpperCase()
    });
}

// Validar JSON
function isValidJSON(str) {
    try {
        JSON.parse(str);
        return true;
    } catch (e) {
        return false;
    }
}
```

---

**Última atualização:** Baseado nas rotas documentadas em `docs/ROTAS_API.md` e controllers em `App/Controllers/`

