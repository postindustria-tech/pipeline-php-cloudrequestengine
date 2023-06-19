param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Parameter(Mandatory=$true)]
    [hashtable]$Keys
)

$env:RESOURCEKEY = $Keys.TestResourceKey

./php/run-integration-tests.ps1 -RepoName $RepoName

exit $LASTEXITCODE
