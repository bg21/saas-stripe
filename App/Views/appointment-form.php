<?php
/**
 * View de Formulário de Agendamento
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/appointments">Agendamentos</a></li>
            <li class="breadcrumb-item active">Novo Agendamento</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Novo Agendamento</h5>
        </div>
        <div class="card-body">
            <form id="appointmentForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cliente *</label>
                        <select class="form-select" name="client_id" id="clientId" required>
                            <option value="">Selecione...</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Pet *</label>
                        <select class="form-select" name="pet_id" id="petId" required disabled>
                            <option value="">Selecione um cliente primeiro...</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Profissional *</label>
                        <select class="form-select" name="professional_id" id="professionalId" required>
                            <option value="">Selecione...</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Especialidade</label>
                        <select class="form-select" name="specialty_id" id="specialtyId">
                            <option value="">Nenhuma</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Data *</label>
                        <input type="date" class="form-control" name="appointment_date" id="appointmentDate" required min="<?php echo date('Y-m-d'); ?>">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Hora *</label>
                        <input type="time" class="form-control" name="appointment_time" id="appointmentTime" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Duração (min) *</label>
                        <input type="number" class="form-control" name="duration_minutes" id="durationMinutes" value="30" required min="15" max="240" step="15">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Observações</label>
                    <textarea class="form-control" name="notes" id="notes" rows="3"></textarea>
                </div>
                <div class="alert alert-info" id="availableSlotsInfo" style="display: none;">
                    <strong>Horários disponíveis:</strong>
                    <div id="availableSlotsList" class="mt-2"></div>
                </div>
                <div class="d-flex justify-content-between">
                    <a href="/appointments" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Criar Agendamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
const petIdParam = urlParams.get('pet_id');
const clientIdParam = urlParams.get('client_id');

let clients = [];
let pets = [];
let professionals = [];
let specialties = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadFormData();
    }, 100);
});

async function loadFormData() {
    try {
        const [clientsRes, professionalsRes, specialtiesRes] = await Promise.all([
            apiRequest('/v1/clients'),
            apiRequest('/v1/professionals'),
            apiRequest('/v1/specialties')
        ]);

        clients = clientsRes.data?.clients || [];
        professionals = professionalsRes.data?.professionals || professionalsRes.data || [];
        specialties = specialtiesRes.data?.specialties || specialtiesRes.data || [];

        renderClients();
        renderProfessionals();
        renderSpecialties();

        // Se pet_id foi passado, carrega o pet e o cliente e desabilita os campos
        if (petIdParam) {
            await loadPetAndClient(petIdParam);
        } else if (clientIdParam) {
            document.getElementById('clientId').value = clientIdParam;
            document.getElementById('clientId').disabled = true; // Desabilita o campo de cliente
            await loadPetsForClient(clientIdParam);
        }
    } catch (error) {
        showAlert('Erro ao carregar dados do formulário: ' + error.message, 'danger');
    }
}

async function loadPetAndClient(petId) {
    try {
        const petRes = await apiRequest(`/v1/pets/${petId}`);
        const pet = petRes.data;
        
        if (pet && pet.client_id) {
            document.getElementById('clientId').value = pet.client_id;
            document.getElementById('clientId').disabled = true; // Desabilita o campo de cliente
            
            await loadPetsForClient(pet.client_id);
            document.getElementById('petId').value = petId;
            document.getElementById('petId').disabled = true; // Desabilita o campo de pet
        }
    } catch (error) {
        showAlert('Erro ao carregar pet: ' + error.message, 'danger');
    }
}

async function loadPetsForClient(clientId) {
    try {
        const petsRes = await apiRequest(`/v1/pets?client_id=${clientId}`);
        pets = petsRes.data?.pets || [];
        renderPets();
        document.getElementById('petId').disabled = false;
    } catch (error) {
        showAlert('Erro ao carregar pets: ' + error.message, 'danger');
    }
}

function renderClients() {
    const select = document.getElementById('clientId');
    select.innerHTML = '<option value="">Selecione...</option>';
    clients.forEach(client => {
        const option = document.createElement('option');
        option.value = client.id;
        option.textContent = client.name;
        select.appendChild(option);
    });
}

function renderPets() {
    const select = document.getElementById('petId');
    select.innerHTML = '<option value="">Selecione...</option>';
    pets.forEach(pet => {
        const option = document.createElement('option');
        option.value = pet.id;
        option.textContent = pet.name;
        select.appendChild(option);
    });
}

function renderProfessionals() {
    const select = document.getElementById('professionalId');
    select.innerHTML = '<option value="">Selecione...</option>';
    professionals.forEach(professional => {
        const option = document.createElement('option');
        option.value = professional.id;
        option.textContent = professional.name || `Profissional #${professional.id}`;
        select.appendChild(option);
    });
}

function renderSpecialties() {
    const select = document.getElementById('specialtyId');
    select.innerHTML = '<option value="">Nenhuma</option>';
    specialties.forEach(specialty => {
        const option = document.createElement('option');
        option.value = specialty.id;
        option.textContent = specialty.name;
        select.appendChild(option);
    });
}

document.getElementById('clientId').addEventListener('change', async function() {
    const clientId = this.value;
    if (clientId) {
        await loadPetsForClient(clientId);
    } else {
        document.getElementById('petId').innerHTML = '<option value="">Selecione um cliente primeiro...</option>';
        document.getElementById('petId').disabled = true;
    }
});

document.getElementById('appointmentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    // Converte valores numéricos
    data.client_id = parseInt(data.client_id);
    data.pet_id = parseInt(data.pet_id);
    data.professional_id = parseInt(data.professional_id);
    data.duration_minutes = parseInt(data.duration_minutes);
    if (data.specialty_id) {
        data.specialty_id = parseInt(data.specialty_id);
    } else {
        delete data.specialty_id;
    }
    
    try {
        const result = await apiRequest('/v1/appointments', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        
        showAlert('Agendamento criado com sucesso!', 'success');
        setTimeout(() => {
            window.location.href = `/appointment-details?id=${result.data.id}`;
        }, 1500);
    } catch (error) {
        showAlert('Erro ao criar agendamento: ' + error.message, 'danger');
    }
});

function showAlert(message, type) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    setTimeout(() => {
        container.innerHTML = '';
    }, 5000);
}
</script>

