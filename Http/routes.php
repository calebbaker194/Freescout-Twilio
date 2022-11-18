<?php

// Webhook.
Route::group([/*'middleware' => 'web', */'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\Twilio\Http\Controllers'], function()
{
    Route::post('/twilio/webhook/{mailbox_id}/{mailbox_secret}', 'TwilioController@webhooks') -> name('twilio.webhook');
});

// Admin.
Route::group(['middleware' => 'web', 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\Twilio\Http\Controllers'], function()
{
    Route::get('/mailbox/{mailbox_id}/twilio', ['uses' => 'TwilioController@settings', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('mailboxes.twilio.settings');
    Route::post('/mailbox/{mailbox_id}/twilio', ['uses' => 'TwilioController@settingsSave', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']]);
});
