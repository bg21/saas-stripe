<?php
/**
 * View de Detalhes da Fatura
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/invoices">Faturas</a></li>
            <li class="breadcrumb-item active">Detalhes</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>
    <div id="loadingInvoice" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div id="invoiceDetails" style="display: none;">
        <!-- Informações da Fatura -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Informações da Fatura</h5>
                <div id="invoiceActions">
                </div>
            </div>
            <div class="card-body" id="invoiceInfo">
            </div>
        </div>

        <!-- Itens da Fatura -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Itens da Fatura</h5>
            </div>
            <div class="card-body">
                <div id="invoiceItemsList"></div>
            </div>
        </div>

        <!-- Resumo Financeiro -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Resumo Financeiro</h5>
                    </div>
                    <div class="card-body" id="invoiceSummary">
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Informações de Pagamento</h5>
                    </div>
                    <div class="card-body" id="invoicePayment">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
const invoiceId = urlParams.get('id');

if (!invoiceId) {
    window.location.href = '/invoices';
}

let invoiceData = null;

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadInvoiceDetails();
    }, 100);
});

async function loadInvoiceDetails() {
    try {
        const invoice = await apiRequest(`/v1/invoices/${invoiceId}`);
        invoiceData = invoice.data;
        renderInvoiceInfo(invoiceData);
        renderInvoiceItems(invoiceData.lines?.data || []);
        renderInvoiceSummary(invoiceData);
        renderInvoicePayment(invoiceData);

        document.getElementById('loadingInvoice').style.display = 'none';
        document.getElementById('invoiceDetails').style.display = 'block';
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
    }
}

function renderInvoiceInfo(invoice) {
    const statusBadge = {
        'paid': 'bg-success',
        'open': 'bg-warning',
        'draft': 'bg-secondary',
        'void': 'bg-danger',
        'uncollectible': 'bg-danger'
    }[invoice.status] || 'bg-secondary';
    
    document.getElementById('invoiceInfo').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> <code>${invoice.id}</code></p>
                <p><strong>Número:</strong> ${invoice.number || '-'}</p>
                <p><strong>Cliente:</strong> ${invoice.customer ? `<a href="/customer-details?id=${invoice.customer}">${invoice.customer}</a>` : '-'}</p>
                <p><strong>Status:</strong> <span class="badge ${statusBadge}">${invoice.status}</span></p>
            </div>
            <div class="col-md-6">
                <p><strong>Data de Emissão:</strong> ${formatDate(invoice.created)}</p>
                <p><strong>Data de Vencimento:</strong> ${invoice.due_date ? formatDate(invoice.due_date) : '-'}</p>
                <p><strong>Pago em:</strong> ${invoice.status_transitions?.paid_at ? formatDate(invoice.status_transitions.paid_at) : '-'}</p>
                <p><strong>Assinatura:</strong> ${invoice.subscription ? `<a href="/subscription-details?id=${invoice.subscription}">${invoice.subscription}</a>` : '-'}</p>
            </div>
        </div>
    `;
    
    // Mostrar botões se disponíveis
    const actionsDiv = document.getElementById('invoiceActions');
    let actionsHtml = '';
    if (invoice.hosted_invoice_url) {
        actionsHtml += `<a href="${invoice.hosted_invoice_url}" target="_blank" class="btn btn-sm btn-outline-primary me-2">
            <i class="bi bi-box-arrow-up-right"></i> Ver no Stripe
        </a>`;
    }
    if (invoice.invoice_pdf) {
        actionsHtml += `<a href="${invoice.invoice_pdf}" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download"></i> Baixar PDF
        </a>`;
    }
    actionsDiv.innerHTML = actionsHtml;
}

function renderInvoiceItems(items) {
    const container = document.getElementById('invoiceItemsList');
    if (items.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhum item encontrado</p>';
        return;
    }
    container.innerHTML = `
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Quantidade</th>
                        <th>Valor Unitário</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${items.map(item => `
                        <tr>
                            <td>${item.description || '-'}</td>
                            <td>${item.quantity || 1}</td>
                            <td>${formatCurrency(item.price?.unit_amount || item.amount, item.currency || 'BRL')}</td>
                            <td><strong>${formatCurrency(item.amount, item.currency || 'BRL')}</strong></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function renderInvoiceSummary(invoice) {
    document.getElementById('invoiceSummary').innerHTML = `
        <p><strong>Subtotal:</strong> ${formatCurrency(invoice.subtotal, invoice.currency)}</p>
        ${invoice.tax ? `<p><strong>Impostos:</strong> ${formatCurrency(invoice.tax, invoice.currency)}</p>` : ''}
        ${invoice.discount ? `<p><strong>Desconto:</strong> -${formatCurrency(invoice.discount, invoice.currency)}</p>` : ''}
        <hr>
        <p class="h5"><strong>Total:</strong> ${formatCurrency(invoice.total, invoice.currency)}</p>
        <p><strong>Valor Pago:</strong> ${formatCurrency(invoice.amount_paid, invoice.currency)}</p>
        <p><strong>Valor Restante:</strong> ${formatCurrency(invoice.amount_remaining, invoice.currency)}</p>
    `;
}

function renderInvoicePayment(invoice) {
    document.getElementById('invoicePayment').innerHTML = `
        ${invoice.payment_intent ? `
            <p><strong>Payment Intent:</strong> <code>${invoice.payment_intent}</code></p>
        ` : ''}
        ${invoice.charge ? `
            <p><strong>Cobrança:</strong> <code>${invoice.charge}</code></p>
        ` : ''}
        ${invoice.payment_method ? `
            <p><strong>Método de Pagamento:</strong> ${invoice.payment_method}</p>
        ` : ''}
        ${invoice.default_payment_method ? `
            <p><strong>Método Padrão:</strong> ${invoice.default_payment_method}</p>
        ` : ''}
        ${invoice.billing_reason ? `
            <p><strong>Motivo:</strong> ${invoice.billing_reason}</p>
        ` : ''}
    `;
}
</script>

