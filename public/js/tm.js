/*
 Component: tm
 Created by andreamartines on 02/10/14.
 Loaded by cattool and upload page.
 */

$.extend(UI, {
    initTM: function() {
        console.log('TM init vediamo');
        $('.popup-tm').height($(window).height());
// script per lo slide del pannello di manage tmx



        $(".popup-tm .x-popup").click(function(e) {
            e.preventDefault();
            $( ".popup-tm").removeClass('open').hide("slide", { direction: "right" }, 400);
            $("#SnapABug_Button").show();
            $(".outer-tm").hide();
            $('body').removeClass('side-popup');
        });

        $(".outer-tm").click(function() {
            $(".popup-tm").removeClass('open').hide("slide", { direction: "right" }, 400);
            $("#SnapABug_Button").show();
            $(".outer-tm").hide();

            $('body').removeClass('side-popup');
        });

        $(".mgmt-tm").click(function() {
            $(".mgmt-panel-gl").hide();
            $(".mgmt-panel-tm").show();
            $("table.mgmt-tm").show();
        });

        $(".mgmt-gl").click(function() {
            $(".mgmt-panel-tm").hide();
            $("table.mgmt-tm").hide();
            $(".mgmt-panel-gl").show();
        });

        $(".mgmt-tm .new .privatekey .btn-ok").click(function(e) {
            e.preventDefault();
            //prevent double click
            if($(this).hasClass('disabled')) return false;
            $(this).addClass('disabled');
            $(this).attr('disabled','');
            $.get("http://mymemory.translated.net/api/createranduser", function(data){
                //parse to appropriate type
                //this is to avoid a curious bug in Chrome, that causes 'data' to be already an Object and not a json string
                if(typeof data == 'string'){
                    data=jQuery.parseJSON(data);
                }
                //put value into input field
                $('#new-tm-key').val(data.key);
                $('.mgmt-tm .new .privatekey .btn-ok').removeClass('disabled');
                setTimeout(function() {
//                    UI.checkAddTMEnable();
//                    UI.checkManageTMEnable();
                }, 100);
                return false;
            });
        });
        // script per fare apparire e scomparire la riga con l'upload della tmx


        $('body').on('click', '#activetm tr.mine a.canceladdtmx', function() {
            $(this).parents('tr').find('.action .addtmx').show();
            $(this).parents('td.uploadfile').remove();

            /*
                        $(".addtmxrow").hide();
                        $(".addtmx").show();
                        UI.clearAddTMRow();
                        */
        }).on('click', '.addtmx', function() {
            $(this).hide();
            var nr = '<td class="uploadfile">' +
                     '  <label class="fileupload">Select a TMX </label>' +
                     '  <input type="file" />' +
                     '  <a class="pull-right canceladdtmx">' +
                     '      <span class="icon-times-circle"></span>' +
                     '  </a>' +
                    '   <a class="pull-right btn-grey addtmxfile">' +
                    '       <span class="text">Upload TMX</span>' +
                    '   </a>' +
                    '<form class="add-TM-Form" action="/" method="post">' +
                    '    <input type="hidden" name="action" value="loadTMX" />' +
                    '    <input type="hidden" name="exec" value="newTM" />' +
                    '    <input type="hidden" name="job_id" value="38424" />' +
                    '    <input type="hidden" name="job_pass" value="48a757e3d46c" />' +
                    '    <input type="hidden" name="tm_key" value="" />' +
                    '    <input type="hidden" name="name" value="" />' +
                    '    <input type="hidden" name="tmx_file" value="" />' +
                    '    <input type="hidden" name="r" value="1" />' +
                    '    <input type="hidden" name="w" value="1" />' +
                    '    <input type="submit" id="addtm-add-submit" style="display: none" />' +
                    '</form>' +
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
//            UI.checkTMheights();
        }).on('click', '.mgmt-tm tr.new a.uploadtm', function() {
//            operation = ($('.mgmt-tm tr.new td.fileupload input[type="file"]').val() == '')? 'key' : 'tm';
            UI.checkTMKey('key');
//            UI.execAddTMKey();
//            UI.addTMKeyToList();

//            operation = ($('#uploadTMX').text() == '')? 'key' : 'tm';
//            UI.checkTMKey($('#addtm-tr-key').val(), operation);
//            $(".clicked td.action").append('progressbar');

            // script per appendere le tmx fra quelle attive e inattive, preso da qui: https://stackoverflow.com/questions/24355817/move-table-rows-that-are-selected-to-another-table-javscript
        }).on('click', '#activetm tr.mine .uploadfile .addtmxfile', function() {
            UI.execAddTM(this);
        }).on('click', '#activetm tr.uploadpanel .uploadfile .addtmxfile', function() {
            UI.execAddTM(this);
        }).on('click', '.popup-tm .savebtn', function() {
            UI.saveTMdata();
        }).on('click', '#activetm tr.new a.addtmxfile', function() {
            console.log('upload file');
            $('#activetm tr.uploadpanel').removeClass('hide');
        }).on('click', 'a.disabletm', function() {
            var row = $(this).closest("tr");
            if(row.find('td.uploadfile').length) {
                row.find('td.uploadfile .canceladdtmx').click();
                row.find('.addtmx').removeAttr('style');
            }
            row.detach();
            $("#inactivetm").append(row);
            $('#inactivetm tr.noresults').hide();
            row.find('a.disabletm .text').text('Use').attr('class', 'text icon-play-circle');
            row.find('.lookup input[type="checkbox"]').first().removeAttr('checked').attr('disabled', 'disabled');
            row.find('.update input[type="checkbox"]').first().removeAttr('checked').attr('disabled', 'disabled');
            row.css('display', 'block');

            // draw the user's attention to it
            row.fadeOut();
            row.fadeIn();
            $(this).addClass("usetm").removeClass("disabletm");
            $('.addtmxrow').hide();
        }).on('click', 'a.usetm', function() {
            var row = $(this).closest("tr");
            row.detach();
            $("#activetm tr.new").before(row);
            if(!$('#inactivetm tbody tr:not(.noresults)').length) $('#inactivetm tr.noresults').show();
            row.addClass('mine');
            row.find('a.usetm .text').text('Stop Use').attr('class', 'text icon-minus-circle');
            row.find('.lookup input[type="checkbox"]').prop('checked', true).removeAttr('disabled');
            row.find('.update input[type="checkbox"]').prop('checked', true).removeAttr('disabled');
            row.css('display', 'block');

            // draw the user's attention to it
            row.fadeOut();
            row.fadeIn();
            $(this).addClass("disabletm").removeClass("usetm");
            $('.addtmxrow').hide();
        }).on('change', '#new-tm-read, #new-tm-write', function() {
            if(UI.checkTMgrants($('.addtm-tr'))) {
//                $('.addtm-tr .error-message').hide();
            }
        }).on('change', '#activetm tr.mine td.uploadfile input[type="file"]', function() {
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
        });


        // script per filtrare il contenuto dinamicamente, da qui: http://www.datatables.net

        $(document).ready(function() {
            console.log("$('#inactivetm'): ", $('#inactivetm'));
            UI.setTMsortable();
            $('#inactivetm').dataTable();

        });

        $('tr').click(function() {
            $('tr').not(this).removeClass('clicked');
            $(this).toggleClass('clicked');
        });

        $(".add-tm").click(function() {
            $(this).hide();
            $(".mgmt-tm tr.new").removeClass('hide').show();
        });

        $(".mgmt-tm tr.new .canceladdtmx").click(function() {
            $("#activetm tr.new").hide();
            $("#activetm tr.uploadpanel").addClass('hide');
            $(".add-tm").show();
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
    openLanguageResourcesPanel: function() {
        $('body').addClass('side-popup');
        $(".popup-tm").addClass('open').show("slide", { direction: "right" }, 400);
//        UI.checkTMheights();
        $("#SnapABug_Button").hide();
        $(".outer-tm").show();
    },
    uploadTM: function(form, action_url, div_id) {
        console.log('div_id: ', div_id);

    },
    setTMsortable: function () {

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
        $("#activetm tbody.sortable").sortable({ items: ".mine" }).disableSelection();
    },

    checkTMheights: function() {
//        var h = $('#activetm tbody tr').first().height();
//        var h = $('#activetm tbody tr:not(.new, .addtmxrow):nth-child(-n+4)').height();
        var h = 0;
        $('#activetm tbody tr:not(.new, .addtmxrow):nth-child(-n+4)').each(function() {
            h += $(this).height();
        })
        $('#activetm tbody').css('height', h + 'px');

        /*
                console.log($('#activetm tbody tr:not(.new, .addtmxrow):nth-child(-n+4)'));
                var h = 0;
                $('#activetm tbody tr:not(.new, .addtmxrow):nth-child(-n+4)').each(function() {
                    h += $(this).height();
                })
                $('#activetm tbody').css('height', h + 'px');
                console.log(h);
        */

    },
    checkTMKey: function(operation) {
        console.log('checkTMKey');
        console.log('operation: ', operation);
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
                console.log('checkTMKey success!!');
                console.log('d: ', d);
                console.log('d.success: ', d.success);
                if(d.success === true) {
                    console.log('key is good');
                    console.log('adding a tm');
                    UI.addTMKeyToList();
                } else {
                    console.log('key is bad');
                }
            }
        });

/*
        if( operation == 'key' ){
            console.log('adding a key');

            if(APP.isCattool) {
                UI.execAddTMKey();
            } else {
                UI.registerTMX();
            }
        } else {
            console.log('adding a tm');
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
                    console.log('checkTMKey success!!');
                    console.log('d: ', d);
                    console.log('d.success: ', d.success);
                    if(d.success === true) {
                        console.log('key is good');
                        console.log('adding a tm');
                        UI.execAddTM('new');
                        return true;
                    } else {
                        console.log('key is bad');
                        return false;
                    }
                }
            });

//            this.addTMXToKey('new');
        }
*/
    },
    registerTMX: function () {
        if(!UI.TMKeysToAdd) UI.TMKeysToAdd = [];
        item = {};
        item.key = $('#new-tm-key').val();
        item.description = $('#new-tm-description').val();
        UI.TMKeysToAdd.push(item);
        $(".canceladdtmx").click();
    },
    execAddTM: function(el) {
        console.log('execAddTM el: ', el);
        if(el == 'new') {
            form = $('.mgmt-tm tr.new .add-TM-Form')[0];
            file = $('.mgmt-tm tr.new td.fileupload input').val();
        } else {
//            tr = $(el).parents('tr');
//            form = tr.find('.add-TM-Form')[0];
            form = $('#activetm tr.new .add-TM-Form')[0];
            file = $(el).parents('.uploadfile').find('input[type="file"]').val();
//            file = tr.find('input[type="file"]').val();
            console.log('form: ', form);
            console.log('file: ', file);
        }
        this.TMFileUpload(form, 'http://' + window.location.hostname + '/?action=loadTMX','uploadCallback', file);

    },
    execAddTMKey: function() {
        var r = ($('#new-tm-read').is(':checked'))? 1 : 0;
        var w = ($('#new-tm-write').is(':checked'))? 1 : 0;
        var desc = $('#new-tm-description').val();
        var TMKey = $('#new-tm-key').val();

        APP.doRequest({
            data: {
                action: 'loadTMX',
                exec: 'addTM',
                job_id: config.job_id,
                job_pass: config.password,
                tm_key: TMKey,
                name: desc,
                r: r,
                w: w
            },
            context: {
                tm_key: TMKey,
                name: desc,
                r: r,
                w: w
            },
            error: function() {
                console.log('addTM error!!');
                $('.mgmt-tm tr.new .message').text('Error adding your TM!');
            },
            success: function() {
                console.log('addTM success!!');
                newTr = '<tr class="ui-sortable-handle">' +
                        '    <td class="privatekey">' + this.tm_key + '</td>' +
                        '    <td class="description">' + this.name + '</td>' +
                        '    <td class="langpair"><span class="mgmt-source">it-IT</span> - <span class="mgmt-target">PT-BR</span></td>' +
                        '    <td class="lookup check text-center"><input type="checkbox"' + ((this.r)? ' checked="checked"' : '') + '></td>' +
                        '    <td class="update check text-center"><input type="checkbox"' + ((this.w)? ' checked="checked"' : '') + '></td>' +
                        '    <td class="action">' +
                        '        <a class="btn-grey pull-left usetm">' +
                        '            <span class="text stopuse">Stop Use</span>' +
                        '        </a>' +
                        '        <a class="btn-grey pull-left addtmx">' +
                        '            <span class="text addtmxbtn">Add TMX</span>' +
                        '        </a>' +
                        '    </td>' +
                        '</tr>';
                $('#activetm').append(newTr);
                $('.mgmt-tm tr.new .canceladdtmx').click();
                UI.pulseTMadded($('#activetm tr').last());
                UI.setTMsortable();
            }
        });
    },
    addTMKeyToList: function () {
        var r = ($('#new-tm-read').is(':checked'))? 1 : 0;
        var w = ($('#new-tm-write').is(':checked'))? 1 : 0;
        var desc = $('#new-tm-description').val();
        var TMKey = $('#new-tm-key').val();

        newTr = '<tr class="mine" data-tm="1" data-glos="1" data-owner="1">' +
                '    <td class="privatekey">' + TMKey + '</td>' +
                '    <td class="description">' + desc + '</td>' +
                '    <td class="lookup check text-center"><input type="checkbox"' + ((r)? ' checked="checked"' : '') + ' /></td>' +
                '    <td class="update check text-center"><input type="checkbox"' + ((w)? ' checked="checked"' : '') + ' /></td>' +
                '    <td class="action">' +
                '        <a class="btn-grey pull-left disabletm">' +
                '            <span class="text stopuse">Stop Use</span>' +
                '        </a>' +
                '        <a class="btn-grey pull-left addtmx">' +
                '            <span class="text addtmxbtn">Add TMX</span>' +
                '        </a>' +
                '    </td>' +
                '</tr>';
        $('#activetm tr.new').before(newTr);
        $('.mgmt-tm tr.new .canceladdtmx').click();
        UI.pulseTMadded($('#activetm tr.mine').last());
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
//        $('.mgmt-tm tr.new .message').text('The key ' + this + ' has been added!');
    },

    clearAddTMRow: function() {
        $('#new-tm-key, #new-tm-description').val('');
        $('#activetm .fileupload').val('');
//        $('#uploadTMX').text('').hide();
        $('.mgmt-tm tr.new .message').text('');
    },
    TMFileUpload: function(form, action_url, div_id, tmName) {
        console.log('div_id: ', div_id);
        console.log('form: ', form);
        // Create the iframe...
        var iframe = document.createElement("iframe");
        iframe.setAttribute("id", "upload_iframe");
        iframe.setAttribute("name", "upload_iframe");
        iframe.setAttribute("width", "0");
        iframe.setAttribute("height", "0");
        iframe.setAttribute("border", "0");
        iframe.setAttribute("style", "width: 0; height: 0; border: none;");

        // Add to document...
        form.parentNode.appendChild(iframe);
        window.frames['upload_iframe'].name = "upload_iframe";
        iframeId = document.getElementById("upload_iframe");

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
            setTimeout('iframeId.parentNode.removeChild(iframeId)', 250);
        }

        if (iframeId.addEventListener) iframeId.addEventListener("load", eventHandler, true);
        if (iframeId.attachEvent) iframeId.attachEvent("onload", eventHandler);

        // Set properties of form...
        form.setAttribute("target", "upload_iframe");
        form.setAttribute("action", action_url);
        form.setAttribute("method", "post");
        form.setAttribute("enctype", "multipart/form-data");
        form.setAttribute("encoding", "multipart/form-data");
        $(form).append('<input type="hidden" name="exec" value="newTM" />')
            .append('<input type="hidden" name="tm_key" value="' + $('#new-tm-key').val() + '" />')
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
        TMKey = $('#new-tm-key').val();
        TMName = $('.mgmt-tm tr.uploadpanel td.uploadfile input[type="file"]').val();
        UI.pollForUploadCallback(TMKey, TMName);

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
    pollForUploadCallback: function(TMKey, TMName) {
        if($('#uploadCallback').text() != '') {
            msg = $.parseJSON($('#uploadCallback pre').text());
            if(msg.success === true) {
                UI.pollForUploadProgress(TMKey, TMName);
            } else {
                APP.showMessage({
                    msg: 'Error: ' + msg.errors[0].message
                });
            }
        } else {
            setTimeout(function() {
                UI.pollForUploadCallback(TMKey, TMName);
            }, 1000);
        }

    },

    pollForUploadProgress: function(TMKey, TMName) {
        APP.doRequest({
            data: {
                action: 'loadTMX',
                exec: 'uploadStatus',
                tm_key: TMKey,
                name: TMName
            },
            context: [TMKey, TMName],
            error: function() {
            },
            success: function(d) {
                console.log('progress success data: ', d);
                if(d.errors.length) {
                    APP.showMessage({
                        msg: d.errors[0].message,
                    });
                } else {
                    if(d.data.total == null) {
                        pollForUploadProgressContext = this;
                        setTimeout(function() {
                            UI.pollForUploadProgress(pollForUploadProgressContext[0], pollForUploadProgressContext[1]);
                        }, 500);
                    } else {
                        if(d.completed) {
                            $('#messageBar .progress').remove();
                            APP.showMessage({
                                msg: 'Your TM has been correctly uploaded. The private TM key is ' + TMKey + '. Store it somewhere safe to use it again.'
                            });
                            UI.clearAddTMpopup();
                            return false;
                        }
                        progress = (parseInt(d.data.done)/parseInt(d.data.total))*100;
                        $('#messageBar .progress').css('width', progress + '%');
                        pollForUploadProgressContext = this;
                        setTimeout(function() {
                            UI.pollForUploadProgress(pollForUploadProgressContext[0], pollForUploadProgressContext[1]);
                        }, 500);
                    }
                }
            }
        });
    },
    checkTMgrants: function() {console.log('checkTMgrants');
        panel = $('.mgmt-tm tr.new');
        var r = ($(panel).find('.r').is(':checked'))? 1 : 0;
        var w = ($(panel).find('.w').is(':checked'))? 1 : 0;
        if(!r && !w) {
            console.log('checkTMgrants NEGATIVE');
            $(panel).find('.action .message').text('Either "Show matches from TM" or "Add translations to TM" must be checked');
            return false;
        } else {
            console.log('checkTMgrants POSITIVE');
            $(panel).find('.action .message').text('');
            return true;
        }
    },
    extractTMdataFromTable: function () {
        tt = $('#activetm tbody tr.mine');
        dataOb = [];
        $(tt).each(function () {
            dd = {
                key: $(this).find('.privatekey').text(),
                tmx_name: $(this).find('.description').text(),
                r: (($(this).find('.lookup input').is(':checked'))? 1 : 0),
                w: (($(this).find('.update input').is(':checked'))? 1 : 0)
            }
            dataOb.push(dd);
        })
        return JSON.stringify(dataOb);
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

    saveTMdata: function() {
        data = this.extractTMdataFromTable();
        console.log('VEDIAMO: ', data);
        APP.doRequest({
            data: {
                action: 'updateJobKeysController',
                job_id: config.job_id,
                job_pass: config.password,
                data: data
            },
            error: function() {
                console.log('Error saving TM data!!');
            },
            success: function() {
                console.log('TM data saved!!');
            }
        });
        /*
         numActive = $('#activetm tbody tr:not(.hide)').length;
         $('.translate-box .numResources').text(numActive);
         $('.resource').show();
         activeTMdata = [];
         $('#activetm tbody tr:not(.hide)').each(function() {
         item = {};
         item.key = $(this).find('.privatekey').text();
         item.tm = $(this).attr('data-tm');
         item.glos = $(this).attr('data-glos');
         item.r = $(this).find('.lookup input').is(':checked');
         item.w = $(this).find('.update input').is(':checked');
         activeTMdata.push(item);
         });
         console.log('activeTMdata; ', activeTMdata);
         console.log('activeTMdata string; ', JSON.stringify(activeTMdata));

         APP.doRequest({
         data: {
         action: 'addTM',
         job_id: config.job_id,
         job_pass: config.password,
         data: JSON.stringify(activeTMdata)
         },
         error: function() {
         console.log('Error saving TM data!!');
         },
         success: function(d) {
         console.log('TM data saved!!');
         }
         });
         */
// ???
        /*
         $('input.checkbox').each(function(){
         if ($(this).is(':checked')) {
         $(this).parent('tr').addClass('selected');
         } else {
         $(this).parent('tr').addClass('disabled');
         }
         });
         */

    },

    updateTM: function (tr) {
        dataMix = {
            action: 'loadTMX',
            exec: 'updateTM'
        };
        console.log('tr: ', tr);
        console.log('tr length: ', tr.length);
        TMdata = this.extractTMdataFromRow(tr);
        $.extend(dataMix, TMdata);
        if(APP.isCattool) {
            dataMix.job_id = config.job_id;
            dataMix.job_pass = config.password;
        }
        APP.doRequest({
            data: dataMix,
            context: [TMdata],
            error: function() {
            },
            success: function(d) {
                console.log(d);
/*
                if(d.errors.length) {
                    APP.showMessage({
                        msg: d.errors[0].message,
                    });
                } else {
                    if(d.data.total == null) {
                        pollForUploadProgressContext = this;
                        setTimeout(function() {
                            UI.pollForUploadProgress(pollForUploadProgressContext[0], pollForUploadProgressContext[1]);
                        }, 500);
                    } else {
                        if(d.completed) {
                            $('#messageBar .progress').remove();
                            APP.showMessage({
                                msg: 'Your TM has been correctly uploaded. The private TM key is ' + TMKey + '. Store it somewhere safe to use it again.'
                            });
                            UI.clearAddTMpopup();
                            return false;
                        }
                        progress = (parseInt(d.data.done)/parseInt(d.data.total))*100;
                        $('#messageBar .progress').css('width', progress + '%');
                        pollForUploadProgressContext = this;
                        setTimeout(function() {
                            UI.pollForUploadProgress(pollForUploadProgressContext[0], pollForUploadProgressContext[1]);
                        }, 500);
                    }
                }
                */
            }
        });
    },

});
