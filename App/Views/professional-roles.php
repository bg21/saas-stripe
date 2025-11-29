<?php
/**
 * View de Gerenciamento de Roles de Profissionais
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bx bx-user-check"></i> Roles de Profissionais</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRoleModal">
            <i class="bx bx-plus-circle"></i> Nova Role
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Nome, descrição...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="true">Ativas</option>
                        <option value="false">Inativas</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="applyFiltersAndRender()">
                        <i class="bx bx-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Roles -->
    <div class="card">
        <div class="card-body">
            <div id="loadingRoles" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="rolesList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Descrição</th>
                                <th>Status</th>
                                <th>Ordem</th>
                                <th>Criado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="rolesTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <i class="bx bx-user-check fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhuma role encontrada</p>
                    <p class="text-muted small">Clique em "Nova Role" para criar a primeira role</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Role -->
<div class="modal fade" id="createRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createRoleForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" class="form-control" name="name" required minlength="2" maxlength="100" placeholder="Ex: Veterinário, Atendente, Gerente">
                        <div class="invalid-feedback"></div>
                        <small class="form-text text-muted">Nome da role (ex: Veterinário, Atendente, Gerente, Admin)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Descrição da role e suas responsabilidades"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="is_active" required>
                                <option value="true">Ativa</option>
                                <option value="false">Inativa</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" name="sort_order" value="0" min="0">
                            <small class="form-text text-muted">Menor número aparece primeiro</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let roles = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadRoles();
    }, 100);
    
    // Event listeners para filtros
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            const filtered = applyFilters([...roles]);
            renderRoles(filtered);
        }, 300));
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', () => {
            const filtered = applyFilters([...roles]);
            renderRoles(filtered);
        });
    }
    
    // Form criar role
    document.getElementById('createRoleForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!e.target.checkValidity()) {
            e.target.classList.add('was-validated');
            return;
        }
        
        const formData = new FormData(e.target);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (key === 'is_active') {
                data[key] = value === 'true';
            } else if (key === 'sort_order') {
                data[key] = parseInt(value) || 0;
            } else if (value !== '') {
                data[key] = value;
            }
        }
        
        // Remove campos vazios
        Object.keys(data).forEach(key => {
            if (data[key] === '' || data[key] === null) {
                delete data[key];
            }
        });
        
        try {
            const response = await apiRequest('/v1/professional-roles', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            cache.clear('/v1/professional-roles');
            showAlert('Role criada com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createRoleModal')).hide();
            e.target.reset();
            e.target.classList.remove('was-validated');
            
            setTimeout(async () => {
                await loadRoles(true);
            }, 100);
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadRoles(skipCache = false) {
    try {
        document.getElementById('loadingRoles').style.display = 'block';
        document.getElementById('rolesList').style.display = 'none';
        
        if (skipCache) {
            cache.clear('/v1/professional-roles');
        }
        
        const response = await apiRequest('/v1/professional-roles', {
            skipCache: skipCache
        });
        
        if (response && response.data !== undefined) {
            roles = Array.isArray(response.data) ? response.data : [];
        } else if (Array.isArray(response)) {
            roles = response;
        } else {
            roles = [];
        }
        
        // Remove duplicatas por ID
        const uniqueRoles = roles.filter((role, index, self) => 
            index === self.findIndex(r => r.id === role.id)
        );
        
        if (uniqueRoles.length !== roles.length) {
            console.warn(`⚠️ Duplicatas removidas: ${roles.length} -> ${uniqueRoles.length}`);
            roles = uniqueRoles;
        }
        
        const filteredRoles = applyFilters([...roles]);
        renderRoles(filteredRoles);
    } catch (error) {
        console.error('Erro ao carregar roles:', error);
        showAlert('Erro ao carregar roles: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingRoles').style.display = 'none';
        document.getElementById('rolesList').style.display = 'block';
    }
}

function applyFilters(rolesArray = roles) {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    
    return rolesArray.filter(role => {
        const matchSearch = !search || 
            (role.name?.toLowerCase().includes(search)) ||
            (role.description?.toLowerCase().includes(search));
        
        const matchStatus = !statusFilter || String(role.is_active) === statusFilter;
        
        return matchSearch && matchStatus;
    });
}

function applyFiltersAndRender() {
    const filtered = applyFilters([...roles]);
    renderRoles(filtered);
}

function renderRoles(rolesToRender = roles) {
    const tbody = document.getElementById('rolesTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (rolesToRender.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    tbody.innerHTML = '';
    
    tbody.innerHTML = rolesToRender.map(role => {
        const statusBadge = role.is_active ? 'bg-success' : 'bg-secondary';
        return `
            <tr>
                <td>${role.id}</td>
                <td><strong>${role.name || '-'}</strong></td>
                <td>${role.description || '-'}</td>
                <td><span class="badge ${statusBadge}">${role.is_active ? 'Ativa' : 'Inativa'}</span></td>
                <td>${role.sort_order || 0}</td>
                <td>${formatDate(role.created_at)}</td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-primary" onclick="editRole(${role.id})" title="Editar">
                            <i class="bx bx-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteRole(${role.id})" title="Excluir">
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

async function editRole(roleId) {
    try {
        const response = await apiRequest(`/v1/professional-roles/${roleId}`);
        const role = response.data;
        
        const name = prompt('Nome da role:', role.name);
        if (!name) return;
        
        const description = prompt('Descrição:', role.description || '');
        const isActive = confirm('Role ativa?');
        const sortOrder = prompt('Ordem de exibição:', role.sort_order || 0);
        
        await apiRequest(`/v1/professional-roles/${roleId}`, {
            method: 'PUT',
            body: JSON.stringify({
                name: name,
                description: description || null,
                is_active: isActive,
                sort_order: parseInt(sortOrder) || 0
            })
        });
        
        cache.clear('/v1/professional-roles');
        showAlert('Role atualizada com sucesso!', 'success');
        
        setTimeout(async () => {
            await loadRoles(true);
        }, 100);
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function deleteRole(roleId) {
    const confirmed = await showConfirmModal(
        'Tem certeza que deseja remover esta role? Esta ação não pode ser desfeita. Profissionais vinculados a esta role não serão afetados, mas a role será removida.',
        'Confirmar Exclusão',
        'Remover Role'
    );
    if (!confirmed) return;
    
    try {
        await apiRequest(`/v1/professional-roles/${roleId}`, {
            method: 'DELETE'
        });
        
        cache.clear('/v1/professional-roles');
        showAlert('Role removida com sucesso!', 'success');
        
        setTimeout(async () => {
            await loadRoles(true);
        }, 100);
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

