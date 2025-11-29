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

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Email, nome...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Role</label>
                    <select class="form-select" id="roleFilter">
                        <option value="">Todos</option>
                        <option value="admin">Admin</option>
                        <option value="editor">Editor</option>
                        <option value="viewer">Viewer</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ordenar por</label>
                    <select class="form-select" id="sortFilter">
                        <option value="created_at">Data de Criação</option>
                        <option value="email">Email</option>
                        <option value="name">Nome</option>
                        <option value="role">Role</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadUsers(true)">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

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
                        <input type="text" class="form-control" name="name" id="createUserName" required minlength="2" maxlength="255">
                        <div class="invalid-feedback"></div>
                        <small class="form-text text-muted">Mínimo 2 caracteres, máximo 255 caracteres</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" id="createUserEmail" required maxlength="255">
                        <div class="invalid-feedback"></div>
                        <small class="form-text text-muted">Máximo 255 caracteres</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha *</label>
                        <input type="password" class="form-control" name="password" id="createUserPassword" required minlength="12" maxlength="128">
                        <div class="invalid-feedback"></div>
                        <small class="form-text text-muted">
                            Mínimo 12 caracteres. Deve conter: maiúscula, minúscula, número e caractere especial
                        </small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select class="form-select" name="role" id="roleSelect" required>
                            <option value="editor">Editor</option>
                            <option value="viewer">Viewer</option>
                            <?php if (($user['role'] ?? '') === 'admin'): ?>
                            <option value="admin">Admin</option>
                            <?php endif; ?>
                        </select>
                        <?php if (($user['role'] ?? '') !== 'admin'): ?>
                        <small class="form-text text-muted">Apenas administradores podem criar outros administradores.</small>
                        <?php endif; ?>
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

let searchTimeout = null;

document.addEventListener('DOMContentLoaded', () => {
    // Carrega dados imediatamente
    loadUsers();
    
    // Debounce na busca
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadUsers(true);
            }, 500);
        });
    }
    
    // Filtros que disparam busca ao mudar
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const sortFilter = document.getElementById('sortFilter');
    
    if (roleFilter) {
        roleFilter.addEventListener('change', () => loadUsers(true));
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', () => loadUsers(true));
    }
    if (sortFilter) {
        sortFilter.addEventListener('change', () => loadUsers(true));
    }
    
    // ✅ CORREÇÃO: validations.js já é carregado em layouts/base.php, não precisa carregar novamente
    // Aguarda o script estar disponível antes de aplicar validações
    function applyValidationsWhenReady() {
        if (typeof validateName === 'function' && typeof validateEmail === 'function' && typeof validatePasswordStrength === 'function') {
            const nameField = document.getElementById('createUserName');
            const emailField = document.getElementById('createUserEmail');
            const passwordField = document.getElementById('createUserPassword');
            
            if (nameField) {
                applyFieldValidation(nameField, (value) => validateName(value, true));
            }
            if (emailField) {
                applyFieldValidation(emailField, validateEmail);
            }
            if (passwordField) {
                applyFieldValidation(passwordField, validatePasswordStrength);
            }
        } else {
            // Tenta novamente após um pequeno delay se as funções ainda não estiverem disponíveis
            setTimeout(applyValidationsWhenReady, 100);
        }
    }
    
    // Aplica validações quando o script estiver pronto
    applyValidationsWhenReady();
    
    // Form criar usuário
    document.getElementById('createUserForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // ✅ MELHORIA: Validação frontend antes de enviar
        const validators = {
            name: (value) => validateName(value, true),
            email: validateEmail,
            password: validatePasswordStrength
        };
        
        const validation = validateForm(e.target, validators);
        if (!validation.valid) {
            // Mostra primeiro erro encontrado
            const firstError = Object.values(validation.errors)[0];
            showAlert(firstError, 'warning');
            return;
        }
        
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        try {
            const response = await apiRequest('/v1/users', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            // ✅ CORREÇÃO: Limpa cache de usuários após criar (antes de recarregar)
            cache.clear('/v1/users');
            
            showAlert('Usuário criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createUserModal')).hide();
            e.target.reset();
            
            // Limpa classes de validação
            e.target.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
                el.classList.remove('is-valid', 'is-invalid');
            });
            
            // ✅ CORREÇÃO: Aguarda um pouco antes de recarregar para garantir que o cache foi limpo
            setTimeout(async () => {
                await loadUsers(true);
            }, 100);
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
    
});

async function loadUsers(skipCache = false) {
    try {
        document.getElementById('loadingUsers').style.display = 'block';
        document.getElementById('usersList').style.display = 'none';
        
        // ✅ CORREÇÃO: Limpa cache antes de fazer a requisição se skipCache for true
        if (skipCache) {
            cache.clear('/v1/users');
        }
        
        // Constrói query string com filtros
        const params = new URLSearchParams();
        
        const search = document.getElementById('searchInput')?.value.trim();
        if (search) {
            params.append('search', search);
        }
        
        const roleFilter = document.getElementById('roleFilter')?.value;
        if (roleFilter) {
            params.append('role', roleFilter);
        }
        
        const statusFilter = document.getElementById('statusFilter')?.value;
        if (statusFilter) {
            params.append('status', statusFilter);
        }
        
        const sortFilter = document.getElementById('sortFilter')?.value;
        if (sortFilter) {
            params.append('sort', sortFilter);
        }
        
        // ✅ CORREÇÃO: Permite pular cache ao recarregar após criar usuário
        const queryString = params.toString();
        const url = queryString ? `/v1/users?${queryString}` : '/v1/users';
        const response = await apiRequest(url, {
            skipCache: skipCache,
            cacheTTL: 10000 // Cache de 10 segundos
        });
        
        // ✅ CORREÇÃO: Debug - verifica estrutura da resposta
        console.log('Resposta da API:', response);
        
        // ✅ CORREÇÃO: A API retorna {users: [...], count: ...}, então precisa acessar response.data.users
        if (response.data && response.data.users && Array.isArray(response.data.users)) {
            users = response.data.users;
        } else if (Array.isArray(response.data)) {
            // Fallback: se response.data for diretamente um array
            users = response.data;
        } else {
            users = [];
        }
        
        console.log('Usuários carregados:', users.length);
        
        renderUsers();
    } catch (error) {
        console.error('Erro ao carregar usuários:', error);
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
                    <div class="btn-group" role="group">
                        <a href="/user-details?id=${user.id}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> Ver Detalhes
                        </a>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${user.id})" title="Excluir usuário">
                            <i class="bi bi-trash"></i> Excluir
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}


async function deleteUser(userId) {
    const confirmed = await showConfirmModal(
        'Tem certeza que deseja remover este usuário? Esta ação não pode ser desfeita.',
        'Confirmar Exclusão',
        'Remover Usuário'
    );
    if (!confirmed) return;
    
    try {
        await apiRequest(`/v1/users/${userId}`, {
            method: 'DELETE'
        });
        
        // ✅ CORREÇÃO: Limpa cache após deletar
        cache.clear('/v1/users');
        
        showAlert('Usuário removido com sucesso!', 'success');
        
        // ✅ CORREÇÃO: Recarrega lista sem cache para mostrar atualização imediata
        setTimeout(async () => {
            await loadUsers(true);
        }, 100);
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

