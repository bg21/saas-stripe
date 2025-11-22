<?php
/**
 * View de Detalhes do Pet
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/pets">Pets</a></li>
            <li class="breadcrumb-item active">Detalhes</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>
    <div id="loadingPet" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div id="petDetails" style="display: none;">
        <!-- Modo Visualização -->
        <div id="viewMode">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Informações do Pet</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleEditMode()">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                </div>
                <div class="card-body" id="petInfo">
                </div>
            </div>
        </div>

        <!-- Modo Edição -->
        <div id="editMode" style="display: none;">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editar Pet</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEditMode()">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
                <div class="card-body">
                    <form id="editPetForm" class="needs-validation" novalidate>
                        <input type="hidden" id="editPetId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editPetName" class="form-label">Nome *</label>
                                <input type="text" class="form-control" id="editPetName" name="name" required minlength="2" maxlength="255">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editPetSpecies" class="form-label">Espécie *</label>
                                <select class="form-select" id="editPetSpecies" name="species" required>
                                    <option value="Cachorro">Cachorro</option>
                                    <option value="Gato">Gato</option>
                                    <option value="Ave">Ave</option>
                                    <option value="Roedor">Roedor</option>
                                    <option value="Réptil">Réptil</option>
                                    <option value="Outro">Outro</option>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editPetBreed" class="form-label">Raça</label>
                                <input type="text" class="form-control" id="editPetBreed" name="breed" maxlength="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editPetGender" class="form-label">Sexo</label>
                                <select class="form-select" id="editPetGender" name="gender">
                                    <option value="unknown">Desconhecido</option>
                                    <option value="male">Macho</option>
                                    <option value="female">Fêmea</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editPetBirthDate" class="form-label">Data de Nascimento</label>
                                <input type="date" class="form-control" id="editPetBirthDate" name="birth_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editPetWeight" class="form-label">Peso (kg)</label>
                                <input type="number" class="form-control" id="editPetWeight" name="weight" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editPetColor" class="form-label">Cor</label>
                                <input type="text" class="form-control" id="editPetColor" name="color" maxlength="50">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editPetMicrochip" class="form-label">Microchip</label>
                                <input type="text" class="form-control" id="editPetMicrochip" name="microchip" maxlength="50">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editPetNotes" class="form-label">Observações</label>
                            <textarea class="form-control" id="editPetNotes" name="notes" rows="3"></textarea>
                        </div>

                        <div id="editPetError" class="alert alert-danger d-none mb-3" role="alert"></div>

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

        <!-- Agendamentos do Pet -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Agendamentos</h5>
                <a href="/appointment-form?pet_id=<?php echo htmlspecialchars($_GET['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-circle"></i> Novo Agendamento
                </a>
            </div>
            <div class="card-body">
                <div id="appointmentsList">
                    <p class="text-muted">Carregando agendamentos...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
const petId = urlParams.get('id');

if (!petId) {
    window.location.href = '/pets';
}

let petData = null;
let appointments = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadPetDetails();
    }, 100);
});

async function loadPetDetails() {
    try {
        const [pet, petAppointments] = await Promise.all([
            apiRequest(`/v1/pets/${petId}`),
            apiRequest(`/v1/pets/${petId}/appointments`).catch(() => ({ data: [] }))
        ]);

        petData = pet.data;
        appointments = Array.isArray(petAppointments.data) ? petAppointments.data : [];
        
        renderPetInfo(petData);
        renderAppointments();

        document.getElementById('loadingPet').style.display = 'none';
        document.getElementById('petDetails').style.display = 'block';
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
    }
}

function renderPetInfo(pet) {
    const age = pet.birth_date ? calculateAge(pet.birth_date) : '-';
    const genderBadge = pet.gender === 'male' ? 'bg-primary' : 
                       pet.gender === 'female' ? 'bg-danger' : 'bg-secondary';
    const genderText = pet.gender === 'male' ? 'Macho' : 
                     pet.gender === 'female' ? 'Fêmea' : 'Desconhecido';
    
    document.getElementById('petInfo').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> ${pet.id}</p>
                <p><strong>Nome:</strong> ${pet.name || '-'}</p>
                <p><strong>Espécie:</strong> ${pet.species || '-'}</p>
                <p><strong>Raça:</strong> ${pet.breed || '-'}</p>
                <p><strong>Sexo:</strong> <span class="badge ${genderBadge}">${genderText}</span></p>
            </div>
            <div class="col-md-6">
                <p><strong>Idade:</strong> ${age}</p>
                <p><strong>Peso:</strong> ${pet.weight ? pet.weight + ' kg' : '-'}</p>
                <p><strong>Cor:</strong> ${pet.color || '-'}</p>
                <p><strong>Microchip:</strong> ${pet.microchip || '-'}</p>
                <p><strong>Cliente:</strong> <a href="/clinic-client-details?id=${pet.client_id}">Ver Cliente</a></p>
                <p><strong>Criado em:</strong> ${formatDate(pet.created_at)}</p>
            </div>
        </div>
        ${pet.notes ? `<div class="mt-3"><strong>Observações:</strong><p class="mt-2">${pet.notes}</p></div>` : ''}
    `;
}

function renderAppointments() {
    const container = document.getElementById('appointmentsList');
    
    if (appointments.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhum agendamento encontrado</p>';
        return;
    }
    
    container.innerHTML = `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Hora</th>
                        <th>Profissional</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${appointments.map(apt => {
                        const statusBadge = apt.status === 'scheduled' ? 'bg-secondary' :
                                          apt.status === 'confirmed' ? 'bg-primary' :
                                          apt.status === 'completed' ? 'bg-success' :
                                          apt.status === 'cancelled' ? 'bg-danger' : 'bg-warning';
                        return `
                            <tr>
                                <td>${formatDate(apt.appointment_date)}</td>
                                <td>${apt.appointment_time}</td>
                                <td>Profissional #${apt.professional_id}</td>
                                <td><span class="badge ${statusBadge}">${apt.status || 'scheduled'}</span></td>
                                <td>
                                    <a href="/appointment-details?id=${apt.id}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function calculateAge(birthDate) {
    const birth = new Date(birthDate);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age + ' anos';
}

function toggleEditMode() {
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    
    if (viewMode.style.display === 'none') {
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        if (petData) {
            loadPetForEdit();
        }
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}

function loadPetForEdit() {
    if (!petData) return;
    
    document.getElementById('editPetId').value = petData.id;
    document.getElementById('editPetName').value = petData.name || '';
    document.getElementById('editPetSpecies').value = petData.species || 'Cachorro';
    document.getElementById('editPetBreed').value = petData.breed || '';
    document.getElementById('editPetGender').value = petData.gender || 'unknown';
    document.getElementById('editPetBirthDate').value = petData.birth_date || '';
    document.getElementById('editPetWeight').value = petData.weight || '';
    document.getElementById('editPetColor').value = petData.color || '';
    document.getElementById('editPetMicrochip').value = petData.microchip || '';
    document.getElementById('editPetNotes').value = petData.notes || '';
}

document.getElementById('editPetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const formData = {
        name: document.getElementById('editPetName').value.trim(),
        species: document.getElementById('editPetSpecies').value,
        breed: document.getElementById('editPetBreed').value.trim() || null,
        gender: document.getElementById('editPetGender').value,
        birth_date: document.getElementById('editPetBirthDate').value || null,
        weight: document.getElementById('editPetWeight').value || null,
        color: document.getElementById('editPetColor').value.trim() || null,
        microchip: document.getElementById('editPetMicrochip').value.trim() || null,
        notes: document.getElementById('editPetNotes').value.trim() || null
    };

    const submitBtn = this.querySelector('button[type="submit"]');
    const spinner = submitBtn.querySelector('.spinner-border');
    const errorDiv = document.getElementById('editPetError');
    
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    errorDiv.classList.add('d-none');

    apiRequest(`/v1/pets/${petId}`, {
        method: 'PUT',
        body: JSON.stringify(formData)
    })
    .then(data => {
        if (data.success) {
            showAlert('Pet atualizado com sucesso!', 'success');
            petData = data.data;
            renderPetInfo(petData);
            toggleEditMode();
            cache.clear(`/v1/pets/${petId}`);
        } else {
            throw new Error(data.error || 'Erro ao atualizar pet');
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
</script>

