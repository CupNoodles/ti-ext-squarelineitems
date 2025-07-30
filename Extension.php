<?php

namespace CupNoodles\SquareLineItems;


use Admin\Widgets\Form;
use Admin\Models\Menus_model;
use Admin\Models\Payments_model;

use System\Classes\BaseExtension;
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
        $this->addExtraMenuNameFields();

    }

    protected function addExtraMenuNameFields()
    {

        // this flag is necessary to stop menu_options_model firing multiple times
        $isExtended = false;

        Event::listen('admin.form.extendFieldsBefore', function (Form $form) use (&$isExtended) {

            if ($isExtended) return;

            if ($form->model instanceof Menus_model) {

                $form->tabs['fields']['square_item_id'] = [
                        'label' => 'cupnoodles.squarelineitems::default.square_item_id_label',
                        'type' => 'text',
                        'span' => 'left'
                ];

                $isExtended = true;

            }

        });

    }

}
