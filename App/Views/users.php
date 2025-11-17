<?php
/**
 * View de Gerenciamento de Usuários (Admin)
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-person-gear"></i> Usuários</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="bi bi-plus-circle"></i> Novo Usuário
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- Lista de Usuários -->
    <div class="card">
        <div class="card-body">
            <div id="loadingUsers" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="usersList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Criado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <i class="bi bi-person-gear fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhum usuário encontrado</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Usuário -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createUserForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha *</label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select class="form-select" name="role" required>
                            <option value="editor">Editor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Usuário</button>
                </div>
            </form>
        </div>
    </div>
</div>



<script>
let users = [];

document.addEventListener('DOMContentLoaded', () => {
    // Carrega dados após um pequeno delay para não bloquear a renderização
    setTimeout(() => {
        loadUsers();
    }, 100);
    
    // Form criar usuário
    document.getElementById('createUserForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        try {
            const response = await apiRequest('/v1/users', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            showAlert('Usuário criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createUserModal')).hide();
            e.target.reset();
            loadUsers();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
    
});

async function loadUsers() {
    try {
        document.getElementById('loadingUsers').style.display = 'block';
        document.getElementById('usersList').style.display = 'none';
        
        const response = await apiRequest('/v1/users');
        users = response.data || [];
        
        renderUsers();
    } catch (error) {
        showAlert('Erro ao carregar usuários: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingUsers').style.display = 'none';
        document.getElementById('usersList').style.display = 'block';
    }
}

function renderUsers() {
    const tbody = document.getElementById('usersTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (users.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    tbody.innerHTML = users.map(user => {
        const roleBadge = user.role === 'admin' ? 'bg-danger' : 'bg-primary';
        const statusBadge = (user.status || 'active') === 'active' ? 'bg-success' : 'bg-secondary';
        
        return `
            <tr>
                <td>${user.id}</td>
                <td>${user.name || '-'}</td>
                <td>${user.email}</td>
                <td><span class="badge ${roleBadge}">${user.role}</span></td>
                <td><span class="badge ${statusBadge}">${user.status || 'active'}</span></td>
                <td>${formatDate(user.created_at)}</td>
                <td>
                    <a href="/user-details?id=${user.id}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i> Ver Detalhes
                    </a>
                </td>
            </tr>
        `;
    }).join('');
}


async function deleteUser(userId) {
    if (!confirm('Tem certeza que deseja remover este usuário?')) {
        return;
    }
    
    try {
        await apiRequest(`/v1/users/${userId}`, {
            method: 'DELETE'
        });
        
        showAlert('Usuário removido com sucesso!', 'success');
        loadUsers();
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

