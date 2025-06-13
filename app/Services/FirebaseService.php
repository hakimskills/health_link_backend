<?php

namespace App\Services;

use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;

class FirebaseService
{
    public function sendNotificationToDevice(string $token, string $title, string $body, int $orderId = null)
{
    $messaging = Firebase::messaging();
    $message = CloudMessage::withTarget('token', $token)
        ->withNotification(Notification::create($title, $body))
        ->withData(['order_id' => (string) $orderId]); // Add order_id
    return $messaging->send($message);
}
}
