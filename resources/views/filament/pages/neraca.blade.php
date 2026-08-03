<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <div style="border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: white;">
            <div style="padding: 16px 24px; font-size: 16px; font-weight: 600; background: #f9fafb; border-bottom: 1px solid #e5e7eb;">Aset</div>
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 12px 24px; font-size: 14px; color: #4b5563;">Kas</td>
                    <td style="padding: 12px 24px; font-size: 14px; text-align: right;">Rp {{ number_format($saldoAset['1100'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 12px 24px; font-size: 14px; color: #4b5563;">Piutang Usaha</td>
                    <td style="padding: 12px 24px; font-size: 14px; text-align: right;">Rp {{ number_format($saldoAset['1200'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 12px 24px; font-size: 14px; color: #4b5563;">Persediaan</td>
                    <td style="padding: 12px 24px; font-size: 14px; text-align: right;">Rp {{ number_format($saldoAset['1300'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="background: #f9fafb; border-top: 2px solid #d1d5db; font-weight: 600;">
                    <td style="padding: 12px 24px; font-size: 14px;">Total Aset</td>
                    <td style="padding: 12px 24px; font-size: 14px; text-align: right;">Rp {{ number_format($totalAset, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        <div style="border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: white;">
            <div style="padding: 16px 24px; font-size: 16px; font-weight: 600; background: #f9fafb; border-bottom: 1px solid #e5e7eb;">Kewajiban & Modal</div>
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 12px 24px; font-size: 14px; color: #4b5563;">Hutang Usaha</td>
                    <td style="padding: 12px 24px; font-size: 14px; text-align: right;">Rp {{ number_format($saldoKewajibanModal['2100'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 12px 24px; font-size: 14px; color: #4b5563;">Modal</td>
                    <td style="padding: 12px 24px; font-size: 14px; text-align: right;">Rp {{ number_format($saldoKewajibanModal['3100'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 12px 24px; font-size: 14px; color: #4b5563;">Laba Ditahan</td>
                    <td style="padding: 12px 24px; font-size: 14px; text-align: right; {{ ($saldoKewajibanModal['laba_ditahan'] ?? 0) < 0 ? 'color: #dc2626;' : '' }}">Rp {{ number_format($saldoKewajibanModal['laba_ditahan'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="background: #f9fafb; border-top: 2px solid #d1d5db; font-weight: 600;">
                    <td style="padding: 12px 24px; font-size: 14px;">Total Kewajiban & Modal</td>
                    <td style="padding: 12px 24px; font-size: 14px; text-align: right;">Rp {{ number_format($totalKewajibanModal, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div style="margin-top: 24px; border-radius: 12px; padding: 16px; border: 1px solid {{ abs($selisih) < 1 ? '#bbf7d0' : '#fca5a5' }}; background: {{ abs($selisih) < 1 ? '#f0fdf4' : '#fef2f2' }};">
        <p style="font-weight: 600; color: {{ abs($selisih) < 1 ? '#15803d' : '#b91c1c' }};">
            @if(abs($selisih) < 1)
                ✅ Neraca balance (Aset = Kewajiban + Modal)
            @else
                ⚠️ Neraca TIDAK balance — selisih Rp {{ number_format($selisih, 0, ',', '.') }}. Ada transaksi yang jurnalnya belum lengkap/konsisten.
            @endif
        </p>
    </div>
</x-filament-panels::page>