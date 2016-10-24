/*
 Component: tm
 Created by andreamartines on 02/10/14.
 Loaded by cattool and upload page.
 */
(function($, undefined) {

    function isVisible($el) {
        var winTop = $(window).scrollTop();
        var winBottom = winTop + $(window).height();
        var elTop = $el.offset().top;
        var elBottom = elTop + $el.height();
        return ((elBottom<= winBottom) && (elTop >= winTop));
    }

    $.extend(UI, {

        initTM: function() {

// script per lo slide del pannello di manage tmx
            UI.setDropDown();
            UI.initOptionsTip();
            UI.initTmxTooltips();
            $(".popup-tm .x-popup, .popup-tm h1 .continue").click(function(e) {
                e.preventDefault();
                UI.closeTMPanel();
            });

            $(".outer-tm").click(function() {
                UI.saveTMdata(true);
            });

            $(".popup-tm li.mgmt-tm").click(function(e) {
                e.preventDefault();
                $(this).addClass("active");
                $(".mgmt-mt,.mgmt-opt").removeClass("active");
                $(".mgmt-table-mt").hide();
                $(".mgmt-table-tm").show();
                $(".mgmt-table-options").hide();
            });
            $(".popup-tm .tm-mgmt").click(function(e) {
                e.preventDefault();
                $(".mgmt-mt").addClass("active");
                $(".mgmt-tm,.mgmt-opt").removeClass("active");
                $(".mgmt-table-tm,.mgmt-table-options").hide();
                $(".mgmt-table-mt").show();
            });
            $(".popup-tm .mgmt-opt").click(function(e) {
                e.preventDefault();
                $(".mgmt-opt").addClass("active");
                $(".mgmt-tm,.mgmt-mt").removeClass("active");
                $(".mgmt-table-tm,.mgmt-table-mt").hide();
                $(".mgmt-table-options").show();
            });



            $(".mgmt-mt").click(function(e) {
                e.preventDefault();
                $(this).addClass("active");
                $(".mgmt-tm,.mgmt-opt").removeClass("active");
                $(".mgmt-table-tm").hide();
                $(".mgmt-table-mt").show();
                $(".mgmt-table-options").hide();
            });
            $("#mt_engine").change(function() {
                if($(this).val() == 0) {
                    $('table.mgmt-mt tr.activemt').removeClass('activemt');
                } else {
                    checkbox = $('table.mgmt-mt tr[data-id=' + $(this).val() + '] .enable-mt input');
                    UI.activateMT(checkbox);
                }
            });
            $("#mt_engine_int").change(function() {
                $('#add-mt-provider-cancel').hide();
                $('#mt-provider-details .error').empty();

                $(".insert-tm").show();
                var provider = $(this).val();
                if(provider == 'none') {
                    $('.step2 .fields').html('');
                    $(".step2").hide();
                    $(".step3").hide();
                    $('#add-mt-provider-cancel').show();
                } else {
                    $('.step2 .fields').html($('#mt-provider-' + provider + '-fields').html());
                    $('.step3 .text-left').html($('#mt-provider-' + provider + '-msg').html());
                    $(".step2").show();
                    $(".step3").show();
                    $("#add-mt-provider-confirm").removeClass('hide');
                }
            });
            $(".add-mt-engine").click(function() {
                if ($(this).hasClass("disabled")) {
                    return false;
                }
                $(this).hide();
                UI.resetMTProviderPanel();
                $(".mgmt-table-mt tr.new").removeClass('hide').show();
                $('#add-mt-provider-cancel').show();
                $("#add-mt-provider-confirm").addClass('hide');
                $(".insert-tm").removeClass('hide');
            });

            $('#add-mt-provider-confirm').click(function(e) {
                e.preventDefault();
                if($(this).hasClass('disabled')) return false;
                var provider = $("#mt_engine_int").val();
                var providerName = $("#mt_engine_int option:selected").text();
                UI.addMTEngine(provider, providerName);
            });
            $('#add-mt-provider-cancel').click(function(e) {
                console.log('clicked add-mt-provider-cancel');
                $(".add-mt-engine").show();
                $(".insert-tm").addClass('hide');
            });
            $('#add-mt-provider-cancel-int').click(function(e) {
                $(".add-mt-engine").show();
                $(".insert-tm").addClass('hide');
                $('#mt_engine_int').val('none').trigger('change');
                $(".insert-tm").addClass('hide').removeAttr('style');
                $('#add-mt-provider-cancel').show();
            });
            $('html').on('input', '#mt-provider-details input', function() {
                num = 0;
                $('#mt-provider-details input.required').each(function () {
                    if($(this).val() == '') num++;
                })
                if(num) {
                    $('#add-mt-provider-confirm').addClass('disabled');
                } else {
                    $('#add-mt-provider-confirm').removeClass('disabled');
                }
            });

            // script per fare apparire e scomparire la riga con l'upload della tmx
            $('body').on('click', 'tr a.canceladdtmx, tr a.cancelsharetmx, tr.ownergroup a.canceladdtmx, tr a.canceladdglossary, tr.ownergroup a.canceladdglossary, #inactivetm tr.new .action .addtmxfile', function() {

                $(this).parents('tr').find('.action a').removeClass('disabled');
                $(this).parents('td.uploadfile, .share-tmx-container').slideToggle(function () {
                    $(this).remove();
                });
                UI.hideAllBoxOnTables();

            }).on('mousedown', '.addtmx:not(.disabled)', function(e) {
                e.preventDefault();
                UI.addFormUpload(this, 'tmx');

            }).on('mousedown', '.addGlossary:not(.disabled)', function(e) {
                e.preventDefault();
                UI.addFormUpload(this, 'glossary');
            }).on('change paste', '#new-tm-key', function(event) {
                // set Timeout to get the text value after paste event, otherwise it is empty
                setTimeout( function(){ UI.checkTMKey('change'); }, 200 );
            }).on('click', '.mgmt-tm tr.new a.uploadtm:not(.disabled)', function() {
                var keyValue = $('#new-tm-key').val();
                var descKey = $('#new-tm-description').val();

                if (config.isLoggedIn) {
                    UI.saveTMkey(keyValue, descKey).done(function () {
                        UI.checkTMKey('key');
                    });
                } else {
                    UI.checkTMKey('key');
                    delete UI.newTmKey;
                }


            }).on('click', 'tr .uploadfile .addtmxfile:not(.disabled)', function() {

                $(this).addClass('disabled');
                $(this).parents('.uploadfile').find('.error').text('').hide();

                UI.execAddTMOrGlossary(this, 'tmx');

            }).on('click', 'tr .uploadfile .addglossaryfile:not(.disabled)', function() {

                $(this).addClass('disabled');
                $(this).parents('.uploadfile').find('.error').text('').hide();

                UI.execAddTMOrGlossary(this, 'glossary');

            }).on('click', '.mgmt-tm tr.mine td.description .edit-desc', function() {

                // $('.mgmt-tm .edit-desc[contenteditable=true]').blur();
                $('#activetm tr.mine td.description .edit-desc:not(.current)').removeAttr('contenteditable');

                $(this).attr('contenteditable', true);
                $(this).focus();


            }).on('blur', '#activetm td.description .edit-desc, #inactivetm td.description .edit-desc', function() {
                $(this).removeAttr('contenteditable');
                UI.saveTMdescription($(this));

            }).on('keydown', '.mgmt-tm td.description .edit-desc', 'return', function(e) {

                if(e.which == 13) {
                    e.preventDefault();
                    $(this).trigger('blur');
                }

            }).on('click', '.popup-tm h1 .btn-ok', function(e) {
                e.preventDefault();
                UI.saveTMdata(true);
            }).on('click', '#activetm tr.new a.addtmxfile:not(.disabled)', function() {
                console.log('upload file');
                UI.checkTMKey('tm');

                $(this).addClass('disabled');
            }).on('click', 'a.disabletm', function() {
                UI.disableTM(this);
            }).on('change', '.mgmt-table-tm tr.mine .activate input', function() {

                UI.checkTMGrantsModifications(this);
                if(APP.isCattool) UI.saveTMdata(false);
                UI.checkTMKeysUpdateChecks();

            }).on('click', '.mgmt-table-mt tr .enable-mt input', function() {

                if($(this).is(':checked')) {
                    UI.activateMT(this);
                } else {
                    UI.deactivateMT(this);
                }

            }).on('click', '#activetm .lookup input, #activetm .update input', function() {
                var tr = $(this).parents('tr.mine');
                if(!tr.find('td.lookup input').is(':checked') && !tr.find('td.update input').is(':checked')) {
                    UI.checkTMGrantsModifications(this);
                    tr.find('.activate input').prop('checked', false);
                }
                UI.checkTMKeysUpdateChecks();


            }).on('click', '.mgmt-table-mt tr .action .deleteMT', function() {
                UI.showMTDeletingMessage($(this));
            }).on('click', 'a.usetm', function() {
                UI.useTM(this);
            }).on('change', '#new-tm-read, #new-tm-write', function() {
                UI.checkTMgrants();
            }).on('change', 'tr td.uploadfile input[type="file"], tr.ownergroup td.uploadfile input[type="file"]', function() {
                if(this.files[0].size > config.maxTMXFileSize) {
                    numMb = config.maxTMXFileSize/(1024*1024);
                    APP.alert('File is too big.<br/>The maximuxm size allowed is ' + numMb + 'MB.');
                    return false;
                }
                if($(this).val() == '') {
                    $(this).parents('.uploadfile').find('.addtmxfile, .addglossaryfile').hide();
                } else {
                    $(this).parents('.uploadfile').find('.addtmxfile, .addglossaryfile').removeClass("disabled").show();
                    $(this).parents('.uploadfile').find('.upload-file-msg-error').hide();
                }
            }).on('keyup', '#filterInactive', function() {
                if($(this).val() == '') {
                    $('#inactivetm').removeClass('filtering');
                    $('#inactivetm tbody tr.found').removeClass('found');
                } else {
                    $('#inactivetm').addClass('filtering');
                    UI.filterInactiveTM($('#filterInactive').val());
                }
            }).on('mousedown', '.mgmt-tm .downloadtmx:not(.disabled)', function(e){
                e.preventDefault();

                UI.openExportTmx(this);

            }).on('click', 'td.owner, .shareKey:not(.disabled)', function(e){
                var tr = $(this).closest('tr');
                if ( tr.hasClass('mymemory') || ( (tr.hasClass('ownergroup') || tr.hasClass('anonymous')) && !config.isLoggedIn) ) return;
                UI.openShareResource( $(this) );
            }).on('mousedown', '.mgmt-tm .downloadGlossary', function(e){
                //Todo
                if($(this).hasClass('disabled')) return false;
                UI.downloadGlossary( $(this) );


            }).on('mousedown', '.mgmt-tm .export-tmx-button', function(e){
                e.preventDefault();
                UI.startExportTmx(this);


            }).on('mousedown', '.mgmt-tm .canceladd-export-tmx', function(e){
                e.preventDefault();
                UI.closeExportTmx($(this).closest('tr'));
            }).on('mousedown', '.mgmt-tm .deleteTM:not(.disabled)', function(e){
                e.preventDefault();
                UI.showDeleteTmMessage(this);
            }).on('keydown', function(e) {

                var esc = 27 ;

                var handleEscPressed = function() {
                    if ($( '.modal:not([data-type=view])' ).length) {
                        e.stopPropagation();
                        APP.closePopup();
                        return;
                    } else if ( $('.popup-tm.open').length) {
                        e.stopPropagation();
                        UI.closeTMPanel();
                        UI.clearTMPanel();
                        return;
                    }
                };

                if ( e.which == esc ) handleEscPressed() ;

            }).on('click', '.share-button', function (e) {
                e.preventDefault();
                UI.clickOnShareButton($(this));
            }).on('keydown', '.message-share-tmx-input-email, .share-popup-container-input-email', function () {
                $(this).removeClass('error');
                UI.hideAllBoxOnTables();
            });
            $(".popup-tm.slide-panel").on("scroll", function(){
                if (!isVisible($(".active-tm-container h3"))) {
                    $('.active-tm-container .notification-message').addClass('fixed-msg');
                }
                else {
                    $('.active-tm-container .notification-message').removeClass('fixed-msg');
                }
                if (!isVisible($(".inactive-tm-container h3"))) {
                    $('.inactive-tm-container .notification-message').addClass('fixed-msg');
                }
                else {
                    $('.inactive-tm-container .notification-message').removeClass('fixed-msg');
                }
            });

            // script per filtrare il contenuto dinamicamente, da qui: http://www.datatables.net

            $(document).ready(function() {

                UI.setTMsortable();
                $("#inactivetm").tablesorter({
                    textExtraction: function(node) {
                        // extract data from markup and return it
                        if($(node).hasClass('privatekey')) {
                            return $(node).text();
                        } else {
                            return $(node).text();
                        }
                    },
                    headers: {
                        4: {
                            sorter: true
                        },
                        5: {
                            sorter: false
                        },
                        6: {
                            sorter: false
                        },
                        7: {
                            sorter: false
                        }
                    }
                });
                UI.CheckCreateTmKeyFromQueryString();

            });

            $(".mgmt-table-tm .add-tm").click(function() {
                $(this).hide();
                UI.openAddNewTm();
            });
            $(".mgmt-tm tr.new .canceladdtmx, .mgmt-tm tr.new .canceladdglossary").click(function() {
                UI.clearTMPanel();
            });

            $(".add-gl").click(function() {
                $(this).hide();
                $(".addrow-gl").show();
            });

            $(".cancel-tm").click(function() {
                $(".mgmt-tm tr.new").hide();
                $(".add-tm").show();
            });

            $("#sign-in").click(function() {
                $(".loginpopup").show();
            });

        },
        CheckCreateTmKeyFromQueryString: function () {
            var keyParam = APP.getParameterByName("private_tm_key");
            if (keyParam) {
                //Check if present and enable it
                var keyActive = UI.checkTMKeyIsActive(keyParam);
                if (keyActive) {
                    return false;
                }
                var keyInactive = UI.checkTMKeyIsInactive(keyParam);
                if (keyInactive){
                    this.activateInactiveKey(keyParam);
                    return false;
                }
                //Create the TM Key
                var keyParams = {
                    r: true,
                    w: true,
                    desc: "",
                    TMKey: keyParam
                };
                this.appendNewTmKeyToPanel( keyParams );
                new UI.DropDown( $(trKey).find( '.wrapper-dropdown-5' ) );
            }
        },
        activateInactiveKey: function (keyParam) {
            var objectsArray = $('#inactivetm tr:not(".new") .privatekey');
            var trKey = $.grep(objectsArray, function( value ){
                if( $(value).text().slice(-5) == keyParam.slice(-5) ){
                    return value;
                }
            });
            //Check the inputs
            var row = $(trKey).closest("tr");
            row.find('td.lookup input, td.update input').attr('checked', true);
            UI.useTM(trKey);
        },
        openLanguageResourcesPanel: function(tab, elToClick) {
            if ($(".popup-tm").hasClass('open') ) {
                return false;
            }
            tab = tab || 'tm';
            elToClick = elToClick || null;
            $('body').addClass('side-popup');
            $(".popup-tm").addClass('open').show("slide", { direction: "right" }, 400);
            $(".outer-tm").show();
            $('.mgmt-panel-tm .nav-tabs .mgmt-' + tab).click();
            if(elToClick) $(elToClick).click();
            $.cookie('tmpanel-open', 1, { path: '/' });
        },
        setTMsortable: function () {

            var fixHelper = function(e, ui) {
                ui.children().each(function() {
                    $(this).width($(this).width());
                });
                return ui;
            };

            $('#activetm tbody').sortable({
                helper: fixHelper,
                handle: '.dragrow',
                items: '.mine'
            });

        },

        checkTMKey: function(operation) {
            //check if the key already exists, it can not be sent nor added twice
            var keyValue = $('#new-tm-key').val();
            if (keyValue === "") {
                UI.showErrorOnKeyInput();
                return false;
            }

            var keyActive = this.checkTMKeyIsActive(keyValue);
            var keyInactive = this.checkTMKeyIsInactive(keyValue);

            if (keyActive) {
                UI.showErrorOnKeyInput('The key is already present in this project.');
                return false;
            } else if (keyInactive) {
                UI.showErrorOnKeyInput('The key is already assigned to one of your Inactive TMs. <a class="active-tm-key-link">Click here to activate it</a>');
                $('.active-tm-key-link').off('click');
                $('.active-tm-key-link').on('click', function() {
                    UI.clearTMPanel();
                    UI.activateInactiveKey(keyValue);
                });
                return false;
            }

            return APP.doRequest({
                data: {
                    action: 'ajaxUtils',
                    exec: 'checkTMKey',
                    tm_key: keyValue
                },
                context: operation,
                error: function() {
                    console.log('checkTMKey error!!');
                },
                success: function(d) {
                    if(d.success === true) {
                        UI.removeErrorOnKeyInput();
                        if(this == 'key') {
                            UI.addTMKeyToList(false);
                            UI.clearTMUploadPanel();
                        }
                    } else {
                        UI.showErrorOnKeyInput('The key is not valid. <a class="active-tm-key-link">Restore generated key</a>');
                        $('.active-tm-key-link').off('click');
                        $('.active-tm-key-link').on('click', function() {
                            UI.openAddNewTm();
                            UI.removeErrorOnKeyInput();
                        });
                    }
                }
            });
        },
        checkTMKeyIsActive: function (key) {
            var keys_of_the_job = $('#activetm tbody tr:not(".new") .privatekey');
            var keyIsAlreadyPresent = false;
            $( keys_of_the_job ).each( function( index, value ){
                if( $(value).text().slice(-5) == key.slice(-5) ){
                    keyIsAlreadyPresent = true;
                    return false;
                }
            } );
            return keyIsAlreadyPresent;
        },
        checkTMKeyIsInactive: function (key) {
            var keys_of_the_job = $('#inactivetm tbody tr:not(".new") .privatekey');
            var keyIsAlreadyPresent = false;
            $( keys_of_the_job ).each( function( index, value ){
                if( $(value).text().slice(-5) == key.slice(-5) ){
                    keyIsAlreadyPresent = true;
                    return false;
                }
            } );
            return keyIsAlreadyPresent;
        },
        showErrorOnKeyInput: function (message) {
            if (message) {
                this.showErrorOnActiveTMTable(message);
            }
            $('#activetm tr.new').addClass('badkey');
            UI.checkTMAddAvailability(); //some enable/disable stuffs
        },
        removeErrorOnKeyInput: function () {

            this.hideAllBoxOnTables();
            $('#activetm tr.new').removeClass('badkey');
            UI.checkTMAddAvailability();
        },
        checkTMAddAvailability: function () {
            if(($('#activetm tr.new').hasClass('badkey'))||($('#activetm tr.new').hasClass('badgrants'))) {
                $('#activetm tr.new .uploadtm').addClass('disabled');
            } else {
                $('#activetm tr.new .uploadtm').removeClass('disabled');
            }
        },

        checkTMgrants: function() {
            panel = $('.mgmt-tm tr.new');
            var r = ($(panel).find('.r').is(':checked'))? 1 : 0;
            var w = ($(panel).find('.w').is(':checked'))? 1 : 0;
            if(!r && !w) {
                $('#activetm tr.new').addClass('badgrants');
                UI.showErrorOnActiveTMTable('Either "Lookup" or "Update" must be checked');
                UI.checkTMAddAvailability();

                return false;
            } else {
                UI.hideAllBoxOnTables();
                $('#activetm tr.new').removeClass('badgrants');
                UI.checkTMAddAvailability();

                return true;
            }
        },
        checkTMGrantsModifications: function (el) {
            var tr = $(el).parents('tr.mine');
            var isActive = ($(tr).parents('table').attr('id') == 'activetm')? true : false;
            var deactivate = isActive && (!tr.find('.lookup input').is(':checked') && !tr.find(' td.update input').is(':checked'));
            if(!tr.find('.activate input').is(':checked') || deactivate ) {
                if(isActive) {
                    if(!config.isLoggedIn) {

                            var data = {
                                grant: ($(el).parents('td').hasClass('lookup')? 'lookup' : 'update'),
                                key: $(tr).find('.privatekey').text()
                            };

                        APP.confirm({
                            name: 'confirmTMDisable',
                            cancelTxt: 'Cancel',
                            onCancel: 'cancelTMDisable',
                            callback: 'continueTMDisable',
                            okTxt: 'Continue',
                            context: JSON.stringify(data),
                            msg: "If you confirm this action, your Private TM key will be lost. <br />If you want to avoid this, please, log in with your account now."
                        });
                        return false;
                    }
                    UI.disableTM(el);
                    $("#inactivetm").trigger("update");
                }
            } else {
                if(!isActive) {
                    UI.useTM(el);
                    $("#inactivetm").trigger("update");
                }
            }

        },
        cancelTMDisable: function (context) {
            var options = $.parseJSON(context);
            $('.mgmt-tm tr[data-key="' + options.key + '"] td.' + options.grant + ' input').click();
        },
        continueTMDisable: function (context) {
            var options = $.parseJSON(context);
            el = $('.mgmt-tm tr[data-key="' + options.key + '"] td.' + options.grant + ' input');
            UI.disableTM(el);
            $("#inactivetm").trigger("update");
        },

        disableTM: function (el) {
            var row = $(el).closest("tr");
            if(row.find('td.uploadfile').length) {
                row.find('td.uploadfile .canceladdtmx').click();
                row.find('.addtmx').removeAttr('style');
            }
            row.detach();
            $("#inactivetm").append(row);

            row.css('display', 'block');

            // draw the user's attention to it
            row.fadeOut();
            row.fadeIn();

            $('.addtmxrow').hide();

        },

        useTM: function (el) {
            var row = $(el).closest("tr");
            row.detach();
            $("#activetm tr.new").before(row);
            if(!$('#inactivetm tbody tr:not(.noresults)').length) $('#inactivetm tr.noresults').show();
            row.addClass('mine');
            row.find('td.lookup input, td.update input').attr('checked', true);
            row.css('display', 'block');

            //update datatable struct
            // draw the user's attention to it
            row.fadeOut();
            row.fadeIn();

            $('.addtmxrow').hide();
        },
        execAddTMOrGlossary: function(el, type) {
            var action = (type == 'glossary') ? '/api/v2/glossaries/import/' : '/?action=loadTMX';
            var line = $(el).parents('tr');
            line.find('.uploadfile').addClass('uploading');
            var form = line.find('.add-TM-Form')[0];
            var path = line.find('.uploadfile').find('input[type="file"]').val();
            var file = path.split('\\')[path.split('\\').length-1];
            this.fileUpload(form, action, 'uploadCallback', file, type);

        },
        addTMKeyToList: function ( uploading ) {
            var descr = $( '#new-tm-description' ).val();
            var key = $( '#new-tm-key' ).val();
            descr = (descr.length) ? descr : "Private TM and Glossary";
            var keyParams = {
                r: $( '#new-tm-read' ).is( ':checked' ),
                w: $( '#new-tm-write' ).is( ':checked' ),
                desc: descr,
                TMKey: $( '#new-tm-key' ).val()
            };

            this.appendNewTmKeyToPanel( keyParams );
            new UI.DropDown( $( '#activetm tr.mine' ).last().find( '.wrapper-dropdown-5' ) );
            if ( uploading ) {
                $( '.mgmt-tm tr.new' ).addClass( 'hide' );
            } else {
                $( '.mgmt-tm tr.new .canceladdtmx' ).click();
            }

            UI.pulseTMadded( $( '#activetm tr.mine' ).last() );

            if ( APP.isCattool ) UI.saveTMdata( false );
            UI.checkTMKeysUpdateChecks();
            if (config.isLoggedIn) {
                UI.checkKeyIsShared(key);
            }
            this.initTmxTooltips();
        },
        checkKeyIsShared: function (key) {
            UI.getUserSharedKey(key).done(function (response) {
                var users = response.data;
                if (users.length > 1) {
                    $('tr.mine[data-key='+ key +'] .icon-owner').removeClass('icon-lock icon-owner-private')
                        .addClass('icon-users icon-owner-shared');
                }
            });
        },
        /**
         * Row structure
         * @var keyParams
         *
         * <code>
         * var keyParams = {
         *       r: 1|0,
         *       w: 1|0,
         *       desc: "string",
         *       TMKey: "string"
         *   };
         * </code>
         */
        appendNewTmKeyToPanel: function( keyParams ){

            keyParams = {
                r: typeof keyParams.r !== 'undefined' ? keyParams.r : 0,
                w: typeof keyParams.w !== 'undefined' ? keyParams.w : 0,
                desc: typeof keyParams.desc !== 'undefined' ? keyParams.desc : '',
                TMKey: typeof keyParams.TMKey !== 'undefined' ? keyParams.TMKey : ''
            };

            var newTr = '<tr class="mine" data-tm="1" data-glos="1" data-key="' + keyParams.TMKey + '" data-owner="' + config.ownerIsMe + '">' +
                '    <td class="dragrow"><div class="status"></div></td>' +
                '    <td class="activate"><input type="checkbox" checked="checked"/></td>' +
                '    <td class="lookup check text-center"><input type="checkbox"' + ( keyParams.r ? ' checked="checked"' : '' ) + ' /></td>' +
                '    <td class="update check text-center"><input type="checkbox"' + ( keyParams.w ? ' checked="checked"' : '' ) + ' /></td>' +
                '    <td class="description"><div class="edit-desc" data-descr="'+ keyParams.desc +'">' + keyParams.desc + '</div></td>' +
                '    <td class="privatekey">' + keyParams.TMKey + '</td>' +
                '    <td class="owner text-center">' +
                '       <a class="icon-owner icon-lock icon-owner-private"></a>'+
                '   </td>' +
                '    <td class="action">' +
                '       <a class="btn pull-left addtmx"><span class="text">Import TMX</span></a>'+
                '          <div class="wrapper-dropdown-5 pull-left" tabindex="1">&nbsp;'+
                '              <ul class="dropdown pull-left">' +
                '                   <li><a class="addGlossary" title="Import Glossary" alt="Import Glossary"><span class="icon-upload"></span>Import Glossary</a></li>'+
                '                   <li><a class="downloadtmx" title="Export TMX" alt="Export TMX"><span class="icon-download"></span>Export TMX</a></li>' +
                '                   <li><a class="downloadGlossary" title="Export Glossary" alt="Export Glossary"><span class="icon-download"></span>Export Glossary</a></li>' +
                '                   <li><a class="shareKey" title="Share resource" alt="Share resource"><span class="icon-users"></span>Share resource</a></li>' +
                '                  <li><a class="deleteTM" title="Delete TMX" alt="Delete TMX"><span class="icon-trash-o"></span>Delete TM</a></li>'+
                '              </ul>'+
                '          </div>'+
                '</td>' +
                '</tr>';

            $('#activetm').find('tr.new').before( newTr );

            UI.setTMsortable();
        },

        pulseTMadded: function (row) {
            setTimeout(function() {
                $("#activetm tbody").animate({scrollTop: 5000}, 0);
                row.fadeOut();
                row.fadeIn();
            }, 10);
            setTimeout(function() {
                $("#activetm tbody").animate({scrollTop: 5000}, 0);
            }, 1000);

        },
        clearTMUploadPanel: function () {
            $('#new-tm-key, #new-tm-description').val('');
            $('#new-tm-key').removeAttr('disabled');
            $('#new-tm-read, #new-tm-write').prop('checked', true);
        },
        clearAddTMRow: function() {
            $('#new-tm-description').val('');
            $('#new-tm-key').removeAttr('disabled');
            $('#activetm .fileupload').val('');
            $('.mgmt-tm tr.new').removeClass('badkey badgrants');
            $('.mgmt-tm tr.new .message').text('');
            $('.mgmt-tm tr.new .error span').text('').hide();
            $('.mgmt-tm tr.new .addtmxfile, .mgmt-tm tr.new .addglossaryfile').show();
        },
        clearTMPanel: function () {

            $('#activetm .edit-desc').removeAttr('contenteditable');
            $('#activetm td.action a').removeClass('disabled');
            $("#activetm tr.new").hide();
            $("tr.tm-key-deleting").removeClass('tm-key-deleting');
            $("#activetm tr.new .addtmxfile, #activetm tr.new .addtmxfile .addglossaryfile").removeClass('disabled');
            $(".mgmt-table-tm .add-tm").show();
            UI.clearTMUploadPanel();
            UI.clearAddTMRow();
            UI.hideAllBoxOnTables();
        },

        fileUpload: function(form, action_url, div_id, tmName, type) {
            // Create the iframe...
            var ts = new Date().getTime();
            var ifId = "upload_iframe-" + ts;
            var iframe = document.createElement("iframe");
            iframe.setAttribute("id", ifId);
            console.log('iframe: ', iframe);
            iframe.setAttribute("name", "upload_iframe");
            iframe.setAttribute("width", "0");
            iframe.setAttribute("height", "0");
            iframe.setAttribute("border", "0");
            iframe.setAttribute("style", "width: 0; height: 0; border: none;");
            // Add to document...
            document.body.appendChild(iframe);

            window.frames['upload_iframe'].name = "upload_iframe";
            iframeId = document.getElementById(ifId);
            UI.UploadIframeId = iframeId;

            // Add event...
            var eventHandler = function () {

                if (iframeId.detachEvent) iframeId.detachEvent("onload", eventHandler);
                else iframeId.removeEventListener("load", eventHandler, false);

                // Message from server...
                if (iframeId.contentDocument) {
                    content = iframeId.contentDocument.body.innerHTML;
                } else if (iframeId.contentWindow) {
                    content = iframeId.contentWindow.document.body.innerHTML;
                } else if (iframeId.document) {
                    content = iframeId.document.body.innerHTML;
                }

                document.getElementById(div_id).innerHTML = content;

            };

            if (iframeId.addEventListener) iframeId.addEventListener("load", eventHandler, true);
            if (iframeId.attachEvent) iframeId.attachEvent("onload", eventHandler);
            var TR = $(form).parents('tr');
            var Key = TR.find('.privatekey').first().text();

            // Set properties of form...
            form.setAttribute("target", "upload_iframe");
            form.setAttribute("action", action_url);
            form.setAttribute("method", "post");
            form.setAttribute("enctype", "multipart/form-data");
            form.setAttribute("encoding", "multipart/form-data");
            if (type === "tmx") {
                $(form).append('<input type="hidden" name="exec" value="newTM" />');
            }
            $(form).append('<input type="hidden" name="tm_key" value="' + Key + '" />')
                    .append('<input type="hidden" name="name" value="' + tmName + '" />')
                    .append('<input type="hidden" name="r" value="1" />')
                    .append('<input type="hidden" name="w" value="1" />');
            if(APP.isCattool) {
                $(form).append('<input type="hidden" name="job_id" value="' + config.job_id + '" />')
                    .append('<input type="hidden" name="job_pass" value="' + config.password + '" />')
            }

            // Submit the form...
            form.submit();

            document.getElementById(div_id).innerHTML = "";
            var filePath =  $(form).find('input[type="file"]').val();
            var fileName = filePath.split('\\')[filePath.split('\\').length-1];

            var TRcaller = $(form).parents('.uploadfile');
            TRcaller.addClass('startUploading');

            setTimeout(function() {
                UI.pollForUploadCallback(Key, fileName, TRcaller, type);
            }, 3000);

            return false;

        },
        pollForUploadCallback: function(Key, fileName, TRcaller, type) {

            if($('#uploadCallback').text() != '') {
                var msg = $.parseJSON($('#uploadCallback pre').text());
                if(msg.success === true) {
                    setTimeout(function() {
                        //delay because server can take some time to process large file
                        TRcaller.removeClass('startUploading');
                        UI.pollForUploadProgress(Key, fileName, TRcaller, type);
                    }, 3000);
                } else {
                    // console.log('error');
                    TRcaller.removeClass('startUploading');
                    TRcaller.find('.action a').removeClass('disabled');
                    $(TRcaller).find('.addtmxfile, .addglossaryfile').hide();
                    UI.UploadIframeId.remove();
                    $(TRcaller).find('.upload-file-msg-error').text('Error').show();
                    if ($(TRcaller).closest('table').attr("id") == 'inactivetm'){
                        UI.showErrorOnInactiveTMTable(msg.errors[0].message);
                    }else {
                        UI.showErrorOnActiveTMTable(msg.errors[0].message)          ;
                    }

                }
            } else {
                setTimeout(function() {
                    UI.pollForUploadCallback(Key, fileName, TRcaller, type);
                }, 1000);
            }

        },
        pollForUploadProgress: function(Key, fileName, TRcaller, type) {
            var glossaryUrl = '/api/v2/glossaries/import/status/' + Key +'/' + fileName;
            var urlReq, data, typeReq;
            if (type === "glossary") {
                urlReq = glossaryUrl;
                data = {};
                typeReq = 'GET';
            } else {
                data = {
                    action: 'loadTMX',
                    exec: 'uploadStatus',
                    tm_key: Key,
                    name: fileName
                };
                typeReq = 'POST';

            }
            APP.doRequest({
                url: urlReq,
                data: data,
                type: typeReq,
                context: [Key, fileName, true, TRcaller],
                error: function() {
                    var TDcaller = this[3];
                    $(TDcaller).find('.addtmxfile, .addglossaryfile, .uploadprogress').hide();
                    $(TDcaller).find('.upload-file-msg-error').text('Error').show();
                    $(TDcaller).find('.canceladdglossary').show();
                    $(TDcaller).find('input[type="file"]').attr("disabled", false);
                    if ($(TDcaller).closest('table').attr("id") == 'inactivetm'){
                        UI.showErrorOnInactiveTMTable('There was an error saving your data. Please retry!');
                    }else {
                        UI.showErrorOnActiveTMTable('There was an error saving your data. Please retry!');
                    }
                    $(TDcaller).closest('tr').find('.action a').removeClass('disabled');
                    UI.UploadIframeId.remove();
                },
                success: function(d) {
                    var TDcaller = this[3];
                    $(TDcaller).closest('tr').find('.action a').removeClass('disabled');
                    if(d.errors.length) {
                        $(TDcaller).find('.addtmxfile, .addglossaryfile, .uploadprogress').hide();
                        $(TDcaller).find('.upload-file-msg-error').text('Error').show();
                        $(TDcaller).find('.canceladdglossary').text('Error').show();
                        $(TDcaller).find('input[type="file"]').attr("disabled", false);
                        if ($(TDcaller).closest('table').attr("id") == 'inactivetm'){
                            UI.showErrorOnInactiveTMTable(d.errors[0].message);
                        } else {
                            UI.showErrorOnActiveTMTable(d.errors[0].message);
                        }
                        UI.UploadIframeId.remove();
                    } else {
                        $(TDcaller).find('.addglossaryfile, .canceladdglossary, .addtmxfile, .canceladdtmx').hide();
                        $(TDcaller).find('input[type="file"]').attr("disabled", true);
                        $(TDcaller).find('.uploadprogress').show();

                        if(d.data.total == null) {
                            setTimeout(function() {
                                UI.pollForUploadProgress(Key, fileName, TDcaller, type);
                            }, 1000);
                        } else {
                            if(d.data.completed) {
                                var tr = $(TDcaller).parents('tr');
                                $(tr).find('.addtmx, .addGlossary').removeClass('disabled');

                                $(TDcaller).find('.uploadprogress,.canceladdtmx,.addtmxfile, .addglossaryfile, .cancelladdglossary').hide();

                                if( !tr.find('td.description .edit-desc').text() ){
                                    tr.find('td.description .edit-desc').text(fileName);
                                }

                                $(TDcaller).find(".upload-file-msg-success").show();
                                setTimeout(function() {
                                    $(TDcaller).slideToggle(function () {
                                        this.remove();
                                    });
                                }, 3000);



                                UI.UploadIframeId.remove();

                                return false;
                            }
                            var progress = (parseInt(d.data.done)/parseInt(d.data.total))*100;
                            $(TDcaller).find('.progress .inner').css('width', progress + '%');
                            setTimeout(function() {
                                UI.pollForUploadProgress(Key, fileName, TDcaller, type);
                            }, 1000);
                        }
                    }

                }
            });
        },
        allTMUploadsCompleted: function () {
            if($('#activetm .uploadfile.uploading').length) {
                APP.alert({msg: 'There is one or more TM uploads in progress. Try again when all uploads are completed!'});
                return false;
            } else if( $( 'tr td a.downloading' ).length ){
                APP.alert({msg: 'There is one or more TM downloads in progress. Try again when all downloads are completed or open another browser tab.'});
                return false;
            } else {
                return true;
            }
        },

        extractTMdataFromTable: function () {
            var categories = ['ownergroup', 'mine', 'anonymous'];
            var newArray = {};
            $.each(categories, function (index, value) {
                newArray[value] = UI.extractTMDataFromRowCategory(this);
            });
            return JSON.stringify(newArray);
        },
        extractTMDataFromRowCategory: function(cat) {
            var tt = $('#activetm tbody tr.' + cat);
            var dataOb = [];
            $(tt).each(function () {
                var r = (($(this).find('.lookup input').is(':checked'))? 1 : 0);
                var w = (($(this).find('.update input').is(':checked'))? 1 : 0);
                var isMymemory = $(this).hasClass('mymemory');
                if ((!r && !w) || isMymemory) {
                    return true;
                }
                var dd = {
                    tm: $(this).attr('data-tm'),
                    glos: $(this).attr('data-glos'),
                    owner: $(this).attr('data-owner'),
                    key: $(this).find('.privatekey').text().trim(), // remove spaces and unwanted chars from string
                    name: $(this).find('.description').text().trim(),
                    r: r,
                    w: w
                };
                dataOb.push(dd);
            });
            return dataOb;
        },

        extractTMdataFromRow: function (tr) {
            data = {
                tm_key: tr.find('.privatekey').text(),
                key: this.tm_key,
                tmx_name: tr.find('.description').text(),
                name: this.tmx_name,
                r: ((tr.find('.lookup input').is(':checked'))? 1 : 0),
                w: ((tr.find('.update input').is(':checked'))? 1 : 0)
            }
            return data;
        },

        saveTMdata: function(closeAfter) {

            $('.popup-tm').addClass('saving');
            if(closeAfter) {
                UI.closeTMPanel();
                UI.clearTMPanel();
            }
            if(!APP.isCattool) return;
            data = this.extractTMdataFromTable();
            APP.doRequest({
                data: {
                    action: 'updateJobKeys',
                    job_id: config.job_id,
                    job_pass: config.password,
                    data: data
                },
                error: function() {
                    UI.showErrorOnActiveTMTable('There was an error saving your data. Please retry!');
                    $('.popup-tm').removeClass('saving');

                },
                success: function(d) {
                    $('.popup-tm').removeClass('saving');
                    if(d.errors.length) {
                        UI.showErrorOnActiveTMTable('There was an error saving your data. Please retry!');
                        // APP.showMessage({msg: d.errors[0].message});
                    } else {
                        UI.hideAllBoxOnTables();
                    }
                }
            });
        },
        saveTMdescription: function (field) {
            if (!config.isLoggedIn) return;
            var tr = field.parents('tr').first();
            var old_descr = tr.find('.edit-desc').data('descr');
            var new_descr = field.text();
            if (old_descr === new_descr) {
                return;
            }
            APP.doRequest({
                data: {
                    action: 'userKeys',
                    exec: 'update',
                    key: tr.find('.privatekey').text(),
                    description: new_descr
                },
                error: function() {
                    UI.showErrorOnActiveTMTable('There was an error saving your description. Please retry!');
                    // APP.showMessage({msg: 'There was an error saving your description. Please retry!'});
                    $('.popup-tm').removeClass('saving');
                },
                success: function(d) {
                    tr.find('.edit-desc').data('descr', new_descr);
                    $('.popup-tm').removeClass('saving');
                    if(d.errors.length) {
                        UI.showErrorOnActiveTMTable(d.errors[0].message);
                        // APP.showMessage({msg: d.errors[0].message});
                    } else {
                        UI.hideAllBoxOnTables();
                    }
                }
            });
        },
        saveTMkey: function (key, desc) {
            delete UI.newTmKey;
            if ( desc.length == 0 ) {
                desc = "Private TM and Glossary";
            }
            return APP.doRequest({
                data: {
                    action: 'userKeys',
                    exec: 'newKey',
                    key: key,
                    description: desc
                },
                error: function() {
                    UI.showErrorOnActiveTMTable('There was an error saving your data. Please retry!');
                    $('.popup-tm').removeClass('saving');
                },
                success: function(d) {
                    $('.popup-tm').removeClass('saving');
                    UI.hideAllBoxOnTables();
                    if(d.errors.length) {
                        UI.showErrorOnActiveTMTable(d.errors[0].message);
                    }
                }
            });
        },


        closeTMPanel: function () {
            $( ".popup-tm").removeClass('open').hide("slide", { direction: "right" }, 400);
            $(".outer-tm").hide();
            $('body').removeClass('side-popup');
            $.cookie('tmpanel-open', 0, { path: '/' });
            if((!APP.isCattool)&&(!checkAnalyzability('closing tmx panel'))) {
                disableAnalyze();
                if(!checkAnalyzabilityTimer) var checkAnalyzabilityTimer = window.setInterval( function () {
                    if(checkAnalyzability('set interval')) {
                        enableAnalyze();
                        window.clearInterval( checkAnalyzabilityTimer );
                    }
                }, 500 );
            }
        },
        filterInactiveTM: function (txt) {
            $('#inactivetm tbody tr').removeClass('found');
            $('#inactivetm tbody td.privatekey:containsNC("' + txt + '"), #inactivetm tbody td.description:containsNC("' + txt + '")').parents('tr').addClass('found');
        },
        downloadTM: function( tm, email ) {
            var tm_key = $( '.privatekey', tm ).text().trim();
            var tm_name = $( '.description', tm ).text().trim();

            var id_job;
            var password;

            if ( typeof config.id_job !== 'undefined' ){
                id_job = config.id_job;
                password = config.password;
            }

            return APP.doRequest({
                data: {
                    action: 'downloadTMX',
                    tm_key: tm_key,
                    tm_name: tm_name,
                    id_job: id_job,
                    password: password,
                    email: email
                }
            });

        },
        downloadGlossary: function( elem ) {
            var tr = elem.closest('tr');
            UI.hideAllBoxOnTables();
            this.openExportGlossary(elem, tr);

            //add a random string to avoid collision for concurrent javascript requests
            //in the same milli second, and also, because a string is needed for token and not number....
            var downloadToken = new Date().getTime() + "_" + parseInt( Math.random( 0, 1 ) * 10000000 );

            //create a random Frame ID and form ID to get it uniquely
            var iFrameID = 'iframeDownload_' + downloadToken;
            var formID = 'form_' + downloadToken;

            //create an iFrame element
            var iFrameDownload = $( document.createElement( 'iframe' ) ).hide().prop( {
                id: iFrameID,
                src: ''
            } );

            $( "body" ).append( iFrameDownload );

            iFrameDownload.ready( function () {

                //create a GLOBAL setInterval so in anonymous function it can be disabled
                var downloadTimer = window.setInterval( function () {

                    //check for cookie equals to it's value.
                    //This is unique by definition and we can do multiple downloads
                    var token = $.cookie( downloadToken );

                    //if the cookie is found, download is completed
                    //remove iframe an re-enable download button
                    if ( token ) {
                        window.clearInterval( downloadTimer );
                        elem.removeClass( 'disabled' );
                        tr.find('.uploadloader').hide();
                        $.cookie( downloadToken, null, {path: '/', expires: -1} );
                        errorMsg = $( '#' + iFrameID ).contents().find( 'body' ).text();
                        errorKey = $( tr ).attr( 'data-key' );
                        if ( errorMsg != '' ) {
                            tr.find('.message-glossary-export-error').show();
                            if (tr.closest('table').attr("id") == 'inactivetm'){
                                UI.showErrorOnInactiveTMTable('Export failed. No glossary found in the resource.');
                            } else {
                                UI.showErrorOnActiveTMTable('Export failed. No glossary found in the resource.');
                            }

                        } else {
                            tr.find('.message-glossary-export-completed').show();
                        }
                        tr.find('.action a').removeClass('disabled');
                        $( '#' + iFrameID ).remove();

                        setTimeout(function () {
                            tr.find('td.download-glossary-container').slideToggle(function () {
                                $(this).remove();
                            });
                        }, 3000);

                    }

                }, 2000 );
            } );
            var tm_key = $( '.privatekey', tr ).text();
            //create the html form and append a token for download
            var iFrameForm = $( document.createElement( 'form' ) ).attr( {
                'id': formID,
                'action': '/api/v2/glossaries/export/'+ tm_key + '/' + downloadToken,
                'method': 'GET'
            } );

            //append from to newly created iFrame and submit form post
            iFrameDownload.contents().find( 'body' ).append( iFrameForm );
            console.log( iFrameDownload.contents().find( "#" + formID ) );
            iFrameDownload.contents().find( "#" + formID ).submit();

        },
        showMTDeletingMessage: function (button) {
            var tr = button.closest('tr');
            var id = tr.data("id");
            $('.mgmt-table-mt .tm-warning-message').html('Do you really want to delete this MT? ' +
                '<a class="pull-right btn-confirm-small continueDeletingMT confirm-tm-key-delete" style="display: inline;">       <span class="text">Confirm</span>   </a>' +
                '<a class="pull-right btn-orange-small cancelDeletingMT cancel-tm-key-delete">      <span class="text"></span>   </a>').show();
            $('.continueDeletingMT, .cancelDeletingMT').off('click');
            $('.continueDeletingMT').on('click', function(e){
                e.preventDefault();
                UI.deleteMT(id);
                $('.mgmt-table-mt .tm-warning-message').empty().hide();
            });
            $('.cancelDeletingMT').on('click', function(e) {
                e.preventDefault();
                UI.hideAllBoxOnTables();
            });
        },
        showDeleteTmMessage: function (button) {
            $("tr.tm-key-deleting").removeClass('tm-key-deleting');
            var message = 'Do you really want to delete the key XXX? ' +
                    '<a class="pull-right btn-orange-small cancelDelete cancel-tm-key-delete">      <span class="text"></span>   </a>' +
                    '<a class="pull-right btn-confirm-small confirmDelete confirm-tm-key-delete" style="display: inline;">       <span class="text">Confirm</span>   </a>';
            var elem = $(button).closest("table");
            var tr = $(button).closest("tr");
            tr.addClass("tm-key-deleting");
            var key = tr.find('.privatekey').text();
            message = message.replace('XXX', key);
            if (elem.attr("id") === "activetm") {
                UI.showWarningOnActiveTMTable(message);
            } else {
                UI.showWarningOnInactiveTMTable(message);
            }
            var removeListeners = function () {
                $('.confirm-tm-key-delete, .cancel-tm-key-delete').off('click');
            };
            $('.confirm-tm-key-delete').off('click');
            $('.confirm-tm-key-delete').on('click', function (e) {
                e.preventDefault();
                UI.deleteTM(button);
                UI.hideAllBoxOnTables();
                removeListeners();
            });
            $('.cancel-tm-key-delete').off('click');
            $('.cancel-tm-key-delete').on('click', function (e) {
                e.preventDefault();
                UI.hideAllBoxOnTables();
                $("tr.tm-key-deleting").removeClass('tm-key-deleting');
                removeListeners();
            });
        },
        deleteTM: function (button) {
            tr = $(button).parents('tr').first();
            $(tr).fadeOut("normal", function() {
                $(this).remove();
            });
            APP.doRequest({
                data: {
                    action: 'userKeys',
                    exec: 'delete',
                    key: tr.find('.privatekey').text()
                },
                error: function() {
                    UI.showErrorOnActiveTMTable('There was an error saving your data. Please retry!');
                    // console.log('Error deleting TM!!');
                },
                success: function(d) {
                    UI.hideAllBoxOnTables();
                }
            });
        },
        deleteMT: function (id) {
            APP.doRequest({
                data: {
                    action: 'engine',
                    exec: 'delete',
                    id: id
                },
                context: id,
                error: function() {
                    $(".mgmt-table-mt .tm-error-message").text("There was an error saving your data. Please retry!").show();
                },
                success: function(d) {
                    // console.log('success');
                    UI.hideAllBoxOnTables();
                    $('.mgmt-table-mt tr[data-id=' + this + ']').remove();
                    $('#mt_engine option[value=' + this + ']').remove();
                    if(!$('#mt_engine option[selected=selected]').length) $('#mt_engine option[value=0]').attr('selected', 'selected');
                }
            });
        },

        addMTEngine: function (provider, providerName) {
            var providerData = {};
            $('.insert-tm .provider-data .provider-field').each(function () {
                field = $(this).find('input, select').first();
                if (field.prop('type') === 'checkbox') {
                    providerData[field.attr('data-field-name')] = field.prop('checked');
                } else {
                    providerData[field.attr('data-field-name')] = field.val();
                }
            });

            var name = $('#new-engine-name').val();
            var data = {
                action: 'engine',
                exec: 'add',
                name: name,
                provider: provider,
                data: JSON.stringify(providerData)
            };
            var context = data;
            context.providerName = providerName;

            APP.doRequest({
                data: data,
                context: context,
                error: function() {
                    console.log('error');
                },
                success: function(d) {
                    if(d.errors.length) {
                        console.log('error');
                        $('#mt-provider-details .error').text(d.errors[0].message);
                    } else {
                        if(d.data.config && Object.keys(d.data.config).length) {
                            UI.renderMTConfig(provider, d.name, d.data.config);
                        }
                        else {
                            console.log('success');
                            UI.renderNewMT(this, d.data.id);
                            if(!APP.isCattool) {
                                UI.activateMT($('table.mgmt-mt tr[data-id=' + d.data.id + '] .enable-mt input'));
                                $('#mt_engine').append('<option value="' + d.data.id + '">' + this.name + '</option>');
                                $('#mt_engine option:selected').removeAttr('selected');
                                $('#mt_engine option[value="' + d.data.id + '"]').attr('selected', 'selected');
                            }
                            $('#mt_engine_int').val('none').trigger('change');
                        }
                    }

                }
            });
        },
        renderNewMT: function (data, id) {
            var newTR =    '<tr data-id="' + id + '">' +
                '    <td class="mt-provider">' + data.providerName + '</td>' +
                '    <td class="engine-name">' + data.name + '</td>' +
                '    <td class="enable-mt text-center">' +
                '        <input type="checkbox" checked />' +
                '    </td>' +
                '    <td class="action">' +
                '        <a class="deleteMT btn pull-left"><span class="text">Delete</span></a>' +
                '    </td>' +
                '</tr>';
            if(APP.isCattool) {
                $('table.mgmt-mt tbody tr:not(.activemt)').first().before(newTR);

            } else {
                $('table.mgmt-mt tbody tr.activetm').removeClass('activetm').find('.enable-mt input').removeAttr('checked');
                $('table.mgmt-mt tbody').prepend(newTR);
            }


        },

        /* codice inserito da Daniele */
        pulseMTadded: function (row) {

            setTimeout(function() {
                $('.activemt').animate({scrollTop: 5000}, 0);
                row.fadeOut();
                row.fadeIn();
            }, 10);
            setTimeout(function() {
                $('.activemt').animate({scrollTop: 5000}, 0);
            }, 1000);

        },
        resetMTProviderPanel: function () {

            if($('.insert-tm .step2').css('display') == 'block') {
                $('#add-mt-provider-cancel-int').click();
                $('.add-mt-engine').click();
            };

        },
        activateMT: function (el) {
            var  tr = $(el).parents('tr');
            $(el).replaceWith('<input type="checkbox" checked class="temp" />');
            var  cbox = tr.find('input[type=checkbox]');
            var  tbody = tr.parents('tbody');
            $(tbody).prepend(tr);
            tbody.find('.activemt input[type=checkbox]').replaceWith('<input type="checkbox" />');
            tbody.find('.activemt').removeClass('activemt');
            tr.addClass('activemt').removeClass('temp');
            $('#mt_engine option').removeAttr('selected');
            $('#mt_engine option[value=' + tr.attr('data-id') + ']').attr('selected', 'selected');
            UI.pulseMTadded($('.activemt').last());

        },
        deactivateMT: function (el) {
            var  tr = $(el).parents('tr');
            $(el).replaceWith('<input type="checkbox" />');
            tr.removeClass('activemt');
            $('#mt_engine option').removeAttr('selected');
            $('#mt_engine option[value=0]').attr('selected', 'selected');
        },
        openTMActionDropdown: function (switcher) {
            $(switcher).parents('td').find('.dropdown').toggle();
        },
        closeTMActionDropdown: function (el) {
            $(el).parents('td').find('.wrapper-dropdown-5').click();
        },

        setDropDown: function(){

            //init dropdown events on every class
            new UI.DropDown( $( '.wrapper-dropdown-5' ) );

            //set control events
            $( '.action' ).off("mouseleave");
            $( '.action' ).mouseleave( function(){
                $( '.wrapper-dropdown-5' ).removeClass( 'activeMenu' );
            } );

            $(document).click(function() {
                // all dropdowns
                $('.wrapper-dropdown-5').removeClass('activeMenu');
            });

        },



        DropDown: function(el){
            this.initEvents = function () {
                var obj = this;
                var fun = function ( event ) {
                    $( this ).toggleClass( 'activeMenu' );
                    event.preventDefault();
                    if($( this ).hasClass( 'activeMenu' )) {
                        event.stopPropagation();
                    }
                };
                obj.dd.off('click');
                obj.dd.on( 'click', fun);
            };
            this.dd = el;
            this.initEvents();
        },

        renderMTConfig: function(provider, newEngineName, configData) {

            if(provider == 'none') {
                $('.step2 .fields').html('');
                $(".step2").hide();
                $(".step3").hide();
                $('#add-mt-provider-cancel').show();
            } else {
                $('.step2 .fields').html($('#mt-provider-' + provider + '-config-fields').html());
                $('.step3 .text-left').html($('#mt-provider-' + provider + '-config-msg').html());
                $(".step2").show();
                $(".step3").show();
                $("#add-mt-provider-confirm").removeClass('hide');
            }

            $('#new-engine-name').val(newEngineName);

            // Populate the template fields with given values and store extra data within their data attributes
            var selectorBase = '.insert-tm .provider-data .provider-field';
            for (var fieldName in configData){
                var field = $(selectorBase + " [data-field-name='" + fieldName +"']");
                var tagName = field.prop('tagName');
                if (tagName == 'INPUT'){
                    var fieldContents = configData[fieldName]['value'];
                    field.val(fieldContents);

                    var fieldData = configData[fieldName]['data'];
                    for (var dataKey in fieldData) {
                        field.attr("data-" + dataKey, fieldData[dataKey]);
                    }
                } else if (tagName == 'SELECT'){
                    for (var optionKey in configData[fieldName]) {
                        var optionName = configData[fieldName][optionKey]['value'];
                        var option = $("<option value='" + optionKey + "'>" + optionName + "</option>");

                        var optionData = configData[fieldName][optionKey]['data'];
                        for (var dataKey in optionData) {
                            option.attr("data-" + dataKey, optionData[dataKey]);
                        }

                        field.append(option);
                    }
                }
            }

            // notify the template's javascript that the template has been populated
            if (typeof renderMTConfigCallback == 'function') {
                renderMTConfigCallback();
            }
        },

        openAddNewTm: function () {
            $(".mgmt-table-tm tr.new").removeClass('hide').show();
            // $('#new-tm-key').attr('disabled','disabled');
            if (UI.newTmKey) {
                UI.copyNewTMKey(UI.newTmKey);
                return false;
            }
            //call API
            APP.doRequest( {
                data: {
                    action: 'createRandUser'
                },
                success: function ( d ) {
                    data = d.data;
                    //put value into input field
                    UI.newTmKey = data.key;
                    UI.copyNewTMKey(UI.newTmKey);
                    return false;
                }
            });
        },
        copyNewTMKey: function (key) {
            $('#new-tm-key').val(key);
            $('#activetm tr.new').removeClass('badkey');
            UI.checkTMAddAvailability();
        },
        openExportTmx: function (elem) {
            $(elem).parents('.action').find('a').each( function() { $(this).addClass('disabled'); } );

            var exportDiv = '<td class="download-tmx-container" style="display: none">' +
                '<div class="message-export-tmx">We will send a link to download the exported TM to this email:</div>' +
                '<div class="message-export-tmx-success"></div>' +
                '<input type="email" required class="email-export-tmx mgmt-input" value="' + config.userMail + '"/>' +
                '<span class="uploadloader"></span>'+
                '<span class="email-export-tmx-email-sent">Request submitted</span>' +
                '<a class="pull-right btn-grey canceladd-export-tmx">' +
                    '<span class="text"></span>'+
                '</a>' +
                '<a class="pull-right btn-ok export-tmx-button">' +
                '   <span class="text export-tmx-button-label">Confirm</span>' +
                '</a>' +
                '<span class="email-export-tmx-email-error">We got an error,</br> please contact support</span>' +
                '</td>';

            $(elem).parents('tr').append(exportDiv);
            $(elem).parents('tr').find('.download-tmx-container').slideToggle();
        },
        getUserSharedKey: function (keyValue) {
            return APP.doRequest({
                data: {
                    action: 'userKeys',
                    exec: 'info',
                    key: keyValue
                },
                error: function() {
                    console.log('getUserSharedKey error');
                },
                success: function(d) {
                    if(d.success !== true) {
                        UI.showErrorOnActiveTMTable('Error retrieving the information, try again');

                    }
                }
            });
        },
        openShareResource: function (elem) {
            var tr = $(elem).parents('tr');
            if (tr.find(".share-tmx-container").length) {
                tr.find('.share-tmx-container').slideToggle(function () {
                    $(this).remove();
                });
                tr.find('.action').find('a').each( function() { $(this).removeClass('disabled'); } );
                return
            }

            var key = tr.find(".privatekey").text();
            if ( key.indexOf('*') >-1) return;
            this.getUserSharedKey(key).done(function (response) {
                if ( response.success !== true ) return;

                var users = response.data;
                //Remove the user from the list
                var indexOfUser = users.map(function(item) { return item.email; }).indexOf(config.userMail);
                var user = users.splice(indexOfUser,1);
                user = user[0];

                tr.find('.action').find('a').each( function() { $(this).addClass('disabled'); } );
                //Create the container
                var exportDiv = '';
                if ( users.length === 0 ) {
                    $('tr.mine[data-key='+ key +'] .icon-owner').removeClass('icon-users icon-owner-shared')
                        .addClass('icon-lock icon-owner-private');
                    exportDiv = '<td class="share-tmx-container" style="display: none">' +
                        '<div class="message-share-tmx">Share ownership of the resource by sharing the key. This action cannot be undone.</div>' +
                        '<input class="message-share-tmx-input-email" placeholder="Enter email addresses separated by comma"/>'+
                        '<a class="pull-right btn-orange-small cancelsharetmx"><span class="text"></span>   </a>' +
                        '<div class="pull-right btn-ok share-button">Share</div>'+
                        '</td>';
                } else if ( users.length > 0 ) {
                    $('tr.mine[data-key='+ key +'] .icon-owner').removeClass('icon-lock icon-owner-private')
                        .addClass('icon-users icon-owner-shared');
                    var totalShareUsers = (users.length === 1) ? '' : 'and <span class="message-share-tmx-openemailpopup">'+ (users.length - 1) +' others</span>';
                    exportDiv = '<td class="share-tmx-container" style="display: none">' +
                        '<div class="message-share-tmx">Shared resource ' +
                        'is co-owned by you, <span class="message-share-tmx-email message-share-tmx-openemailpopup">'+ users[0].first_name + ' ' + users[0].last_name +'</span>  ' +
                        totalShareUsers +
                        '</div>' +
                        '<input class="message-share-tmx-input-email" placeholder="Enter email addresses separated by comma" type="email" multiple/>'+
                        '<a class="pull-right btn-orange-small cancelsharetmx"><span class="text"></span>   </a>' +
                        '<div class="pull-right btn-ok share-button">Share</div>'+
                        '</td>';
                } else {
                    return false;
                }


                tr.append(exportDiv);
                tr.find('.share-tmx-container').slideToggle();

                //If still not shared dont create the popup
                if ( users.length === 0 ) return;

                var description = tr.find('.edit-desc').data('descr');

                if (description.length) {
                    description = "<span class='share-popup-description'>"+description+"</span>";
                }

                //Create the users list with the logged user first
                var htmlUsersList = '<div class="share-popup-list-item">'+
                    '<span class="share-popup-item-name">'+user.first_name + ' ' + user.last_name + ' (you)</span>'+
                    '<span class="share-popup-item-email">'+ user.email+ '</span>'+
                    '</div>';
                users.forEach(function (item) {
                    htmlUsersList = htmlUsersList +
                        '<div class="share-popup-list-item">'+
                        '<span class="share-popup-item-name">'+item.first_name + ' ' + item.last_name + '</span>'+
                        '<span class="share-popup-item-email">'+ item.email+ '</span>'+
                        '</div>';
                });

                var message = "<div class='share-popup-container'>" +
                    "<div class='share-popup-top'>" +
                    "<h3 class='popup-tm pull-left'>Share ownership of the resource: <br />"+
                    description + " - <span class='share-popup-key'>" + key + "</span>" +
                    "</h3>"+
                    "</div>"+
                    "<div class='share-popup-container-bottom'>" +
                    "<p>This action cannot be undone.</p>"+  
                    "<div class='share-popup-copy-result'></div>"+                        
                    "<input class='share-popup-container-input-email' placeholder='Enter email addresses separated by comma'>"+
                    "<div class='pull-right btn-confirm-medium share-button share-button-popup'>Share</div>"+
                    "<div class='share-popup-input-result'></div>"+
                    "</div>"+
                    "</div>"+
                    "<div class='share-popup-container-list'>" +
                    "<h3 class='popup-tm'>Who owns the resource</h3>"+
                    "<div class='share-popup-list'>" +
                    htmlUsersList +
                    "</div>"+
                    "</div>";
                tr.find('.message-share-tmx-openemailpopup').on("click", function () {
                    APP.confirm({
                        type: 'share_key_popup',
                        name: 'share-window',
                        cancelTxt: 'Cancel',
                        msg: message,
                        title: 'Share resource'
                    });

                    $('.share-popup-copy-link-button').data("powertip", "<div style='line-height: 20px;font-size: 15px;'>Click to copy to clipboard</div>");
                    $('.share-popup-copy-link-button').powerTip({
                        placement : 'n',
                        popupId : "matecatTip",
                    });
                });

            });

        },
        openExportGlossary: function (elem, tr) {
            tr.find('.action a').addClass('disabled');
            var exportDiv = '<td class="download-glossary-container" style="display: none">' +
                '<div class="message-export-glossary">We are exporting the glossary. Please wait...</div>' +
                '<span class="message-glossary-export-completed">Export Completed</span>' +
                '<span class="message-glossary-export-error">Export failed. No glossary found in the resource.</span>' +
                '<span class="uploadloader"></span>'+
                '</td>';

            tr.append(exportDiv);
            tr.find('.uploadloader').show();
            tr.find('.download-glossary-container').slideToggle();
        },
        startExportTmx: function (elem) {
            var line = $(elem).closest('tr');
            var email = line.find('.email-export-tmx').val();
            var successText = 'You should receive the link at ' + email + ' in <strong>%XX% minutes.</strong>';

            line.find('.uploadloader').show();
            line.find('.export-tmx-button, .canceladd-export-tmx').addClass('disabled');
            UI.downloadTM( line, email ).done(function (response) {
                if (response.errors.length == 0 && !response.data.error) {
                    var time = Math.round(response.data.estimatedTime / 60);
                    time = (time > 0 ) ? time : 1;
                    successText = successText.replace('%XX%', time);
                    setTimeout(function () {
                        line.find('.message-export-tmx-success').html(successText);
                        line.find('.uploadloader').hide();
                        line.find('.export-tmx-button, .canceladd-export-tmx, .email-export-tmx').hide();
                        line.find('.message-export-tmx').hide();
                        line.find('.message-export-tmx-success, .email-export-tmx-email-sent').show();
                        setTimeout(function () {
                            UI.closeExportTmx(line);
                        }, 5000);
                    }, 3000);
                } else {
                    setTimeout(function () {
                        line.find('.uploadloader').hide();
                        line.find('.export-tmx-button').hide();
                        line.find('.action a').removeClass('disabled');
                        line.find('.email-export-tmx-email-error').show();
                    }, 2000);
                }
            });

        },
        closeExportTmx: function (elem) {
            $(elem).find('td.download-tmx-container').slideToggle(function () {
                $(this).remove();
            });
            $(elem).find('.action a').removeClass('disabled');
        },
        addFormUpload: function (elem, type) {
            var label, format;
            if (type == 'tmx') {
                label = '<p class="pull-left">Select TMX file to import</p>';
                format = '.tmx';
            } else if (type == 'glossary') {
                label = '<p class="pull-left">Select glossary in XLSX format ' +
                        '   <a href="http://www.matecat.com/support/managing-language-resources/add-glossary/" target="_blank">(How-to)</a>' +
                        '</p>';
                format = '.xlsx,.xls';
            }
            $(elem).closest("tr").find('.action a').addClass('disabled');
            var nr = '<td class="uploadfile" style="display: none">' +
                label +
                '<form class="existing add-TM-Form pull-left" action="/" method="post">' +
                '    <input type="submit" class="addtm-add-submit" style="display: none" />' +
                '    <input type="file" name="uploaded_file" accept="'+ format +'"/>' +
                '</form>' +
                '   <a class="pull-right btn-grey canceladd'+ type +'">' +
                '      <span class="text"></span>' +
                '   </a>' +
                '   <a class="existingKey pull-right btn-ok add'+ type +'file">' +
                '       <span class="text">Confirm</span>' +
                '   </a>' +
                '   <span class="upload-file-msg-error"></span>' +
                '   <span class="upload-file-msg-success">Import Complete</span>' +
                '  <div class="uploadprogress">' +
                '       <span class="msgText">Uploading</span>' +
                '       <span class="progress">' +
                '           <span class="inner"></span>' +
                '       </span>' +
                '  </div>' +
                '</td>';

            $(elem).parents('tr').append(nr);
            $(elem).parents('tr').find('.uploadfile').slideToggle();
        },
        showErrorOnActiveTMTable: function (message) {
            $('.mgmt-container .active-tm-container .tm-error-message').html(message).fadeIn(100);
        },
        showErrorOnInactiveTMTable: function (message) {
            $('.mgmt-container .inactive-tm-container .tm-error-message').html(message).fadeIn(100);
        },
        showWarningOnActiveTMTable: function (message) {
            $('.mgmt-container .active-tm-container .tm-warning-message').html(message).fadeIn(100);
        },
        showWarningOnInactiveTMTable: function (message) {
            $('.mgmt-container .inactive-tm-container .tm-warning-message').html(message).fadeIn(100);
        },
        showSuccessOnActiveTMTable: function (message) {
            $('.mgmt-container .active-tm-container .tm-success-message').html(message).fadeIn(100);
        },
        showSuccessOnInactiveTMTable: function (message) {
            $('.mgmt-container .inactive-tm-container .tm-success-message').html(message).fadeIn(100);
        },
        hideAllBoxOnTables: function () {
            $('.mgmt-container .active-tm-container .tm-error-message, .mgmt-container .active-tm-container .tm-warning-message, .mgmt-container .active-tm-container .tm-success-message,' +
                '.mgmt-container .inactive-tm-container .tm-error-message, .mgmt-container .inactive-tm-container .tm-warning-message, .mgmt-container .inactive-tm-container .tm-success-message,' +
                '.mgmt-table-mt .tm-error-message').fadeOut(0, function () {
                $(this).html("");
            });
        },

        initOptionsTip: function () {

            var guesstagText = "<div class='powerTip-options-tm'><div class='powerTip-options-tm-title'>​​Supported bilingual languages pairs </br>(i.e. works for English to German and German to English):</div>" +
                "<ul>";

            for (var key in config.tag_projection_languages) {
                guesstagText = guesstagText + "<li class='powerTip-options-tm-list'>"+ config.tag_projection_languages[key].replace("-", "<>") +"</li>"
            }
            guesstagText = guesstagText + "</ul></div>";

            $(".tooltip-guess-tags").data("powertip", guesstagText);
            $(".tooltip-guess-tags").powerTip({
                placement : 's',
                popupId : "matecatTip",
                mouseOnToPopup: true

            });

            var acceptedLanguagesLXQ = config.lexiqa_languages.slice();
            var lexiqaText = "<div class='powerTip-options-tm'><div class='powerTip-options-tm-title'>Any combination of the supported languages:</div>" +
                "<ul>";
            acceptedLanguagesLXQ.forEach(function (elem) {
                var name = config.languages_array.find(function (e) {
                    return e.code === elem;
                }).name;
                lexiqaText = lexiqaText + "<li class='powerTip-options-tm-list'>" + name + "</li>";
            });
            lexiqaText = lexiqaText + "</ul></div>";

            $(".tooltip-lexiqa").data("powertip", lexiqaText);
            $(".tooltip-lexiqa").powerTip({
                placement : 's',
                popupId : "matecatTip",
                mouseOnToPopup: true

            });
        },

        initTmxTooltips: function () {
            //Description input
            if (config.isLoggedIn) {
                $('.edit-desc').data("powertip", "<div style='line-height: 18px;font-size: 15px;'>Rename</div>");
                $('.edit-desc').powerTip({
                    placement: 's',
                    popupId: "matecatTip",
                });

                $('.icon-owner-private').data("powertip", "<div style='line-height: 18px;font-size: 15px;'>Private resource, only available to you.<br/>Click to share.</div>");
                $('.icon-owner-private').powerTip({
                    placement : 's',
                    popupId : "matecatTip",
                });
            } else {
                $('.icon-owner-private').data("powertip", "<div style='line-height: 18px;font-size: 15px;'>To retrieve resource information or share it <br/>you must be logged.<br/></div>");
                $('.icon-owner-private').powerTip({
                    placement : 's',
                    popupId : "matecatTip",
                });
            }



            $('.icon-owner-public').data("powertip", "<div style='line-height: 20px;font-size: 15px;'>Public translation memory.</div>");
            $('.icon-owner-public').powerTip({
                placement : 's',
                popupId : "matecatTip",
            });

            $('.icon-owner-shared').data("powertip", "<div style='line-height: 20px;font-size: 15px;'>Shared resource.<br>Click to see who owns the resource.</div>");
            $('.icon-owner-shared').powerTip({
                placement : 's',
                popupId : "matecatTip",
            });
            var mymemoryChecks = $('#activetm tr.mymemory .activate div, #activetm tr.mymemory .lookup div, #activetm tr.mymemory .update div');
            mymemoryChecks.data("powertip", "<div style='line-height: 20px;font-size: 15px;'>Settings for MyMemory cannot be changed manually.</div>");
            mymemoryChecks.powerTip({
                placement : 's',
                popupId : "matecatTip",
            });


        },

        setLanguageTooltipTP: function () {
            var gtTooltip = $(".tooltip-guess-tags").data("powertip");
            $(".tagp .onoffswitch-container").data("powertip", gtTooltip);
            $(".tagp .onoffswitch-container").powerTip({
                placement : 's',
                popupId : "matecatTip",
                mouseOnToPopup: true
            });
        },

        setLanguageTooltipLXQ: function () {
            var lxTooltip = $(".tooltip-lexiqa").data("powertip");

            $(".qa-box .onoffswitch-container").data("powertip", lxTooltip);
            $(".qa-box .onoffswitch-container").powerTip({
                placement : 's',
                popupId : "matecatTip",
                mouseOnToPopup: true
            });
        },

        removeTooltipTP: function () {
            $('.qa-box .onoffswitch-container').powerTip('destroy');
            $('.tagp .onoffswitch-container').powerTip('destroy');
        },

        removeTooltipLXQ: function () {
            $('.qa-box .onoffswitch-container').powerTip('destroy');
            $('.tagp .onoffswitch-container').powerTip('destroy');
        },

        checkTMKeysUpdateChecks: function () {
            var updateCheck = $("#activetm").find("tr:not(.mymemory, .new)  .update input:checked").length;
            if ( updateCheck  === 0) {
                $("#activetm").find("tr.mymemory .update input").prop('checked', true);
            } else {
                $("#activetm").find("tr.mymemory .update input").prop('checked', false);
            }
        },
        clickOnShareButton(button) {
            var tr = button.closest('tr');
            var key = ( tr.length ) ? tr.data('key') : button.closest('.share-popup-container').find('.share-popup-key').text();
            var descr = ( tr.length ) ? tr.find('.edit-desc').data('descr') : button.closest('.share-popup-container').find('.share-popup-description').text();
            var msg = 'The resource <span style="font-weight: bold">' + descr + '(' + key + ')' +'</span> has been shared.';

            var validateEmail = function(emails) {
                var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                var result = true;
                emails.split(',').forEach(function (email) {
                   if ( !re.test(email) )
                       result = email;
                });
                return result;
            };

            var emails = (button.hasClass('share-button-popup')) ? button.closest('.share-popup-container-bottom').find('.share-popup-container-input-email').val()
                : button.closest('.share-tmx-container').find('.message-share-tmx-input-email').val();

            var validateReturn = validateEmail(emails);

            if ( validateReturn !== true ) {
                var errorMsg = 'The email <span style="font-weight: bold">'+ validateReturn +'</span> is not valid.';
                if (button.hasClass('share-button-popup')) {
                    $('.share-popup-input-result').html(errorMsg);
                    $('.share-popup-container-input-email').addClass('error');
                } else {
                    if (tr.closest('table').attr("id") == 'inactivetm'){
                        UI.showErrorOnInactiveTMTable(errorMsg);
                    }else {
                        UI.showErrorOnActiveTMTable(errorMsg);
                    }
                    tr.find('.message-share-tmx-input-email').addClass('error');
                }
                return;
            }

            UI.shareKeyByEmail(emails, key).done(function (response) {
                UI.hideAllBoxOnTables();
                if (response.errors.length === 0) {
                    if (button.hasClass('share-button-popup')) {
                        APP.closePopup();
                        UI.showSuccessOnActiveTMTable(msg);
                        $('.share-tmx-container').slideToggle(function () {
                            $(this).remove();
                        });
                    } else {
                        button.closest('.share-tmx-container').find('.cancelsharetmx').click();
                        if ( tr.closest('table').attr("id") == 'inactivetm'){
                            UI.showSuccessOnInactiveTMTable(msg);
                        }else {
                            UI.showSuccessOnActiveTMTable(msg);
                        }
                    }


                    setTimeout(function () {
                        UI.hideAllBoxOnTables();
                    }, 4000)
                } else {
                    var errorMsg = response.errors[0].message;
                    if (button.hasClass('share-button-popup')) {
                        $('.share-popup-input-result').text(errorMsg);
                        $('.share-popup-container-input-email').addClass('error');
                    } else {
                        if (tr.closest('table').attr("id") == 'inactivetm'){
                            UI.showErrorOnInactiveTMTable(errorMsg);
                        }else {
                            UI.showErrorOnActiveTMTable(errorMsg);
                        }
                        tr.find('.message-share-tmx-input-email').addClass('error');
                    }
                }
            });

        },
        /**
         * Share a key to one or more email, separated by comma
         * @param container
         */
        shareKeyByEmail: function (emails, key) {
            return APP.doRequest({
                data: {
                    action: 'userKeys',
                    exec: 'share',
                    key: key,
                    emails: emails
                },
                error: function() {
                    UI.showErrorOnActiveTMTable('There was a problem sharing the key, try again or contact the support.');
                }
            });
        }

    });
})(jQuery);