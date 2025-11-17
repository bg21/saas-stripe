<?php
/**
 * View de Histórico de Assinaturas
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/subscriptions">Assinaturas</a></li>
            <li class="breadcrumb-item active">Histórico</li>
        </ol>
    </nav>

    <h1 class="h3 mb-4"><i class="bi bi-clock-history"></i> Histórico de Assinaturas</h1>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="active">Ativas</option>
                        <option value="canceled">Canceladas</option>
                        <option value="past_due">Vencidas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Período</label>
                    <select class="form-select" id="periodFilter">
                        <option value="all">Todos</option>
                        <option value="month">Este Mês</option>
                        <option value="year">Este Ano</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadHistory()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-primary" id="totalSubscriptions">-</h5>
                    <p class="text-muted mb-0">Total</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-success" id="activeSubscriptions">-</h5>
                    <p class="text-muted mb-0">Ativas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-danger" id="canceledSubscriptions">-</h5>
                    <p class="text-muted mb-0">Canceladas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-warning" id="trialSubscriptions">-</h5>
                    <p class="text-muted mb-0">Em Trial</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <div id="loadingHistory" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div id="historyList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Status</th>
                                <th>Valor</th>
                                <th>Criado em</th>
                                <th>Cancelado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
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
        loadHistory();
    }, 100);
});

async function loadHistory() {
    try {
        document.getElementById('loadingHistory').style.display = 'block';
        document.getElementById('historyList').style.display = 'none';
        
        const response = await apiRequest('/v1/subscriptions');
        const subscriptions = response.data || [];
        
        updateStats(subscriptions);
        renderHistory(subscriptions);
    } catch (error) {
        showAlert('Erro ao carregar histórico: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingHistory').style.display = 'none';
        document.getElementById('historyList').style.display = 'block';
    }
}

function updateStats(subscriptions) {
    document.getElementById('totalSubscriptions').textContent = subscriptions.length;
    document.getElementById('activeSubscriptions').textContent = subscriptions.filter(s => s.status === 'active').length;
    document.getElementById('canceledSubscriptions').textContent = subscriptions.filter(s => s.status === 'canceled').length;
    document.getElementById('trialSubscriptions').textContent = subscriptions.filter(s => s.status === 'trialing').length;
}

function renderHistory(subscriptions) {
    const tbody = document.getElementById('historyTableBody');
    
    if (subscriptions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhuma assinatura encontrada</td></tr>';
        return;
    }
    
    tbody.innerHTML = subscriptions.map(sub => {
        const statusBadge = {
            'active': 'bg-success',
            'canceled': 'bg-danger',
            'past_due': 'bg-warning',
            'trialing': 'bg-info'
        }[sub.status] || 'bg-secondary';
        
        return `
            <tr>
                <td>${sub.id}</td>
                <td>${sub.customer_id || '-'}</td>
                <td><span class="badge ${statusBadge}">${sub.status}</span></td>
                <td>${sub.amount ? formatCurrency(sub.amount, sub.currency || 'BRL') : '-'}</td>
                <td>${formatDate(sub.created_at)}</td>
                <td>${sub.canceled_at ? formatDate(sub.canceled_at) : '-'}</td>
                <td>
                    <a href="/subscription-details?id=${sub.id}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
        `;
    }).join('');
}
</script>

