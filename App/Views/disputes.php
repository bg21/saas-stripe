<?php
/**
 * View de Disputas
 */
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-4"><i class="bi bi-exclamation-triangle"></i> Disputas e Chargebacks</h1>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="warning_needs_response">Precisa Resposta</option>
                        <option value="warning_under_review">Em Revisão</option>
                        <option value="warning_closed">Fechada</option>
                        <option value="needs_response">Aguardando Resposta</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadDisputes()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <div id="loadingDisputes" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div id="disputesList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Charge</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Razão</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="disputesTableBody">
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
        loadDisputes();
    }, 100);
});

async function loadDisputes() {
    try {
        document.getElementById('loadingDisputes').style.display = 'block';
        document.getElementById('disputesList').style.display = 'none';
        
        const response = await apiRequest('/v1/disputes');
        const disputes = response.data || [];
        
        renderDisputes(disputes);
    } catch (error) {
        showAlert('Erro ao carregar disputas: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingDisputes').style.display = 'none';
        document.getElementById('disputesList').style.display = 'block';
    }
}

function renderDisputes(disputes) {
    const tbody = document.getElementById('disputesTableBody');
    
    if (disputes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhuma disputa encontrada</td></tr>';
        return;
    }
    
    tbody.innerHTML = disputes.map(dispute => {
        const statusBadge = {
            'warning_needs_response': 'bg-danger',
            'needs_response': 'bg-danger',
            'warning_under_review': 'bg-warning',
            'under_review': 'bg-warning',
            'warning_closed': 'bg-secondary',
            'won': 'bg-success',
            'lost': 'bg-danger'
        }[dispute.status] || 'bg-secondary';
        
        return `
            <tr>
                <td><code>${dispute.id}</code></td>
                <td><code>${dispute.charge || '-'}</code></td>
                <td>${formatCurrency(dispute.amount, dispute.currency || 'BRL')}</td>
                <td><span class="badge ${statusBadge}">${dispute.status || '-'}</span></td>
                <td>${dispute.reason || '-'}</td>
                <td>${formatDate(dispute.created)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewDispute('${dispute.id}')">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function viewDispute(id) {
    alert('Detalhes da disputa: ' + id);
}
</script>

