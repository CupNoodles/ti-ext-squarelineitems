<?php

namespace CupNoodles\SquareLineItems\Payments;

use Igniter\PayRegister\Payments\Square;
use Square\Environment;
use Square\Models;
use Square\Models\OrderLineItemTaxScope;
use Square\Models\Builders\OrderLineItemBuilder;
use Square\Models\Builders\OrderLineItemTaxBuilder;
use Square\Models\Builders\OrderServiceChargeBuilder;
use Square\Models\Builders\CreateOrderRequestBuilder;
use Square\Models\Builders\OrderBuilder;
use Square\Models\Builders\MoneyBuilder;
use Square\Models\Builders\OrderQuantityUnitBuilder;
use Square\Models\Builders\MeasurementUnitBuilder;
use Square\Models\Builders\MeasurementUnitCustomBuilder;
use Square\SquareClient;


use Illuminate\Support\Facades\Log;

class SquareLineItems extends Square
{

    public function beforeRenderPaymentForm($host, $controller)
    {

        $endpoint = $this->isTestMode() ? 'sandbox.' : '';
        $controller->addJs('https://'.$endpoint.'web.squarecdn.com/v1/square.js', 'square-js');
        $controller->addJs('$/cupnoodles/squarelineitems/assets/js/process.squarelineitems.js', 'process-square-js');
    }

    public function createPayment($fields, $order, $host)
    {


        try {

            $client = $this->createClient();
            $ordersApi = $client->getOrdersApi();
            $idempotencyKey = str_random();

            $lineItems = [];

            foreach($order->getOrderMenusWithOptions() as $menu){

                $category_name = \Admin\Models\Menus_model::find($menu->menu_id)->categories()->first()->name ?? '';
                $square_item_id = \Admin\Models\Menus_model::find($menu->menu_id)->square_item_id ?? null;

                
                if($menu->quantity != $menu->quantity % 1 ){ // this is only necessary if you have fractional qtys, for instance if you're using pricebyweight
                    
                    $lineItem = OrderLineItemBuilder::init($menu->quantity)
                    ->name($menu->name . '['.$category_name.']')
                    ->catalogObjectID($menu->square_item_id ?? '')
                    ->quantityUnit(
                        OrderQuantityUnitBuilder::init()
                            ->measurementUnit(MeasurementUnitBuilder::init()
                                ->customUnit(MeasurementUnitCustomBuilder::init($menu->uom_tag, $menu->uom_tag)->build())   
                                ->build() 
                            )
                            ->precision($menu->uom_decimals)
                            ->build()
                    )
                    ->basePriceMoney(
                        MoneyBuilder::init()
                            ->amount($menu->price * 100)
                            ->currency($fields['currency'])
                            ->build()
                    );

                    if($square_item_id){
                        $lineItem->catalogObjectID($square_item_id);
                    }


                    $lineItems[] = $lineItem->build();
                    
                }
                else{
                    $lineItem = OrderLineItemBuilder::init($menu->quantity)
                    ->name($menu->name . '['.$category_name.']')
                    
                    ->basePriceMoney(
                        MoneyBuilder::init()
                            ->amount($menu->price * 100)
                            ->currency($fields['currency'])
                            ->build()
                    );
                    
                    if($square_item_id){
                        $lineItem->catalogObjectID($square_item_id);
                    }

                    $lineItem->build();
                    $lineItems[] = $lineItem;
                }

            }

            $taxes = [];
            $serviceCharges = [];
            $subtotal = 0;
            foreach($order->getOrderTotals() as $ot){
                if($ot->code == 'subtotal'){
                    $subtotal += $ot->value;
                }
                if($ot->code == 'driver_tip'){
                    $subtotal += $ot->value;
                }
            }
            foreach($order->getOrderTotals() as $ot){
 
                if($ot->code == 'tax' || stripos($ot->title, 'tax') !== false){
                    if($ot->value != 0){
                        
                        $taxMoney = new Models\Money();
                        $taxMoney->setAmount($ot->value * 100);
                        $taxMoney->setCurrency($fields['currency']);
                        
                        $taxes[] = OrderLineItemTaxBuilder::init()
                        ->name($ot->title)
                        ->percentage( number_format(($ot->value / $subtotal) * 100, 4) ) 
                        ->appliedMoney($taxMoney)
                        ->scope(OrderLineItemTaxScope::ORDER)
                        ->build();
                    }
                }

                if($ot->code == 'delivery'){
                    $deliveryMoney = new Models\Money();
                    $deliveryMoney->setAmount($ot->value * 100);
                    $deliveryMoney->setCurrency($fields['currency']);


                    $serviceCharges[] = OrderServiceChargeBuilder::init()
                    ->name($ot->title)
                    ->amountMoney($deliveryMoney)
                    ->calculationPhase('TOTAL_PHASE')
                    ->build();
                }

                // driver_tip is a custom order total line added via Relay checkout - add as a line item in order to differntiate it from staff tip 
                if($ot->code == 'driver_tip'){
                    $lineItems[] = OrderLineItemBuilder::init(1)
                    ->name('Driver Tip')
                    ->basePriceMoney(
                        MoneyBuilder::init()
                            ->amount($ot->value * 100)
                            ->currency($fields['currency'])
                            ->build()
                    )
                    ->unsetAppliedTaxes(0)
                    ->build();
                }

            }

            

            
            $body = CreateOrderRequestBuilder::init()
            ->order( OrderBuilder::init( $this->getLocationId() )
                ->referenceId($order->order_id)
                ->lineItems($lineItems)
                ->taxes($taxes)
                ->serviceCharges($serviceCharges)
                ->build()
            )
            ->idempotencyKey($idempotencyKey)
            ->build();



            $apiResponse = $ordersApi->createOrder($body);
            Log::info(print_r($apiResponse, true));

            if ($apiResponse->isSuccess()) {
                $createOrderResponse = $apiResponse->getResult();
                $createdOrderID = $createOrderResponse->getOrder()->getID();
            } else {
                
                $errors = $apiResponse->getErrors();
                Log::info(print_r($errors, true));
                $order->logPaymentAttempt('Payment error -> '. print_r($errors, true), 0, $fields, []);
            }

        }        
        catch (Exception $ex) {
            $order->logPaymentAttempt('Payment error -> '.$ex->getMessage(), 0, $fields, []);
            throw new ApplicationException('Error creating order on Square');
        }



        try {
            
            $client = $this->createClient();
            $paymentsApi = $client->getPaymentsApi();
            
            $amountMoney = new Models\Money();
            $amountMoney->setAmount(($fields['amount'] * 100) );
            $amountMoney->setCurrency($fields['currency']);

            $body = new Models\CreatePaymentRequest($fields['sourceId'], $idempotencyKey, $amountMoney);

            $body->setAutocomplete(true);
            if (isset($fields['customerReference'])) {
                $body->setCustomerId($fields['customerReference']);
            }
            if (isset($fields['token'])) {
                $body->setVerificationToken($fields['token']);
            }

            $body->setLocationId($this->getLocationId());
            $body->setReferenceId($fields['referenceId']);
            $body->setNote($order->customer_name);

            if($createdOrderID){
                $body->setOrderId($createdOrderID);
            }

            if (isset($fields['tip'])) {
                $tipMoney = new Models\Money();
                $tipMoney->setAmount($fields['tip'] * 100);
                $tipMoney->setCurrency($fields['currency']);
                $body->setTipMoney($tipMoney);
            }
Log::info(print_r($body, true));
            $response = $paymentsApi->createPayment($body);

            $this->handlePaymentResponse($response, $order, $host, $fields);
        }
        catch (Exception $ex) {
            $order->logPaymentAttempt('Payment error -> '.$ex->getMessage(), 0, $fields, []);
            throw new ApplicationException('Sorry, there was an error processing your payment. Please try again later');
        }
    }

}