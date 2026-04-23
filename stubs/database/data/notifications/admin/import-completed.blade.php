<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
    <tr>
        <td style="font-size: 15px; line-height: 24px; color: #334155;">
            <!-- Section Label -->
            <p
                style="margin: 0 0 4px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                📥 Import Completed</p>

            <p style="margin: 0 0 20px; font-size: 16px; line-height: 26px; color: #334155;">
                Hi <strong style="color: #0f172a;">{{ $user->first_name ?? ($user->name ?? 'System') }}</strong>,
            </p>

            <p style="margin: 0 0 28px; font-size: 15px; line-height: 24px; color: #334155;">Your
                <strong>{{ $import->model }}</strong> import has completed with status
                <strong>{{ $import->status }}</strong>.</p>

            <!-- Section Header -->
            <p
                style="margin: 32px 0 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                Import Summary</p>

            <!-- Data Card -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px; background-color: #f8fafc; overflow: hidden;">
                <tbody>
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Successfully Imported</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; font-weight: 700; color: #16a34a; text-align: right;">
                            {{ $import->successed }}</td>
                    </tr>
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Failed</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; font-weight: 700; color: #dc2626; text-align: right;">
                            {{ $import->failed }}</td>
                    </tr>
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Skipped</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                            {{ $import->skipped }}</td>
                    </tr>
                    <tr>
                        <td
                            style="padding: 13px 18px; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Total Processed</td>
                        <td
                            style="padding: 13px 18px; font-size: 14px; font-weight: 700; color: #0f172a; text-align: right;">
                            {{ $import->successed + $import->failed + $import->skipped }}</td>
                    </tr>
                </tbody>
            </table>

            @if ($import->failed + $import->skipped > 0)
                <!-- Warning Panel for Failures -->
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                    style="background-color: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; margin: 24px 0;">
                    <tr>
                        <td style="padding: 16px 20px;">
                            <p style="margin: 0; font-size: 14px; line-height: 22px; color: #991b1b;">⚠️ Some rows did
                                not import successfully. Review the failed/skipped logs for details.</p>
                        </td>
                    </tr>
                </table>
            @else
                <!-- Success Panel -->
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                    style="background-color: #f0fdf4; border: 1px solid #dcfce7; border-radius: 8px; margin: 24px 0;">
                    <tr>
                        <td style="padding: 16px 20px;">
                            <p style="margin: 0; font-size: 14px; line-height: 22px; color: #166534;">✅ All rows
                                imported successfully!</p>
                        </td>
                    </tr>
                </table>
            @endif

            <!-- Info Note -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background-color: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; margin: 24px 0;">
                <tr>
                    <td style="padding: 12px 16px;">
                        <p style="margin: 0; font-size: 12px; line-height: 18px; color: #0c4a6e;">ℹ️ This summary is
                            based only on counts provided at completion. Detailed error lines are available in the
                            import log.</p>
                    </td>
                </tr>
            </table>

            <p style="margin: 28px 0 0; font-size: 15px; line-height: 24px; color: #334155;">
                Regards,<br>
                <strong style="color: #0f172a;">{{ $app->name }}</strong>
            </p>
        </td>
    </tr>
</table>
