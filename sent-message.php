<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Base API URL
$apiBase = "http://localhost:3000";

// Function to create a new WhatsApp session
function createSession($sessionId) {
    global $apiBase;
    
    $data = [
        "sessionId" => $sessionId
    ];
    
    $options = [
        "http" => [
            "header" => "Content-Type: application/json",
            "method" => "POST",
            "content" => json_encode($data),
            "ignore_errors" => true
        ],
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents("$apiBase/sessions/create", false, $context);
    
    // Get HTTP response code
    $statusLine = $http_response_header[0];
    preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
    $statusCode = $match[1];
    
    return [
        "statusCode" => $statusCode,
        "response" => $response
    ];
}

// Function to regenerate QR code for a session
function regenerateQR($sessionId) {
    global $apiBase;
    
    $data = [
        "sessionId" => $sessionId
    ];
    
    $options = [
        "http" => [
            "header" => "Content-Type: application/json",
            "method" => "POST",
            "content" => json_encode($data),
            "ignore_errors" => true
        ],
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents("$apiBase/sessions/$sessionId/regenerate-qr", false, $context);
    
    // Get HTTP response code
    $statusLine = $http_response_header[0];
    preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
    $statusCode = $match[1];
    
    return [
        "statusCode" => $statusCode,
        "response" => $response
    ];
}

// Function to get QR code for a session
function getSessionQR($sessionId) {
    global $apiBase;
    
    $options = [
        "http" => [
            "method" => "GET",
            "ignore_errors" => true
        ],
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents("$apiBase/sessions/$sessionId/qr", false, $context);
    
    if ($response === false) {
        return [
            "success" => false,
            "qrCode" => null,
            "error" => "Failed to fetch QR code"
        ];
    }
    
    // Get HTTP response code
    $statusLine = $http_response_header[0];
    preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
    $statusCode = $match[1];
    
    if ($statusCode == 200) {
        $data = json_decode($response, true);
        return $data;
    } else {
        return [
            "success" => false,
            "qrCode" => null,
            "error" => "Failed to fetch QR code (Status: $statusCode)"
        ];
    }
}

// Function to list all active sessions
function listSessions() {
    global $apiBase;
    
    $options = [
        "http" => [
            "method" => "GET",
            "ignore_errors" => true
        ],
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents("$apiBase/sessions", false, $context);
    
    // Get HTTP response code
    $statusLine = $http_response_header[0];
    preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
    $statusCode = $match[1];
    
    if ($statusCode == 200) {
        return json_decode($response, true);
    } else {
        return [
            "success" => false,
            "error" => "Failed to list sessions"
        ];
    }
}

// Function to delete a session
function deleteSession($sessionId) {
    global $apiBase;
    
    $options = [
        "http" => [
            "method" => "DELETE",
            "ignore_errors" => true
        ],
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents("$apiBase/sessions/$sessionId", false, $context);
    
    // Get HTTP response code
    $statusLine = $http_response_header[0];
    preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
    $statusCode = $match[1];
    
    return [
        "statusCode" => $statusCode,
        "response" => $response
    ];
}

// Function to send a message
function sendMessage($sessionId, $phoneNumber, $message) {
    global $apiBase;
    
    // Format the number correctly with country code if needed
    if (substr($phoneNumber, 0, 1) === "0") {
        $phoneNumber = "880" . substr($phoneNumber, 1); // For Bangladesh numbers
    }
    
    $data = [
        "sessionId" => $sessionId,
        "number" => $phoneNumber,
        "message" => $message
    ];
    
    $jsonData = json_encode($data);
    
    $options = [
        "http" => [
            "header" => "Content-Type: application/json",
            "method" => "POST",
            "content" => $jsonData,
            "ignore_errors" => true
        ],
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents("$apiBase/send-message", false, $context);
    
    // Get HTTP response code
    $statusLine = $http_response_header[0];
    preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
    $statusCode = $match[1];
    
    return [
        "statusCode" => $statusCode,
        "response" => $response,
        "sentData" => $jsonData
    ];
}

// Process form submissions
$result = null;
$action = $_POST['action'] ?? '';
$qrData = null;
$selectedSession = $_GET['session'] ?? '';
$regenerateQR = isset($_GET['regenerate']) && $_GET['regenerate'] === 'true';

if ($action === 'create_session') {
    $sessionId = $_POST['session_id'] ?? '';
    if (!empty($sessionId)) {
        $result = createSession($sessionId);
        $selectedSession = $sessionId;
    }
} else if ($action === 'delete_session') {
    $sessionId = $_POST['session_id'] ?? '';
    if (!empty($sessionId)) {
        $result = deleteSession($sessionId);
    }
} else if ($action === 'send_message') {
    $sessionId = $_POST['session_id'] ?? '';
    $phoneNumber = $_POST['phone_number'] ?? '';
    $message = $_POST['message'] ?? '';
    
    if (!empty($sessionId) && !empty($phoneNumber) && !empty($message)) {
        $result = sendMessage($sessionId, $phoneNumber, $message);
    }
}

// Regenerate QR if requested
if ($regenerateQR && !empty($selectedSession)) {
    $result = regenerateQR($selectedSession);
}

// Get QR code for selected session
if (!empty($selectedSession)) {
    $qrData = getSessionQR($selectedSession);
}

// Get list of sessions for display
$sessions = listSessions();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Multi-Connection Client</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #128C7E;
        }
        .card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], 
        textarea,
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #128C7E;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #075E54;
        }
        .session-list {
            list-style: none;
            padding: 0;
        }
        .session-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .connected {
            color: green;
            font-weight: bold;
        }
        .disconnected {
            color: red;
        }
        .result {
            background-color: #e8f5e9;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .error {
            background-color: #ffebee;
        }
        textarea {
            height: 100px;
        }
        .qr-container {
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .qr-code {
            margin: 10px auto;
            max-width: 250px;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            margin-right: 5px;
            background-color: #eee;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
        }
        .tab.active {
            background-color: #128C7E;
            color: white;
        }
        .actions {
            display: flex;
            gap: 10px;
        }
        .view-qr-btn {
            background-color: #0277bd;
        }
        .view-qr-btn:hover {
            background-color: #01579b;
        }
        .regenerate-btn {
            background-color: #ff9800;
        }
        .regenerate-btn:hover {
            background-color: #f57c00;
        }
    </style>
</head>
<body>
    <h1>WhatsApp Multi-Connection Client</h1>
    
    <?php if ($result): ?>
    <div class="result <?php echo ($result['statusCode'] != 200) ? 'error' : ''; ?>">
        <h3>Result</h3>
        <p>Status: <?php echo $result['statusCode']; ?></p>
        <pre><?php echo isset($result['sentData']) ? "Sent data: " . $result['sentData'] . "\n\n" : ''; ?><?php echo $result['response']; ?></pre>
    </div>
    <?php endif; ?>
    
    <?php if ($qrData && $qrData['success'] && $qrData['qrCode']): ?>
    <div class="qr-container">
        <h2>Scan QR Code for <?php echo htmlspecialchars($selectedSession); ?></h2>
        <p>Scan this QR code with your WhatsApp app to connect</p>
        <img class="qr-code" src="<?php echo $qrData['qrCode']; ?>" alt="WhatsApp QR Code">
        <p>Last updated: <?php echo date('Y-m-d H:i:s', strtotime($qrData['lastUpdate'])); ?></p>
    </div>
    <?php elseif ($qrData && !$qrData['success']): ?>
    <div class="result error">
        <h3>QR Code Error</h3>
        <p><?php echo $qrData['error']; ?></p>
        <form method="get">
            <input type="hidden" name="session" value="<?php echo htmlspecialchars($selectedSession); ?>">
            <input type="hidden" name="regenerate" value="true">
            <button type="submit" class="regenerate-btn">Generate QR Code Again</button>
        </form>
    </div>
    <?php elseif (!empty($selectedSession)): ?>
    <div class="qr-container">
        <h2>QR Code for <?php echo htmlspecialchars($selectedSession); ?></h2>
        <p>QR code expired or not available</p>
        <form method="get">
            <input type="hidden" name="session" value="<?php echo htmlspecialchars($selectedSession); ?>">
            <input type="hidden" name="regenerate" value="true">
            <button type="submit" class="regenerate-btn">Generate QR Code Again</button>
        </form>
    </div>
    <?php endif; ?>
    
    <div class="tabs">
        <div class="tab active" onclick="showTab('sessions')">Sessions</div>
        <div class="tab" onclick="showTab('messaging')">Messaging</div>
    </div>
    
    <div id="sessions-tab">
        <div class="card">
            <h2>Active Sessions</h2>
            <?php if (!empty($sessions['sessions'])): ?>
                <ul class="session-list">
                    <?php foreach ($sessions['sessions'] as $session): ?>
                        <li class="session-item">
                            <span>
                                <?php echo htmlspecialchars($session['sessionId']); ?> 
                                <span class="<?php echo $session['isConnected'] ? 'connected' : 'disconnected'; ?>">
                                    (<?php echo $session['isConnected'] ? 'Connected' : 'Disconnected'; ?>)
                                </span>
                            </span>
                            <div class="actions">
                                <form method="get" style="display: inline;">
                                    <input type="hidden" name="session" value="<?php echo htmlspecialchars($session['sessionId']); ?>">
                                    <button type="submit" class="view-qr-btn">View QR</button>
                                </form>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_session">
                                    <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($session['sessionId']); ?>">
                                    <button type="submit">Delete</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No active sessions found.</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>Create New Session</h2>
            <form method="post">
                <input type="hidden" name="action" value="create_session">
                <div class="form-group">
                    <label for="session_id">Session ID (e.g., phone1, phone2):</label>
                    <input type="text" id="session_id" name="session_id" required>
                </div>
                <button type="submit">Create Session</button>
            </form>
        </div>
    </div>
    
    <div id="messaging-tab" style="display: none;">
        <div class="card">
            <h2>Send Message</h2>
            <form method="post">
                <input type="hidden" name="action" value="send_message">
                <div class="form-group">
                    <label for="send_session_id">Session ID:</label>
                    <select id="send_session_id" name="session_id" required>
                        <option value="">Select a session</option>
                        <?php if (!empty($sessions['sessions'])): ?>
                            <?php foreach ($sessions['sessions'] as $session): ?>
                                <option value="<?php echo htmlspecialchars($session['sessionId']); ?>" 
                                    <?php echo !$session['isConnected'] ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($session['sessionId']); ?> 
                                    <?php echo !$session['isConnected'] ? '(Disconnected)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="phone_number">Phone Number:</label>
                    <input type="text" id="phone_number" name="phone_number" placeholder="e.g., 01712923446" required>
                </div>
                <div class="form-group">
                    <label for="message">Message:</label>
                    <textarea id="message" name="message" required></textarea>
                </div>
                <button type="submit">Send Message</button>
            </form>
        </div>
    </div>
    
    <script>
        // Tab switching
        function showTab(tabName) {
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab').forEach(tab => {
                if (tab.textContent.toLowerCase().includes(tabName)) {
                    tab.classList.add('active');
                }
            });
            
            document.getElementById('sessions-tab').style.display = tabName === 'sessions' ? 'block' : 'none';
            document.getElementById('messaging-tab').style.display = tabName === 'messaging' ? 'block' : 'none';
        }
    </script>
</body>
</html>