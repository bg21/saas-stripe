<?php
/**
 * View de Preços
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-tag"></i> Preços</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPriceModal">
            <i class="bi bi-plus-circle"></i> Novo Preço
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
                        <option value="true">Ativos</option>
                        <option value="false">Inativos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" id="typeFilter">
                        <option value="">Todos</option>
                        <option value="one_time">Pagamento Único</option>
                        <option value="recurring">Recorrente</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Moeda</label>
                    <select class="form-select" id="currencyFilter">
                        <option value="">Todas</option>
                        <option value="brl">BRL</option>
                        <option value="usd">USD</option>
                        <option value="eur">EUR</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadPrices()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Preços -->
    <div class="card">
        <div class="card-body">
            <div id="loadingPrices" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="pricesList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Produto</th>
                                <th>Valor</th>
                                <th>Tipo</th>
                                <th>Intervalo</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="pricesTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <i class="bi bi-tag fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhum preço encontrado</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Preço -->
<div class="modal fade" id="createPriceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Preço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createPriceForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Produto *</label>
                        <select class="form-select" name="product" id="productSelect" required>
                            <option value="">Carregando produtos...</option>
                        </select>
                        <div class="invalid-feedback" id="productError"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor (em centavos) *</label>
                        <input type="number" class="form-control" name="unit_amount" id="unitAmountInput" min="1" max="99999999" required>
                        <div class="invalid-feedback" id="unitAmountError"></div>
                        <small class="text-muted">Ex: 2999 = R$ 29,99 (mínimo: 1, máximo: 99.999.999)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Moeda *</label>
                        <select class="form-select" name="currency" required>
                            <option value="brl">BRL (Real)</option>
                            <option value="usd">USD (Dólar)</option>
                            <option value="eur">EUR (Euro)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo *</label>
                        <select class="form-select" name="price_type" id="priceType" required>
                            <option value="one_time">Pagamento Único</option>
                            <option value="recurring">Recorrente</option>
                        </select>
                    </div>
                    <div class="mb-3" id="recurringOptions" style="display: none;">
                        <label class="form-label">Intervalo *</label>
                        <select class="form-select" name="interval" id="intervalSelect" required>
                            <option value="">Selecione um intervalo</option>
                            <option value="day">Diário</option>
                            <option value="week">Semanal</option>
                            <option value="month">Mensal</option>
                            <option value="year">Anual</option>
                        </select>
                        <div class="invalid-feedback">Intervalo é obrigatório para preços recorrentes</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Preço</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let prices = [];
let products = [];

document.addEventListener('DOMContentLoaded', () => {
    // Carrega dados após um pequeno delay para não bloquear a renderização
    setTimeout(() => {
        loadPrices();
        loadProducts();
    }, 100);
    
    // Toggle campos recorrentes
    const priceTypeSelect = document.getElementById('priceType');
    const recurringOptions = document.getElementById('recurringOptions');
    const intervalSelect = document.getElementById('intervalSelect');
    
    priceTypeSelect.addEventListener('change', (e) => {
        const isRecurring = e.target.value === 'recurring';
        recurringOptions.style.display = isRecurring ? 'block' : 'none';
        
        // Torna interval obrigatório quando recurring é selecionado
        if (isRecurring) {
            intervalSelect.setAttribute('required', 'required');
            intervalSelect.setAttribute('aria-required', 'true');
            // Garante que há um valor selecionado (padrão: month)
            if (!intervalSelect.value) {
                intervalSelect.value = 'month';
            }
        } else {
            intervalSelect.removeAttribute('required');
            intervalSelect.removeAttribute('aria-required');
            intervalSelect.value = '';
        }
    });
    
    // Validação de unit_amount
    const unitAmountInput = document.getElementById('unitAmountInput');
    if (unitAmountInput) {
        unitAmountInput.addEventListener('input', () => {
            const value = parseInt(unitAmountInput.value);
            if (value < 1) {
                unitAmountInput.classList.add('is-invalid');
                document.getElementById('unitAmountError').textContent = 'Valor mínimo é 1 centavo';
            } else if (value > 99999999) {
                unitAmountInput.classList.add('is-invalid');
                document.getElementById('unitAmountError').textContent = 'Valor máximo é 99.999.999 centavos';
            } else {
                unitAmountInput.classList.remove('is-invalid');
                document.getElementById('unitAmountError').textContent = '';
            }
        });
    }
    
    // Form criar preço
    document.getElementById('createPriceForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        // Valida unit_amount
        const unitAmount = parseInt(formData.get('unit_amount'));
        if (unitAmount < 1 || unitAmount > 99999999) {
            showAlert('Valor deve estar entre 1 e 99.999.999 centavos', 'danger');
            return;
        }
        
        // Valida interval se recurring
        const priceType = formData.get('price_type');
        if (priceType === 'recurring') {
            const interval = formData.get('interval');
            if (!interval || interval.trim() === '') {
                showAlert('Intervalo é obrigatório para preços recorrentes', 'danger');
                intervalSelect.classList.add('is-invalid');
                intervalSelect.focus();
                return;
            }
            intervalSelect.classList.remove('is-invalid');
        }
        
        const data = {
            product: formData.get('product'),
            unit_amount: unitAmount,
            currency: formData.get('currency')
        };
        
        if (priceType === 'recurring') {
            data.recurring = {
                interval: formData.get('interval')
            };
        }
        
        try {
            const response = await apiRequest('/v1/prices', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            // Limpa cache após criar preço
            if (typeof cache !== 'undefined' && cache.clear) {
                cache.clear('/v1/prices');
            }
            
            showAlert('Preço criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createPriceModal')).hide();
            e.target.reset();
            recurringOptions.style.display = 'none';
            intervalSelect.removeAttribute('required');
            unitAmountInput.classList.remove('is-invalid');
            loadPrices();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadProducts() {
    const select = document.getElementById('productSelect');
    
    try {
        const response = await apiRequest('/v1/products');
        products = response.data || [];
        
        select.innerHTML = '<option value="">Selecione um produto</option>' +
            products.map(p => {
                const name = escapeHtml(p.name || p.id);
                return `<option value="${p.id}">${name} (${p.id})</option>`;
            }).join('');
    } catch (error) {
        console.error('Erro ao carregar produtos:', error);
        select.innerHTML = '<option value="">Erro ao carregar produtos</option>';
        select.classList.add('is-invalid');
        document.getElementById('productError').textContent = 'Erro ao carregar lista de produtos';
    }
}

async function loadPrices() {
    try {
        document.getElementById('loadingPrices').style.display = 'block';
        document.getElementById('pricesList').style.display = 'none';
        
        const params = new URLSearchParams();
        const statusFilter = document.getElementById('statusFilter')?.value;
        const typeFilter = document.getElementById('typeFilter')?.value;
        const currencyFilter = document.getElementById('currencyFilter')?.value;
        
        if (statusFilter) params.append('active', statusFilter);
        if (typeFilter) params.append('type', typeFilter);
        if (currencyFilter) params.append('currency', currencyFilter);
        
        const url = '/v1/prices' + (params.toString() ? '?' + params.toString() : '');
        const response = await apiRequest(url);
        prices = response.data || [];
        
        renderPrices();
    } catch (error) {
        showAlert('Erro ao carregar preços: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingPrices').style.display = 'none';
        document.getElementById('pricesList').style.display = 'block';
    }
}

function renderPrices() {
    const tbody = document.getElementById('pricesTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (prices.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    tbody.innerHTML = prices.map(price => {
        const product = products.find(p => p.id === price.product);
        const type = price.type === 'recurring' ? 'Recorrente' : 'Único';
        const interval = price.recurring?.interval ? 
            (price.recurring.interval === 'month' ? 'Mensal' : 
             price.recurring.interval === 'year' ? 'Anual' :
             price.recurring.interval === 'week' ? 'Semanal' : 'Diário') : '-';
        
        return `
            <tr>
                <td><code class="text-muted">${price.id}</code></td>
                <td>${product ? product.name : price.product}</td>
                <td><strong>${formatCurrency(price.unit_amount, price.currency)}</strong></td>
                <td><span class="badge bg-${price.type === 'recurring' ? 'primary' : 'secondary'}">${type}</span></td>
                <td>${interval}</td>
                <td><span class="badge bg-${price.active ? 'success' : 'secondary'}">${price.active ? 'Ativo' : 'Inativo'}</span></td>
                <td>
                    <a href="/price-details?id=${price.id}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i> Ver Detalhes
                    </a>
                </td>
            </tr>
        `;
    }).join('');
}

</script>

