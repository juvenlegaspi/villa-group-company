$ErrorActionPreference = 'Stop'

function Color([int]$r, [int]$g, [int]$b) {
    return $r + ($g * 256) + ($b * 65536)
}

$navy = Color 13 42 82
$blue = Color 34 91 170
$cyan = Color 25 169 205
$gold = Color 235 179 42
$green = Color 31 151 104
$orange = Color 230 126 34
$ink = Color 28 43 62
$muted = Color 102 118 138
$light = Color 242 246 251
$white = Color 255 255 255
$line = Color 215 225 237

$outputDir = Join-Path (Get-Location) 'deliverables'
New-Item -ItemType Directory -Force -Path $outputDir | Out-Null
$outputPath = [System.IO.Path]::GetFullPath((Join-Path $outputDir 'Weekly_IT_System_Development_Update_2026-10-16.docx'))

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$document = $word.Documents.Add()
$document.PageSetup.TopMargin = 50
$document.PageSetup.BottomMargin = 46
$document.PageSetup.LeftMargin = 58
$document.PageSetup.RightMargin = 58

function Add-Paragraph($text, $size = 10.5, $color = $ink, $bold = $false, $spaceAfter = 7, $alignment = 0) {
    $range = $document.Range($document.Content.End - 1, $document.Content.End - 1)
    $range.Text = $text
    $range.Font.Name = 'Aptos'
    $range.Font.Size = [single]$size
    $range.Font.Color = [int]$color
    $range.Font.Bold = $(if ($bold) { 1 } else { 0 })
    $range.ParagraphFormat.Alignment = $alignment
    $range.ParagraphFormat.SpaceAfter = $spaceAfter
    $range.ParagraphFormat.LineSpacingRule = 0
    $range.InsertParagraphAfter()
}

function Add-Heading($number, $title) {
    Add-Paragraph "$number  $title" 16 $navy $true 4
    Add-Paragraph ('─' * 86) 6 $cyan $false 10
}

function Add-Bullet($text) {
    $range = $document.Range($document.Content.End - 1, $document.Content.End - 1)
    $range.Text = $text
    $range.Font.Name = 'Aptos'
    $range.Font.Size = 10.5
    $range.Font.Color = $ink
    $range.ParagraphFormat.LeftIndent = 18
    $range.ParagraphFormat.FirstLineIndent = -10
    $range.ParagraphFormat.SpaceAfter = 5
    $range.ListFormat.ApplyBulletDefault()
    $range.InsertParagraphAfter()
}

function Set-Cell($cell, $text, $fill, $fontColor = $ink, $bold = $false, $size = 9.5) {
    $cell.Range.Text = $text
    $cell.Range.Font.Name = 'Aptos'
    $cell.Range.Font.Size = [single]$size
    $cell.Range.Font.Color = [int]$fontColor
    $cell.Range.Font.Bold = $(if ($bold) { 1 } else { 0 })
    $cell.Range.ParagraphFormat.SpaceAfter = 0
    $cell.Range.ParagraphFormat.SpaceBefore = 0
    $cell.VerticalAlignment = 1
    $cell.Shading.BackgroundPatternColor = [int]$fill
    $cell.TopPadding = 7
    $cell.BottomPadding = 7
    $cell.LeftPadding = 8
    $cell.RightPadding = 8
}

function Add-Spacer($points = 5) {
    Add-Paragraph '' 3 $ink $false $points
}

# Cover
$cover = $document.Range($document.Content.End - 1, $document.Content.End - 1)
$cover.Text = "VILLA GROUP OF COMPANIES`r"
$cover.Font.Name = 'Aptos Display'
$cover.Font.Size = 12
$cover.Font.Bold = 1
$cover.Font.Color = $cyan
$cover.ParagraphFormat.SpaceAfter = 42
$cover.InsertParagraphAfter()

Add-Paragraph 'WEEKLY IT SYSTEM' 29 $navy $true 0
Add-Paragraph 'DEVELOPMENT UPDATE' 29 $navy $true 16
Add-Paragraph 'Vessel Operations, Tracking & AIS Readiness' 17 $blue $true 22
Add-Paragraph 'A management progress report covering voyage-route enhancements, vessel-based access control, AIS integration coordination, and the deployment of the current release for system testing.' 12 $muted $false 30

$range = $document.Range($document.Content.End - 1, $document.Content.End - 1)
$table = $document.Tables.Add($range, 3, 2)
$table.AllowAutoFit = $false
$table.Columns.Item(1).Width = 130
$table.Columns.Item(2).Width = 340
Set-Cell ($table.Cell(1,1)) 'REPORTING PERIOD' $navy $white $true 9
Set-Cell ($table.Cell(1,2)) 'October 12–16, 2026' $light $ink $true 10
Set-Cell ($table.Cell(2,1)) 'PREPARED BY' $navy $white $true 9
Set-Cell ($table.Cell(2,2)) 'IT Department' $light $ink $true 10
Set-Cell ($table.Cell(3,1)) 'RELEASE STATUS' $navy $white $true 9
Set-Cell ($table.Cell(3,2)) 'Deployed for System Testing and Validation' (Color 232 247 241) $green $true 10
$document.Range($document.Content.End - 1, $document.Content.End - 1).InsertBreak(7)

# Executive Summary
Add-Heading '1.' 'EXECUTIVE SUMMARY'
Add-Paragraph 'This week focused on improving vessel route planning and tracking, refining vessel-based user access, progressing the planned AIS integration, and deploying the latest system updates for controlled testing.' 11 $ink $false 12

$range = $document.Range($document.Content.End - 1, $document.Content.End - 1)
$statusTable = $document.Tables.Add($range, 5, 3)
$statusTable.AllowAutoFit = $false
$statusTable.Columns.Item(1).Width = 125
$statusTable.Columns.Item(2).Width = 115
$statusTable.Columns.Item(3).Width = 275
Set-Cell ($statusTable.Cell(1,1)) 'WORKSTREAM' $navy $white $true 9
Set-Cell ($statusTable.Cell(1,2)) 'STATUS' $navy $white $true 9
Set-Cell ($statusTable.Cell(1,3)) 'MANAGEMENT SUMMARY' $navy $white $true 9
$rows = @(
    @('Voyage Routing', 'ENHANCED', 'Automatic and manual route tools improved; planned and actual tracks are separately visible.', $blue),
    @('Vessel Access', 'DEPLOYED', 'Company-wide and assigned-vessel manager responsibilities are now separated.', $green),
    @('AIS Integration', 'PENDING', 'Kpler/MarineTraffic requirements submitted; provider activation and commercial terms are pending.', $orange),
    @('System Testing', 'ACTIVE', 'The current release is deployed for user testing and operational validation.', $cyan)
)
for ($i = 0; $i -lt $rows.Count; $i++) {
    $row = $i + 2
    Set-Cell ($statusTable.Cell($row,1)) $rows[$i][0] $white $ink $true 9.5
    Set-Cell ($statusTable.Cell($row,2)) $rows[$i][1] $light $rows[$i][3] $true 9
    Set-Cell ($statusTable.Cell($row,3)) $rows[$i][2] $white $ink $false 9.5
}
Add-Spacer 10
Add-Paragraph 'Overall Status' 10 $green $true 3
Add-Paragraph 'Core system improvements are already available for testing. External AIS service activation remains the primary dependency before automatic live vessel-position synchronization can be completed.' 10.5 $ink $false 14

# Voyage Tracking
Add-Heading '2.' 'VESSEL TRACKING AND VOYAGE ROUTE IMPROVEMENTS'
Add-Bullet 'Improved voyage-route generation by supporting a local route-generation method whenever the SeaRoutes API subscription is unavailable.'
Add-Bullet 'Retained the automatically generated suggested route as an available planning option.'
Add-Bullet 'Added a manual route-drawing option for users who need to define the intended vessel route directly on the map.'
Add-Bullet 'Improved manual route editing by allowing users to adjust and drag route points instead of repeatedly redrawing an entire route.'
Add-Bullet 'Separated the approved planned route from the actual traveled track created from vessel-location updates.'
Add-Bullet 'Improved voyage-completion behavior by connecting the latest recorded position to the remaining planned route and final destination.'
Add-Bullet 'Resolved live-server database compatibility issues involving the storage of planned-route information.'

$range = $document.Range($document.Content.End - 1, $document.Content.End - 1)
$flow = $document.Tables.Add($range, 1, 5)
$flow.AllowAutoFit = $false
$flow.Columns.Item(1).Width = 135
$flow.Columns.Item(2).Width = 34
$flow.Columns.Item(3).Width = 135
$flow.Columns.Item(4).Width = 34
$flow.Columns.Item(5).Width = 135
Set-Cell ($flow.Cell(1,1)) "1. PLAN`rSuggested or manually drawn route" (Color 235 242 252) $blue $true 9
Set-Cell ($flow.Cell(1,2)) '→' $white $gold $true 16
Set-Cell ($flow.Cell(1,3)) "2. TRACK`rActual location updates form a traveled line" (Color 234 248 251) $cyan $true 9
Set-Cell ($flow.Cell(1,4)) '→' $white $gold $true 16
Set-Cell ($flow.Cell(1,5)) "3. COMPLETE`rRemaining route connects to destination" (Color 232 247 241) $green $true 9
Add-Spacer 12

# User Access
Add-Heading '3.' 'MARINE OPERATIONS USER-ACCESS IMPROVEMENT'
Add-Paragraph 'The two manager positions now have clearly separated responsibilities based on operational scope.' 10.5 $ink $false 9

$range = $document.Range($document.Content.End - 1, $document.Content.End - 1)
$access = $document.Tables.Add($range, 5, 3)
$access.AllowAutoFit = $false
$access.Columns.Item(1).Width = 160
$access.Columns.Item(2).Width = 170
$access.Columns.Item(3).Width = 170
Set-Cell ($access.Cell(1,1)) 'ACCESS AREA' $navy $white $true 9
Set-Cell ($access.Cell(1,2)) 'OPERATIONS MANAGER' $navy $white $true 9
Set-Cell ($access.Cell(1,3)) 'OPERATION MANAGER' $blue $white $true 9
$accessRows = @(
    @('Operational scope', 'All company vessels', 'Assigned vessels only'),
    @('Vessel Assignment', 'Not required', 'Required'),
    @('Management authority', 'Company-wide', 'Within assigned vessels'),
    @('Checklist reminders', 'Company-wide monitoring', 'Assigned vessel only')
)
for ($i = 0; $i -lt $accessRows.Count; $i++) {
    $row = $i + 2
    Set-Cell ($access.Cell($row,1)) $accessRows[$i][0] $light $ink $true 9.5
    Set-Cell ($access.Cell($row,2)) $accessRows[$i][1] $white $ink $false 9.5
    Set-Cell ($access.Cell($row,3)) $accessRows[$i][2] $white $ink $false 9.5
}
Add-Spacer 8
Add-Paragraph 'The assigned-vessel restriction applies across voyage monitoring, technical and defect records, certificates, dry-docking records, calendar schedules, and checklists.' 10.5 $ink $false 12

# AIS
Add-Heading '4.' 'MARINETRAFFIC / KPLER AIS INTEGRATION COORDINATION'
Add-Bullet 'Created and configured a Kpler Developer account for the proposed AIS integration.'
Add-Bullet 'Generated and tested an API credential for the Kpler AIS Latest endpoint.'
Add-Bullet 'Confirmed that AIS data access still requires provider-side commercial activation for the company account.'
Add-Bullet 'Submitted the company use case for internal live monitoring of Villa Shipping Lines cargo vessels.'
Add-Bullet 'Coordinated with Derrick Toh of Kpler/MarineTraffic, who referred the request to Yu Yamada for Philippine-market support.'

$range = $document.Range($document.Content.End - 1, $document.Content.End - 1)
$ais = $document.Tables.Add($range, 2, 4)
$ais.AllowAutoFit = $false
for ($c = 1; $c -le 4; $c++) { $ais.Columns.Item($c).Width = 125 }
Set-Cell ($ais.Cell(1,1)) 'ONBOARD AIS' $navy $white $true 9
Set-Cell ($ais.Cell(1,2)) 'AIS NETWORK' $blue $white $true 9
Set-Cell ($ais.Cell(1,3)) 'KPLER API' $orange $white $true 9
Set-Cell ($ais.Cell(1,4)) 'VSLI SYSTEM' $green $white $true 9
Set-Cell ($ais.Cell(2,1)) 'Xinuo Class B equipment' $light $ink $false 9
Set-Cell ($ais.Cell(2,2)) 'Receiver and satellite coverage' $light $ink $false 9
Set-Cell ($ais.Cell(2,3)) 'Activation and pricing pending' $light $ink $false 9
Set-Cell ($ais.Cell(2,4)) 'Automatic live-map updates' $light $ink $false 9
Add-Spacer 8
Add-Paragraph 'Current Status: Awaiting Kpler/MarineTraffic confirmation of vessel coverage, API activation, commercial terms, and pricing.' 10.5 $orange $true 12

# Deployment
Add-Heading '5.' 'DEPLOYMENT AND SYSTEM TESTING'
Add-Paragraph 'The latest release has been deployed to the live environment for controlled system testing and validation. This deployment allows authorized users to verify the updated workflows before final operational acceptance.' 10.5 $ink $false 10

$range = $document.Range($document.Content.End - 1, $document.Content.End - 1)
$metrics = $document.Tables.Add($range, 2, 4)
$metrics.AllowAutoFit = $false
for ($c = 1; $c -le 4; $c++) { $metrics.Columns.Item($c).Width = 125 }
Set-Cell ($metrics.Cell(1,1)) '43' (Color 235 242 252) $blue $true 22
Set-Cell ($metrics.Cell(1,2)) '374' (Color 234 248 251) $cyan $true 22
Set-Cell ($metrics.Cell(1,3)) '0' (Color 232 247 241) $green $true 22
Set-Cell ($metrics.Cell(1,4)) '100%' (Color 253 247 229) $gold $true 22
Set-Cell ($metrics.Cell(2,1)) 'Targeted tests passed' $white $muted $true 8.5
Set-Cell ($metrics.Cell(2,2)) 'Assertions passed' $white $muted $true 8.5
Set-Cell ($metrics.Cell(2,3)) 'New tables or columns' $white $muted $true 8.5
Set-Cell ($metrics.Cell(2,4)) 'Selected test success' $white $muted $true 8.5
Add-Spacer 8
Add-Bullet 'Validated vessel-access restrictions and vessel assignments.'
Add-Bullet 'Validated calendar and checklist permissions.'
Add-Bullet 'Validated organization access and User Management workflows.'
Add-Bullet 'Applied and verified the required live permission migration.'
Add-Bullet 'Cleared application caches after deployment.'

# Next Steps
Add-Heading '6.' 'NEXT STEPS'
Add-Bullet 'Monitor the testing deployment and collect user feedback on the Operation Manager vessel-assignment workflow.'
Add-Bullet 'Follow up with Kpler/MarineTraffic regarding API activation, Philippine vessel coverage, commercial terms, and pricing.'
Add-Bullet 'After approval, implement secure server-side AIS synchronization and MMSI/IMO-based vessel matching.'
Add-Bullet 'Validate live-map accuracy, route behavior, and access permissions before final operational sign-off.'
Add-Bullet 'Continue improving the usability and accuracy of planned-route and actual-track visualization.'

Add-Spacer 14
$range = $document.Range($document.Content.End - 1, $document.Content.End - 1)
$closing = $document.Tables.Add($range, 1, 1)
$closing.Columns.Item(1).Width = 500
Set-Cell ($closing.Cell(1,1)) 'MANAGEMENT NOTE  •  Core system improvements are available for testing. Completion of automatic AIS tracking remains dependent on Kpler/MarineTraffic service activation.' $navy $white $true 10

# Header and footer
foreach ($section in $document.Sections) {
    $header = $section.Headers.Item(1).Range
    $header.Text = 'VILLA GROUP OF COMPANIES  |  IT DEPARTMENT'
    $header.Font.Name = 'Aptos'
    $header.Font.Size = 8
    $header.Font.Bold = 1
    $header.Font.Color = $muted

    $footer = $section.Footers.Item(1).Range
    $footer.Text = 'Internal Management Update  •  October 16, 2026'
    $footer.Font.Name = 'Aptos'
    $footer.Font.Size = 8
    $footer.Font.Color = $muted
    $footer.ParagraphFormat.Alignment = 0
}

$document.SaveAs2($outputPath, 16)
$document.Close()
$word.Quit()
[System.Runtime.InteropServices.Marshal]::ReleaseComObject($document) | Out-Null
[System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null
Write-Output $outputPath
