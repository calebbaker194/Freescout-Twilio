<?php

namespace Modules\Twilio\Http\Controllers;

use App\Conversation;
use App\Customer;
use App\Mailbox;
use App\Thread;
use Twilio\Rest\Client;
use Twilio\Twiml;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Magyarjeti\MimeTypes\MimeTypeConverter;

class TwilioController extends Controller {

    public function webhooks(Request $request, $mailbox_id, $mailbox_secret) {

	if (class_exists('Debugbar')) {
            \Debugbar::disable();
        }
        $mailbox = Mailbox::find($mailbox_id);

        if (!$mailbox || \Twilio::getMailboxSecret($mailbox_id) != $mailbox_secret
        ) {
            \Twilio::log('Incorrect webhook URL: ' . url()->current(), $mailbox ?? null);
            abort(404);
        }
        
        $converter = new MimeTypeConverter;
        
        $twilio = \Twilio::getTwilio($mailbox, $request);

        if (!$twilio) { abort(404);}
        $from = $request->input('From');
        $body = $request->input('Body');
        $NumMedia = (int) $request->input('NumMedia');
        $files = [];
        

        for ($i = 0; $i < $NumMedia; $i++) {
            $file = new class{};
            $file->url = $request->input("MediaUrl$i");
            $MIMEType = $request->input("MediaContentType$i");
            $file->mime = $MIMEType;
            array_push($files, $file);
        }

        \Twilio::processIncomingMessage($from,$body,$mailbox,$files);
    }

    /**
     * Settings.
     */
    public function settings($mailbox_id) {
        $mailbox = Mailbox::findOrFail($mailbox_id);

        if (!auth()->user()->isAdmin()) {
            \Helper::denyAccess();
        }

        $settings = $mailbox->meta['twilio'] ?? [];

        return view('twilio::settings', [
            'mailbox' => $mailbox,
            'settings' => $settings,
        ]);
    }

    /**
     * Settings save.
     */
    public function settingsSave(Request $request, $mailbox_id) {
        $mailbox = Mailbox::findOrFail($mailbox_id);

        $mailbox->setMetaParam('twilio', $request->settings);
        $mailbox->save();

        \Session::flash('flash_success_floating', __('Settings updated'));

        return redirect()->route('mailboxes.twilio.settings', ['mailbox_id' => $mailbox_id]);
    }

}
