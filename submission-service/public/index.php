<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Controllers\SubmissionController;
use App\Controllers\MilestoneController;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

// Initialize database
$db = Database::getInstance();

// Initialize router
$router = new Router();
$authMiddleware = new AuthMiddleware();

// Health check
$router->get('/health', function () {
    echo json_encode([
        'status' => 'healthy',
        'service' => 'submission-service'
    ]);
});

// Submission routes
$router->post('/api/academic/submissions', function () use ($authMiddleware) {
    $authMiddleware->handle();
    $controller = new SubmissionController();
    $controller->create();
});

$router->get('/api/academic/submissions', function () use ($authMiddleware) {
    $authMiddleware->handle();
    $controller = new SubmissionController();
    $controller->getByUser();
});

$router->get('/api/academic/submissions/{id}', function ($id) use ($authMiddleware) {
    $authMiddleware->handle();
    $controller = new SubmissionController();
    $controller->getById($id);
});

$router->put('/api/academic/submissions/{id}', function ($id) use ($authMiddleware) {
    $authMiddleware->handle();
    $controller = new SubmissionController();
    $controller->update($id);
});

// Milestone routes
$router->get('/api/academic/submissions/{id}/milestones', function ($id) use ($authMiddleware) {
    $authMiddleware->handle();
    $controller = new MilestoneController();
    $controller->getBySubmission($id);
});

$router->put('/api/academic/milestones/{id}', function ($id) use ($authMiddleware) {
    $authMiddleware->handle();
    $controller = new MilestoneController();
    $controller->update($id);
});

// Run router
$router->run();
