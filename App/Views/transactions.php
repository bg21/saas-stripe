<?php
/**
 * View de Transações
 */
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-4"><i class="bi bi-arrow-left-right"></i> Transações</h1>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" id="typeFilter">
                        <option value="">Todos</option>
                        <option value="charge">Cobrança</option>
                        <option value="refund">Reembolso</option>
                        <option value="payout">Saque</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="succeeded">Sucesso</option>
                        <option value="pending">Pendente</option>
                        <option value="failed">Falhou</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadTransactions()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <div id="loadingTransactions" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div id="transactionsList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="transactionsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadTransactions();
    }, 100);
});

async function loadTransactions() {
    try {
        document.getElementById('loadingTransactions').style.display = 'block';
        document.getElementById('transactionsList').style.display = 'none';
        
        // Carrega balance transactions
        const balanceResponse = await apiRequest('/v1/balance-transactions');
        const transactions = balanceResponse.data || [];
        
        renderTransactions(transactions);
    } catch (error) {
        showAlert('Erro ao carregar transações: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingTransactions').style.display = 'none';
        document.getElementById('transactionsList').style.display = 'block';
    }
}

function renderTransactions(transactions) {
    const tbody = document.getElementById('transactionsTableBody');
    
    if (transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhuma transação encontrada</td></tr>';
        return;
    }
    
    tbody.innerHTML = transactions.map(tx => {
        const statusBadge = {
            'available': 'bg-success',
            'pending': 'bg-warning',
            'failed': 'bg-danger'
        }[tx.status] || 'bg-secondary';
        
        return `
            <tr>
                <td><code>${tx.id}</code></td>
                <td><span class="badge bg-info">${tx.type || '-'}</span></td>
                <td>${tx.description || '-'}</td>
                <td>${formatCurrency(tx.amount, tx.currency || 'BRL')}</td>
                <td><span class="badge ${statusBadge}">${tx.status || '-'}</span></td>
                <td>${formatDate(tx.created)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewTransaction('${tx.id}')">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function viewTransaction(id) {
    window.location.href = `/transaction-details?id=${id}`;
}
</script>

