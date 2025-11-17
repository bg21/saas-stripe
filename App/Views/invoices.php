<?php
/**
 * View de Faturas (Geral)
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-receipt"></i> Faturas</h1>
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
                        <option value="draft">Rascunho</option>
                        <option value="open">Aberta</option>
                        <option value="paid">Paga</option>
                        <option value="void">Anulada</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadInvoices()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <div id="loadingInvoices" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div id="invoicesList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="invoicesTableBody">
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
        loadInvoices();
    }, 100);
});

async function loadInvoices() {
    try {
        document.getElementById('loadingInvoices').style.display = 'block';
        document.getElementById('invoicesList').style.display = 'none';
        
        // Nota: Esta view precisa de um endpoint para listar todas as faturas
        // Por enquanto, mostra mensagem
        showAlert('Funcionalidade em desenvolvimento. Use a lista de faturas por cliente.', 'info');
        
        document.getElementById('loadingInvoices').style.display = 'none';
        document.getElementById('invoicesList').style.display = 'block';
        document.getElementById('invoicesTableBody').innerHTML = 
            '<tr><td colspan="6" class="text-center text-muted">Use a lista de faturas por cliente</td></tr>';
    } catch (error) {
        showAlert('Erro: ' + error.message, 'danger');
    }
}
</script>

