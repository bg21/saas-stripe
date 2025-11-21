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
                <div class="col-md-3">
                    <label class="form-label">Cliente ID</label>
                    <input type="number" class="form-control" id="customerFilter" placeholder="ID do cliente">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" id="typeFilter">
                        <option value="">Todos</option>
                        <option value="card">Cartão</option>
                        <option value="bank_account">Conta Bancária</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadPaymentMethods()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <div id="loadingMethods" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div id="methodsList" style="display: none;">
                <p class="text-muted">Selecione um cliente para ver seus métodos de pagamento</p>
                <div id="paymentMethodsContainer"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        // Carrega métodos de pagamento se houver customer_id na URL
        const urlParams = new URLSearchParams(window.location.search);
        const customerId = urlParams.get('customer_id');
        if (customerId) {
            document.getElementById('customerFilter').value = customerId;
            loadPaymentMethods();
        }
    }, 100);
});

async function loadPaymentMethods() {
    const customerId = document.getElementById('customerFilter').value;
    
    if (!customerId) {
        showAlert('Por favor, informe o ID do cliente', 'warning');
        return;
    }
    
    try {
        document.getElementById('loadingMethods').style.display = 'block';
        document.getElementById('methodsList').style.display = 'none';
        
        const response = await apiRequest(`/v1/customers/${customerId}/payment-methods`);
        const methods = response.data || [];
        
        renderPaymentMethods(methods, customerId);
    } catch (error) {
        showAlert('Erro ao carregar métodos de pagamento: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingMethods').style.display = 'none';
        document.getElementById('methodsList').style.display = 'block';
    }
}

function renderPaymentMethods(methods, customerId) {
    const container = document.getElementById('paymentMethodsContainer');
    
    if (methods.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhum método de pagamento encontrado para este cliente</p>';
        return;
    }
    
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

