@echo off
setlocal

:: Configuration
set "MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe"
set "PHP=C:\xampp\php\php.exe"
set "SCRIPT=C:\xampp\securite\chiffrer.php"
set "BACKUP_DIR=C:\xampp\backups\salleexamen"

:: Créer le dossier de sauvegarde si nécessaire
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

:: Générer la date et l'heure
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HH-mm-ss"') do set "DATE=%%i"

set "FICHIER=%BACKUP_DIR%\salleexamen_%DATE%.sql"

:: Vérifier les programmes nécessaires
if not exist "%MYSQLDUMP%" (
    echo [%DATE%] ERREUR : mysqldump introuvable >> "%BACKUP_DIR%\backup.log"
    exit /b 1
)

if not exist "%PHP%" (
    echo [%DATE%] ERREUR : PHP introuvable >> "%BACKUP_DIR%\backup.log"
    exit /b 1
)

if not exist "%SCRIPT%" (
    echo [%DATE%] ERREUR : script de chiffrement introuvable >> "%BACKUP_DIR%\backup.log"
    exit /b 1
)

:: Effectuer la sauvegarde SQL
"%MYSQLDUMP%" --host=localhost --user=root --databases salleexamen --routines --triggers --events --single-transaction --result-file="%FICHIER%" 2>> "%BACKUP_DIR%\backup.log"

if errorlevel 1 (
    echo [%DATE%] ECHEC de la sauvegarde SQL >> "%BACKUP_DIR%\backup.log"
    exit /b 1
)

:: Vérifier que le fichier SQL existe
if not exist "%FICHIER%" (
    echo [%DATE%] ERREUR : fichier SQL absent >> "%BACKUP_DIR%\backup.log"
    exit /b 1
)

for %%A in ("%FICHIER%") do (
    if %%~zA EQU 0 (
        echo [%DATE%] ERREUR : fichier SQL vide >> "%BACKUP_DIR%\backup.log"
        exit /b 1
    )
)

:: Chiffrer la sauvegarde
"%PHP%" "%SCRIPT%" "%FICHIER%" >> "%BACKUP_DIR%\backup.log" 2>&1

if errorlevel 1 (
    echo [%DATE%] ECHEC du chiffrement. Fichier SQL conserve. >> "%BACKUP_DIR%\backup.log"
    exit /b 1
)

:: Vérifier le fichier chiffre
if not exist "%FICHIER%.enc" (
    echo [%DATE%] ERREUR : fichier chiffre absent. SQL conserve. >> "%BACKUP_DIR%\backup.log"
    exit /b 1
)

:: Supprimer le SQL non chiffre uniquement après réussite
del "%FICHIER%"

echo [%DATE%] Sauvegarde et chiffrement reussis >> "%BACKUP_DIR%\backup.log"

:: Supprimer les sauvegardes chiffrées de plus de 30 jours
forfiles /p "%BACKUP_DIR%" /m salleexamen_*.sql.enc /d -30 /c "cmd /c del @path" 2>nul

endlocal
exit /b 0