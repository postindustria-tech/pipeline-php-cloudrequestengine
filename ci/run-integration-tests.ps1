param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Parameter(Mandatory=$true)]
    [hashtable]$Keys
)

./php/run-integration-tests.ps1 -RepoName $RepoName -Keys $Keys

exit $LASTEXITCODE
