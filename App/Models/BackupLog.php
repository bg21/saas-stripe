<?php

namespace App\Models;

/**
 * Model para gerenciar logs de backup
 */
class BackupLog extends BaseModel
{
    protected string $table = 'backup_logs';

    /**
     * Cria um novo registro de backup
     */
    public function create(array $data): int
    {
        return $this->insert($data);
    }
}

