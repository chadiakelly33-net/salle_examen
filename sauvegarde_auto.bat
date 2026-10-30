@echo off
setlocal

set "MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe"
set "BACKUP_DIR=C:\xampp\backups\salleexamen"

:: Créer le dossier de sauvegarde
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

:: Générer la date et l'heure
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HH-mm-ss"') do set "DATE=%%i"

:: Effectuer la sauvegarde
"%MYSQLDUMP%" --host=localhost --user=root --databases salleexamen --routines --triggers --events --single-transaction --result-file="%BACKUP_DIR%\salleexamen_%DATE%.sql" 2>> "%BACKUP_DIR%\backup.log"

:: Vérifier le résultat
if errorlevel 1 (
    echo [%DATE%] ECHEC de la sauvegarde >> "%BACKUP_DIR%\backup.log"
    exit /b 1
)

echo [%DATE%] Sauvegarde réussie >> "%BACKUP_DIR%\backup.log"

:: Supprimer les sauvegardes de plus de 30 jours
forfiles /p "%BACKUP_DIR%" /m salleexamen_*.sql /d -30 /c "cmd /c del @path"

endlocal