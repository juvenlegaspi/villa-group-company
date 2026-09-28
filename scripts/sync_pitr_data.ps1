param(
    [Parameter(Mandatory = $true)]
    [string] $TargetDatabase,

    [string] $SourceDatabase = 'villa_pitr_recovery'
)

$ErrorActionPreference = 'Stop'
$mysql = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$outputDirectory = 'C:\laragon\www\villa\storage\app\restore_backups'
$outputFile = Join-Path $outputDirectory "sync_${SourceDatabase}_to_${TargetDatabase}.sql"
$excludedTables = @('migrations', 'cache', 'cache_locks', 'sessions', 'password_reset_tokens')

$tableQuery = @"
SELECT t.TABLE_NAME
FROM information_schema.TABLES t
JOIN information_schema.TABLES s
  ON s.TABLE_SCHEMA = '$SourceDatabase'
 AND s.TABLE_NAME = t.TABLE_NAME
 AND s.TABLE_TYPE = 'BASE TABLE'
WHERE t.TABLE_SCHEMA = '$TargetDatabase'
  AND t.TABLE_TYPE = 'BASE TABLE'
ORDER BY t.TABLE_NAME
"@

$tables = @(& $mysql -u root -N -B -e $tableQuery) | Where-Object { $_ -notin $excludedTables }
$statements = [System.Collections.Generic.List[string]]::new()
$statements.Add('SET FOREIGN_KEY_CHECKS=0;')
$statements.Add('START TRANSACTION;')

foreach ($table in $tables) {
    $columnQuery = @"
SET SESSION group_concat_max_len=100000;
SELECT GROUP_CONCAT(CONCAT(CHAR(96), c.COLUMN_NAME, CHAR(96)) ORDER BY c.ORDINAL_POSITION SEPARATOR ',')
FROM information_schema.COLUMNS c
JOIN information_schema.COLUMNS s
  ON s.TABLE_SCHEMA = '$SourceDatabase'
 AND s.TABLE_NAME = c.TABLE_NAME
 AND s.COLUMN_NAME = c.COLUMN_NAME
WHERE c.TABLE_SCHEMA = '$TargetDatabase'
  AND c.TABLE_NAME = '$table'
"@

    $columns = & $mysql -u root -N -B -e $columnQuery
    if (-not $columns) {
        throw "No shared columns found for $table"
    }

    $selectColumns = $columns
    if ($table -eq 'suppliers') {
        $selectQuery = @"
SET SESSION group_concat_max_len=100000;
SELECT GROUP_CONCAT(
    CASE
        WHEN c.COLUMN_NAME = 'tin' THEN CONCAT(
            'CASE WHEN s.id IN (11,13) THEN CONCAT(s.tin,',
            CHAR(39), '-DUP-', CHAR(39),
            ',s.id) ELSE s.tin END'
        )
        ELSE CONCAT('s.', CHAR(96), c.COLUMN_NAME, CHAR(96))
    END
    ORDER BY c.ORDINAL_POSITION SEPARATOR ','
)
FROM information_schema.COLUMNS c
JOIN information_schema.COLUMNS source_column
  ON source_column.TABLE_SCHEMA = '$SourceDatabase'
 AND source_column.TABLE_NAME = c.TABLE_NAME
 AND source_column.COLUMN_NAME = c.COLUMN_NAME
WHERE c.TABLE_SCHEMA = '$TargetDatabase'
  AND c.TABLE_NAME = 'suppliers'
"@
        $selectColumns = & $mysql -u root -N -B -e $selectQuery
    }

    $statements.Add("DELETE FROM ``$TargetDatabase``.``$table``;")
    $statements.Add("INSERT INTO ``$TargetDatabase``.``$table`` ($columns) SELECT $selectColumns FROM ``$SourceDatabase``.``$table`` s;")
}

$statements.Add('COMMIT;')
$statements.Add('SET FOREIGN_KEY_CHECKS=1;')
[System.IO.File]::WriteAllLines($outputFile, $statements, [System.Text.UTF8Encoding]::new($false))

$sourcePath = $outputFile.Replace('\', '/')
& $mysql -u root -e "source $sourcePath"
if ($LASTEXITCODE -ne 0) {
    throw "Point-in-time synchronization failed for $TargetDatabase; the transaction was rolled back."
}

Write-Output "Synchronized $($tables.Count) tables from $SourceDatabase to $TargetDatabase"
Write-Output $outputFile
