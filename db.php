<?php
// db.php — подключение к SQLite, инициализация схемы и функции работы с рекордами

declare(strict_types=1);

function getDb(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbPath = __DIR__ . '/records.db';
    $isNew = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Включаем WAL для конкурентного доступа
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    // Создаём таблицу рекордов, если её нет
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS records (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT    NOT NULL DEFAULT \'Аноним\',
            score      INTEGER NOT NULL CHECK (score >= 0),
            created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ');

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_score ON records(score DESC)');

    // Создаём таблицу rate limit (для защиты API от флуда)
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS rate_limit (
            ip          TEXT PRIMARY KEY,
            last_write  INTEGER NOT NULL
        )
    ');

    // Права на файл БД (только владелец)
    if ($isNew) {
        @chmod($dbPath, 0660);
    }

    return $pdo;
}

/**
 * Обрезает UTF-8 строку до $limit символов без зависимости от mbstring.
 * Работает через регулярное выражение с модификатором /u.
 */
function safe_substr(string $str, int $limit): string
{
    if ($limit <= 0) {
        return '';
    }
    if (preg_match('/^.{0,' . $limit . '}/us', $str, $m)) {
        return $m[0];
    }
    // Если строка невалидный UTF-8 — вернём как есть, обрезав по байтам
    return substr($str, 0, $limit);
}

/**
 * Проверяет, может ли IP писать прямо сейчас.
 * Возвращает true, если запись разрешена.
 */
function checkRateLimit(string $ip, int $cooldownSec = 10): bool
{
    $pdo = getDb();

    // Подчищаем старые записи, чтобы таблица не пухла
    $pdo->exec('DELETE FROM rate_limit WHERE last_write < ' . (time() - 3600));

    $stmt = $pdo->prepare('SELECT last_write FROM rate_limit WHERE ip = :ip');
    $stmt->execute([':ip' => $ip]);
    $last = $stmt->fetchColumn();

    $now = time();
    if ($last !== false && ($now - (int)$last) < $cooldownSec) {
        return false;
    }

    $stmt = $pdo->prepare('
        INSERT INTO rate_limit (ip, last_write) VALUES (:ip, :t)
        ON CONFLICT(ip) DO UPDATE SET last_write = excluded.last_write
    ');
    $stmt->execute([':ip' => $ip, ':t' => $now]);

    return true;
}

/**
 * Получить топ-N рекордов.
 */
function getTopRecords(int $limit = 5): array
{
    $stmt = getDb()->prepare(
        'SELECT name, score, created_at
         FROM records
         ORDER BY score DESC, created_at ASC
         LIMIT :lim'
    );
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return array_map(static function (array $row): array {
        return [
            'name'  => $row['name'],
            'score' => (int) $row['score'],
            'date'  => formatDate($row['created_at']),
        ];
    }, $stmt->fetchAll());
}

/**
 * Добавить рекорд. Возвращает массив с полем success, is_top и обновлённым списком.
 */
function addRecord(string $name, int $score, int $maxRecords = 5): array
{
    // Санитайз имени: буквы (включая кириллицу), цифры, пробел, дефис, подчёркивание, точка
    $name = trim($name);
    if ($name === '') {
        $name = 'Аноним';
    }
    $name = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $name);
    $name = trim($name);
    if ($name === '') {
        $name = 'Аноним';
    }
    $name = safe_substr($name, 20);

    // Санитайз счёта
    if ($score < 0) {
        $score = 0;
    }
    if ($score > 10000) {
        $score = 10000;
    }

    $pdo = getDb();

    // Проверяем, попадёт ли в топ
    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM records');
    $stmt->execute();
    $count = (int) $stmt->fetchColumn();

    $isTop = $count < $maxRecords;
    if (!$isTop) {
        $stmt = $pdo->prepare(
            'SELECT MIN(score) FROM (
                SELECT score FROM records ORDER BY score DESC LIMIT :lim
             )'
        );
        $stmt->bindValue(':lim', $maxRecords, PDO::PARAM_INT);
        $stmt->execute();
        $minTop = (int) $stmt->fetchColumn();
        $isTop = $score > $minTop;
    }

    // Вставляем запись
    $stmt = $pdo->prepare(
        'INSERT INTO records (name, score) VALUES (:name, :score)'
    );
    $stmt->execute([':name' => $name, ':score' => $score]);

    // Чистим таблицу, оставляя только топ-N
    $pdo->exec('
        DELETE FROM records
        WHERE id NOT IN (
            SELECT id FROM records ORDER BY score DESC, created_at ASC LIMIT ' . (int) $maxRecords . '
        )
    ');

    return [
        'success' => true,
        'is_top'  => $isTop,
        'records' => getTopRecords($maxRecords),
    ];
}

/**
 * Форматирует дату для отображения (dd.mm.yy HH:MM).
 * SQLite хранит дату в UTC (CURRENT_TIMESTAMP) — приводим к локальной зоне.
 */
function formatDate(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    try {
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $dt->format('d.m.y H:i');
    } catch (Exception $e) {
        return '';
    }
}