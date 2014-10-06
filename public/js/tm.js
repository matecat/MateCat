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
            $( ".popup-tm" ).hide("slide", { direction: "right" }, 400);
            $("#SnapABug_Button").show();
            $(".outer-tm").hide();
        });

        $(".outer-tm").click(function() {
            $(".popup-tm").hide("slide", { direction: "right" }, 400);
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

        // script per fare apparire e scomparire la riga con l'upload della tmx


        $(".addtmx").click(function() {
            $(this).hide();
            var newRow = '<tr class="addtmxrow"><td colspan="5"><label class="fileupload">Select a TMX </label><input type="file" /></td><td><a class="pull-left btn-grey uploadtm"><span class="icon-upload"></span> Upload</a> <a class="btn-grey pull-left canceladdtmx"><span class="icon-times-circle"></span> Cancel</a> </td></tr>';
            $(this).closest("tr").after(newRow);
        })

        $('body').on('click', 'a.canceladdtmx', function() {
            $(".addtmxrow").hide();
            $(".addtmx").show();
        }).on('click', 'a.uploadtm', function() {
            $('.addtmxrow').hide().fadeOut();
            $(".clicked td.action").append('progressbar');
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
                $("#activetm").append(row);
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
            $("tr.new").show();
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
        console.log('VEDIAMO');
        $(".popup-tm").show("slide", { direction: "right" }, 400);
        console.log('A');
        $("#SnapABug_Button").hide();
        console.log('B');
        $(".outer-tm").show();
        console.log('C');
    },

});
