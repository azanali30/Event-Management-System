<?php
/**
 * QR Code Scanner for Admin Panel
 * Event Management System - Attendance Tracking
 */

session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';

// Handle AJAX requests for QR scanning
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    if ($_POST['action'] === 'scan_qr') {
        $qr_data = $_POST['qr_data'] ?? '';
        
        try {
            // Parse QR data
            $parsed_data = parseQRData($qr_data);
            
            if (!$parsed_data) {
                throw new Exception('Invalid QR code format');
            }
            
            // Get registration details
            $stmt = $pdo->prepare("
                SELECT 
                    r.registration_id,
                    r.student_id,
                    r.event_id,
                    u.email as student_email,
                    ud.full_name as student_name,
                    e.title as event_name,
                    e.event_date,
                    e.venue,
                    a.attended,
                    a.marked_on
                FROM registrations r
                INNER JOIN events e ON r.event_id = e.event_id
                INNER JOIN users u ON r.student_id = u.user_id
                LEFT JOIN userdetails ud ON u.user_id = ud.user_id
                LEFT JOIN attendance a ON r.event_id = a.event_id AND r.student_id = a.student_id
                WHERE r.registration_id = ?
            ");
            
            $stmt->execute([$parsed_data['registration_id']]);
            $registration = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$registration) {
                throw new Exception('Registration not found');
            }
            
            // Check if already marked attendance
            if ($registration['attended']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Attendance already marked',
                    'data' => $registration,
                    'already_marked' => true
                ]);
                exit;
            }
            
            // Mark attendance
            $stmt = $pdo->prepare("
                INSERT INTO attendance (event_id, student_id, attended, marked_on)
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE attended = 1, marked_on = NOW()
            ");
            
            $stmt->execute([$registration['event_id'], $registration['student_id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Attendance marked successfully',
                'data' => $registration
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

function parseQRData($qr_data) {
    // Try JSON format first (new format)
    $json_data = json_decode($qr_data, true);
    if ($json_data && isset($json_data['registration_id'])) {
        return $json_data;
    }
    
    // Try old text format
    $lines = explode("\n", $qr_data);
    $data = [];
    
    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $data[strtolower(trim($key))] = trim($value);
        }
    }
    
    if (isset($data['reg'])) {
        return ['registration_id' => $data['reg']];
    }
    
    return false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner - Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .scanner-section {
            padding: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        
        .scanner-panel {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
        }
        
        .scanner-panel h3 {
            color: #495057;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        
        #video {
            width: 100%;
            max-width: 400px;
            height: 300px;
            border-radius: 10px;
            background: #000;
            margin-bottom: 20px;
        }
        
        .scanner-controls {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .results-panel {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
        }
        
        .results-panel h3 {
            color: #495057;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        
        .scan-result {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #28a745;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .scan-result.error {
            border-left-color: #dc3545;
        }
        
        .scan-result.warning {
            border-left-color: #ffc107;
        }
        
        .result-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .result-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .result-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .manual-input {
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            border: 2px dashed #dee2e6;
        }
        
        .manual-input textarea {
            width: 100%;
            height: 100px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            resize: vertical;
            font-family: monospace;
        }
        
        .stats-section {
            padding: 40px;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .scanner-section {
                grid-template-columns: 1fr;
            }
            
            .scanner-controls {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-qrcode"></i> QR Code Scanner</h1>
            <p>Scan QR codes to mark attendance for events</p>
        </div>
        
        <div class="scanner-section">
            <div class="scanner-panel">
                <h3><i class="fas fa-camera"></i> Camera Scanner</h3>
                <video id="video" autoplay></video>
                <canvas id="canvas" style="display: none;"></canvas>
                
                <div class="scanner-controls">
                    <button id="startBtn" class="btn btn-primary">
                        <i class="fas fa-play"></i> Start Scanner
                    </button>
                    <button id="stopBtn" class="btn btn-danger" style="display: none;">
                        <i class="fas fa-stop"></i> Stop Scanner
                    </button>
                    <button id="switchCamera" class="btn btn-primary" style="display: none;">
                        <i class="fas fa-sync"></i> Switch Camera
                    </button>
                </div>
                
                <div class="manual-input">
                    <h4>Manual Input</h4>
                    <textarea id="manualQR" placeholder="Paste QR code data here..."></textarea>
                    <button id="processManual" class="btn btn-success" style="margin-top: 10px;">
                        <i class="fas fa-check"></i> Process QR Data
                    </button>
                </div>
            </div>
            
            <div class="results-panel">
                <h3><i class="fas fa-list"></i> Scan Results</h3>
                <div id="scanResults">
                    <p style="text-align: center; color: #6c757d; padding: 40px;">
                        <i class="fas fa-qrcode" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                        No scans yet. Start scanning QR codes to see results here.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="stats-section">
            <h3 style="text-align: center; margin-bottom: 30px; color: #495057;">
                <i class="fas fa-chart-bar"></i> Today's Statistics
            </h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number" id="totalScans">0</div>
                    <div class="stat-label">Total Scans</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="successfulScans">0</div>
                    <div class="stat-label">Successful</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="duplicateScans">0</div>
                    <div class="stat-label">Duplicates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="errorScans">0</div>
                    <div class="stat-label">Errors</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include QR Scanner Library -->
    <script src="https://unpkg.com/jsqr@1.4.0/dist/jsQR.js"></script>
    
    <script>
        // QR Scanner Implementation
        class QRScanner {
            constructor() {
                this.video = document.getElementById('video');
                this.canvas = document.getElementById('canvas');
                this.ctx = this.canvas.getContext('2d');
                this.scanning = false;
                this.stream = null;
                this.facingMode = 'environment'; // Start with back camera
                
                this.stats = {
                    total: 0,
                    successful: 0,
                    duplicates: 0,
                    errors: 0
                };
                
                this.initializeEventListeners();
            }
            
            initializeEventListeners() {
                document.getElementById('startBtn').addEventListener('click', () => this.startScanning());
                document.getElementById('stopBtn').addEventListener('click', () => this.stopScanning());
                document.getElementById('switchCamera').addEventListener('click', () => this.switchCamera());
                document.getElementById('processManual').addEventListener('click', () => this.processManualInput());
            }
            
            async startScanning() {
                try {
                    this.stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: this.facingMode }
                    });
                    
                    this.video.srcObject = this.stream;
                    this.scanning = true;
                    
                    document.getElementById('startBtn').style.display = 'none';
                    document.getElementById('stopBtn').style.display = 'inline-flex';
                    document.getElementById('switchCamera').style.display = 'inline-flex';
                    
                    this.scanFrame();
                } catch (error) {
                    console.error('Error starting camera:', error);
                    this.showResult('Error starting camera: ' + error.message, 'error');
                }
            }
            
            stopScanning() {
                this.scanning = false;
                
                if (this.stream) {
                    this.stream.getTracks().forEach(track => track.stop());
                }
                
                document.getElementById('startBtn').style.display = 'inline-flex';
                document.getElementById('stopBtn').style.display = 'none';
                document.getElementById('switchCamera').style.display = 'none';
            }
            
            async switchCamera() {
                this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';
                this.stopScanning();
                await new Promise(resolve => setTimeout(resolve, 100));
                this.startScanning();
            }
            
            scanFrame() {
                if (!this.scanning) return;
                
                if (this.video.readyState === this.video.HAVE_ENOUGH_DATA) {
                    this.canvas.width = this.video.videoWidth;
                    this.canvas.height = this.video.videoHeight;
                    this.ctx.drawImage(this.video, 0, 0);
                    
                    const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
                    const code = jsQR(imageData.data, imageData.width, imageData.height);
                    
                    if (code) {
                        this.processQRCode(code.data);
                    }
                }
                
                requestAnimationFrame(() => this.scanFrame());
            }
            
            processManualInput() {
                const qrData = document.getElementById('manualQR').value.trim();
                if (qrData) {
                    this.processQRCode(qrData);
                    document.getElementById('manualQR').value = '';
                }
            }
            
            async processQRCode(qrData) {
                this.stats.total++;
                this.updateStats();
                
                try {
                    const response = await fetch('qr_scanner.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=scan_qr&qr_data=${encodeURIComponent(qrData)}`
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.stats.successful++;
                        this.showResult(`Attendance marked for ${result.data.student_name}`, 'success', result.data);
                    } else if (result.already_marked) {
                        this.stats.duplicates++;
                        this.showResult(`Already marked: ${result.data.student_name}`, 'warning', result.data);
                    } else {
                        this.stats.errors++;
                        this.showResult(result.message, 'error');
                    }
                } catch (error) {
                    this.stats.errors++;
                    this.showResult('Network error: ' + error.message, 'error');
                }
                
                this.updateStats();
            }
            
            showResult(message, type, data = null) {
                const resultsContainer = document.getElementById('scanResults');
                
                // Clear "no scans" message
                if (resultsContainer.querySelector('p')) {
                    resultsContainer.innerHTML = '';
                }
                
                const resultDiv = document.createElement('div');
                resultDiv.className = `scan-result ${type}`;
                
                const statusClass = type === 'success' ? 'status-success' : 
                                  type === 'warning' ? 'status-warning' : 'status-error';
                
                const statusIcon = type === 'success' ? 'fa-check-circle' : 
                                 type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle';
                
                let detailsHtml = '';
                if (data) {
                    detailsHtml = `
                        <div class="result-details">
                            <div><strong>Student:</strong> ${data.student_name || 'N/A'}</div>
                            <div><strong>Event:</strong> ${data.event_name || 'N/A'}</div>
                            <div><strong>Date:</strong> ${data.event_date || 'N/A'}</div>
                            <div><strong>Venue:</strong> ${data.venue || 'N/A'}</div>
                        </div>
                    `;
                }
                
                resultDiv.innerHTML = `
                    <div class="result-header">
                        <span><i class="fas ${statusIcon}"></i> ${message}</span>
                        <span class="result-status ${statusClass}">${type.toUpperCase()}</span>
                    </div>
                    ${detailsHtml}
                    <div style="font-size: 0.8rem; color: #6c757d; margin-top: 10px;">
                        ${new Date().toLocaleTimeString()}
                    </div>
                `;
                
                resultsContainer.insertBefore(resultDiv, resultsContainer.firstChild);
                
                // Keep only last 10 results
                while (resultsContainer.children.length > 10) {
                    resultsContainer.removeChild(resultsContainer.lastChild);
                }
            }
            
            updateStats() {
                document.getElementById('totalScans').textContent = this.stats.total;
                document.getElementById('successfulScans').textContent = this.stats.successful;
                document.getElementById('duplicateScans').textContent = this.stats.duplicates;
                document.getElementById('errorScans').textContent = this.stats.errors;
            }
        }
        
        // Initialize scanner when page loads
        document.addEventListener('DOMContentLoaded', () => {
            new QRScanner();
        });
    </script>
</body>
</html>
