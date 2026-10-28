const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');

let mainWindow = null;

function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1366,
        height: 768,
        minWidth: 1024,
        minHeight: 650,
        title: 'حمزة للعطور - كاشير ونقطة بيع الفروع (أوفلاين)',
        icon: path.join(__dirname, 'src', 'logo.png'),
        backgroundColor: '#16100E',
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            nodeIntegration: false,
            contextIsolation: true,
            webSecurity: false // Allows connecting to local/remote ERP endpoints without CORS issues
        }
    });

    mainWindow.loadFile(path.join(__dirname, 'src', 'index.html'));
    mainWindow.maximize();

    mainWindow.on('closed', () => {
        mainWindow = null;
    });

    // Window controls
    ipcMain.on('window-minimize', () => {
        if (mainWindow) mainWindow.minimize();
    });

    ipcMain.on('window-maximize', () => {
        if (!mainWindow) return;
        if (mainWindow.isMaximized()) {
            mainWindow.unmaximize();
        } else {
            mainWindow.maximize();
        }
    });

    ipcMain.on('window-close', () => {
        if (mainWindow) mainWindow.close();
    });

    ipcMain.on('toggle-fullscreen', () => {
        if (!mainWindow) return;
        mainWindow.setFullScreen(!mainWindow.isFullScreen());
    });

    ipcMain.on('open-devtools', () => {
        if (mainWindow) mainWindow.webContents.openDevTools();
    });

    // Thermal Receipt Printing Handler
    ipcMain.handle('print-receipt', async (event, htmlContent, options = {}) => {
        return new Promise((resolve, reject) => {
            const printWindow = new BrowserWindow({
                show: false,
                webPreferences: {
                    nodeIntegration: false,
                    contextIsolation: true
                }
            });

            printWindow.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(htmlContent)}`);

            printWindow.webContents.on('did-finish-load', () => {
                const silent = options.silent !== undefined ? options.silent : false;
                const printOptions = {
                    silent: silent,
                    printBackground: true,
                    margins: { marginType: 'none' },
                    pageSize: { width: 80000, height: 297000 } // 80mm thermal roll format in microns
                };

                if (options.deviceName) {
                    printOptions.deviceName = options.deviceName;
                }

                printWindow.webContents.print(printOptions, (success, failureReason) => {
                    printWindow.close();
                    if (!success) {
                        resolve({ success: false, error: failureReason });
                    } else {
                        resolve({ success: true });
                    }
                });
            });
        });
    });
}

app.whenReady().then(() => {
    createWindow();

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) createWindow();
    });
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});
