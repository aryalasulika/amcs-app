<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| AMCS operational channel
|--------------------------------------------------------------------------
| Only authenticated users with permission to view the stand board may
| subscribe. The client listens for a lightweight "changed" signal and then
| refreshes the live board over the REST API.
*/
Broadcast::channel('amcs.stands', function ($user) {
    return (bool) $user->active && $user->can('stands.view');
});
