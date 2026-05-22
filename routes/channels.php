<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// User receives real-time updates for their own downloads only.
Broadcast::channel('users.{userId}.downloads', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
