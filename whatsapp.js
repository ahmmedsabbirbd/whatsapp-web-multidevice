const crypto = require('crypto');
// Make crypto available globally for Baileys library
global.crypto = crypto;

const { makeWASocket, useMultiFileAuthState } = require("@whiskeysockets/baileys");
const QRCode = require("qrcode-terminal");
const express = require("express");

const app = express();
app.use(express.json());

async function startWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState("./auth_info");

    const sock = makeWASocket({
        auth: state,
        printQRInTerminal: true
    });

    sock.ev.on("creds.update", saveCreds);

    sock.ev.on("connection.update", (update) => {
        const { connection, lastDisconnect } = update;
        if (connection === "open") {
            console.log("✅ WhatsApp Connected!");
        } else if (connection === "close") {
            console.log("❌ Disconnected, restarting...");
            startWhatsApp();
        }
    });

    app.post("/send-message", async (req, res) => {
        const { number, message } = req.body;
        try {
            await sock.sendMessage(number + "@s.whatsapp.net", { text: message });
            res.json({ success: true, message: "Message sent successfully!" });
        } catch (error) {
            res.json({ success: false, error: error.message });
        }
    });
}

startWhatsApp();
app.listen(3000, () => console.log("🚀 API running on http://localhost:3000"));