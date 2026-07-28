<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Clock;
use PDO;

final class ResourceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array{id: int, key: string, name: string, icon_key: string}>
     */
    public function findAllActive(): array
    {
        return $this->pdo->query(
            'SELECT id, key, name, icon_key FROM resources WHERE is_active = 1 ORDER BY id ASC',
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findIdByKey(string $key): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM resources WHERE key = :key LIMIT 1');
        $statement->execute(['key' => $key]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * @return array<int, int> resource_id => amount
     */
    public function findFamilyBalances(int $familyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT resource_id, amount FROM family_resources WHERE family_id = :family_id',
        );
        $statement->execute(['family_id' => $familyId]);

        $balances = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $balances[(int) $row['resource_id']] = (int) $row['amount'];
        }

        return $balances;
    }

    /**
     * Erhoeht den Rohstoffbestand einer Familie und legt die Zeile bei Bedarf an.
     * Muss innerhalb einer bestehenden Transaktion aufgerufen werden.
     *
     * Bewusst ohne "INSERT ... ON CONFLICT DO UPDATE" (SQLite-Upsert, erst ab 3.24.0):
     * Der Produktivserver liefert SQLite 3.7.17 (2013) aus, das diese Syntax nicht kennt
     * und mit einem Syntax-Fehler abbricht. Stattdessen zwei einfache, ueberall
     * unterstuetzte Anweisungen.
     */
    public function incrementBalance(int $familyId, int $resourceId, int $amount): void
    {
        $now = Clock::nowIso();

        $insert = $this->pdo->prepare(
            'INSERT OR IGNORE INTO family_resources (family_id, resource_id, amount, updated_at)
             VALUES (:family_id, :resource_id, 0, :now)',
        );
        $insert->execute(['family_id' => $familyId, 'resource_id' => $resourceId, 'now' => $now]);

        $update = $this->pdo->prepare(
            'UPDATE family_resources SET amount = amount + :amount, updated_at = :now
             WHERE family_id = :family_id AND resource_id = :resource_id',
        );
        $update->execute([
            'family_id' => $familyId,
            'resource_id' => $resourceId,
            'amount' => $amount,
            'now' => $now,
        ]);
    }

    /**
     * Verringert den Rohstoffbestand einer Familie. Die WHERE-Bedingung
     * "amount >= :amount" ist ein zusaetzlicher Schutz auf DB-Ebene gegen
     * negative Bestaende (die Anwendung muss die Verfuegbarkeit trotzdem
     * vorher pruefen, damit ein Fehlschlag hier nie den Normalfall ist).
     * Muss innerhalb einer bestehenden Transaktion aufgerufen werden.
     *
     * @throws \RuntimeException wenn nicht genug Rohstoffe vorhanden sind
     */
    public function decrementBalance(int $familyId, int $resourceId, int $amount): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE family_resources SET amount = amount - :amount, updated_at = :now
             WHERE family_id = :family_id AND resource_id = :resource_id AND amount >= :amount_check',
        );
        $statement->execute([
            'family_id' => $familyId,
            'resource_id' => $resourceId,
            'amount' => $amount,
            'amount_check' => $amount,
            'now' => Clock::nowIso(),
        ]);

        if ($statement->rowCount() === 0) {
            throw new \RuntimeException('Nicht genuegend Rohstoffe vorhanden.');
        }
    }
}
