<?php
/**
 * View de Detalhes do Cliente
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/customers">Clientes</a></li>
            <li class="breadcrumb-item active">Detalhes</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>
    <div id="loadingCustomer" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div id="customerDetails" style="display: none;">
        <!-- Modo Visualização -->
        <div id="viewMode">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Informações do Cliente</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleEditMode()">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                </div>
                <div class="card-body" id="customerInfo">
                </div>
            </div>
        </div>

        <!-- Modo Edição -->
        <div id="editMode" style="display: none;">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editar Cliente</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEditMode()">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
                <div class="card-body">
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
                            <button type="button" class="btn btn-secondary" onclick="toggleEditMode()">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Assinaturas -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Assinaturas</h5>
                    </div>
                    <div class="card-body">
                        <div id="subscriptionsList"></div>
                    </div>
                </div>
            </div>

            <!-- Faturas -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Faturas</h5>
                    </div>
                    <div class="card-body">
                        <div id="invoicesList"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Métodos de Pagamento -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Métodos de Pagamento</h5>
            </div>
            <div class="card-body">
                <div id="paymentMethodsList"></div>
            </div>
        </div>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
const customerId = urlParams.get('id');

if (!customerId) {
    window.location.href = '/customers';
}

document.addEventListener('DOMContentLoaded', () => {
    loadCustomerDetails();
});

async function loadCustomerDetails() {
    try {
        const [customer, subscriptions, invoices, paymentMethods] = await Promise.all([
            apiRequest(`/v1/customers/${customerId}`, { cacheTTL: 30000 }),
            apiRequest(`/v1/customers/${customerId}/subscriptions`, { cacheTTL: 20000 }).catch(() => ({ data: [] })),
            apiRequest(`/v1/customers/${customerId}/invoices`, { cacheTTL: 20000 }).catch(() => ({ data: [] })),
            apiRequest(`/v1/customers/${customerId}/payment-methods`, { cacheTTL: 30000 }).catch(() => ({ data: [] }))
        ]);

        customerData = customer.data;
        renderCustomerInfo(customerData);
        renderSubscriptions(subscriptions.data || []);
        renderInvoices(invoices.data || []);
        renderPaymentMethods(paymentMethods.data || []);

        document.getElementById('loadingCustomer').style.display = 'none';
        document.getElementById('customerDetails').style.display = 'block';
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
    }
}

function renderCustomerInfo(customer) {
    document.getElementById('customerInfo').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> ${customer.id}</p>
                <p><strong>Nome:</strong> ${customer.name || '-'}</p>
                <p><strong>Email:</strong> ${customer.email}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Stripe ID:</strong> <code>${customer.stripe_customer_id || '-'}</code></p>
                <p><strong>Criado em:</strong> ${formatDate(customer.created_at)}</p>
            </div>
        </div>
    `;
}

function renderSubscriptions(subscriptions) {
    const container = document.getElementById('subscriptionsList');
    if (subscriptions.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhuma assinatura encontrada</p>';
        return;
    }
    container.innerHTML = subscriptions.map(sub => `
        <div class="border-bottom pb-2 mb-2">
            <a href="/subscription-details?id=${sub.id}">Assinatura #${sub.id}</a>
            <span class="badge bg-${sub.status === 'active' ? 'success' : 'secondary'} ms-2">${sub.status}</span>
        </div>
    `).join('');
}

function renderInvoices(invoices) {
    const container = document.getElementById('invoicesList');
    if (invoices.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhuma fatura encontrada</p>';
        return;
    }
    container.innerHTML = invoices.slice(0, 5).map(inv => `
        <div class="border-bottom pb-2 mb-2">
            <div class="d-flex justify-content-between">
                <div>
                    <strong>${inv.id}</strong><br>
                    <small class="text-muted">${formatDate(inv.created)}</small>
                </div>
                <div class="text-end">
                    <strong>${formatCurrency(inv.amount_due, inv.currency)}</strong><br>
                    <span class="badge bg-${inv.status === 'paid' ? 'success' : 'warning'}">${inv.status}</span>
                </div>
            </div>
        </div>
    `).join('');
}

function renderPaymentMethods(methods) {
    const container = document.getElementById('paymentMethodsList');
    if (methods.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhum método de pagamento encontrado</p>';
        return;
    }
    container.innerHTML = methods.map(pm => `
        <div class="border rounded p-3 mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>${pm.type === 'card' ? 'Cartão' : pm.type}</strong>
                    ${pm.card ? ` - ****${pm.card.last4} (${pm.card.brand})` : ''}
                </div>
                ${pm.is_default ? '<span class="badge bg-primary">Padrão</span>' : ''}
            </div>
        </div>
    `).join('');
}

let customerData = null;

function toggleEditMode() {
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    
    if (viewMode.style.display === 'none') {
        // Voltar para visualização
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        // Ir para edição
        if (customerData) {
            loadCustomerForEdit();
        }
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}

function loadCustomerForEdit() {
    if (!customerData) return;
    
    document.getElementById('editCustomerId').value = customerData.id;
    document.getElementById('editCustomerName').value = customerData.name || '';
    document.getElementById('editCustomerEmail').value = customerData.email || '';
    
    if (customerData.metadata) {
        document.getElementById('editCustomerMetadata').value = 
            JSON.stringify(customerData.metadata, null, 2);
    } else {
        document.getElementById('editCustomerMetadata').value = '';
    }
}

// Submissão do formulário de edição
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
            showAlert('Erro: Metadados devem estar em formato JSON válido', 'danger');
            return;
        }
    }

    const submitBtn = this.querySelector('button[type="submit"]');
    const spinner = submitBtn.querySelector('.spinner-border');
    const errorDiv = document.getElementById('editCustomerError');
    
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    errorDiv.classList.add('d-none');

    apiRequest(`/v1/customers/${customerId}`, {
        method: 'PUT',
        body: JSON.stringify(formData)
    })
    .then(data => {
        if (data.success) {
            showAlert('Cliente atualizado com sucesso!', 'success');
            customerData = data.data;
            renderCustomerInfo(customerData);
            toggleEditMode();
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
</script>

