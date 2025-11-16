/**
 * Script para página de dashboard
 */

let currentSubscriptionId = null;
let cancelModal = null;

document.addEventListener('DOMContentLoaded', function() {
    loadSubscriptions();
    
    // Inicializar modal
    cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
    
    // Event listener para confirmar cancelamento
    document.getElementById('confirmCancelBtn').addEventListener('click', handleConfirmCancel);
});

/**
 * Carrega todas as assinaturas
 */
async function loadSubscriptions() {
    const loadingState = document.getElementById('loadingState');
    const subscriptionsContainer = document.getElementById('subscriptionsContainer');
    const emptyState = document.getElementById('emptyState');
    
    try {
        loadingState.style.display = 'block';
        subscriptionsContainer.style.display = 'none';
        emptyState.style.display = 'none';
        
        const result = await api.listSubscriptions();
        const subscriptions = result.data || [];
        
        loadingState.style.display = 'none';
        
        if (subscriptions.length === 0) {
            emptyState.style.display = 'block';
            return;
        }
        
        subscriptionsContainer.style.display = 'block';
        renderSubscriptions(subscriptions);
    } catch (error) {
        loadingState.style.display = 'none';
        showAlert('danger', `Erro ao carregar assinaturas: ${error.message}`);
    }
}

/**
 * Renderiza as assinaturas na tela
 */
function renderSubscriptions(subscriptions) {
    const container = document.getElementById('subscriptionsContainer');
    container.innerHTML = '';
    
    subscriptions.forEach(subscription => {
        const subscriptionCard = createSubscriptionCard(subscription);
        container.appendChild(subscriptionCard);
    });
}

/**
 * Cria um card de assinatura
 */
function createSubscriptionCard(subscription) {
    const col = document.createElement('div');
    col.className = 'col-12';
    
    const status = subscription.status || 'unknown';
    const statusClass = `status-${status}`;
    const statusText = formatStatus(status);
    
    const planName = subscription.plan?.nickname || 
                     subscription.plan?.product?.name || 
                     subscription.plan?.id || 
                     'Plano';
    
    const amount = subscription.plan?.unit_amount ? subscription.plan.unit_amount / 100 : 0;
    const currency = subscription.plan?.currency?.toUpperCase() || 'BRL';
    const interval = subscription.plan?.recurring?.interval || 'month';
    const intervalText = interval === 'month' ? 'mês' : interval === 'year' ? 'ano' : interval;
    
    const currentPeriodEnd = subscription.current_period_end 
        ? new Date(subscription.current_period_end * 1000).toLocaleDateString('pt-BR')
        : 'N/A';
    
    const currentPeriodStart = subscription.current_period_start
        ? new Date(subscription.current_period_start * 1000).toLocaleDateString('pt-BR')
        : 'N/A';
    
    col.innerHTML = `
        <div class="card card-subscription">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="card-title mb-2">
                            <i class="bi bi-credit-card me-2"></i>${planName}
                        </h5>
                        <p class="text-muted mb-2">
                            <span class="status-badge ${statusClass}">${statusText}</span>
                        </p>
                        <div class="mt-3">
                            <p class="mb-1">
                                <strong>Valor:</strong> ${formatCurrency(amount, currency)}/${intervalText}
                            </p>
                            <p class="mb-1">
                                <strong>Período atual:</strong> ${currentPeriodStart} até ${currentPeriodEnd}
                            </p>
                            ${subscription.trial_end ? `
                                <p class="mb-1">
                                    <strong>Trial até:</strong> ${new Date(subscription.trial_end * 1000).toLocaleDateString('pt-BR')}
                                </p>
                            ` : ''}
                        </div>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        ${status === 'active' ? `
                            <button class="btn btn-danger-custom me-2" onclick="openCancelModal('${subscription.id}')">
                                <i class="bi bi-x-circle me-2"></i>Cancelar Assinatura
                            </button>
                        ` : ''}
                        ${status === 'canceled' ? `
                            <button class="btn btn-success-custom" onclick="reactivateSubscription('${subscription.id}')">
                                <i class="bi bi-arrow-clockwise me-2"></i>Reativar Assinatura
                            </button>
                        ` : ''}
                        ${status === 'trialing' ? `
                            <p class="text-muted mb-0">
                                <small>Período de teste ativo</small>
                            </p>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    return col;
}

/**
 * Abre modal de confirmação de cancelamento
 */
function openCancelModal(subscriptionId) {
    currentSubscriptionId = subscriptionId;
    cancelModal.show();
}

/**
 * Confirma cancelamento
 */
async function handleConfirmCancel() {
    if (!currentSubscriptionId) return;
    
    const immediately = document.getElementById('cancelImmediately').checked;
    const confirmBtn = document.getElementById('confirmCancelBtn');
    
    try {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cancelando...';
        
        await api.cancelSubscription(currentSubscriptionId, immediately);
        
        cancelModal.hide();
        showAlert('success', immediately 
            ? 'Assinatura cancelada imediatamente.' 
            : 'Assinatura será cancelada no final do período.');
        
        // Recarregar lista
        setTimeout(() => {
            loadSubscriptions();
        }, 1000);
    } catch (error) {
        showAlert('danger', `Erro ao cancelar assinatura: ${error.message}`);
    } finally {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = 'Sim, Cancelar';
    }
}

/**
 * Reativa uma assinatura cancelada
 */
async function reactivateSubscription(subscriptionId) {
    if (!confirm('Tem certeza que deseja reativar esta assinatura?')) {
        return;
    }
    
    try {
        await api.reactivateSubscription(subscriptionId);
        showAlert('success', 'Assinatura reativada com sucesso!');
        
        // Recarregar lista
        setTimeout(() => {
            loadSubscriptions();
        }, 1000);
    } catch (error) {
        showAlert('danger', `Erro ao reativar assinatura: ${error.message}`);
    }
}

/**
 * Formata status da assinatura
 */
function formatStatus(status) {
    const statusMap = {
        'active': 'Ativa',
        'trialing': 'Em Teste',
        'canceled': 'Cancelada',
        'past_due': 'Pagamento Atrasado',
        'unpaid': 'Não Paga',
        'incomplete': 'Incompleta',
        'incomplete_expired': 'Incompleta Expirada',
        'paused': 'Pausada'
    };
    
    return statusMap[status] || status;
}

/**
 * Formata moeda
 */
function formatCurrency(amount, currency) {
    const currencyMap = {
        'BRL': 'pt-BR',
        'USD': 'en-US',
        'EUR': 'de-DE'
    };
    
    const locale = currencyMap[currency] || 'pt-BR';
    
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Mostra um alerta
 */
function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    const alertId = 'alert-' + Date.now();
    
    const alertHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
            <i class="bi bi-${type === 'danger' ? 'x-circle' : type === 'warning' ? 'exclamation-triangle' : 'check-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    container.innerHTML = alertHTML;
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}

