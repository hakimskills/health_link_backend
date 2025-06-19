<!DOCTYPE html>
<html>
<head>
    <title>Registration Status</title>
</head>
<body>
    <p>Hello <?php echo e($firstName); ?> <?php echo e($lastName); ?>,</p>

    <?php if($status == 'approved'): ?>
        <p>Congratulations! Your registration request has been approved. You can now log in to your account.</p>
    <?php else: ?>
        <p>We're sorry, but your registration request has been rejected.</p>
    <?php endif; ?>

    <p>Best regards,<br>Admin Team</p>
</body>
</html>
<?php /**PATH C:\Games\New folder\health_link_backend\resources\views/emails/registration_status.blade.php ENDPATH**/ ?>