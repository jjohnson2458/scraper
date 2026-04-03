<?php

namespace App\Core;

require_once __DIR__ . '/../../config/database.php';

/**
 * Base Model
 *
 * Provides common database operations (CRUD) for all models.
 * Each child model defines its own table and fillable fields.
 *
 * @package    ClaudeScraper
 * @subpackage Core
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
abstract class BaseModel
{
    /** @var string The database table name */
    protected string $table;

    /** @var string The primary key column */
    protected string $primaryKey = 'id';

    /** @var array<string> Columns that can be mass-assigned */
    protected array $fillable = [];

    /** @var PDO The database connection */
    protected \PDO $db;

    /**
     * BaseModel constructor.
     */
    public function __construct()
    {
        $this->db = \Database::getConnection();
    }

    /**
     * Find a record by its primary key.
     *
     * @param int|string $id The primary key value.
     * @return array|null The record or null.
     */
    public function find(int|string $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all records, optionally ordered.
     *
     * @param string $orderBy Column to order by.
     * @param string $direction Sort direction (ASC or DESC).
     * @return array
     */
    public function all(string $orderBy = 'id', string $direction = 'DESC'): array
    {
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $stmt = $this->db->query(
            "SELECT * FROM {$this->table} ORDER BY {$orderBy} {$direction}"
        );
        return $stmt->fetchAll();
    }

    /**
     * Get paginated records.
     *
     * @param int    $page     Current page number (1-based).
     * @param int    $perPage  Records per page.
     * @param string $orderBy  Column to order by.
     * @param string $direction Sort direction.
     * @return array{data: array, total: int, page: int, perPage: int, lastPage: int}
     */
    public function paginate(int $page = 1, int $perPage = 20, string $orderBy = 'id', string $direction = 'DESC'): array
    {
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->db->query("SELECT COUNT(*) FROM {$this->table}");
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} ORDER BY {$orderBy} {$direction} LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Find records matching conditions.
     *
     * @param array  $conditions Associative array of column => value.
     * @param string $orderBy    Column to order by.
     * @param string $direction  Sort direction.
     * @return array
     */
    public function where(array $conditions, string $orderBy = 'id', string $direction = 'DESC'): array
    {
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $clauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $clauses[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $whereClause = implode(' AND ', $clauses);
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$whereClause} ORDER BY {$orderBy} {$direction}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Create a new record.
     *
     * @param array $data Associative array of column => value.
     * @return int|string The new record's primary key.
     */
    public function create(array $data): int|string
    {
        $data = $this->filterFillable($data);
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));

        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})"
        );
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    /**
     * Update a record by its primary key.
     *
     * @param int|string $id   The primary key value.
     * @param array      $data Associative array of column => value.
     * @return bool
     */
    public function update(int|string $id, array $data): bool
    {
        $data = $this->filterFillable($data);
        $setClauses = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $data['id'] = $id;

        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET {$setClauses} WHERE {$this->primaryKey} = :id"
        );
        return $stmt->execute($data);
    }

    /**
     * Delete a record by its primary key.
     *
     * @param int|string $id The primary key value.
     * @return bool
     */
    public function delete(int|string $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Search records by a keyword across specified columns.
     *
     * @param string $keyword The search term.
     * @param array  $columns Columns to search in.
     * @param int    $page    Current page.
     * @param int    $perPage Records per page.
     * @return array Paginated results.
     */
    public function search(string $keyword, array $columns, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $likeClauses = implode(' OR ', array_map(fn($c) => "{$c} LIKE :keyword", $columns));

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE {$likeClauses}"
        );
        $countStmt->execute(['keyword' => "%{$keyword}%"]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$likeClauses} ORDER BY id DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':keyword', "%{$keyword}%");
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Filter data to only include fillable columns.
     *
     * @param array $data The input data.
     * @return array Filtered data.
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }
}
