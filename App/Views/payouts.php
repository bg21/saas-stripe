<?php
/**
 * View de Saques (Payouts)
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-bank"></i> Saques</h1>
    </div>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="paid">Pago</option>
                        <option value="pending">Pendente</option>
                        <option value="in_transit">Em Trânsito</option>
                        <option value="canceled">Cancelado</option>
                        <option value="failed">Falhou</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadPayouts()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <div id="loadingPayouts" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div id="payoutsList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Método</th>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="payoutsTableBody">
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
        loadPayouts();
    }, 100);
});

async function loadPayouts() {
    try {
        document.getElementById('loadingPayouts').style.display = 'block';
        document.getElementById('payoutsList').style.display = 'none';
        
        const response = await apiRequest('/v1/payouts');
        const payouts = response.data || [];
        
        renderPayouts(payouts);
    } catch (error) {
        showAlert('Erro ao carregar saques: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingPayouts').style.display = 'none';
        document.getElementById('payoutsList').style.display = 'block';
    }
}

function renderPayouts(payouts) {
    const tbody = document.getElementById('payoutsTableBody');
    
    if (payouts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhum saque encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = payouts.map(payout => {
        const statusBadge = {
            'paid': 'bg-success',
            'pending': 'bg-warning',
            'in_transit': 'bg-info',
            'canceled': 'bg-secondary',
            'failed': 'bg-danger'
        }[payout.status] || 'bg-secondary';
        
        return `
            <tr>
                <td><code>${payout.id}</code></td>
                <td>${formatCurrency(payout.amount, payout.currency || 'BRL')}</td>
                <td><span class="badge ${statusBadge}">${payout.status || '-'}</span></td>
                <td>${payout.method || '-'}</td>
                <td>${formatDate(payout.created)}</td>
                <td>${payout.description || '-'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewPayout('${payout.id}')">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function viewPayout(id) {
    alert('Detalhes do saque: ' + id);
}
</script>

