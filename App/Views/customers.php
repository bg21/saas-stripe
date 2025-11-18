<?php
/**
 * View de Clientes
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-people"></i> Clientes</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCustomerModal">
            <i class="bi bi-plus-circle"></i> Novo Cliente
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Email, nome...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ordenar por</label>
                    <select class="form-select" id="sortFilter">
                        <option value="created_at">Data de Criação</option>
                        <option value="email">Email</option>
                        <option value="name">Nome</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadCustomers()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Clientes -->
    <div class="card">
        <div class="card-body">
            <div id="loadingCustomers" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="customersList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Stripe ID</th>
                                <th>Assinaturas</th>
                                <th>Criado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="customersTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <i class="bi bi-people fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhum cliente encontrado</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Cliente -->
<div class="modal fade" id="createCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createCustomerForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" id="customerEmail" required>
                        <div class="invalid-feedback" id="emailError"></div>
                        <div class="valid-feedback" id="emailSuccess" style="display: none;">
                            Email disponível
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control" name="name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone</label>
                        <input type="text" class="form-control" name="phone">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Cliente</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
let customers = [];

let currentPage = 1;
let pageSize = 20;
let searchTimeout = null;

document.addEventListener('DOMContentLoaded', () => {
    // Carrega dados imediatamente
    loadCustomers();
    
    // Debounce na busca
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            currentPage = 1;
            loadCustomers();
        }, 500));
    }
    
    // Validação assíncrona de email duplicado
    const emailInput = document.getElementById('customerEmail');
    let emailCheckTimeout = null;
    
    if (emailInput) {
        emailInput.addEventListener('blur', async () => {
            const email = emailInput.value.trim();
            if (!email || !emailInput.validity.valid) return;
            
            clearTimeout(emailCheckTimeout);
            emailCheckTimeout = setTimeout(async () => {
                try {
                    const response = await apiRequest('/v1/customers?search=' + encodeURIComponent(email));
                    const existingCustomers = response.data || [];
                    const emailExists = existingCustomers.some(c => c.email.toLowerCase() === email.toLowerCase());
                    
                    if (emailExists) {
                        emailInput.classList.add('is-invalid');
                        emailInput.classList.remove('is-valid');
                        document.getElementById('emailError').textContent = 'Este email já está cadastrado';
                        document.getElementById('emailSuccess').style.display = 'none';
                    } else {
                        emailInput.classList.remove('is-invalid');
                        emailInput.classList.add('is-valid');
                        document.getElementById('emailError').textContent = '';
                        document.getElementById('emailSuccess').style.display = 'block';
                    }
                } catch (error) {
                    // Ignora erros na validação (não bloqueia o formulário)
                    console.error('Erro ao validar email:', error);
                }
            }, 500);
        });
        
        emailInput.addEventListener('input', () => {
            emailInput.classList.remove('is-invalid', 'is-valid');
            document.getElementById('emailError').textContent = '';
            document.getElementById('emailSuccess').style.display = 'none';
        });
    }
    
    // Form criar cliente
    document.getElementById('createCustomerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        try {
            const response = await apiRequest('/v1/customers', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            // Limpa cache após criar cliente
            if (typeof cache !== 'undefined' && cache.clear) {
                cache.clear('/v1/customers');
            }
            
            showAlert('Cliente criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createCustomerModal')).hide();
            e.target.reset();
            emailInput.classList.remove('is-invalid', 'is-valid');
            document.getElementById('emailError').textContent = '';
            document.getElementById('emailSuccess').style.display = 'none';
            loadCustomers();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadCustomers() {
    try {
        document.getElementById('loadingCustomers').style.display = 'block';
        document.getElementById('customersList').style.display = 'none';
        
        // Constrói query string com paginação e filtros
        const params = new URLSearchParams();
        params.append('page', currentPage);
        params.append('limit', pageSize);
        
        const search = document.getElementById('searchInput')?.value.trim();
        if (search) {
            params.append('search', search);
        }
        
        const statusFilter = document.getElementById('statusFilter')?.value;
        if (statusFilter) {
            params.append('status', statusFilter);
        }
        
        const sortFilter = document.getElementById('sortFilter')?.value;
        if (sortFilter) {
            params.append('sort', sortFilter);
        }
        
        const response = await apiRequest('/v1/customers?' + params.toString(), {
            cacheTTL: 10000 // Cache de 10 segundos
        });
        
        customers = response.data || [];
        const total = response.meta?.total || customers.length;
        const totalPages = Math.ceil(total / pageSize);
        
        renderCustomers();
        renderPagination(totalPages);
    } catch (error) {
        showAlert('Erro ao carregar clientes: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingCustomers').style.display = 'none';
        document.getElementById('customersList').style.display = 'block';
    }
}

function renderPagination(totalPages) {
    const container = document.getElementById('paginationContainer');
    if (!container) {
        // Cria container de paginação se não existir
        const tableContainer = document.querySelector('.table-responsive');
        if (tableContainer && totalPages > 1) {
            const paginationDiv = document.createElement('div');
            paginationDiv.id = 'paginationContainer';
            paginationDiv.className = 'mt-3 d-flex justify-content-center';
            tableContainer.parentElement.appendChild(paginationDiv);
        } else {
            return;
        }
    }
    
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<nav><ul class="pagination">';
    
    // Botão anterior
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;">Anterior</a>
    </li>`;
    
    // Páginas
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(1); return false;">1</a></li>`;
        if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
    
    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
        </li>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${totalPages}); return false;">${totalPages}</a></li>`;
    }
    
    // Botão próximo
    html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;">Próximo</a>
    </li>`;
    
    html += '</ul></nav>';
    container.innerHTML = html;
}

function changePage(page) {
    currentPage = page;
    loadCustomers();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function renderCustomers() {
    const tbody = document.getElementById('customersTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (customers.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    tbody.innerHTML = customers.map(customer => `
        <tr>
            <td>${customer.id}</td>
            <td>${customer.name || '-'}</td>
            <td>${customer.email}</td>
            <td><code class="text-muted">${customer.stripe_customer_id || '-'}</code></td>
            <td><span class="badge bg-info">${customer.subscriptions_count || 0}</span></td>
            <td>${formatDate(customer.created_at)}</td>
            <td>
                <a href="/customer-details?id=${customer.id}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-eye"></i> Ver Detalhes
                </a>
            </td>
        </tr>
    `).join('');
}

</script>

