Speech2Text = {};

(function( $, Speech2Text, undefined ) {
    $.extend( Speech2Text, {
        recognition: null,
        recognizing: false,
        finalTranscript: '',
        ignoreOnEnd: false,
        targetElement: null,
        isToEmptyTargetElement: true,
        segment: null,
        loadRecognition: function() {
            if ( 'webkitSpeechRecognition' in window ) {
                Speech2Text.recognition = new webkitSpeechRecognition();
                Speech2Text.recognition.continuous = true;
                Speech2Text.recognition.interimResults = true;
                Speech2Text.recognition.onstart = Speech2Text.onRecognitionStart;
                Speech2Text.recognition.onerror = Speech2Text.onRecognitionError;
                Speech2Text.recognition.onend = Speech2Text.onRecognitionEnd;
                Speech2Text.recognition.onresult = Speech2Text.onRecognitionResult;
                Speech2Text.recognition.lang = config.target_lang;
            }
        },
        enableMicrophone: function( segment ) {
            var microphone = segment.find( '.micSpeech' );

            if( Speech2Text.recognition ) {
                Speech2Text.targetElement = microphone.parent().find( '.editarea' );

                var segmentId = '#segment-' + Speech2Text.targetElement.data('sid');

                Speech2Text.segment = $( segmentId );

                if( Speech2Text.segment.hasClass('status-translated')
                        || Speech2Text.segment.hasClass('status-approved') ) {
                    Speech2Text.isToEmptyTargetElement = false;
                } else {
                    Speech2Text.isToEmptyTargetElement = true;
                }

                microphone.click( Speech2Text.clickMicrophone );
            } else {
                microphone.hide();

                //TODO: Display a user-friendly error message
                console.error('Web Speech API is not supported by this browser. Upgrade to Chrome version 25 or later.');
            }
        },
        disableMicrophone: function( segment ) {
            var microphone = segment.find( '.micSpeech' );
            Speech2Text.stopSpeechRecognition( microphone );
            microphone.click( false );
            Speech2Text.restartVars();
        },
        clickMicrophone: function( event ) {
            var microphone = $( this );

            if( microphone.hasClass( 'micSpeechActive' ) ) {
                Speech2Text.stopSpeechRecognition( microphone );
            } else {
                Speech2Text.startSpeechRecognition( microphone );
            }
        },
        restartVars: function() {
            Speech2Text.recognizing = false;
            Speech2Text.finalTranscript = '';
            Speech2Text.ignoreOnEnd = false;
            Speech2Text.targetElement = null;
            Speech2Text.isToEmptyTargetElement = true;
        },
        startSpeechRecognition: function( microphone ) {
            microphone.addClass( 'micSpeechActive' );

            if( Speech2Text.isToEmptyTargetElement ) {
                Speech2Text.finalTranscript = '';
            } else {
                Speech2Text.finalTranscript = Speech2Text.targetElement.text() + ' ';
            }
            Speech2Text.recognition.start();
        },
        stopSpeechRecognition: function( microphone ) {
            microphone.removeClass( 'micSpeechActive' );

            Speech2Text.recognition.stop();
        },
        onRecognitionStart: function() {
            Speech2Text.recognizing = true;
        },
        onRecognitionError: function( event ) {
            //TODO: Display a user-friendly error message
            console.error( 'Error found: ' + event.error );
        },
        onRecognitionEnd: function() {
            Speech2Text.recognizing = false;
        },
        onRecognitionResult: function( event ) {
            var interim_transcript = '';

            for (var i = event.resultIndex; i < event.results.length; ++i) {
                if (event.results[i].isFinal) {
                    Speech2Text.finalTranscript += event.results[i][0].transcript;
                } else {
                    interim_transcript += event.results[i][0].transcript;
                }
            }

            Speech2Text.finalTranscript = Speech2Text.capitalize(Speech2Text.finalTranscript);
            Speech2Text.targetElement.html(
                    Speech2Text.linebreak( Speech2Text.finalTranscript )
                    + Speech2Text.linebreak( interim_transcript )
            );
        },
        capitalize: function ( s ) {
            var first_char = /\S/;

            return s.replace(first_char, function(m) { return m.toUpperCase(); });
        },
        linebreak: function ( s ) {
            var two_line = /\n\n/g;
            var one_line = /\n/g;

            return s.replace(two_line, '<p></p>').replace(one_line, '<br>');
        }
    });

    $( document ).ready( function() {
        Speech2Text.loadRecognition();
    });
})( jQuery, Speech2Text );