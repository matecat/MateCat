/*
 Component: tm
 Created by andreamartines on 02/10/14.
 Loaded by cattool and upload page.
 */

$.extend(UI, {
    initTM: function() {
        console.log('TM init vediamo');

// script per lo slide del pannello di manage tmx



        $(".popup-tm .x-popup").click(function(e) {
            e.preventDefault();
            $( ".popup-tm").removeClass('open').hide("slide", { direction: "right" }, 400);
            $("#SnapABug_Button").show();
            $(".outer-tm").hide();
        });

        $(".outer-tm").click(function() {
            $(".popup-tm").removeClass('open').hide("slide", { direction: "right" }, 400);
            $("#SnapABug_Button").show();
            $(".outer-tm").hide();
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

        $("#activetm .new .privatekey .btn-ok").click(function(e) {
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
                $('#activetm .new .privatekey .btn-ok').removeClass('disabled');
                setTimeout(function() {
//                    UI.checkAddTMEnable();
//                    UI.checkManageTMEnable();
                }, 100);
                return false;
            });
        });
        // script per fare apparire e scomparire la riga con l'upload della tmx


        $(".addtmx").click(function() {
            $(this).hide();
            var newRow = '<tr class="addtmxrow"><td class="addtmxtd" colspan="5"><label class="fileupload">Select a TMX </label><input type="file" /></td><td><a class="pull-left btn-grey uploadtm"><span class="icon-upload"></span> Upload</a> <a class="btn-grey pull-left canceladdtmx"><span class="icon-times-circle"></span> Cancel</a> </td></tr>';
            $(this).closest("tr").after(newRow);
            UI.uploadTM($('#addtm-upload-form')[0],'http://' + window.location.hostname + '/?action=addTM','uploadCallback');
            UI.checkTMheights();
        })

        $('body').on('click', 'a.canceladdtmx', function() {
            $(".addtmxrow").hide();
            $(".addtmx").show();
            UI.clearAddTMRow();
        }).on('click', 'a.uploadtm', function() {
            operation = ($("#activetm .new td.fileupload input").val() == '')? 'key' : 'tm';
//            $('.addtmxrow').hide().fadeOut();
            UI.checkTMKey(operation);

//            operation = ($('#uploadTMX').text() == '')? 'key' : 'tm';
//            UI.checkTMKey($('#addtm-tr-key').val(), operation);
//            $(".clicked td.action").append('progressbar');

            // script per appendere le tmx fra quelle attive e inattive, preso da qui: https://stackoverflow.com/questions/24355817/move-table-rows-that-are-selected-to-another-table-javscript
        }).on('click', 'a.usetm', function() {
            // get the row containing this link
            var row = $(this).closest("tr");
            var x = 0;
            // find out in which table it resides
            var table = $(this).closest("table");

            // move it
            row.detach();

            if (table.is("#activetm") && (x==0)) {
                $("#inactivetm").append(row);
            }

            else {
                $("#activetm .new").before(row);
            }
            // draw the user's attention to it
            row.fadeOut();
            row.fadeIn();

            $(this).addClass("disabletm").removeClass("usetm").text("Stop use").prepend('<span class="icon-minus-circle"></span> ');
            $('.addtmxrow').hide();
            $(".addtmx").show();

        }).on('click', 'a.disabletm', function() {
            // get the row containing this link
            var row = $(this).closest("tr");
            var x = 0;
            // find out in which table it resides
            var table = $(this).closest("table");

            // move it
            row.detach();

            if (table.is("#inactivetm") && (x==0)) {
                $("#activetm").append(row);
            }

            else {
                $("#inactivetm").append(row);
            }
            // draw the user's attention to it
            row.fadeOut();
            row.fadeIn();

            $(this).addClass("usetm").removeClass("disabletm").text("Use").prepend('<span class="icon-play-circle"></span>');
            $('.addtmxrow').hide();
            $(".addtmx").show();

        });


        // script per filtrare il contenuto dinamicamente, da qui: http://www.datatables.net

        $(document).ready(function() {
            console.log("$('#inactivetm'): ", $('#inactivetm'));
            $('#inactivetm').dataTable();
        });

        $('tr').click(function(event) {
            $('tr').not(this).removeClass('clicked');
            $(this).toggleClass('clicked');
        });

        $(".add-tm").click(function() {
            $(this).hide();
            $("tr.new").removeClass('hide').show();
        });

        $(".canceladdtmx").click(function() {
            $("tr.new").hide();
            $(".add-tm").show();
        });

        $(".add-gl").click(function() {
            $(this).hide();
            $(".addrow-gl").show();
        });

        $(".cancel-tm").click(function() {
            $("tr.new").hide();
            $(".add-tm").show();
        });

        $("#sign-in").click(function() {
            $(".loginpopup").show();
        });

//    	$('#sort td:first').addClass('index');


// plugin per rendere sortable le tabelle
// sorgente: http://www.foliotek.com/devblog/make-table-rows-sortable-using-jquery-ui-sortable/

        var fixHelperModified = function(e, tr) {
            var $originals = tr.children();
            var $helper = tr.clone();
            $helper.children().each(function(index) {
                $(this).width($originals.eq(index).width())
            });
            return $helper;
        };
// codice per incrementare il numero della priority
//    updateIndex = function(e, ui) {
//        $('.index', ui.item.parent()).each(function (i) {
//            $(this).html(i + 1);
//        });
//    };


/*
// temporary disabled: this has to be realeased without jquery-ui (which is not loaded in the cattool), try to use tablesorter, who is already used in manage page
        $("#activetm tbody.sortable").sortable({
            helper: fixHelperModified
            //   stop: updateIndex
        }).disableSelection();
*/

//$('.enable').click(function() {
//  $(this).closest('tr td:first-child').toggleClass('index');
//   	   	$(this).closest("tr").toggleClass('disabled');
//		$('tr.selected .number').show();
//	 	$('tr.selected .nonumber').hide();
//});





        $('.savebtn').click(function() {
            UI.saveTMdata();
        });


    },
    openLanguageResourcesPanel: function() {
        $(".popup-tm").addClass('open').show("slide", { direction: "right" }, 400);
        UI.checkTMheights();
        $("#SnapABug_Button").hide();
        $(".outer-tm").show();
    },
    uploadTM: function(form, action_url, div_id) {
        console.log('div_id: ', div_id);

    },
    checkTMheights: function() {return false;
        console.log($('#activetm tbody tr:not(.new, .addtmxrow):nth-child(-n+4)'));
        var h = 0;
        $('#activetm tbody tr:not(.new, .addtmxrow):nth-child(-n+4)').each(function() {
            h += $(this).height();
        })
        $('#activetm tbody').css('height', h + 'px');
        console.log(h);


    },
    checkTMKey: function(operation) {
        console.log('checkTMKey');
        console.log('operation: ', operation);

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
                    if(d.success == true) {
                        console.log('key is good');
                        console.log('adding a tm');
                        UI.execAddTM();
                        return true;
                    } else {
                        console.log('key is bad');
                        return false;
                        if(this == 'key') {
                            console.log('error adding a key');
                            $('#activetm tr.new .message').text(d.errors[0].message);
                        } else {
                            console.log('error adding a tm');
                            $('#activetm tr.new .message').text(d.errors[0].message);
                        }
                        return false;
                    }
                }
            });

        }

    },
    registerTMX: function () {
        if(!UI.TMKeysToAdd) UI.TMKeysToAdd = [];
        item = {};
        item.key = $('#new-tm-key').val();
        item.description = $('#new-tm-description').val();
        UI.TMKeysToAdd.push(item);
        $(".canceladdtmx").click();
    },
    execAddTM: function() {
        this.TMFileUpload($('#activetm .new .add-TM-Form')[0],'http://' + window.location.hostname + '/?action=addTM','uploadCallback', $('#activetm .new td.fileupload input').val());
    },
    execAddTMKey: function() {
//        var r = ($('#addtm-tr-read').is(':checked'))? 1 : 0;
//        var w = ($('#addtm-tr-write').is(':checked'))? 1 : 0;
        var TMKey = $('#new-tm-key').val();

        APP.doRequest({
            data: {
                action: 'addTM',
                exec: 'addTM',
                job_id: config.job_id,
                job_pass: config.password,
                tm_key: TMKey
//                r: r,
//                w: w
            },
            context: TMKey,
            error: function() {
                console.log('addTM error!!');
                $('#activetm tr.new .message').text('Error adding your TM!');
            },
            success: function(d) {
                console.log('addTM success!!');
                $('#activetm tr.new .message').text('The key ' + this + ' has been added!');
/*
                txt = (d.success == true)? 'The TM Key ' + this + ' has been added to your translation job.' : d.errors[0].message;
                $('.popup-addtm-tr .x-popup').click();
                UI.showMessage({
                    msg: txt
                });
                UI.clearAddTMpopup();
*/
            }
        });
    },
    clearAddTMRow: function() {
        $('#new-tm-key, #new-tm-description').val('');
        $('#activetm .fileupload').val('');
//        $('#uploadTMX').text('').hide();
        $('#activetm tr.new .message').text('');
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

//    document.getElementById(div_id).innerHTML = "Uploading...";
        $('.popup-addtm-tr .x-popup').click();
        UI.showMessage({
            msg: 'Uploading your TM...'
        });
        $('#messageBar .msg').after('<span class="progress"></span>');
        TMKey = $('#addtm-tr-key').val();
        TMName = $('#uploadTMX').text();
        console.log('TMKey 1: ', TMKey);
        console.log('TMName 1: ', TMName);
//    UI.pollForUploadProgress(TMKey, TMName);
        UI.pollForUploadCallback(TMKey, TMName);
    },

});
