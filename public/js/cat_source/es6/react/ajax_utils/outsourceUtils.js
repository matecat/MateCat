if (!API) {
    var API = {}
}


API.OUTSOURCE = {

    getOutsourceQuote: function(idProject, password, jid, jpassword, fixedDelivery, typeOfService, timezone, currency ) {

        var data = {
            action: 'outsourceTo',
            pid: idProject,
            currency: currency,
            ppassword: password,
            fixedDelivery: fixedDelivery,
            typeOfService: typeOfService,
            timezone: timezone,
            jobs: [
                {
                    jid: jid,
                    jpassword: jpassword
                }
            ]
        };

        return $.ajax({
            data: data,
            type: "POST",
            url : "/?action=outsourceTo"
        });
    },

    fetchChangeRates( ) {
        return $.ajax({
            type: "POST",
            url : "/?action=fetchChangeRates"
        });

    }

};