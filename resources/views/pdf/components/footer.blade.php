<div class="footer">
    <div class="footer-left">
        @if(isset($leftText))
            {{ $leftText }}
        @elseif($showGeneratedAt ?? true)
            Generated on: {{ now()->format('Y-m-d H:i:s') }}
        @endif
    </div>
    <div class="footer-right">
        @if(isset($rightText))
            {{ $rightText }}
        @elseif(isset($totalRecords))
            Total Records: {{ $totalRecords }}
        @endif
    </div>
</div>
