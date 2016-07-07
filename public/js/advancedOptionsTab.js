/*
 Component: Advanced Options Tab
 */

if ( true )
(function($, UI, undefined) {
    function showModalNotSupportedLanguages(notAcceptedLanguages, acceptedLanguages) {
        APP.alert({
            title: 'Option not available',
            okTxt: 'Continue',
            msg: "Not available for " + notAcceptedLanguages.join(", ") +
            ".</br> Only available for " + acceptedLanguages.join(", ") +"."
        });
    }
    function createSupportedLanguagesArrays(acceptedLanguages) {
        var notAcceptedLanguages = [];
        var notAcceptedLanguagesCodes = [], acceptedLanguagesCodes = [];
        var source_lan = config.source_rfc;
        var target_lan = config.target_rfc;
        if (acceptedLanguages.indexOf(source_lan) === -1 ) {
            notAcceptedLanguages.push((_.find(config.languages_array, function (e) {
                if (e.code === source_lan) {
                    notAcceptedLanguagesCodes.push(e.code.split("-")[0].toUpperCase());
                    return true;
                }
                return false;
            })).name);
        }

        if (acceptedLanguages.indexOf(target_lan) === -1) {
            notAcceptedLanguages.push((_.find(config.languages_array, function (e) {
                if (e.code === target_lan) {
                    notAcceptedLanguagesCodes.push(e.code.split("-")[0].toUpperCase());
                    return true;
                }
                return false;
            })).name);
        }
        acceptedLanguages.forEach(function (value, index, array) {
            var result = _.find(config.languages_array, function (e) {
                return e.code === value;
            });
            array[index] = result.name;
            if (acceptedLanguagesCodes.indexOf(result.code.split("-")[0].toUpperCase()) === -1) {
                acceptedLanguagesCodes.push(result.code.split("-")[0].toUpperCase() );
            }
        });
        return {
            accepted: acceptedLanguages,
            acceptedCodes: acceptedLanguagesCodes,
            notAccepted: notAcceptedLanguages,
            notAcceptedCodes: notAcceptedLanguagesCodes
        }
    }
    $.extend(UI, {
        
        initAdvanceOptions: function() {
            var lexiqaCheck = $('.qa-box #lexi_qa');
            var speech2textCheck = $('.s2t-box #s2t_check');
            var tagProjectionCheck = $('.tagp #tagp_check');



            $('.mgmt-table-options .options-box.dqf_options_box').hide();
            $('.mgmt-table-options .options-box.seg_rule select#segm_rule').val(config.segmentation_rule).attr("disabled", true);
            $('.mgmt-table-options .options-box.seg_rule').on("click", function () {
                APP.alert({
                    title: 'Option not editable',
                    okTxt: 'Continue',
                    msg: "Segment rules settings can only be edited <br/> when creating the project.    "
                });
            });
            //Check Lexiqa check
            if (LXQ.checkCanActivate()) {
                (LXQ.enabled()) ? lexiqaCheck.attr('checked', true) : lexiqaCheck.attr('checked', false);
                lexiqaCheck.on('change', this.toggleLexiqaOption.bind(this));
            } else {

                var LXQContainer = $('.options-box.qa-box');
                $('.options-box #lexi_qa').prop( "disabled", true ).attr('checked', false);

                var acceptedLanguagesLXQ = config.lexiqa_languages.slice();
                var languagesArraysLXQ = createSupportedLanguagesArrays(acceptedLanguagesLXQ);
                LXQContainer.find('.option-supported-languages').html(languagesArraysLXQ.acceptedCodes.join(', '));
                LXQContainer.find('.option-notsupported-languages').html(languagesArraysLXQ.notAcceptedCodes.join(', '));
                LXQContainer.find('.onoffswitch').off('click').on('click', function () {
                    showModalNotSupportedLanguages(languagesArraysLXQ.notAccepted, languagesArraysLXQ.accepted);
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

                var acceptedLanguagesTP = config.tag_projection_languages.slice();
                var languagesArraysTP = createSupportedLanguagesArrays(acceptedLanguagesTP);
                tpContainer.find('.option-supported-languages').html(languagesArraysTP.acceptedCodes.join(', '));
                tpContainer.find('.option-notsupported-languages').html(languagesArraysTP.notAcceptedCodes.join(', '));
                tpContainer.find('.onoffswitch').off('click').on('click', function () {
                    showModalNotSupportedLanguages(languagesArraysTP.notAccepted, languagesArraysTP.accepted);
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
                    APP.alert({
                        title: 'Option not available',
                        okTxt: 'Continue',
                        msg: "This options is only available on Chrome browser."
                    });
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
