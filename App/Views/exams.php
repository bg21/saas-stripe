<?php
/**
 * View de Gerenciamento de Exames
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bx bx-test-tube"></i> Exames</h1>
        <?php if (($user['role'] ?? '') === 'admin' || ($user['role'] ?? '') === 'editor'): ?>
        <button class="btn btn-primary" id="createExamBtn" data-bs-toggle="modal" data-bs-target="#createExamModal">
            <i class="bx bx-plus-circle"></i> Novo Exame
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
                    <input type="text" class="form-control" id="searchInput" placeholder="Nome do exame, pet, cliente...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="pending">Pendente</option>
                        <option value="scheduled">Agendado</option>
                        <option value="completed">Concluído</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" id="typeFilter">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadExams()">
                        <i class="bx bx-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Exames -->
    <div class="card">
        <div class="card-body">
            <div id="loadingExams" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="examsList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Pet</th>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th>Profissional</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="examsTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <i class="bx bx-test-tube fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhum exame encontrado</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Exame -->
<?php if (($user['role'] ?? '') === 'admin' || ($user['role'] ?? '') === 'editor'): ?>
<div class="modal fade" id="createExamModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Exame</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createExamForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pet *</label>
                            <select class="form-select" name="pet_id" id="createExamPetId" required>
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Profissional *</label>
                            <select class="form-select" name="professional_id" id="createExamProfessionalId" required>
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Exame *</label>
                            <select class="form-select" name="exam_type_id" id="createExamTypeId" required>
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Data do Exame *</label>
                            <input type="date" class="form-control" name="exam_date" id="createExamDate" required min="<?php echo date('Y-m-d'); ?>">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Hora</label>
                            <input type="time" class="form-control" name="exam_time" id="createExamTime">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" name="notes" id="createExamNotes" rows="3" placeholder="Observações adicionais..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Exame</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const userRole = '<?php echo $user['role'] ?? 'viewer'; ?>';
let exams = [];
let pets = [];
let professionals = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadExamTypesForFilter();
        loadExamTypesForSelect();
        loadExams();
        loadPetsForSelect();
        loadProfessionalsForSelect();
    }, 100);
    
    // Form criar exame
    <?php if (($user['role'] ?? '') === 'admin' || ($user['role'] ?? '') === 'editor'): ?>
    document.getElementById('createExamForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!e.target.checkValidity()) {
            e.target.classList.add('was-validated');
            return;
        }
        
        const formData = new FormData(e.target);
        const data = {};
        for (let [key, value] of formData.entries()) {
            if (value !== '') {
                data[key] = value;
            }
        }
        
        // Converte IDs para inteiros
        if (data.pet_id) data.pet_id = parseInt(data.pet_id);
        if (data.professional_id) data.professional_id = parseInt(data.professional_id);
        if (data.exam_type_id) data.exam_type_id = parseInt(data.exam_type_id);
        
        // Remove campos vazios
        Object.keys(data).forEach(key => {
            if (data[key] === '' || data[key] === null) {
                delete data[key];
            }
        });
        
        try {
            const response = await apiRequest('/v1/exams', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            cache.clear('/v1/exams');
            showAlert('Exame criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createExamModal')).hide();
            e.target.reset();
            e.target.classList.remove('was-validated');
            
            setTimeout(async () => {
                await loadExams(true);
            }, 100);
        } catch (error) {
            showAlert(error.message || 'Erro ao criar exame', 'danger');
        }
    });
    <?php endif; ?>
});

async function loadExams(skipCache = false) {
    try {
        document.getElementById('loadingExams').style.display = 'block';
        document.getElementById('examsList').style.display = 'none';
        
        // Tenta carregar da API
        try {
            if (skipCache) {
                cache.clear('/v1/exams');
            }
            
            const queryParams = new URLSearchParams();
            const search = document.getElementById('searchInput')?.value;
            const status = document.getElementById('statusFilter')?.value;
            const examTypeId = document.getElementById('typeFilter')?.value;
            
            if (search) queryParams.append('search', search);
            if (status) queryParams.append('status', status);
            if (examTypeId) queryParams.append('exam_type_id', examTypeId);
            
            const url = '/v1/exams' + (queryParams.toString() ? '?' + queryParams.toString() : '');
            const response = await apiRequest(url, {
                skipCache: skipCache
            });
            
            console.log('Resposta da API de exames:', response);
            exams = Array.isArray(response.data) ? response.data : [];
            console.log('Exames carregados:', exams.length);
        } catch (apiError) {
            console.error('Erro ao carregar exames da API:', apiError);
            // Se a API não existir ainda, não mostra erro, apenas lista vazia
            if (apiError.message && !apiError.message.includes('404') && !apiError.message.includes('não encontrado')) {
                console.warn('API de exames ainda não implementada:', apiError.message);
            }
            exams = [];
        }
        
        renderExams();
    } catch (error) {
        console.error('Erro ao carregar exames:', error);
        showAlert('Erro ao carregar exames: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingExams').style.display = 'none';
        document.getElementById('examsList').style.display = 'block';
    }
}

async function renderExams() {
    console.log('Renderizando exames, total:', exams.length);
    const tbody = document.getElementById('examsTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (!tbody || !emptyState) {
        console.error('Elementos do DOM não encontrados');
        return;
    }
    
    if (exams.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    // ✅ Busca dados de pets, clientes e profissionais se não estiverem expandidos
    const uniquePetIds = [...new Set(exams.map(exam => exam.pet_id).filter(Boolean))];
    const uniqueClientIds = [...new Set(exams.map(exam => exam.client_id).filter(Boolean))];
    const uniqueProfessionalIds = [...new Set(exams.map(exam => exam.professional_id).filter(Boolean))];
    
    const [petsData, clientsData, professionalsData] = await Promise.all([
        Promise.all(uniquePetIds.map(id => 
            apiRequest(`/v1/pets/${id}`).then(r => ({ id, name: r.data?.name || `Pet #${id}` })).catch(() => ({ id, name: `Pet #${id}` }))
        )),
        Promise.all(uniqueClientIds.map(id => 
            apiRequest(`/v1/clients/${id}`).then(r => ({ id, name: r.data?.name || `Cliente #${id}` })).catch(() => ({ id, name: `Cliente #${id}` }))
        )),
        Promise.all(uniqueProfessionalIds.map(id => 
            apiRequest(`/v1/professionals/${id}`).then(r => ({ 
                id, 
                name: r.data?.name || (r.data?.user && r.data.user.name) || `Profissional #${id}`,
                crmv: r.data?.crmv 
            })).catch(() => ({ id, name: `Profissional #${id}`, crmv: null }))
        ))
    ]);
    
    const petsMap = new Map(petsData.map(p => [p.id, p.name]));
    const clientsMap = new Map(clientsData.map(c => [c.id, c.name]));
    const professionalsMap = new Map(professionalsData.map(p => [p.id, { name: p.name, crmv: p.crmv }]));
    
    const enrichedExams = exams.map(exam => {
        const professional = professionalsMap.get(exam.professional_id);
        
        // Obtém o nome do tipo de exame
        let examTypeText = 'N/A';
        if (exam.exam_type && typeof exam.exam_type === 'object') {
            examTypeText = exam.exam_type.name || 'N/A';
        } else if (exam.exam_type_id) {
            // Se não estiver enriquecido, tenta buscar
            examTypeText = `Tipo #${exam.exam_type_id}`;
        }
        
        return {
            ...exam,
            petName: exam.pet?.name || petsMap.get(exam.pet_id) || `Pet #${exam.pet_id}`,
            clientName: exam.client?.name || clientsMap.get(exam.client_id) || `Cliente #${exam.client_id}`,
            professionalName: professional ? (professional.crmv ? `${professional.name} - CRMV: ${professional.crmv}` : professional.name) : (exam.professional_id ? `Profissional #${exam.professional_id}` : 'Não atribuído'),
            examTypeText: examTypeText
        };
    });
    
    tbody.innerHTML = enrichedExams.map(exam => {
        const statusBadge = exam.status === 'pending' ? 'bg-warning' :
                          exam.status === 'scheduled' ? 'bg-info' :
                          exam.status === 'completed' ? 'bg-success' :
                          exam.status === 'cancelled' ? 'bg-danger' : 'bg-secondary';
        const statusText = exam.status === 'pending' ? 'Pendente' :
                          exam.status === 'scheduled' ? 'Agendado' :
                          exam.status === 'completed' ? 'Concluído' :
                          exam.status === 'cancelled' ? 'Cancelado' : exam.status;
        
        // Formata hora (remove segundos se existir)
        const examTime = exam.exam_time ? exam.exam_time.substring(0, 5) : '-';
        
        return `
            <tr>
                <td>${exam.id}</td>
                <td>${formatDate(exam.exam_date)}</td>
                <td>${examTime}</td>
                <td>${escapeHtml(exam.petName)}</td>
                <td>${escapeHtml(exam.clientName)}</td>
                <td>${escapeHtml(exam.examTypeText)}</td>
                <td>${escapeHtml(exam.professionalName)}</td>
                <td><span class="badge ${statusBadge}">${statusText}</span></td>
                <td>
                    <div class="btn-group" role="group">
                        <a href="/exam-details?id=${exam.id}" class="btn btn-sm btn-outline-primary" title="Ver detalhes">
                            <i class="bx bx-show"></i>
                        </a>
                        ${(userRole === 'admin' || userRole === 'editor') ? `
                            <a href="/exam-details?id=${exam.id}" class="btn btn-sm btn-outline-info" title="Editar exame">
                                <i class="bx bx-edit"></i>
                            </a>
                        ` : ''}
                        ${userRole === 'admin' ? `
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteExam(${exam.id})" title="Excluir exame">
                                <i class="bx bx-trash"></i>
                            </button>
                        ` : ''}
                        ${exam.status === 'pending' || exam.status === 'scheduled' ? `
                            <button class="btn btn-sm btn-outline-warning" onclick="rescheduleExam(${exam.id})" title="Remarcar exame">
                                <i class="bx bx-calendar"></i>
                            </button>
                        ` : ''}
                        ${exam.status !== 'cancelled' && exam.status !== 'completed' ? `
                            <button class="btn btn-sm btn-outline-danger" onclick="cancelExam(${exam.id})" title="Cancelar exame">
                                <i class="bx bx-x-circle"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

async function loadPetsForSelect() {
    try {
        const response = await apiRequest('/v1/pets');
        pets = Array.isArray(response.data?.pets) ? response.data.pets : 
              Array.isArray(response.data) ? response.data : [];
        
        const select = document.getElementById('createExamPetId');
        if (select) {
            pets.forEach(pet => {
                const option = document.createElement('option');
                option.value = pet.id;
                option.textContent = `${pet.name || 'Pet #' + pet.id} - ${pet.client?.name || 'Cliente #' + pet.client_id}`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar pets:', error);
    }
}

async function loadProfessionalsForSelect() {
    try {
        const response = await apiRequest('/v1/professionals?status=active');
        professionals = Array.isArray(response.data) ? response.data : 
                       Array.isArray(response.data?.professionals) ? response.data.professionals : [];
        
        const select = document.getElementById('createExamProfessionalId');
        if (select) {
            professionals.forEach(prof => {
                if (prof.status === 'active') {
                    const option = document.createElement('option');
                    option.value = prof.id;
                    const name = prof.name || (prof.user && prof.user.name) || `Profissional #${prof.id}`;
                    let displayName = name;
                    if (prof.crmv) {
                        displayName += ` - CRMV: ${prof.crmv}`;
                    }
                    option.textContent = displayName;
                    select.appendChild(option);
                }
            });
        }
    } catch (error) {
        console.error('Erro ao carregar profissionais:', error);
    }
}

async function loadExamTypesForSelect() {
    try {
        const response = await apiRequest('/v1/exam-types?status=active');
        const examTypes = Array.isArray(response.data) ? response.data : [];
        
        const select = document.getElementById('createExamTypeId');
        if (select) {
            select.innerHTML = '<option value="">Selecione...</option>';
            examTypes.forEach(examType => {
                if (examType.status === 'active') {
                    const option = document.createElement('option');
                    option.value = examType.id;
                    option.textContent = examType.name;
                    select.appendChild(option);
                }
            });
        }
    } catch (error) {
        console.error('Erro ao carregar tipos de exames:', error);
    }
}

async function loadExamTypesForFilter() {
    try {
        const response = await apiRequest('/v1/exam-types?status=active');
        const examTypes = Array.isArray(response.data) ? response.data : [];
        
        const select = document.getElementById('typeFilter');
        if (select) {
            // Mantém a opção "Todos"
            select.innerHTML = '<option value="">Todos</option>';
            
            // Agrupa por categoria para melhor organização
            const categoryLabels = {
                'blood': 'Sangue',
                'urine': 'Urina',
                'imaging': 'Imagem',
                'other': 'Outro'
            };
            
            const grouped = {};
            examTypes.forEach(examType => {
                if (examType.status === 'active') {
                    const category = examType.category || 'other';
                    if (!grouped[category]) {
                        grouped[category] = [];
                    }
                    grouped[category].push(examType);
                }
            });
            
            // Adiciona por categoria
            Object.keys(grouped).sort().forEach(category => {
                const categoryLabel = categoryLabels[category] || category;
                const optgroup = document.createElement('optgroup');
                optgroup.label = categoryLabel;
                
                grouped[category].forEach(examType => {
                    const option = document.createElement('option');
                    option.value = examType.id;
                    option.textContent = examType.name;
                    optgroup.appendChild(option);
                });
                
                select.appendChild(optgroup);
            });
        }
    } catch (error) {
        // Se a API não existir, usa valores padrão como fallback
        console.warn('Erro ao carregar tipos de exames:', error);
        const select = document.getElementById('typeFilter');
        if (select) {
            select.innerHTML = `
                <option value="">Todos</option>
                <option value="blood">Sangue</option>
                <option value="urine">Urina</option>
                <option value="xray">Raio-X</option>
                <option value="ultrasound">Ultrassom</option>
                <option value="other">Outro</option>
            `;
        }
    }
}

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

async function rescheduleExam(examId) {
    // Busca o exame na lista atual ou tenta carregar da API
    let exam = exams.find(e => e.id === examId);
    
    if (!exam) {
        try {
            const response = await apiRequest(`/v1/exams/${examId}`);
            exam = response.data;
        } catch (error) {
            showAlert('Exame não encontrado', 'danger');
            return;
        }
    }
    
    const currentDate = exam.exam_date ? formatDate(exam.exam_date) : '';
    const currentTime = exam.exam_time || '';
    
    const newDate = prompt(`Digite a nova data do exame (dd/mm/aaaa):\nData atual: ${currentDate}`);
    if (!newDate) return;
    
    // Converte data do formato brasileiro para ISO
    const [day, month, year] = newDate.split('/');
    if (!day || !month || !year || day.length !== 2 || month.length !== 2 || year.length !== 4) {
        showAlert('Data inválida. Use o formato dd/mm/aaaa', 'danger');
        return;
    }
    
    const isoDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
    const newTime = prompt(`Digite o novo horário (HH:mm):\nHorário atual: ${currentTime}`);
    if (!newTime) return;
    
    // Valida formato de hora
    if (!/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/.test(newTime)) {
        showAlert('Horário inválido. Use o formato HH:mm (ex: 14:30)', 'danger');
        return;
    }
    
    try {
        const response = await apiRequest(`/v1/exams/${examId}`, {
            method: 'PUT',
            body: JSON.stringify({
                exam_date: isoDate,
                exam_time: newTime,
                status: 'scheduled'
            })
        });
        
        cache.clear('/v1/exams');
        showAlert('Exame remarcado com sucesso!', 'success');
        
        setTimeout(async () => {
            await loadExams(true);
        }, 100);
    } catch (error) {
        if (error.message && !error.message.includes('404') && !error.message.includes('não encontrado')) {
            showAlert('Erro ao remarcar exame: ' + error.message, 'danger');
        } else {
            showAlert('Funcionalidade de remarcação em desenvolvimento. A API será implementada em breve.', 'info');
        }
    }
}

async function deleteExam(examId) {
    if (!confirm('Tem certeza que deseja excluir este exame? Esta ação não pode ser desfeita.')) return;
    
    try {
        await apiRequest(`/v1/exams/${examId}`, {
            method: 'DELETE'
        });
        
        cache.clear('/v1/exams');
        showAlert('Exame excluído com sucesso!', 'success');
        
        setTimeout(async () => {
            await loadExams(true);
        }, 100);
    } catch (error) {
        showAlert(error.message || 'Erro ao excluir exame', 'danger');
    }
}

async function cancelExam(examId) {
    // Busca o exame na lista atual ou tenta carregar da API
    let exam = exams.find(e => e.id === examId);
    
    if (!exam) {
        try {
            const response = await apiRequest(`/v1/exams/${examId}`);
            exam = response.data;
        } catch (error) {
            showAlert('Exame não encontrado', 'danger');
            return;
        }
    }
    
    const examInfo = `Pet: ${exam.petName || exam.pet?.name || 'N/A'}\nData: ${formatDate(exam.exam_date)}\nTipo: ${exam.examTypeText || exam.exam_type || 'N/A'}`;
    
    const confirmed = await showConfirmModal(
        `Tem certeza que deseja cancelar este exame?\n\n${examInfo}`,
        'Confirmar Cancelamento',
        'Cancelar Exame'
    );
    
    if (!confirmed) return;
    
    try {
        const response = await apiRequest(`/v1/exams/${examId}`, {
            method: 'PUT',
            body: JSON.stringify({
                status: 'cancelled'
            })
        });
        
        cache.clear('/v1/exams');
        showAlert('Exame cancelado com sucesso!', 'success');
        
        setTimeout(async () => {
            await loadExams(true);
        }, 100);
    } catch (error) {
        if (error.message && !error.message.includes('404') && !error.message.includes('não encontrado')) {
            showAlert('Erro ao cancelar exame: ' + error.message, 'danger');
        } else {
            showAlert('Funcionalidade de cancelamento em desenvolvimento. A API será implementada em breve.', 'info');
        }
    }
}
</script>

