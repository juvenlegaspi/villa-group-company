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
$red = Color 201 55 64
$ink = Color 28 43 62
$muted = Color 102 118 138
$light = Color 242 246 251
$white = Color 255 255 255
$line = Color 215 225 237

$outputDir = Join-Path $PSScriptRoot '..\deliverables'
New-Item -ItemType Directory -Force -Path $outputDir | Out-Null
$outputPath = [System.IO.Path]::GetFullPath((Join-Path $outputDir 'Weekly_IT_System_Development_Update_2026-10-16.pptx'))

$ppt = New-Object -ComObject PowerPoint.Application
$ppt.Visible = -1
$presentation = $ppt.Presentations.Add()
$presentation.PageSetup.SlideSize = 16
$sw = $presentation.PageSetup.SlideWidth
$sh = $presentation.PageSetup.SlideHeight

function Add-Text($slide, $text, $x, $y, $w, $h, $size = 18, $color = $ink, $bold = $false, $font = 'Aptos') {
    $shape = $slide.Shapes.AddTextbox(1, $x, $y, $w, $h)
    $shape.TextFrame.TextRange.Text = $text
    $shape.TextFrame.MarginLeft = 0
    $shape.TextFrame.MarginRight = 0
    $shape.TextFrame.MarginTop = 0
    $shape.TextFrame.MarginBottom = 0
    $shape.TextFrame.TextRange.Font.Name = $font
    $shape.TextFrame.TextRange.Font.Size = $size
    $shape.TextFrame.TextRange.Font.Color.RGB = $color
    $shape.TextFrame.TextRange.Font.Bold = $(if ($bold) { -1 } else { 0 })
    return $shape
}

function Add-Box($slide, $x, $y, $w, $h, $fill, $radius = $true, $border = $fill) {
    $shapeType = $(if ($radius) { 5 } else { 1 })
    $shape = $slide.Shapes.AddShape($shapeType, $x, $y, $w, $h)
    $shape.Fill.ForeColor.RGB = $fill
    $shape.Line.ForeColor.RGB = $border
    $shape.Line.Weight = 1
    return $shape
}

function Add-Line($slide, $x1, $y1, $x2, $y2, $color = $line, $weight = 2, $dash = 1) {
    $shape = $slide.Shapes.AddLine($x1, $y1, $x2, $y2)
    $shape.Line.ForeColor.RGB = $color
    $shape.Line.Weight = $weight
    $shape.Line.DashStyle = $dash
    return $shape
}

function Add-Header($slide, $section, $title, $subtitle = '') {
    Add-Text $slide $section.ToUpper() 48 28 620 20 10 $cyan $true | Out-Null
    Add-Text $slide $title 48 52 820 44 28 $navy $true | Out-Null
    if ($subtitle) { Add-Text $slide $subtitle 48 98 820 28 13 $muted $false | Out-Null }
    Add-Line $slide 48 128 912 128 $line 1 | Out-Null
}

function Add-Footer($slide, $number) {
    Add-Text $slide 'VILLA GROUP OF COMPANIES  •  INTERNAL MANAGEMENT UPDATE' 48 516 650 14 8 $muted $true | Out-Null
    Add-Text $slide ("{0:D2}" -f $number) 884 513 28 16 9 $muted $true | Out-Null
}

# Slide 1: Cover
$slide = $presentation.Slides.Add(1, 12)
Add-Box $slide 0 0 $sw $sh $navy $false | Out-Null
Add-Box $slide 0 0 18 $sh $gold $false | Out-Null
Add-Text $slide 'WEEKLY SYSTEMS DEVELOPMENT UPDATE' 58 80 650 30 12 $cyan $true | Out-Null
Add-Text $slide 'Vessel Operations,' 58 132 760 60 34 $white $true | Out-Null
Add-Text $slide 'Tracking & AIS Readiness' 58 188 800 60 34 $white $true | Out-Null
Add-Text $slide 'Progress report covering voyage-route enhancements, role-based vessel access, AIS integration coordination, and testing deployment.' 58 270 700 72 16 (Color 205 220 239) $false | Out-Null
Add-Box $slide 58 376 294 62 (Color 24 61 108) $true (Color 56 93 140) | Out-Null
Add-Text $slide 'REPORTING PERIOD' 76 389 130 15 9 $cyan $true | Out-Null
Add-Text $slide 'October 12–16, 2026' 76 408 240 22 14 $white $true | Out-Null
Add-Text $slide 'Prepared by: IT Department' 58 478 360 20 11 (Color 181 201 227) $false | Out-Null

# Slide 2: Executive summary
$slide = $presentation.Slides.Add(2, 12)
Add-Header $slide 'Executive summary' 'This week at a glance' 'Four workstreams moved forward, with the AIS connection dependent on provider activation.'
$cards = @(
    @{x=48; title='Voyage Routing'; status='ENHANCED'; color=$blue; body='Auto-generated and manual route options improved, including editable route points.'},
    @{x=272; title='Vessel Access'; status='DEPLOYED'; color=$green; body='Operations Manager and vessel-specific Operation Manager access are now separated.'},
    @{x=496; title='AIS Integration'; status='AWAITING PROVIDER'; color=$orange; body='Kpler/MarineTraffic account and requirements submitted; commercial activation pending.'},
    @{x=720; title='System Testing'; status='READY'; color=$cyan; body='Testing release deployed and verified with targeted automated test coverage.'}
)
foreach ($c in $cards) {
    Add-Box $slide $c.x 164 192 262 $white $true $line | Out-Null
    Add-Box $slide $c.x 164 192 8 $c.color $false $c.color | Out-Null
    Add-Text $slide $c.title ($c.x+18) 188 156 26 16 $navy $true | Out-Null
    Add-Box $slide ($c.x+18) 230 150 26 (Color 239 245 251) $true (Color 239 245 251) | Out-Null
    Add-Text $slide $c.status ($c.x+28) 236 132 14 8 $c.color $true | Out-Null
    Add-Text $slide $c.body ($c.x+18) 280 156 106 12 $muted $false | Out-Null
}
Add-Box $slide 48 450 864 44 $light $true $light | Out-Null
Add-Text $slide 'Overall status' 66 464 110 16 10 $muted $true | Out-Null
Add-Text $slide 'Core system improvements are deployed for testing; external AIS activation remains the primary dependency.' 174 461 712 20 12 $ink $true | Out-Null
Add-Footer $slide 2

# Slide 3: Voyage routing
$slide = $presentation.Slides.Add(3, 12)
Add-Header $slide 'Voyage tracking' 'Improved route planning and actual-track visibility' 'The planned route remains available while actual vessel updates create a distinct operational track.'
$steps = @(
    @{x=58; n='01'; title='Plan'; body='Generate a suggested route or draw a manual route on the map.'; color=$blue},
    @{x=340; n='02'; title='Track'; body='Recorded vessel positions create a separate traveled line.'; color=$cyan},
    @{x=622; n='03'; title='Complete'; body='Connect the latest position to the remaining planned route and destination.'; color=$green}
)
foreach ($s in $steps) {
    Add-Box $slide $s.x 168 244 150 $white $true $line | Out-Null
    Add-Box $slide ($s.x+18) 186 42 42 $s.color $true $s.color | Out-Null
    Add-Text $slide $s.n ($s.x+29) 198 28 15 11 $white $true | Out-Null
    Add-Text $slide $s.title ($s.x+74) 191 138 24 17 $navy $true | Out-Null
    Add-Text $slide $s.body ($s.x+18) 246 204 52 12 $muted $false | Out-Null
}
Add-Line $slide 302 243 336 243 $gold 3 | Out-Null
Add-Line $slide 584 243 618 243 $gold 3 | Out-Null
Add-Text $slide 'ROUTE VISUALIZATION' 58 350 190 18 10 $muted $true | Out-Null
Add-Line $slide 74 411 270 377 $blue 5 4 | Out-Null
Add-Line $slide 270 377 454 430 $blue 5 4 | Out-Null
Add-Line $slide 74 431 248 410 $cyan 5 1 | Out-Null
Add-Line $slide 248 410 454 430 $cyan 5 1 | Out-Null
Add-Text $slide 'Planned route' 490 380 145 18 11 $blue $true | Out-Null
Add-Line $slide 650 390 716 390 $blue 4 4 | Out-Null
Add-Text $slide 'Actual traveled track' 490 421 170 18 11 $cyan $true | Out-Null
Add-Line $slide 650 431 716 431 $cyan 4 1 | Out-Null
Add-Box $slide 754 371 148 82 $light $true $light | Out-Null
Add-Text $slide 'Key benefit' 770 384 115 16 9 $green $true | Out-Null
Add-Text $slide 'Deviations remain visible without losing the approved plan.' 770 405 115 40 10 $ink $true | Out-Null
Add-Footer $slide 3

# Slide 4: Access control
$slide = $presentation.Slides.Add(4, 12)
Add-Header $slide 'User management' 'Clear management scope by vessel responsibility' 'Access now follows the operating structure of Marine Operations.'
Add-Box $slide 62 164 390 286 $white $true $line | Out-Null
Add-Box $slide 508 164 390 286 $white $true $line | Out-Null
Add-Box $slide 62 164 390 58 $navy $true $navy | Out-Null
Add-Box $slide 508 164 390 58 $blue $true $blue | Out-Null
Add-Text $slide 'OPERATIONS MANAGER' 84 183 310 22 17 $white $true | Out-Null
Add-Text $slide 'OPERATION MANAGER' 530 183 310 22 17 $white $true | Out-Null
Add-Text $slide 'Company-wide management scope' 84 242 300 20 13 $navy $true | Out-Null
Add-Text $slide 'Vessel-specific management scope' 530 242 310 20 13 $blue $true | Out-Null
$left = "• Access to all vessels`r• No vessel assignment required`r• Company-wide operational oversight`r• Full Marine Operations visibility"
$right = "• Vessel Assignment is required`r• Access limited to assigned vessels`r• Manager actions within assigned vessels`r• Vessel-specific checklist reminders"
Add-Text $slide $left 84 286 320 122 13 $ink $false | Out-Null
Add-Text $slide $right 530 286 320 122 13 $ink $false | Out-Null
Add-Box $slide 62 466 836 34 (Color 232 247 241) $true (Color 232 247 241) | Out-Null
Add-Text $slide 'Result: stronger data separation, clearer accountability, and access aligned with vessel assignments.' 82 475 800 18 11 $green $true | Out-Null
Add-Footer $slide 4

# Slide 5: AIS
$slide = $presentation.Slides.Add(5, 12)
Add-Header $slide 'AIS integration' 'Kpler / MarineTraffic coordination status' 'The system design is ready to consume live vessel positions once commercial API access is activated.'
$nodes = @(
    @{x=54; title='Onboard AIS'; sub='Xinuo Class B'; color=$navy},
    @{x=264; title='AIS Network'; sub='Receiver coverage'; color=$blue},
    @{x=474; title='Kpler API'; sub='Activation pending'; color=$orange},
    @{x=684; title='VSLI System'; sub='Live monitoring map'; color=$green}
)
foreach ($n in $nodes) {
    Add-Box $slide $n.x 195 174 104 $white $true $line | Out-Null
    Add-Box $slide $n.x 195 174 10 $n.color $false $n.color | Out-Null
    Add-Text $slide $n.title ($n.x+16) 220 145 20 15 $navy $true | Out-Null
    Add-Text $slide $n.sub ($n.x+16) 252 145 18 10 $muted $false | Out-Null
}
Add-Line $slide 228 247 260 247 $gold 3 | Out-Null
Add-Line $slide 438 247 470 247 $gold 3 | Out-Null
Add-Line $slide 648 247 680 247 $gold 3 | Out-Null
Add-Box $slide 54 338 804 112 $light $true $light | Out-Null
Add-Text $slide 'COMPLETED' 76 357 100 15 9 $green $true | Out-Null
Add-Text $slide 'Developer account created • API credential generated • requirements and vessel-monitoring use case submitted' 76 378 750 24 12 $ink $true | Out-Null
Add-Text $slide 'PENDING EXTERNAL ACTION' 76 414 160 15 9 $orange $true | Out-Null
Add-Text $slide 'Provider activation, coverage confirmation, commercial terms, and pricing proposal from Kpler/MarineTraffic' 244 411 590 30 11 $ink $false | Out-Null
Add-Footer $slide 5

# Slide 6: Deployment and testing
$slide = $presentation.Slides.Add(6, 12)
Add-Header $slide 'Deployment & quality' 'Current release deployed for system testing' 'The live environment is being used for controlled validation before final operational acceptance.'
Add-Box $slide 52 160 856 70 (Color 232 247 241) $true (Color 199 232 216) | Out-Null
Add-Text $slide 'TESTING RELEASE DEPLOYED' 76 177 300 22 17 $green $true | Out-Null
Add-Text $slide 'Available for user validation and workflow confirmation' 76 202 520 18 11 $ink $false | Out-Null
Add-Box $slide 724 177 158 34 $green $true $green | Out-Null
Add-Text $slide 'DEPLOYED' 766 187 90 16 10 $white $true | Out-Null
$metrics = @(
    @{x=64; value='43'; label='Targeted tests passed'; color=$blue},
    @{x=286; value='374'; label='Assertions passed'; color=$cyan},
    @{x=508; value='0'; label='New tables or columns'; color=$green},
    @{x=730; value='100%'; label='Selected test success'; color=$gold}
)
foreach ($m in $metrics) {
    Add-Box $slide $m.x 270 176 130 $white $true $line | Out-Null
    Add-Text $slide $m.value ($m.x+18) 292 140 42 28 $m.color $true | Out-Null
    Add-Text $slide $m.label ($m.x+18) 347 140 34 10 $muted $true | Out-Null
}
Add-Text $slide 'Validation completed' 64 431 170 18 10 $muted $true | Out-Null
Add-Text $slide 'Vessel access restrictions  •  Calendar permissions  •  Organization access  •  User management' 64 455 820 24 12 $ink $true | Out-Null
Add-Footer $slide 6

# Slide 7: next steps
$slide = $presentation.Slides.Add(7, 12)
Add-Header $slide 'Next steps' 'Priorities for the next work cycle' 'Focus shifts to live-user validation and the external AIS decision.'
$items = @(
    @{y=164; n='01'; title='Monitor the testing deployment'; body='Collect user feedback and confirm the Operation Manager vessel-assignment workflow.'; color=$green},
    @{y=238; n='02'; title='Follow up with Kpler / MarineTraffic'; body='Secure API activation, vessel coverage confirmation, technical terms, and pricing.'; color=$orange},
    @{y=312; n='03'; title='Prepare AIS server integration'; body='Implement secure server-side synchronization and MMSI/IMO vessel matching after approval.'; color=$blue},
    @{y=386; n='04'; title='Complete operational acceptance'; body='Validate live map accuracy, route behavior, and user permissions before final sign-off.'; color=$cyan}
)
foreach ($i in $items) {
    Add-Box $slide 58 $i.y 54 54 $i.color $true $i.color | Out-Null
    Add-Text $slide $i.n 75 ($i.y+18) 26 18 12 $white $true | Out-Null
    Add-Text $slide $i.title 136 ($i.y+2) 330 25 15 $navy $true | Out-Null
    Add-Text $slide $i.body 136 ($i.y+30) 690 34 11 $muted $false | Out-Null
    Add-Line $slide 136 ($i.y+66) 894 ($i.y+66) $line 1 | Out-Null
}
Add-Box $slide 710 468 184 34 $navy $true $navy | Out-Null
Add-Text $slide 'END OF UPDATE' 750 478 110 16 9 $white $true | Out-Null
Add-Footer $slide 7

$presentation.SaveAs($outputPath, 24)
$presentation.Close()
$ppt.Quit()
[System.Runtime.InteropServices.Marshal]::ReleaseComObject($presentation) | Out-Null
[System.Runtime.InteropServices.Marshal]::ReleaseComObject($ppt) | Out-Null
Write-Output $outputPath
