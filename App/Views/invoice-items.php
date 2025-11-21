<?php
/**
 * View de Itens de Fatura
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-list-ul"></i> Itens de Fatura</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createInvoiceItemModal">
            <i class="bi bi-plus-circle"></i> Novo Item
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Cliente</label>
                    <input type="number" class="form-control" id="customerFilter" placeholder="ID do cliente">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadInvoiceItems()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <div id="loadingItems" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div id="itemsList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Descrição</th>
                                <th>Quantidade</th>
                                <th>Valor</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Item -->
<div class="modal fade" id="createInvoiceItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Item de Fatura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createInvoiceItemForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Cliente ID *</label>
                        <input type="number" class="form-control" name="customer_id" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor (em centavos) *</label>
                        <input type="number" class="form-control" name="amount" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Moeda *</label>
                        <select class="form-select" name="currency" required>
                            <option value="brl">BRL</option>
                            <option value="usd">USD</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadInvoiceItems();
    }, 100);
    
    document.getElementById('createInvoiceItemForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = {
            customer_id: parseInt(formData.get('customer_id')),
            amount: parseInt(formData.get('amount')),
            currency: formData.get('currency'),
            description: formData.get('description')
        };
        
        try {
            await apiRequest('/v1/invoice-items', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            showAlert('Item criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createInvoiceItemModal')).hide();
            e.target.reset();
            loadInvoiceItems();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadInvoiceItems() {
    try {
        document.getElementById('loadingItems').style.display = 'block';
        document.getElementById('itemsList').style.display = 'none';
        
        const response = await apiRequest('/v1/invoice-items');
        const items = response.data || [];
        
        renderItems(items);
    } catch (error) {
        showAlert('Erro ao carregar itens: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingItems').style.display = 'none';
        document.getElementById('itemsList').style.display = 'block';
    }
}

function renderItems(items) {
    const tbody = document.getElementById('itemsTableBody');
    
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhum item encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = items.map(item => `
        <tr>
            <td><code>${item.id}</code></td>
            <td>${item.customer || item.customer_id || '-'}</td>
            <td>${item.description || '-'}</td>
            <td>${item.quantity || 1}</td>
            <td>${formatCurrency(item.amount, item.currency || 'BRL')}</td>
            <td>${formatDate(item.created)}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewItem('${item.id}')">
                    <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('${item.id}')">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function viewItem(id) {
    // ✅ Valida formato de invoice_item_id
    if (typeof validateStripeId === 'function') {
        const idError = validateStripeId(id, 'invoice_item_id', true);
        if (idError) {
            showAlert('ID de item de fatura inválido: ' + idError, 'danger');
            return;
        }
    } else {
        // Fallback: validação básica
        const idPattern = /^ii_[a-zA-Z0-9]+$/;
        if (!idPattern.test(id)) {
            showAlert('Formato de Invoice Item ID inválido. Use: ii_xxxxx', 'danger');
            return;
        }
    }
    
    alert('Detalhes do item: ' + id);
}

async function deleteItem(id) {
    // ✅ Valida formato de invoice_item_id
    if (typeof validateStripeId === 'function') {
        const idError = validateStripeId(id, 'invoice_item_id', true);
        if (idError) {
            showAlert('ID de item de fatura inválido: ' + idError, 'danger');
            return;
        }
    } else {
        // Fallback: validação básica
        const idPattern = /^ii_[a-zA-Z0-9]+$/;
        if (!idPattern.test(id)) {
            showAlert('Formato de Invoice Item ID inválido. Use: ii_xxxxx', 'danger');
            return;
        }
    }
    
    const confirmed = await showConfirmModal(
        'Tem certeza que deseja remover este item?',
        'Confirmar Exclusão',
        'Remover Item'
    );
    if (!confirmed) return;
    
    try {
        await apiRequest(`/v1/invoice-items/${id}`, { method: 'DELETE' });
        showAlert('Item removido com sucesso!', 'success');
        loadInvoiceItems();
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

