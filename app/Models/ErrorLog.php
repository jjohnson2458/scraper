<?php

namespace App\Models;

use App\Core\BaseModel;

/**
 * ErrorLog Model
 *
 * Stores application errors in the database and optionally
 * triggers email notifications.
 *
 * @package    ClaudeScraper
 * @subpackage Models
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class ErrorLog extends BaseModel
{
    /** @var string The database table */
    protected string $table = 'error_log';

    /** @var array<string> Mass-assignable columns */
    protected array $fillable = [
        'level', 'message', 'context', 'file', 'line',
        'trace', 'user_id', 'url', 'ip_address'
    ];

    /**
     * Log an error to the database.
     *
     * @param string      $message The error message.
     * @param string      $level   The error level.
     * @param array       $context Additional context.
     * @param string|null $file    The source file.
     * @param int|null    $line    The source line.
     * @return int|string The error log ID.
     */
    public function log(
        string $message,
        string $level = 'error',
        array $context = [],
        ?string $file = null,
        ?int $line = null
    ): int|string {
        return $this->create([
            'level' => $level,
            'message' => $message,
            'context' => json_encode($context),
            'file' => $file,
            'line' => $line,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
                ? json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5))
                : null,
            'user_id' => $_SESSION['user_id'] ?? null,
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    /**
     * Get recent errors.
     *
     * @param int    $limit Number of records.
     * @param string $level Filter by level.
     * @return array
     */
    public function getRecent(int $limit = 50, string $level = ''): array
    {
        $where = $level ? "WHERE level = :level" : "";
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} {$where} ORDER BY created_at DESC LIMIT :limit"
        );
        if ($level) {
            $stmt->bindValue(':level', $level);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
