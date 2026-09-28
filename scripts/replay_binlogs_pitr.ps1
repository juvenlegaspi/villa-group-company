param(
    [string] $Database = 'villa_pitr_recovery',
    [int] $FirstFile = 133,
    [int] $LastFile = 147,
    [int] $FinalStopPosition = 20770
)

$ErrorActionPreference = 'Continue'
$mysql = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$mysqlbinlog = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqlbinlog.exe'
$binlogDirectory = 'C:\laragon\data\mysql-8.4'
$workDirectory = 'C:\laragon\www\villa\storage\app\restore_backups\pitr_replay'

New-Item -ItemType Directory -Force -Path $workDirectory | Out-Null

$skipped = [System.Collections.Generic.List[object]]::new()

foreach ($number in $FirstFile..$LastFile) {
    $suffix = $number.ToString('000000')
    $binlogPath = Join-Path $binlogDirectory "binlog.$suffix"
    $startPosition = 4
    $attempt = 0

    while ($true) {
        $attempt++
        if ($attempt -gt 200) {
            throw "Too many recovery attempts for binlog.$suffix"
        }

        $sqlPath = Join-Path $workDirectory "binlog_${suffix}_from_${startPosition}.sql"
        $arguments = @(
            "--rewrite-db=villa->$Database",
            '--idempotent',
            '--disable-log-bin',
            "--start-position=$startPosition",
            "--result-file=$sqlPath"
        )

        if ($number -eq $LastFile) {
            $arguments += "--stop-position=$FinalStopPosition"
        }

        $arguments += $binlogPath
        & $mysqlbinlog @arguments
        if ($LASTEXITCODE -ne 0) {
            throw "mysqlbinlog extraction failed for binlog.$suffix from $startPosition"
        }

        $sourcePath = $sqlPath.Replace('\', '/')
        $mysqlOutput = @(& $mysql -u root $Database -e "source $sourcePath" 2>&1)
        $mysqlExitCode = $LASTEXITCODE

        if ($mysqlExitCode -eq 0) {
            Write-Output "RECOVERED binlog.$suffix from position $startPosition"
            break
        }

        $errorText = ($mysqlOutput | Out-String)
        $lineMatch = [regex]::Match($errorText, 'at line (\d+)')
        if (-not $lineMatch.Success) {
            Write-Output $errorText
            throw "Could not identify the failed SQL line for binlog.$suffix from $startPosition"
        }

        $failedLineNumber = [int] $lineMatch.Groups[1].Value
        $sqlLines = @(Get-Content -LiteralPath $sqlPath)
        $nextPosition = $null

        for ($index = $failedLineNumber; $index -lt $sqlLines.Count; $index++) {
            $positionMatch = [regex]::Match($sqlLines[$index], '^# at (\d+)$')
            if ($positionMatch.Success) {
                $nextPosition = [int64] $positionMatch.Groups[1].Value
                break
            }
        }

        if ($null -eq $nextPosition -or $nextPosition -le $startPosition) {
            Write-Output $errorText
            throw "Could not advance beyond the failed event in binlog.$suffix"
        }

        $contextStart = [Math]::Max(0, $failedLineNumber - 8)
        $contextLength = [Math]::Min(12, $sqlLines.Count - $contextStart)
        $context = ($sqlLines[$contextStart..($contextStart + $contextLength - 1)] -join ' ')
        $skipped.Add([pscustomobject]@{
            File = "binlog.$suffix"
            StartPosition = $startPosition
            FailedLine = $failedLineNumber
            ResumePosition = $nextPosition
            Error = ($errorText -replace '\s+', ' ').Trim()
            Context = ($context -replace '\s+', ' ').Trim()
        })

        Write-Output "SKIPPED binlog.$suffix failed SQL line $failedLineNumber; resuming at $nextPosition"
        $startPosition = $nextPosition
    }
}

Write-Output 'SKIPPED_EVENT_SUMMARY'
$skipped | Format-Table File, StartPosition, FailedLine, ResumePosition, Error -AutoSize
