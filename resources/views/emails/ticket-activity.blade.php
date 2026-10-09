@php
    $priorityLook = ['low' => ['#f1f5f9', '#475569'], 'medium' => ['#e0f2fe', '#0369a1'], 'high' => ['#ffedd5', '#c2410c'], 'critical' => ['#fee2e2', '#b91c1c']];
    $rows = [];
    if ($ticket) {
        $rows[] = ['Ticket', $ticket->ticket_no];
        $rows[] = ['Subject', $ticket->subject];
        $rows[] = ['Status', $ticket->statusLabel()];
        $rows[] = ['Category', $ticket->category];
        if ($ticket->user) {
            $rows[] = ['Requested by', $ticket->user->name . ($ticket->user->company ? ' (' . $ticket->user->company . ')' : '')];
        }
        if ($ticket->assignee) {
            $rows[] = ['Engineer', $ticket->assignee->name];
        }
        if ($ticket->scheduled_for) {
            $rows[] = ['When', $ticket->whenLabel()];
        }
    }
    $font = "-apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;">
    {{-- Preview text shown next to the subject in the inbox --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#f1f5f9;">{{ $lead }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr><td style="height:6px;background:{{ $brandColor }};font-size:0;line-height:0;">&nbsp;</td></tr>

                    <tr>
                        <td style="padding:22px 28px 0 28px;font-family:{{ $font }};">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="vertical-align:middle;"><img src="{{ $logo }}" alt="{{ $brandName }}" height="40" style="display:block;height:40px;max-width:160px;width:auto;border:0;"></td>
                                    <td style="vertical-align:middle;padding-left:12px;font-size:15px;font-weight:700;color:#0f172a;">{{ $brandName }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 28px 0 28px;font-family:{{ $font }};">
                            <span style="display:inline-block;padding:3px 10px;border-radius:999px;background:{{ $pillBg }};color:{{ $pillFg }};font-size:12px;font-weight:600;">{{ $pill }}</span>
                            <h1 style="margin:12px 0 0 0;font-size:21px;line-height:1.3;font-weight:700;color:#0f172a;">{{ $title }}</h1>
                            <p style="margin:10px 0 0 0;font-size:15px;line-height:1.6;color:#334155;">Hi {{ $name }},</p>
                            <p style="margin:6px 0 0 0;font-size:15px;line-height:1.6;color:#334155;">{{ $lead }}</p>
                        </td>
                    </tr>

                    @if ($rows)
                        <tr>
                            <td style="padding:20px 28px 0 28px;font-family:{{ $font }};">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
                                    @foreach ($rows as $i => [$label, $value])
                                        <tr>
                                            <td style="padding:{{ $i === 0 ? '12px' : '6px' }} 0 6px 16px;width:104px;vertical-align:top;font-size:13px;color:#64748b;">{{ $label }}</td>
                                            <td style="padding:{{ $i === 0 ? '12px' : '6px' }} 16px 6px 0;font-size:14px;color:#0f172a;font-weight:{{ in_array($label, ['Ticket', 'Subject']) ? '600' : '400' }};">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                    @if ($ticket)
                                        @php([$pBg, $pFg] = $priorityLook[$ticket->priority] ?? $priorityLook['medium'])
                                        <tr>
                                            <td style="padding:6px 0 12px 16px;width:104px;font-size:13px;color:#64748b;">Priority</td>
                                            <td style="padding:6px 16px 12px 0;"><span style="display:inline-block;padding:2px 9px;border-radius:999px;background:{{ $pBg }};color:{{ $pFg }};font-size:12px;font-weight:600;">{{ $ticket->priorityLabel() }}</span></td>
                                        </tr>
                                    @endif
                                </table>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:24px 28px 8px 28px;font-family:{{ $font }};">
                            <a href="{{ $url }}" style="display:inline-block;padding:12px 22px;border-radius:9px;background:{{ $brandColor }};color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;">{{ $button }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:6px 28px 26px 28px;font-family:{{ $font }};font-size:12px;line-height:1.5;color:#94a3b8;">
                            Button not working? Copy this link into your browser:<br>
                            <a href="{{ $url }}" style="color:#64748b;word-break:break-all;">{{ $url }}</a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-family:{{ $font }};font-size:12px;line-height:1.6;color:#64748b;">
                            You get this email because you are part of {{ $brandName }} on JMS One IT.
                            @if ($alwaysSent)
                                Security emails are always sent.
                            @else
                                <a href="{{ $prefsUrl }}" style="color:#475569;">Change your email preferences</a> any time in My Profile.
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
