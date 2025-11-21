<?php
/**
 * View de Detalhes da Transação
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/transactions">Transações</a></li>
            <li class="breadcrumb-item active">Detalhes</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>
    <div id="loadingTransaction" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div id="transactionDetails" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Detalhes da Transação</h5>
            </div>
            <div class="card-body" id="transactionInfo">
            </div>
        </div>
    </div>
</div>

<script>
const transactionId = new URLSearchParams(window.location.search).get('id');

if (!transactionId) {
    window.location.href = '/transactions';
}

// ✅ Valida formato de balance_transaction_id da URL
if (typeof validateStripeId === 'function') {
    const transactionIdError = validateStripeId(transactionId, 'balance_transaction_id', true);
    if (transactionIdError) {
        showAlert('ID de transação inválido na URL: ' + transactionIdError, 'danger');
        window.location.href = '/transactions';
        throw new Error('Invalid balance_transaction_id format');
    }
} else {
    // Fallback: validação básica
    const transactionIdPattern = /^txn_[a-zA-Z0-9]+$/;
    if (!transactionIdPattern.test(transactionId)) {
        showAlert('Formato de Transaction ID inválido na URL. Use: txn_xxxxx', 'danger');
        window.location.href = '/transactions';
        throw new Error('Invalid balance_transaction_id format');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadTransactionDetails();
    }, 100);
});

async function loadTransactionDetails() {
    try {
        const transaction = await apiRequest(`/v1/balance-transactions/${transactionId}`);
        
        renderTransactionInfo(transaction.data);
        
        document.getElementById('loadingTransaction').style.display = 'none';
        document.getElementById('transactionDetails').style.display = 'block';
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
    }
}

function renderTransactionInfo(tx) {
    document.getElementById('transactionInfo').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> <code>${tx.id}</code></p>
                <p><strong>Tipo:</strong> <span class="badge bg-info">${tx.type || '-'}</span></p>
                <p><strong>Status:</strong> <span class="badge bg-${tx.status === 'available' ? 'success' : 'warning'}">${tx.status || '-'}</span></p>
                <p><strong>Descrição:</strong> ${tx.description || '-'}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Valor Bruto:</strong> ${formatCurrency(tx.amount, tx.currency || 'BRL')}</p>
                <p><strong>Valor Líquido:</strong> ${formatCurrency(tx.net || tx.amount, tx.currency || 'BRL')}</p>
                <p><strong>Taxa:</strong> ${formatCurrency((tx.amount - (tx.net || tx.amount)), tx.currency || 'BRL')}</p>
                <p><strong>Data:</strong> ${formatDate(tx.created)}</p>
            </div>
        </div>
        ${tx.source ? `
            <div class="row mt-3">
                <div class="col-12">
                    <strong>Origem:</strong> <code>${tx.source}</code>
                </div>
            </div>
        ` : ''}
    `;
}
</script>

