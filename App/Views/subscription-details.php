<?php
/**
 * View de Detalhes da Assinatura
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/subscriptions">Assinaturas</a></li>
            <li class="breadcrumb-item active">Detalhes</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>
    <div id="loadingSubscription" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div id="subscriptionDetails" style="display: none;">
        <!-- Modo Visualização -->
        <div id="viewMode">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Informações da Assinatura</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="toggleEditMode()">
                            <i class="bi bi-pencil"></i> Editar
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="cancelSubscription()">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="card-body" id="subscriptionInfo">
                </div>
            </div>
        </div>

        <!-- Modo Edição -->
        <div id="editMode" style="display: none;">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editar Assinatura</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEditMode()">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
                <div class="card-body">
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
                            <button type="button" class="btn btn-secondary" onclick="toggleEditMode()">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Histórico</h5>
            </div>
            <div class="card-body">
                <div id="historyList"></div>
            </div>
        </div>
    </div>
</div>

<script>
const subscriptionId = new URLSearchParams(window.location.search).get('id');

if (!subscriptionId) {
    window.location.href = '/subscriptions';
}

document.addEventListener('DOMContentLoaded', () => {
    loadSubscriptionDetails();
});

async function loadSubscriptionDetails() {
    try {
        const [subscription, history] = await Promise.all([
            apiRequest(`/v1/subscriptions/${subscriptionId}`, { cacheTTL: 15000 }),
            apiRequest(`/v1/subscriptions/${subscriptionId}/history`, { cacheTTL: 30000 }).catch(() => ({ data: [] }))
        ]);

        subscriptionData = subscription.data;
        renderSubscriptionInfo(subscriptionData);
        renderHistory(history.data || []);

        document.getElementById('loadingSubscription').style.display = 'none';
        document.getElementById('subscriptionDetails').style.display = 'block';
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
    }
}

function renderSubscriptionInfo(sub) {
    document.getElementById('subscriptionInfo').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> ${sub.id}</p>
                <p><strong>Status:</strong> <span class="badge bg-${sub.status === 'active' ? 'success' : 'secondary'}">${sub.status}</span></p>
                <p><strong>Cliente:</strong> ${sub.customer_id}</p>
                <p><strong>Preço:</strong> <code>${sub.price_id || '-'}</code></p>
            </div>
            <div class="col-md-6">
                <p><strong>Valor:</strong> ${sub.amount ? formatCurrency(sub.amount, sub.currency || 'BRL') : '-'}</p>
                <p><strong>Próximo Pagamento:</strong> ${sub.current_period_end ? formatDate(sub.current_period_end) : '-'}</p>
                <p><strong>Criado em:</strong> ${formatDate(sub.created_at)}</p>
            </div>
        </div>
    `;
}

function renderHistory(history) {
    const container = document.getElementById('historyList');
    if (history.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhum histórico encontrado</p>';
        return;
    }
    container.innerHTML = history.map(h => `
        <div class="border-bottom pb-2 mb-2">
            <strong>${h.action || h.event}</strong> - ${formatDate(h.created_at || h.timestamp)}
        </div>
    `).join('');
}

let subscriptionData = null;
let prices = [];

function toggleEditMode() {
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    
    if (viewMode.style.display === 'none') {
        // Voltar para visualização
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        // Ir para edição
        if (subscriptionData) {
            loadSubscriptionForEdit();
        }
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}

async function loadSubscriptionForEdit() {
    if (!subscriptionData) return;
    
    document.getElementById('editSubscriptionId').value = subscriptionData.id;
    document.getElementById('editSubscriptionCancelAtPeriodEnd').checked = subscriptionData.cancel_at_period_end || false;
    
    if (subscriptionData.metadata) {
        document.getElementById('editSubscriptionMetadata').value = 
            JSON.stringify(subscriptionData.metadata, null, 2);
    } else {
        document.getElementById('editSubscriptionMetadata').value = '';
    }
    
    // Carregar preços para o select
    if (prices.length === 0) {
        try {
            const response = await apiRequest('/v1/prices?active=true', { cacheTTL: 60000 });
            prices = response.data || [];
            
            const select = document.getElementById('editSubscriptionPrice');
            select.innerHTML = '<option value="">Manter plano atual</option>';
            prices.forEach(price => {
                const option = document.createElement('option');
                option.value = price.id;
                const amount = formatCurrency(price.unit_amount, price.currency);
                const interval = price.recurring?.interval || 'one-time';
                option.textContent = `${price.product?.name || 'Produto'} - ${amount}/${interval}`;
                if (price.id === subscriptionData.price_id) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Erro ao carregar preços:', error);
        }
    }
}

// Submissão do formulário de edição
document.getElementById('editSubscriptionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const subscriptionId = document.getElementById('editSubscriptionId').value;
    const formData = {};

    const priceId = document.getElementById('editSubscriptionPrice').value;
    if (priceId) {
        formData.price_id = priceId;
    }

    formData.cancel_at_period_end = document.getElementById('editSubscriptionCancelAtPeriodEnd').checked;

    const metadataText = document.getElementById('editSubscriptionMetadata').value.trim();
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
    const errorDiv = document.getElementById('editSubscriptionError');
    
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    errorDiv.classList.add('d-none');

    apiRequest(`/v1/subscriptions/${subscriptionId}`, {
        method: 'PUT',
        body: JSON.stringify(formData)
    })
    .then(data => {
        if (data.success) {
            showAlert('Assinatura atualizada com sucesso!', 'success');
            subscriptionData = data.data;
            renderSubscriptionInfo(subscriptionData);
            toggleEditMode();
        } else {
            throw new Error(data.error || 'Erro ao atualizar assinatura');
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

async function cancelSubscription() {
    const confirmed = await showConfirmModal(
        'Tem certeza que deseja cancelar esta assinatura? Esta ação não pode ser desfeita.',
        'Confirmar Cancelamento',
        'Cancelar Assinatura'
    );
    if (!confirmed) return;
    
    try {
        await apiRequest(`/v1/subscriptions/${subscriptionId}`, { method: 'DELETE' });
        showAlert('Assinatura cancelada com sucesso!', 'success');
        setTimeout(() => window.location.href = '/subscriptions', 2000);
    } catch (error) {
        showAlert('Erro ao cancelar assinatura: ' + error.message, 'danger');
    }
}
</script>

