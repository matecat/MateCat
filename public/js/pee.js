

(function ($) {
    PEE = {
        init: function () {
            this.tableGenerate();
            this.initTable();

            //        $("input[data-column='0']").attr("placeholder", "All");
            //        $("input[data-column='1']").attr("placeholder", "All");
            //        $("select[data-column='2']").val("All");
            //        $("select[data-column='3']").val("> 100.000");
            //        $("input[data-column='4']").attr("placeholder", ">50%");
            //
            //        $("select[data-column='2'] option ").first().remove();
            //        $("select[data-column='3'] option ").first().remove();

            $("#tablePEE").data('tablesorter').sortList = [ [0, 0], [1, 0] ];
            $("#tablePEE").trigger('update');
        },
        initTable: function() {
            $('#tablePEE')
                .bind('filterInit', function () {
                    // check that storage ulility is loadedBul
                    if ($.tablesorter.storage) {
                        // get saved filters
                        var f = $.tablesorter.storage(this, 'tablesorter-filters') || [];
                        $(this).trigger('search', [f]);
                    }
                })
                .bind('filterEnd', function () {
                    if ($.tablesorter.storage) {
                        // save current filters
                        var f = $(this).find('.tablesorter-filter').map(function () {
                            return $(this).val() || '';
                        }).get();
                        $.tablesorter.storage(this, 'tablesorter-filters', f);

                    }

                    var rowTotal = $('#tablePEE tr').length - 2;
                    var rowsFiltered = $('#tablePEE tbody tr.filtered').length;
                    if (rowTotal - rowsFiltered == 0) {
                        if ($('#no-results-row').length == 0) {
                            $("body").append("<p class='notfound' id='no-results-row'>No results found.</p>");
                        }
                    }
                    else if ($('#no-results-row').length > 0) {
                        $('#no-results-row').remove();
                    }
                })
                .tablesorter({
                    // *** APPEARANCE ***
                    // Add a theme - try 'blackice', 'blue', 'dark', 'default'
                    //        theme: 'blue',
                    // fix the column widths
                    widthFixed: true,
                    // include zebra and any other widgets, options:
                    // 'columns', 'filter', 'stickyHeaders' & 'resizable'
                    // 'uitheme' is another widget, but requires loading
                    // a different skin and a jQuery UI theme.
                    widgets: ['zebra', 'filter'],
                    widgetOptions: {
                        // zebra widget: adding zebra striping, using content and
                        // default styles - the ui css removes the background
                        // from default even and odd class names included for this
                        // demo to allow switching themes
                        // [ "even", "odd" ]
                        zebra: [
                            "ui-widget-content even",
                            "ui-state-default odd"
                        ],
                        // uitheme widget: * Updated! in tablesorter v2.4 **
                        // Instead of the array of icon class names, this option now
                        // contains the name of the theme. Currently jQuery UI ("jui")
                        // and Bootstrap ("bootstrap") themes are supported. To modify
                        // the class names used, extend from the themes variable
                        // look for the "$.extend($.tablesorter.themes.jui" code below
                        uitheme: 'jui',
                        // columns widget: change the default column class names
                        // primary is the 1st column sorted, secondary is the 2nd, etc
                        columns: [
                            "primary",
                            "secondary",
                            "tertiary"
                        ],
                        // columns widget: If true, the class names from the columns
                        // option will also be added to the table tfoot.
                        columns_tfoot: true,
                        // columns widget: If true, the class names from the columns
                        // option will also be added to the table thead.
                        columns_thead: true,
                        // filter widget: If there are child rows in the table (rows with
                        // class name from "cssChildRow" option) and this option is true
                        // and a match is found anywhere in the child row, then it will make
                        // that row visible; default is false
                        filter_childRows: false,
                        // filter widget: If true, a filter will be added to the top of
                        // each table column.
                        filter_columnFilters: true,
                        // filter widget: css class applied to the table row containing the
                        // filters & the inputs within that row
                        filter_cssFilter: "tablesorter-filter",
                        // filter widget: Customize the filter widget by adding a select
                        // dropdown with content, custom options or custom filter functions
                        // see http://goo.gl/HQQLW for more details
                        filter_functions: {
                            2: {
                                "All": function (e, n, f, i, $r, c, data) {
                                    return true;
                                },
                                "< 25": function (e, n, f, i, $r, c, data) {
                                    return n < 25;
                                },
                                "< 50": function (e, n, f, i, $r, c, data) {
                                    return n < 50;
                                },
                                "< 75": function (e, n, f, i, $r, c, data) {
                                    return n < 75;
                                }

                            },

                            3: {
                                "All": function (e, n, f, i, $r, c, data) {
                                    return true;
                                },
                                "> 100.000": function (e, n, f, i, $r, c, data) {
                                    return n > 100000;
                                },
                                "> 200.000": function (e, n, f, i, $r, c, data) {
                                    return n > 200000;
                                },
                                "> 500.000": function (e, n, f, i, $r, c, data) {
                                    return n > 500000;
                                },
                                "> 1.000.000": function (e, n, f, i, $r, c, data) {
                                    return n > 1000000;
                                },
                                "> 2.000.000": function (e, n, f, i, $r, c, data) {
                                    return n > 2000000;
                                },
                                "> 5.000.000": function (e, n, f, i, $r, c, data) {
                                    return n > 5000000;
                                }
                            },
                            8: {
                                "All": function (e, n, f, i, $r, c, data) {
                                    return true;
                                },
                                "100%_PUBLIC": function (e, n, f, i, $r, c, data) {
                                    return e == f;
                                },
                                "50%-74%": function (e, n, f, i, $r, c, data) {
                                    return e == f;
                                },
                                "75%-84%": function (e, n, f, i, $r, c, data) {
                                    return e == f;
                                },
                                "85%-94%": function (e, n, f, i, $r, c, data) {
                                    return e == f;
                                },
                                "95%-99%": function (e, n, f, i, $r, c, data) {
                                    return e == f;
                                },
                                "ALL": function (e, n, f, i, $r, c, data) {
                                    return e == f;
                                },
                                "INTERNAL": function (e, n, f, i, $r, c, data) {
                                    return e == f;
                                },
                                "MT_MyMemory": function (e, n, f, i, $r, c, data) {
                                    return e == f;
                                },
                                "NO_MATCH": function (e, n, f, i, $r, c, data) {
                                    return e == f;
                                },
                                "REPETITIONS": function (e, n, f, i, $r, c, data) {
                                    return e == f;
                                },
                            }
                        },
                        // filter widget: Set this option to true to hide the filter row
                        // initially. The rows is revealed by hovering over the filter
                        // row or giving any filter input/select focus.
                        filter_hideFilters: false,
                        // filter widget: Set this option to false to keep the searches
                        // case sensitive
                        filter_ignoreCase: true,
                        // filter widget: jQuery selector string of an element used to
                        // reset the filters.
                        filter_reset: null,
                        // Delay in milliseconds before the filter widget starts searching;
                        // This option prevents searching for every character while typing
                        // and should make searching large tables faster.
                        filter_searchDelay: 300,
                        // filter widget: Set this option to true to use the filter to find
                        // text from the start of the column. So typing in "a" will find
                        // "albert" but not "frank", both have a's; default is false
                        filter_startsWith: true,
                        // filter widget: If true, ALL filter searches will only use parsed
                        // data. To only use parsed data in specific columns, set this option
                        // to false and add class name "filter-parsed" to the header
                        filter_useParsedData: false,
                        // Resizable widget: If this option is set to false, resized column
                        // widths will not be saved. Previous saved values will be restored
                        // on page reload
                        resizable: true,
                        // saveSort widget: If this option is set to false, new sorts will
                        // not be saved. Any previous saved sort will be restored on page
                        // reload.
                        saveSort: true,
                        // stickyHeaders widget: css class name applied to the sticky header
                        stickyHeaders: "tablesorter-stickyHeader"
                    }
                });

        },
        tableGenerate : function () {
            var data = langStats;
            var i = 0;
            var html = '';
            $.each(data, function (i,elem) {
                html += '<tr><td id="col1">' +
                    elem.source +
                    '</td>' +
                    '<td id="col2">' +
                    elem.target +
                    '</td>' +
                    '<td id="col3">' +
                    '<div class="labelpee">'+elem.pee+'%</div>' +
                    '<div class="pee progressbar">' +
                    '<div class="progress" style="width:'+elem.pee+'%"></div>' +
                    '</div>'+
                    '</td>' +
                    '<td id="col4" style="text-align:right">' +
                    elem.totalwordPEE +
                    '</td> ' +
                    '<td id="col5">' +
                    elem.job_count +
                    '</td>' +
                    '<td id="col6">' +
                    elem.current_payable + '%' +
                    '</td>' +
                    '<td id="col7">' +
                    elem.payable_rate + '%' +
                    '</td>' +
                    '<td id="col8">' +
                    elem.saving_diff +
                    '</td>' +
                    '<td id="col9">' +
                    elem.fuzzy_band +
                    '</td></tr>';

            });
            $('#tablePEE tbody').append(html);
        }
    };


    $(document).ready(function (el) {
        PEE.init();
        $( 'div.selectContainer form select' ).on( 'change', function(){
            $( '#filterDateForm' ).submit();
        } );
    });
})($);
