<?php
/**
 * JsonDB — a tiny NoSQL-style document store.
 * Each "collection" is a single JSON file holding an array of associative-array documents.
 * File locking (flock) keeps concurrent read/write requests from corrupting the file.
 */
class JsonDB
{
    private $file;

    public function __construct(string $collection)
    {
        $this->file = DATA_DIR . '/' . $collection . '.json';
        if (!file_exists($this->file)) {
            file_put_contents($this->file, '[]');
        }
    }

    public function all(): array
    {
        $fh = fopen($this->file, 'r');
        if (!$fh) return [];
        flock($fh, LOCK_SH);
        $contents = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        $docs = json_decode($contents, true);
        return is_array($docs) ? $docs : [];
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $doc) {
            if (($doc['id'] ?? null) === $id) return $doc;
        }
        return null;
    }

    public function where(callable $predicate): array
    {
        return array_values(array_filter($this->all(), $predicate));
    }

    public function insert(array $doc): array
    {
        if (empty($doc['id'])) {
            $doc['id'] = bin2hex(random_bytes(8));
        }
        $doc['created_at'] = $doc['created_at'] ?? date('c');

        $this->withLock(function (array $docs) use ($doc) {
            $docs[] = $doc;
            return $docs;
        });

        return $doc;
    }

    /** Insert several documents in one write. With $replace, they become the whole collection. */
    public function insertMany(array $docs, bool $replace = false): array
    {
        $now = date('c');
        foreach ($docs as &$doc) {
            if (empty($doc['id'])) {
                $doc['id'] = bin2hex(random_bytes(8));
            }
            $doc['created_at'] = $doc['created_at'] ?? $now;
        }
        unset($doc);

        $this->withLock(function (array $existing) use ($docs, $replace) {
            return $replace ? $docs : array_merge($existing, $docs);
        });

        return $docs;
    }

    public function update(string $id, array $fields): bool
    {
        $found = false;
        $this->withLock(function (array $docs) use ($id, $fields, &$found) {
            foreach ($docs as &$doc) {
                if (($doc['id'] ?? null) === $id) {
                    $doc = array_merge($doc, $fields);
                    $found = true;
                }
            }
            return $docs;
        });
        return $found;
    }

    public function delete(string $id): bool
    {
        $found = false;
        $this->withLock(function (array $docs) use ($id, &$found) {
            $before = count($docs);
            $docs = array_values(array_filter($docs, function ($d) use ($id) { return ($d['id'] ?? null) !== $id; }));
            $found = count($docs) !== $before;
            return $docs;
        });
        return $found;
    }

    /** Delete several documents in one write. Returns how many were removed. */
    public function deleteMany(array $ids): int
    {
        $ids = array_fill_keys(array_map('strval', $ids), true);
        $removed = 0;
        $this->withLock(function (array $docs) use ($ids, &$removed) {
            $before = count($docs);
            $docs = array_values(array_filter($docs, function ($d) use ($ids) { return !isset($ids[(string)($d['id'] ?? '')]); }));
            $removed = $before - count($docs);
            return $docs;
        });
        return $removed;
    }

    /** Read-modify-write the whole collection under an exclusive lock. */
    private function withLock(callable $mutator): void
    {
        $fh = fopen($this->file, 'c+');
        flock($fh, LOCK_EX);
        $contents = stream_get_contents($fh);
        $docs = json_decode($contents, true);
        if (!is_array($docs)) $docs = [];

        $docs = $mutator($docs);

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode(array_values($docs), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}
