<?php

namespace YYCircle\JoinJob\Util;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class JPushNotification
{
    private $productionEnvironment;
    private $key;
    private $secret;

    public function __construct(string $platform = 'yyjobs')
    {
        $this->productionEnvironment = config('app.env') == 'production';
        $this->key = config(sprintf('jpush.%s.app_key', $platform));
        $this->secret = config(sprintf('jpush.%s.master_secret', $platform));
    }
    /**
     * @param  array  $registrationIds  设备ID
     * @param  string  $title  通知标题
     * @param  string  $alert  通知内容
     * @param  string  $messageType
     * @param  array  $extras
     *
     * @return void
     */
    public function push(array $registrationIds, string $title, string $alert, string $messageType, $extras = [])
    {
        $countIds = count($registrationIds);
        if ($countIds == 0) {
            return;
        }

        $chunks = array_chunk($registrationIds, 1000);

        $extras['msg_type'] = $messageType;
        $extras['nationality'] = 'uk';
        foreach ($chunks as $chunk) {
            try {
                (new Client(['verify' => false]))->request(
                    'POST',
                    'https://api.jpush.cn/v3/push',
                    [
                        'auth' => [$this->key, $this->secret],
                        'json' => [
                            'platform'     => 'all',
                            'audience'     => ['registration_id' => $chunk],
                            'notification' => [
                                'android' => [
                                    'alert'  => $alert,
                                    'title'  => $title,
                                    'sound'  => 'sound',
                                    'extras' => $extras,
                                ],
                                'ios'     => [
                                    'alert'  => $alert,
                                    'sound'  => 'sound.caf',
                                    'badge'  => 1,
                                    'extras' => $extras,
                                ],
                            ],
                            'options'      => [
                                'apns_production' => $this->productionEnvironment
                            ],
                        ]
                    ]
                );
            } catch (\Exception|GuzzleException $e) {
                Log::error("JPush http request error. response body:[{$e->getMessage()}] message type:[\"$messageType\"] push message:[\"$alert\"].");
            }
        }
    }
}