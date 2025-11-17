/**
 * Script para página de sucesso
 */

document.addEventListener('DOMContentLoaded', function() {
    verifyCheckout();
});

/**
 * Verifica o status do checkout
 */
async function verifyCheckout() {
    // Obter session_id da URL
    const urlParams = new URLSearchParams(window.location.search);
    const sessionId = urlParams.get('session_id');
    
    if (!sessionId) {
        showError('Session ID não encontrado na URL');
        return;
    }
    
    try {
        // Verificar status do checkout
        const result = await api.getCheckout(sessionId);
        const checkout = result.data;
        
        // Mostrar informações do checkout
        displayCheckoutInfo(checkout);
        
        // Verificar status do pagamento
        if (checkout.payment_status === 'paid') {
            showSuccess();
        } else if (checkout.payment_status === 'unpaid' || checkout.payment_status === 'no_payment_required') {
            showPending();
        } else {
            showError('Status de pagamento desconhecido: ' + checkout.payment_status);
        }
    } catch (error) {
        console.error('Erro ao verificar checkout:', error);
        showError(`Erro ao verificar pagamento: ${error.message}`);
    }
}

/**
 * Mostra estado de sucesso
 */
function showSuccess() {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('errorState').style.display = 'none';
    document.getElementById('pendingState').style.display = 'none';
    document.getElementById('successState').style.display = 'block';
}

/**
 * Mostra estado de erro
 */
function showError(message) {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('successState').style.display = 'none';
    document.getElementById('pendingState').style.display = 'none';
    document.getElementById('errorState').style.display = 'block';
    document.getElementById('errorMessage').textContent = message;
}

/**
 * Mostra estado pendente
 */
function showPending() {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('successState').style.display = 'none';
    document.getElementById('errorState').style.display = 'none';
    document.getElementById('pendingState').style.display = 'block';
}

/**
 * Exibe informações do checkout
 */
function displayCheckoutInfo(checkout) {
    const infoContainer = document.getElementById('checkoutInfo');
    
    if (!checkout) return;
    
    const infoHTML = `
        <div class="card bg-light">
            <div class="card-body">
                <h6 class="card-title mb-3">
                    <i class="bi bi-receipt me-2"></i>Detalhes da Transação
                </h6>
                <div class="row text-start">
                    <div class="col-6 mb-2">
                        <small class="text-muted">Status:</small><br>
                        <strong>${formatPaymentStatus(checkout.payment_status)}</strong>
                    </div>
                    <div class="col-6 mb-2">
                        <small class="text-muted">Valor:</small><br>
                        <strong>${formatAmount(checkout.amount_total, checkout.currency)}</strong>
                    </div>
                    ${checkout.customer_email ? `
                        <div class="col-12 mb-2">
                            <small class="text-muted">Email:</small><br>
                            <strong>${checkout.customer_email}</strong>
                        </div>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
    
    infoContainer.innerHTML = infoHTML;
}

/**
 * Formata status de pagamento
 */
function formatPaymentStatus(status) {
    const statusMap = {
        'paid': 'Pago',
        'unpaid': 'Não Pago',
        'no_payment_required': 'Sem Pagamento Necessário'
    };
    
    return statusMap[status] || status;
}

/**
 * Formata valor monetário
 */
function formatAmount(amount, currency) {
    const value = amount / 100;
    const currencyCode = currency.toUpperCase();
    
    const currencyMap = {
        'BRL': 'pt-BR',
        'USD': 'en-US',
        'EUR': 'de-DE'
    };
    
    const locale = currencyMap[currencyCode] || 'pt-BR';
    
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currencyCode
    }).format(value);
}

