<?php

namespace App\Core;

use PDO;

abstract class Model
{
    protected static string $table = '';

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function all(string $orderBy = 'id DESC'): array
    {
        return static::query('SELECT * FROM ' . static::$table . ' ORDER BY ' . $orderBy)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = static::query('SELECT * FROM ' . static::$table . ' WHERE id = ?', [$id])->fetch();
        return $row ?: null;
    }

    public static function where(string $column, $value, string $orderBy = 'id DESC'): array
    {
        return static::query('SELECT * FROM ' . static::$table . " WHERE {$column} = ? ORDER BY {$orderBy}", [$value])->fetchAll();
    }

    public static function first(string $column, $value): ?array
    {
        $row = static::query('SELECT * FROM ' . static::$table . " WHERE {$column} = ? LIMIT 1", [$value])->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . static::$table . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
        static::query($sql, array_values($data));
        return Database::lastId();
    }

    public static function update(int $id, array $data): void
    {
        $set = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($data)));
        $sql = 'UPDATE ' . static::$table . " SET {$set} WHERE id = ?";
        static::query($sql, [...array_values($data), $id]);
    }

    public static function delete(int $id): void
    {
        static::query('DELETE FROM ' . static::$table . ' WHERE id = ?', [$id]);
    }

    public static function count(string $where = '1=1', array $params = []): int
    {
        $row = static::query('SELECT COUNT(*) AS c FROM ' . static::$table . ' WHERE ' . $where, $params)->fetch();
        return (int) ($row['c'] ?? 0);
    }

    public static function sum(string $column, string $where = '1=1', array $params = []): float
    {
        $row = static::query("SELECT SUM({$column}) AS s FROM " . static::$table . ' WHERE ' . $where, $params)->fetch();
        return (float) ($row['s'] ?? 0);
    }
}
