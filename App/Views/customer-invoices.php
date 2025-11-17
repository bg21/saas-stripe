<?php
/**
 * View de Faturas do Cliente
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/customers">Clientes</a></li>
            <li class="breadcrumb-item"><a href="/customer-details?id=<?php echo htmlspecialchars($_GET['customer_id'] ?? '', ENT_QUOTES); ?>">Detalhes</a></li>
            <li class="breadcrumb-item active">Faturas</li>
        </ol>
    </nav>

    <h1 class="h3 mb-4"><i class="bi bi-receipt"></i> Faturas do Cliente</h1>

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

    <!-- Lista de Faturas -->
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
                                <th>Número</th>
                                <th>Data</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Período</th>
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
const customerId = new URLSearchParams(window.location.search).get('customer_id');

if (!customerId) {
    window.location.href = '/customers';
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadInvoices();
    }, 100);
});

async function loadInvoices() {
    try {
        document.getElementById('loadingInvoices').style.display = 'block';
        document.getElementById('invoicesList').style.display = 'none';
        
        const response = await apiRequest(`/v1/customers/${customerId}/invoices`);
        const invoices = response.data || [];
        
        renderInvoices(invoices);
    } catch (error) {
        showAlert('Erro ao carregar faturas: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingInvoices').style.display = 'none';
        document.getElementById('invoicesList').style.display = 'block';
    }
}

function renderInvoices(invoices) {
    const tbody = document.getElementById('invoicesTableBody');
    
    if (invoices.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Nenhuma fatura encontrada</td></tr>';
        return;
    }
    
    tbody.innerHTML = invoices.map(inv => {
        const statusBadge = {
            'draft': 'bg-secondary',
            'open': 'bg-warning',
            'paid': 'bg-success',
            'void': 'bg-danger'
        }[inv.status] || 'bg-secondary';
        
        return `
            <tr>
                <td><code>${inv.id}</code></td>
                <td>${formatDate(inv.created)}</td>
                <td>${formatCurrency(inv.amount_due, inv.currency)}</td>
                <td><span class="badge ${statusBadge}">${inv.status}</span></td>
                <td>${inv.period_start && inv.period_end ? 
                    `${formatDate(inv.period_start)} - ${formatDate(inv.period_end)}` : '-'}</td>
                <td>
                    <a href="/invoice-details?id=${inv.id}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i> Ver Detalhes
                    </a>
                </td>
            </tr>
        `;
    }).join('');
}

</script>

