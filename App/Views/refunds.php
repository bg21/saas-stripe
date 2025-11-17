<?php
/**
 * View de Reembolsos
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-arrow-counterclockwise"></i> Reembolsos</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRefundModal">
            <i class="bi bi-plus-circle"></i> Novo Reembolso
        </button>
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
                        <option value="pending">Pendente</option>
                        <option value="succeeded">Sucesso</option>
                        <option value="failed">Falhou</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadRefunds()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <div id="loadingRefunds" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div id="refundsList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Charge</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Razão</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="refundsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Reembolso -->
<div class="modal fade" id="createRefundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Reembolso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createRefundForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Charge ID *</label>
                        <input type="text" class="form-control" name="charge_id" placeholder="ch_xxxxx" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor (em centavos, deixe vazio para reembolso total)</label>
                        <input type="number" class="form-control" name="amount" min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Razão</label>
                        <select class="form-select" name="reason">
                            <option value="duplicate">Duplicado</option>
                            <option value="fraudulent">Fraudulento</option>
                            <option value="requested_by_customer" selected>Solicitado pelo cliente</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Reembolso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadRefunds();
    }, 100);
    
    document.getElementById('createRefundForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = {
            charge_id: formData.get('charge_id'),
            reason: formData.get('reason')
        };
        
        if (formData.get('amount')) {
            data.amount = parseInt(formData.get('amount'));
        }
        
        try {
            await apiRequest('/v1/refunds', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            showAlert('Reembolso criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createRefundModal')).hide();
            e.target.reset();
            loadRefunds();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadRefunds() {
    try {
        document.getElementById('loadingRefunds').style.display = 'block';
        document.getElementById('refundsList').style.display = 'none';
        
        // Nota: Precisa de endpoint para listar reembolsos
        showAlert('Funcionalidade em desenvolvimento', 'info');
        
        document.getElementById('loadingRefunds').style.display = 'none';
        document.getElementById('refundsList').style.display = 'block';
        document.getElementById('refundsTableBody').innerHTML = 
            '<tr><td colspan="7" class="text-center text-muted">Nenhum reembolso encontrado</td></tr>';
    } catch (error) {
        showAlert('Erro: ' + error.message, 'danger');
    }
}
</script>

