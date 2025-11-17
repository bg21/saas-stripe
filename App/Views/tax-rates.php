<?php
/**
 * View de Taxas de Imposto
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-percent"></i> Taxas de Imposto</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaxRateModal">
            <i class="bi bi-plus-circle"></i> Nova Taxa
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <div id="loadingTaxRates" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div id="taxRatesList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Porcentagem</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Jurisdição</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="taxRatesTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Taxa -->
<div class="modal fade" id="createTaxRateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Taxa de Imposto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createTaxRateForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome de Exibição *</label>
                        <input type="text" class="form-control" name="display_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Porcentagem *</label>
                        <input type="number" class="form-control" name="percentage" min="0" max="100" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo</label>
                        <select class="form-select" name="inclusive">
                            <option value="0">Exclusiva (adiciona ao preço)</option>
                            <option value="1">Inclusiva (já incluída no preço)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jurisdição</label>
                        <input type="text" class="form-control" name="jurisdiction" placeholder="BR, US, etc.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Taxa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadTaxRates();
    }, 100);
    
    document.getElementById('createTaxRateForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = {
            display_name: formData.get('display_name'),
            percentage: parseFloat(formData.get('percentage')),
            inclusive: formData.get('inclusive') === '1',
            jurisdiction: formData.get('jurisdiction') || null
        };
        
        try {
            await apiRequest('/v1/tax-rates', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            showAlert('Taxa criada com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createTaxRateModal')).hide();
            e.target.reset();
            loadTaxRates();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadTaxRates() {
    try {
        document.getElementById('loadingTaxRates').style.display = 'block';
        document.getElementById('taxRatesList').style.display = 'none';
        
        const response = await apiRequest('/v1/tax-rates');
        const taxRates = response.data || [];
        
        renderTaxRates(taxRates);
    } catch (error) {
        showAlert('Erro ao carregar taxas: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingTaxRates').style.display = 'none';
        document.getElementById('taxRatesList').style.display = 'block';
    }
}

function renderTaxRates(taxRates) {
    const tbody = document.getElementById('taxRatesTableBody');
    
    if (taxRates.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhuma taxa encontrada</td></tr>';
        return;
    }
    
    tbody.innerHTML = taxRates.map(rate => `
        <tr>
            <td><code>${rate.id}</code></td>
            <td>${rate.display_name || '-'}</td>
            <td><strong>${rate.percentage}%</strong></td>
            <td><span class="badge bg-${rate.inclusive ? 'info' : 'secondary'}">${rate.inclusive ? 'Inclusiva' : 'Exclusiva'}</span></td>
            <td><span class="badge bg-${rate.active ? 'success' : 'secondary'}">${rate.active ? 'Ativa' : 'Inativa'}</span></td>
            <td>${rate.jurisdiction || '-'}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewTaxRate('${rate.id}')">
                    <i class="bi bi-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function viewTaxRate(id) {
    alert('Detalhes da taxa: ' + id);
}
</script>

