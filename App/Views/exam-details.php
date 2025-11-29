<?php
/**
 * View de Detalhes do Exame
 */
?>
<style>
/* Estilos específicos para a página de detalhes do exame */
.exam-header {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
}

.exam-header-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #212529;
    margin-bottom: 0.5rem;
}

.exam-header-subtitle {
    color: #6c757d;
    font-size: 0.95rem;
    margin-bottom: 1rem;
}

.exam-header-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}

.status-badge-large {
    padding: 0.5rem 1.25rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.exam-info-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    margin-bottom: 1.5rem;
    border: 1px solid #e9ecef;
}

.exam-info-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.exam-info-card .card-header {
    background: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    border-radius: 12px 12px 0 0 !important;
    padding: 1rem 1.5rem;
    font-weight: 600;
    color: #495057;
}

.info-item {
    padding: 1rem 0;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    transition: background-color 0.2s ease;
}

.info-item:hover {
    background-color: #f8f9fa;
    margin: 0 -1rem;
    padding-left: 1rem;
    padding-right: 1rem;
    border-radius: 8px;
}

.info-item:last-child {
    border-bottom: none;
}

.info-item-label {
    font-weight: 600;
    color: #495057;
    min-width: 160px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.95rem;
}

.info-item-label i {
    font-size: 1.25rem;
    color: #667eea;
    width: 24px;
    text-align: center;
}

.info-item-value {
    color: #212529;
    flex: 1;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-item-value strong {
    color: #212529;
    font-weight: 600;
}

.info-section-title {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #6c757d;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e9ecef;
}

.btn-action {
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

@media (max-width: 768px) {
    .exam-header {
        padding: 1.5rem;
    }
    
    .exam-header-title {
        font-size: 1.5rem;
    }
    
    .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
        padding: 0.875rem 0;
    }
    
    .info-item:hover {
        margin: 0;
        padding: 0.875rem 0;
    }
    
    .info-item-label {
        min-width: auto;
        width: 100%;
    }
    
    .info-item-value {
        width: 100%;
    }
}
</style>

<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/exams"><i class='bx bx-arrow-back'></i> Exames</a></li>
            <li class="breadcrumb-item active">Detalhes</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>
    
    <!-- Loading State -->
    <div id="loadingExam" class="text-center py-5">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Carregando...</span>
        </div>
        <p class="mt-3 text-muted">Carregando informações do exame...</p>
    </div>

    <div id="examDetails" style="display: none;">
        <!-- Header do Exame -->
        <div class="exam-header">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <h1 class="exam-header-title" id="examTitle">Exame</h1>
                    <div class="exam-header-subtitle" id="examSubtitle">
                        <i class='bx bx-calendar'></i> <span id="examDateHeader">-</span> às <span id="examTimeHeader">-</span>
                    </div>
                    <div class="mt-2" id="examStatusHeader"></div>
                </div>
                <div class="exam-header-actions mt-3 mt-md-0">
                    <?php if (($user['role'] ?? '') === 'admin' || ($user['role'] ?? '') === 'editor'): ?>
                    <button class="btn btn-primary btn-action" onclick="toggleEditMode()" id="editBtn">
                        <i class='bx bx-edit'></i> Editar
                    </button>
                    <?php endif; ?>
                    <?php if (($user['role'] ?? '') === 'admin'): ?>
                    <button class="btn btn-danger btn-action" onclick="deleteExam()" id="deleteBtn">
                        <i class='bx bx-trash'></i> Excluir
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary btn-action" onclick="window.print()">
                        <i class='bx bx-printer'></i> Imprimir
                    </button>
                </div>
            </div>
        </div>

        <!-- Modo Visualização -->
        <div id="viewMode">
            <div class="row">
                <!-- Informações do Exame -->
                <div class="col-lg-6">
                    <div class="card exam-info-card">
                        <div class="card-header">
                            <i class='bx bx-test-tube'></i> Informações do Exame
                        </div>
                        <div class="card-body" id="examInfo">
                        </div>
                    </div>
                </div>

                <!-- Informações dos Participantes -->
                <div class="col-lg-6">
                    <div class="card exam-info-card">
                        <div class="card-header">
                            <i class='bx bx-group'></i> Participantes
                        </div>
                        <div class="card-body" id="participantsInfo">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Observações e Resultados -->
            <div class="row">
                <div class="col-12">
                    <div class="card exam-info-card">
                        <div class="card-header">
                            <i class='bx bx-note'></i> Observações e Resultados
                        </div>
                        <div class="card-body" id="notesInfo">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modo Edição -->
        <?php if (($user['role'] ?? '') === 'admin' || ($user['role'] ?? '') === 'editor'): ?>
        <div id="editMode" style="display: none;">
            <div class="card exam-info-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class='bx bx-edit'></i> Editar Exame</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEditMode()">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                </div>
                <div class="card-body">
                    <form id="editExamForm" class="needs-validation" novalidate>
                        <input type="hidden" id="editExamId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editExamPetId" class="form-label">
                                    <i class='bx bx-paw'></i> Pet <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="editExamPetId" name="pet_id" required>
                                    <option value="">Selecione...</option>
                                </select>
                                <div class="invalid-feedback">Por favor, selecione o pet.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editExamProfessionalId" class="form-label">
                                    <i class='bx bx-user-circle'></i> Profissional
                                </label>
                                <select class="form-select" id="editExamProfessionalId" name="professional_id">
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editExamTypeId" class="form-label">
                                    <i class='bx bx-test-tube'></i> Tipo de Exame <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="editExamTypeId" name="exam_type_id" required>
                                    <option value="">Selecione...</option>
                                </select>
                                <div class="invalid-feedback">Por favor, selecione o tipo de exame.</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="editExamDate" class="form-label">
                                    <i class='bx bx-calendar'></i> Data <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="editExamDate" name="exam_date" required>
                                <div class="invalid-feedback">Por favor, informe a data do exame.</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="editExamTime" class="form-label">
                                    <i class='bx bx-time'></i> Hora
                                </label>
                                <input type="time" class="form-control" id="editExamTime" name="exam_time">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editExamStatus" class="form-label">
                                    <i class='bx bx-info-circle'></i> Status <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="editExamStatus" name="status" required>
                                    <option value="pending">Pendente</option>
                                    <option value="scheduled">Agendado</option>
                                    <option value="completed">Concluído</option>
                                    <option value="cancelled">Cancelado</option>
                                </select>
                                <div class="invalid-feedback">Por favor, selecione o status.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editExamNotes" class="form-label">
                                <i class='bx bx-note'></i> Observações
                            </label>
                            <textarea class="form-control" id="editExamNotes" name="notes" rows="3" placeholder="Observações sobre o exame..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editExamResults" class="form-label">
                                <i class='bx bx-file-blank'></i> Resultados (Texto)
                            </label>
                            <textarea class="form-control" id="editExamResults" name="results" rows="4" placeholder="Resultados do exame em texto..."></textarea>
                            <small class="form-text text-muted">Use este campo para resultados em texto ou faça upload de um PDF abaixo.</small>
                        </div>
                        <div class="mb-3">
                            <label for="editExamResultsFile" class="form-label">
                                <i class='bx bx-file-pdf'></i> Resultados em PDF
                            </label>
                            <input type="file" class="form-control" id="editExamResultsFile" name="results_file" accept="application/pdf">
                            <small class="form-text text-muted">Faça upload de um arquivo PDF com os resultados do exame (máximo 10MB).</small>
                            <div id="currentResultsFile" class="mt-2" style="display: none;">
                                <div class="alert alert-info mb-0">
                                    <i class='bx bx-file-pdf'></i> <span id="currentResultsFileName"></span>
                                    <a href="#" id="viewCurrentResultsFile" target="_blank" class="btn btn-sm btn-outline-primary ms-2">
                                        <i class='bx bx-show'></i> Visualizar
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div id="editExamError" class="alert alert-danger d-none mb-3" role="alert"></div>

                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-secondary btn-action" onclick="toggleEditMode()">
                                <i class='bx bx-x'></i> Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary btn-action">
                                <i class='bx bx-save'></i> Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
const examId = urlParams.get('id');

if (!examId) {
    window.location.href = '/exams';
}

let examData = null;
let pets = [];
let professionals = [];
let examTypes = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadExamDetails();
        loadPetsForSelect();
        loadProfessionalsForSelect();
        loadExamTypesForSelect();
    }, 100);
});

async function loadExamDetails() {
    try {
        const response = await apiRequest(`/v1/exams/${examId}`);
        examData = response.data;
        
        // Debug: verifica se results_file está presente
        console.log('Exam data loaded:', examData);
        console.log('Results file:', examData.results_file);
        
        renderExamInfo(examData);
        document.getElementById('loadingExam').style.display = 'none';
        document.getElementById('examDetails').style.display = 'block';
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
    }
}

function renderExamInfo(exam) {
    const statusBadge = exam.status === 'pending' ? 'bg-warning' :
                        exam.status === 'scheduled' ? 'bg-info' :
                        exam.status === 'completed' ? 'bg-success' :
                        exam.status === 'cancelled' ? 'bg-danger' : 'bg-secondary';
    const statusText = exam.status === 'pending' ? 'Pendente' :
                      exam.status === 'scheduled' ? 'Agendado' :
                      exam.status === 'completed' ? 'Concluído' :
                      exam.status === 'cancelled' ? 'Cancelado' : exam.status;
    const statusIcon = exam.status === 'pending' ? 'bx-time' :
                      exam.status === 'scheduled' ? 'bx-calendar' :
                      exam.status === 'completed' ? 'bx-check' :
                      exam.status === 'cancelled' ? 'bx-x-circle' : 'bx-info-circle';
    
    // Atualiza header
    document.getElementById('examDateHeader').textContent = formatDate(exam.exam_date);
    document.getElementById('examTimeHeader').textContent = exam.exam_time ? exam.exam_time.substring(0, 5) : '-';
    document.getElementById('examStatusHeader').innerHTML = `
        <span class="status-badge-large ${statusBadge}">
            <i class='bx ${statusIcon}'></i> ${statusText}
        </span>
    `;
    
    // Informações do Exame
    const examTypeName = exam.exam_type?.name || (exam.exam_type_id ? `Tipo #${exam.exam_type_id}` : 'N/A');
    document.getElementById('examInfo').innerHTML = `
        <div class="info-section-title">Detalhes do Exame</div>
        <div class="info-item">
            <div class="info-item-label">
                <i class='bx bx-id-card'></i> ID do Exame
            </div>
            <div class="info-item-value">
                <span class="badge bg-secondary">#${exam.id}</span>
            </div>
        </div>
        <div class="info-item">
            <div class="info-item-label">
                <i class='bx bx-test-tube'></i> Tipo de Exame
            </div>
            <div class="info-item-value"><strong>${escapeHtml(examTypeName)}</strong></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">
                <i class='bx bx-calendar'></i> Data
            </div>
            <div class="info-item-value"><strong>${formatDate(exam.exam_date)}</strong></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">
                <i class='bx bx-time'></i> Hora
            </div>
            <div class="info-item-value"><strong>${exam.exam_time ? exam.exam_time.substring(0, 5) : '-'}</strong></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">
                <i class='bx bx-info-circle'></i> Status
            </div>
            <div class="info-item-value">
                <span class="status-badge-large ${statusBadge}">
                    <i class='bx ${statusIcon}'></i> ${statusText}
                </span>
            </div>
        </div>
        <div class="info-section-title" style="margin-top: 1.5rem;">Registro</div>
        <div class="info-item">
            <div class="info-item-label">
                <i class='bx bx-time'></i> Criado em
            </div>
            <div class="info-item-value">${formatDate(exam.created_at)}</div>
        </div>
        ${exam.completed_at ? `
        <div class="info-item">
            <div class="info-item-label">
                <i class='bx bx-check'></i> Concluído em
            </div>
            <div class="info-item-value">${formatDate(exam.completed_at)}</div>
        </div>
        ` : ''}
        ${exam.cancelled_at ? `
        <div class="info-item">
            <div class="info-item-label">
                <i class='bx bx-x-circle'></i> Cancelado em
            </div>
            <div class="info-item-value">${formatDate(exam.cancelled_at)}</div>
        </div>
        ` : ''}
    `;
    
    // Participantes
    let professionalName = 'Não atribuído';
    if (exam.professional) {
        const name = exam.professional.user?.name || exam.professional.name || 'Profissional';
        professionalName = exam.professional.crmv ? `${name} - CRMV: ${exam.professional.crmv}` : name;
    } else if (exam.professional_id) {
        professionalName = `Profissional #${exam.professional_id}`;
    }
    
    document.getElementById('participantsInfo').innerHTML = `
        <div class="info-section-title">Profissional</div>
        <div class="info-item">
            <div class="info-item-label">
                <i class='bx bx-user-circle'></i> Profissional
            </div>
            <div class="info-item-value">
                ${exam.professional_id ? `
                    <a href="/professional-details?id=${exam.professional_id}" class="text-decoration-none text-primary">
                        ${escapeHtml(professionalName)} <i class='bx bx-link-external' style="font-size: 0.875rem;"></i>
                    </a>
                ` : '<span class="text-muted">Não atribuído</span>'}
            </div>
        </div>
        <div class="info-section-title" style="margin-top: 1.5rem;">Cliente e Pet</div>
        <div class="info-item">
            <div class="info-item-label">
                <i class='bx bx-user'></i> Cliente
            </div>
            <div class="info-item-value">
                <a href="/clinic-client-details?id=${exam.client_id}" class="text-decoration-none text-primary">
                    ${exam.client?.name || `Cliente #${exam.client_id}`} <i class='bx bx-link-external' style="font-size: 0.875rem;"></i>
                </a>
            </div>
        </div>
        <div class="info-item">
            <div class="info-item-label">
                <i class='bx bx-paw'></i> Pet
            </div>
            <div class="info-item-value">
                <a href="/pet-details?id=${exam.pet_id}" class="text-decoration-none text-primary">
                    ${exam.pet?.name || `Pet #${exam.pet_id}`} <i class='bx bx-link-external' style="font-size: 0.875rem;"></i>
                </a>
            </div>
        </div>
    `;
    
    // Observações e Resultados
    const notesEl = document.getElementById('notesInfo');
    let notesHtml = '';
    
    // Observações
    if (exam.notes && exam.notes.trim()) {
        notesHtml += `
            <div class="mb-3">
                <div class="info-section-title">Observações</div>
                <p class="mb-0" style="color: #495057; line-height: 1.6;">${escapeHtml(exam.notes)}</p>
            </div>
        `;
    }
    
    // Resultados - pode ter texto E/OU PDF
    const hasResultsText = exam.results && exam.results.trim && exam.results.trim() !== '';
    const hasResultsFile = exam.results_file && exam.results_file.trim && exam.results_file.trim() !== '';
    
    // Debug
    console.log('Checking results:', {
        hasResultsText,
        hasResultsFile,
        results_file: exam.results_file,
        results: exam.results
    });
    
    if (hasResultsText || hasResultsFile) {
        notesHtml += `
            <div class="mt-3">
                <div class="info-section-title">Resultados</div>
        `;
        
        // Resultados em texto
        if (hasResultsText) {
            notesHtml += `
                <div class="alert alert-info mb-3" style="border-left: 4px solid #0dcaf0;">
                    <pre style="white-space: pre-wrap; font-family: inherit; margin: 0;">${escapeHtml(exam.results)}</pre>
                </div>
            `;
        }
        
        // Resultados em PDF
        if (hasResultsFile) {
            notesHtml += `
                <div class="alert alert-info mb-0" style="border-left: 4px solid #0dcaf0;">
                    <div class="d-flex align-items-center gap-3">
                        <i class='bx bx-file-pdf fs-3 text-danger'></i>
                        <div class="flex-grow-1">
                            <strong>Resultado em PDF</strong>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-primary me-2" onclick="viewExamPDF(${exam.id})">
                                    <i class='bx bx-show'></i> Visualizar
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="downloadExamPDF(${exam.id})">
                                    <i class='bx bx-download'></i> Baixar PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        notesHtml += `</div>`;
    }
    
    if (exam.cancellation_reason) {
        notesHtml += `
            <div class="mt-3">
                <div class="info-section-title" style="color: #dc3545;">Motivo do Cancelamento</div>
                <div class="alert alert-danger mb-0" style="border-left: 4px solid #dc3545;">
                    <i class='bx bx-error-circle'></i> ${escapeHtml(exam.cancellation_reason)}
                </div>
            </div>
        `;
    }
    
    if (!notesHtml) {
        notesHtml = '<p class="text-muted mb-0">Nenhuma observação ou resultado registrado.</p>';
    }
    
    notesEl.innerHTML = notesHtml;
}

async function loadPetsForSelect() {
    try {
        const response = await apiRequest('/v1/pets');
        pets = Array.isArray(response.data?.pets) ? response.data.pets : 
              Array.isArray(response.data) ? response.data : [];
        
        const select = document.getElementById('editExamPetId');
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
        professionals = Array.isArray(response.data?.professionals) ? response.data.professionals : 
                       Array.isArray(response.data) ? response.data : [];
        
        const select = document.getElementById('editExamProfessionalId');
        if (select) {
            professionals.forEach(prof => {
                const option = document.createElement('option');
                option.value = prof.id;
                option.textContent = prof.crmv ? `${prof.name} - CRMV: ${prof.crmv}` : prof.name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar profissionais:', error);
    }
}

async function loadExamTypesForSelect() {
    try {
        const response = await apiRequest('/v1/exam-types?status=active');
        examTypes = Array.isArray(response.data?.exam_types) ? response.data.exam_types : 
                   Array.isArray(response.data) ? response.data : [];
        
        const select = document.getElementById('editExamTypeId');
        if (select) {
            examTypes.forEach(type => {
                const option = document.createElement('option');
                option.value = type.id;
                option.textContent = type.name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar tipos de exames:', error);
    }
}

function toggleEditMode() {
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    
    if (viewMode.style.display === 'none') {
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        if (examData) {
            loadExamForEdit();
        }
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}

function loadExamForEdit() {
    if (!examData) return;
    
    document.getElementById('editExamId').value = examData.id;
    document.getElementById('editExamPetId').value = examData.pet_id || '';
    document.getElementById('editExamProfessionalId').value = examData.professional_id || '';
    document.getElementById('editExamTypeId').value = examData.exam_type_id || '';
    document.getElementById('editExamDate').value = examData.exam_date || '';
    document.getElementById('editExamTime').value = examData.exam_time ? examData.exam_time.substring(0, 5) : '';
    document.getElementById('editExamStatus').value = examData.status || 'pending';
    document.getElementById('editExamNotes').value = examData.notes || '';
    document.getElementById('editExamResults').value = examData.results || '';
    
    // Mostra arquivo atual se existir
    if (examData.results_file) {
        const currentFileDiv = document.getElementById('currentResultsFile');
        const currentFileName = document.getElementById('currentResultsFileName');
        const viewLink = document.getElementById('viewCurrentResultsFile');
        
        currentFileName.textContent = 'Arquivo atual: ' + examData.results_file.split('/').pop();
        viewLink.href = `/v1/exams/${examData.id}/results-file`;
        currentFileDiv.style.display = 'block';
    } else {
        document.getElementById('currentResultsFile').style.display = 'none';
    }
}

document.getElementById('editExamForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const submitBtn = this.querySelector('button[type="submit"]');
    const errorDiv = document.getElementById('editExamError');
    
    submitBtn.disabled = true;
    errorDiv.classList.add('d-none');

    try {
        // Primeiro, atualiza dados do exame (sem arquivo)
        const formData = {
            pet_id: parseInt(document.getElementById('editExamPetId').value),
            professional_id: document.getElementById('editExamProfessionalId').value ? parseInt(document.getElementById('editExamProfessionalId').value) : null,
            exam_type_id: parseInt(document.getElementById('editExamTypeId').value),
            exam_date: document.getElementById('editExamDate').value,
            exam_time: document.getElementById('editExamTime').value || null,
            status: document.getElementById('editExamStatus').value,
            notes: document.getElementById('editExamNotes').value.trim() || null,
            results: document.getElementById('editExamResults').value.trim() || null
        };
        
        // Remove campos vazios
        Object.keys(formData).forEach(key => {
            if (formData[key] === '' || formData[key] === null) {
                delete formData[key];
            }
        });

        const data = await apiRequest(`/v1/exams/${examId}`, {
            method: 'PUT',
            body: JSON.stringify(formData)
        });
        
        // Se houver arquivo para upload, faz upload separadamente
        const fileInput = document.getElementById('editExamResultsFile');
        if (fileInput && fileInput.files && fileInput.files.length > 0) {
            const fileFormData = new FormData();
            fileFormData.append('file', fileInput.files[0]);
            
            try {
                const uploadResponse = await fetch(`/v1/exams/${examId}/results-file`, {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + (localStorage.getItem('api_token') || '')
                    },
                    body: fileFormData
                });
                
                if (!uploadResponse.ok) {
                    const errorData = await uploadResponse.json();
                    throw new Error(errorData.error || 'Erro ao fazer upload do arquivo');
                }
            } catch (uploadError) {
                console.error('Erro no upload:', uploadError);
                // Não bloqueia a atualização se o upload falhar
                showAlert('Exame atualizado, mas houve erro ao fazer upload do PDF: ' + uploadError.message, 'warning');
            }
        }
        
        if (data.success) {
            showAlert('Exame atualizado com sucesso!', 'success');
            examData = data.data;
            renderExamInfo(examData);
            toggleEditMode();
            if (typeof cache !== 'undefined' && cache.clear) {
                cache.clear(`/v1/exams/${examId}`);
                cache.clear('/v1/exams');
            }
            
            // Recarrega dados para pegar o arquivo atualizado
            setTimeout(async () => {
                await loadExamDetails();
            }, 500);
        } else {
            throw new Error(data.error || 'Erro ao atualizar exame');
        }
    } catch (error) {
        console.error('Erro:', error);
        errorDiv.textContent = error.message;
        errorDiv.classList.remove('d-none');
    } finally {
        submitBtn.disabled = false;
    }
});

async function deleteExam() {
    if (!confirm('Tem certeza que deseja excluir este exame? Esta ação não pode ser desfeita.')) return;
    
    try {
        await apiRequest(`/v1/exams/${examId}`, {
            method: 'DELETE'
        });
        
        showAlert('Exame excluído com sucesso!', 'success');
        if (typeof cache !== 'undefined' && cache.clear) {
            cache.clear(`/v1/exams/${examId}`);
            cache.clear('/v1/exams');
        }
        
        setTimeout(() => {
            window.location.href = '/exams';
        }, 1000);
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function viewExamPDF(examId) {
    try {
        // Obtém session_id do localStorage (usado pelo dashboard.js)
        const sessionId = localStorage.getItem('session_id') || localStorage.getItem('api_token') || '';
        const url = `/v1/exams/${examId}/results-file${sessionId ? '?session_id=' + encodeURIComponent(sessionId) : ''}`;
        window.open(url, '_blank');
    } catch (error) {
        showAlert('Erro ao abrir PDF: ' + error.message, 'danger');
    }
}

async function downloadExamPDF(examId) {
    try {
        // Obtém session_id do localStorage (usado pelo dashboard.js)
        const sessionId = localStorage.getItem('session_id') || localStorage.getItem('api_token') || '';
        const url = `/v1/exams/${examId}/results-file${sessionId ? '?session_id=' + encodeURIComponent(sessionId) + '&download=1' : '?download=1'}`;
        
        // Usa fetch para fazer download com autenticação
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': sessionId ? `Bearer ${sessionId}` : ''
            }
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ error: 'Erro ao baixar PDF' }));
            throw new Error(errorData.error || 'Erro ao baixar PDF');
        }
        
        const blob = await response.blob();
        const downloadUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = downloadUrl;
        a.download = `resultado_exame_${examId}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(downloadUrl);
        document.body.removeChild(a);
    } catch (error) {
        showAlert('Erro ao baixar PDF: ' + error.message, 'danger');
    }
}
</script>

