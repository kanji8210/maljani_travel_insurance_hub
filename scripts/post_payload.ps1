[Net.ServicePointManager]::ServerCertificateValidationCallback = { $true }
$body = Get-Content -Raw "$PSScriptRoot\payload.json"
try {
    $resp = Invoke-RestMethod -Uri 'https://localhost/wordpress/wp-json/support-chat/v1/send' -Method Post -Body $body -ContentType 'application/json' -UseBasicParsing
    $resp | ConvertTo-Json -Depth 4
} catch {
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $content = $reader.ReadToEnd()
        Write-Output $content
    } else {
        Write-Output $_.Exception.Message
    }
}
