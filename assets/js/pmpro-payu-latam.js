(function($){

    const form = $('form#pmpro_form');
    const containerCard = '#card_payu_latam';
    const cardNumber = 'input#card_number';
    const cardExpire = 'input#card_expiry';
    const cardType = 'input#card_type';

    new Card({
        // a selector or DOM element for the form where users will
        // be entering their information
        form: document.querySelector(containerCard), // *required*
        // a selector or DOM element for the container
        // where you want the card to appear
        container: '.card-wrapper', // *required*

        formSelectors: {
            numberInput: cardNumber, // optional — default input[name="number"]
            expiryInput: cardExpire, // optional — default input[name="expiry"]
            cvcInput: 'input#card_cvc', // optional — default input[name="cvc"]
            nameInput: 'input#card_name' // optional - defaults input[name="name"]
        },

        width: 200, // optional — default 350px
        formatting: true, // optional - default true

        // Strings for translation - optional
        messages: {
            validDate: 'expire\ndate',
            monthYear: 'mm/yy', // optional - default 'month/year'
        },

        // Default placeholders for rendered fields - optional
        placeholders: {
            number: '•••• •••• •••• ••••',
            name: 'Full Name',
            expiry: '••/••',
            cvc: '•••'
        },

        masks: {
            cardNumber: '•' // optional - mask card number
        },

        // if true, will log helpful messages for setting up Card
        debug: true // optional - default false
    });

    const cardsAllowed =  ['mastercard', 'visa', 'amex', 'diners'];


    $(cardNumber).on('change, keyup', function() {
        const self = this;

        if($(self).hasClass('jp-card-valid')){
            const cardClasses = $(self).attr("class").split(' ');
            let card = cardClasses.find(card => cardsAllowed.includes(card));
            card = card.toUpperCase();
            $(cardType).val(card);
        }
    });

    $(cardExpire).on('change, keyup', function() {
        const self = this;
        if(self.value.length >= 7 && $(self).hasClass('jp-card-invalid')){
            $(self).val('');
        }
    });


    function valid_credit_card(value) {
        // accept only digits, dashes or spaces
        if (/[^0-9-\s]+/.test(value)) return false;

        // The Luhn Algorithm. It's so pretty.
         let nCheck = 0, nDigit = 0, bEven = false;
        value = value.replace(/\D/g, "");

        for (let n = value.length - 1; n >= 0; n--) {
            const cDigit = value.charAt(n);
            nDigit = parseInt(cDigit, 10);

            if (bEven) {
                if ((nDigit *= 2) > 9) nDigit -= 9;
            }

            nCheck += nDigit;
            bEven = !bEven;
        }

        return (nCheck % 10) === 0;
    }

    function validateDate(yearEnd, month){

        let date = new Date();
        let currentMonth = ("0" + (date.getMonth() + 1)).slice(-2);
        let year = date.getFullYear();

        return (parseInt(yearEnd) > year) || (parseInt(yearEnd) === year && month >= currentMonth);
    }
})(jQuery);