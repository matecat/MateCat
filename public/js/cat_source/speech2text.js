Speech2Text = {
    enabled: function () {
        return !!('webkitSpeechRecognition' in window && config.speech2textEnabled);
    },
    disable: function () {
        if (config.speech2textEnabled) {
            config.speech2textEnabled = false;
            UI.render();
        }
    },
    enable: function () {
        if (!config.speech2textEnabled) {
            config.speech2textEnabled = true;
            UI.render();
        }
    }
};

if ( Speech2Text.enabled() ) {
    (function ($, Speech2Text, undefined) {
        $.extend(Speech2Text, {
            recognition: null,
            recognizing: false,
            microphone: null,
            finalTranscript: '',
            interimTranscript: '',
            targetElement: null,
            isToEmptyTargetElement: true,
            isStopingRecognition: false,
            isToKeepRecognizing: false,
            wereMatchesPreviouslyOpened: false,
            loadRecognition: function () {
                Speech2Text.recognition = new webkitSpeechRecognition();
                Speech2Text.recognition.continuous = true;
                Speech2Text.recognition.interimResults = true;
                Speech2Text.recognition.onstart = Speech2Text.onRecognitionStart;
                Speech2Text.recognition.onerror = Speech2Text.onRecognitionError;
                Speech2Text.recognition.onend = Speech2Text.onRecognitionEnd;
                Speech2Text.recognition.onresult = Speech2Text.onRecognitionResult;
                Speech2Text.recognition.lang = config.target_lang;
            },
            enableMicrophone: function (segment) {
                Speech2Text.microphone = segment.find('.micSpeech');

                if (Speech2Text.recognition) {
                    Speech2Text.targetElement = Speech2Text.microphone.parent().find('.editarea');

                    var segmentId = segment.data('split-original-id');
                    var segmentRecord = MateCat.db.segments.by('sid', segmentId);

                    Speech2Text.isToEmptyTargetElement = Speech2Text
                        .shouldEmptyTargetElement(segmentRecord);

                    Speech2Text.microphone.click(Speech2Text.clickMicrophone);

                    if (Speech2Text.recognizing) {
                        Speech2Text.startSpeechRecognition(Speech2Text.microphone);
                    }
                } else {
                    Speech2Text.microphone.hide();

                    //TODO: Display a user-friendly error message
                    console.error('Web Speech API is not supported by this browser. Upgrade to Chrome version 25 or later.');
                }
            },
            disableMicrophone: function (segment) {
                var microphone = segment.find('.micSpeech');
                microphone.unbind('click');
                Speech2Text.stopSpeechRecognition(microphone);
            },
            clickMicrophone: function (event) {
                var microphone = $(this);

                Speech2Text.isStopingRecognition = false;

                if (microphone.hasClass('micSpeechActive')) {
                    Speech2Text.disableContinuousRecognizing();
                    Speech2Text.stopSpeechRecognition(microphone);
                } else {
                    Speech2Text.startSpeechRecognition(microphone);
                    Speech2Text.enableContinuousRecognizing();
                }
            },
            startSpeechRecognition: function (microphone) {
                if (!microphone.hasClass('micSpeechActive')) {
                    microphone.addClass('micSpeechActive');
                    Speech2Text.animateSpeechActive();
                }

                if (Speech2Text.isToEmptyTargetElement) {
                    Speech2Text.finalTranscript = '';
                    Speech2Text.targetElement.html('');
                } else {
                    Speech2Text.finalTranscript = Speech2Text.targetElement.text() + ' ';
                }

                Speech2Text.interimTranscript = '';

                if (!Speech2Text.recognizing) {
                    Speech2Text.recognition.start();
                    Speech2Text.showMatches();
                    Speech2Text.targetElement.on('blur keyup paste input', function (event) {
                        Speech2Text.finalTranscript = $(this).text().trim() + ' ';
                    });
                }
            },
            stopSpeechRecognition: function (microphone) {
                microphone.removeClass('micSpeechActive');

                Speech2Text.recognition.stop();
                Speech2Text.targetElement.off('blur keyup paste input');

                if (Speech2Text.recognizing) {
                    Speech2Text.isStopingRecognition = true;
                    Speech2Text.hideMatches();
                }
            },
            onRecognitionStart: function () {
                Speech2Text.recognizing = true;
            },
            onRecognitionError: function (event) {
                if (event.error === 'no-speech') {
                    Speech2Text.disableContinuousRecognizing();
                    Speech2Text.stopSpeechRecognition(Speech2Text.microphone);
                } else {
                    //TODO: Display a user-friendly error message
                    console.error('Error found: ' + event.error);
                }
            },
            onRecognitionEnd: function () {
                Speech2Text.recognizing = false;
                Speech2Text.isStopingRecognition = false;

                if (Speech2Text.isToKeepRecognizing) {
                    Speech2Text.startSpeechRecognition(Speech2Text.microphone);
                } else {
                    Speech2Text.microphone.removeClass('micSpeechActive');
                }
            },
            onRecognitionResult: function (event) {
                Speech2Text.interimTranscript = '';

                for (var i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        Speech2Text.finalTranscript += event.results[i][0].transcript;
                        Speech2Text.animateSpeechActive();
                    } else {
                        Speech2Text.interimTranscript += event.results[i][0].transcript;
                        Speech2Text.animateSpeechReceiving();
                    }
                }

                Speech2Text.finalTranscript = Speech2Text.capitalize(Speech2Text.finalTranscript);

                if (!Speech2Text.isStopingRecognition) {
                    Speech2Text.targetElement.html(
                        Speech2Text.linebreak(Speech2Text.finalTranscript)
                        + Speech2Text.linebreak(Speech2Text.interimTranscript)
                    );
                }
            },
            capitalize: function (s) {
                var first_char = /\S/;

                return s.replace(first_char, function (m) {
                    return m.toUpperCase();
                });
            },
            linebreak: function (s) {
                var two_line = /\n\n/g;
                var one_line = /\n/g;

                return s.replace(two_line, '<p></p>').replace(one_line, '<br>');
            },
            putSegmentsInStore: function (data) {
                $.each(data.files, function () {
                    $.each(this.segments, function () {
                        MateCat.db.upsert('segments', 'sid', _.clone(this));
                    });
                });
            },
            shouldEmptyTargetElement: function (segment) {
                if ((segment.autopropagated_from && segment.autopropagated_from != "0")
                    || segment.suggestion_match === "100"
                    || segment.status === "TRANSLATED"
                    || segment.status === "REJECTED"
                    || segment.status === "APPROVED"
                    || segment.status === "FIXED"
                    || segment.status === "REBUTTED") {
                    return false;
                }

                return true;
            },
            enableContinuousRecognizing: function () {
                Speech2Text.isToKeepRecognizing = true;
            },
            disableContinuousRecognizing: function () {
                Speech2Text.isToKeepRecognizing = false;
            },
            showMatches: function () {
                if ($('body').hasClass('hideMatches')) {
                    $('body').removeClass('hideMatches');
                    Speech2Text.wereMatchesPreviouslyOpened = false;
                } else {
                    Speech2Text.wereMatchesPreviouslyOpened = true;
                }
            },
            hideMatches: function () {
                if (!Speech2Text.wereMatchesPreviouslyOpened) {
                    if (!$('body').hasClass('hideMatches')) {
                        $('body').addClass('hideMatches');
                    }
                }
            },
            animateSpeechActive: function () {
                Speech2Text.microphone.removeClass('micSpeechReceiving');
            },
            animateSpeechReceiving: function () {
                Speech2Text.microphone.addClass('micSpeechReceiving');
            }
        });

        $(document).ready(function () {
            Speech2Text.loadRecognition();
        });
    })(jQuery, Speech2Text);
}