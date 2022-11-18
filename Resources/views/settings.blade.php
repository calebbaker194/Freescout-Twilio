@extends('layouts.app')

@section('title_full', __('Twilio').' - '.$mailbox->name)

@section('sidebar')
    @include('partials/sidebar_menu_toggle')
    @include('mailboxes/sidebar_menu')
@endsection

@section('content')

    <div class="section-heading margin-bottom">
        {{ __('Twilio') }}
    </div>

    <div class="col-xs-12">
  
        <form class="form-horizontal margin-bottom" method="POST" action="" autocomplete="off">
            {{ csrf_field() }}


            <div class="form-group">
                <label class="col-sm-2 control-label">{{ __('Account SID') }}</label>

                <div class="col-sm-6">
                    <input type="text" class="form-control input-sized-lg" name="settings[account_sid]" value="{{ $settings['account_sid'] ?? '' }}">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label">{{ __('Auth token') }}</label>

                <div class="col-sm-6">
                    <input type="text" class="form-control input-sized-lg" name="settings[auth_token]" value="{{ $settings['auth_token'] ?? '' }}">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label">{{ __('Callback URL') }}</label>

                <div class="col-sm-6">
                    <label class="control-label">
                        <span class="text-help">{{ route('twilio.webhook', ['mailbox_id' => $mailbox->id, 'mailbox_secret' => \Twilio::getMailboxSecret($mailbox->id)]) }}</span>
                    </label>
                </div>
            </div>

            <div class="form-group margin-top">
                <div class="col-sm-6 col-sm-offset-2">
                    <button type="submit" class="btn btn-primary">
                        {{ __('Save') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
