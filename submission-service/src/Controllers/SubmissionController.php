<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;

class SubmissionController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $user = $GLOBALS['auth_user'];

        if (empty($input['title'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Title is required'
            ]);
            return;
        }

        // Generate ticket number
        $ticketNumber = 'SKR-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO submissions (ticket_number, identity_number, title, abstract, status)
                VALUES (:ticket_number, :identity_number, :title, :abstract, 'pengajuan')
            ");

            $stmt->execute([
                'ticket_number' => $ticketNumber,
                'identity_number' => $user['identity_number'],
                'title' => $input['title'],
                'abstract' => $input['abstract'] ?? null
            ]);

            $submissionId = $this->db->lastInsertId();

            // Create default milestones
            $milestones = [
                'Proposal Skripsi',
                'Bab 1 - Pendahuluan',
                'Bab 2 - Tinjauan Pustaka',
                'Bab 3 - Metodologi',
                'Bab 4 - Hasil dan Pembahasan',
                'Bab 5 - Kesimpulan',
                'Sidang Skripsi'
            ];

            $stmt = $this->db->prepare("
                INSERT INTO milestones (submission_id, milestone_name, status)
                VALUES (:submission_id, :milestone_name, 'pending')
            ");

            foreach ($milestones as $milestone) {
                $stmt->execute([
                    'submission_id' => $submissionId,
                    'milestone_name' => $milestone
                ]);
            }

            $this->db->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Submission created successfully',
                'data' => [
                    'id' => $submissionId,
                    'ticket_number' => $ticketNumber
                ]
            ]);
        } catch (\Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create submission',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getByUser(): void
    {
        $user = $GLOBALS['auth_user'];
        $userRole = $user['role'] ?? 'mahasiswa';

        try {
            // Jika dosen atau kaprodi, tampilkan semua submission
            // Jika mahasiswa, tampilkan hanya submission mereka sendiri
            if ($userRole === 'dosen' || $userRole === 'kaprodi') {
                $stmt = $this->db->prepare("
                    SELECT s.*, 
                           COUNT(m.id) as total_milestones,
                           SUM(CASE WHEN m.status = 'acc' THEN 1 ELSE 0 END) as completed_milestones,
                           s.identity_number as student_identity_number,
                           s.identity_number as student_name
                    FROM submissions s
                    LEFT JOIN milestones m ON s.id = m.submission_id
                    GROUP BY s.id
                    ORDER BY 
                        CASE WHEN s.status = 'pengajuan' THEN 0 ELSE 1 END,
                        s.created_at DESC
                ");
                $stmt->execute();
            } else {
                $stmt = $this->db->prepare("
                    SELECT s.*, 
                           COUNT(m.id) as total_milestones,
                           SUM(CASE WHEN m.status = 'acc' THEN 1 ELSE 0 END) as completed_milestones
                    FROM submissions s
                    LEFT JOIN milestones m ON s.id = m.submission_id
                    WHERE s.identity_number = :identity_number
                    GROUP BY s.id
                    ORDER BY s.created_at DESC
                ");
                $stmt->execute(['identity_number' => $user['identity_number']]);
            }
            
            $submissions = $stmt->fetchAll();

            foreach ($submissions as &$submission) {
                $submission['total_progress'] = $submission['total_milestones'] > 0
                    ? round(($submission['completed_milestones'] / $submission['total_milestones']) * 100)
                    : 0;
            }

            echo json_encode([
                'success' => true,
                'data' => $submissions
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to fetch submissions',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getById(int $id): void
    {
        $user = $GLOBALS['auth_user'];
        $userRole = $user['role'] ?? 'mahasiswa';

        try {
            // Jika dosen/kaprodi, bisa lihat semua submission
            // Jika mahasiswa, hanya bisa lihat submission mereka sendiri
            if ($userRole === 'dosen' || $userRole === 'kaprodi') {
                $stmt = $this->db->prepare("
                    SELECT * FROM submissions 
                    WHERE id = :id
                );

                $stmt->execute(['id' => $id]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT * FROM submissions 
                    WHERE id = :id AND identity_number = :identity_number
                ");

                $stmt->execute([
                    'id' => $id,
                    'identity_number' => $user['identity_number']
                ]);
            }

            $submission = $stmt->fetch();

            if (!$submission) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Submission not found'
                ]);
                return;
            }

            echo json_encode([
                'success' => true,
                'data' => $submission
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to fetch submission',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function update(int $id): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $user = $GLOBALS['auth_user'];
        $userRole = $user['role'] ?? 'mahasiswa';

        try {
            // Jika dosen/kaprodi, bisa update status tanpa check identity_number
            // Jika mahasiswa, hanya bisa update submission mereka sendiri
            if ($userRole === 'dosen' || $userRole === 'kaprodi') {
                $stmt = $this->db->prepare("
                    UPDATE submissions 
                    SET title = COALESCE(:title, title),
                        abstract = COALESCE(:abstract, abstract),
                        status = COALESCE(:status, status)
                    WHERE id = :id
                ");

                $stmt->execute([
                    'id' => $id,
                    'title' => $input['title'] ?? null,
                    'abstract' => $input['abstract'] ?? null,
                    'status' => $input['status'] ?? null
                ]);
            } else {
                $stmt = $this->db->prepare("
                    UPDATE submissions 
                    SET title = COALESCE(:title, title),
                        abstract = COALESCE(:abstract, abstract),
                        status = COALESCE(:status, status)
                    WHERE id = :id AND identity_number = :identity_number
                ");

                $stmt->execute([
                    'id' => $id,
                    'identity_number' => $user['identity_number'],
                    'title' => $input['title'] ?? null,
                    'abstract' => $input['abstract'] ?? null,
                    'status' => $input['status'] ?? null
                ]);
            }

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Submission not found or no changes made'
                ]);
                return;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Submission updated successfully'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update submission',
                'error' => $e->getMessage()
            ]);
        }
    }
}
