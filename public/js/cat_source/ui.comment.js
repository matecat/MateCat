/*
 Component: ui.comment
 */
if ( config.commentEnabled && !!window.EventSource ) {

    SSE.init();
    var source = SSE.getSource('comments');

    source.addEventListener('message', function(e) {
        console.log('message', JSON.parse(e.data));
    }, false);

    source.addEventListener('open', function(e) {
        // Connection was opened.
        console.log('connection open');
    }, false);

    source.addEventListener('error', function(e) {
        if (e.readyState == EventSource.CLOSED) {
            // Connection was closed.
            console.log('connection closed');
        }
    }, false);

    // temp global
    window.mbc_postComment = function() {
        var data = {
            action : 'comment',
            id_client: 123,
            id_job: config.job_id,
            id_segment: 12345,
            username: 'John Doe',
            password: config.password,
            role: getRole(),
            message: 'this is my message'
        }

        APP.doRequest({
            data: data,
            success: function() {
                console.log('success');
            },
            failure: function() {

            }
        });
    }

}
