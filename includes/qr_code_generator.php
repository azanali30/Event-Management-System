<?php
/**
 * QR Code Generator for Event Registration System
 * Generates unique QR codes for approved registrations containing:
 * - Student Name
 * - Student ID  
 * - Event Title
 * - Event Date
 * - Registration ID
 */

// QR Code generation using online API only for better reliability

class QRCodeGenerator {
    
    /**
     * Generate QR code for registration
     * @param array $registration_data - Registration data from database
     * @return array - Contains success status, qr_code_data, and file_path
     */
    public static function generateRegistrationQR($registration_data) {
        try {
            // Validate required data
            if (!isset($registration_data['id']) || !isset($registration_data['student_name']) ||
                !isset($registration_data['event_title']) || !isset($registration_data['event_date'])) {
                $missing = [];
                if (!isset($registration_data['id'])) $missing[] = 'id';
                if (!isset($registration_data['student_name'])) $missing[] = 'student_name';
                if (!isset($registration_data['event_title'])) $missing[] = 'event_title';
                if (!isset($registration_data['event_date'])) $missing[] = 'event_date';
                throw new Exception('Missing required registration data for QR generation: ' . implode(', ', $missing));
            }
            
            // Create simplified QR code data (shorter for better compatibility)
            $qr_data = json_encode([
                'name' => $registration_data['student_name'],
                'id' => $registration_data['student_id'] ?? 'N/A',
                'event' => $registration_data['event_title'],
                'date' => $registration_data['event_date'],
                'reg_id' => $registration_data['id'],
                'hash' => substr(md5($registration_data['student_id'] . $registration_data['event_title'] . $registration_data['event_date']), 0, 8)
            ]);
            
            // Always use online QR code API for better reliability
            $qr_png_data = self::generateQRCodeViaAPI($qr_data);

            // Basic validation - just check if we got data and it's a PNG
            if (empty($qr_png_data)) {
                throw new Exception('No QR code data received from API');
            }

            // Verify it's a valid PNG (less strict check)
            if (substr($qr_png_data, 0, 4) !== "\x89PNG") {
                throw new Exception('Generated QR code is not a valid PNG format');
            }
            
            // Create directory if it doesn't exist
            $qr_dir = __DIR__ . '/../uploads/qr_codes/';
            if (!is_dir($qr_dir)) {
                mkdir($qr_dir, 0755, true);
            }
            
            // Generate unique filename
            $filename = 'qr_' . $registration_data['id'] . '_' . time() . '.png';
            $file_path = $qr_dir . $filename;
            $relative_path = 'uploads/qr_codes/' . $filename;

            // Save QR code to file
            file_put_contents($file_path, $qr_png_data);

            // Get base64 encoded data for database storage
            $qr_code_base64 = base64_encode($qr_png_data);

            return [
                'success' => true,
                'qr_code_data' => $qr_code_base64,
                'file_path' => $relative_path,
                'full_path' => $file_path,
                'data_uri' => 'data:image/png;base64,' . $qr_code_base64,
                'raw_data' => $qr_data
            ];
            
        } catch (Exception $e) {
            error_log('QR Code generation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate verification hash for QR code security
     */
    private static function generateVerificationHash($registration_data) {
        $hash_data = $registration_data['id'] . 
                    $registration_data['student_name'] . 
                    $registration_data['event_title'] . 
                    $registration_data['event_date'] . 
                    'EVENTSPHERE_SECRET_2024';
        return hash('sha256', $hash_data);
    }
    
    /**
     * Verify QR code data integrity
     */
    public static function verifyQRCode($qr_data_json) {
        try {
            $qr_data = json_decode($qr_data_json, true);
            
            if (!$qr_data || !isset($qr_data['verification_hash'])) {
                return false;
            }
            
            // Recreate hash and verify
            $expected_hash = self::generateVerificationHash($qr_data);
            return hash_equals($expected_hash, $qr_data['verification_hash']);
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get QR code as data URI for display
     */
    public static function getQRCodeDataUri($base64_data) {
        return 'data:image/png;base64,' . $base64_data;
    }
    
    /**
     * Create QR code download response
     */
    public static function downloadQRCode($registration_id, $pdo) {
        try {
            // Get registration data with proper table structure
            $stmt = $pdo->prepare("
                SELECT
                    r.registration_id,
                    r.student_id,
                    r.event_id,
                    r.status,
                    u.email as student_email,
                    ud.full_name as student_name,
                    e.title as event_title,
                    e.event_date,
                    e.event_time,
                    e.venue,
                    e.category
                FROM registrations r
                JOIN events e ON r.event_id = e.event_id
                JOIN users u ON r.student_id = u.user_id
                LEFT JOIN userdetails ud ON u.user_id = ud.user_id
                WHERE r.registration_id = ? AND r.status IN ('confirmed', 'waitlist')
            ");
            $stmt->execute([$registration_id]);
            $registration = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registration) {
                throw new Exception('Registration not found or not confirmed');
            }

            // Prepare registration data for QR generation
            $qr_registration_data = [
                'id' => $registration['registration_id'],
                'student_id' => $registration['student_id'],
                'student_name' => $registration['student_name'] ?: 'Unknown Student',
                'event_title' => $registration['event_title'],
                'event_date' => $registration['event_date'],
                'event_time' => $registration['event_time'],
                'venue' => $registration['venue']
            ];

            // Generate QR code
            $qr_result = self::generateRegistrationQR($qr_registration_data);
            if (!$qr_result['success']) {
                throw new Exception('Failed to generate QR code: ' . $qr_result['error']);
            }

            // Create safe filename by removing special characters and spaces
            $safe_student_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $qr_registration_data['student_name']);
            $safe_event_title = preg_replace('/[^a-zA-Z0-9_-]/', '_', $registration['event_title']);
            $safe_filename = 'QR_' . $safe_student_name . '_' . $safe_event_title . '_' . $registration_id . '.png';

            // Return QR code data for download
            return [
                'success' => true,
                'qr_data' => $qr_result['qr_code_data'],
                'filename' => $safe_filename,
                'mime_type' => 'image/png'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check user access to registration QR code
     */
    public static function hasQRAccess($user_id, $user_role, $registration_id, $pdo) {
        try {
            // Admin can access all QR codes
            if ($user_role === 'admin') {
                return true;
            }

            // Get registration data to check ownership
            $stmt = $pdo->prepare("SELECT student_id FROM registrations WHERE registration_id = ?");
            $stmt->execute([$registration_id]);
            $registration = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registration) {
                return false;
            }

            // Regular users can only access their own registrations
            return $user_id == $registration['student_id'];

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Generate QR code data for registration (without saving to DB)
     */
    public static function generateQRData($registration) {
        return json_encode([
            'name' => $registration['student_name'],
            'student_id' => $registration['student_id'],
            'event' => $registration['event_title'],
            'date' => $registration['event_date'],
            'time' => $registration['event_time'] ?? null,
            'venue' => $registration['venue'],
            'reg_id' => $registration['id'],
            'hash' => substr(md5($registration['student_id'] . $registration['event_title'] . $registration['event_date']), 0, 8)
        ]);
    }

    /**
     * Generate QR code using online API
     */
    private static function generateQRCodeViaAPI($data) {
        $qr_text = urlencode($data);
        // Use larger size and better error correction
        $api_url = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&ecc=M&format=png&data=" . $qr_text;

        // Use cURL to fetch the QR code image
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'EventSphere QR Generator');

        $qr_png_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($http_code === 200 && $qr_png_data !== false && empty($curl_error)) {
            // Basic validation - check if it looks like a PNG
            if (substr($qr_png_data, 0, 4) === "\x89PNG" && strlen($qr_png_data) > 100) {
                return $qr_png_data;
            } else {
                throw new Exception("API returned invalid or corrupted PNG data");
            }
        } else {
            $error_msg = "Failed to generate QR code via API. HTTP: $http_code";
            if (!empty($curl_error)) {
                $error_msg .= ", cURL Error: $curl_error";
            }
            throw new Exception($error_msg);
        }
    }
}
