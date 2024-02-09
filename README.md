## Square Line Items Extension for TastyIgniter

This extension creates a new payment gateway as an extension of the existing `igniter.payregister`s Square gateway which, on top of creating a payment, also creates a square 'Order' via the Orders API with individual line items, as well as per-order tax values. 

## Why do I want this?

Most users will not need this! The existing Square payment gateway will suffice for almost everybody - this extension adds a few features on top of the Square payment gateway, at the cost of a lot of extra complexity. 

- As it is, order line items don't make it into Square by name. With this extension, communication from Square to the customer such as receipts and payment emails will contain not just the total amount charged, but also individual line items by name. 
- If any menu items have different taxable amounts based on your jurisdiction, you'll need this extension in order to figure out what part of your Square income to pay as Tax. This is only applicable if you're using the taxclasses extension. 

## Installation

Clone this repo as `/extensions/cupnoodles/squarelineitems`.

## Usage notes

Please note that this creates a _new_ payment gateway with the code 'squarelineitems'. Any custom references to the 'square' gateway in your theme will need to be updated. Also, Square's JS library can only be loaded once per page, so do not allow both 'square' and 'squarelineitems' to be active on any location at the same time.

