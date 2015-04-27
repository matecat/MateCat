/*
 Component: tm
 Created by andreamartines on 02/10/14.
 Loaded by cattool and upload page.
 */


$.extend(UI, {
    initTM: function() {
//        $('.popup-tm').height($(window).height());
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
            console.log('questo');
            $(this).addClass("active");
            $(".mgmt-mt").removeClass("active");
            $(".mgmt-table-mt").hide();
            $(".mgmt-table-tm").show();
        });
        $(".popup-tm .tm-mgmt").click(function(e) {
            e.preventDefault();
            $(".mgmt-mt").addClass("active");
            $(".mgmt-tm").removeClass("active");
            $(".mgmt-table-tm").hide();
            $(".mgmt-table-mt").show();
        });

        

        $(".mgmt-mt").click(function(e) {
            e.preventDefault();
            $(this).addClass("active");
            $(".mgmt-tm").removeClass("active");
            $(".mgmt-table-tm").hide();
            $(".mgmt-table-mt").show();
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
            provider = $("#mt_engine_int").val();
            providerName = $("#mt_engine_int option:selected").text();
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
            //$.get("https://mymemory.translated.net/api/createranduser", function(data){
            //    //parse to appropriate type
            //    //this is to avoid a curious bug in Chrome, that causes 'data' to be already an Object and not a json string
            //    if(typeof data == 'string'){
            //        data=jQuery.parseJSON(data);
            //    }
            //    //put value into input field
            //    $('#new-tm-key').val(data.key);
            //    $('.mgmt-tm .new .privatekey .btn-ok').removeClass('disabled');
            //    $('#activetm tr.new').removeClass('badkey');
            //    $('#activetm tr.new .error .tm-error-key').text('').hide();
            //    UI.checkTMAddAvailability();
            //    return false;
            //});

            //call API
            APP.doRequest( {
                data: {
                    action: 'createRandUser'
                },
                success: function ( d ) {
                    data = d.data;
                    //put value into input field
                    $('#new-tm-key').val(data.key);
//                    $('.mgmt-tm .new .privatekey .btn-ok').removeClass('disabled');
                    $('#activetm tr.new').removeClass('badkey');
                    $('#activetm tr.new .error .tm-error-key').text('').hide();
                    UI.checkTMAddAvailability();
                    return false;
                }
            } );

        });
        // script per fare apparire e scomparire la riga con l'upload della tmx


        $('body').on('click', 'tr.mine a.canceladdtmx, #inactivetm tr.new .action .addtmxfile', function() {
            $(this).parents('tr').find('.action .addtmx').removeClass('disabled');
            $(this).parents('td.uploadfile').remove();

            /*
                        $(".addtmxrow").hide();
                        $(".addtmx").show();
                        UI.clearAddTMRow();
                        */
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
                    '    <input type="file" multiple name="tmx_file" />' +
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
/*
            var nr = '<tr class="addtmxrow">' +
                    '   <td class="addtmxtd" colspan="5">' +
                    '       <label class="fileupload">Select a TMX </label>' +
                    '       <input type="file" />' +
                    '   </td>' +
                    '   <td>' +
                    '       <a class="pull-left btn-grey uploadtm">' +
                    '           <span class="icon-upload"></span> Upload</a>'+
                    '       <form class="add-TM-Form" action="/" method="post">' +
                    '           <input type="hidden" name="action" value="addTM" />' +
                    '           <input type="hidden" name="exec" value="newTM" />' +
                    '           <input type="hidden" name="job_id" value="38424" />' +
                    '           <input type="hidden" name="job_pass" value="48a757e3d46c" />' +
                    '           <input type="hidden" name="tm_key" value="" />' +
                    '           <input type="hidden" name="name" value="" />' +
                    '           <input type="hidden" name="tmx_file" value="" />' +
                    '           <input type="hidden" name="r" value="1" />' +
                    '           <input type="hidden" name="w" value="1" />' +
                    '           <input type="submit" style="display: none" />' +
                    '       </form>' +

                    '       <a class="btn-grey pull-left canceladdtmx">' +
                    '           <span class="icon-times-circle"></span> Cancel</a>' +
                    '   </td>' +
                    '</tr>';
            $(this).closest("tr").after(nr);
*/
            $(this).parents('tr').append(nr);
//            UI.uploadTM($('#addtm-upload-form')[0],'http://' + window.location.hostname + '/?action=addTM','uploadCallback');
        }).on('change paste', '#new-tm-key', function(event) {
            // set Timeout to get the text value after paste event, otherwise it is empty
            setTimeout( function(){ UI.checkTMKey('change'); }, 200 );
        }).on('click', '.mgmt-tm tr.new a.uploadtm:not(.disabled)', function() {
//            operation = ($('.mgmt-tm tr.new td.fileupload input[type="file"]').val() == '')? 'key' : 'tm';
            UI.checkTMKey('key');
            UI.saveTMkey($(this));
//            UI.addTMKeyToList();

//            operation = ($('#uploadTMX').text() == '')? 'key' : 'tm';
//            UI.checkTMKey($('#addtm-tr-key').val(), operation);
//            $(".clicked td.action").append('progressbar');

            // script per appendere le tmx fra quelle attive e inattive, preso da qui: https://stackoverflow.com/questions/24355817/move-table-rows-that-are-selected-to-another-table-javscript
        }).on('click', 'tr.mine .uploadfile .addtmxfile:not(.disabled)', function() {
            $(this).addClass('disabled');
            $(this).parents('.uploadfile').find('.error').text('').hide();

            UI.execAddTM(this);
//        }).on('click', '#activetm td.description', function() {
//            console.log($(this).find())
        }).on('click', '.mgmt-tm tr.mine td.description .edit-desc', function() {
//            console.log('.edit-desc');
//            $(this).addClass('current');
            $('.mgmt-tm .edit-desc[contenteditable=true]').blur();
            $('#activetm tr.mine td.description .edit-desc:not(.current)').removeAttr('contenteditable');
//            $(this).removeClass('current');
            $(this).attr('contenteditable', true);
        }).on('blur', '#activetm td.description .edit-desc', function() {
//            console.log('blur');
            $(this).removeAttr('contenteditable');
            UI.saveTMdata(false);
//            if(APP.isCattool) UI.saveTMdata(false);

//            $('.popup-tm tr.mine td.description .edit-desc').removeAttr('contenteditable');
/*
        }).on('keydown', '#activetm td.description .edit-desc', 'return', function(e) {
            if(e.which == 13) {
                e.preventDefault();
                $(this).removeAttr('contenteditable');
                if(APP.isCattool) UI.saveTMdata(false);
            }
*/
        }).on('blur', '#inactivetm td.description .edit-desc', function() {
            $(this).removeAttr('contenteditable');
//            if(APP.isCattool) UI.saveTMdescription($(this));
            UI.saveTMdescription($(this));
        }).on('keydown', '.mgmt-tm td.description .edit-desc', 'return', function(e) {
            console.log('return');
            e.preventDefault();
            $(this).trigger('blur');
        }).on('click', '.mgmt-mt td.engine-name .edit-desc', function() {
            $('.mgmt-mt .edit-desc[contenteditable=true]').blur();
//            $('#activetm tr.mine td.description .edit-desc:not(.current)').removeAttr('contenteditable');
//            $(this).removeClass('current');
            $(this).attr('contenteditable', true);
        }).on('blur', '.mgmt-mt td.engine-name .edit-desc', function() {
            $(this).removeAttr('contenteditable');
//            UI.saveTMdata(false);
//        }).on('blur', '#inactivetm td.description .edit-desc', function() {
//            $(this).removeAttr('contenteditable');
//            if(APP.isCattool) UI.saveTMdescription($(this));
//            UI.saveTMdescription($(this));
        }).on('keydown', '.mgmt-mt td.engine-name .edit-desc', 'return', function(e) {
            e.preventDefault();
            $(this).trigger('blur');
        }).on('click', '#activetm tr.uploadpanel .uploadfile .addtmxfile:not(.disabled)', function() {
            $(this).addClass('disabled');
            UI.execAddTM(this);
//        }).on('click', '.popup-tm .savebtn', function() {
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
//            UI.toggleTM(this);
        }).on('change', '.mgmt-table-mt tr .enable-mt input', function() {
//            console.log($(this).prop('checked'));
//            $(this).prop('checked', true);
            if($(this).is(':checked')) {
                UI.activateMT(this);
            } else {
                UI.deactivateMT(this);
            }

        }).on('click', '.mgmt-table-mt tr .action .deleteMT', function() {
//            UI.deleteMT($(this));
            $('.mgmt-table-mt .tm-warning-message').html('Do you really want to delete this MT? <a href="#" class="continueDeletingMT" data-id="' + $(this).parents('tr').attr('data-id') + '">Continue</a>').show();
        }).on('click', '.continueDeletingMT', function(e){
            e.preventDefault();
            UI.deleteMT($('.mgmt-table-mt table.mgmt-mt tr[data-id="' + $(this).attr('data-id') + '"] .deleteMT'));
            $('.mgmt-table-mt .tm-warning-message').empty().hide();
        }).on('click', 'a.usetm', function() {
            UI.useTM(this);
        }).on('change', '#new-tm-read, #new-tm-write', function() {
            UI.checkTMgrants();
        }).on('change', 'tr.mine td.uploadfile input[type="file"]', function() {
            if(this.files[0].size > config.maxTMXFileSize) {
                numMb = config.maxTMXFileSize/(1024*1024);
                APP.alert('File too big.<br/>The maximuxm allowed size is ' + numMb + 'Mb.');
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
/*
            $('.mgmt-container .tm-warning-message').html('Do you really want to delete this TM? <a href="#" class="continueDeletingTM" data-key="' + $(this).parents('tr').attr('data-key') + '">Continue</a>').show();
//            UI.deleteTM($(this));
        }).on('click', '.continueDeletingTM', function(e){
            e.preventDefault();
            console.log('continue');
            console.log($('table.mgmt-tm tr[data-key=' + $(this).attr('data-key') + ']'));
            UI.deleteTM($('table.mgmt-tm tr[data-key="' + $(this).attr('data-key') + '"] .deleteTM'));
            $('.mgmt-container .tm-warning-message').empty().hide();
*/
        })

        // script per filtrare il contenuto dinamicamente, da qui: http://www.datatables.net

        $(document).ready(function() {
//            console.log("$('#inactivetm'): ", $('#inactivetm'));
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

            /*
                        $('#inactivetm').dataTable({
                            "columnDefs":  [ { targets: [0,2,3,4], orderable: false } ]
                        });
            */
        });
/*
        $('tr').click(function() {
            $('tr').not(this).removeClass('clicked');
            $(this).toggleClass('clicked');
        });
*/
        $(".add-mt-engine").click(function() {
            $(this).hide();
            console.log('ADD MT ENGINE');
            UI.resetMTProviderPanel();
//            $('#add-mt-provider-cancel-int').click();
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

       

//    	$('#sort td:first').addClass('index');


// plugin per rendere sortable le tabelle
// sorgente: http://www.foliotek.com/devblog/make-table-rows-sortable-using-jquery-ui-sortable/


// codice per incrementare il numero della priority
//    updateIndex = function(e, ui) {
//        $('.index', ui.item.parent()).each(function (i) {
//            $(this).html(i + 1);
//        });
//    };






//$('.enable').click(function() {
//  $(this).closest('tr td:first-child').toggleClass('index');
//   	   	$(this).closest("tr").toggleClass('disabled');
//		$('tr.selected .number').show();
//	 	$('tr.selected .nonumber').hide();
//});








    },
    openLanguageResourcesPanel: function(tab, elToClick) {
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
    uploadTM: function(form, action_url, div_id) {
        console.log('div_id: ', div_id);

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

 /*       console.log('setTMsortable');
        var fixHelperModified = function(e, tr) {
            var $originals = tr.children();
            var $helper = tr.clone();
            $helper.children().each(function(index) {
                $(this).width($originals.eq(index).width())
            });
            return $helper;
        };
        console.log('fixHelperModified: ', fixHelperModified);
        */
 /*
        $(".dragrow" ).mouseover(function() {
            $("#activetm tbody.sortable").sortable({ items: ".mine" }).sortable('enable').disableSelection();
        });
        $(".dragrow" ).mouseout(function() {
            $("#activetm tbody.sortable").sortable('disable');
        });
*/
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
//                    $('.popup-tm .tm-warning-message').html('If you confirm this action, your Private TM key will be lost. If you want to avoid this, please, log in with your account now. <a href="#" class="continue-disable-tm">Continue</a> or <a href="#" class="cancel-disable-tm">Cancel</a>').show();
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
//        console.log('lookup: ', tr.find('.lookup input').is(':checked'));
//        console.log('update: ', tr.find('.update input').is(':checked'));
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
//        row.find('a.disabletm .text').text('Use').attr('class', 'text');
//        row.find('.lookup input[type="checkbox"]').first().attr('disabled', 'disabled');
//        row.find('.update input[type="checkbox"]').first().attr('disabled', 'disabled');
        row.css('display', 'block');

        // draw the user's attention to it
        row.fadeOut();
        row.fadeIn();
//        $(el).addClass("usetm").removeClass("disabletm");
        $('.addtmxrow').hide();
        // draft of hack for nodata row management from datatables plugin
//            $('#inactivetm tr.odd:not(.mine)').hide();
    },

    useTM: function (el) {
        var row = $(el).closest("tr");
        row.detach();
        $("#activetm tr.new").before(row);
        if(!$('#inactivetm tbody tr:not(.noresults)').length) $('#inactivetm tr.noresults').show();
        row.addClass('mine');
//        row.find('a.usetm .text').text('Stop Use').attr('class', 'text');
//        row.find('.lookup input[type="checkbox"]').prop('checked', true).removeAttr('disabled');
//        row.find('.update input[type="checkbox"]').prop('checked', true).removeAttr('disabled');
        row.css('display', 'block');

        //update datatable struct
//        $('#inactivetm' ).DataTable().row(row).remove().draw(false);

        // draw the user's attention to it
        row.fadeOut();
        row.fadeIn();
//        $(el).addClass("disabletm").removeClass("usetm");
        $('.addtmxrow').hide();
    },

    /*
        registerTMX: function () {
            if(!UI.TMKeysToAdd) UI.TMKeysToAdd = [];
            item = {};
            item.key = $('#new-tm-key').val();
            item.description = $('#new-tm-description').val();
            UI.TMKeysToAdd.push(item);
            $(".canceladdtmx").click();
        },
    */
    execAddTM: function(el) {
        table = $(el).parents('table');
        existing = ($(el).hasClass('existingKey'))? true : false;
        if(existing) {
            $(el).parents('.uploadfile').addClass('uploading');
        } else {
            $(table).find('tr.uploadpanel .uploadfile').addClass('uploading');
        }
        var trClass = (existing)? 'mine' : 'uploadpanel';
        form = $(table).find('tr.' + trClass + ' .add-TM-Form')[0];
        path = $(el).parents('.uploadfile').find('input[type="file"]').val();
        file = path.split('\\')[path.split('\\').length-1];
        this.TMFileUpload(form, '/?action=loadTMX','uploadCallback', file);
    },
    addTMKeyToList: function (uploading) {
        var r = ($('#new-tm-read').is(':checked'))? 1 : 0;
        var w = ($('#new-tm-write').is(':checked'))? 1 : 0;
        var desc = $('#new-tm-description').val();
        var TMKey = $('#new-tm-key').val();

        newTr = '<tr class="mine" data-tm="1" data-glos="1" data-key="' + TMKey + '" data-owner="' + config.ownerIsMe + '">' +
                '    <td class="dragrow"><div class="status"></div></td>' +
                '    <td class="privatekey">' + TMKey + '</td>' +
                '    <td class="owner">You</td>' +
                '    <td class="description"><div class="edit-desc">' + desc + '</div></td>' +
                '    <td class="lookup check text-center"><input type="checkbox"' + ((r)? ' checked="checked"' : '') + ' /></td>' +
                '    <td class="update check text-center"><input type="checkbox"' + ((w)? ' checked="checked"' : '') + ' /></td>' +
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
        $('#activetm tr.new').before(newTr);
        if(uploading) {
            $('.mgmt-tm tr.new').addClass('hide');
        } else {
            $('.mgmt-tm tr.new .canceladdtmx').click();
        }
        UI.pulseTMadded($('#activetm tr.mine').last());
        UI.setTMsortable();
        if(APP.isCattool) UI.saveTMdata(false);
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
//        $('.mgmt-tm tr.new .message').text('The key ' + this + ' has been added!');
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
//        $('#activetm td.uploadfile').remove();
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
//        form.parentNode.appendChild(iframe);
        window.frames['upload_iframe'].name = "upload_iframe";
        iframeId = document.getElementById(ifId);

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

            // Del the iframe...
//            setTimeout('iframeId.parentNode.removeChild(iframeId)', 250);
        }

        if (iframeId.addEventListener) iframeId.addEventListener("load", eventHandler, true);
        if (iframeId.attachEvent) iframeId.attachEvent("onload", eventHandler);
        existing = ($(form).hasClass('existing'))? true : false;
        TMKey = (existing)? $(form).parents('.mine').find('.privatekey').first().text() : $('#new-tm-key').val();

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
//        console.log('vediamolo: ', TMName.split('\\')[TMName.split('\\').length-1]);
        TRcaller = (existing)? $(form).parents('.uploadfile') : $('#activetm .uploadpanel .uploadfile');
        TRcaller.addClass('startUploading');
        if(!existing) {
            UI.addTMKeyToList(true);
//            $('.popup-tm h1 .btn-ok').click();
        }

        setTimeout(function() {
            UI.pollForUploadCallback(TMKey, TMName, existing, TRcaller);
        }, 3000);

        return false;
/*
//    document.getElementById(div_id).innerHTML = "Uploading...";
        $('.popup-addtm-tr .x-popup').click();
        APP.showMessage({
            msg: 'Uploading your TM...'
        });
        $('#messageBar .msg').after('<span class="progress"></span>');
        TMKey = $('#new-tm-key').val();
        TMName = $('.mgmt-tm tr.new td.fileupload input[type="file"]').val();
        console.log('TMKey 1: ', TMKey);
        console.log('TMName 1: ', TMName);
//    UI.pollForUploadProgress(TMKey, TMName);
        UI.pollForUploadCallback(TMKey, TMName);
*/
    },
    pollForUploadCallback: function(TMKey, TMName, existing, TRcaller) {
        if($('#uploadCallback').text() != '') {
            msg = $.parseJSON($('#uploadCallback pre').text());
//            msg.success = false;
//            msg.errors = [{message: 'questo è un errore'}];
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
//                $(TRcaller).find('.addtmxfile').removeClass('disabled');
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
//                d.errors = [{message: 'questo è un errore'}];
                if(d.errors.length) {
                    if(existing) {
                        console.log('error');
                        console.log($(TRcaller));
//                        $(TRcaller).find('.standard').hide();
//                        $(TRcaller).find('.uploadprogress').show();
                        $(TRcaller).find('.error').text(d.errors[0].message).show();
//                        $(TRcaller).find('.addtmxfile').removeClass('disabled');
                    } else {
                        $('#activetm tr.uploadpanel .uploadfile').removeClass('uploading');
                    }

                    /*
                                       APP.showMessage({
                                           msg: d.errors[0].message,
                                       });
                                       */
                } else {
                    $(TRcaller).find('.uploadprogress .msgText').text('Uploading ' + this[1]);
//                    $(TRcaller).find('.standard').hide();
                    $(TRcaller).find('.uploadprogress').show();

//                    $(TRcaller).html('<span class="progress"><span class="inner" style="float: left; height: 5px; width: 0%; background: #09BEEC"></span></span><span class="msgText">Uploading ' + this[1]+ '...</span>');
                    if(d.data.total == null) {
                        pollForUploadProgressContext = this;
                        setTimeout(function() {
                            UI.pollForUploadProgress(pollForUploadProgressContext[0], pollForUploadProgressContext[1], pollForUploadProgressContext[2], pollForUploadProgressContext[3]);
                        }, 1000);
                    } else {
                        if(d.completed) {
                            if(existing) {
                                if(config.isAnonymousUser) {
                                    console.log('anonimo');
                                } else {
                                    console.log('loggato');
                                }
                                var tr = $(TRcaller).parents('tr.mine');
                                $(tr).find('.addtmx').removeClass('disabled');
                                UI.pulseTMadded(tr);
                            }
//                            $(TRcaller).empty();

                            $(TRcaller).find('.uploadprogress').hide();
                            $(TRcaller).find('.uploadprogress .msgText').text('Uploading');
//                            $(TRcaller).find('.standard').show();
                            if(existing) {
                                $(TRcaller).addClass('tempTRcaller').append('<span class="msg">Import Complete</span>');
                                setTimeout(function() {
                                    $('.tempTRcaller').remove();
                                }, 3000);
                            } else {
                                $('.mgmt-tm tr.new .canceladdtmx').click();
                                $('.mgmt-tm tr.new').removeClass('hide');
                                $('#activetm tr.uploadpanel .uploadfile').removeClass('uploading');
                            }


//                            APP.showMessage({
//                                msg: 'Your TM has been correctly uploaded. The private TM key is ' + TMKey + '. Store it somewhere safe to use it again.'
//                            });
//                            UI.clearAddTMpopup();
                            return false;
                        }
                        progress = (parseInt(d.data.done)/parseInt(d.data.total))*100;
                        $(TRcaller).find('.progress .inner').css('width', progress + '%');
                        pollForUploadProgressContext = this;
                        setTimeout(function() {
                            UI.pollForUploadProgress(pollForUploadProgressContext[0], pollForUploadProgressContext[1], pollForUploadProgressContext[2], pollForUploadProgressContext[3]);
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
//                APP.showMessage({msg: 'There was an error saving your data. Please retry!'});
                $('.tm-error-message').text('There was an error saving your data. Please retry!').show();
                $('.popup-tm').removeClass('saving');

//                $('.mgmt-panel-tm .warning-message').text('').hide();
//                $('.mgmt-panel-tm .error-message').text('There was an error saving your data. Please retry!').show();
            },
            success: function(d) {
                $('.popup-tm').removeClass('saving');

//                d.errors = [];
                if(d.errors.length) {
                    APP.showMessage({msg: d.errors[0].message});
//                    $('.mgmt-panel-tm .warning-message').text('').hide();
//                    $('.mgmt-panel-tm .error-message').text(d.errors[0].message).show();
                } else {
                    console.log('TM data saved!!');
/*
                    $('.mgmt-panel-tm .error-message').text('').hide();
                    $('.mgmt-panel-tm .warning-message').text('Your data has been saved.').show();
                    setTimeout(function(){
                        UI.clearTMPanel();
                    },1000);
*/
                    /*
                                        setTimeout(function(){
                                            $('.mgmt-panel-tm .warning-message').animate({
                                                opacity: 0
                                            },300);
                                            UI.closeTMPanel();

                                            setTimeout(function(){
                                                $('.mgmt-panel-tm .warning-message' )
                                                        .animate({
                                                            height:0,
                                                            padding:0,
                                                            margin:0
                                                        },300);

                                                setTimeout(function(){
                                                    $('.mgmt-panel-tm .warning-message' )
                                                        .text("")
                                                        .animate({
                                                            opacity : 1,
                                                            height: 'auto',
                                                            padding: 'auto',
                                                            margin: 'auto'
                                                        },0 )
                                                        .hide()
                                                },300);

                                            }, 300);
                                            console.log('ORA');
                                        }, 2000);
                    */
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
//                APP.showMessage({msg: 'There was an error saving your key. Please retry!'});
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
                    if ( token == downloadToken ) {
                        $( tm ).find( '.' + button_class ).removeClass('disabled' ).removeClass('downloading');
                        $(tm).find('span.notify').remove();
                        window.clearInterval( downloadTimer );
                        $.cookie( downloadToken, null, {path: '/', expires: -1} );
                        errorMsg = $('#' + iFrameID).contents().find('body').text();
                        errorKey = $(tm).attr('data-key');
                        if(errorMsg != '') {
                            APP.alert('Error on downloading a TM with key ' + errorKey + ':<br />' + errorMsg);
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
            field = $(this).find('input').first();
            providerData[field.attr('data-field-name')] = field.val();
        })
//        console.log(providerData);
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
//        return false;

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

/*
            if(config.ownerIsMe) {

            } else {
                $('table.mgmt-mt tbody').prepend(newTR);
            }
*/
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
//        $('.mgmt-tm tr.new .message').text('The key ' + this + ' has been added!');
    },
    resetMTProviderPanel: function () {
        if($('.insert-tm .step2').css('display') == 'block') {
            $('#add-mt-provider-cancel-int').click();
            $('.add-mt-engine').click();
//            $('.insert-tm .step2').css('display', 'none');
        };
        /*
        $(".add-mt-engine").show();
        $(".insert-tm").addClass('hide');
        $('#mt_engine_int').val('none').trigger('change');
        $(".insert-tm").addClass('hide').removeAttr('style');
        $('#add-mt-provider-cancel').show();
        */
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
        UI.pulseMTadded($('.activemt').last()); /* codice inserito da Daniele */

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

    /*
        toggleTM: function (el) {
            setTimeout(function() {
                newChecked = ($(el).attr('checked') == 'checked')? '' : ' checked';
                $(el).replaceWith('<input type="checkbox"' + newChecked + ' />');
            }, 200);
        },
    */
    /* codice inserito da Daniele */
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
    }
});