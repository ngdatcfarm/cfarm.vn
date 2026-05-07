<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Care;

use PDO;

/**
 * CareController - Serve care UI page on cloud.
 *
 * Cloud hosts the care UI at /care for remote farm management.
 */
class CareController
{
    public function __construct(private PDO $pdo) {}

    public function index(array $vars): void
    {
        $title = 'Chăm sóc - CFarm';
        $content = view_path('care/care_index.php');
        require view_path('layouts/main.php');
    }
}