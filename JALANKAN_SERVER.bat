@echo off
title Menjalankan Server Sistem Keuangan UANG TAMBUN
color 0b
echo =========================================================
echo   MEMULAI SERVER SISTEM KEUANGAN (LARAVEL + SQLITE)
echo =========================================================
echo.
cd /d "%~dp0"

:: Cek apakah PHP terinstall
where php >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] PHP tidak ditemukan di sistem!
    echo Harap pastikan PHP telah terpasang di PATH Windows.
    echo.
    pause
    exit /b
)

:: Buka browser otomatis setelah 2 detik di background
start "" cmd /c "timeout /t 2 /nobreak >nul & start http://127.0.0.1:8000"

echo [OK] Membuka http://127.0.0.1:8000 di browser...
echo [INFO] Server sedang aktif. JANGAN TUTUP jendela ini selama menggunakan aplikasi.
echo.
echo Tekan Ctrl + C jika ingin menghentikan server.
echo =========================================================
echo.
php artisan serve --host=127.0.0.1 --port=8000
pause
