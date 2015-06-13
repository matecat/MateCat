SSE = {
    init : function() {
        // TODO configure this
        this.baseURL = 'http://127.0.0.1:7788';
    },
    getSource : function(what) {
        var source = '';
        switch(what) {
            case 'comments':
                source = '/channel/comments' + '?jid=' + config.job_id + '&pw=' + config.password ;
                break;

            default:
                throw new Exception('source mapping not found');
        }

        return new EventSource(SSE.baseURL + source );
    }
}
