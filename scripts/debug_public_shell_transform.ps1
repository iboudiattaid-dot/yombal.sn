param(
    [string]$InputPath = 'C:\Users\Administrator\Documents\YOMBAL\tmp-fetch\partner.html'
)

$ErrorActionPreference = 'Stop'

function Find-TagRegion {
    param(
        [string]$Html,
        [string]$Tag,
        [string[]]$Anchors,
        [switch]$ExpandFooterTail
    )

    $tagStart = $null

    foreach ($anchor in $Anchors) {
        $anchorPos = $Html.IndexOf($anchor, [System.StringComparison]::OrdinalIgnoreCase)
        if ($anchorPos -lt 0) {
            continue
        }

        $prefix = $Html.Substring(0, $anchorPos)
        $candidate = $prefix.LastIndexOf("<$Tag", [System.StringComparison]::OrdinalIgnoreCase)
        if ($candidate -lt 0) {
            continue
        }

        $tagStart = $candidate
        break
    }

    if ($null -eq $tagStart) {
        return $null
    }

    $closing = "</$Tag>"
    $tagClose = $Html.IndexOf($closing, $tagStart, [System.StringComparison]::OrdinalIgnoreCase)
    if ($tagClose -lt 0) {
        return $null
    }

    $end = $tagClose + $closing.Length

    if ($ExpandFooterTail) {
        $tail = $Html.Substring($end)
        $pattern = '^\s*(?:<!--\s*Responsive footer\s*-->\s*)?<style\b[^>]*>.*?</style>'
        $match = [regex]::Match($tail, $pattern, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase -bor [System.Text.RegularExpressions.RegexOptions]::Singleline)
        if ($match.Success) {
            $end += $match.Length
        }
    }

    return @{
        Start = $tagStart
        End = $end
    }
}

$text = Get-Content -LiteralPath $InputPath -Raw
Write-Output ("file={0}" -f $InputPath)
Write-Output ("orig len={0}" -f $text.Length)

$header = '<header class="yhr-site-header" data-yhr-site-header>HEADER</header>'
$headerRegion = Find-TagRegion -Html $text -Tag 'header' -Anchors @('<header id="site-header"', ' id="site-header"', ' class="site-header"')
if ($null -eq $headerRegion) {
    throw 'Header region not found'
}

$text2 = $text.Substring(0, $headerRegion.Start) + $header + $text.Substring($headerRegion.End)
Write-Output ("after insert len={0} yhr={1}" -f $text2.Length, $text2.Contains('data-yhr-site-header'))

$headerRegion2 = Find-TagRegion -Html $text2 -Tag 'header' -Anchors @('<header id="site-header"', ' id="site-header"', ' class="site-header"')
if ($null -ne $headerRegion2) {
    $text2 = $text2.Substring(0, $headerRegion2.Start) + $text2.Substring($headerRegion2.End)
}

Write-Output ("after remove header len={0} site={1} yhr={2}" -f $text2.Length, $text2.Contains('site-header'), $text2.Contains('data-yhr-site-header'))

$footer = '<footer class="yhr-site-footer">FOOTER</footer>'
$footerRegion = Find-TagRegion -Html $text2 -Tag 'footer' -Anchors @('<footer id="page-footer"', ' id="page-footer"', ' class="site-footer"') -ExpandFooterTail
if ($null -ne $footerRegion) {
    $text2 = $text2.Substring(0, $footerRegion.Start) + $footer + $text2.Substring($footerRegion.End)
}

if (-not $text2.Contains('yhr-site-footer')) {
    $marker = '<!-- #page-wrap -->'
    $markerPos = $text2.IndexOf($marker, [System.StringComparison]::OrdinalIgnoreCase)
    if ($markerPos -ge 0) {
        $text2 = $text2.Substring(0, $markerPos) + $footer + "`n`n" + $text2.Substring($markerPos)
    }
}

Write-Output ("after footer len={0} footer={1}" -f $text2.Length, $text2.Contains('yhr-site-footer'))
Write-Output '---START---'
Write-Output ($text2.Substring(0, [Math]::Min(500, $text2.Length)))
Write-Output '---END---'
Write-Output ($text2.Substring([Math]::Max(0, $text2.Length - 500)))
