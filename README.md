# Thesis Microservices Project

This project implements a microservices architecture for a thesis management system. It consists of several services, each built with a different technology stack, communicating via an Nginx API Gateway.

## Architecture Overview

The system comprises the following microservices:

-   **Auth Service (Golang + PostgreSQL)**: Handles user registration, login, and JWT token generation/validation.
-   **Submission Service (PHP + MySQL)**: Manages thesis submissions, including titles, abstracts, and milestones.
-   **Guidance Service (Node.js + MongoDB)**: Provides a chat/logbook system for guidance sessions between students and lecturers.
-   **Monitoring Service (Python + FastAPI + Redis)**: Collects and presents global statistics from other services with caching.
-   **Nginx API Gateway**: Routes incoming API requests to the appropriate microservice and handles CORS.

## Setup & Installation

### Prerequisites
-   [Docker](https://www.docker.com/products/docker-desktop)
-   [Docker Compose](https://docs.docker.com/compose/install/)

### Steps to Run

1.  **Clone the repository (if not already done):**
    ```bash
    git clone <your-repository-url>
    cd thesis-microservices
    ```
    *(Assuming this project is in a Git repository)*

2.  **Bring up the services:**
    This command will build the Docker images for all services and start them in detached mode.
    ```bash
    docker compose up --build -d
    ```

3.  **Verify services are running:**
    ```bash
    docker compose ps
    ```
    You should see all services in a `Up` or `Up (healthy)` status.

## API Endpoints & Testing

All API endpoints are exposed through the Nginx API Gateway, typically accessible on `http://localhost`.

### 1. Auth Service (via Nginx: `http://localhost/api/auth`)

-   **Description**: Handles user authentication and authorization.

#### a. Register User (`POST`)
-   **URL**: `http://localhost/api/auth/register`
-   **Method**: `POST`
-   **Request Body (JSON)**:
    ```json
    {
        "identity_number": "12345",
        "full_name": "John Doe",
        "password": "password123",
        "role": "mahasiswa" // or "dosen", "kaprodi"
    }
    ```
-   **Expected Response**: `{ "token": "...", "user": { "id": "...", "full_name": "...", "role": "...", "identity_number": "..." } }`

#### b. Login User (`POST`)
-   **URL**: `http://localhost/api/auth/login`
-   **Method**: `POST`
-   **Request Body (JSON)**:
    ```json
    {
        "identity_number": "12345",
        "password": "password123"
    }
    ```
-   **Expected Response**: `{ "token": "...", "user": { "id": "...", "full_name": "...", "role": "...", "identity_number": "..." } }`

### 2. Submission Service (via Nginx: `http://localhost/api/academic`)

-   **Description**: Manages thesis submission data and milestones.
-   **Authentication**: Most endpoints require a valid JWT in the `Authorization: Bearer <token>` header.

#### a. Create Submission (`POST`)
-   **URL**: `http://localhost/api/academic/submissions`
-   **Method**: `POST`
-   **Headers**: `Authorization: Bearer <your_jwt_token>` (obtained from Auth Service login)
-   **Request Body (JSON)**:
    ```json
    {
        "title": "Analisis Performa Microservices dengan Docker dan Kubernetes",
        "abstract": "Penelitian ini mengkaji performa aplikasi microservices..."
    }
    ```
-   **Expected Response**: `{ "success": true, "message": "Submission created successfully", "data": { "id": ..., "ticket_number": "..." } }`

#### b. Get Submissions by User (`GET`)
-   **URL**: `http://localhost/api/academic/submissions`
-   **Method**: `GET`
-   **Headers**: `Authorization: Bearer <your_jwt_token>`
-   **Expected Response**: `{ "success": true, "data": [...] }` (list of submissions for the authenticated user)

#### c. Get Submission by ID (`GET`)
-   **URL**: `http://localhost/api/academic/submissions/{id}` (replace `{id}` with actual submission ID)
-   **Method**: `GET`
-   **Headers**: `Authorization: Bearer <your_jwt_token>`
-   **Expected Response**: `{ "success": true, "data": { ...submission_details... } }`

#### d. Update Submission (`PUT`)
-   **URL**: `http://localhost/api/academic/submissions/{id}` (replace `{id}` with actual submission ID)
-   **Method**: `PUT`
-   **Headers**: `Authorization: Bearer <your_jwt_token>`
-   **Request Body (JSON)**: (partial update is possible)
    ```json
    {
        "title": "Analisis Performa Microservices (Revisi)",
        "status": "bimbingan" // or "revisi", "sidang", "lulus"
    }
    ```
-   **Expected Response**: `{ "success": true, "message": "Submission updated successfully" }`

#### e. Get Milestones for Submission (`GET`)
-   **URL**: `http://localhost/api/academic/submissions/{id}/milestones` (replace `{id}` with actual submission ID)
-   **Method**: `GET`
-   **Headers**: `Authorization: Bearer <your_jwt_token>`
-   **Expected Response**: `{ "success": true, "data": [...] }` (list of milestones for the specified submission)

#### f. Update Milestone (`PUT`)
-   **URL**: `http://localhost/api/academic/milestones/{id}` (replace `{id}` with actual milestone ID)
-   **Method**: `PUT`
-   **Headers**: `Authorization: Bearer <your_jwt_token>` (User role must be "dosen" or "kaprodi")
-   **Request Body (JSON)**:
    ```json
    {
        "status": "acc", // or "pending", "progress", "revision"
        "notes": "Great progress on this milestone!"
    }
    ```
-   **Expected Response**: `{ "success": true, "message": "Milestone updated successfully" }`

### 3. Guidance Service (via Nginx: `http://localhost/api/guidance`)

-   **Description**: Manages guidance chat sessions.
-   **Authentication**: Endpoints might require a valid JWT (implementation to be added if needed).

#### a. Send Message (`POST`)
-   **URL**: `http://localhost/api/guidance/sessions`
-   **Method**: `POST`
-   **Request Body (JSON)**:
    ```json
    {
        "submission_id": 1,         // ID of the thesis submission
        "sender_id": "mahasiswa123", // Identity number of the sender
        "receiver_id": "dosen456",   // Identity number of the receiver
        "message": "Progress laporan bab 1 sudah 70% pak.",
        "attachments": [
            {
                "file_name": "bab1_rev1.pdf",
                "file_url": "http://example.com/files/bab1_rev1.pdf"
            }
        ]
    }
    ```
-   **Expected Response**: `{ "success": true, "data": { ...new_message_details... } }`

#### b. Get Chat History (`GET`)
-   **URL**: `http://localhost/api/guidance/sessions/{submissionId}` (replace `{submissionId}` with actual submission ID)
-   **Method**: `GET`
-   **Expected Response**: `{ "success": true, "data": [...] }` (list of messages for the specified submission)

#### c. Verify Message (`PATCH`)
-   **URL**: `http://localhost/api/guidance/verify/{chatId}` (replace `{chatId}` with actual message ID)
-   **Method**: `PATCH`
-   **Authentication**: Requires lecturer role (not implemented yet, but good to keep in mind).
-   **Request Body (JSON)**: (empty or `{}`)
    ```json
    {}
    ```
-   **Expected Response**: `{ "success": true, "data": { ...verified_message_details... } }` (message with `is_verified_by_lecturer: true`)

### 4. Monitoring Service (via Nginx: `http://localhost/api/monitor`)

-   **Description**: Provides global statistics.

#### a. Get Global Statistics (`GET`)
-   **URL**: `http://localhost/api/monitor/stats/global`
-   **Method**: `GET`
-   **Expected Response**:
    ```json
    {
        "total_submissions": 10,
        "graduated_submissions": 2,
        "average_completion_time": "N/A",
        "average_progress_percentage": 55.25
    }
    ```

## How to Test in Browser

For `GET` requests, you can simply open your web browser and navigate to the respective URL. For example, to check the health of the monitoring service, go to `http://localhost/api/monitor/health`.

## How to Test with Postman (or cURL)

For `POST`, `PUT`, `PATCH` and authenticated `GET` requests, you will need a tool like [Postman](https://www.postman.com/downloads/) or `cURL`.

1.  **Install Postman** (if you don't have it).
2.  **Create a new request** in Postman.
3.  **Set the HTTP Method** (GET, POST, PUT, PATCH).
4.  **Enter the Request URL**.
5.  If the request requires a **JSON Body**:
    *   Select `Body` tab.
    *   Choose `raw` and `JSON` from the dropdown.
    *   Paste the example JSON data.
6.  If the request requires **Authentication**:
    *   Select `Headers` tab.
    *   Add a header with `Key: Authorization` and `Value: Bearer <your_jwt_token>`.

---
**Disclaimer**:
- The `JWT_SECRET` in `.env` files should be replaced with a strong, unique secret in a production environment.
- The `AUTH_SERVICE_URL` environment variable for `submission-service` and `guidance-service` are placeholders. Implementations for inter-service communication via direct HTTP calls would need to utilize these if cross-service data fetching or validation is required. For now, the example controllers assume direct database access or simple external calls.
- The `average_completion_time` in Monitoring Service is currently "N/A" and requires more complex logic to calculate based on submission and milestone timestamps.
