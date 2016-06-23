/*
 Component: tm
 Created by andreamartines on 02/10/14.
 Loaded by cattool and upload page.
 */

$.extend(UI, {

    initTM: function() {

// script per lo slide del pannello di manage tmx
        UI.setDropDown();
        
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
            };
        });
        $("#mt_engine_int").change(function() {
            $('#add-mt-provider-cancel').hide();
            $('#mt-provider-details .error').empty();

            $(".insert-tm").show();
            provider = $(this).val();
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
            $(this).hide();
//            $('.add-mt-provider-cancel-int').click();
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

        $(".mgmt-tm .new .privatekey .btn-ok").click(function(e) {
            e.preventDefault();
            //prevent double click

            if($(this).hasClass('disabled')) return false;
            $(this).addClass('disabled');
            $('#new-tm-key').attr('disabled','disabled');

            //call API
            APP.doRequest( {
                data: {
                    action: 'createRandUser'
                },
                success: function ( d ) {
                    data = d.data;
                    //put value into input field
                    $('#new-tm-key').val(data.key);
                    $('#activetm tr.new').removeClass('badkey');
                    $('#activetm tr.new .error .tm-error-key').text('').hide();
                    UI.checkTMAddAvailability();
                    return false;
                }
            } );

        });

        // script per fare apparire e scomparire la riga con l'upload della tmx
        $('body').on('click', 'tr.mine a.canceladdtmx, tr.ownergroup a.canceladdtmx, #inactivetm tr.new .action .addtmxfile', function() {

            $(this).parents('tr').find('.action .addtmx').removeClass('disabled');
            $(this).parents('td.uploadfile').remove();

        }).on('click', '#activetm tr.uploadpanel a.canceladdtmx, #inactivetm tr.uploadpanel a.canceladdtmx', function() {
            $('#activetm tr.uploadpanel, #inactivetm tr.uploadpanel').addClass('hide');
            $('#activetm tr.new .action .addtmxfile, #inactivetm tr.new .action .addtmxfile').removeClass('disabled');
        }).on('mousedown', '.addtmx:not(.disabled)', function(e) {
            e.preventDefault();
            $(this).parents('.action').find('.addtmx').each( function(el) { $(this).addClass('disabled'); } );
            var nr = '<td class="uploadfile">' +
                    '<form class="existing add-TM-Form pull-left" action="/" method="post">' +
                    '    <input type="hidden" name="action" value="loadTMX" />' +
                    '    <input type="hidden" name="exec" value="newTM" />' +
                    '    <input type="hidden" name="tm_key" value="" />' +
                    '    <input type="hidden" name="name" value="" />' +
                    '    <input type="submit" class="addtm-add-submit" style="display: none" />' +
                    '    <input type="file" name="tmx_file" />' +
                    '</form>' +
                     '  <a class="pull-left btn-grey canceladdtmx">' +
                     '      <span class="text">Cancel</span>' +
                     '  </a>' +
                    '   <a class="existingKey pull-left btn-ok addtmxfile">' +
                    '       <span class="text">Confirm</span>' +
                    '   </a>' +
                    '   <span class="error"></span>' +
                    '  <div class="uploadprogress">' +
                    '       <span class="progress">' +
                    '           <span class="inner"></span>' +
                    '       </span>' +
                    '       <span class="msgText">Uploading</span>' +
                    '       <span class="error"></span>' +
                    '  </div>' +
                    '</td>';

            $(this).parents('tr').append(nr);

        }).on('change paste', '#new-tm-key', function(event) {
            // set Timeout to get the text value after paste event, otherwise it is empty
            setTimeout( function(){ UI.checkTMKey('change'); }, 200 );
        }).on('click', '.mgmt-tm tr.new a.uploadtm:not(.disabled)', function() {

            UI.checkTMKey('key');
            UI.saveTMkey($(this));

            // script per appendere le tmx fra quelle attive e inattive, preso da qui: https://stackoverflow.com/questions/24355817/move-table-rows-that-are-selected-to-another-table-javscript
        }).on('click', 'tr.mine .uploadfile .addtmxfile:not(.disabled), tr.ownergroup .uploadfile .addtmxfile:not(.disabled)', function() {

            $(this).addClass('disabled');
            $(this).parents('.uploadfile').find('.error').text('').hide();

            UI.execAddTM(this);

        }).on('click', '.mgmt-tm tr.mine td.description .edit-desc', function() {

            $('.mgmt-tm .edit-desc[contenteditable=true]').blur();
            $('#activetm tr.mine td.description .edit-desc:not(.current)').removeAttr('contenteditable');

            $(this).attr('contenteditable', true);

        }).on('blur', '#activetm td.description .edit-desc', function() {

            $(this).removeAttr('contenteditable');
            UI.saveTMdata(false);

        }).on('blur', '#inactivetm td.description .edit-desc', function() {
            $(this).removeAttr('contenteditable');

            UI.saveTMdescription($(this));

        }).on('keydown', '.mgmt-tm td.description .edit-desc', 'return', function(e) {

            if(e.which == 13) {
                e.preventDefault();
                $(this).trigger('blur');
            }

        }).on('click', '.mgmt-mt td.engine-name .edit-desc', function() {

            $('.mgmt-mt .edit-desc[contenteditable=true]').blur();
            $(this).attr('contenteditable', true);

        }).on('blur', '.mgmt-mt td.engine-name .edit-desc', function() {

            $(this).removeAttr('contenteditable');

        }).on('keydown', '.mgmt-mt td.engine-name .edit-desc', 'return', function(e) {
            e.preventDefault();
            $(this).trigger('blur');
        }).on('click', '#activetm tr.uploadpanel .uploadfile .addtmxfile:not(.disabled)', function() {

            $(this).addClass('disabled');
            UI.execAddTM(this);

        }).on('click', '.popup-tm h1 .btn-ok', function(e) {
            e.preventDefault();
            UI.saveTMdata(true);
        }).on('click', '#activetm tr.new a.addtmxfile:not(.disabled)', function() {
            console.log('upload file');
            UI.checkTMKey('tm');

            $('#activetm tr.uploadpanel').removeClass('hide');
            $(this).addClass('disabled');
        }).on('click', 'a.disabletm', function() {
            UI.disableTM(this);
        }).on('change', '.mgmt-table-tm tr.mine .lookup input, .mgmt-table-tm tr.mine .update input', function() {

            if(APP.isCattool) UI.saveTMdata(false);
            UI.checkTMGrantsModifications(this);

        }).on('click', '.mgmt-table-mt tr .enable-mt input', function() {

            if($(this).is(':checked')) {
                UI.activateMT(this);
            } else {
                UI.deactivateMT(this);
            }

        }).on('click', '.mgmt-table-mt tr .action .deleteMT', function() {

            $('.mgmt-table-mt .tm-warning-message').html('Do you really want to delete this MT? <a href="#" class="continueDeletingMT" data-id="' + $(this).parents('tr').attr('data-id') + '">Continue</a>').show();

        }).on('click', '.continueDeletingMT', function(e){
            e.preventDefault();
            UI.deleteMT($('.mgmt-table-mt table.mgmt-mt tr[data-id="' + $(this).attr('data-id') + '"] .deleteMT'));
            $('.mgmt-table-mt .tm-warning-message').empty().hide();
        }).on('click', 'a.usetm', function() {
            UI.useTM(this);
        }).on('change', '#new-tm-read, #new-tm-write', function() {
            UI.checkTMgrants();
        }).on('change', 'tr.mine td.uploadfile input[type="file"], tr.ownergroup td.uploadfile input[type="file"]', function() {
            if(this.files[0].size > config.maxTMXFileSize) {
                numMb = config.maxTMXFileSize/(1024*1024);
                APP.alert('File is too big.<br/>The maximuxm size allowed is ' + numMb + 'MB.');
                return false;
            };
            if($(this).val() == '') {
                $(this).parents('.uploadfile').find('.addtmxfile').hide();
            } else {
                $(this).parents('.uploadfile').find('.addtmxfile').show();
            }
        }).on('change', '.mgmt-tm tr.uploadpanel td.uploadfile input[type="file"]', function() {
            if($(this).val() == '') {
                $(this).parents('.uploadfile').find('.addtmxfile').hide();
            } else {
                $(this).parents('.uploadfile').find('.addtmxfile').show();
            }
        }).on('keyup', '#filterInactive', function() {
            if($(this).val() == '') {
                $('#inactivetm').removeClass('filtering');
                $('#inactivetm tbody tr.found').removeClass('found');
            } else {
                $('#inactivetm').addClass('filtering');
                UI.filterInactiveTM($('#filterInactive').val());
            }
        }).on('mousedown', '.mgmt-tm .downloadtmx', function(){
            if($(this).hasClass('downloading')) return false;
           UI.downloadTM( $(this).parentsUntil('tbody', 'tr'), 'downloadtmx' );
            $(this).addClass('disabled' );
            $(this).prepend('<span class="uploadloader"></span>');
            var msg = '<span class="notify"><span class="uploadloader"></span> Downloading TMX... ' + ((APP.isCattool)? 'You can close the panel and continue translating.' : 'This can take a few minutes.')+ '</span>';
            $(this).parents('td').first().append(msg);
        }).on('mousedown', '.mgmt-tm .deleteTM', function(){
            UI.deleteTM($(this));
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
                        sorter: false
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

        });

        $(".add-mt-engine").click(function() {

            $(this).hide();
            console.log('ADD MT ENGINE');
            UI.resetMTProviderPanel();
            $(".mgmt-table-mt tr.new").removeClass('hide').show();

        });
        $(".mgmt-table-tm .add-tm").click(function() {
            $(this).hide();
            $(".mgmt-table-tm tr.new").removeClass('hide').show();
        });
        $(".mgmt-tm tr.new .canceladdtmx").click(function() {
            $("#activetm tr.new").hide();
            $("#activetm tr.new .addtmxfile").removeClass('disabled');
            $("#activetm tr.uploadpanel").addClass('hide');
            $(".mgmt-table-tm .add-tm").show();
            UI.clearAddTMRow();
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
    openLanguageResourcesPanel: function(tab, elToClick) {
        if ($(".popup-tm").hasClass('open') ) {
            return false;
        }
        tab = tab || 'tm';
        elToClick = elToClick || null;
        $('body').addClass('side-popup');
        $(".popup-tm").addClass('open').show("slide", { direction: "right" }, 400);
        $("#SnapABug_Button").hide();
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
        console.log('checkTMKey');
        console.log('operation: ', operation);

        //check if the key already exists, it can not be sent nor added twice
        var keys_of_the_job = $('#activetm tbody tr:not(".new") .privatekey' );
        var keyIsAlreadyPresent = false;
        $( keys_of_the_job ).each( function( index, value ){
            if( $(value).text().slice(-5) == $('#new-tm-key').val().slice(-5) ){
                console.log('key is bad');
                $('#activetm tr.new').addClass('badkey');
                $('#activetm tr.new .error .tm-error-key').text('The key is already present in this project.').show();
                $('#activetm tr.new .error').show();
                UI.checkTMAddAvailability(); //some enable/disable stuffs
                keyIsAlreadyPresent = true;
                return false;
            }
        } );
        if( keyIsAlreadyPresent ){ return false; }
        //check if the key already exists, it can not be sent nor added twice

        APP.doRequest({
            data: {
                action: 'ajaxUtils',
                exec: 'checkTMKey',
                tm_key: $('#new-tm-key').val()
            },
            context: operation,
            error: function() {
                console.log('checkTMKey error!!');
            },
            success: function(d) {
                if(d.success === true) {
                    console.log('key is good');
                    console.log('adding a tm');
                    $('#activetm tr.new').removeClass('badkey');
                    $('#activetm tr.new .error .tm-error-key').text('').hide();
                    $('#activetm tr.new .error').hide();
                    UI.checkTMAddAvailability();

                    if(this == 'key') {
                        UI.addTMKeyToList(false);
                        UI.clearTMUploadPanel();
                    } else {

                    }

                } else {
                    console.log('key is bad');
                    $('#activetm tr.new').addClass('badkey');
                    $('#activetm tr.new .error .tm-error-key').text('The key is not valid').show();
                    $('#activetm tr.new .error').show();
                    UI.checkTMAddAvailability();
                }
            }
        });
    },
    checkTMAddAvailability: function () {
        console.log('checkTMAddAvailability');
        if(($('#activetm tr.new').hasClass('badkey'))||($('#activetm tr.new').hasClass('badgrants'))) {
            $('#activetm tr.new .uploadtm').addClass('disabled');
            $('#activetm tr.uploadpanel .addtmxfile').addClass('disabled');
        } else {
            $('#activetm tr.new .uploadtm').removeClass('disabled');
            $('#activetm tr.uploadpanel .addtmxfile').removeClass('disabled');

        }
    },

    checkTMgrants: function() {
        console.log('checkTMgrants');
        panel = $('.mgmt-tm tr.new');
        var r = ($(panel).find('.r').is(':checked'))? 1 : 0;
        var w = ($(panel).find('.w').is(':checked'))? 1 : 0;
        if(!r && !w) {
            console.log('checkTMgrants NEGATIVE');
            $('#activetm tr.new').addClass('badgrants');
            $(panel).find('.action .error .tm-error-grants').text('Either "Lookup" or "Update" must be checked').show();
            UI.checkTMAddAvailability();

            return false;
        } else {
            console.log('checkTMgrants POSITIVE');
            $('#activetm tr.new').removeClass('badgrants');
            $(panel).find('.action .error .tm-error-grants').text('').hide();
            UI.checkTMAddAvailability();

            return true;
        }
    },
    checkTMGrantsModifications: function (el) {
        console.log('el: ', el);
        tr = $(el).parents('tr.mine');
        isActive = ($(tr).parents('table').attr('id') == 'activetm')? true : false;
        if((!tr.find('.lookup input').is(':checked')) && (!tr.find('.update input').is(':checked'))) {
            if(isActive) {
                if(config.isAnonymousUser) {

                    var data = {
                        grant: ($(el).parents('td').hasClass('lookup')? 'lookup' : 'update'),
                        key: $(tr).find('.privatekey').text()
                    }

                    APP.confirm({
                        name: 'confirmTMDisable',
                        cancelTxt: 'Cancel',
                        onCancel: 'cancelTMDisable',
                        callback: 'continueTMDisable',
                        okTxt: 'Continue',
//                        context: ($(el).parents('td').hasClass('lookup')? 'lookup' : 'update'),
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
        options = $.parseJSON(context);
        $('.mgmt-tm tr.mine[data-key="' + options.key + '"] td.' + options.grant + ' input').click();
    },
    continueTMDisable: function (context) {
        options = $.parseJSON(context);
        el = $('.mgmt-tm tr.mine[data-key="' + options.key + '"] td.' + options.grant + ' input');
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

        row.css('display', 'block');

        //update datatable struct
        // draw the user's attention to it
        row.fadeOut();
        row.fadeIn();

        $('.addtmxrow').hide();
    },

    execAddTM: function(el) {

        table = $(el).parents('table');
        existing = ($(el).hasClass('existingKey'))? true : false;
        if(existing) {
            $(el).parents('.uploadfile').addClass('uploading');
        } else {
            $(table).find('tr.uploadpanel .uploadfile').addClass('uploading');
        }
        if(existing) {
            if($(el).parents('tr').hasClass('mine')) {
                trClass = 'mine';
            } else {
                trClass = 'ownergroup';
            }
        } else {
            trClass = 'uploadpanel';
        }

        form = $(table).find('tr.' + trClass + ' .add-TM-Form')[0];
        path = $(el).parents('.uploadfile').find('input[type="file"]').val();
        file = path.split('\\')[path.split('\\').length-1];
        this.TMFileUpload(form, '/?action=loadTMX','uploadCallback', file);

    },
    addTMKeyToList: function ( uploading ) {

        var keyParams = {
            r: $( '#new-tm-read' ).is( ':checked' ),
            w: $( '#new-tm-write' ).is( ':checked' ),
            desc: $( '#new-tm-description' ).val(),
            TMKey: $( '#new-tm-key' ).val()
        };

        this.appendNewTmKeyToPanel( keyParams );

        if ( uploading ) {
            $( '.mgmt-tm tr.new' ).addClass( 'hide' );
        } else {
            $( '.mgmt-tm tr.new .canceladdtmx' ).click();
        }

        UI.pulseTMadded( $( '#activetm tr.mine' ).last() );

        if ( APP.isCattool ) UI.saveTMdata( false );
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
            '    <td class="privatekey">' + keyParams.TMKey + '</td>' +
            '    <td class="owner">You</td>' +
            '    <td class="description"><div class="edit-desc">' + keyParams.desc + '</div></td>' +
            '    <td class="lookup check text-center"><input type="checkbox"' + ( keyParams.r ? ' checked="checked"' : '' ) + ' /></td>' +
            '    <td class="update check text-center"><input type="checkbox"' + ( keyParams.w ? ' checked="checked"' : '' ) + ' /></td>' +
            '    <td class="action">' +
            '       <a class="btn pull-left addtmx"><span class="text">Import TMX</span></a>'+
            '          <div class="wrapper-dropdown-5 pull-left" tabindex="1">&nbsp;'+
            '              <ul class="dropdown pull-left">' +
            '                   <li><a class="downloadtmx" title="Export TMX" alt="Export TMX"><span class="icon-download"></span>Export TMX</a></li>'+
            '                  <li><a class="deleteTM" title="Delete TMX" alt="Delete TMX"><span class="icon-trash-o"></span>Delete TM</a></li>'+
            '              </ul>'+
            '          </div>'+
            '</td>' +
            '</tr>';

        $('#activetm').find('tr.new').before( newTr );

        UI.setTMsortable();
        UI.updateTMAddedMsg();

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
        $('.mgmt-tm tr.new .privatekey .btn-ok').removeClass('disabled');
        $('#new-tm-read, #new-tm-write').prop('checked', true);
    },
    clearAddTMRow: function() {
        $('#new-tm-description').val('');
        $('#new-tm-key').removeAttr('disabled');
        $('.mgmt-tm tr.new .privatekey .btn-ok').removeClass('disabled');
        $('#activetm .fileupload').val('');
        $('.mgmt-tm tr.new').removeClass('badkey badgrants');
        $('.mgmt-tm tr.new .message').text('');
        $('.mgmt-tm tr.new .error span').text('').hide();
        $('.mgmt-tm tr.new .addtmxfile').show();
    },
    clearTMPanel: function () {
        $('.mgmt-container .tm-error-message').hide();
        $('.mgmt-container .tm-warning-message').hide();
        $('#activetm .edit-desc').removeAttr('contenteditable');
        $('#activetm td.action .addtmx').removeClass('disabled');
        $('#activetm tr.new .canceladdtmx').click();
    },

    TMFileUpload: function(form, action_url, div_id, tmName) {
        // Create the iframe...
        ts = new Date().getTime();
        ifId = "upload_iframe-" + ts;
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
        UI.TMuploadIframeId = iframeId;

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

        }

        if (iframeId.addEventListener) iframeId.addEventListener("load", eventHandler, true);
        if (iframeId.attachEvent) iframeId.attachEvent("onload", eventHandler);
        existing = ($(form).hasClass('existing'))? true : false;
        if(existing) {
            TR = $(form).parents('tr');
            TMKey = TR.find('.privatekey').first().text();
        } else {
            TMKey = $('#new-tm-key').val();
        }

        // Set properties of form...
        form.setAttribute("target", "upload_iframe");
        form.setAttribute("action", action_url);
        form.setAttribute("method", "post");
        form.setAttribute("enctype", "multipart/form-data");
        form.setAttribute("encoding", "multipart/form-data");
        $(form).append('<input type="hidden" name="exec" value="newTM" />')
            .append('<input type="hidden" name="tm_key" value="' + TMKey + '" />')
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
        TMPath = (existing)? $(form).find('input[type="file"]').val(): $('.mgmt-tm tr.uploadpanel td.uploadfile input[type="file"]').val();
        TMName = TMPath.split('\\')[TMPath.split('\\').length-1];

        TRcaller = (existing)? $(form).parents('.uploadfile') : $('#activetm .uploadpanel .uploadfile');
        TRcaller.addClass('startUploading');
        if(!existing) {
            UI.addTMKeyToList(true);

        }

        setTimeout(function() {
            UI.pollForUploadCallback(TMKey, TMName, existing, TRcaller);
        }, 3000);

        return false;

    },
    pollForUploadCallback: function(TMKey, TMName, existing, TRcaller) {

        if($('#uploadCallback').text() != '') {
            msg = $.parseJSON($('#uploadCallback pre').text());
            if(msg.success === true) {
                setTimeout(function() {
                    //delay because server can take some time to process large file
                    TRcaller.removeClass('startUploading');
                    UI.pollForUploadProgress(TMKey, TMName, existing, TRcaller);
                }, 3000);
            } else {
                console.log('error');
                TRcaller.removeClass('startUploading');
                $(TRcaller).find('.error').text(msg.errors[0].message).show();
            }
        } else {
            setTimeout(function() {
                UI.pollForUploadCallback(TMKey, TMName, existing, TRcaller);
            }, 1000);
        }

    },
    pollForUploadProgress: function(TMKey, TMName, existing, TRcaller) {

        APP.doRequest({
            data: {
                action: 'loadTMX',
                exec: 'uploadStatus',
                tm_key: TMKey,
                name: TMName
            },
            context: [TMKey, TMName, existing, TRcaller],
            error: function() {
                existing = this[2];
                if(existing) {
                    console.log('error');
                } else {
                    $('#activetm tr.uploadpanel .uploadfile').removeClass('uploading');
                }
            },
            success: function(d) {

                console.log('progress success data: ', d);
                existing = this[2];
                TRcaller = this[3];

                if(d.errors.length) {
                    if(existing) {
                        console.log('error');
                        console.log($(TRcaller));
                        $(TRcaller).find('.error').text(d.errors[0].message).show();

                    } else {
                        $('#activetm tr.uploadpanel .uploadfile').removeClass('uploading');
                    }

                } else {

                    $(TRcaller).find('.uploadprogress .msgText').text('Uploading ' + this[1]);

                    $(TRcaller).find('.uploadprogress').show();

                    if(d.data.total == null) {
                        setTimeout(function() {
                            UI.pollForUploadProgress(TMKey, TMName, existing, TRcaller);
                        }, 1000);
                    } else {
                        if(d.completed) {
                            if(existing) {
                                var tr = $(TRcaller).parents('tr.mine');
                                $(tr).find('.addtmx').removeClass('disabled');
                                UI.pulseTMadded(tr);
                            }

                            $(TRcaller).find('.uploadprogress').hide();
                            $(TRcaller).find('.uploadprogress .msgText').text('Uploading');

                            if(existing) {

                                if( !tr.find('td.description .edit-desc').text() ){
                                    tr.find('td.description .edit-desc').text(TMName);
                                }

                                $(TRcaller).addClass('tempTRcaller').append('<span class="msg">Import Complete</span>');
                                setTimeout(function() {
                                    $('.tempTRcaller').remove();
                                }, 3000);
                                
                            } else {
                                $('.mgmt-tm tr.new .canceladdtmx').click();
                                $('.mgmt-tm tr.new').removeClass('hide');
                                $('#activetm tr.uploadpanel .uploadfile').removeClass('uploading');
                            }

                            UI.TMuploadIframeId.parentNode.removeChild(UI.TMuploadIframeId);

                            return false;
                        }
                        progress = (parseInt(d.data.done)/parseInt(d.data.total))*100;
                        $(TRcaller).find('.progress .inner').css('width', progress + '%');
                        setTimeout(function() {
                            UI.pollForUploadProgress(TMKey, TMName, existing, TRcaller);
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
        categories = ['ownergroup', 'mine', 'anonymous'];
        var newArray = {};
        $.each(categories, function (index, value) {
            data = UI.extractTMDataFromRowCategory(this);
            newArray[value] = data;
        });
        return JSON.stringify(newArray);
    },
    extractTMDataFromRowCategory: function(cat) {
        tt = $('#activetm tbody tr.' + cat);
        dataOb = [];
        $(tt).each(function () {
            r = (($(this).find('.lookup input').is(':checked'))? 1 : 0);
            w = (($(this).find('.update input').is(':checked'))? 1 : 0);
            if(!r && !w) {
                return true;
            }
            dd = {
                tm: $(this).attr('data-tm'),
                glos: $(this).attr('data-glos'),
                owner: $(this).attr('data-owner'),
                key: $(this).find('.privatekey').text().trim(), // remove spaces and unwanted chars from string
                name: $(this).find('.description').text().trim(),
                r: r,
                w: w
            }
            dataOb.push(dd);
        })
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
        if(!APP.isCattool) {
            UI.updateTMAddedMsg();
            return false;
        }

        data = this.extractTMdataFromTable();
        APP.doRequest({
            data: {
                action: 'updateJobKeys',
                job_id: config.job_id,
                job_pass: config.password,
                data: data
            },
            error: function() {

                console.log('Error saving TM data!!');

                $('.tm-error-message').text('There was an error saving your data. Please retry!').show();
                $('.popup-tm').removeClass('saving');

            },
            success: function(d) {
                $('.popup-tm').removeClass('saving');

                if(d.errors.length) {
                    APP.showMessage({msg: d.errors[0].message});
                } else {
                    console.log('TM data saved!!');

                }
            }
        });
    },
    saveTMdescription: function (field) {
        console.log(field);
        var tr = field.parents('tr').first();

        APP.doRequest({
            data: {
                action: 'userKeys',
                exec: 'update',
                key: tr.find('.privatekey').text(),
                description: field.text()
            },
            error: function() {
                console.log('Error saving TM description!!');
                APP.showMessage({msg: 'There was an error saving your description. Please retry!'});
                $('.popup-tm').removeClass('saving');
            },
            success: function(d) {
                $('.popup-tm').removeClass('saving');
                if(d.errors.length) {
                    APP.showMessage({msg: d.errors[0].message});
                } else {
                    console.log('TM description saved!!');
                }
            }
        });
    },
    saveTMkey: function (button) {
        APP.doRequest({
            data: {
                action: 'userKeys',
                exec: 'newKey',
                key: $('#new-tm-key').val(),
                description: $('#new-tm-description').val()
            },
            error: function() {
                console.log('Error saving TM key!');
                $('.popup-tm').removeClass('saving');
            },
            success: function(d) {
                $('.popup-tm').removeClass('saving');
                if(d.errors.length) {
//                    APP.showMessage({msg: d.errors[0].message});
                } else {
                    console.log('TM key saved!!');
                }
            }
        });
    },


    closeTMPanel: function () {
        $('.mgmt-tm tr.uploadpanel').hide();
        $( ".popup-tm").removeClass('open').hide("slide", { direction: "right" }, 400);
        $("#SnapABug_Button").show();
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
    downloadTM: function( tm, button_class ) {

        if ( !$( tm ).find( '.' + button_class ).hasClass( 'disabled' ) ) {

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
                        $( tm ).find( '.' + button_class ).removeClass( 'disabled' ).removeClass( 'downloading' );
                        $( tm ).find( 'span.notify' ).remove();
                        window.clearInterval( downloadTimer );
                        $.cookie( downloadToken, null, {path: '/', expires: -1} );
                        errorMsg = $( '#' + iFrameID ).contents().find( 'body' ).text();
                        errorKey = $( tm ).attr( 'data-key' );
                        if ( errorMsg != '' ) {
                            APP.alert( 'Error on downloading a TM with key ' + errorKey + ':<br />' + errorMsg );
                        }

                        $( '#' + iFrameID ).remove();
                    }

                }, 2000 );
            } );

            //create the html form and append a token for download
            var iFrameForm = $( document.createElement( 'form' ) ).attr( {
                'id': formID,
                'action': '/',
                'method': 'POST'
            } ).append(
                    //action to call
                    $( document.createElement( 'input' ) ).prop( {
                        type: 'hidden',
                        name: 'action',
                        value: 'downloadTMX'
                    } ),
                    //we tell to the controller to check a field in the post named downloadToken
                    // and to set a cookie named as it's value with it's value ( equals )
                    $( document.createElement( 'input' ) ).prop( {
                        type: 'hidden',
                        name: 'downloadToken',
                        value: downloadToken
                    } ),
                    //set other values
                    $( document.createElement( 'input' ) ).prop( {
                        type: 'hidden',
                        name: 'tm_key',
                        value: $( '.privatekey', tm ).text()
                    } )
            );

            if ( typeof config.id_job !== 'undefined' ){
                iFrameForm.append(
                    $( document.createElement( 'input' ) ).prop( {
                        type: 'hidden',
                        name: 'id_job',
                        value: config.id_job
                    } ),
                    $( document.createElement( 'input' ) ).prop( {
                        type: 'hidden',
                        name: 'password',
                        value: config.password
                    } )
                );
            }

            //append from to newly created iFrame and submit form post
            iFrameDownload.contents().find( 'body' ).append( iFrameForm );
            console.log( iFrameDownload.contents().find( "#" + formID ) );
            iFrameDownload.contents().find( "#" + formID ).submit();

        }

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
                console.log('Error deleting TM!!');
            },
            success: function(d) {

            }
        });
    },
    deleteMT: function (button) {
        id = $(button).parents('tr').first().attr('data-id');
        APP.doRequest({
            data: {
                action: 'engine',
                exec: 'delete',
                id: id
            },
            context: id,
            error: function() {
                console.log('error');
            },
            success: function(d) {
                console.log('success');
                $('.mgmt-table-mt tr[data-id=' + this + ']').remove();
                $('#mt_engine option[value=' + this + ']').remove();
                if(!$('#mt_engine option[selected=selected]').length) $('#mt_engine option[value=0]').attr('selected', 'selected');
            }
        });
    },

    addMTEngine: function (provider, providerName) {
        providerData = {};
        $('.insert-tm .provider-data .provider-field').each(function () {
            field = $(this).find('input, select').first();
            if (field.prop('type') === 'checkbox') {
                providerData[field.attr('data-field-name')] = field.prop('checked');
            } else {
                providerData[field.attr('data-field-name')] = field.val();
            }
        })

        name = $('#new-engine-name').val();
        data = {
            action: 'engine',
            exec: 'add',
            name: name,
            provider: provider,
            data: JSON.stringify(providerData)
        }
        context = data;
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
        newTR =    '<tr data-id="' + id + '">' +
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
        tr = $(el).parents('tr');
        $(el).replaceWith('<input type="checkbox" checked class="temp" />');
        cbox = tr.find('input[type=checkbox]');
        tbody = tr.parents('tbody');
        $(tbody).prepend(tr);
        tbody.find('.activemt input[type=checkbox]').replaceWith('<input type="checkbox" />');
        tbody.find('.activemt').removeClass('activemt');
        tr.addClass('activemt').removeClass('temp');
        $('#mt_engine option').removeAttr('selected');
        $('#mt_engine option[value=' + tr.attr('data-id') + ']').attr('selected', 'selected');
        UI.pulseMTadded($('.activemt').last());

    },
    deactivateMT: function (el) {
        tr = $(el).parents('tr');
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
            obj.dd.on( 'click', function ( event ) {
                $( this ).toggleClass( 'activeMenu' );
                event.preventDefault();
                if($( this ).hasClass( 'activeMenu' )) {
                    event.stopPropagation();
                }
            } );
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
    }
});