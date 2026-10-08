const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');
const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode');
const express = require('express');
const cors = require('cors');

let mainWindow;
let whatsappClient;

function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1000,
        height: 700,
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            nodeIntegration: false,
            contextIsolation: true
        }
    });
    mainWindow.loadFile('index.html');
}

function startAPI() {
    const apiApp = express();
    apiApp.use(cors());
    apiApp.use(express.json({ limit: '50mb' }));
    apiApp.use(express.urlencoded({ limit: '50mb', extended: true }));

    // نقطة إرسال الفواتير أو أي رسالة من الموقع
    apiApp.post('/api/send-message', async (req, res) => {
        const { phone, message, pdfBase64, htmlContent } = req.body;
        if (!phone || !message) {
            return res.status(400).json({ success: false, error: 'Phone and message are required' });
        }
        
        try {
            // معالجة الرقم المصري (تحويل 010 إلى 2010)
            let formattedPhone = phone.replace(/\D/g, ''); // إزالة أي مسافات أو رموز
            if (formattedPhone.startsWith('01') && formattedPhone.length === 11) {
                formattedPhone = '2' + formattedPhone;
            }
            
            const chatId = formattedPhone.includes('@c.us') ? formattedPhone : `${formattedPhone}@c.us`;
            
            if (!whatsappClient) {
                return res.status(500).json({ success: false, error: 'WhatsApp client is not initialized' });
            }

            const { MessageMedia } = require('whatsapp-web.js');
            let media = null;

            if (htmlContent) {
                const { BrowserWindow } = require('electron');
                let win = new BrowserWindow({ show: false, webPreferences: { offscreen: true } });
                await win.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(htmlContent));
                
                // انتظار تحميل الصور والخطوط
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                const pdfBuffer = await win.webContents.printToPDF({ 
                    printBackground: true, 
                    pageSize: 'A5' 
                });
                win.destroy();
                
                media = new MessageMedia('application/pdf', pdfBuffer.toString('base64'), 'Invoice.pdf');
            } else if (pdfBase64) {
                const base64Data = pdfBase64.includes(',') ? pdfBase64.split(',')[1] : pdfBase64;
                media = new MessageMedia('application/pdf', base64Data, 'Invoice.pdf');
            }
            
            if (media) {
                await whatsappClient.sendMessage(chatId, message, { media: media });
            } else {
                await whatsappClient.sendMessage(chatId, message);
            }
            
            res.json({ success: true });
        } catch (error) {
            console.error("WhatsApp Send Error:", error);
            res.status(500).json({ success: false, error: error.message ? error.message.toString() : 'Unknown error' });
        }
    });

    apiApp.listen(3000, () => {
        console.log('Local API is running on http://localhost:3000');
    });
}

function initWhatsApp() {
    whatsappClient = new Client({
        authStrategy: new LocalAuth(), // لحفظ تسجيل الدخول
        puppeteer: {
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        }
    });

    whatsappClient.on('qr', async (qr) => {
        try {
            const qrDataUrl = await qrcode.toDataURL(qr);
            if (mainWindow) mainWindow.webContents.send('qr-code', qrDataUrl);
        } catch(err) {
            console.error('QR Generate Error', err);
        }
    });

    whatsappClient.on('ready', () => {
        console.log('WhatsApp is ready!');
        if (mainWindow) mainWindow.webContents.send('ready');
    });

    whatsappClient.initialize();
}

app.whenReady().then(() => {
    createWindow();
    initWhatsApp();
    startAPI();

    app.on('activate', function () {
        if (BrowserWindow.getAllWindows().length === 0) createWindow();
    });
});

app.on('window-all-closed', function () {
    if (process.platform !== 'darwin') app.quit();
});

ipcMain.handle('send-message', async (event, phone, text) => {
    try {
        const chatId = phone.includes('@c.us') ? phone : `${phone}@c.us`;
        await whatsappClient.sendMessage(chatId, text);
        return { success: true };
    } catch (err) {
        return { success: false, error: err.message };
    }
});
