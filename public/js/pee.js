

(function ($) {
    PEE = {
        chartOptions: {
            chart: {
                title: 'Andamento PEE'
            },
            tooltip: {isHtml: true},
            width: '100%',
            height: 500,
            pointSize: 0,
            hAxis: {
                title: 'Months'
            },
            vAxis: {
                title: 'PEE'
            }
        },

        annotations: [
            {
                id: 1,
                name: "Neural",
                date: "2017-04",
                text: "Introduzione Neural</br> 6 Aprile 2017",
                langsPairs: [
                    {l1: "ar-SA",  l2: "en-GB"},
                    {l1: "zh-CN",  l2: "en-GB"},
                    {l1: "en-GB",  l2: "fr-FR"},
                    {l1: "en-GB",  l2: "de-DE"},
                    {l1: "en-GB",  l2: "hi-IN"},
                    {l1: "en-GB",  l2: "ja-JP"},
                    {l1: "en-GB",  l2: "ko-KR"},
                    {l1: "en-GB",  l2: "pl-PL"},
                    {l1: "en-GB",  l2: "pt-PT"},
                    {l1: "en-GB",  l2: "ru-RU"},
                    {l1: "en-GB",  l2: "es-ES"},
                    {l1: "en-GB",  l2: "th-TH"},
                    {l1: "en-GB",  l2: "tr-TR"},
                    {l1: "en-GB",  l2: "vi-VN"}
                ]
            },
            {
                id: 2,
                name: "Neural",
                date: "2017-04",
                text: "Introduzione Neural</br> 16 Aprile 2017",
                langsPairs: [
                    {l1: "en-GB",  l2: "it-IT"},
                ]
            },
            {
                id: 3,
                name: "Guess Tags",
                date: "2016-02",
                text: "Introduzione Guess Tags</br> 6 Maggio 2016",
                langsPairs: [
                    {l1: "en-GB",  l2: "es-ES"},
                    {l1: "en-GB",  l2: "de-DE"}
                ]
            }
        ],

        init: function () {
            this.tableGenerate();
            this.initFilters();
            this.initTable();
            this.initGraph();
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
        initFilters: function () {
            $('#source-lang, #target-lang, #fuzzy-select').dropdown({
                selectOnKeydown: false,
                fullTextSearch: 'exact'
            });

            $('.ui.accordion').accordion();

            $.fn.form.settings.rules.evaluateInterval = function(value) {
                var d1 = new Date(value);
                var endDate = $('.ui.form').form('get values', ['end_date']);
                var d2 = new Date(endDate.end_date);
                return d1.getTime() < d2.getTime();
            };
            $.fn.form.settings.rules.evaluateInterval2 = function(value) {
                var startDate = $('.ui.form').form('get values', ['start_date']);
                var d1 = new Date(startDate.start_date);
                var d2 = new Date(value);
                return d1.getTime() < d2.getTime();
            };

            $('.ui.form')
                .form({
                    fields: {
                        source: {
                            identifier: 'source_lang',
                            rules: [
                                {
                                    type   : 'minCount[1]',
                                    prompt : 'Please select at least one source language'
                                }
                            ]
                        },
                        target: {
                            identifier: 'target_lang',
                            rules: [
                                {
                                    type   : 'minCount[1]',
                                    prompt : 'Please select at least one target language'
                                }
                            ]
                        },
                        startDate: {
                            identifier: 'start_date',
                            rules: [
                                {
                                    type: 'evaluateInterval',
                                    prompt: 'Select a correct time interval'
                                }
                            ]
                        },
                        endDate: {
                            identifier: 'end_date',
                            rules: [
                                {
                                    type: 'evaluateInterval2',
                                    prompt: 'Select a correct time interval'
                                }
                            ]
                        }
                    }
                })
            ;

            $('#create-button').on('click', PEE.createGraph);
        },
        createGraph: function () {
            var $form = $('.ui.form'), fields = $form.form('get values', ['source_lang', 'target_lang', 'fuzzy_band',
                'start_date', 'end_date']);
            $form.form('validate form');
            if (!$form.hasClass('error')) {
                console.log(fields);
                PEE.addGraphFilterToUrl(fields);
                PEE.requestDataGraph(fields).done(function (data) {
                    PEE.createDataForGraph(data);
                });
            }

        },
        initGraph: function () {
            google.charts.load('current', {'packages':['corechart']});

            google.charts.setOnLoadCallback(PEE.drawDefaultChart);



        },
        requestDataGraph: function (fields) {
            var data = {
                sources: fields.source_lang,
                targets: fields.target_lang,
                month_interval: [fields.start_date, fields.end_date],
                fuzzy_band: fields.fuzzy_band
            };
            return $.ajax({
                data: data,
                type: "POST",
                url : "/api/app/utils/pee/graph"
            });
        },
        createDataForGraph: function (data) {
            var columns = [];
            var rows = [];
            var findLangName = function (code) {
                  return config.languages_array.find(function (element) {
                      return element.code === code;
                  }).name;
            };
            var findAnnotations = function (l1, l2, date) {
                return annotation = PEE.annotations.find(function (elem) {
                    return elem.date === date && (function (l1, l2, elem) {
                            return !!elem.langsPairs.find(function (item) {
                                return (item.l1.substring(0, 3) === l1.substring(0, 3) && item.l2.substring(0, 3) === l2.substring(0, 3)) ||
                                    (item.l1.substring(0, 3) === l2.substring(0, 3) && item.l2.substring(0, 3) === l1.substring(0, 3))
                            });
                        })(l1, l2, elem);
                });
            };
            data.lines.forEach(function (langs) {
                var column = findLangName(langs[0]) + "-" + findLangName(langs[1]) + " " + langs[2];
                columns.push(column)
            });
            data.dataSet.forEach(function (item) {
                var row = [];
                var properties = Object.keys(item);
                row.push(properties[0]);
                item[properties[0]].forEach(function (v, index) {
                    row.push(v);
                    var annotation = findAnnotations(data.lines[index][0], data.lines[index][1], properties[0]);
                    if (annotation) {
                        row.push(annotation.name);
                        row.push(annotation.text);
                    } else {
                        row.push(null);
                        row.push(null);
                    }

                });
                rows.push(row);
            });

            PEE.drawChart(columns, rows);
        },
        drawDefaultChart: function() {
            var fields = PEE.checkQueryStringFilterForGraph();
            if (!fields) {
                fields = {
                    source_lang: ["en-GB"],
                    target_lang: ["it-IT", "fr-FR"],
                    start_date: "2016-01",
                    end_date: "2017-05",
                };
            }

            PEE.requestDataGraph(fields).done(function (data) {
                PEE.createDataForGraph(data);
            });

        },
        drawChart: function(columns, rows) {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Months');
            columns.forEach(function (column) {
                data.addColumn('number', column);
                data.addColumn({type: 'string', role: 'annotation'});
                data.addColumn({type: 'string', role: 'annotationText', p: {html:true}});
            });

            data.addRows(rows);

            var chart = new google.visualization.LineChart(document.getElementById('myChart'));

            chart.draw(data, PEE.chartOptions);
        },
        initTable: function() {
            $('#tablePEE')
                .bind('filterInit', function () {
                    // check that storage ulility is loadedBul
                    PEE.checkQueryStringFilter.call(this);
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
                        if (history.pushState) {
                            var newurl = PEE.updateQueryStringParameter('filters', f.toString());
                            window.history.pushState({path:newurl},'',newurl);
                        }
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
                .bind('sortEnd', function () {
                    PEE.checkQueryStringFilter.call(this);
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
                var savingColor = (elem.saving_diff > 0) ? '#f3afaf' : '#bdeab5';
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
                    '<td id="col8" style="background-color: '+ savingColor +'">' +
                    elem.saving_diff +
                    '</td>' +
                    '<td id="col9">' +
                    elem.fuzzy_band +
                    '</td></tr>';

            });
            $('#tablePEE tbody').append(html);

        },
        checkQueryStringFilter: function () {
            var keyParam = APP.getParameterByName("filters");
            if (keyParam) {
                var filters = keyParam.split(',');
                //Check if present and enable it
                console.log(keyParam);
                if ($.tablesorter.storage) {
                    $.tablesorter.storage(this, 'tablesorter-filters', filters);
                }
            }
        },
        addGraphFilterToUrl: function (fields) {
            if (history.pushState) {
                var newurl;
                newurl = PEE.removeParam('gs');
                newurl =PEE.removeParam('gt', newurl);
                newurl =PEE.removeParam('gf', newurl);
                newurl =PEE.removeParam('gfrom', newurl);
                newurl =PEE.removeParam('gend', newurl);
                if (fields.source_lang) {
                    newurl = PEE.updateQueryStringParameter('gfilter', 1);
                    newurl = PEE.updateQueryStringParameter('gs', fields.source_lang.toString(), newurl);
                }
                if (fields.target_lang) {
                    newurl = PEE.updateQueryStringParameter('gt', fields.target_lang.toString(), newurl);
                }
                if (fields.fuzzy_band) {
                    newurl = PEE.updateQueryStringParameter('gf', fields.fuzzy_band.toString(), newurl);
                }
                if (fields.start_date) {
                    newurl = PEE.updateQueryStringParameter('gfrom', fields.start_date, newurl);
                }
                if (fields.end_date) {
                    newurl = PEE.updateQueryStringParameter('gend', fields.end_date, newurl);
                }
                window.history.pushState({path:newurl},'',newurl);
            }
        },
        checkQueryStringFilterForGraph: function () {
            var gFilters = APP.getParameterByName("gfilter");
            var fields;
            if (gFilters) {
                fields = {};
                fields.source_lang = APP.getParameterByName("gs").split(",");
                fields.target_lang = APP.getParameterByName("gt").split(",");
                fields.start_date = APP.getParameterByName("gfrom");
                fields.end_date = APP.getParameterByName("gend");
                if (APP.getParameterByName("gf")) {
                    fields.fuzzy_band = APP.getParameterByName("gf").split(",");
                }
            }
            return fields;
        },
        updateQueryStringParameter: function( key, value, uri) {
            value = encodeURI(value);
            if (!uri) {
                uri = document.location.href;
            }
            var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
            var separator = uri.indexOf('?') !== -1 ? "&" : "?";
            if (uri.match(re)) {
                return uri.replace(re, '$1' + key + "=" + value + '$2');
            }
            else {
                return uri + separator + key + "=" + value;
            }
        },
        removeParam: function(key, sourceURl) {
            if (!sourceURl) {
                sourceURL = document.location.href;
            }
            var rtn = sourceURL.split("?")[0],
                param,
                params_arr = [],
                queryString = (sourceURL.indexOf("?") !== -1) ? sourceURL.split("?")[1] : "";
            if (queryString !== "") {
                params_arr = queryString.split("&");
                for (var i = params_arr.length - 1; i >= 0; i -= 1) {
                    param = params_arr[i].split("=")[0];
                    if (param === key) {
                        params_arr.splice(i, 1);
                    }
                }
                rtn = rtn + "?" + params_arr.join("&");
            }
            return rtn;
        }
    };


    $(document).ready(function (el) {
        PEE.init();
        $( 'div.selectContainer form select' ).on( 'change', function(){
            $( '#filterDateForm' ).submit();
        } );
    });
})($);
