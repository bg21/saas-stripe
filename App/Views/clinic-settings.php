<?php
/**
 * View de Configurações da Clínica
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-gear"></i> Configurações da Clínica</h1>
    </div>

    <div id="alertContainer"></div>
    <div id="loadingConfig" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
    </div>

    <div id="configDetails" style="display: none;">
        <form id="configForm">
            <!-- Horários de Funcionamento -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Horários de Funcionamento</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        $days = [
                            'monday' => 'Segunda-feira',
                            'tuesday' => 'Terça-feira',
                            'wednesday' => 'Quarta-feira',
                            'thursday' => 'Quinta-feira',
                            'friday' => 'Sexta-feira',
                            'saturday' => 'Sábado',
                            'sunday' => 'Domingo'
                        ];
                        foreach ($days as $dayKey => $dayName):
                        ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo $dayName; ?></label>
                            <div class="row">
                                <div class="col-6">
                                    <input type="time" class="form-control" name="opening_time_<?php echo $dayKey; ?>" id="opening_<?php echo $dayKey; ?>">
                                </div>
                                <div class="col-6">
                                    <input type="time" class="form-control" name="closing_time_<?php echo $dayKey; ?>" id="closing_<?php echo $dayKey; ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Configurações de Agendamento -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Configurações de Agendamento</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duração Padrão de Consulta (minutos) *</label>
                            <input type="number" class="form-control" name="default_appointment_duration" id="defaultAppointmentDuration" required min="15" max="240" step="15" value="30">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Intervalo entre Horários (minutos) *</label>
                            <input type="number" class="form-control" name="time_slot_interval" id="timeSlotInterval" required min="5" max="60" step="5" value="15">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="allow_online_booking" id="allowOnlineBooking" value="1">
                                <label class="form-check-label" for="allowOnlineBooking">
                                    Permitir agendamento online
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="require_confirmation" id="requireConfirmation" value="1">
                                <label class="form-check-label" for="requireConfirmation">
                                    Exigir confirmação de agendamento
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Horas Mínimas para Cancelamento</label>
                        <input type="number" class="form-control" name="cancellation_hours" id="cancellationHours" min="0" max="168" value="24">
                        <small class="form-text text-muted">Número de horas antes do agendamento que permite cancelamento</small>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                    Salvar Configurações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let configData = null;

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadConfig();
    }, 100);
    
    document.getElementById('configForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!e.target.checkValidity()) {
            e.target.classList.add('was-validated');
            return;
        }
        
        const formData = new FormData(e.target);
        const data = {};
        
        // Processa todos os campos
        for (let [key, value] of formData.entries()) {
            if (key.startsWith('opening_time_') || key.startsWith('closing_time_')) {
                data[key] = value || null;
            } else if (key === 'allow_online_booking' || key === 'require_confirmation') {
                data[key] = value === '1' ? 1 : 0;
            } else if (value !== '') {
                data[key] = value;
            }
        }
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const spinner = submitBtn.querySelector('.spinner-border');
        
        submitBtn.disabled = true;
        spinner.classList.remove('d-none');
        
        try {
            const response = await apiRequest('/v1/clinic/configuration', {
                method: 'PUT',
                body: JSON.stringify(data)
            });
            
            if (response.success) {
                showAlert('Configurações salvas com sucesso!', 'success');
                configData = response.data;
                loadConfig();
            }
        } catch (error) {
            showAlert(error.message, 'danger');
        } finally {
            submitBtn.disabled = false;
            spinner.classList.add('d-none');
        }
    });
});

async function loadConfig() {
    try {
        document.getElementById('loadingConfig').style.display = 'block';
        document.getElementById('configDetails').style.display = 'none';
        
        const response = await apiRequest('/v1/clinic/configuration');
        configData = response.data;
        
        if (configData) {
            // Preenche formulário
            document.getElementById('defaultAppointmentDuration').value = configData.default_appointment_duration || 30;
            document.getElementById('timeSlotInterval').value = configData.time_slot_interval || 15;
            document.getElementById('cancellationHours').value = configData.cancellation_hours || 24;
            document.getElementById('allowOnlineBooking').checked = configData.allow_online_booking == 1;
            document.getElementById('requireConfirmation').checked = configData.require_confirmation == 1;
            
            // Preenche horários
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            days.forEach(day => {
                const openingField = document.getElementById(`opening_${day}`);
                const closingField = document.getElementById(`closing_${day}`);
                if (openingField && configData[`opening_time_${day}`]) {
                    openingField.value = configData[`opening_time_${day}`].substring(0, 5);
                }
                if (closingField && configData[`closing_time_${day}`]) {
                    closingField.value = configData[`closing_time_${day}`].substring(0, 5);
                }
            });
        }
        
        document.getElementById('loadingConfig').style.display = 'none';
        document.getElementById('configDetails').style.display = 'block';
    } catch (error) {
        console.error('Erro ao carregar configurações:', error);
        showAlert('Erro ao carregar configurações: ' + error.message, 'danger');
    }
}
</script>

