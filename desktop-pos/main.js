const { app, BrowserWindow, ipcMain, Menu } = require('electron');
const path = require('path');
const fs = require('fs');

const isSmokeTest = process.argv.includes('--smoke-test');

// Pin persistent userData path so IndexedDB survives across Portable/Setup runs & updates
app.setAppUserModelId('com.hamzaperfumes.pos');
app.setPath('userData', path.join(app.getPath('appData'), 'HamzaPerfumesPOS'));

let mainWindow = null;

function createWindow() {
    const icoPath = path.join(__dirname, 'src', 'icon.ico');
    const iconFile = fs.existsSync(icoPath) ? icoPath : path.join(__dirname, 'src', 'logo.png');

    mainWindow = new BrowserWindow({
        width: 1366,
        height: 768,
        minWidth: 1024,
        minHeight: 650,
        show: !isSmokeTest,
        autoHideMenuBar: true,
        title: 'حمزة للعطور - كاشير ونقطة بيع الفروع (أوفلاين)',
        icon: iconFile,
        backgroundColor: '#16100E',
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            nodeIntegration: false,
            contextIsolation: true,
            webSecurity: false // Allows connecting to local/remote ERP endpoints without CORS issues
        }
    });

    mainWindow.loadFile(path.join(__dirname, 'src', 'index.html'));

    if (isSmokeTest) {
        mainWindow.webContents.once('did-finish-load', () => {
            console.log('SMOKE_TEST_OK');
            setTimeout(() => app.exit(0), 300);
        });
    } else {
        mainWindow.maximize();
    }

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

function registerIpcHandlers() {
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
        return new Promise((resolve) => {
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

const gotTheLock = app.requestSingleInstanceLock();
if (!gotTheLock && !isSmokeTest) {
    app.quit();
} else {
    app.on('second-instance', () => {
        if (mainWindow) {
            if (mainWindow.isMinimized()) mainWindow.restore();
            mainWindow.focus();
        }
    });

    app.whenReady().then(() => {
        Menu.setApplicationMenu(null);
        registerIpcHandlers();
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
}
