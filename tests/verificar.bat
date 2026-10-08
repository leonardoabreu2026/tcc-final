@echo off
chcp 65001 >nul
rem ============================================================
rem VERIFICACAO COMPLETA - clique duas vezes neste arquivo.
rem Confere a sintaxe de todos os PHP, roda o teste rapido e as jornadas (Apache e MySQL ligados no XAMPP).
rem ============================================================
cd /d "%~dp0.."
echo.
echo === TCC Final - Conecta Vagas DF - verificacao completa ===
echo.
C:\xampp\php\php.exe tests\lint.php
if errorlevel 1 goto erro
C:\xampp\php\php.exe tests\smoke.php
if errorlevel 1 goto erro
rem A extensao zip (usada so pelo teste, para montar um DOCX) costuma vir desligada no php.ini do XAMPP: liga so nesta execucao.
set ZIPEXT=
C:\xampp\php\php.exe -r "exit(class_exists('ZipArchive') ? 0 : 1);" || set ZIPEXT=-d extension=zip
C:\xampp\php\php.exe %ZIPEXT% tests\jornadas.php
if errorlevel 1 goto erro
echo.
echo TUDO CERTO: pode usar e fazer commit.
pause
exit /b 0
:erro
echo.
echo ATENCAO: algo falhou (veja acima).
echo Para voltar a ultima versao estavel do codigo:  git checkout tcc-final-v1.4
echo Para voltar o banco: veja storage\backups\...\COMO_RESTAURAR.txt
pause
exit /b 1
