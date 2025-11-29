<?php

namespace App\Handlers;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Level;
use App\Utils\Database;
use PDO;

/**
 * Handler customizado do Monolog para salvar logs no banco de dados
 * 
 * Salva logs de forma assíncrona para não bloquear a aplicação
 */
class DatabaseLogHandler extends AbstractProcessingHandler
{
    private PDO $db;
    private bool $enabled;
    private array $buffer = [];
    private int $bufferSize;
    private int $flushInterval;

    public function __construct(
        Level $level = Level::Debug,
        bool $bubble = true,
        int $bufferSize = 10,
        int $flushInterval = 5
    ) {
        parent::__construct($level, $bubble);
        
        $this->db = Database::getInstance();
        $this->enabled = \Config::get('LOG_DATABASE_ENABLED', 'true') === 'true';
        $this->bufferSize = $bufferSize;
        $this->flushInterval = $flushInterval;
        
        // Registra função de shutdown para salvar logs pendentes
        register_shutdown_function([$this, 'flush']);
    }

    /**
     * Processa e salva o log
     */
    protected function write(LogRecord $record): void
    {
        if (!$this->enabled) {
            return;
        }

        // Prepara dados do log
        $logData = [
            'request_id' => $record->context['request_id'] ?? null,
            'level' => $record->level->getName(),
            'level_value' => $record->level->value,
            'message' => $record->message,
            'context' => !empty($record->context) ? json_encode($record->context) : null,
            'channel' => $record->channel,
            'tenant_id' => $record->context['tenant_id'] ?? null,
            'user_id' => $record->context['user_id'] ?? null,
            'created_at' => $record->datetime->format('Y-m-d H:i:s')
        ];

        // Adiciona ao buffer
        $this->buffer[] = $logData;

        // Se o buffer atingir o tamanho máximo, salva imediatamente
        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }
    }

    /**
     * Salva logs do buffer no banco de dados
     * Executado de forma assíncrona via register_shutdown_function
     */
    public function flush(): void
    {
        if (empty($this->buffer) || !$this->enabled) {
            return;
        }

        try {
            $sql = "INSERT INTO application_logs 
                    (request_id, level, level_value, message, context, channel, tenant_id, user_id, created_at) 
                    VALUES 
                    (:request_id, :level, :level_value, :message, :context, :channel, :tenant_id, :user_id, :created_at)";

            $stmt = $this->db->prepare($sql);

            foreach ($this->buffer as $logData) {
                try {
                    $stmt->execute($logData);
                } catch (\PDOException $e) {
                    // Ignora erros de inserção (não deve quebrar a aplicação)
                    // Em produção, pode logar em arquivo se necessário
                    error_log("Erro ao salvar log no banco: " . $e->getMessage());
                }
            }

            // Limpa buffer após salvar
            $this->buffer = [];
        } catch (\Exception $e) {
            // Não deve quebrar a aplicação se o log falhar
            error_log("Erro ao salvar logs no banco: " . $e->getMessage());
        }
    }
}

