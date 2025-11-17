<?php
/**
 * View de Cancelamento do Checkout
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Cancelado - Sistema SaaS</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-body py-5">
                        <div class="mb-4">
                            <i class="bi bi-x-circle-fill text-warning" style="font-size: 4rem;"></i>
                        </div>
                        <h2 class="mb-3">Checkout Cancelado</h2>
                        <p class="text-muted mb-4">Você cancelou o processo de pagamento. Nenhuma cobrança foi realizada.</p>
                        
                        <div class="d-grid gap-2">
                            <a href="/" class="btn btn-primary">
                                <i class="bi bi-arrow-left"></i> Voltar e Tentar Novamente
                            </a>
                            <a href="/login" class="btn btn-outline-secondary">
                                Fazer Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

