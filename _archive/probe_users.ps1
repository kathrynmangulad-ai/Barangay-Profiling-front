$s = $null
$url = "http://localhost/barangay_system/login.php"
$g = Invoke-WebRequest $url -SessionVariable s -UseBasicParsing
$t = ([regex]::Match($g.Content, 'name="csrf_token" value="([a-f0-9]{64})"')).Groups[1].Value
Invoke-WebRequest $url -Method Post -Body @{username='admin'; password='admin123'; csrf_token=$t} -WebSession $s -UseBasicParsing | Out-Null
Write-Output "--- login response status: $($r.StatusCode)"
Write-Output "--- dashboard after login ---"
$r2 = Invoke-WebRequest "http://localhost/barangay_system/dashboard.php" -WebSession $s -UseBasicParsing
$c2 = $r2.Content
Write-Output "dashboard has title: $($c2 -match 'Dashboard')"
Write-Output "--- users.php?tab=approved ---"
$r3 = Invoke-WebRequest "http://localhost/barangay_system/users.php?tab=approved" -WebSession $s -UseBasicParsing
$c3 = $r3.Content
$rows = [regex]::Matches($c3, '<tr>\s*<td[^>]*>(\d+)</td>\s*<td[^>]*>([^<]+)</td>')
Write-Output "approved tab rows found: $($rows.Count / 2)"
for ($i = 0; $i -lt $rows.Count; $i += 2) {
  Write-Output "  id=$($rows[$i].Groups[1].Value) user=$($rows[$i].Groups[2].Value)"
}
Write-Output "--- users.php?tab=admin ---"
$r4 = Invoke-WebRequest "http://localhost/barangay_system/users.php?tab=admin" -WebSession $s -UseBasicParsing
$c4 = $r4.Content
$rows4 = [regex]::Matches($c4, '<tr>\s*<td[^>]*>(\d+)</td>\s*<td[^>]*>([^<]+)</td>')
Write-Output "admin tab rows found: $($rows4.Count / 2)"
for ($i = 0; $i -lt $rows4.Count; $i += 2) {
  Write-Output "  id=$($rows4[$i].Groups[1].Value) user=$($rows4[$i].Groups[2].Value)"
}
Write-Output "--- users.php?tab=secretary ---"
$r5 = Invoke-WebRequest "http://localhost/barangay_system/users.php?tab=secretary" -WebSession $s -UseBasicParsing
$c5 = $r5.Content
$rows5 = [regex]::Matches($c5, '<tr>\s*<td[^>]*>(\d+)</td>\s*<td[^>]*>([^<]+)</td>')
Write-Output "secretary tab rows found: $($rows5.Count / 2)"
for ($i = 0; $i -lt $rows5.Count; $i += 2) {
  Write-Output "  id=$($rows5[$i].Groups[1].Value) user=$($rows5[$i].Groups[2].Value)"
}
Write-Output "--- DB ---"
& 'C:\xampp\mysql\bin\mysql.exe' -u root brgy_system -e "SELECT id, username, role, status, barangay_id FROM users ORDER BY id;"
