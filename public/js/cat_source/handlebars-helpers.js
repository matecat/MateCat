Handlebars.registerHelper('ifCond', function(v1, v2, options) {
    if(v1 == v2) {
        return options.fn(this);
    }
    return options.inverse(this);
});

Handlebars.registerHelper('formatDate', function(date, format, options) {
    return moment( date ).format( format );
});


Handlebars.registerHelper('downcase', function(string, options) {
    return string.toLowerCase();
});

Handlebars.registerHelper('statusLabel', function(string, options) {
    return config.status_labels[ string.toUpperCase() ];
});
Handlebars.registerHelper('statusLabelLC', function(string, options) {
    return config.status_labels[ string.toUpperCase() ].toLowerCase() ;
});
