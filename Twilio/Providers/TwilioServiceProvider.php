<?php

namespace Modules\Twilio\Providers;

use App\Attachment;
use App\Conversation;
use App\Customer;
use App\Mailbox;
use App\Thread;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;
use Twilio\Rest\Client;

require_once __DIR__.'/../vendor/autoload.php';

class TwilioServiceProvider extends ServiceProvider
{
    const DRIVER = 'twilio';

    const CHANNEL = 3194;
    const CHANNEL_NAME = 'Twilio';

    const LOG_NAME = 'twilio_errors';
    const SALT = '1dwVMOD0RMC';

    public static $skip_messages = [
        '%%%_IMAGE_%%%',
        '%%%_VIDEO_%%%',
        '%%%_FILE_%%%',
        '%%%_AUDIO_%%%',
        '%%%_LOCATION_%%%',
    ];

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->hooks();
    }

    /**
     * Module hooks.
     */
    public function hooks()
    {
        // Add item to the mailbox menu
        \Eventy::addAction('mailboxes.settings.menu', function($mailbox) {
            if (auth()->user()->isAdmin()) {
                echo \View::make('twilio::partials/settings_menu', ['mailbox' => $mailbox])->render();
            }
        }, 20);


        \Eventy::addFilter('menu.selected', function($menu) {
            $menu['twilio'] = [
                'mailboxes.twilio.settings',
            ];
            return $menu;
        });

        \Eventy::addFilter('channel.name', function($name, $channel) {
            if ($name) {
                return $name;
            }
            if ($channel == self::CHANNEL) {
                return self::CHANNEL_NAME;
            } else {
                return $name;
            }
        }, 20, 2);


        \Eventy::addAction('chat_conversation.send_reply', function($conversation, $replies, $customer) {

            \Twilio::log('Sending Text Reply');

            if ($conversation->channel != self::CHANNEL) {
                 \Twilio::log('No Channel');
                return;
            }

            if (!$customer->channel_id) {
                \Twilio::log('Can not send a reply to the customer ('.$customer->id.': '.$customer->getFullName().'): customer has no messenger ID.', $conversation->mailbox);
                return;
            }

            $twilio = \Twilio::getTwilio($conversation->mailbox,null);

            if (!$twilio) {
                 \Twilio::log('No Twilio Client');
                return;
            }
            
            // We send only the last reply.
            $replies = $replies->sortByDesc(function ($item, $key) {
                return $item->id;
            });
            $thread = $replies[0];

            // If thread is draft, it means it has been undone
            $thread = $thread->fresh();
            
            if ($thread->isDraft()) {
                return;
            }
            
            $mediaUrls = [];
            
            if ($thread->has_attachments) {
                foreach ($thread->attachments as $attachment) {
                    array_push($mediaUrls,$attachment->url());
                }
            }           
            
             \Twilio::log('Sending Message With From : +'.$customer->channel_id);
            
            // Text Customer $text,  $customer->channel_id (Probably number too)
            $message = $twilio->messages
                  ->create("+".$customer->channel_id, // to
                           [
                               "body" => $thread->getBodyAsText(),
                               "from" => "+18884283898",
                               "mediaUrl" => $mediaUrls
                           ]
            );

        }, 20, 3);

    }

    public static function getTwilio($mailbox,$request)
    {
        $driver_config = $mailbox->meta['twilio'] ?? [];

        $driver_config['verification'] = \Twilio::getMailboxVerifyToken($mailbox->id);

        if (empty($driver_config['account_sid']) || empty($driver_config['auth_token'])) {
            \Twilio::log('Webhook executed, but '.self::CHANNEL_NAME.' is not configured for this mailbox.', $mailbox);
            return false;
        }

        if($request != null and ($driver_config['account_sid'] != $request->get('AccountSid'))) {
            \Twilio::log('Webhook executed, but Incorrect AccountSid'.$driver_config['account_sid'].' Expected '.$request->get('AccountSid'), $mailbox);
            return false;
        }
        
        return new Client($driver_config['account_sid'], $driver_config['auth_token']);
    }

    public static function getFinalUrl($uri)
    {
      \Twilio::log('Finding final url', null);
      $gclient = new \GuzzleHttp\Client(['allow_redirects' => ['track_redirects' => true]]);
      $gresponse = $gclient->get($uri, [
        'query'   => ['get' => 'params'],
    	'on_stats' => function (\GuzzleHttp\TransferStats $stats) use (&$url) {
    	  \Twilio::log($stats->getEffectiveUri(), null);
          $url = $stats->getEffectiveUri();
        }
      ]);
      
      return $url; 
    }

    public static function processIncomingMessage($from, $text, $mailbox, $files = [])
    {
        $messenger_user = null;
        $customer_info = [];
        $customer_info['id'] = ltrim($from,"+");
        $customer_info['first_name'] = $from;
        $customer_info['last_name'] = substr(crc32(time()), 0, 5);
        $customer_info['email'] = '';
        $customer_info['profile_pic'] = '';

        $channel = \Twilio::CHANNEL;

        $customer = Customer::where('channel', $channel)
            ->where('channel_id', ltrim($from, "+"))
            ->first();

        $channel_id = $customer_info['id'];
        
        if (!$customer) {
            if ($messenger_user) {
                $customer_info = $messenger_user->getInfo();
            }
            $customer_data = [
                'channel' => $channel,
                'channel_id' => $channel_id,
                'first_name' => $customer_info['first_name'] ?: $channel_id,
                'last_name' => $customer_info['last_name'],
                // 'social_profiles' => Customer::formatSocialProfiles([[
                //     'type' => Customer::SOCIAL_TYPE_SMS,
                // ]])
            ];

            // Social networks.
            $email = $customer_info['email'] ?? $customer_info['mail'] ?? '';
            if ($email) {
                $customer = Customer::create($email, $customer_data);
            } else {
                $customer = Customer::createWithoutEmail($customer_data);
            }
            if (!$customer) {
                \Twilio::log('Could not create a customer.', $mailbox);
                return;
            }
        }

        // Get last customer conversation or create a new one.
        $conversation = Conversation::where('mailbox_id', $mailbox->id)
            ->where('customer_id', $customer->id)
            ->where('channel', $channel)
            ->orderBy('created_at', 'desc')
            ->first();

        $attachments = [];
        if (count($files)) {
            foreach ($files as $file) {
                if (!$file->url) {
                    continue;
                }
                $attachments[] = [
                    'file_name' => \Helper::remoteFileName($file->url),
                    'file_url' => \Twilio::getFinalUrl($file->url),
                    'mime_type' => $file->mime,
                ];
            }
        }

        if ($conversation) {
            // Create thread in existing conversation.
            Thread::createExtended([
                    'type' => Thread::TYPE_CUSTOMER,
                    'customer_id' => $customer->id,
                    'body' => $text,
                    'attachments' => $attachments,
                ],
                $conversation,
                $customer
            );
        } else {
            // Create conversation.
            Conversation::create([
                    'type' => Conversation::TYPE_CHAT,
                    'subject' => Conversation::subjectFromText($text),
                    'mailbox_id' => $mailbox->id,
                    'source_type' => Conversation::SOURCE_TYPE_WEB,
                    'channel' => $channel,
                ], [[
                    'type' => Thread::TYPE_CUSTOMER,
                    'customer_id' => $customer->id,
                    'body' => $text,
                    'attachments' => $attachments,
                ]],
                $customer
            );
        }
    }

    public static function getMailboxSecret($id)
    {
        return crc32(config('app.key').$id.'salt'.self::SALT);
    }

    public static function getMailboxVerifyToken($id)
    {
        return crc32(config('app.key').$id.'verify'.self::SALT).'';
    }

    public static function log($text, $mailbox = null, $is_webhook = true)
    {
        \Helper::log(\Twilio::LOG_NAME, '['.self::CHANNEL_NAME.($is_webhook ? ' Webhook' : '').'] '.($mailbox ? '('.$mailbox->name.') ' : '').$text);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTranslations();
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('twilio.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'twilio'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/twilio');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/twilio';
        }, \Config::get('view.paths')), [$sourcePath]), 'twilio');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $this->loadJsonTranslationsFrom(__DIR__ .'/../Resources/lang');
    }

    /**
     * Register an additional directory of factories.
     * @source https://github.com/sebastiaanluca/laravel-resource-flow/blob/develop/src/Modules/ModuleServiceProvider.php#L66
     */
    public function registerFactories()
    {
        if (! app()->environment('production')) {
            app(Factory::class)->load(__DIR__ . '/../Database/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
