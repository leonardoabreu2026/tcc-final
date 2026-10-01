# Capa de e-book: desenha a 1ª página de cada PDF como PNG, com o leitor de PDF do próprio Windows
# (Windows.Data.Pdf, o mesmo do Edge) — sem instalar nada. Usado por CapaPdf::gerar().
# -Lista: arquivo de texto com uma linha por PDF: "caminho do PDF<TAB>caminho do PNG de saída".
param([Parameter(Mandatory)][string]$Lista, [int]$Largura = 800)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Runtime.WindowsRuntime
$null = [Windows.Storage.StorageFile, Windows.Storage, ContentType = WindowsRuntime]
$null = [Windows.Data.Pdf.PdfDocument, Windows.Data.Pdf, ContentType = WindowsRuntime]
$null = [Windows.Storage.Streams.InMemoryRandomAccessStream, Windows.Storage.Streams, ContentType = WindowsRuntime]
$metodos = [System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object { $_.Name -eq 'AsTask' -and $_.GetParameters().Count -eq 1 }
$asTaskOp = $metodos | Where-Object { $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1' } | Select-Object -First 1
$asTaskAcao = $metodos | Where-Object { $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncAction' } | Select-Object -First 1
function Esperar($op, [Type]$tipo) { $t = $asTaskOp.MakeGenericMethod($tipo).Invoke($null, @($op)); $null = $t.Wait(60000); $t.Result }
function EsperarAcao($op) { $t = $asTaskAcao.Invoke($null, @($op)); $null = $t.Wait(60000) }

foreach ($linha in Get-Content -LiteralPath $Lista -Encoding UTF8) {
    $partes = $linha -split "`t"
    if ($partes.Count -ne 2) { continue }
    try {
        $arquivo = Esperar ([Windows.Storage.StorageFile]::GetFileFromPathAsync($partes[0])) ([Windows.Storage.StorageFile])
        $doc = Esperar ([Windows.Data.Pdf.PdfDocument]::LoadFromFileAsync($arquivo)) ([Windows.Data.Pdf.PdfDocument])
        $opcoes = New-Object Windows.Data.Pdf.PdfPageRenderOptions
        $opcoes.DestinationWidth = $Largura
        $fluxo = New-Object Windows.Storage.Streams.InMemoryRandomAccessStream
        EsperarAcao ($doc.GetPage(0).RenderToStreamAsync($fluxo, $opcoes))
        $entrada = [System.IO.WindowsRuntimeStreamExtensions]::AsStreamForRead($fluxo.GetInputStreamAt(0))
        $saida = [System.IO.File]::Create($partes[1])
        $entrada.CopyTo($saida)
        $saida.Close()
    } catch {
        # PDF protegido ou com defeito: fica sem PNG e o PHP tenta a outra forma.
    }
}
