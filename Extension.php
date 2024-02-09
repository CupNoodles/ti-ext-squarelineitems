<?php

namespace CupNoodles\SquareLineItems;


use System\Classes\BaseExtension;
use Admin\Models\Payments_model;
use Illuminate\Support\Facades\Event;

class Extension extends BaseExtension
{

    public function beforeRenderPaymentForm($host, $controller)
    {
        $endpoint = $this->isTestMode() ? 'sandbox.' : '';
        $controller->addJs('https://'.$endpoint.'web.squarecdn.com/v1/square.js', 'square-js');
        $controller->addJs('$/cupnoodles/squarelineitems/assets/process.squarelineitems.js', 'process-square-js');
    }


    public function registerPaymentGateways()
    {


        return [
            \CupNoodles\SquareLineItems\Payments\SquareLineItems::class => [
                'code' => 'squarelineitems',
                'name' => 'lang:cupnoodles.squarelineitems::default.text_payment_title',
                'description' => 'lang:cupnoodles.squarelineitems::default.text_payment_desc',
            ]
        ];
    }


    public function boot()
    {


    }

}
