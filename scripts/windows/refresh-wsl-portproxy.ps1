# =============================================================================
# refresh-wsl-portproxy.ps1
#   - WSL2 ubuntu-20.04 の現在の IP を取得して、netsh portproxy を再構成する。
#   - WSL2 の IP は再起動で変わるので、ログオン時 / ネットワーク変更時に呼ぶ。
#   - 80, 443 を 0.0.0.0 で受けて WSL に転送する。
#
#   呼び出し:
#     PowerShell (管理者) で:
#       powershell -ExecutionPolicy Bypass -File refresh-wsl-portproxy.ps1
#     通常はスケジューラから自動実行。
# =============================================================================

$ErrorActionPreference = 'Stop'

$LogDir = "$env:ProgramData\Rice"
if (-not (Test-Path $LogDir)) { New-Item -ItemType Directory -Path $LogDir -Force | Out-Null }
$LogFile = Join-Path $LogDir 'wsl-portproxy.log'

function Write-Log($msg) {
    $line = "{0} {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $msg
    Add-Content -Path $LogFile -Value $line
    Write-Host $line
}

# --- 1. WSL の IP を取得 -----------------------------------------------------
$distro = 'ubuntu-20.04'
$raw = wsl.exe -d $distro -e hostname -I
if (-not $raw) {
    Write-Log "ERROR: wsl.exe -d $distro hostname -I returned empty. Is WSL running?"
    # WSL を起こす試み
    wsl.exe -d $distro -e true | Out-Null
    Start-Sleep -Seconds 2
    $raw = wsl.exe -d $distro -e hostname -I
}
$wslIp = ($raw -split ' ' | Where-Object { $_ -match '^\d+\.\d+\.\d+\.\d+$' } | Select-Object -First 1).Trim()
if (-not $wslIp) {
    Write-Log "ERROR: failed to detect WSL IP. raw='$raw'"
    exit 1
}
Write-Log "WSL IP: $wslIp"

# --- 2. 既存の portproxy を 80/443 だけ削除 ----------------------------------
foreach ($port in 80, 443) {
    # listenaddress=0.0.0.0 のものをまず消す (両方を listen するため)
    netsh interface portproxy delete v4tov4 listenport=$port listenaddress=0.0.0.0 2>$null | Out-Null
}

# --- 3. 新しい portproxy を登録 ----------------------------------------------
foreach ($port in 80, 443) {
    $output = netsh interface portproxy add v4tov4 `
        listenport=$port listenaddress=0.0.0.0 `
        connectport=$port connectaddress=$wslIp 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Log "ERROR: portproxy add failed for $port -> $($wslIp): $output"
        exit 1
    }
    Write-Log "portproxy: 0.0.0.0:$port -> ${wslIp}:$port"
}

# --- 4. 現状を出力 -----------------------------------------------------------
$current = netsh interface portproxy show v4tov4
Write-Log "current portproxy table:`n$current"

Write-Log 'refresh completed'
