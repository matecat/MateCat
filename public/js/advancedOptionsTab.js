/*
 Component: Advanced Options Tab
 */

if ( true )
(function($, UI, undefined) {
    
    $.extend(UI, {
        initAdvanceOptions: function() {
            var lexiqaCheck = $('.qa-box #lexi_qa');
            var speech2textCheck = $('.s2t-box #s2t_check');
            var tagProjectionCheck = $('.tagp #tagp_check');
            
            $('.mgmt-table-options .options-box.seg_rule').hide();
            $('.mgmt-table-options .options-box.dqf_options_box').hide();


            //Check Lexiqa check
            if (LXQ.checkCanActivate()) {
                (LXQ.enabled()) ? lexiqaCheck.attr('checked', true) : lexiqaCheck.attr('checked', false);
                lexiqaCheck.on('change', this.toggleLexiqaOption.bind(this));
            } else {
                var LXQContainer = $('.options-box.qa-box');
                $('.options-box #lexi_qa').prop( "disabled", true ).attr('checked', false);
                // LXQContainer.css({opacity: 0.6 });
                LXQContainer.find('.onoffswitch').on('click', function () {
                    LXQContainer.find('.option-qa-box-languages').addClass('pulse');
                    setTimeout(function () {
                        LXQContainer.find('.option-qa-box-languages').removeClass('pulse');
                    }, 1200)
                });
                LXQContainer.addClass('option-unavailable');
            }
            //Check Tag Projection
            if (UI.checkTpCanActivate()) {
                (UI.checkTPEnabled()) ? tagProjectionCheck.attr('checked', true) : tagProjectionCheck.attr('checked', false);
                tagProjectionCheck.on('change', this.toggleTagProjectionOption.bind(this));
            } else {
                var tpContainer= $('.options-box.tagp');
                $('.options-box #tagp_check').prop( "disabled", true ).attr('checked', false);
                // tpContainer.css({opacity: 0.6 });
                tpContainer.find('.onoffswitch').on('click', function () {
                    tpContainer.find('.option-tagp-languages').addClass('pulse');
                    setTimeout(function () {
                        tpContainer.find('.option-tagp-languages').removeClass('pulse');
                    }, 1200)
                });
                tpContainer.addClass('option-unavailable');

            }
            //Check Speech To Text
            if (!('webkitSpeechRecognition' in window)) {
                var speech2textContainer = $('.s2t-box');
                speech2textCheck.prop( "disabled", true ).attr('checked', false);
                // speech2textContainer.css({opacity: 0.6 });
                speech2textContainer.find('.option-s2t-box-chrome-label').css('display', 'inline');
                speech2textContainer.find('.onoffswitch').on('click', function () {
                    speech2textContainer.find('.option-s2t-box-chrome-label').addClass('pulse');
                    setTimeout(function () {
                        speech2textContainer.find('.option-s2t-box-chrome-label').removeClass('pulse');
                    }, 1200)
                });
                speech2textCheck.addClass('option-unavailable');
            } else {
                //Check Speech to Text
                speech2textCheck.on('change', this.toggleSpeech2TextOption.bind(this));
                (Speech2Text.enabled()) ? speech2textCheck.attr('checked', true) : speech2textCheck.attr('checked', false);
            }
        },

        toggleLexiqaOption: function () {
            var selected = $('.qa-box #lexi_qa').is(':checked');
            (selected) ? LXQ.enable() : LXQ.disable();
        },

        toggleSpeech2TextOption: function () {
            var selected = $('.s2t-box #s2t_check').is(':checked');
            (selected) ? Speech2Text.enable() : Speech2Text.disable();
        },

        toggleTagProjectionOption: function () {
            var selected = $('.tagp #tagp_check').is(':checked');
            (selected) ? UI.enableTagProjectionInJob() : UI.disableTagProjectionInJob();
        }

    });
    
})(jQuery, UI ); 
