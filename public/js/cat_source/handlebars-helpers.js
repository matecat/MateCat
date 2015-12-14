Handlebars.registerHelper('ifCond', function(v1, v2, options) {
    if(v1 == v2) {
        return options.fn(this);
    }
    return options.inverse(this);
});

Handlebars.registerHelper('formatDate', function(date, format, options) {
    return moment( date ).format( format );
});
