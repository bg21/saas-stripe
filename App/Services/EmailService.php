<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use App\Services\Logger;

/**
 * Serviço para envio de emails usando PHPMailer
 * Integrado com eventos do Stripe para notificações automáticas
 */
class EmailService
{
    /**
     * @var string Host SMTP
     */
    private string $host;

    /**
     * @var int Porta SMTP
     */
    private int $port;

    /**
     * @var string Usuário SMTP
     */
    private string $username;

    /**
     * @var string Senha SMTP
     */
    private string $password;

    /**
     * @var string Criptografia SMTP
     */
    private string $encryption;

    /**
     * @var string Endereço de email remetente
     */
    private string $fromAddress;

    /**
     * @var string Nome do remetente
     */
    private string $fromName;

    /**
     * @var string Email de suporte
     */
    private string $supportEmail;

    /**
     * @var bool Ativa o modo de debug
     */
    private bool $debug = false;

    /**
     * @var string Caminho para templates de email
     */
    private string $templatesPath;

    /**
     * Construtor
     * 
     * @param bool $debug Ativa o modo de debug
     */
    public function __construct(bool $debug = false)
    {
        // Carrega configurações de email
        $emailConfig = require dirname(dirname(__DIR__)) . '/config/email.php';
        $env = $_ENV['APP_ENV'] ?? 'development';
        
        $config = $emailConfig[$env] ?? $emailConfig['development'];
        
        // Permite sobrescrever com variáveis de ambiente
        if (isset($_ENV['MAIL_HOST'])) $config['host'] = $_ENV['MAIL_HOST'];
        if (isset($_ENV['MAIL_PORT'])) $config['port'] = (int)$_ENV['MAIL_PORT'];
        if (isset($_ENV['MAIL_USERNAME'])) $config['username'] = $_ENV['MAIL_USERNAME'];
        if (isset($_ENV['MAIL_PASSWORD'])) $config['password'] = $_ENV['MAIL_PASSWORD'];
        if (isset($_ENV['MAIL_ENCRYPTION'])) $config['encryption'] = $_ENV['MAIL_ENCRYPTION'];
        if (isset($_ENV['MAIL_FROM_ADDRESS'])) $config['from_email'] = $_ENV['MAIL_FROM_ADDRESS'];
        if (isset($_ENV['MAIL_FROM_NAME'])) $config['from_name'] = $_ENV['MAIL_FROM_NAME'];
        if (isset($_ENV['SUPORTE_EMAIL'])) $config['support_email'] = $_ENV['SUPORTE_EMAIL'];
        
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->encryption = $config['encryption'];
        $this->fromAddress = $config['from_email'];
        $this->fromName = $config['from_name'];
        $this->supportEmail = $config['support_email'];
        $this->debug = $debug;
        
        // Define caminho dos templates
        $this->templatesPath = dirname(__DIR__) . '/Templates/Email';
        
        $this->logEmailConfig();
    }

    /**
     * Registra as configurações de email nos logs (sem senha)
     */
    private function logEmailConfig(): void
    {
        Logger::debug("EmailService inicializado", [
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'encryption' => $this->encryption,
            'from_email' => $this->fromAddress,
            'from_name' => $this->fromName,
            'support_email' => $this->supportEmail
        ]);
    }

    /**
     * Define o modo de debug
     * 
     * @param bool $debug
     * @return self
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Configura o PHPMailer
     *
     * @return PHPMailer
     * @throws Exception
     */
    private function configurarMailer(): PHPMailer
    {
        $mail = new PHPMailer(true); // true para habilitar exceções
        
        try {
            // Nível de debug
            $mail->SMTPDebug = $this->debug ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
            
            // Configurações do servidor
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            $mail->SMTPSecure = $this->encryption === 'tls' 
                ? PHPMailer::ENCRYPTION_STARTTLS 
                : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $this->port;
            $mail->CharSet = 'UTF-8';
            
            // Remetente
            $mail->setFrom($this->fromAddress, $this->fromName);
            
            return $mail;
        } catch (Exception $e) {
            Logger::error("Erro ao configurar PHPMailer", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Envia um email
     *
     * @param string $destinatario Email do destinatário
     * @param string $assunto Assunto do email
     * @param string $corpo Corpo do email (HTML)
     * @param string $nomeDestinatario Nome do destinatário (opcional)
     * @param array $anexos Lista de anexos (opcional)
     * @return bool
     */
    public function enviar(
        string $destinatario,
        string $assunto,
        string $corpo,
        string $nomeDestinatario = '',
        array $anexos = []
    ): bool {
        Logger::info("Iniciando envio de email", [
            'to' => $destinatario,
            'subject' => $assunto
        ]);
        
        // Modo de desenvolvimento - apenas loga o email
        if ($_ENV['APP_ENV'] === 'development' && ($_ENV['MAIL_DRIVER'] ?? 'smtp') === 'log') {
            $this->logEmailDevelopment($destinatario, $assunto, $corpo, $nomeDestinatario);
            return true;
        }
        
        try {
            $mail = $this->configurarMailer();
            
            // Destinatário
            $mail->addAddress($destinatario, $nomeDestinatario);
            
            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body = $corpo;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $corpo));
            
            // Anexos
            foreach ($anexos as $anexo) {
                if (isset($anexo['path']) && file_exists($anexo['path'])) {
                    $nome = $anexo['name'] ?? basename($anexo['path']);
                    $mail->addAttachment($anexo['path'], $nome);
                    Logger::debug("Anexo adicionado ao email", ['file' => $nome]);
                }
            }
            
            // Envia o email
            $enviado = $mail->send();
            
            if ($enviado) {
                Logger::info("Email enviado com sucesso", [
                    'to' => $destinatario,
                    'subject' => $assunto
                ]);
            } else {
                Logger::error("Falha ao enviar email", [
                    'to' => $destinatario,
                    'error' => $mail->ErrorInfo
                ]);
            }
            
            return $enviado;
        } catch (Exception $e) {
            Logger::error("Exceção ao enviar email", [
                'to' => $destinatario,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Loga o email em modo de desenvolvimento
     */
    private function logEmailDevelopment(string $destinatario, string $assunto, string $corpo, string $nomeDestinatario): void
    {
        $logDir = dirname(dirname(dirname(__DIR__))) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $emailLogFile = $logDir . '/emails-' . date('Y-m-d') . '.log';
        
        $logMessage = "=== EMAIL ENVIADO (MODO DESENVOLVIMENTO) ===\n";
        $logMessage .= "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "Para: " . ($nomeDestinatario ? "{$nomeDestinatario} <{$destinatario}>" : $destinatario) . "\n";
        $logMessage .= "De: {$this->fromName} <{$this->fromAddress}>\n";
        $logMessage .= "Assunto: {$assunto}\n";
        $logMessage .= "Corpo:\n{$corpo}\n";
        $logMessage .= "==========================================\n\n";
        
        file_put_contents($emailLogFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        Logger::info("Email logado em modo desenvolvimento", ['file' => $emailLogFile]);
    }

    /**
     * Renderiza um template de email
     *
     * @param string $template Nome do template (sem extensão)
     * @param array $data Dados para o template
     * @return string HTML renderizado
     */
    private function renderTemplate(string $template, array $data = []): string
    {
        $templateFile = $this->templatesPath . '/' . $template . '.html';
        
        if (!file_exists($templateFile)) {
            Logger::warning("Template de email não encontrado", ['template' => $template]);
            return $this->getDefaultTemplate($data);
        }
        
        // Extrai variáveis para o template
        extract($data);
        
        // Captura o conteúdo do template
        ob_start();
        include $templateFile;
        $content = ob_get_clean();
        
        return $content;
    }

    /**
     * Template padrão caso o template específico não exista
     */
    private function getDefaultTemplate(array $data): string
    {
        $subject = $data['subject'] ?? 'Notificação';
        $message = $data['message'] ?? 'Você recebeu uma notificação do sistema.';
        $appName = $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos';
        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$subject}</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #4CAF50;'>{$subject}</h1>
                <p>{$message}</p>
                <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                <p style='font-size: 12px; color: #777;'>
                    Esta é uma mensagem automática de {$appName}. Por favor, não responda a este email.
                </p>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Envia notificação de pagamento falhado
     *
     * @param array $invoice Dados da fatura do Stripe
     * @param array $customer Dados do customer do banco
     * @return bool
     */
    public function enviarNotificacaoPagamentoFalhado(array $invoice, array $customer): bool
    {
        $assunto = '⚠️ Falha no pagamento da sua assinatura';
        
        $data = [
            'customer_name' => $customer['name'] ?? 'Cliente',
            'customer_email' => $customer['email'],
            'invoice_id' => $invoice['id'] ?? 'N/A',
            'amount_due' => isset($invoice['amount_due']) ? number_format($invoice['amount_due'] / 100, 2, ',', '.') : '0,00',
            'currency' => strtoupper($invoice['currency'] ?? 'BRL'),
            'attempt_count' => $invoice['attempt_count'] ?? 0,
            'next_payment_attempt' => isset($invoice['next_payment_attempt']) 
                ? date('d/m/Y H:i', $invoice['next_payment_attempt']) 
                : null,
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $this->supportEmail,
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ];
        
        $corpo = $this->renderTemplate('payment_failed', $data);
        
        return $this->enviar(
            $customer['email'],
            $assunto,
            $corpo,
            $customer['name'] ?? ''
        );
    }

    /**
     * Envia notificação de assinatura cancelada
     *
     * @param array $subscription Dados da assinatura
     * @param array $customer Dados do customer
     * @return bool
     */
    public function enviarNotificacaoAssinaturaCancelada(array $subscription, array $customer): bool
    {
        $assunto = 'Assinatura cancelada';
        
        $data = [
            'customer_name' => $customer['name'] ?? 'Cliente',
            'customer_email' => $customer['email'],
            'subscription_id' => $subscription['stripe_subscription_id'] ?? 'N/A',
            'canceled_at' => isset($subscription['canceled_at']) 
                ? date('d/m/Y H:i', strtotime($subscription['canceled_at'])) 
                : date('d/m/Y H:i'),
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $this->supportEmail,
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ];
        
        $corpo = $this->renderTemplate('subscription_canceled', $data);
        
        return $this->enviar(
            $customer['email'],
            $assunto,
            $corpo,
            $customer['name'] ?? ''
        );
    }

    /**
     * Envia notificação de nova assinatura criada
     *
     * @param array $subscription Dados da assinatura
     * @param array $customer Dados do customer
     * @return bool
     */
    public function enviarNotificacaoAssinaturaCriada(array $subscription, array $customer): bool
    {
        $assunto = '✅ Bem-vindo! Sua assinatura foi ativada';
        
        $data = [
            'customer_name' => $customer['name'] ?? 'Cliente',
            'customer_email' => $customer['email'],
            'subscription_id' => $subscription['stripe_subscription_id'] ?? 'N/A',
            'plan_name' => $subscription['plan_id'] ?? 'Plano',
            'amount' => isset($subscription['amount']) ? number_format($subscription['amount'], 2, ',', '.') : '0,00',
            'currency' => strtoupper($subscription['currency'] ?? 'BRL'),
            'current_period_end' => isset($subscription['current_period_end']) 
                ? date('d/m/Y', strtotime($subscription['current_period_end'])) 
                : null,
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $this->supportEmail,
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ];
        
        $corpo = $this->renderTemplate('subscription_created', $data);
        
        return $this->enviar(
            $customer['email'],
            $assunto,
            $corpo,
            $customer['name'] ?? ''
        );
    }

    /**
     * Envia notificação de trial terminando
     *
     * @param array $subscription Dados da assinatura
     * @param array $customer Dados do customer
     * @return bool
     */
    public function enviarNotificacaoTrialTerminando(array $subscription, array $customer): bool
    {
        $assunto = '⏰ Seu período de trial está terminando em breve';
        
        $trialEnd = isset($subscription['trial_end']) 
            ? strtotime($subscription['trial_end']) 
            : (isset($subscription['current_period_end']) ? strtotime($subscription['current_period_end']) : null);
        
        $data = [
            'customer_name' => $customer['name'] ?? 'Cliente',
            'customer_email' => $customer['email'],
            'subscription_id' => $subscription['stripe_subscription_id'] ?? 'N/A',
            'trial_end' => $trialEnd ? date('d/m/Y H:i', $trialEnd) : null,
            'days_remaining' => $trialEnd ? ceil(($trialEnd - time()) / 86400) : 0,
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $this->supportEmail,
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ];
        
        $corpo = $this->renderTemplate('trial_ending', $data);
        
        return $this->enviar(
            $customer['email'],
            $assunto,
            $corpo,
            $customer['name'] ?? ''
        );
    }

    /**
     * Envia notificação de fatura próxima
     *
     * @param array $invoice Dados da fatura do Stripe
     * @param array $customer Dados do customer
     * @return bool
     */
    public function enviarNotificacaoFaturaProxima(array $invoice, array $customer): bool
    {
        $assunto = '📋 Fatura próxima - Confirme seu método de pagamento';
        
        $data = [
            'customer_name' => $customer['name'] ?? 'Cliente',
            'customer_email' => $customer['email'],
            'invoice_id' => $invoice['id'] ?? 'N/A',
            'amount_due' => isset($invoice['amount_due']) ? number_format($invoice['amount_due'] / 100, 2, ',', '.') : '0,00',
            'currency' => strtoupper($invoice['currency'] ?? 'BRL'),
            'due_date' => isset($invoice['due_date']) 
                ? date('d/m/Y', $invoice['due_date']) 
                : (isset($invoice['period_end']) ? date('d/m/Y', $invoice['period_end']) : null),
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $this->supportEmail,
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ];
        
        $corpo = $this->renderTemplate('invoice_upcoming', $data);
        
        return $this->enviar(
            $customer['email'],
            $assunto,
            $corpo,
            $customer['name'] ?? ''
        );
    }

    /**
     * Envia notificação de disputa criada
     *
     * @param array $dispute Dados da disputa do Stripe
     * @param array $customer Dados do customer
     * @return bool
     */
    public function enviarNotificacaoDisputaCriada(array $dispute, array $customer): bool
    {
        $assunto = '⚠️ Disputa de pagamento registrada';
        
        $data = [
            'customer_name' => $customer['name'] ?? 'Cliente',
            'customer_email' => $customer['email'],
            'dispute_id' => $dispute['id'] ?? 'N/A',
            'amount' => isset($dispute['amount']) ? number_format($dispute['amount'] / 100, 2, ',', '.') : '0,00',
            'currency' => strtoupper($dispute['currency'] ?? 'BRL'),
            'reason' => $dispute['reason'] ?? 'N/A',
            'status' => $dispute['status'] ?? 'warning_needs_response',
            'evidence_due_by' => isset($dispute['evidence_due_by']) 
                ? date('d/m/Y H:i', $dispute['evidence_due_by']) 
                : null,
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $this->supportEmail,
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ];
        
        $corpo = $this->renderTemplate('dispute_created', $data);
        
        return $this->enviar(
            $customer['email'],
            $assunto,
            $corpo,
            $customer['name'] ?? ''
        );
    }

    /**
     * Envia notificação de assinatura reativada
     *
     * @param array $subscription Dados da assinatura
     * @param array $customer Dados do customer
     * @return bool
     */
    public function enviarNotificacaoAssinaturaReativada(array $subscription, array $customer): bool
    {
        $assunto = '✅ Sua assinatura foi reativada com sucesso!';
        
        $data = [
            'customer_name' => $customer['name'] ?? 'Cliente',
            'customer_email' => $customer['email'],
            'subscription_id' => $subscription['stripe_subscription_id'] ?? 'N/A',
            'plan_name' => $subscription['plan_id'] ?? 'Plano',
            'current_period_end' => isset($subscription['current_period_end']) 
                ? date('d/m/Y', strtotime($subscription['current_period_end'])) 
                : null,
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $this->supportEmail,
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ];
        
        $corpo = $this->renderTemplate('subscription_reactivated', $data);
        
        return $this->enviar(
            $customer['email'],
            $assunto,
            $corpo,
            $customer['name'] ?? ''
        );
    }

    /**
     * Envia email de redefinição de senha
     *
     * @param string $destinatario Email do destinatário
     * @param string $nome Nome do destinatário
     * @param string $linkResetSenha Link para redefinição de senha
     * @return bool
     */
    public function enviarEmailResetSenha(string $destinatario, string $nome, string $linkResetSenha): bool
    {
        $assunto = '🔐 Redefinição de Senha - Sistema de Pagamentos';
        
        $data = [
            'customer_name' => $nome,
            'customer_email' => $destinatario,
            'link_reset_senha' => $linkResetSenha,
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $this->supportEmail,
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ];
        
        $corpo = $this->renderTemplate('password_reset', $data);
        
        return $this->enviar($destinatario, $assunto, $corpo, $nome);
    }

    /**
     * Envia email de confirmação de cadastro
     *
     * @param string $destinatario Email do destinatário
     * @param string $nome Nome do destinatário
     * @param string $linkConfirmacao Link para confirmação do email
     * @return bool
     */
    public function enviarEmailConfirmacao(string $destinatario, string $nome, string $linkConfirmacao): bool
    {
        $assunto = '✉️ Confirme seu email - Sistema de Pagamentos';
        
        $data = [
            'customer_name' => $nome,
            'customer_email' => $destinatario,
            'link_confirmacao' => $linkConfirmacao,
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $this->supportEmail,
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ];
        
        $corpo = $this->renderTemplate('email_confirmation', $data);
        
        return $this->enviar($destinatario, $assunto, $corpo, $nome);
    }

    /**
     * Envia notificação de novo login na conta
     *
     * @param string $destinatario Email do destinatário
     * @param string $nome Nome do destinatário
     * @param string $dataHora Data e hora do login
     * @param string $ip Endereço IP de onde foi feito o login
     * @param string|null $localizacao Localização aproximada (opcional)
     * @param string|null $dispositivo Dispositivo usado (opcional)
     * @return bool
     */
    public function enviarNotificacaoLogin(
        string $destinatario, 
        string $nome, 
        string $dataHora, 
        string $ip,
        ?string $localizacao = null,
        ?string $dispositivo = null
    ): bool {
        $assunto = '🔒 Novo Login Detectado - Notificação de Segurança';
        
        $data = [
            'customer_name' => $nome,
            'customer_email' => $destinatario,
            'data_hora' => $dataHora,
            'ip' => $ip,
            'localizacao' => $localizacao,
            'dispositivo' => $dispositivo,
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $this->supportEmail,
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ];
        
        $corpo = $this->renderTemplate('login_notification', $data);
        
        return $this->enviar($destinatario, $assunto, $corpo, $nome);
    }
}

