<?php
/**
 * View de Detalhes do Usuário (Admin)
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/users">Usuários</a></li>
            <li class="breadcrumb-item active">Detalhes</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>
    <div id="loadingUser" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div id="userDetails" style="display: none;">
        <!-- Modo Visualização -->
        <div id="viewMode">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Informações do Usuário</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleEditMode()">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                </div>
                <div class="card-body" id="userInfo">
                </div>
            </div>
        </div>

        <!-- Modo Edição -->
        <div id="editMode" style="display: none;">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editar Usuário</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEditMode()">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
                <div class="card-body">
                    <form id="editUserForm" class="needs-validation" novalidate>
                        <input type="hidden" id="editUserId" name="user_id">
                        
                        <div class="mb-3">
                            <label for="editUserName" class="form-label">
                                Nome <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="editUserName" 
                                name="name"
                                required 
                                minlength="2"
                            >
                            <div class="invalid-feedback">
                                Por favor, insira um nome válido.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editUserEmail" class="form-label">
                                Email <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="email" 
                                class="form-control" 
                                id="editUserEmail" 
                                name="email"
                                required 
                            >
                            <div class="invalid-feedback">
                                Por favor, insira um email válido.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editUserStatus" class="form-label">
                                Status
                            </label>
                            <select 
                                class="form-select" 
                                id="editUserStatus" 
                                name="status"
                            >
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Alterar Role</label>
                            <div class="d-flex gap-2">
                                <select class="form-select" id="editUserRole">
                                    <option value="">Selecione um role</option>
                                    <option value="admin">Administrador</option>
                                    <option value="editor">Editor</option>
                                    <option value="viewer">Visualizador</option>
                                </select>
                                <button type="button" class="btn btn-outline-primary" onclick="updateUserRole()">
                                    Atualizar Role
                                </button>
                            </div>
                            <div class="form-text">
                                Use o botão acima para alterar o role do usuário
                            </div>
                        </div>

                        <div id="editUserError" class="alert alert-danger d-none mb-3" role="alert"></div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                                Salvar Alterações
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="toggleEditMode()">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Permissões do Usuário -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Permissões</h5>
            </div>
            <div class="card-body">
                <div id="permissionsList"></div>
            </div>
        </div>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
const userId = urlParams.get('id');

if (!userId) {
    window.location.href = '/users';
}

let userData = null;

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadUserDetails();
    }, 100);
});

async function loadUserDetails() {
    try {
        const [user, permissions] = await Promise.all([
            apiRequest(`/v1/users/${userId}`),
            apiRequest(`/v1/users/${userId}/permissions`).catch(() => ({ data: [] }))
        ]);

        userData = user.data;
        renderUserInfo(userData);
        renderPermissions(permissions.data || []);

        document.getElementById('loadingUser').style.display = 'none';
        document.getElementById('userDetails').style.display = 'block';
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
    }
}

function renderUserInfo(user) {
    document.getElementById('userInfo').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> ${user.id}</p>
                <p><strong>Nome:</strong> ${user.name}</p>
                <p><strong>Email:</strong> ${user.email}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Role:</strong> <span class="badge bg-${user.role === 'admin' ? 'danger' : user.role === 'editor' ? 'primary' : 'secondary'}">${user.role}</span></p>
                <p><strong>Status:</strong> <span class="badge bg-${user.status === 'active' ? 'success' : 'secondary'}">${user.status === 'active' ? 'Ativo' : 'Inativo'}</span></p>
                <p><strong>Criado em:</strong> ${formatDate(user.created_at)}</p>
            </div>
        </div>
    `;
}

function renderPermissions(permissions) {
    const container = document.getElementById('permissionsList');
    if (permissions.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhuma permissão específica atribuída</p>';
        return;
    }
    container.innerHTML = `
        <div class="d-flex flex-wrap gap-2">
            ${permissions.map(perm => `
                <span class="badge bg-primary">${perm}</span>
            `).join('')}
        </div>
    `;
}

function toggleEditMode() {
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    
    if (viewMode.style.display === 'none') {
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        if (userData) {
            loadUserForEdit();
        }
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}

function loadUserForEdit() {
    if (!userData) return;
    
    document.getElementById('editUserId').value = userData.id;
    document.getElementById('editUserName').value = userData.name || '';
    document.getElementById('editUserEmail').value = userData.email || '';
    document.getElementById('editUserStatus').value = userData.status || 'active';
    document.getElementById('editUserRole').value = userData.role || '';
}

// Submissão do formulário de edição
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const userId = document.getElementById('editUserId').value;
    const formData = {
        name: document.getElementById('editUserName').value.trim(),
        email: document.getElementById('editUserEmail').value.trim(),
        status: document.getElementById('editUserStatus').value
    };

    const submitBtn = this.querySelector('button[type="submit"]');
    const spinner = submitBtn.querySelector('.spinner-border');
    const errorDiv = document.getElementById('editUserError');
    
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    errorDiv.classList.add('d-none');

    apiRequest(`/v1/users/${userId}`, {
        method: 'PUT',
        body: JSON.stringify(formData)
    })
    .then(data => {
        if (data.success) {
            showAlert('Usuário atualizado com sucesso!', 'success');
            userData = data.data;
            renderUserInfo(userData);
            toggleEditMode();
        } else {
            throw new Error(data.error || 'Erro ao atualizar usuário');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        errorDiv.textContent = error.message;
        errorDiv.classList.remove('d-none');
    })
    .finally(() => {
        submitBtn.disabled = false;
        spinner.classList.add('d-none');
    });
});

async function updateUserRole() {
    const userId = document.getElementById('editUserId').value;
    const role = document.getElementById('editUserRole').value;
    
    if (!role) {
        showAlert('Por favor, selecione um role', 'warning');
        return;
    }
    
    if (!confirm(`Tem certeza que deseja alterar o role deste usuário para "${role}"?`)) {
        return;
    }
    
    try {
        const data = await apiRequest(`/v1/users/${userId}/role`, {
            method: 'PUT',
            body: JSON.stringify({ role })
        });
        
        if (data.success) {
            showAlert('Role atualizado com sucesso!', 'success');
            userData.role = role;
            renderUserInfo(userData);
            document.getElementById('editUserRole').value = '';
        }
    } catch (error) {
        showAlert('Erro ao atualizar role: ' + error.message, 'danger');
    }
}
</script>

