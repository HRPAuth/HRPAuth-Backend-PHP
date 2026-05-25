<?php

namespace App\services;

class AuthService {
    
    private $db;
    
    public function __construct() {
        $this->db = \Database::getInstance();
    }
    
    public function verifyCredentials($identifier, $password, $allowUsernameLogin = false) {
        $config = require __DIR__ . '/../../config/zggdrasilapi.php';
        $nonEmailLogin = $allowUsernameLogin ?? $config['feature_flags']['non_email_login'];
        
        if ($nonEmailLogin) {
            $stmt = $this->db->query('SELECT u.uuid, u.email, u.username, u.password, u.locale FROM users u JOIN profiles p ON u.uuid = p.user_id WHERE u.email = ? OR p.name = ?', [$identifier, $identifier]);
            $user = $stmt->fetch();
        } else {
            $stmt = $this->db->query('SELECT uuid, email, username, password, locale FROM users WHERE email = ?', [$identifier]);
            $user = $stmt->fetch();
        }
        
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        
        return $user;
    }
    
    public function getUserProfiles($userId) {
        $stmt = $this->db->query('SELECT id, name, model FROM profiles WHERE user_id = ?', [$userId]);
        $profiles = [];
        
        while ($profile = $stmt->fetch()) {
            $profileData = ['id' => $profile['id'], 'name' => $profile['name']];
            if ($profile['model']) {
                $profileData['model'] = $profile['model'];
            }
            $profiles[] = $profileData;
        }
        
        return $profiles;
    }
    
    public function createToken($accessToken, $clientToken, $userId, $profileId, $expiresInDays) {
        $issuedAt = $this->getCurrentTimestamp();
        
        try {
            $this->db->query(
                'INSERT INTO tokens (access_token, client_token, user_id, selected_profile_id, issued_at, expires_in_days, state) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$accessToken, $clientToken, $userId, $profileId, $issuedAt, $expiresInDays, 'valid']
            );
            return true;
        } catch (\PDOException $e) {
            error_log('Token insertion failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function invalidateToken($accessToken) {
        return $this->db->query('UPDATE tokens SET state = ? WHERE access_token = ?', ['invalid', $accessToken]);
    }
    
    public function validateToken($accessToken, $clientToken = null) {
        $stmt = $this->db->query('SELECT * FROM tokens WHERE access_token = ? AND state = ?', [$accessToken, 'valid']);
        $token = $stmt->fetch();
        
        if (!$token) {
            return false;
        }
        
        if ($clientToken && $clientToken !== $token['client_token']) {
            return false;
        }
        
        return $token;
    }
    
    public function getProfileById($profileId) {
        $stmt = $this->db->query('SELECT p.id, u.username, p.model FROM profiles p JOIN users u ON p.user_id = u.uuid WHERE p.id = ?', [$profileId]);
        return $stmt->fetch();
    }
    
    public function isProfileOwnedByUser($profileId, $userId) {
        $stmt = $this->db->query('SELECT id FROM profiles WHERE id = ? AND user_id = ?', [$profileId, $userId]);
        return (bool)$stmt->fetch();
    }
    
    private function getCurrentTimestamp() {
        return round(microtime(true) * 1000);
    }
}