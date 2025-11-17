<?php
/**
 * View de Cobranças (Charges)
 */
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-4"><i class="bi bi-credit-card-2-front"></i> Cobranças</h1>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="succeeded">Sucesso</option>
                        <option value="pending">Pendente</option>
                        <option value="failed">Falhou</option>
                        <option value="refunded">Reembolsado</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadCharges()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <div id="loadingCharges" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div id="chargesList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Descrição</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="chargesTableBody">
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
        loadCharges();
    }, 100);
});

async function loadCharges() {
    try {
        document.getElementById('loadingCharges').style.display = 'block';
        document.getElementById('chargesList').style.display = 'none';
        
        const response = await apiRequest('/v1/charges');
        const charges = response.data || [];
        
        renderCharges(charges);
    } catch (error) {
        showAlert('Erro ao carregar cobranças: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingCharges').style.display = 'none';
        document.getElementById('chargesList').style.display = 'block';
    }
}

function renderCharges(charges) {
    const tbody = document.getElementById('chargesTableBody');
    
    if (charges.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhuma cobrança encontrada</td></tr>';
        return;
    }
    
    tbody.innerHTML = charges.map(charge => {
        const statusBadge = {
            'succeeded': 'bg-success',
            'pending': 'bg-warning',
            'failed': 'bg-danger',
            'refunded': 'bg-info'
        }[charge.status] || 'bg-secondary';
        
        return `
            <tr>
                <td><code>${charge.id}</code></td>
                <td>${charge.customer || '-'}</td>
                <td>${formatCurrency(charge.amount, charge.currency || 'BRL')}</td>
                <td><span class="badge ${statusBadge}">${charge.status || '-'}</span></td>
                <td>${charge.description || '-'}</td>
                <td>${formatDate(charge.created)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewCharge('${charge.id}')">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function viewCharge(id) {
    alert('Detalhes da cobrança: ' + id);
}
</script>

