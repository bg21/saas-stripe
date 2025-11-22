<?php
/**
 * View de Detalhes do Agendamento
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/appointments">Agendamentos</a></li>
            <li class="breadcrumb-item active">Detalhes</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>
    <div id="loadingAppointment" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div id="appointmentDetails" style="display: none;">
        <!-- Informações do Agendamento -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Informações do Agendamento</h5>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleEditMode()" id="editBtn">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                    <button class="btn btn-sm btn-outline-success" onclick="confirmAppointment()" id="confirmBtn" style="display: none;">
                        <i class="bi bi-check-circle"></i> Confirmar
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="cancelAppointment()" id="cancelBtn" style="display: none;">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="completeAppointment()" id="completeBtn" style="display: none;">
                        <i class="bi bi-check2-all"></i> Concluir
                    </button>
                </div>
            </div>
            <div class="card-body" id="appointmentInfo">
            </div>
        </div>

        <!-- Modo Edição -->
        <div id="editMode" style="display: none;">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editar Agendamento</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEditMode()">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
                <div class="card-body">
                    <form id="editAppointmentForm" class="needs-validation" novalidate>
                        <input type="hidden" id="editAppointmentId">
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="editAppointmentDate" class="form-label">Data *</label>
                                <input type="date" class="form-control" id="editAppointmentDate" name="appointment_date" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="editAppointmentTime" class="form-label">Hora *</label>
                                <input type="time" class="form-control" id="editAppointmentTime" name="appointment_time" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="editAppointmentDuration" class="form-label">Duração (min) *</label>
                                <input type="number" class="form-control" id="editAppointmentDuration" name="duration_minutes" required min="15" max="240" step="15">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editAppointmentNotes" class="form-label">Observações</label>
                            <textarea class="form-control" id="editAppointmentNotes" name="notes" rows="3"></textarea>
                        </div>

                        <div id="editAppointmentError" class="alert alert-danger d-none mb-3" role="alert"></div>

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

        <!-- Histórico do Agendamento -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Histórico</h5>
            </div>
            <div class="card-body">
                <div id="historyList">
                    <p class="text-muted">Carregando histórico...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
const appointmentId = urlParams.get('id');

if (!appointmentId) {
    window.location.href = '/appointments';
}

let appointmentData = null;
let history = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadAppointmentDetails();
    }, 100);
});

async function loadAppointmentDetails() {
    try {
        const [appointment, appointmentHistory] = await Promise.all([
            apiRequest(`/v1/appointments/${appointmentId}`),
            apiRequest(`/v1/appointments/${appointmentId}/history`).catch(() => ({ data: [] }))
        ]);

        appointmentData = appointment.data;
        history = Array.isArray(appointmentHistory.data) ? appointmentHistory.data : [];
        
        renderAppointmentInfo(appointmentData);
        renderHistory();
        updateActionButtons();

        document.getElementById('loadingAppointment').style.display = 'none';
        document.getElementById('appointmentDetails').style.display = 'block';
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
    }
}

function renderAppointmentInfo(appointment) {
    const statusBadge = appointment.status === 'scheduled' ? 'bg-secondary' :
                        appointment.status === 'confirmed' ? 'bg-primary' :
                        appointment.status === 'completed' ? 'bg-success' :
                        appointment.status === 'cancelled' ? 'bg-danger' : 'bg-warning';
    const statusText = appointment.status === 'scheduled' ? 'Marcado' :
                      appointment.status === 'confirmed' ? 'Confirmado' :
                      appointment.status === 'completed' ? 'Concluído' :
                      appointment.status === 'cancelled' ? 'Cancelado' : 'Falta';
    
    document.getElementById('appointmentInfo').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> ${appointment.id}</p>
                <p><strong>Data:</strong> ${formatDate(appointment.appointment_date)}</p>
                <p><strong>Hora:</strong> ${appointment.appointment_time}</p>
                <p><strong>Duração:</strong> ${appointment.duration_minutes || 30} minutos</p>
                <p><strong>Status:</strong> <span class="badge ${statusBadge}">${statusText}</span></p>
            </div>
            <div class="col-md-6">
                <p><strong>Profissional:</strong> <a href="/professional-details?id=${appointment.professional_id}">Ver Profissional</a></p>
                <p><strong>Cliente:</strong> <a href="/clinic-client-details?id=${appointment.client_id}">Ver Cliente</a></p>
                <p><strong>Pet:</strong> <a href="/pet-details?id=${appointment.pet_id}">Ver Pet</a></p>
                ${appointment.specialty_id ? `<p><strong>Especialidade:</strong> Especialidade #${appointment.specialty_id}</p>` : ''}
                <p><strong>Criado em:</strong> ${formatDate(appointment.created_at)}</p>
            </div>
        </div>
        ${appointment.notes ? `<div class="mt-3"><strong>Observações:</strong><p class="mt-2">${appointment.notes}</p></div>` : ''}
        ${appointment.cancellation_reason ? `<div class="mt-3"><strong>Motivo do Cancelamento:</strong><p class="mt-2 text-danger">${appointment.cancellation_reason}</p></div>` : ''}
    `;
}

function renderHistory() {
    const container = document.getElementById('historyList');
    
    if (history.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhum histórico encontrado</p>';
        return;
    }
    
    container.innerHTML = `
        <div class="list-group">
            ${history.map(h => `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>${h.action || h.event_type || 'Ação'}</strong>
                            <p class="mb-0 text-muted small">${formatDate(h.created_at)}</p>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function updateActionButtons() {
    if (!appointmentData) return;
    
    const status = appointmentData.status;
    const editBtn = document.getElementById('editBtn');
    const confirmBtn = document.getElementById('confirmBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const completeBtn = document.getElementById('completeBtn');
    
    // Reset
    editBtn.style.display = 'inline-block';
    confirmBtn.style.display = 'none';
    cancelBtn.style.display = 'none';
    completeBtn.style.display = 'none';
    
    if (status === 'scheduled') {
        confirmBtn.style.display = 'inline-block';
        cancelBtn.style.display = 'inline-block';
    } else if (status === 'confirmed') {
        cancelBtn.style.display = 'inline-block';
        completeBtn.style.display = 'inline-block';
    } else if (status === 'completed' || status === 'cancelled') {
        editBtn.style.display = 'none';
    }
}

function toggleEditMode() {
    const viewMode = document.getElementById('appointmentInfo').closest('.card');
    const editMode = document.getElementById('editMode');
    
    if (viewMode.style.display === 'none') {
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        if (appointmentData) {
            loadAppointmentForEdit();
        }
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}

function loadAppointmentForEdit() {
    if (!appointmentData) return;
    
    document.getElementById('editAppointmentId').value = appointmentData.id;
    document.getElementById('editAppointmentDate').value = appointmentData.appointment_date || '';
    document.getElementById('editAppointmentTime').value = appointmentData.appointment_time || '';
    document.getElementById('editAppointmentDuration').value = appointmentData.duration_minutes || 30;
    document.getElementById('editAppointmentNotes').value = appointmentData.notes || '';
}

document.getElementById('editAppointmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const formData = {
        appointment_date: document.getElementById('editAppointmentDate').value,
        appointment_time: document.getElementById('editAppointmentTime').value,
        duration_minutes: parseInt(document.getElementById('editAppointmentDuration').value) || 30,
        notes: document.getElementById('editAppointmentNotes').value.trim() || null
    };
    
    // Remove campos vazios
    Object.keys(formData).forEach(key => {
        if (formData[key] === '' || formData[key] === null) {
            delete formData[key];
        }
    });

    const submitBtn = this.querySelector('button[type="submit"]');
    const spinner = submitBtn.querySelector('.spinner-border');
    const errorDiv = document.getElementById('editAppointmentError');
    
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    errorDiv.classList.add('d-none');

    apiRequest(`/v1/appointments/${appointmentId}`, {
        method: 'PUT',
        body: JSON.stringify(formData)
    })
    .then(data => {
        if (data.success) {
            showAlert('Agendamento atualizado com sucesso!', 'success');
            appointmentData = data.data;
            renderAppointmentInfo(appointmentData);
            updateActionButtons();
            toggleEditMode();
            cache.clear(`/v1/appointments/${appointmentId}`);
        } else {
            throw new Error(data.error || 'Erro ao atualizar agendamento');
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

async function confirmAppointment() {
    try {
        const response = await apiRequest(`/v1/appointments/${appointmentId}/confirm`, {
            method: 'POST'
        });
        
        if (response.success) {
            showAlert('Agendamento confirmado com sucesso!', 'success');
            appointmentData = response.data;
            renderAppointmentInfo(appointmentData);
            updateActionButtons();
            cache.clear(`/v1/appointments/${appointmentId}`);
        }
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function cancelAppointment() {
    const reason = prompt('Motivo do cancelamento:');
    if (!reason) return;
    
    try {
        await apiRequest(`/v1/appointments/${appointmentId}`, {
            method: 'DELETE',
            body: JSON.stringify({ reason: reason })
        });
        
        showAlert('Agendamento cancelado com sucesso!', 'success');
        cache.clear(`/v1/appointments/${appointmentId}`);
        
        setTimeout(async () => {
            await loadAppointmentDetails();
        }, 100);
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function completeAppointment() {
    try {
        const response = await apiRequest(`/v1/appointments/${appointmentId}/complete`, {
            method: 'POST'
        });
        
        if (response.success) {
            showAlert('Agendamento marcado como concluído!', 'success');
            appointmentData = response.data;
            renderAppointmentInfo(appointmentData);
            updateActionButtons();
            cache.clear(`/v1/appointments/${appointmentId}`);
        }
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

