<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../app/config/database.php';

$options = getopt('', ['email:', 'password:', 'name::']);

$email = isset($options['email']) ? trim((string) $options['email']) : '';
$password = isset($options['password']) ? (string) $options['password'] : '';
$fullName = isset($options['name']) ? trim((string) $options['name']) : 'Admin Principal';

if ($email === '' || $password === '') {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php scripts/create_admin.php --email=\"admin@email.com\" --password=\"MotDePasseFort123!\" --name=\"Admin Principal\"\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email format.\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admins (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(191) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(255),
            status ENUM('active', 'suspended') DEFAULT 'active',
            last_login_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $existingStmt = $pdo->prepare('SELECT id FROM admins WHERE email = :email LIMIT 1');
    $existingStmt->execute(['email' => $email]);
    $existingAdmin = $existingStmt->fetch();

    if ($existingAdmin) {
        $updateStmt = $pdo->prepare(
            'UPDATE admins
             SET password_hash = :password_hash, full_name = :full_name, status = :status
             WHERE id = :id'
        );
        $updateStmt->execute([
            'password_hash' => $passwordHash,
            'full_name' => $fullName,
            'status' => 'active',
            'id' => (int) $existingAdmin['id'],
        ]);

        fwrite(STDOUT, "Admin account updated and activated: {$email}\n");
        exit(0);
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO admins (email, password_hash, full_name, status)
         VALUES (:email, :password_hash, :full_name, :status)'
    );
    $insertStmt->execute([
        'email' => $email,
        'password_hash' => $passwordHash,
        'full_name' => $fullName,
        'status' => 'active',
    ]);

    fwrite(STDOUT, "Admin account created: {$email}\n");
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, "Failed to create/update admin account: " . $throwable->getMessage() . "\n");
    exit(1);
}
