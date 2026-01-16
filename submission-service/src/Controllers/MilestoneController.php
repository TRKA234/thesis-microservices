<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;


class MilestoneController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getBySubmission(int $submissionId): void
    {
        $user = $GLOBALS['auth_user'];

        try {
            // Verify submission belongs to user
            $stmt = $this->db->prepare("
                SELECT id FROM submissions 
                WHERE id = :id AND identity_number = :identity_number
            ");
            $stmt->execute([
                'id' => $submissionId,
                'identity_number' => $user['identity_number']
            ]);

            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Submission not found'
                ]);
                return;
            }

            // Get milestones
            $stmt = $this->db->prepare("
                SELECT * FROM milestones 
                WHERE submission_id = :submission_id
                ORDER BY id ASC
            ");
            $stmt->execute(['submission_id' => $submissionId]);
            $milestones = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => $milestones
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to fetch milestones',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function update(int $id): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $user = $GLOBALS['auth_user'];

        // Only dosen and kaprodi can update milestone status
        if (!in_array($user['role'], ['dosen', 'kaprodi'])) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Only lecturers can update milestone status'
            ]);
            return;
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE milestones 
                SET status = COALESCE(:status, status),
                    notes = COALESCE(:notes, notes)
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $id,
                'status' => $input['status'] ?? null,
                'notes' => $input['notes'] ?? null
            ]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Milestone not found'
                ]);
                return;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Milestone updated successfully'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update milestone',
                'error' => $e->getMessage()
            ]);
        }
    }
}
