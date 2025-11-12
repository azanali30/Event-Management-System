<?php
/**
 * QR Code Generator Class
 * Handles QR code generation, storage, and management
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;

class QRCodeGenerator {
    private $pdo;
    private $qrCodesDir;
    private $baseUrl;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->qrCodesDir = __DIR__ . '/../qr_codes/';
        $this->baseUrl = $this->getBaseUrl();
        
        // Create QR codes directory if it doesn't exist
        if (!is_dir($this->qrCodesDir)) {
            mkdir($this->qrCodesDir, 0755, true);
        }
    }
    
    /**
     * Get base URL for the application
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['REQUEST_URI'] ?? '');
        return $protocol . '://' . $host . $path;
    }
    
    /**
     * Generate unique UID for registration
     */
    public function generateUID($prefix = 'USER') {
        do {
            $uid = $prefix . strtoupper(substr(uniqid(), -3)) . rand(100, 999);
            
            // Check if UID already exists
            $stmt = $this->pdo->prepare("SELECT registration_id FROM registrations WHERE uid = ?");
            $stmt->execute([$uid]);
            $exists = $stmt->fetch();
            
        } while ($exists);
        
        return $uid;
    }
    
    /**
     * Generate UID for approved registration
     */
    public function generateUIDForRegistration($registrationId) {
        try {
            // Check if registration exists and is approved
            $stmt = $this->pdo->prepare("
                SELECT r.*, e.title as event_title, u.email, ud.full_name 
                FROM registrations r 
                JOIN events e ON r.event_id = e.event_id 
                JOIN users u ON r.student_id = u.user_id 
                LEFT JOIN userdetails ud ON u.user_id = ud.user_id 
                WHERE r.registration_id = ? AND r.status = 'confirmed'
            ");
            $stmt->execute([$registrationId]);
            $registration = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$registration) {
                throw new Exception('Registration not found or not approved');
            }
            
            // Generate UID if not exists
            if (empty($registration['uid'])) {
                $uid = $this->generateUID();
                
                // Update registration with UID
                $updateStmt = $this->pdo->prepare("UPDATE registrations SET uid = ? WHERE registration_id = ?");
                $updateStmt->execute([$uid, $registrationId]);
                
                $registration['uid'] = $uid;
            }
            
            return $registration;
            
        } catch (Exception $e) {
            error_log("Error generating UID: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate QR code for registration
     */
    public function generateQRCode($registrationId) {
        try {
            // Get registration with UID
            $registration = $this->generateUIDForRegistration($registrationId);
            $uid = $registration['uid'];
            
            // Create attendance URL
            $attendanceUrl = $this->baseUrl . '/attendance.php?uid=' . $uid;
            
            // Create QR code
            $qrCode = QrCode::create($attendanceUrl)
                ->setEncoding(new Encoding('UTF-8'))
                ->setErrorCorrectionLevel(new ErrorCorrectionLevelLow())
                ->setSize(300)
                ->setMargin(10)
                ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin());
            
            // Create PNG writer
            $writer = new PngWriter();
            
            // Generate file path
            $fileName = $uid . '.png';
            $filePath = $this->qrCodesDir . $fileName;
            $relativePath = 'qr_codes/' . $fileName;
            
            // Write QR code to file
            $result = $writer->write($qrCode);
            file_put_contents($filePath, $result->getString());
            
            // Update database with QR path
            $stmt = $this->pdo->prepare("UPDATE registrations SET qr_path = ? WHERE registration_id = ?");
            $stmt->execute([$relativePath, $registrationId]);
            
            return [
                'success' => true,
                'uid' => $uid,
                'qr_path' => $relativePath,
                'full_path' => $filePath,
                'attendance_url' => $attendanceUrl,
                'registration' => $registration
            ];
            
        } catch (Exception $e) {
            error_log("QR Code generation error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get QR code download URL
     */
    public function getDownloadUrl($uid) {
        return $this->baseUrl . '/download_qr_code.php?uid=' . $uid;
    }
    
    /**
     * Check if QR code exists for registration
     */
    public function qrCodeExists($registrationId) {
        try {
            $stmt = $this->pdo->prepare("SELECT qr_path, uid FROM registrations WHERE registration_id = ?");
            $stmt->execute([$registrationId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['qr_path'])) {
                $fullPath = $this->qrCodesDir . basename($result['qr_path']);
                return file_exists($fullPath) ? $result : false;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error checking QR code existence: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete QR code file and database entry
     */
    public function deleteQRCode($registrationId) {
        try {
            $stmt = $this->pdo->prepare("SELECT qr_path FROM registrations WHERE registration_id = ?");
            $stmt->execute([$registrationId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['qr_path'])) {
                $fullPath = $this->qrCodesDir . basename($result['qr_path']);
                
                // Delete file if exists
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
                
                // Clear database entry
                $updateStmt = $this->pdo->prepare("UPDATE registrations SET qr_path = NULL WHERE registration_id = ?");
                $updateStmt->execute([$registrationId]);
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error deleting QR code: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Download QR code for registration
     */
    public static function downloadQRCode($registrationId, $pdo) {
        try {
            // Get registration details
            $stmt = $pdo->prepare("
                SELECT r.*, e.title as event_title, u.email, ud.full_name
                FROM registrations r
                JOIN events e ON r.event_id = e.event_id
                JOIN users u ON r.student_id = u.user_id
                LEFT JOIN userdetails ud ON u.user_id = ud.user_id
                WHERE r.registration_id = ? AND r.status = 'confirmed'
            ");
            $stmt->execute([$registrationId]);
            $registration = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registration) {
                throw new Exception('Registration not found or not confirmed');
            }

            // Generate QR code if it doesn't exist
            $generator = new self($pdo);
            $qrResult = $generator->generateQRCode($registrationId);

            if (!$qrResult['success']) {
                throw new Exception($qrResult['error']);
            }

            // Read the QR code file
            $filePath = $qrResult['full_path'];
            if (!file_exists($filePath)) {
                throw new Exception('QR code file not found');
            }

            $qrData = file_get_contents($filePath);
            $filename = 'qr_code_' . $registration['registration_id'] . '_' . time() . '.png';

            return [
                'success' => true,
                'qr_data' => base64_encode($qrData),
                'filename' => $filename,
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
     * Get Canva QR prompt text
     */
    public function getCanvaPrompt($registrationId) {
        try {
            $registration = $this->generateUIDForRegistration($registrationId);
            $attendanceUrl = $this->baseUrl . '/attendance.php?uid=' . $registration['uid'];

            $prompt = "QR Code for " . ($registration['full_name'] ?: $registration['email']) . "\n";
            $prompt .= "Event: " . $registration['event_title'] . "\n\n";
            $prompt .= "Use this URL in Canva's QR generator:\n";
            $prompt .= $attendanceUrl . "\n\n";
            $prompt .= "Instructions:\n";
            $prompt .= "1. Open Canva QR Code generator\n";
            $prompt .= "2. Paste the URL above\n";
            $prompt .= "3. Customize design as needed\n";
            $prompt .= "4. Download and distribute to student";

            return $prompt;
        } catch (Exception $e) {
            return "Error generating prompt: " . $e->getMessage();
        }
    }
}
?>
