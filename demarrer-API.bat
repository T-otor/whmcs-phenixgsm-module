@echo off
REM ============================================
REM PHENIX Centralizer - Script de lancement
REM Lancer en tant qu'administrateur si besoin
REM ============================================

echo ============================================
echo   PHENIX Centralizer API - Lancement
echo ============================================
echo.

REM Vérifier si Docker est installé
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERREUR] Docker n'est pas installe ou introuvable dans le PATH
    echo.
    echo Installez Docker Desktop depuis https://www.docker.com/products/docker-desktop/
    pause
    exit /b 1
)

echo [OK] Docker est installe:
docker --version
echo.

REM Verifier si l'image existe deja
docker images | findstr phenix-centralizer >nul 2>&1
if %errorlevel% equ 0 (
    echo [INFO] Image Docker trouvee (lancement direct)
) else (
    echo [INFO] Construction de l'image Docker...
    docker-compose build
    if %errorlevel% neq 0 (
        echo.
        echo [ERREUR] Echec de la construction de l'image
        pause
        exit /b 1
    )
)

echo.
echo ============================================
echo   Lancement du conteneur...
echo ============================================
echo.
docker-compose up -d
if %errorlevel% neq 0 (
    echo.
    echo [ERREUR] Echec du lancement du conteneur
    pause
    exit /b 1
)

echo.
echo ============================================
echo   Attendre quelques secondes pour le demarrage...
echo ============================================
timeout /t 5 /nobreak >nul

echo.
echo ============================================
echo   API PHENIX CENTRALIZER
echo ============================================
echo.
echo [+] API active:     http://localhost:8000
echo [+] Docs Swagger:   http://localhost:8000/docs
echo [+] Redoc:          http://localhost:8000/redoc
echo [+] Health Check:   http://localhost:8000/health
echo.
echo [INFO] Pour arreter l'API, exécutez l'arret-API.bat
echo ============================================
echo.

REM Tester le health check
curl -s http://localhost:8000/health >nul 2>&1
if %errorlevel% equ 0 (
    echo [+] Health check reussi!
) else (
    echo [-] Le service n'est pas encore pret... patientez quelques secondes.
)

echo.
pause
