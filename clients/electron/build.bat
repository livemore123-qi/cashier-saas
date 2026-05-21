@echo off
chcp 65001 >nul
echo ===================================
echo   收银系统 Electron 客户端构建
echo ===================================
echo.

cd /d "%~dp0"

echo [1/4] 清理旧构建...
if exist "node_modules" rmdir /s /q "node_modules"
if exist "dist" rmdir /s /q "dist"
if exist "release" rmdir /s /q "release"

echo [2/4] 安装依赖 (请等待,需要下载约200MB)...
call npm install
if %ERRORLEVEL% neq 0 (
    echo 安装失败! 请检查网络连接和 Node.js 环境.
    pause
    exit /b 1
)

echo [3/4] 编译 TypeScript...
call npx tsc
if %ERRORLEVEL% neq 0 (
    echo 编译失败!
    pause
    exit /b 1
)

echo [4/4] 打包 Windows 安装包...
call npx electron-builder
if %ERRORLEVEL% neq 0 (
    echo 打包失败! 请检查是否安装了 NSIS.
    pause
    exit /b 1
)

echo.
echo ===================================
echo   构建完成!
echo   安装包位置: release\*.exe
echo ===================================
pause