<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Mysql\Repositories;

use App\Domains\Weight\Entities\WeightSession;
use App\Domains\Weight\Entities\WeightSample;
use PDO;

class WeightRepository
{
    public function __construct(private PDO $pdo) {}

    public function create_session(WeightSession $session): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO weight_sessions (cycle_id, day_age, sample_count, avg_weight_g, note, weighed_at)
            VALUES (:cycle_id, :day_age, :sample_count, :avg_weight_g, :note, :weighed_at)
        ");
        $stmt->execute([
            ':cycle_id'     => $session->cycle_id,
            ':day_age'      => $session->day_age,
            ':sample_count' => $session->sample_count,
            ':avg_weight_g' => $session->avg_weight_g,
            ':note'         => $session->note,
            ':weighed_at'   => $session->weighed_at,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function add_sample(WeightSample $sample): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO weight_samples (session_id, weight_g, gender)
            VALUES (:session_id, :weight_g, :gender)
        ");
        $stmt->execute([
            ':session_id' => $sample->session_id,
            ':weight_g'   => $sample->weight_g,
            ':gender'     => $sample->gender,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->recalc_session($sample->session_id);
        return $id;
    }

    public function recalc_session(int $session_id): void
    {
        $this->pdo->prepare("
            UPDATE weight_sessions ws
            SET
                sample_count  = (SELECT COUNT(*)   FROM weight_samples WHERE session_id = :id1),
                avg_weight_g  = (SELECT AVG(weight_g) FROM weight_samples WHERE session_id = :id2)
            WHERE ws.id = :id3
        ")->execute([':id1' => $session_id, ':id2' => $session_id, ':id3' => $session_id]);
    }

    public function delete_sample(int $sample_id): void
    {
        $stmt = $this->pdo->prepare("SELECT session_id FROM weight_samples WHERE id = :id");
        $stmt->execute([':id' => $sample_id]);
        $row = $stmt->fetch();
        $this->pdo->prepare("DELETE FROM weight_samples WHERE id = :id")->execute([':id' => $sample_id]);
        if ($row) $this->recalc_session((int)$row['session_id']);
    }

    public function find_session_by_id(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM weight_sessions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function find_samples_by_session(int $session_id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM weight_samples WHERE session_id = :id ORDER BY id ASC
        ");
        $stmt->execute([':id' => $session_id]);
        return $stmt->fetchAll();
    }

    public function find_sessions_by_cycle(int $cycle_id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM weight_sessions
            WHERE cycle_id = :cycle_id
            ORDER BY day_age ASC, weighed_at ASC
        ");
        $stmt->execute([':cycle_id' => $cycle_id]);
        return $stmt->fetchAll();
    }

    public function delete_session(int $id): void
    {
        $this->pdo->prepare("DELETE FROM weight_sessions WHERE id = :id")->execute([':id' => $id]);
    }

    public function find_sessions_with_gender_avg(int $cycle_id): array
    {
        $sessions = $this->find_sessions_by_cycle($cycle_id);
        foreach ($sessions as &$ws) {
            $stmt = $this->pdo->prepare("
                SELECT gender,
                       COUNT(*)      AS count,
                       AVG(weight_g) AS avg_g
                FROM weight_samples
                WHERE session_id = :id
                GROUP BY gender
            ");
            $stmt->execute([':id' => $ws['id']]);
            $by_gender = [];
            foreach ($stmt->fetchAll() as $row) {
                $by_gender[$row['gender']] = [
                    'count' => (int)$row['count'],
                    'avg_g' => round((float)$row['avg_g'], 1),
                ];
            }
            $ws['by_gender'] = $by_gender;
        }
        unset($ws);
        return $sessions;
    }



}