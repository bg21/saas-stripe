<?php
/**
 * View de Gerenciamento de Clientes da Clínica (Donos de Pets)
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-person-heart"></i> Clientes</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createClientModal">
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
                    <input type="text" class="form-control" id="searchInput" placeholder="Nome, email, telefone...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ordenar por</label>
                    <select class="form-select" id="sortFilter">
                        <option value="created_at">Data de Criação</option>
                        <option value="name">Nome</option>
                        <option value="email">Email</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadClients()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Clientes -->
    <div class="card">
        <div class="card-body">
            <div id="loadingClients" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="clientsList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Telefone</th>
                                <th>Pets</th>
                                <th>Criado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="clientsTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <i class="bi bi-person-heart fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhum cliente encontrado</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Cliente -->
<div class="modal fade" id="createClientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createClientForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" class="form-control" name="name" required minlength="2" maxlength="255">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" maxlength="255">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefone *</label>
                            <input type="text" class="form-control" name="phone" required placeholder="(00) 00000-0000">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefone Alternativo</label>
                            <input type="text" class="form-control" name="phone_alt" placeholder="(00) 00000-0000">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Endereço</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Cidade</label>
                            <input type="text" class="form-control" name="city" maxlength="100">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Estado</label>
                            <input type="text" class="form-control" name="state" maxlength="2" placeholder="SP">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">CEP</label>
                            <input type="text" class="form-control" name="postal_code" maxlength="10" placeholder="00000-000">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
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
let clients = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadClients();
    }, 100);
    
    // Form criar cliente
    document.getElementById('createClientForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!e.target.checkValidity()) {
            e.target.classList.add('was-validated');
            return;
        }
        
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // Remove campos vazios
        Object.keys(data).forEach(key => {
            if (data[key] === '') {
                delete data[key];
            }
        });
        
        try {
            const response = await apiRequest('/v1/clients', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            cache.clear('/v1/clients');
            showAlert('Cliente criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createClientModal')).hide();
            e.target.reset();
            e.target.classList.remove('was-validated');
            
            setTimeout(async () => {
                await loadClients(true);
            }, 100);
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadClients(skipCache = false) {
    try {
        document.getElementById('loadingClients').style.display = 'block';
        document.getElementById('clientsList').style.display = 'none';
        
        if (skipCache) {
            cache.clear('/v1/clients');
        }
        
        const response = await apiRequest('/v1/clients', {
            skipCache: skipCache
        });
        
        // A API retorna { data: { clients: [...], pagination: {...} } }
        clients = Array.isArray(response.data?.clients) ? response.data.clients : 
                  Array.isArray(response.data) ? response.data : [];
        
        // Aplicar filtros
        applyFilters();
        
        renderClients();
    } catch (error) {
        console.error('Erro ao carregar clientes:', error);
        showAlert('Erro ao carregar clientes: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingClients').style.display = 'none';
        document.getElementById('clientsList').style.display = 'block';
    }
}

function applyFilters() {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const sortBy = document.getElementById('sortFilter')?.value || 'created_at';
    
    // Filtrar
    let filtered = clients.filter(client => {
        return !search || 
            (client.name?.toLowerCase().includes(search)) ||
            (client.email?.toLowerCase().includes(search)) ||
            (client.phone?.includes(search));
    });
    
    // Ordenar
    filtered.sort((a, b) => {
        if (sortBy === 'name') {
            return (a.name || '').localeCompare(b.name || '');
        } else if (sortBy === 'email') {
            return (a.email || '').localeCompare(b.email || '');
        } else {
            return new Date(b.created_at) - new Date(a.created_at);
        }
    });
    
    clients = filtered;
}

function renderClients() {
    const tbody = document.getElementById('clientsTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (clients.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    tbody.innerHTML = clients.map(client => {
        return `
            <tr>
                <td>${client.id}</td>
                <td>${client.name || '-'}</td>
                <td>${client.email || '-'}</td>
                <td>${client.phone || '-'}</td>
                <td>
                    <a href="/clinic-client-details?id=${client.id}" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-paw"></i> Ver Pets
                    </a>
                </td>
                <td>${formatDate(client.created_at)}</td>
                <td>
                    <div class="btn-group" role="group">
                        <a href="/clinic-client-details?id=${client.id}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> Ver
                        </a>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteClient(${client.id})" title="Excluir cliente">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

async function deleteClient(clientId) {
    const confirmed = await showConfirmModal(
        'Tem certeza que deseja remover este cliente? Esta ação não pode ser desfeita.',
        'Confirmar Exclusão',
        'Remover Cliente'
    );
    if (!confirmed) return;
    
    try {
        await apiRequest(`/v1/clients/${clientId}`, {
            method: 'DELETE'
        });
        
        cache.clear('/v1/clients');
        showAlert('Cliente removido com sucesso!', 'success');
        
        setTimeout(async () => {
            await loadClients(true);
        }, 100);
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

