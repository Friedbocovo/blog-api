<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Default user channel — used by Laravel notifications.
 */
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Private chat channel — private-chat.{receiverId}
 *
 * Only the intended receiver (identified by {userId}) is allowed
 * to subscribe to their own chat channel.
 *
 * Validates: Requirements 11.1, 11.4, Property 7
 */
Broadcast::channel('chat.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/**
 * Private notification channel — private-user.{userId}
 *
 * Only the user identified by {userId} may subscribe to their own
 * notification channel.
 *
 * Validates: Requirements 11.2, 11.4, Property 7
 */
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/**
 * Presence channel — presence-online
 *
 * Any authenticated user may join the presence channel.
 * Returns user identity data so connected peers can see who is online.
 *
 * Validates: Requirements 11.3, 11.5
 */
Broadcast::channel('online', function ($user) {
    return [
        'id'   => $user->id,
        'name' => $user->name,
    ];
});
