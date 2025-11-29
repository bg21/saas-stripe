<?php
/**
 * View de Gerenciamento de Tipos de Exames
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bx bx-test-tube"></i> Tipos de Exames</h1>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createExamTypeModal">
            <i class="bx bx-plus-circle"></i> Novo Tipo de Exame
        </button>
        <?php endif; ?>
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
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Categoria</label>
                    <select class="form-select" id="categoryFilter">
                        <option value="">Todas</option>
                        <option value="blood">Sangue</option>
                        <option value="urine">Urina</option>
                        <option value="imaging">Imagem</option>
                        <option value="other">Outro</option>
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

    <!-- Lista de Tipos de Exames -->
    <div class="card">
        <div class="card-body">
            <div id="loadingExamTypes" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="examTypesList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Categoria</th>
                                <th>Descrição</th>
                                <th>Status</th>
                                <th>Criado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="examTypesTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <i class="bx bx-test-tube fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhum tipo de exame encontrado</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Tipo de Exame -->
<?php if (($user['role'] ?? '') === 'admin'): ?>
<div class="modal fade" id="createExamTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Tipo de Exame</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createExamTypeForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" class="form-control" name="name" required minlength="2" maxlength="255" placeholder="Ex: Exame de Sangue Completo">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoria *</label>
                        <select class="form-select" name="category" required>
                            <option value="">Selecione...</option>
                            <option value="blood">Sangue</option>
                            <option value="urine">Urina</option>
                            <option value="imaging">Imagem (Raio-X, Ultrassom, etc.)</option>
                            <option value="other">Outro</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Descrição do tipo de exame..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" required>
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Tipo de Exame</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Tipo de Exame -->
<div class="modal fade" id="editExamTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Tipo de Exame</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editExamTypeForm">
                <input type="hidden" name="id" id="editExamTypeId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" class="form-control" name="name" id="editExamTypeName" required minlength="2" maxlength="255">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoria *</label>
                        <select class="form-select" name="category" id="editExamTypeCategory" required>
                            <option value="">Selecione...</option>
                            <option value="blood">Sangue</option>
                            <option value="urine">Urina</option>
                            <option value="imaging">Imagem (Raio-X, Ultrassom, etc.)</option>
                            <option value="other">Outro</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" id="editExamTypeDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" id="editExamTypeStatus" required>
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
let examTypes = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadExamTypes();
    }, 100);
    
    // Event listeners para filtros
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            const filtered = applyFilters([...examTypes]);
            renderExamTypes(filtered);
        }, 300));
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', () => {
            const filtered = applyFilters([...examTypes]);
            renderExamTypes(filtered);
        });
    }
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', () => {
            const filtered = applyFilters([...examTypes]);
            renderExamTypes(filtered);
        });
    }
    
    <?php if (($user['role'] ?? '') === 'admin'): ?>
    // Form criar tipo de exame
    document.getElementById('createExamTypeForm').addEventListener('submit', async (e) => {
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
            const response = await apiRequest('/v1/exam-types', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            cache.clear('/v1/exam-types');
            showAlert('Tipo de exame criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createExamTypeModal')).hide();
            e.target.reset();
            e.target.classList.remove('was-validated');
            
            setTimeout(async () => {
                await loadExamTypes(true);
            }, 100);
        } catch (error) {
            if (error.message && !error.message.includes('404') && !error.message.includes('não encontrado')) {
                showAlert('Erro ao criar tipo de exame: ' + error.message, 'danger');
            } else {
                showAlert('Funcionalidade de tipos de exames em desenvolvimento. A API será implementada em breve.', 'info');
            }
        }
    });
    
    // Form editar tipo de exame
    document.getElementById('editExamTypeForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!e.target.checkValidity()) {
            e.target.classList.add('was-validated');
            return;
        }
        
        const formData = new FormData(e.target);
        const examTypeId = formData.get('id');
        const data = Object.fromEntries(formData);
        delete data.id; // Remove o ID dos dados
        
        // Remove campos vazios
        Object.keys(data).forEach(key => {
            if (data[key] === '') {
                delete data[key];
            }
        });
        
        try {
            const response = await apiRequest(`/v1/exam-types/${examTypeId}`, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
            
            cache.clear('/v1/exam-types');
            showAlert('Tipo de exame atualizado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('editExamTypeModal')).hide();
            e.target.reset();
            e.target.classList.remove('was-validated');
            
            setTimeout(async () => {
                await loadExamTypes(true);
            }, 100);
        } catch (error) {
            if (error.message && !error.message.includes('404') && !error.message.includes('não encontrado')) {
                showAlert('Erro ao atualizar tipo de exame: ' + error.message, 'danger');
            } else {
                showAlert('Funcionalidade de tipos de exames em desenvolvimento. A API será implementada em breve.', 'info');
            }
        }
    });
    <?php endif; ?>
});

async function loadExamTypes(skipCache = false) {
    try {
        document.getElementById('loadingExamTypes').style.display = 'block';
        document.getElementById('examTypesList').style.display = 'none';
        
        // ✅ Por enquanto, mostra lista vazia até a API ser implementada
        examTypes = [];
        
        // Tenta carregar da API se existir
        try {
            if (skipCache) {
                cache.clear('/v1/exam-types');
            }
            
            const queryParams = new URLSearchParams();
            const search = document.getElementById('searchInput')?.value;
            const status = document.getElementById('statusFilter')?.value;
            const category = document.getElementById('categoryFilter')?.value;
            
            if (search) queryParams.append('search', search);
            if (status) queryParams.append('status', status);
            if (category) queryParams.append('category', category);
            
            const url = '/v1/exam-types' + (queryParams.toString() ? '?' + queryParams.toString() : '');
            const response = await apiRequest(url, {
                skipCache: skipCache
            });
            
            examTypes = Array.isArray(response.data?.exam_types) ? response.data.exam_types : 
                       Array.isArray(response.data) ? response.data : [];
        } catch (apiError) {
            // Se a API não existir ainda, não mostra erro, apenas lista vazia
            if (apiError.message && !apiError.message.includes('404') && !apiError.message.includes('não encontrado')) {
                console.warn('API de tipos de exames ainda não implementada:', apiError.message);
            }
            examTypes = [];
        }
        
        applyFiltersAndRender();
    } catch (error) {
        console.error('Erro ao carregar tipos de exames:', error);
        showAlert('Erro ao carregar tipos de exames: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingExamTypes').style.display = 'none';
        document.getElementById('examTypesList').style.display = 'block';
    }
}

function applyFilters(examTypesList = examTypes) {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    const categoryFilter = document.getElementById('categoryFilter')?.value || '';
    
    return examTypesList.filter(examType => {
        const matchSearch = !search || 
            (examType.name?.toLowerCase().includes(search)) ||
            (examType.description?.toLowerCase().includes(search));
        
        const matchStatus = !statusFilter || examType.status === statusFilter;
        const matchCategory = !categoryFilter || examType.category === categoryFilter;
        
        return matchSearch && matchStatus && matchCategory;
    });
}

function applyFiltersAndRender() {
    const filtered = applyFilters([...examTypes]);
    renderExamTypes(filtered);
}

function renderExamTypes(examTypesList) {
    const tbody = document.getElementById('examTypesTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (examTypesList.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    const categoryLabels = {
        'blood': 'Sangue',
        'urine': 'Urina',
        'imaging': 'Imagem',
        'other': 'Outro'
    };
    
    tbody.innerHTML = examTypesList.map(examType => {
        const statusBadge = examType.status === 'active' ? 'bg-success' : 'bg-secondary';
        const statusText = examType.status === 'active' ? 'Ativo' : 'Inativo';
        const categoryLabel = categoryLabels[examType.category] || examType.category || '-';
        
        return `
            <tr>
                <td>${examType.id}</td>
                <td><strong>${escapeHtml(examType.name || '-')}</strong></td>
                <td><span class="badge bg-info">${escapeHtml(categoryLabel)}</span></td>
                <td>${escapeHtml(examType.description || '-')}</td>
                <td><span class="badge ${statusBadge}">${statusText}</span></td>
                <td>${formatDate(examType.created_at)}</td>
                <td>
                    <div class="btn-group" role="group">
                        ${USER && USER.role === 'admin' ? `
                        <button class="btn btn-sm btn-outline-primary" onclick="editExamType(${examType.id})" title="Editar">
                            <i class="bx bx-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteExamType(${examType.id})" title="Excluir">
                            <i class="bx bx-trash"></i>
                        </button>
                        ` : '<span class="text-muted">-</span>'}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

<?php if (($user['role'] ?? '') === 'admin'): ?>
async function editExamType(examTypeId) {
    try {
        const response = await apiRequest(`/v1/exam-types/${examTypeId}`);
        const examType = response.data;
        
        document.getElementById('editExamTypeId').value = examType.id;
        document.getElementById('editExamTypeName').value = examType.name || '';
        document.getElementById('editExamTypeCategory').value = examType.category || '';
        document.getElementById('editExamTypeDescription').value = examType.description || '';
        document.getElementById('editExamTypeStatus').value = examType.status || 'active';
        
        const modal = new bootstrap.Modal(document.getElementById('editExamTypeModal'));
        modal.show();
    } catch (error) {
        if (error.message && !error.message.includes('404') && !error.message.includes('não encontrado')) {
            showAlert('Erro ao carregar tipo de exame: ' + error.message, 'danger');
        } else {
            showAlert('Funcionalidade de tipos de exames em desenvolvimento. A API será implementada em breve.', 'info');
        }
    }
}

async function deleteExamType(examTypeId) {
    const examType = examTypes.find(et => et.id === examTypeId);
    if (!examType) {
        showAlert('Tipo de exame não encontrado', 'danger');
        return;
    }
    
    const confirmed = await showConfirmModal(
        `Tem certeza que deseja remover o tipo de exame "${examType.name}"?\n\nEsta ação não pode ser desfeita.`,
        'Confirmar Exclusão',
        'Remover Tipo de Exame'
    );
    
    if (!confirmed) return;
    
    try {
        await apiRequest(`/v1/exam-types/${examTypeId}`, {
            method: 'DELETE'
        });
        
        cache.clear('/v1/exam-types');
        showAlert('Tipo de exame removido com sucesso!', 'success');
        
        setTimeout(async () => {
            await loadExamTypes(true);
        }, 100);
    } catch (error) {
        if (error.message && !error.message.includes('404') && !error.message.includes('não encontrado')) {
            showAlert('Erro ao remover tipo de exame: ' + error.message, 'danger');
        } else {
            showAlert('Funcionalidade de tipos de exames em desenvolvimento. A API será implementada em breve.', 'info');
        }
    }
}
<?php endif; ?>

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script>

