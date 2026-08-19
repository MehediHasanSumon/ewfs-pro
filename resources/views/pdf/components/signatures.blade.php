<div class="signature-section" style="margin-top: 30px;">
    <table style="width: 100%; border: none; margin: 0; background: none;">
        <tr style="background: none !important;">
            @if(isset($signatures) && is_array($signatures))
                @foreach($signatures as $sig)
                    <td style="border: none !important; text-align: center; padding: 20px 10px; font-weight: bold; font-size: 12px;">
                        {{ $sig }}
                    </td>
                @endforeach
            @else
                <td style="border: none !important; text-align: center; padding: 20px 10px; font-weight: bold; font-size: 12px;">Prepared By</td>
                <td style="border: none !important; text-align: center; padding: 20px 10px; font-weight: bold; font-size: 12px;">Checked By</td>
                <td style="border: none !important; text-align: center; padding: 20px 10px; font-weight: bold; font-size: 12px;">Chief Accountant</td>
                <td style="border: none !important; text-align: center; padding: 20px 10px; font-weight: bold; font-size: 12px;">Manager</td>
                <td style="border: none !important; text-align: center; padding: 20px 10px; font-weight: bold; font-size: 12px;">Director</td>
                <td style="border: none !important; text-align: center; padding: 20px 10px; font-weight: bold; font-size: 12px;">Managing Director</td>
            @endif
        </tr>
    </table>
</div>
