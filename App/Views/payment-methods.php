<?php
/**
 * View de Métodos de Pagamento
 */
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-4"><i class="bi bi-wallet2"></i> Métodos de Pagamento</h1>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Cliente</label>
                    <select class="form-select" id="customerFilter" onchange="loadPaymentMethods()">
                        <option value="">Selecione um cliente...</option>
                    </select>
                    <small class="text-muted">Ou informe o ID manualmente abaixo</small>
                </div>
                <div class="col-md-2">
                    <label class="form-label">ID Manual</label>
                    <input type="number" class="form-control" id="customerIdManual" placeholder="ID do cliente" onchange="document.getElementById('customerFilter').value = this.value || ''; loadPaymentMethods();">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" id="typeFilter" onchange="loadPaymentMethods()">
                        <option value="">Todos</option>
                        <option value="card">Cartão</option>
                        <option value="bank_account">Conta Bancária</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100" onclick="loadPaymentMethods()">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <div id="loadingMethods" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Carregando métodos de pagamento...</p>
            </div>
            <div id="methodsList">
                <div id="emptyState" class="text-center py-5">
                    <i class="bi bi-wallet2 fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Selecione um cliente para ver seus métodos de pagamento</p>
                </div>
                <div id="paymentMethodsContainer"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Carrega lista de clientes
    loadCustomers();
    
    setTimeout(() => {
        // Carrega métodos de pagamento se houver customer_id na URL
        const urlParams = new URLSearchParams(window.location.search);
        const customerId = urlParams.get('customer_id');
        if (customerId) {
            document.getElementById('customerFilter').value = customerId;
            document.getElementById('customerIdManual').value = customerId;
            loadPaymentMethods();
        }
    }, 100);
});

async function loadCustomers() {
    try {
        const response = await apiRequest('/v1/customers?limit=100');
        // ✅ CORREÇÃO: response.data já é o array de clientes
        const customers = Array.isArray(response.data) ? response.data : [];
        
        const select = document.getElementById('customerFilter');
        // Limpa opções existentes (exceto a primeira)
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }
        
        customers.forEach(customer => {
            const option = document.createElement('option');
            option.value = customer.id;
            const name = customer.name || customer.email || 'Cliente';
            option.textContent = `${name} (ID: ${customer.id})`;
            select.appendChild(option);
        });
    } catch (error) {
        console.warn('Erro ao carregar lista de clientes:', error);
        // Não bloqueia a funcionalidade se falhar
    }
}

async function loadPaymentMethods() {
    // ✅ CORREÇÃO: Tenta obter customer_id do select ou do input manual
    let customerId = document.getElementById('customerFilter').value;
    if (!customerId) {
        customerId = document.getElementById('customerIdManual').value;
        if (customerId) {
            document.getElementById('customerFilter').value = customerId;
        }
    } else {
        document.getElementById('customerIdManual').value = customerId;
    }
    
    if (!customerId) {
        showAlert('Por favor, selecione ou informe o ID do cliente', 'warning');
        // Mostra estado vazio
        document.getElementById('emptyState').style.display = 'block';
        document.getElementById('paymentMethodsContainer').innerHTML = '';
        return;
    }
    
    try {
        document.getElementById('loadingMethods').style.display = 'block';
        document.getElementById('emptyState').style.display = 'none';
        document.getElementById('paymentMethodsContainer').innerHTML = '';
        
        const response = await apiRequest(`/v1/customers/${customerId}/payment-methods`);
        // ✅ CORREÇÃO: Garante que response.data seja um array
        const methods = Array.isArray(response.data) ? response.data : [];
        
        renderPaymentMethods(methods, customerId);
    } catch (error) {
        console.error('Erro ao carregar métodos de pagamento:', error);
        showAlert('Erro ao carregar métodos de pagamento: ' + error.message, 'danger');
        // Mostra estado vazio em caso de erro
        document.getElementById('emptyState').style.display = 'block';
        document.getElementById('paymentMethodsContainer').innerHTML = '';
    } finally {
        document.getElementById('loadingMethods').style.display = 'none';
    }
}

function renderPaymentMethods(methods, customerId) {
    const container = document.getElementById('paymentMethodsContainer');
    const emptyState = document.getElementById('emptyState');
    
    // ✅ CORREÇÃO: Garante que methods seja um array
    if (!Array.isArray(methods)) {
        console.warn('renderPaymentMethods recebeu valor não-array:', methods);
        methods = [];
    }
    
    if (methods.length === 0) {
        emptyState.style.display = 'block';
        emptyState.innerHTML = `
            <i class="bi bi-wallet2 fs-1 text-muted"></i>
            <p class="text-muted mt-3">Nenhum método de pagamento encontrado para este cliente</p>
        `;
        container.innerHTML = '';
        return;
    }
    
    emptyState.style.display = 'none';
    container.innerHTML = `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Últimos 4 dígitos</th>
                        <th>Bandeira</th>
                        <th>Validade</th>
                        <th>Padrão</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${methods.map(pm => `
                        <tr>
                            <td><code>${pm.id}</code></td>
                            <td><span class="badge bg-info">${pm.type || '-'}</span></td>
                            <td>${pm.card ? `****${pm.card.last4}` : '-'}</td>
                            <td>${pm.card ? pm.card.brand : '-'}</td>
                            <td>${pm.card ? `${pm.card.exp_month}/${pm.card.exp_year}` : '-'}</td>
                            <td>${pm.is_default ? '<span class="badge bg-primary">Padrão</span>' : '-'}</td>
                            <td>
                                ${!pm.is_default ? `
                                    <button class="btn btn-sm btn-outline-primary" onclick="setDefault('${customerId}', '${pm.id}')">
                                        <i class="bi bi-star"></i> Definir Padrão
                                    </button>
                                ` : ''}
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteMethod('${customerId}', '${pm.id}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function setDefault(customerId, methodId) {
    // ✅ Valida formato de payment_method_id
    if (typeof validateStripeId === 'function') {
        const methodIdError = validateStripeId(methodId, 'payment_method_id', true);
        if (methodIdError) {
            showAlert('ID de método de pagamento inválido: ' + methodIdError, 'danger');
            return;
        }
    } else {
        // Fallback: validação básica
        const methodIdPattern = /^pm_[a-zA-Z0-9]+$/;
        if (!methodIdPattern.test(methodId)) {
            showAlert('Formato de Payment Method ID inválido. Use: pm_xxxxx', 'danger');
            return;
        }
    }
    
    try {
        await apiRequest(`/v1/customers/${customerId}/payment-methods/${methodId}/set-default`, {
            method: 'POST'
        });
        
        showAlert('Método de pagamento definido como padrão!', 'success');
        loadPaymentMethods();
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function deleteMethod(customerId, methodId) {
    // ✅ Valida formato de payment_method_id
    if (typeof validateStripeId === 'function') {
        const methodIdError = validateStripeId(methodId, 'payment_method_id', true);
        if (methodIdError) {
            showAlert('ID de método de pagamento inválido: ' + methodIdError, 'danger');
            return;
        }
    } else {
        // Fallback: validação básica
        const methodIdPattern = /^pm_[a-zA-Z0-9]+$/;
        if (!methodIdPattern.test(methodId)) {
            showAlert('Formato de Payment Method ID inválido. Use: pm_xxxxx', 'danger');
            return;
        }
    }
    
    const confirmed = await showConfirmModal(
        'Tem certeza que deseja remover este método de pagamento?',
        'Confirmar Exclusão',
        'Remover Método'
    );
    if (!confirmed) return;
    
    try {
        await apiRequest(`/v1/customers/${customerId}/payment-methods/${methodId}`, {
            method: 'DELETE'
        });
        
        showAlert('Método de pagamento removido!', 'success');
        loadPaymentMethods();
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

