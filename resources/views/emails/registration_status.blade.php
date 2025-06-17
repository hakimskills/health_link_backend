<!DOCTYPE html>
<html>
<head>
    <title>Registration Status</title>
</head>
<body>
    <p>Hello {{ $firstName }} {{ $lastName }},</p>

    @if($status == 'approved')
        <p>Congratulations! Your registration request has been approved. You can now log in to your account.</p>
    @else
        <p>We're sorry, but your registration request has been rejected.</p>
    @endif

    <p>Best regards,<br>Admin Team</p>
</body>
</html>
