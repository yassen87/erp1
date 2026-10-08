const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('whatsapp', {
    onQR: (callback) => ipcRenderer.on('qr-code', (_event, qr) => callback(qr)),
    onReady: (callback) => ipcRenderer.on('ready', () => callback()),
    sendMessage: (phone, text) => ipcRenderer.invoke('send-message', phone, text)
});
