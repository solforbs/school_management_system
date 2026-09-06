<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>{{ $institutionName }} — notice</title>
</head>

<body style="font-family: sans-serif; color: #18181b; margin: 0; padding: 24px; background: #fafafa;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 32px;">
        <p style="margin: 0 0 4px; font-size: 13px; font-weight: 600; color: #4f46e5; text-transform: uppercase; letter-spacing: .04em;">
            {{ $institutionName }}
        </p>
        <p style="margin: 16px 0 0; font-size: 14px; line-height: 1.7; white-space: pre-line;">{{ $bodyText }}</p>
    </div>

    <p style="max-width: 560px; margin: 16px auto 0; font-size: 11px; color: #a1a1aa; text-align: center;">
        Sent by {{ $institutionName }} via {{ config('app.name') }}.
    </p>
</body>

</html>
