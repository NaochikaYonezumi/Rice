# =============================================================================
# setup-wsl-portproxy.ps1   (一度だけ管理者で実行する)
#   - Windows Firewall に Inbound 80/443 許可ルールを作る
#   - refresh-wsl-portproxy.ps1 を以下のタイミングで自動実行するタスクを登録:
#       * システム起動時
#       * ユーザーログオン時
#       * ネットワーク接続変更時 (Event ID 10000 / NetworkProfile)
#   - 即座に1回 refresh を回して portproxy を確定させる
#
#   実行方法:
#     1. PowerShell を「管理者として実行」で開く
#     2. cd C:\path\to\Rice\scripts\windows
#     3. powershell -ExecutionPolicy Bypass -File setup-wsl-portproxy.ps1
# =============================================================================

$ErrorActionPreference = 'Stop'

# --- 管理者チェック ---------------------------------------------------------
$me = [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
if (-not $me.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Error 'このスクリプトは管理者として実行してください。'
    exit 1
}

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$refreshScript = Join-Path $scriptDir 'refresh-wsl-portproxy.ps1'
if (-not (Test-Path $refreshScript)) {
    Write-Error "refresh-wsl-portproxy.ps1 が見つかりません: $refreshScript"
    exit 1
}

# --- 1. Firewall ルール ----------------------------------------------------
foreach ($p in @(@{Port=80;Name='Rice HTTP (80)'}, @{Port=443;Name='Rice HTTPS (443)'})) {
    $existing = Get-NetFirewallRule -DisplayName $p.Name -ErrorAction SilentlyContinue
    if ($existing) {
        Write-Host "Firewall rule already exists: $($p.Name)"
    } else {
        New-NetFirewallRule `
            -DisplayName $p.Name `
            -Direction Inbound `
            -Protocol TCP `
            -LocalPort $p.Port `
            -Action Allow `
            -Profile Domain,Private `
            -Enabled True | Out-Null
        Write-Host "Firewall rule created: $($p.Name)"
    }
}

# --- 2. スケジューラタスク -------------------------------------------------
$taskName = 'Rice-WSL-PortProxy-Refresh'
$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "Removing existing task: $taskName"
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
}

$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$refreshScript`""
$triggers = @(
    (New-ScheduledTaskTrigger -AtStartup),
    (New-ScheduledTaskTrigger -AtLogOn)
)
# ネットワーク変更時のトリガ (NetworkProfile/Operational イベント 10000)
$eventTrigger = New-CimInstance -CimClass (Get-CimClass -ClassName MSFT_TaskEventTrigger -Namespace Root/Microsoft/Windows/TaskScheduler) -ClientOnly
$eventTrigger.Enabled    = $true
$eventTrigger.Subscription = @'
<QueryList>
  <Query Id="0" Path="Microsoft-Windows-NetworkProfile/Operational">
    <Select Path="Microsoft-Windows-NetworkProfile/Operational">*[System[(EventID=10000)]]</Select>
  </Query>
</QueryList>
'@
$triggers += $eventTrigger

$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$settings  = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 5) -MultipleInstances IgnoreNew

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $triggers -Principal $principal -Settings $settings -Description 'Refresh netsh portproxy to current WSL2 IP for Rice' | Out-Null
Write-Host "Scheduled task registered: $taskName"

# --- 3. すぐに 1 回だけ実行して反映 ---------------------------------------
Write-Host ''
Write-Host '=== Running initial refresh ==='
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $refreshScript

Write-Host ''
Write-Host '=== Setup complete ==='
Write-Host '次に行うこと:'
Write-Host '  1. Windows DNS Server に rice.cosy.co.jp の A レコード を追加'
Write-Host '       192.168.11.74 (Wi-Fi)'
Write-Host '       192.168.100.2 (Ethernet)'
Write-Host '  2. WSL の Rice ディレクトリで bash scripts/issue-cert.sh を実行 (Let''s Encrypt 取得)'
Write-Host '  3. docker compose up -d --build で再起動'
