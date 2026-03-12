<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Mysql\Repositories;

use App\Domains\Care\Contracts\CareRepositoryInterface;
use App\Domains\Care\Entities\CareFeed;
use App\Domains\Care\Entities\CareDeath;
use App\Domains\Care\Entities\CareMedication;
use App\Domains\Care\Entities\CareSale;
use App\Domains\Care\Entities\FeedTroughCheck;
use PDO;

class CareRepository implements CareRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    // --- FEEDS ---

    public function create_feed(CareFeed $feed): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO care_feeds
                (cycle_id, feed_type_id, bags, kg_actual, session, note, recorded_at)
            VALUES
                (:cycle_id, :feed_type_id, :bags, :kg_actual, :session, :note, :recorded_at)
        ");
        $stmt->execute([
            ':cycle_id'     => $feed->cycle_id,
            ':feed_type_id' => $feed->feed_type_id,
            ':bags'         => $feed->bags,
            ':kg_actual'    => $feed->kg_actual,
            ':session'      => $feed->session,
            ':note'         => $feed->note,
            ':recorded_at'  => $feed->recorded_at,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function find_feeds_by_cycle_and_date(int $cycle_id, string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT cf.*,
                   ft.code     AS feed_code,
                   fb.name     AS brand_name,
                   fb.kg_per_bag,
                   -- lấy check mới nhất cho feed này trong ngày
                   (SELECT ftc.remaining_pct
                    FROM feed_trough_checks ftc
                    WHERE ftc.ref_feed_id = cf.id
                    ORDER BY ftc.checked_at DESC LIMIT 1
                   ) AS latest_remaining_pct,
                   (SELECT ftc.checked_at
                    FROM feed_trough_checks ftc
                    WHERE ftc.ref_feed_id = cf.id
                    ORDER BY ftc.checked_at DESC LIMIT 1
                   ) AS latest_checked_at
            FROM care_feeds cf
            JOIN feed_types ft  ON cf.feed_type_id  = ft.id
            JOIN feed_brands fb ON ft.feed_brand_id = fb.id
            WHERE cf.cycle_id = :cycle_id
              AND DATE(cf.recorded_at) = :date
            ORDER BY cf.recorded_at ASC
        ");
        $stmt->execute([':cycle_id' => $cycle_id, ':date' => $date]);
        return $stmt->fetchAll();
    }

    public function find_feed_by_id(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT cf.*, ft.code AS feed_code, fb.name AS brand_name, fb.kg_per_bag
            FROM care_feeds cf
            JOIN feed_types ft  ON cf.feed_type_id  = ft.id
            JOIN feed_brands fb ON ft.feed_brand_id = fb.id
            WHERE cf.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // --- TROUGH CHECKS ---

    public function create_trough_check(FeedTroughCheck $check): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO feed_trough_checks
                (cycle_id, ref_feed_id, remaining_pct, checked_at, note)
            VALUES
                (:cycle_id, :ref_feed_id, :remaining_pct, :checked_at, :note)
        ");
        $stmt->execute([
            ':cycle_id'      => $check->cycle_id,
            ':ref_feed_id'   => $check->ref_feed_id,
            ':remaining_pct' => $check->remaining_pct,
            ':checked_at'    => $check->checked_at,
            ':note'          => $check->note,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function find_trough_checks_by_cycle_and_date(int $cycle_id, string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ftc.*,
                   cf.bags      AS feed_bags,
                   cf.kg_actual AS feed_kg_actual,
                   ft.code      AS feed_code,
                   fb.name      AS brand_name
            FROM feed_trough_checks ftc
            JOIN care_feeds  cf ON ftc.ref_feed_id   = cf.id
            JOIN feed_types  ft ON cf.feed_type_id   = ft.id
            JOIN feed_brands fb ON ft.feed_brand_id  = fb.id
            WHERE ftc.cycle_id = :cycle_id
              AND DATE(ftc.checked_at) = :date
            ORDER BY ftc.checked_at ASC
        ");
        $stmt->execute([':cycle_id' => $cycle_id, ':date' => $date]);
        return $stmt->fetchAll();
    }

    // --- DEATHS ---

    public function create_death(CareDeath $death): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO care_deaths
                (cycle_id, quantity, reason, symptoms, image_path, note, recorded_at)
            VALUES
                (:cycle_id, :quantity, :reason, :symptoms, :image_path, :note, :recorded_at)
        ");
        $stmt->execute([
            ':cycle_id'    => $death->cycle_id,
            ':quantity'    => $death->quantity,
            ':reason'      => $death->reason,
            ':symptoms'    => $death->symptoms,
            ':image_path'  => $death->image_path,
            ':note'        => $death->note,
            ':recorded_at' => $death->recorded_at,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function find_deaths_by_cycle_and_date(int $cycle_id, string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM care_deaths
            WHERE cycle_id = :cycle_id AND DATE(recorded_at) = :date
            ORDER BY recorded_at ASC
        ");
        $stmt->execute([':cycle_id' => $cycle_id, ':date' => $date]);
        return $stmt->fetchAll();
    }

    // --- MEDICATIONS ---

    public function create_medication(CareMedication $med): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO care_medications
                (cycle_id, medication_id, medication_name, dosage, unit, method, note, recorded_at)
            VALUES
                (:cycle_id, :medication_id, :medication_name, :dosage, :unit, :method, :note, :recorded_at)
        ");
        $stmt->execute([
            ':cycle_id'        => $med->cycle_id,
            ':medication_id'   => $med->medication_id,
            ':medication_name' => $med->medication_name,
            ':dosage'          => $med->dosage,
            ':unit'            => $med->unit,
            ':method'          => $med->method,
            ':note'            => $med->note,
            ':recorded_at'     => $med->recorded_at,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function find_medications_by_cycle_and_date(int $cycle_id, string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM care_medications
            WHERE cycle_id = :cycle_id AND DATE(recorded_at) = :date
            ORDER BY recorded_at ASC
        ");
        $stmt->execute([':cycle_id' => $cycle_id, ':date' => $date]);
        return $stmt->fetchAll();
    }

    // --- SALES ---

    public function create_sale(CareSale $sale): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO care_sales
                (cycle_id, quantity, gender, weight_kg, price_per_kg, total_amount, note, recorded_at)
            VALUES
                (:cycle_id, :quantity, :gender, :weight_kg, :price_per_kg, :total_amount, :note, :recorded_at)
        ");
        $stmt->execute([
            ':cycle_id'     => $sale->cycle_id,
            ':quantity'     => $sale->quantity,
            ':gender'       => $sale->gender,
            ':weight_kg'    => $sale->weight_kg,
            ':price_per_kg' => $sale->price_per_kg,
            ':total_amount' => $sale->total_amount,
            ':note'         => $sale->note,
            ':recorded_at'  => $sale->recorded_at,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function find_sales_by_cycle_and_date(int $cycle_id, string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM care_sales
            WHERE cycle_id = :cycle_id AND DATE(recorded_at) = :date
            ORDER BY recorded_at ASC
        ");
        $stmt->execute([':cycle_id' => $cycle_id, ':date' => $date]);
        return $stmt->fetchAll();
    }
}
