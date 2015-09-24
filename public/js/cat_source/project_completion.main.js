ProjectCompletion = {
    enabled : function() {
        return config.projectCompletionFeature ;
    }
}

if ( ProjectCompletion.enabled() ) {
(function($,config,ProjectCompletion,undefined) {

    console.log('projectCompletionFeature enabled');

    $(document).on('click', '#markAsCompleteButton', function(el) {
        console.log( 'mark as complete click');

        var data = {
            action   : 'Features_ProjectCompletion_SetChunkCompleted',
            id_job   : config.id_job,
            password : config.password
        }

        var success = function( resp ) {
            console.log( 'response success', resp );
            button.removeClass('notMarkedComplete')
                .addClass('isMarkedComplete');
        }

        var error = function( error ) {
            console.log(error);
        }

        var request = APP.doRequest( {
            data : data ,
            success : success,
            error : error
        });
    }

    var isCompletionClickable = function() {
        return  (
            button.hasClass('translated') &&
            button.hasClass('notMarkedComplete')
        );
    }

    $(document).on('click', '#markAsCompleteButton', function(el) {
        if ( isCompletionClickable() ) {
            submitProjectCompletion();
        }
    });

    $(document).on('setTranslation:success', function(ev, data) {
        console.log( 'setTranslation',  data.stats );
        lastStats = data.stats ;
    });


})(jQuery, window.config, window.ProjectCompletion);
}
