<?php
/**
 * View de Gerenciamento de Permissões (Admin)
 */
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-4"><i class="bi bi-shield-check"></i> Permissões</h1>

    <div id="alertContainer"></div>

    <div class="row">
        <!-- Lista de Permissões Disponíveis -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Permissões Disponíveis</h5>
                </div>
                <div class="card-body">
                    <div id="loadingPermissions" class="text-center py-5">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                    <ul class="list-group" id="permissionsList" style="display: none;">
                    </ul>
                </div>
            </div>
        </div>

        <!-- Permissões por Usuário -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Permissões por Usuário</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Selecione um usuário</label>
                        <select class="form-select" id="userSelect" onchange="loadUserPermissions()">
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                    <div id="userPermissionsContainer">
                        <p class="text-muted text-center">Selecione um usuário para ver suas permissões</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Adicionar Permissão -->
<div class="modal fade" id="addPermissionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar Permissão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addPermissionForm">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="addPermissionUserId">
                    <div class="mb-3">
                        <label class="form-label">Permissão *</label>
                        <select class="form-select" name="permission" id="addPermissionSelect" required>
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Adicionar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let permissions = [];
let users = [];
let currentUserId = null;

document.addEventListener('DOMContentLoaded', () => {
    // Carrega dados após um pequeno delay para não bloquear a renderização
    setTimeout(() => {
        loadPermissions();
        loadUsers();
    }, 100);
    
    // Form adicionar permissão
    document.getElementById('addPermissionForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const userId = formData.get('user_id');
        const permission = formData.get('permission');
        
        try {
            await apiRequest(`/v1/users/${userId}/permissions`, {
                method: 'POST',
                body: JSON.stringify({ permission })
            });
            
            showAlert('Permissão adicionada com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addPermissionModal')).hide();
            loadUserPermissions();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadPermissions() {
    try {
        const response = await apiRequest('/v1/permissions');
        permissions = response.data || [];
        
        renderPermissions();
    } catch (error) {
        showAlert('Erro ao carregar permissões: ' + error.message, 'danger');
    }
}

function renderPermissions() {
    const list = document.getElementById('permissionsList');
    document.getElementById('loadingPermissions').style.display = 'none';
    list.style.display = 'block';
    
    if (permissions.length === 0) {
        list.innerHTML = '<li class="list-group-item text-muted">Nenhuma permissão disponível</li>';
        return;
    }
    
    list.innerHTML = permissions.map(perm => `
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><code>${perm}</code></span>
            <small class="text-muted">Permissão do sistema</small>
        </li>
    `).join('');
}

async function loadUsers() {
    try {
        const response = await apiRequest('/v1/users');
        users = response.data || [];
        
        const select = document.getElementById('userSelect');
        select.innerHTML = '<option value="">Selecione...</option>' +
            users.map(u => `<option value="${u.id}">${u.name || u.email} (${u.role})</option>`).join('');
    } catch (error) {
        console.error('Erro ao carregar usuários:', error);
    }
}

async function loadUserPermissions() {
    const userId = document.getElementById('userSelect').value;
    currentUserId = userId;
    const container = document.getElementById('userPermissionsContainer');
    
    if (!userId) {
        container.innerHTML = '<p class="text-muted text-center">Selecione um usuário para ver suas permissões</p>';
        return;
    }
    
    try {
        container.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
        
        const response = await apiRequest(`/v1/users/${userId}/permissions`);
        const userPermissions = response.data || [];
        
        container.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Permissões do Usuário</h6>
                <button class="btn btn-sm btn-primary" onclick="openAddPermissionModal(${userId})">
                    <i class="bi bi-plus-circle"></i> Adicionar
                </button>
            </div>
            <div id="userPermissionsList">
                ${userPermissions.length === 0 ? 
                    '<p class="text-muted">Nenhuma permissão atribuída</p>' :
                    userPermissions.map(perm => `
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                            <code>${perm}</code>
                            <button class="btn btn-sm btn-outline-danger" onclick="removePermission(${userId}, '${perm}')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `).join('')
                }
            </div>
        `;
    } catch (error) {
        container.innerHTML = `<div class="alert alert-danger">Erro ao carregar permissões: ${error.message}</div>`;
    }
}

function openAddPermissionModal(userId) {
    const select = document.getElementById('addPermissionSelect');
    select.innerHTML = '<option value="">Selecione...</option>' +
        permissions.map(p => `<option value="${p}">${p}</option>`).join('');
    
    document.getElementById('addPermissionUserId').value = userId;
    new bootstrap.Modal(document.getElementById('addPermissionModal')).show();
}

async function removePermission(userId, permission) {
    if (!confirm(`Tem certeza que deseja remover a permissão "${permission}"?`)) {
        return;
    }
    
    try {
        await apiRequest(`/v1/users/${userId}/permissions/${permission}`, {
            method: 'DELETE'
        });
        
        showAlert('Permissão removida com sucesso!', 'success');
        loadUserPermissions();
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

