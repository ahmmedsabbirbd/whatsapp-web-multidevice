const crypto = require('crypto');
// Make crypto available globally for Baileys library
global.crypto = crypto;

const { makeWASocket, useMultiFileAuthState } = require("@whiskeysockets/baileys");
const express = require("express");
const fs = require('fs');
const path = require('path');
const qrcode = require('qrcode');
const cors = require('cors');

const app = express();
app.use(express.json());
app.use(cors()); // Enable CORS for all routes

// Store active connections and QR codes
const connections = {};
const qrCodes = {};

// Create sessions directory if it doesn't exist
const SESSIONS_DIR = path.join(__dirname, 'sessions');
if (!fs.existsSync(SESSIONS_DIR)) {
    fs.mkdirSync(SESSIONS_DIR);
}


// Get all available sessions
function getAvailableSessions() {
    if (!fs.existsSync(SESSIONS_DIR)) return [];
    return fs.readdirSync(SESSIONS_DIR).filter(file => 
        fs.statSync(path.join(SESSIONS_DIR, file)).isDirectory()
    );
}

// Force QR generation for a session
async function forceGenerateQR(sessionId) {
    // Check if session exists
    if (!connections[sessionId]) {
        return {
            success: false,
            error: "Session not found"
        };
    }
    
    // Delete existing session from connections
    delete connections[sessionId];
    
    // Delete qrCode data
    delete qrCodes[sessionId];
    
    // Create a new connection to get a fresh QR code
    await createConnection(sessionId);
    
    return {
        success: true,
        message: "QR code regeneration initiated"
    };
}

// Create a WhatsApp connection for a specific session ID
async function createConnection(sessionId) {
    // Use a unique folder for each session's auth info
    const sessionPath = path.join(SESSIONS_DIR, sessionId);
    if (!fs.existsSync(sessionPath)) {
        fs.mkdirSync(sessionPath, { recursive: true });
    }

    const { state, saveCreds } = await useMultiFileAuthState(sessionPath);

    // Set initial QR code state
    qrCodes[sessionId] = {
        qr: null,
        error: null,
        lastUpdate: new Date()
    };

    const sock = makeWASocket({
        auth: state,
        printQRInTerminal: false  // Set to false to disable QR code in terminal
    });

    sock.ev.on("creds.update", saveCreds);

    // Handle QR code events
    sock.ev.on("connection.update", (update) => {
        const { connection, lastDisconnect, qr } = update;
        
        // Save QR code if available
        if (qr) {
            console.log(`QR Code received for session: ${sessionId}. Check web interface to scan.`);
            
            // Convert QR code to data URL
            qrcode.toDataURL(qr, (err, url) => {
                if (err) {
                    qrCodes[sessionId] = {
                        qr: null,
                        error: "Failed to generate QR code: " + err.message,
                        lastUpdate: new Date()
                    };
                } else {
                    qrCodes[sessionId] = {
                        qr: url,
                        error: null,
                        lastUpdate: new Date()
                    };
                }
            });
        }
        
        if (connection === "open") {
            console.log(`✅ WhatsApp Connected for session: ${sessionId}`);
            connections[sessionId].isConnected = true;
            
            // Clear QR code after successful connection
            qrCodes[sessionId] = {
                qr: null,
                error: null,
                lastUpdate: new Date()
            };
        } else if (connection === "close") {
            console.log(`❌ Disconnected session: ${sessionId}, attempting to reconnect...`);
            connections[sessionId].isConnected = false;
            
            // Reconnect after a short delay
            setTimeout(() => {
                if (connections[sessionId]) {
                    delete connections[sessionId];
                    createConnection(sessionId);
                }
            }, 5000);
        }
    });

    // Save connection info
    connections[sessionId] = {
        sock,
        isConnected: false,
        createdAt: new Date()
    };

    return sock;
}

// Initialize all existing sessions
async function initializeAllSessions() {
    const sessions = getAvailableSessions();
    console.log(`Found ${sessions.length} existing sessions`);
    
    for (const session of sessions) {
        await createConnection(session);
    }
}

// API routes
// Create a new session - improved with better validation
app.post("/sessions/create", async (req, res) => {
    const { sessionId } = req.body;
    
    if (!sessionId) {
        return res.status(400).json({ success: false, error: "Session ID is required" });
    }
    
    try {
        // Check if session already exists
        if (connections[sessionId]) {
            console.log(`Session ${sessionId} already exists. Closing and recreating.`);
            
            // Close existing connection if possible
            try {
                if (connections[sessionId].sock && typeof connections[sessionId].sock.close === 'function') {
                    connections[sessionId].sock.close();
                }
            } catch (socketError) {
                console.log(`Error closing existing socket: ${socketError.message}`);
            }
            
            // Remove from memory
            delete connections[sessionId];
            delete qrCodes[sessionId];
        }
        
        // Check if session directory exists
        const sessionPath = path.join(SESSIONS_DIR, sessionId);
        if (fs.existsSync(sessionPath)) {
            console.log(`Session directory exists. Removing for clean start: ${sessionPath}`);
            fs.rmSync(sessionPath, { recursive: true, force: true });
        }
        
        // Create new connection
        console.log(`Creating new session: ${sessionId}`);
        await createConnection(sessionId);
        
        res.json({ 
            success: true, 
            message: "Session created. Scan the QR code when it appears in the web interface." 
        });
    } catch (error) {
        console.error(`Error creating session ${sessionId}:`, error);
        res.status(500).json({ 
            success: false, 
            error: `Failed to create session: ${error.message}` 
        });
    }
});

// Regenerate QR code for a session
app.post("/sessions/:sessionId/regenerate-qr", async (req, res) => {
    const { sessionId } = req.params;
    
    try {
        const result = await forceGenerateQR(sessionId);
        if (result.success) {
            res.json({ success: true, message: result.message });
        } else {
            res.status(404).json({ success: false, error: result.error });
        }
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// Get QR code for a session
app.get("/sessions/:sessionId/qr", (req, res) => {
    const { sessionId } = req.params;
    
    console.log(`QR Code Request: Session ID = ${sessionId}`);
    console.log(`Available QR Codes: ${Object.keys(qrCodes).join(', ')}`);
    
    if (!qrCodes[sessionId]) {
        console.log(`Error: QR code for session ${sessionId} not found`);
        return res.status(404).json({ 
            success: false, 
            error: "Session not found or QR code not yet generated" 
        });
    }
    
    console.log(`QR Status for ${sessionId}: QR present = ${qrCodes[sessionId].qr !== null}, Error = ${qrCodes[sessionId].error}`);
    
    res.json({
        success: true,
        qrCode: qrCodes[sessionId].qr,
        error: qrCodes[sessionId].error,
        lastUpdate: qrCodes[sessionId].lastUpdate
    });
});

// Get all active sessions
app.get("/sessions", (req, res) => {
    const activeSessions = Object.keys(connections).map(id => ({
        sessionId: id,
        isConnected: connections[id].isConnected,
        createdAt: connections[id].createdAt,
        hasQrCode: qrCodes[id] && qrCodes[id].qr !== null
    }));
    
    res.json({ success: true, sessions: activeSessions });
});

// Delete a session - improved with better cleanup
app.delete("/sessions/:sessionId", (req, res) => {
    const { sessionId } = req.params;
    
    try {
        // First, check if the session exists
        if (connections[sessionId]) {
            console.log(`Deleting session: ${sessionId}`);
            
            // Get the session path
            const sessionPath = path.join(SESSIONS_DIR, sessionId);
            
            // 1. Close the socket connection if it exists
            try {
                if (connections[sessionId].sock && typeof connections[sessionId].sock.close === 'function') {
                    connections[sessionId].sock.close();
                }
            } catch (socketError) {
                console.log(`Error closing socket: ${socketError.message}`);
                // Continue with deletion even if socket close fails
            }
            
            // 2. Remove session directory
            if (fs.existsSync(sessionPath)) {
                console.log(`Removing session directory: ${sessionPath}`);
                fs.rmSync(sessionPath, { recursive: true, force: true });
            }
            
            // 3. Clean up session objects
            console.log(`Removing session from memory`);
            delete connections[sessionId];
            delete qrCodes[sessionId];
            
            // 4. Provide success response
            res.json({ 
                success: true, 
                message: "Session deleted successfully" 
            });
        } else {
            // Session not found in connections, but directory might still exist
            const sessionPath = path.join(SESSIONS_DIR, sessionId);
            if (fs.existsSync(sessionPath)) {
                console.log(`Session not in memory but directory exists. Removing: ${sessionPath}`);
                fs.rmSync(sessionPath, { recursive: true, force: true });
                res.json({ 
                    success: true, 
                    message: "Session directory deleted successfully" 
                });
            } else {
                res.status(404).json({ 
                    success: false, 
                    error: "Session not found" 
                });
            }
        }
    } catch (error) {
        console.error(`Error deleting session ${sessionId}:`, error);
        res.status(500).json({ 
            success: false, 
            error: `Failed to delete session: ${error.message}` 
        });
    }
});

// Send a message using a specific session
app.post("/send-message", async (req, res) => {
    const { sessionId, number, message } = req.body;
    
    if (!sessionId || !number || !message) {
        return res.status(400).json({ 
            success: false, 
            error: "sessionId, number, and message are required" 
        });
    }
    
    if (!connections[sessionId]) {
        return res.status(404).json({ success: false, error: "Session not found" });
    }
    
    if (!connections[sessionId].isConnected) {
        return res.status(400).json({ 
            success: false, 
            error: "Session is not connected. Please scan the QR code first." 
        });
    }
    
    try {
        const formattedNumber = `${number}@s.whatsapp.net`;
        await connections[sessionId].sock.sendMessage(formattedNumber, { text: message });
        res.json({ success: true, message: "Message sent successfully!" });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// Debugging endpoint
app.get("/debug/qrcodes", (req, res) => {
    const debugData = {};
    
    for (const sessionId in qrCodes) {
        debugData[sessionId] = {
            hasQR: qrCodes[sessionId].qr !== null,
            error: qrCodes[sessionId].error,
            lastUpdate: qrCodes[sessionId].lastUpdate
        };
    }
    
    res.json({
        success: true,
        qrCodesStatus: debugData,
        activeSessions: Object.keys(connections)
    });
});

// Start the server
async function startServer() {
    // Initialize existing sessions
    await initializeAllSessions();
    
    // Start the API server
    const PORT = process.env.PORT || 3000;
    app.listen(PORT, () => {
        console.log(`🚀 API server running on http://localhost:${PORT}`);
        console.log(`Available endpoints:`);
        console.log(`- POST /sessions/create - Create a new WhatsApp session`);
        console.log(`- POST /sessions/:sessionId/regenerate-qr - Regenerate QR code for a session`);
        console.log(`- GET /sessions/:sessionId/qr - Get QR code for a session`);
        console.log(`- GET /sessions - List all active sessions`);
        console.log(`- DELETE /sessions/:sessionId - Delete a session`);
        console.log(`- POST /send-message - Send a message using a specific session`);
        console.log(`- GET /debug/qrcodes - Debug QR code status`);
    });
}

startServer();