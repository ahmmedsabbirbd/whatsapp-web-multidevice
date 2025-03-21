# WhatsApp Multi-Phone API with QR Code Web Interface

This document provides step-by-step instructions for setting up and running the WhatsApp Multi-Phone API with QR Code Web Interface.

## Requirements

- Node.js (v14 or later)
- PHP (v7.4 or later)
- A web server (Apache, Nginx, etc.)

## Installation

### Step 1: Install the Node.js Server Dependencies

```bash
# Create a new directory for the project
mkdir whatsapp-multi-phone
cd whatsapp-multi-phone

# Initialize a new Node.js project
npm init -y

# Install required dependencies
npm install @whiskeysockets/baileys qrcode-terminal express qrcode cors

# Create the sessions directory
mkdir sessions
```

### Step 2: Set Up the Server

1. Create a file named `server.js` in your project directory
2. Copy the entire code from the "WhatsApp Server with Web QR Code Support" file
3. Save the file

### Step 3: Set Up the PHP Web Client

1. Create a file named `whatsapp-client.php` in your web server's directory (e.g., htdocs, www, public_html)
2. Copy the entire code from the "Web WhatsApp Client with QR Code Display" file
3. Save the file

### Step 4: Start the Server

Run the Node.js server:

```bash
node server.js
```

You should see output similar to:

```
🚀 API server running on http://localhost:3000
Available endpoints:
- POST /sessions/create - Create a new WhatsApp session
- GET /sessions/:sessionId/qr - Get QR code for a session
- GET /sessions - List all active sessions
- DELETE /sessions/:sessionId - Delete a session
- POST /send-message - Send a message using a specific session
```

### Step 5: Access the Web Interface

Open your web browser and navigate to:

```
http://localhost/whatsapp-client.php
```

Replace `localhost` with your server's address if you're not running it locally.

## Using the System

### Creating a New WhatsApp Connection

1. In the web interface, go to the "Sessions" tab
2. Enter a session ID (e.g., "phone1") in the "Create New Session" section
3. Click "Create Session"
4. The QR code will appear on the page
5. Scan the QR code with your WhatsApp mobile app:
   - Open WhatsApp on your phone
   - Tap the three dots (⋮) in the top right
   - Select "Linked Devices"
   - Tap "Link a Device"
   - Scan the QR code displayed in your web browser

### Sending Messages

1. In the web interface, go to the "Messaging" tab
2. Select a connected session from the dropdown
3. Enter the recipient's phone number (with or without leading zero)
4. Type your message
5. Click "Send Message"

### Debugging Tips

1. If the QR code doesn't appear:
   - Check that the Node.js server is running
   - Click "Refresh QR Code"
   - Check browser console for any errors

2. If messages aren't being sent:
   - Verify the session is connected (shows "Connected" in green)
   - Check the phone number format
   - Review any error messages displayed in the result box

3. Common issues:
   - The WhatsApp mobile app needs to be online for the connection to work
   - Each phone can have up to 4 linked devices at once
   - The QR code expires after a certain time - refresh if needed