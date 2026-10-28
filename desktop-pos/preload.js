const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
    isDesktop: true,
    printReceipt: (htmlContent, options = {}) => ipcRenderer.invoke('print-receipt', htmlContent, options),
    toggleFullscreen: () => ipcRenderer.send('toggle-fullscreen'),
    minimize: () => ipcRenderer.send('window-minimize'),
    maximize: () => ipcRenderer.send('window-maximize'),
    close: () => ipcRenderer.send('window-close'),
    openDevTools: () => ipcRenderer.send('open-devtools')
});
