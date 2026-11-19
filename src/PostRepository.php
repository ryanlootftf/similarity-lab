<?php

namespace Lab;

use PDO;

/**
 * SQLite storage for posts and their packed float32 vectors.
 */
class PostRepository
{
    private PDO $pdo;

    public function __construct(string $databasePath)
    {
        if (! is_dir(dirname($databasePath))) {
            mkdir(dirname($databasePath), 0777, true);
        }

        $this->pdo = new PDO('sqlite:'.$databasePath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createTable();
    }

    private function createTable(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reference TEXT,
            category TEXT NOT NULL,
            area TEXT,
            lang TEXT,
            title TEXT NOT NULL,
            description TEXT NOT NULL,
            model TEXT NOT NULL,
            vector BLOB NOT NULL,
            created_at TEXT NOT NULL
        )');
    }

    /**
     * @param  array{category: string, area: ?string, title: string, description: string, lang?: ?string, reference?: ?string}  $post
     * @param  list<float>  $vector
     */
    public function create(array $post, string $model, array $vector): int
    {
        $statement = $this->pdo->prepare('INSERT INTO posts (reference, category, area, lang, title, description, model, vector, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->bindValue(1, $post['reference'] ?? null);
        $statement->bindValue(2, $post['category']);
        $statement->bindValue(3, $post['area']);
        $statement->bindValue(4, $post['lang'] ?? null);
        $statement->bindValue(5, $post['title']);
        $statement->bindValue(6, $post['description']);
        $statement->bindValue(7, $model);
        $statement->bindValue(8, self::pack($vector), PDO::PARAM_LOB);
        $statement->bindValue(9, date('c'));
        $statement->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Posts embedded with the given model, with vectors unpacked.
     *
     * @return list<array{id: int, reference: ?string, category: string, area: ?string, lang: ?string, title: string, description: string, created_at: string, vector: list<float>}>
     */
    public function allWithVectors(string $model): array
    {
        $statement = $this->pdo->prepare('SELECT id, reference, category, area, lang, title, description, created_at, vector FROM posts WHERE model = ?');
        $statement->execute([$model]);

        return array_map(function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['vector'] = self::unpack($row['vector']);

            return $row;
        }, $statement->fetchAll());
    }

    /**
     * @return list<array{id: int, reference: ?string, category: string, area: ?string, lang: ?string, title: string, description: string, created_at: string}>
     */
    public function latest(int $limit = 100): array
    {
        $statement = $this->pdo->prepare('SELECT id, reference, category, area, lang, title, description, created_at FROM posts ORDER BY id DESC LIMIT ?');
        $statement->execute([$limit]);

        return $statement->fetchAll();
    }

    /**
     * Every post, oldest first, without vectors.
     *
     * @return list<array{id: int, reference: ?string, category: string, area: ?string, lang: ?string, title: string, description: string}>
     */
    public function all(): array
    {
        return array_map(function (array $row): array {
            $row['id'] = (int) $row['id'];

            return $row;
        }, $this->pdo->query('SELECT id, reference, category, area, lang, title, description FROM posts ORDER BY id')->fetchAll());
    }

    /**
     * @param  list<float>  $vector
     */
    public function updateVector(int $id, string $model, array $vector, string $language): void
    {
        $statement = $this->pdo->prepare('UPDATE posts SET model = ?, vector = ?, lang = ? WHERE id = ?');
        $statement->bindValue(1, $model);
        $statement->bindValue(2, self::pack($vector), PDO::PARAM_LOB);
        $statement->bindValue(3, $language);
        $statement->bindValue(4, $id);
        $statement->execute();
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
    }

    /**
     * Drops and recreates the table, so schema changes apply and ids start again from 1.
     */
    public function reset(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS posts');
        $this->createTable();
    }

    /**
     * @param  list<float>  $vector
     */
    public static function pack(array $vector): string
    {
        return pack('g*', ...$vector);
    }

    /**
     * @return list<float>
     */
    public static function unpack(string $blob): array
    {
        return array_values(unpack('g*', $blob));
    }
}
