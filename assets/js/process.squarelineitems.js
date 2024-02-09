+function ($) {
    "use strict"

    var ProcessSquareLineItems = function (element, options) {
        this.$el = $(element)
        this.options = options || {}
        this.$checkoutForm = this.$el.closest('#checkout-form')
        this.sqElementID = 'sq-card'
        this.card = null
        this.payments = null
        this.initialized = null
        console.log('here');
        $('[name=payment][value=squarelineitems]', this.$checkoutForm).on('change', $.proxy(this.init, this))
    }

    ProcessSquareLineItems.prototype.init = function () {
        console.log('here');
        if(!this.initialized){
            console.log('here');
            if (!$('#'+this.sqElementID).length)
                return
                console.log('here');
            if (this.options.applicationId === undefined)
                throw new Error('Missing square application id')
                console.log('here');
            this.payments = window.Square.payments(this.options.applicationId, this.options.locationId);
            console.log('here');
            this.initializeCard(this.payments).catch(e => {
                throw new Error('Initializing Card failed', e)
            });
            console.log('here');
            this.$checkoutForm.on('submitCheckoutForm', $.proxy(this.submitFormHandler, this))
            console.log('here');
            this.initialized = true
            console.log('here');
        }
    }

    ProcessSquareLineItems.prototype.initializeCard = async function(payments) {

        // Customize the CSS for WebPayments SDK elements
        this.card = await payments.card({
            style: this.options.cardFormStyle
        });
        await this.card.attach('#' + this.sqElementID);
    }

    ProcessSquareLineItems.prototype.submitFormHandler = async function (event) {
        var $form = this.$checkoutForm,
            $paymentInput = $form.find('input[name="payment"]:checked')

        if ($paymentInput.val() !== 'squarelineitems') return

        // Prevent the form from submitting with the default action
        event.preventDefault()

        var tokenResult = await this.card.tokenize();
        this.onResponseReceived(tokenResult);
    }

    ProcessSquareLineItems.prototype.onResponseReceived = async function (tokenResult) {
        var self = this,
            $form = this.$checkoutForm,
            verificationDetails = {
                intent: 'CHARGE',
                amount: this.options.orderTotal.toString(),
                currencyCode: this.options.currencyCode,
                billingContact: {
                    givenName: $('input[name="first_name"]', this.$checkoutForm).val(),
                    familyName: $('input[name="last_name"]', this.$checkoutForm).val(),
                }
            }

        if (tokenResult.errors) {
            var $el = '<b>Encountered errors:</b>';
            tokenResult.errors.forEach(function (error) {
                $el += '<div>' + error.message + '</div>'
            });
            $form.find(this.options.errorSelector).html($el);
            return;
        }

        var verificationToken = await this.verifyBuyerHelper(tokenResult, verificationDetails);

        $form.find('input[name="square_card_nonce"]').val(tokenResult.token);
        $form.find('input[name="square_card_token"]').val(verificationToken);

        // Switch back to default to submit form
        $form.unbind('submitCheckoutForm').submit()

    }

    ProcessSquareLineItems.prototype.verifyBuyerHelper = async function(paymentToken, verificationDetails) {

        var verificationResults = await this.payments.verifyBuyer(
          paymentToken.token,
          verificationDetails
        );
        return verificationResults.token;
    }

    ProcessSquareLineItems.DEFAULTS = {
        applicationId: undefined,
        locationId: undefined,
        orderTotal: undefined,
        currencyCode: undefined,
        errorSelector: '#square-card-errors',
        cardFormStyle: {
            input: {
                backgroundColor: '#FFF',
                color: '#000000',
                fontSize: '16px'
            },
            'input::placeholder': {
                color: '#A5A5A5',
            },
            '.message-icon': {
                color: '#A5A5A5',
            }
        }
    }

    // PLUGIN DEFINITION
    // ============================

    var old = $.fn.processSquareLineItems

    $.fn.processSquareLineItems = function (option) {
        var $this = $(this).first()
        var options = $.extend(true, {}, ProcessSquareLineItems.DEFAULTS, $this.data(), typeof option == 'object' && option)

        return new ProcessSquareLineItems($this, options)
    }

    $.fn.processSquareLineItems.Constructor = ProcessSquareLineItems

    $.fn.processSquareLineItems.noConflict = function () {
        $.fn.processSquareLineItems = old
        return this
    }

    $(document).render(function () {
        $('#squareLineItemsPaymentForm').processSquareLineItems()
    })
}(window.jQuery)
