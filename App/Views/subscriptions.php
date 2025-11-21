<?php
/**
 * View de Assinaturas
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-credit-card"></i> Assinaturas</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSubscriptionModal">
            <i class="bi bi-plus-circle"></i> Nova Assinatura
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
                        <option value="active">Ativas</option>
                        <option value="canceled">Canceladas</option>
                        <option value="past_due">Vencidas</option>
                        <option value="trialing">Em Trial</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cliente</label>
                    <input type="text" class="form-control" id="customerFilter" placeholder="ID ou Email">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadSubscriptions()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
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
                    <h5 class="text-warning" id="trialingSubscriptions">-</h5>
                    <p class="text-muted mb-0">Em Trial</p>
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
    </div>

    <!-- Lista de Assinaturas -->
    <div class="card">
        <div class="card-body">
            <div id="loadingSubscriptions" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="subscriptionsList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Status</th>
                                <th>Plano</th>
                                <th>Valor</th>
                                <th>Próximo Pagamento</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="subscriptionsTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <i class="bi bi-credit-card fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhuma assinatura encontrada</p>
                </div>
                <div id="paginationContainer" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Assinatura -->
<div class="modal fade" id="createSubscriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Assinatura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createSubscriptionForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Cliente *</label>
                        <select class="form-select" name="customer_id" id="customerSelect" required>
                            <option value="">Carregando...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Preço (Price ID) *</label>
                        <input type="text" class="form-control" name="price_id" id="priceIdInput" placeholder="price_xxxxx" pattern="^price_[a-zA-Z0-9]+$" required>
                        <div class="invalid-feedback" id="priceIdError"></div>
                        <small class="text-muted">Formato: price_xxxxx</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Período de Trial (dias)</label>
                        <input type="number" class="form-control" name="trial_period_days" min="0" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Assinatura</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
let subscriptions = [];
let customers = [];
let paginationMeta = {};

let currentPage = 1;
let pageSize = 20;

document.addEventListener('DOMContentLoaded', () => {
    // Carrega dados imediatamente e em paralelo
    Promise.all([
        loadSubscriptions(),
        loadCustomers()
    ]);
    
    // ✅ Validação de price_id no frontend usando função reutilizável
    const priceIdInput = document.getElementById('priceIdInput');
    if (priceIdInput) {
        applyStripeIdValidation(priceIdInput, 'price_id', true, 'priceIdError');
    }
    
    // Form criar assinatura
    document.getElementById('createSubscriptionForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // ✅ Valida price_id antes de submeter usando função reutilizável
        const priceIdError = validateStripeId(data.price_id, 'price_id', true);
        if (priceIdError) {
            showAlert(priceIdError, 'danger');
            priceIdInput.classList.add('is-invalid');
            priceIdInput.focus();
            return;
        }
        
        if (data.trial_period_days) {
            data.trial_period_days = parseInt(data.trial_period_days);
        }
        
        try {
            const response = await apiRequest('/v1/subscriptions', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            // Limpa cache após criar assinatura
            if (typeof cache !== 'undefined' && cache.clear) {
                cache.clear('/v1/subscriptions');
            }
            
            showAlert('Assinatura criada com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createSubscriptionModal')).hide();
            e.target.reset();
            loadSubscriptions();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
    
});

async function loadCustomers() {
    const select = document.getElementById('customerSelect');
    
    try {
        const response = await apiRequest('/v1/customers');
        customers = response.data || [];
        
        select.innerHTML = '<option value="">Selecione um cliente</option>' +
            customers.map(c => {
                const name = escapeHtml(c.name || c.email);
                return `<option value="${c.id}">${name} (ID: ${c.id})</option>`;
            }).join('');
    } catch (error) {
        console.error('Erro ao carregar clientes:', error);
        select.innerHTML = `
            <option value="">Erro ao carregar clientes</option>
        `;
        select.classList.add('is-invalid');
        
        // Adiciona botão de tentar novamente
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger mt-2';
        errorDiv.innerHTML = `
            <i class="bi bi-exclamation-triangle"></i> Erro ao carregar clientes: ${escapeHtml(error.message)}
            <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="loadCustomers()">
                <i class="bi bi-arrow-clockwise"></i> Tentar novamente
            </button>
        `;
        select.parentElement.appendChild(errorDiv);
        
        // Remove mensagem de erro após 5 segundos
        setTimeout(() => {
            errorDiv.remove();
            select.classList.remove('is-invalid');
        }, 5000);
    }
}

async function loadSubscriptions() {
    try {
        document.getElementById('loadingSubscriptions').style.display = 'block';
        document.getElementById('subscriptionsList').style.display = 'none';
        
        const params = new URLSearchParams();
        params.append('page', currentPage);
        params.append('limit', pageSize);
        
        const statusFilter = document.getElementById('statusFilter')?.value;
        if (statusFilter) {
            params.append('status', statusFilter);
        }
        
        const customerFilter = document.getElementById('customerFilter')?.value.trim();
        if (customerFilter) {
            params.append('customer', customerFilter);
        }
        
        const response = await apiRequest('/v1/subscriptions?' + params.toString(), {
            cacheTTL: 10000
        });
        
        subscriptions = response.data || [];
        paginationMeta = response.meta || {};
        
        updateStats();
        renderSubscriptions();
        renderPagination();
    } catch (error) {
        showAlert('Erro ao carregar assinaturas: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingSubscriptions').style.display = 'none';
        document.getElementById('subscriptionsList').style.display = 'block';
    }
}

function updateStats() {
    // Usa estatísticas do meta quando disponível (precisas)
    // Fallback para contagem da página atual se meta não tiver estatísticas
    const total = paginationMeta.total || subscriptions.length;
    
    // Se meta tiver estatísticas por status, usa elas (mais preciso)
    if (paginationMeta.stats) {
        document.getElementById('totalSubscriptions').textContent = paginationMeta.stats.total || total;
        document.getElementById('activeSubscriptions').textContent = paginationMeta.stats.active || 0;
        document.getElementById('trialingSubscriptions').textContent = paginationMeta.stats.trialing || 0;
        document.getElementById('canceledSubscriptions').textContent = paginationMeta.stats.canceled || 0;
    } else {
        // Fallback: conta apenas da página atual (aproximado)
        const active = subscriptions.filter(s => s.status === 'active').length;
        const trialing = subscriptions.filter(s => s.status === 'trialing').length;
        const canceled = subscriptions.filter(s => s.status === 'canceled').length;
        
        document.getElementById('totalSubscriptions').textContent = total;
        document.getElementById('activeSubscriptions').textContent = active;
        document.getElementById('trialingSubscriptions').textContent = trialing;
        document.getElementById('canceledSubscriptions').textContent = canceled;
    }
}

function renderSubscriptions() {
    const tbody = document.getElementById('subscriptionsTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (subscriptions.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    tbody.innerHTML = subscriptions.map(sub => {
        const customer = customers.find(c => c.id === sub.customer_id);
        const statusBadge = {
            'active': 'bg-success',
            'canceled': 'bg-danger',
            'past_due': 'bg-warning',
            'trialing': 'bg-info',
            'incomplete': 'bg-secondary'
        }[sub.status] || 'bg-secondary';
        
        // Sanitiza dados para prevenir XSS
        const customerName = customer ? escapeHtml(customer.name || customer.email) : `ID: ${sub.customer_id}`;
        const status = escapeHtml(sub.status);
        const priceId = escapeHtml(sub.price_id || '-');
        
        return `
            <tr>
                <td>${sub.id}</td>
                <td>${customerName}</td>
                <td><span class="badge ${statusBadge}">${status}</span></td>
                <td><code class="text-muted">${priceId}</code></td>
                <td>${sub.amount ? formatCurrency(sub.amount, sub.currency || 'BRL') : '-'}</td>
                <td>${sub.current_period_end ? formatDate(sub.current_period_end) : '-'}</td>
                <td>
                    <a href="/subscription-details?id=${sub.id}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i> Ver Detalhes
                    </a>
                </td>
            </tr>
        `;
    }).join('');
}

function renderPagination() {
    const container = document.getElementById('paginationContainer');
    if (!paginationMeta.total_pages || paginationMeta.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    const pages = [];
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(paginationMeta.total_pages, startPage + maxVisible - 1);
    
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    // Botão Anterior
    pages.push(`
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;">Anterior</a>
        </li>
    `);
    
    // Primeira página
    if (startPage > 1) {
        pages.push(`
            <li class="page-item">
                <a class="page-link" href="#" onclick="changePage(1); return false;">1</a>
            </li>
        `);
        if (startPage > 2) {
            pages.push(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
        }
    }
    
    // Páginas visíveis
    for (let i = startPage; i <= endPage; i++) {
        pages.push(`
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
            </li>
        `);
    }
    
    // Última página
    if (endPage < paginationMeta.total_pages) {
        if (endPage < paginationMeta.total_pages - 1) {
            pages.push(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
        }
        pages.push(`
            <li class="page-item">
                <a class="page-link" href="#" onclick="changePage(${paginationMeta.total_pages}); return false;">${paginationMeta.total_pages}</a>
            </li>
        `);
    }
    
    // Botão Próximo
    pages.push(`
        <li class="page-item ${currentPage === paginationMeta.total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;">Próximo</a>
        </li>
    `);
    
    container.innerHTML = `
        <nav>
            <ul class="pagination justify-content-center mb-0">
                ${pages.join('')}
            </ul>
        </nav>
        <div class="text-center text-muted mt-2">
            Mostrando ${((currentPage - 1) * pageSize) + 1} - ${Math.min(currentPage * pageSize, paginationMeta.total || 0)} de ${paginationMeta.total || 0} assinaturas
        </div>
    `;
}

function changePage(page) {
    if (page < 1 || page > (paginationMeta.total_pages || 1)) return;
    currentPage = page;
    loadSubscriptions();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

</script>

