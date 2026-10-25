<?php

namespace App\Support;

use PDO;
use RuntimeException;

/** Private, persistent, per-recipient journal. Never delete/reset for a retry. */
class ServiceMailLedger
{
    private PDO $db;

    private string $key;

    public function __construct(string $path, string $fingerprint)
    {
        $this->db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->exec('PRAGMA busy_timeout=5000; PRAGMA synchronous=FULL;');
        $this->db->exec('CREATE TABLE IF NOT EXISTS metadata (id INTEGER PRIMARY KEY CHECK(id=1), secret TEXT NOT NULL, fingerprint TEXT NOT NULL)');
        $insert = $this->db->prepare('INSERT OR IGNORE INTO metadata VALUES (1, ?, ?)');
        $insert->execute([bin2hex(random_bytes(32)), $fingerprint]);
        $meta = $this->db->query('SELECT * FROM metadata WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        if (! hash_equals($meta['fingerprint'], $fingerprint)) {
            throw new RuntimeException('Mailinhoud gewijzigd; verzending geblokkeerd.');
        }
        $this->key = $meta['secret'];
        $this->db->exec('CREATE TABLE IF NOT EXISTS deliveries (recipient TEXT PRIMARY KEY, status TEXT NOT NULL, attempted_at TEXT NOT NULL, accepted_at TEXT, provider_id TEXT)');
    }

    public static function recipients(iterable $emails): array
    {
        $unique = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string) $email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $unique[$email] = true;
            }
        }
        $result = array_keys($unique);
        sort($result);

        return $result;
    }

    public function status(string $email): ?string
    {
        $query = $this->db->prepare('SELECT status FROM deliveries WHERE recipient=?');
        $query->execute([$this->token($email)]);

        return $query->fetchColumn() ?: null;
    }

    /** Commit before contacting the provider. Uncertain attempts NEVER auto-retry. */
    public function claim(string $email): bool
    {
        $query = $this->db->prepare("INSERT OR IGNORE INTO deliveries (recipient,status,attempted_at) VALUES (?, 'uncertain', ?)");
        $query->execute([$this->token($email), gmdate('c')]);

        return $query->rowCount() === 1;
    }

    public function accepted(string $email, string $providerId): void
    {
        $query = $this->db->prepare("UPDATE deliveries SET status='accepted', accepted_at=?, provider_id=? WHERE recipient=? AND status='uncertain'");
        $query->execute([gmdate('c'), $providerId, $this->token($email)]);
        if ($query->rowCount() !== 1) {
            throw new RuntimeException('Verzendregistratie kon niet worden bevestigd.');
        }
    }

    private function token(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), $this->key);
    }
}
