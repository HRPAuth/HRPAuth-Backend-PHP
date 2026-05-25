<?php
/**
 * Auto-create profiles for existing users who don't have one
 * Run this script once to fix existing users
 * For debug purpose only!
 */

require_once __DIR__ . '/config/db.php';

// Function to generate UUID without hyphens
function generateUUID() {
    return str_replace('-', '', sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    ));
}

try {
    $db = Database::getInstance();
    $pdo = $db->getPDO();
    
    echo "=== Auto-Create Profiles Script ===\n";
    echo "Running at: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Step 1: Find users without profiles
    $stmt = $pdo->query('
        SELECT u.uuid, u.username 
        FROM users u
        LEFT JOIN profiles p ON u.uuid = p.user_id
        WHERE p.id IS NULL
    ');
    
    $usersWithoutProfiles = $stmt->fetchAll();
    
    if (empty($usersWithoutProfiles)) {
        echo "✓ All users already have profiles!\n";
        exit(0);
    }
    
    echo "Found " . count($usersWithoutProfiles) . " users without profiles:\n";
    
    // Step 2: Create profiles for each user
    $createdCount = 0;
    $failedCount = 0;
    
    foreach ($usersWithoutProfiles as $user) {
        // Skip users without UUID (shouldn't happen but just in case)
        if (empty($user['uuid'])) {
            echo "✗ Skipping user without UUID\n";
            continue;
        }
        
        try {
            $profileId = generateUUID();
            $username = $user['username'] ?: 'Player' . mt_rand(1000, 9999);
            
            $insert = $pdo->prepare(
                'INSERT INTO profiles (id, user_id, name, model) VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$profileId, $user['uuid'], $username, 'default']);
            
            echo "✓ Created profile for user: " . $user['username'] . " (UUID: " . substr($user['uuid'], 0, 8) . "...)\n";
            $createdCount++;
        } catch (Exception $e) {
            echo "✗ Failed to create profile for user: " . $user['username'] . "\n";
            echo "  Error: " . $e->getMessage() . "\n";
            $failedCount++;
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Total users without profiles: " . count($usersWithoutProfiles) . "\n";
    echo "Successfully created: " . $createdCount . "\n";
    echo "Failed: " . $failedCount . "\n";
    
    if ($failedCount === 0) {
        echo "\n✓ All profiles created successfully!\n";
    }
    
} catch (Exception $e) {
    echo "✗ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>