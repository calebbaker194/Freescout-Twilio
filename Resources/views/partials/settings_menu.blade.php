<li @if (\App\Misc\Helper::isMenuSelected('twilio'))class="active" @endif>
  <a href="{{ route('mailboxes.twilio.settings', ['mailbox_id'=>$mailbox->id]) }}">
    <i class="glyphicon glyphicon-phone"></i> {{ __('Twilio') }}
  </a>
</li>
