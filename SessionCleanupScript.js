// cleanup-sessions.js
const fs = require('fs');
const path = require('path');

// Path to the sessions directory
const SESSIONS_DIR = path.join(__dirname, 'sessions');

// Function to delete all session directories
function cleanupAllSessions() {
    console.log('Starting session cleanup...');
    
    if (!fs.existsSync(SESSIONS_DIR)) {
        console.log('Sessions directory does not exist. Creating it...');
        fs.mkdirSync(SESSIONS_DIR);
        console.log('Sessions directory created.');
        return;
    }
    
    const sessions = fs.readdirSync(SESSIONS_DIR).filter(file => 
        fs.statSync(path.join(SESSIONS_DIR, file)).isDirectory()
    );
    
    console.log(`Found ${sessions.length} session directories.`);
    
    if (sessions.length === 0) {
        console.log('No sessions to delete.');
        return;
    }
    
    // Delete each session directory
    for (const session of sessions) {
        const sessionPath = path.join(SESSIONS_DIR, session);
        try {
            console.log(`Deleting session: ${session}`);
            fs.rmSync(sessionPath, { recursive: true, force: true });
            console.log(`Successfully deleted session: ${session}`);
        } catch (error) {
            console.error(`Error deleting session ${session}:`, error);
        }
    }
    
    console.log('Session cleanup completed.');
}

// Run the cleanup
cleanupAllSessions();