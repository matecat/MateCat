$( document ).ready( function()
{
    $( ".needitfaster" ).click( function() {
        var hours = new Date().getHours() + 1;
        hours += ( hours % 2 == 0 ) ? 1 : 0;
        hours = ( hours > 21 || hours < 7 ) ? 7 : hours;

        $( "#changeTimezone").addClass( "hide" );
        $('#forceDeliveryContainer').removeClass('hide');
        $( "#whenTime option[value='" + hours + "']" ).attr( "selected", "selected" );
        $( "#whenTimezone option[value='" + $( "#changeTimezone").val() + "']" ).attr( "selected", "selected" );
    });

    $('.modal.outsource .x-popup2').click(function () {
        $('#forceDeliveryContainer').addClass('hide');
    });

    $('.modal.outsource .btn-cancel').click(function() {
        prepareAndSubmitQuote( 0, true );
    });

    $( ".popup").on( "change", "#whenTime, #whenTimezone", function() {
        prepareAndSubmitQuote( getChosenDeliveryDate(), false );
    });


    $( ".popup").on( "click", ".datepickerDays a, .datepickerDays span", function() {
        prepareAndSubmitQuote( getChosenDeliveryDate(), false );
    });


    $( ".forceDeliveryButtonOk").click( function() {
        $('#forceDeliveryContainer').addClass('hide');
        $( "#changeTimezone").removeClass( "hide" );
    });

    
	var initLayout = function() {
	    var now = new Date();
	    now.setHours(0, 0, 0, 0);

	    var yesterday = new Date();
	    yesterday.setDate(yesterday.getDate() - 1);
	    yesterday.setHours(0, 0, 0, 0);

	    var tomorrow = new Date();
	    tomorrow.setDate(now.getDate() + 1);
	    tomorrow.setHours(0, 0, 0, 0);

	    var formattedNow = now.getFullYear() + "-" + (now.getMonth() + 1) + "-" + now.getDate();
	    var formattedTomorrow = tomorrow.getFullYear() + "-" + (tomorrow.getMonth() + 1) + "-" + tomorrow.getDate();

	    $('#date2').DatePicker({
	        flat: true,
	        date: formattedNow,
	        current: formattedNow,
	        format: 'Y-m-d',
	        calendars: 1,
	        mode: 'single',
	        view: 'days',

	        onBeforeShow: function() {
	            if ($('#inputDate').val() != $("#defaultAutoLabel").html()) {
	                $('#inputDate').DatePickerSetDate($('#inputDate').val(), true);
	            } else {
	                $('#inputDate').DatePickerSetDate(now, true);
	            }
	        },
	        onRender: function(date) {
	            return {
	                disabled: (date.valueOf() < now.valueOf()),
	                className: date.valueOf() == now.valueOf() ? '  datepickerSpecial' : false
	            }
	        },

	        onChange: function(formated, dates) {},
	        starts: 0
	    });
	};

	EYE.register(initLayout, 'init');
});



function getChosenDeliveryDate() {
    var day 	 = $( "#date2" ).DatePickerGetDate().getDate();
    var month 	 = $( "#date2" ).DatePickerGetDate().getMonth();
    var year 	 = $( "#date2" ).DatePickerGetDate().getFullYear();
    var time 	 = $( "#whenTime" ).val();
    var timezone = $( "#whenTimezone" ).val();

    return new Date ( year, month, day, time, 00 ).getTime() - ( parseFloat( timezone).toFixed( 1 ) * 3600000 ) - ( new Date().getTimezoneOffset() * 60000 );
}


function checkChosenDeliveryDate( chosenDate ) {
    var baseDate = new Date();
    var now = baseDate.getTime();

    return chosenDate > now;
}


function prepareAndSubmitQuote( chosenDate, hideNeedItFaster ) {
    if( chosenDate != 0 && !checkChosenDeliveryDate( chosenDate ) ) {
        $( "#delivery_manual_error").removeClass( "hide" );
        $('#delivery_before_time,#delivery_not_available').addClass('hide');
        $('.forceDeliveryButtonOk').addClass('disabled')
        return;
    }

    setCookie( "matecat_timezone", $( "#whenTimezone").val() );

    $( "#delivery_manual_error").addClass( "hide" );
    $('.forceDeliveryButtonOk').removeClass('disabled')
    $( "#forceDeliveryChosenDate").text( chosenDate );

    if( hideNeedItFaster ) {
        $('#forceDeliveryContainer').addClass('hide');
        $( "#changeTimezone").removeClass( "hide" );
    }

    var fullTranslateUrl = $(".onyourown a.uploadbtn").attr("href");
    $(".translate[href='" + fullTranslateUrl.substr(fullTranslateUrl.indexOf("/translate/")) + "']").trigger( "click" );
}


(function() {
    var cache = {};

    this.tmpl = function tmpl(str, data) {
        var fn = !/\W/.test(str) ?
            cache[str] = cache[str] ||
            tmpl(document.getElementById(str).innerHTML) :

            new Function("obj",
                "var p=[],print=function(){p.push.apply(p,arguments);};" +
                "with(obj){p.push('" +
                str
	                .replace(/[\r\t\n]/g, " ")
	                .split("<%").join("\t")
	                .replace(/((^|%>)[^\t]*)'/g, "$1\r")
	                .replace(/\t=(.*?)%>/g, "',$1,'")
	                .split("\t").join("');")
	                .split("%>").join("p.push('")
	                .split("\r").join("\\'") + "');}return p.join('');");

        return data ? fn(data) : fn;
    };
})();


(function($) {
    var EYE = window.EYE = function() {
        var _registered = {
            init: []
        };
        return {
            init: function() {
                $.each(_registered.init, function(nr, fn) {
                    fn.call();
                });
            },
            extend: function(prop) {
                for (var i in prop) {
                    if (prop[i] != undefined) {
                        this[i] = prop[i];
                    }
                }
            },
            register: function(fn, type) {
                if (!_registered[type]) {
                    _registered[type] = [];
                }
                _registered[type].push(fn);
            }
        };
    }();
    $(EYE.init);
})(jQuery);


(function($) {
    EYE.extend({
        getPosition: function(e, forceIt) {
            var x = 0;
            var y = 0;
            var es = e.style;
            var restoreStyles = false;
            if (forceIt && jQuery.curCSS(e, 'display') == 'none') {
                var oldVisibility = es.visibility;
                var oldPosition = es.position;
                restoreStyles = true;
                es.visibility = 'hidden';
                es.display = 'block';
                es.position = 'absolute';
            }
            var el = e;
            if (el.getBoundingClientRect) { // IE
                var box = el.getBoundingClientRect();
                x = box.left + Math.max(document.documentElement.scrollLeft, document.body.scrollLeft) - 2;
                y = box.top + Math.max(document.documentElement.scrollTop, document.body.scrollTop) - 2;
            } else {
                x = el.offsetLeft;
                y = el.offsetTop;
                el = el.offsetParent;
                if (e != el) {
                    while (el) {
                        x += el.offsetLeft;
                        y += el.offsetTop;
                        el = el.offsetParent;
                    }
                }
                if (jQuery.browser.safari && jQuery.curCSS(e, 'position') == 'absolute') {
                    x -= document.body.offsetLeft;
                    y -= document.body.offsetTop;
                }
                el = e.parentNode;
                while (el && el.tagName.toUpperCase() != 'BODY' && el.tagName.toUpperCase() != 'HTML') {
                    if (jQuery.curCSS(el, 'display') != 'inline') {
                        x -= el.scrollLeft;
                        y -= el.scrollTop;
                    }
                    el = el.parentNode;
                }
            }
            if (restoreStyles == true) {
                es.display = 'none';
                es.position = oldPosition;
                es.visibility = oldVisibility;
            }
            return {
                x: x,
                y: y
            };
        },
        getSize: function(e) {
            var w = parseInt(jQuery.curCSS(e, 'width'), 10);
            var h = parseInt(jQuery.curCSS(e, 'height'), 10);
            var wb = 0;
            var hb = 0;
            if (jQuery.curCSS(e, 'display') != 'none') {
                wb = e.offsetWidth;
                hb = e.offsetHeight;
            } else {
                var es = e.style;
                var oldVisibility = es.visibility;
                var oldPosition = es.position;
                es.visibility = 'hidden';
                es.display = 'block';
                es.position = 'absolute';
                wb = e.offsetWidth;
                hb = e.offsetHeight;
                es.display = 'none';
                es.position = oldPosition;
                es.visibility = oldVisibility;
            }
            return {
                w: w,
                h: h,
                wb: wb,
                hb: hb
            };
        },
        getClient: function(e) {
            var h, w;
            if (e) {
                w = e.clientWidth;
                h = e.clientHeight;
            } else {
                var de = document.documentElement;
                w = window.innerWidth || self.innerWidth || (de && de.clientWidth) || document.body.clientWidth;
                h = window.innerHeight || self.innerHeight || (de && de.clientHeight) || document.body.clientHeight;
            }
            return {
                w: w,
                h: h
            };
        },
        getScroll: function(e) {
            var t = 0,
                l = 0,
                w = 0,
                h = 0,
                iw = 0,
                ih = 0;
            if (e && e.nodeName.toLowerCase() != 'body') {
                t = e.scrollTop;
                l = e.scrollLeft;
                w = e.scrollWidth;
                h = e.scrollHeight;
            } else {
                if (document.documentElement) {
                    t = document.documentElement.scrollTop;
                    l = document.documentElement.scrollLeft;
                    w = document.documentElement.scrollWidth;
                    h = document.documentElement.scrollHeight;
                } else if (document.body) {
                    t = document.body.scrollTop;
                    l = document.body.scrollLeft;
                    w = document.body.scrollWidth;
                    h = document.body.scrollHeight;
                }
                if (typeof pageYOffset != 'undefined') {
                    t = pageYOffset;
                    l = pageXOffset;
                }
                iw = self.innerWidth || document.documentElement.clientWidth || document.body.clientWidth || 0;
                ih = self.innerHeight || document.documentElement.clientHeight || document.body.clientHeight || 0;
            }
            return {
                t: t,
                l: l,
                w: w,
                h: h,
                iw: iw,
                ih: ih
            };
        },
        getMargins: function(e, toInteger) {
            var t = jQuery.curCSS(e, 'marginTop') || '';
            var r = jQuery.curCSS(e, 'marginRight') || '';
            var b = jQuery.curCSS(e, 'marginBottom') || '';
            var l = jQuery.curCSS(e, 'marginLeft') || '';
            if (toInteger)
                return {
                    t: parseInt(t, 10) || 0,
                    r: parseInt(r, 10) || 0,
                    b: parseInt(b, 10) || 0,
                    l: parseInt(l, 10)
                };
            else
                return {
                    t: t,
                    r: r,
                    b: b,
                    l: l
                };
        },
        getPadding: function(e, toInteger) {
            var t = jQuery.curCSS(e, 'paddingTop') || '';
            var r = jQuery.curCSS(e, 'paddingRight') || '';
            var b = jQuery.curCSS(e, 'paddingBottom') || '';
            var l = jQuery.curCSS(e, 'paddingLeft') || '';
            if (toInteger)
                return {
                    t: parseInt(t, 10) || 0,
                    r: parseInt(r, 10) || 0,
                    b: parseInt(b, 10) || 0,
                    l: parseInt(l, 10)
                };
            else
                return {
                    t: t,
                    r: r,
                    b: b,
                    l: l
                };
        },
        getBorder: function(e, toInteger) {
            var t = jQuery.curCSS(e, 'borderTopWidth') || '';
            var r = jQuery.curCSS(e, 'borderRightWidth') || '';
            var b = jQuery.curCSS(e, 'borderBottomWidth') || '';
            var l = jQuery.curCSS(e, 'borderLeftWidth') || '';
            if (toInteger)
                return {
                    t: parseInt(t, 10) || 0,
                    r: parseInt(r, 10) || 0,
                    b: parseInt(b, 10) || 0,
                    l: parseInt(l, 10) || 0
                };
            else
                return {
                    t: t,
                    r: r,
                    b: b,
                    l: l
                };
        },
        traverseDOM: function(nodeEl, func) {
            func(nodeEl);
            nodeEl = nodeEl.firstChild;
            while (nodeEl) {
                EYE.traverseDOM(nodeEl, func);
                nodeEl = nodeEl.nextSibling;
            }
        },
        getInnerWidth: function(el, scroll) {
            var offsetW = el.offsetWidth;
            return scroll ? Math.max(el.scrollWidth, offsetW) - offsetW + el.clientWidth : el.clientWidth;
        },
        getInnerHeight: function(el, scroll) {
            var offsetH = el.offsetHeight;
            return scroll ? Math.max(el.scrollHeight, offsetH) - offsetH + el.clientHeight : el.clientHeight;
        },
        getExtraWidth: function(el) {
            if ($.boxModel)
                return (parseInt($.curCSS(el, 'paddingLeft')) || 0) + (parseInt($.curCSS(el, 'paddingRight')) || 0) + (parseInt($.curCSS(el, 'borderLeftWidth')) || 0) + (parseInt($.curCSS(el, 'borderRightWidth')) || 0);
            return 0;
        },
        getExtraHeight: function(el) {
            if ($.boxModel)
                return (parseInt($.curCSS(el, 'paddingTop')) || 0) + (parseInt($.curCSS(el, 'paddingBottom')) || 0) + (parseInt($.curCSS(el, 'borderTopWidth')) || 0) + (parseInt($.curCSS(el, 'borderBottomWidth')) || 0);
            return 0;
        },
        isChildOf: function(parentEl, el, container) {
            if (parentEl == el) {
                return true;
            }
            if (!el || !el.nodeType || el.nodeType != 1) {
                return false;
            }
            if (parentEl.contains && !$.browser.safari) {
                return parentEl.contains(el);
            }
            if (parentEl.compareDocumentPosition) {
                return !!(parentEl.compareDocumentPosition(el) & 16);
            }
            var prEl = el.parentNode;
            while (prEl && prEl != container) {
                if (prEl == parentEl)
                    return true;
                prEl = prEl.parentNode;
            }
            return false;
        },
        centerEl: function(el, axis) {
            var clientScroll = EYE.getScroll();
            var size = EYE.getSize(el);
            if (!axis || axis == 'vertically')
                $(el).css({
                    top: clientScroll.t + ((Math.min(clientScroll.h, clientScroll.ih) - size.hb) / 2) + 'px'
                });
            if (!axis || axis == 'horizontally')
                $(el).css({
                    left: clientScroll.l + ((Math.min(clientScroll.w, clientScroll.iw) - size.wb) / 2) + 'px'
                });
        }
    });
    if (!$.easing.easeout) {
        $.easing.easeout = function(p, n, firstNum, delta, duration) {
            return -delta * ((n = n / duration - 1) * n * n * n - 1) + firstNum;
        };
    }
})(jQuery);


(function($) {
    var DatePicker = function() {
        var ids = {},
            views = {
                years: 'datepickerViewYears',
                moths: 'datepickerViewMonths',
                days: 'datepickerViewDays'
            },
            tpl = {
                wrapper: '<div class="datepicker"><div class="datepickerBorderT" /><div class="datepickerBorderB" /><div class="datepickerBorderL" /><div class="datepickerBorderR" /><div class="datepickerBorderTL" /><div class="datepickerBorderTR" /><div class="datepickerBorderBL" /><div class="datepickerBorderBR" /><div class="datepickerContainer"><table cellspacing="0" cellpadding="0"><tbody><tr></tr></tbody></table></div></div>',
                head: [
                    '<td>',
                    '<table cellspacing="0" cellpadding="0">',
                    '<thead>',
                    '<tr>',
                    '<th class="datepickerGoPrev"><a href="#"><span><%=prev%></span></a></th>',
                    '<th colspan="5" class="datepickerMonth "><a class="datepickerDisabled" href="#" onclick="return false;" ><span></span></a></th>',
                    '<th class="datepickerGoNext"><a href="#"><span><%=next%></span></a></th>',
                    '</tr>',
                    '<tr class="datepickerDoW">',
                    '<th><span><%=week%></span></th>',
                    '<th><span><%=day1%></span></th>',
                    '<th><span><%=day2%></span></th>',
                    '<th><span><%=day3%></span></th>',
                    '<th><span><%=day4%></span></th>',
                    '<th><span><%=day5%></span></th>',
                    '<th><span><%=day6%></span></th>',
                    '<th><span><%=day7%></span></th>',
                    '</tr>',
                    '</thead>',
                    '</table></td>'
                ],
                space: '<td class="datepickerSpace"><div></div></td>',
                days: [
                    '<tbody class="datepickerDays">',
                    '<tr>',
                    '<th class="datepickerWeek"><a href="#"><span><%=weeks[0].week%></span></a></th>',
                    '<td class="<%=weeks[0].days[0].classname%>"><a href="#"><span><%=weeks[0].days[0].text%></span></a></td>',
                    '<td class="<%=weeks[0].days[1].classname%>"><a href="#"><span><%=weeks[0].days[1].text%></span></a></td>',
                    '<td class="<%=weeks[0].days[2].classname%>"><a href="#"><span><%=weeks[0].days[2].text%></span></a></td>',
                    '<td class="<%=weeks[0].days[3].classname%>"><a href="#"><span><%=weeks[0].days[3].text%></span></a></td>',
                    '<td class="<%=weeks[0].days[4].classname%>"><a href="#"><span><%=weeks[0].days[4].text%></span></a></td>',
                    '<td class="<%=weeks[0].days[5].classname%>"><a href="#"><span><%=weeks[0].days[5].text%></span></a></td>',
                    '<td class="<%=weeks[0].days[6].classname%>"><a href="#"><span><%=weeks[0].days[6].text%></span></a></td>',
                    '</tr>',
                    '<tr>',
                    '<th class="datepickerWeek"><a href="#"><span><%=weeks[1].week%></span></a></th>',
                    '<td class="<%=weeks[1].days[0].classname%>"><a href="#"><span><%=weeks[1].days[0].text%></span></a></td>',
                    '<td class="<%=weeks[1].days[1].classname%>"><a href="#"><span><%=weeks[1].days[1].text%></span></a></td>',
                    '<td class="<%=weeks[1].days[2].classname%>"><a href="#"><span><%=weeks[1].days[2].text%></span></a></td>',
                    '<td class="<%=weeks[1].days[3].classname%>"><a href="#"><span><%=weeks[1].days[3].text%></span></a></td>',
                    '<td class="<%=weeks[1].days[4].classname%>"><a href="#"><span><%=weeks[1].days[4].text%></span></a></td>',
                    '<td class="<%=weeks[1].days[5].classname%>"><a href="#"><span><%=weeks[1].days[5].text%></span></a></td>',
                    '<td class="<%=weeks[1].days[6].classname%>"><a href="#"><span><%=weeks[1].days[6].text%></span></a></td>',
                    '</tr>',
                    '<tr>',
                    '<th class="datepickerWeek"><a href="#"><span><%=weeks[2].week%></span></a></th>',
                    '<td class="<%=weeks[2].days[0].classname%>"><a href="#"><span><%=weeks[2].days[0].text%></span></a></td>',
                    '<td class="<%=weeks[2].days[1].classname%>"><a href="#"><span><%=weeks[2].days[1].text%></span></a></td>',
                    '<td class="<%=weeks[2].days[2].classname%>"><a href="#"><span><%=weeks[2].days[2].text%></span></a></td>',
                    '<td class="<%=weeks[2].days[3].classname%>"><a href="#"><span><%=weeks[2].days[3].text%></span></a></td>',
                    '<td class="<%=weeks[2].days[4].classname%>"><a href="#"><span><%=weeks[2].days[4].text%></span></a></td>',
                    '<td class="<%=weeks[2].days[5].classname%>"><a href="#"><span><%=weeks[2].days[5].text%></span></a></td>',
                    '<td class="<%=weeks[2].days[6].classname%>"><a href="#"><span><%=weeks[2].days[6].text%></span></a></td>',
                    '</tr>',
                    '<tr>',
                    '<th class="datepickerWeek"><a href="#"><span><%=weeks[3].week%></span></a></th>',
                    '<td class="<%=weeks[3].days[0].classname%>"><a href="#"><span><%=weeks[3].days[0].text%></span></a></td>',
                    '<td class="<%=weeks[3].days[1].classname%>"><a href="#"><span><%=weeks[3].days[1].text%></span></a></td>',
                    '<td class="<%=weeks[3].days[2].classname%>"><a href="#"><span><%=weeks[3].days[2].text%></span></a></td>',
                    '<td class="<%=weeks[3].days[3].classname%>"><a href="#"><span><%=weeks[3].days[3].text%></span></a></td>',
                    '<td class="<%=weeks[3].days[4].classname%>"><a href="#"><span><%=weeks[3].days[4].text%></span></a></td>',
                    '<td class="<%=weeks[3].days[5].classname%>"><a href="#"><span><%=weeks[3].days[5].text%></span></a></td>',
                    '<td class="<%=weeks[3].days[6].classname%>"><a href="#"><span><%=weeks[3].days[6].text%></span></a></td>',
                    '</tr>',
                    '<tr>',
                    '<th class="datepickerWeek"><a href="#"><span><%=weeks[4].week%></span></a></th>',
                    '<td class="<%=weeks[4].days[0].classname%>"><a href="#"><span><%=weeks[4].days[0].text%></span></a></td>',
                    '<td class="<%=weeks[4].days[1].classname%>"><a href="#"><span><%=weeks[4].days[1].text%></span></a></td>',
                    '<td class="<%=weeks[4].days[2].classname%>"><a href="#"><span><%=weeks[4].days[2].text%></span></a></td>',
                    '<td class="<%=weeks[4].days[3].classname%>"><a href="#"><span><%=weeks[4].days[3].text%></span></a></td>',
                    '<td class="<%=weeks[4].days[4].classname%>"><a href="#"><span><%=weeks[4].days[4].text%></span></a></td>',
                    '<td class="<%=weeks[4].days[5].classname%>"><a href="#"><span><%=weeks[4].days[5].text%></span></a></td>',
                    '<td class="<%=weeks[4].days[6].classname%>"><a href="#"><span><%=weeks[4].days[6].text%></span></a></td>',
                    '</tr>',
                    '<tr>',
                    '<th class="datepickerWeek"><a href="#"><span><%=weeks[5].week%></span></a></th>',
                    '<td class="<%=weeks[5].days[0].classname%>"><a href="#"><span><%=weeks[5].days[0].text%></span></a></td>',
                    '<td class="<%=weeks[5].days[1].classname%>"><a href="#"><span><%=weeks[5].days[1].text%></span></a></td>',
                    '<td class="<%=weeks[5].days[2].classname%>"><a href="#"><span><%=weeks[5].days[2].text%></span></a></td>',
                    '<td class="<%=weeks[5].days[3].classname%>"><a href="#"><span><%=weeks[5].days[3].text%></span></a></td>',
                    '<td class="<%=weeks[5].days[4].classname%>"><a href="#"><span><%=weeks[5].days[4].text%></span></a></td>',
                    '<td class="<%=weeks[5].days[5].classname%>"><a href="#"><span><%=weeks[5].days[5].text%></span></a></td>',
                    '<td class="<%=weeks[5].days[6].classname%>"><a href="#"><span><%=weeks[5].days[6].text%></span></a></td>',
                    '</tr>',
                    '</tbody>'
                ],
                months: [
                    '<tbody class="<%=className%>">',
                    '<tr>',
                    '<td colspan="2"><a href="#"><span><%=data[0]%></span></a></td>',
                    '<td colspan="2"><a href="#"><span><%=data[1]%></span></a></td>',
                    '<td colspan="2"><a href="#"><span><%=data[2]%></span></a></td>',
                    '<td colspan="2"><a href="#"><span><%=data[3]%></span></a></td>',
                    '</tr>',
                    '<tr>',
                    '<td colspan="2"><a href="#"><span><%=data[4]%></span></a></td>',
                    '<td colspan="2"><a href="#"><span><%=data[5]%></span></a></td>',
                    '<td colspan="2"><a href="#"><span><%=data[6]%></span></a></td>',
                    '<td colspan="2"><a href="#"><span><%=data[7]%></span></a></td>',
                    '</tr>',
                    '<tr>',
                    '<td colspan="2"><a href="#"><span><%=data[8]%></span></a></td>',
                    '<td colspan="2"><a href="#"><span><%=data[9]%></span></a></td>',
                    '<td colspan="2"><a href="#"><span><%=data[10]%></span></a></td>',
                    '<td colspan="2"><a href="#"><span><%=data[11]%></span></a></td>',
                    '</tr>',
                    '</tbody>'
                ]
            },
            defaults = {
                flat: false,
                starts: 1,
                prev: '&#9664;',
                next: '&#9654;',
                lastSel: false,
                mode: 'single',
                view: 'days',
                calendars: 1,
                format: 'Y-m-d',
                position: 'bottom',
                eventName: 'click',
                onRender: function() {
                    return {};
                },
                onChange: function() {
                    return true;
                },
                onShow: function() {
                    return true;
                },
                onBeforeShow: function() {
                    return true;
                },
                onHide: function() {
                    return true;
                },
                locale: {
                    days: ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"],
                    daysShort: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
                    daysMin: ["Su", "Mo", "Tu", "We", "Th", "Fr", "Sa", "Su"],
                    months: ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
                    monthsShort: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
                    weekMin: 'wk'
                }

            },



            fill = function(el) {
                var options = $(el).data('datepicker');
                var cal = $(el);
                var currentCal = Math.floor(options.calendars / 2),
                    date, data, dow, month, cnt = 0,
                    week, days, indic, indic2, html, tblCal;
                cal.find('td>table tbody').remove();
                for (var i = 0; i < options.calendars; i++) {
                    date = new Date(options.current);
                    date.addMonths(-currentCal + i);
                    tblCal = cal.find('table').eq(i + 1);
                    switch (tblCal[0].className) {
                        case 'datepickerViewDays':
                            dow = formatDate(date, 'B, Y');
                            break;
                        case 'datepickerViewMonths':
                            dow = date.getFullYear();
                            break;
                        case 'datepickerViewYears':
                            dow = (date.getFullYear() - 6) + ' - ' + (date.getFullYear() + 5);
                            break;
                    }
                    tblCal.find('thead tr:first th:eq(1) span').text(dow);
                    dow = date.getFullYear() - 6;
                    data = {
                        data: [],
                        className: 'datepickerYears'
                    }
                    for (var j = 0; j < 12; j++) {
                        data.data.push(dow + j);
                    }
                    html = tmpl(tpl.months.join(''), data);
                    date.setDate(1);
                    data = {
                        weeks: [],
                        test: 10
                    };
                    month = date.getMonth();
                    var dow = (date.getDay() - options.starts) % 7;
                    date.addDays(-(dow + (dow < 0 ? 7 : 0)));
                    week = -1;
                    cnt = 0;
                    while (cnt < 42) {
                        indic = parseInt(cnt / 7, 10);
                        indic2 = cnt % 7;
                        if (!data.weeks[indic]) {
                            week = date.getWeekNumber();
                            data.weeks[indic] = {
                                week: week,
                                days: []
                            };
                        }
                        data.weeks[indic].days[indic2] = {
                            text: date.getDate(),
                            classname: []
                        };
                        if (month != date.getMonth()) {
                            data.weeks[indic].days[indic2].classname.push('datepickerNotInMonth');
                        }
                        if (date.getDay() == 0) {
                            data.weeks[indic].days[indic2].classname.push('datepickerSunday');
                        }
                        if (date.getDay() == 6) {
                            data.weeks[indic].days[indic2].classname.push('datepickerSaturday');
                        }
                        var fromUser = options.onRender(date);
                        var val = date.valueOf();
                        if (fromUser.selected || options.date == val || $.inArray(val, options.date) > -1 || (options.mode == 'range' && val >= options.date[0] && val <= options.date[1])) {
                            data.weeks[indic].days[indic2].classname.push('datepickerSelected');
                        }
                        if (fromUser.disabled) {
                            data.weeks[indic].days[indic2].classname.push('datepickerDisabled');
                        }
                        if (fromUser.className) {
                            data.weeks[indic].days[indic2].classname.push(fromUser.className);
                        }
                        data.weeks[indic].days[indic2].classname = data.weeks[indic].days[indic2].classname.join(' ');
                        cnt++;
                        date.addDays(1);
                    }
                    html = tmpl(tpl.days.join(''), data) + html;
                    data = {
                        data: options.locale.monthsShort,
                        className: 'datepickerMonths'
                    };
                    html = tmpl(tpl.months.join(''), data) + html;
                    tblCal.append(html);
                }
            },
            parseDate = function(date, format) {
                if (date.constructor == Date) {
                    return new Date(date);
                }
                var parts = date.split(/\W+/);
                var against = format.split(/\W+/),
                    d, m, y, h, min, now = new Date();
                for (var i = 0; i < parts.length; i++) {
                    switch (against[i]) {
                        case 'd':
                        case 'e':
                            d = parseInt(parts[i], 10);
                            break;
                        case 'm':
                            m = parseInt(parts[i], 10) - 1;
                            break;
                        case 'Y':
                        case 'y':
                            y = parseInt(parts[i], 10);
                            y += y > 100 ? 0 : (y < 29 ? 2000 : 1900);
                            break;
                        case 'H':
                        case 'I':
                        case 'k':
                        case 'l':
                            h = parseInt(parts[i], 10);
                            break;
                        case 'P':
                        case 'p':
                            if (/pm/i.test(parts[i]) && h < 12) {
                                h += 12;
                            } else if (/am/i.test(parts[i]) && h >= 12) {
                                h -= 12;
                            }
                            break;
                        case 'M':
                            min = parseInt(parts[i], 10);
                            break;
                    }
                }
                return new Date(
                    y === undefined ? now.getFullYear() : y,
                    m === undefined ? now.getMonth() : m,
                    d === undefined ? now.getDate() : d,
                    h === undefined ? now.getHours() : h,
                    min === undefined ? now.getMinutes() : min,
                    0
                );
            },
            formatDate = function(date, format) {
                var m = date.getMonth();
                var d = date.getDate();
                var y = date.getFullYear();
                var wn = date.getWeekNumber();
                var w = date.getDay();
                var s = {};
                var hr = date.getHours();
                var pm = (hr >= 12);
                var ir = (pm) ? (hr - 12) : hr;
                var dy = date.getDayOfYear();
                if (ir == 0) {
                    ir = 12;
                }
                var min = date.getMinutes();
                var sec = date.getSeconds();
                var parts = format.split(''),
                    part;
                for (var i = 0; i < parts.length; i++) {
                    part = parts[i];
                    switch (parts[i]) {
                        case 'a':
                            part = date.getDayName();
                            break;
                        case 'A':
                            part = date.getDayName(true);
                            break;
                        case 'b':
                            part = date.getMonthName();
                            break;
                        case 'B':
                            part = date.getMonthName(true);
                            break;
                        case 'C':
                            part = 1 + Math.floor(y / 100);
                            break;
                        case 'd':
                            part = (d < 10) ? ("0" + d) : d;
                            break;
                        case 'e':
                            part = d;
                            break;
                        case 'H':
                            part = (hr < 10) ? ("0" + hr) : hr;
                            break;
                        case 'I':
                            part = (ir < 10) ? ("0" + ir) : ir;
                            break;
                        case 'j':
                            part = (dy < 100) ? ((dy < 10) ? ("00" + dy) : ("0" + dy)) : dy;
                            break;
                        case 'k':
                            part = hr;
                            break;
                        case 'l':
                            part = ir;
                            break;
                        case 'm':
                            part = (m < 9) ? ("0" + (1 + m)) : (1 + m);
                            break;
                        case 'M':
                            part = (min < 10) ? ("0" + min) : min;
                            break;
                        case 'p':
                        case 'P':
                            part = pm ? "PM" : "AM";
                            break;
                        case 's':
                            part = Math.floor(date.getTime() / 1000);
                            break;
                        case 'S':
                            part = (sec < 10) ? ("0" + sec) : sec;
                            break;
                        case 'u':
                            part = w + 1;
                            break;
                        case 'w':
                            part = w;
                            break;
                        case 'y':
                            part = ('' + y).substr(2, 2);
                            break;
                        case 'Y':
                            part = y;
                            break;
                    }
                    parts[i] = part;
                }
                return parts.join('');
            },
            extendDate = function(options) {
                if (Date.prototype.tempDate) {
                    return;
                }
                Date.prototype.tempDate = null;
                Date.prototype.months = options.locale.months;
                Date.prototype.monthsShort = options.locale.monthsShort;
                Date.prototype.days = options.locale.days;
                Date.prototype.daysShort = options.locale.daysShort;

                /*
		   Date.prototype.months_it=options.locale_it.months;
		   Date.prototype.monthsShort_it=options.locale_it.monthsShort;
		   Date.prototype.days_it=options.locale_it.days;
		   Date.prototype.daysShort_it=options.locale_it.daysShort;

		   Date.prototype.months_fr=options.locale_fr.months;
		   Date.prototype.monthsShort_fr=options.locale_fr.monthsShort;
		   Date.prototype.days_fr= options.locale_fr.days;
		   Date.prototype.daysShort_fr=options.locale_fr.daysShort;


		   Date.prototype.months_de=options.locale_de.months;
		   Date.prototype.monthsShort_de=options.locale_de.monthsShort;
		   Date.prototype.days_de=options.locale_de.days;
		   Date.prototype.daysShort_de=options.locale_de.daysShort;

		   Date.prototype.months_es=options.locale_es.months;
		   Date.prototype.monthsShort_es=options.locale_es.monthsShort;
		   Date.prototype.days_es=options.locale_es.days;
		   Date.prototype.daysShort_es=options.locale_es.daysShort;

		   Date.prototype.months_sv=options.locale_sv.months;
		   Date.prototype.monthsShort_sv=options.locale_sv.monthsShort;
		   Date.prototype.days_sv=options.locale_sv.days;
		   Date.prototype.daysShort_sv=options.locale_sv.daysShort;
		 */

                /*
		   Date.prototype.months_<lang>=options.locale_<lang>.months;
		   Date.prototype.monthsShort_<lang>=options.locale_<lang>.monthsShort;
		   Date.prototype.days_<lang>=options.locale_<lang>.days;
		   Date.prototype.daysShort_<lang>=options.locale_<lang>.daysShort;
		 */


                Date.prototype.getMonthName = function(fullName) {
                    return this[fullName ? 'months' : 'monthsShort'][this.getMonth()];
                };
                Date.prototype.getDayName = function(fullName) {
                    return this[fullName ? 'days' : 'daysShort'][this.getDay()];
                };
                Date.prototype.addDays = function(n) {
                    this.setDate(this.getDate() + n);
                    this.tempDate = this.getDate();
                };
                Date.prototype.addMonths = function(n) {
                    if (this.tempDate == null) {
                        this.tempDate = this.getDate();
                    }
                    this.setDate(1);
                    this.setMonth(this.getMonth() + n);
                    this.setDate(Math.min(this.tempDate, this.getMaxDays()));
                };
                Date.prototype.addYears = function(n) {
                    if (this.tempDate == null) {
                        this.tempDate = this.getDate();
                    }
                    this.setDate(1);
                    this.setFullYear(this.getFullYear() + n);
                    this.setDate(Math.min(this.tempDate, this.getMaxDays()));
                };
                Date.prototype.getMaxDays = function() {
                    var tmpDate = new Date(Date.parse(this)),
                        d = 28,
                        m;
                    m = tmpDate.getMonth();
                    d = 28;
                    while (tmpDate.getMonth() == m) {
                        d++;
                        tmpDate.setDate(d);
                    }
                    return d - 1;
                };
                Date.prototype.getFirstDay = function() {
                    var tmpDate = new Date(Date.parse(this));
                    tmpDate.setDate(1);
                    return tmpDate.getDay();
                };
                Date.prototype.getWeekNumber = function() {
                    var tempDate = new Date(this);
                    tempDate.setDate(tempDate.getDate() - (tempDate.getDay() + 6) % 7 + 3);
                    var dms = tempDate.valueOf();
                    tempDate.setMonth(0);
                    tempDate.setDate(4);
                    return Math.round((dms - tempDate.valueOf()) / (604800000)) + 1;
                };
                Date.prototype.getDayOfYear = function() {
                    var now = new Date(this.getFullYear(), this.getMonth(), this.getDate(), 0, 0, 0);
                    var then = new Date(this.getFullYear(), 0, 0, 0, 0, 0);
                    var time = now - then;
                    return Math.floor(time / 24 * 60 * 60 * 1000);
                };

            },
            layout = function(el) {
                var options = $(el).data('datepicker');
                var cal = $('#' + options.id);
                if (!options.extraHeight) {
                    var divs = $(el).find('div');
                    options.extraHeight = divs.get(0).offsetHeight + divs.get(1).offsetHeight;
                    options.extraWidth = divs.get(2).offsetWidth + divs.get(3).offsetWidth;
                }
                var tbl = cal.find('table:first').get(0);
                var width = tbl.offsetWidth;
                var height = tbl.offsetHeight;
                cal.css({
                    width: width + options.extraWidth + 'px',
                    height: height + options.extraHeight + 'px'
                }).find('div.datepickerContainer').css({
                    width: width + 'px',
                    height: height + 'px'
                });
            },
            click = function(ev) {
                var clickedOnAcceptableDay = false;
                if ($(ev.target).is('span')) {
                    ev.target = ev.target.parentNode;
                }

                var el = $(ev.target);
                if (el.is('a')) {
                    ev.target.blur();
                    if (el.hasClass('datepickerDisabled')) {
                        return false;
                    }
                    var options = $(this).data('datepicker');
                    var parentEl = el.parent();
                    var tblEl = parentEl.parent().parent().parent();
                    var tblIndex = $('table', this).index(tblEl.get(0)) - 1;
                    var tmp = new Date(options.current);
                    var changed = false;
                    var fillIt = false;
                    if (parentEl.is('th')) {
                        if (parentEl.hasClass('datepickerWeek') && options.mode == 'range' && !parentEl.next().hasClass('datepickerDisabled')) {
                            var val = parseInt(parentEl.next().text(), 10);
                            tmp.addMonths(tblIndex - Math.floor(options.calendars / 2));
                            if (parentEl.next().hasClass('datepickerNotInMonth')) {
                                tmp.addMonths(val > 15 ? -1 : 1);
                            }
                            tmp.setDate(val);
                            options.date[0] = (tmp.setHours(0, 0, 0, 0)).valueOf();
                            tmp.setHours(23, 59, 59, 0);
                            tmp.addDays(6);
                            options.date[1] = tmp.valueOf();
                            fillIt = true;
                            changed = true;
                            options.lastSel = false;
                        } else if (parentEl.hasClass('datepickerMonth')) {
                            tmp.addMonths(tblIndex - Math.floor(options.calendars / 2));
                            switch (tblEl.get(0).className) {
                                case 'datepickerViewDays':
                                    tblEl.get(0).className = 'datepickerViewMonths';
                                    el.find('span').text(tmp.getFullYear());
                                    break;
                                case 'datepickerViewMonths':
                                    tblEl.get(0).className = 'datepickerViewYears';
                                    el.find('span').text((tmp.getFullYear() - 6) + ' - ' + (tmp.getFullYear() + 5));
                                    break;
                                case 'datepickerViewYears':
                                    tblEl.get(0).className = 'datepickerViewDays';
                                    el.find('span').text(formatDate(tmp, 'B, Y'));
                                    break;
                            }
                        } else if (parentEl.parent().parent().is('thead')) {
                            switch (tblEl.get(0).className) {
                                case 'datepickerViewDays':
                                    options.current.addMonths(parentEl.hasClass('datepickerGoPrev') ? -1 : 1);
                                    break;
                                case 'datepickerViewMonths':
                                    options.current.addYears(parentEl.hasClass('datepickerGoPrev') ? -1 : 1);
                                    break;
                                case 'datepickerViewYears':
                                    options.current.addYears(parentEl.hasClass('datepickerGoPrev') ? -12 : 12);
                                    break;
                            }
                            fillIt = true;
                        }
                    } else if (parentEl.is('td') && !parentEl.hasClass('datepickerDisabled')) {
                        switch (tblEl.get(0).className) {
                            case 'datepickerViewMonths':
                                options.current.setMonth(tblEl.find('tbody.datepickerMonths td').index(parentEl));
                                options.current.setFullYear(parseInt(tblEl.find('thead th.datepickerMonth span').text(), 10));
                                options.current.addMonths(Math.floor(options.calendars / 2) - tblIndex);
                                tblEl.get(0).className = 'datepickerViewDays';
                                break;
                            case 'datepickerViewYears':
                                options.current.setFullYear(parseInt(el.text(), 10));
                                tblEl.get(0).className = 'datepickerViewMonths';
                                break;
                            default:
                                var val = parseInt(el.text(), 10);
                                tmp.addMonths(tblIndex - Math.floor(options.calendars / 2));
                                if (parentEl.hasClass('datepickerNotInMonth')) {
                                    tmp.addMonths(val > 15 ? -1 : 1);
                                }
                                tmp.setDate(val);

                                if( !( parentEl.hasClass('datepickerSaturday') || parentEl.hasClass('datepickerSunday') ) ) {
                                    clickedOnAcceptableDay = true;
                                }

                                switch (options.mode) {
                                    case 'multiple':
                                        val = (tmp.setHours(0, 0, 0, 0)).valueOf();
                                        if ($.inArray(val, options.date) > -1) {
                                            $.each(options.date, function(nr, dat) {
                                                if (dat == val) {
                                                    options.date.splice(nr, 1);
                                                    return false;
                                                }
                                            });
                                        } else {
                                            options.date.push(val);
                                        }
                                        break;
                                    case 'range':
                                        if (!options.lastSel) {
                                            options.date[0] = (tmp.setHours(0, 0, 0, 0)).valueOf();
                                        }
                                        val = (tmp.setHours(23, 59, 59, 0)).valueOf();
                                        if (val < options.date[0]) {
                                            options.date[1] = options.date[0] + 86399000;
                                            options.date[0] = val - 86399000;
                                        } else {
                                            options.date[1] = val;
                                        }
                                        options.lastSel = !options.lastSel;
                                        break;
                                    default:
                                        options.date = tmp.valueOf();
                                        break;
                                }
                                break;
                        }
                        fillIt = true;
                        changed = true;
                    }
                    if (fillIt) {
                        fill(this);
                    }
                    if (changed) {
                        options.onChange.apply(this, prepareDate(options));
                    }
                }

                if( !checkChosenDeliveryDate( getChosenDeliveryDate() ) ) {
                    $( "#delivery_manual_error").removeClass( "hide" );
                    $('.delivery_before_time,.delivery_not_available,.modal.outsource .tooltip').addClass('hide');

                } else {
                    $( "#delivery_manual_error").addClass( "hide" );
                    if( clickedOnAcceptableDay ) {
                        prepareAndSubmitQuote(getChosenDeliveryDate(), false);
                    }
                }

                return false;
            },
            prepareDate = function(options) {
                var tmp;
                if (options.mode == 'single') {
                    tmp = new Date(options.date);
                    return [formatDate(tmp, options.format), tmp, options.el];
                } else {
                    tmp = [
                        [],
                        [], options.el
                    ];
                    $.each(options.date, function(nr, val) {
                        var date = new Date(val);
                        tmp[0].push(formatDate(date, options.format));
                        tmp[1].push(date);
                    });
                    return tmp;
                }
            },
            getViewport = function() {
                var m = document.compatMode == 'CSS1Compat';
                return {
                    l: window.pageXOffset || (m ? document.documentElement.scrollLeft : document.body.scrollLeft),
                    t: window.pageYOffset || (m ? document.documentElement.scrollTop : document.body.scrollTop),
                    w: window.innerWidth || (m ? document.documentElement.clientWidth : document.body.clientWidth),
                    h: window.innerHeight || (m ? document.documentElement.clientHeight : document.body.clientHeight)
                };
            },
            isChildOf = function(parentEl, el, container) {
                if (parentEl == el) {
                    return true;
                }
                if (parentEl.contains) {
                    return parentEl.contains(el);
                }
                if (parentEl.compareDocumentPosition) {
                    return !!(parentEl.compareDocumentPosition(el) & 16);
                }
                var prEl = el.parentNode;
                while (prEl && prEl != container) {
                    if (prEl == parentEl)
                        return true;
                    prEl = prEl.parentNode;
                }
                return false;
            },
            show = function(ev) {
                var cal = $('#' + $(this).data('datepickerId'));
                if (!cal.is(':visible')) {
                    var calEl = cal.get(0);
                    fill(calEl);
                    var options = cal.data('datepicker');
                    options.onBeforeShow.apply(this, [cal.get(0)]);
                    var pos = $(this).offset();
                    var viewPort = getViewport();
                    var top = pos.top;
                    var left = pos.left;
                    var oldDisplay = $.curCSS(calEl, 'display');
                    cal.css({
                        visibility: 'hidden',
                        display: 'block'
                    });
                    layout(calEl);
                    switch (options.position) {
                        case 'top':
                            top -= calEl.offsetHeight;
                            break;
                        case 'left':
                            left -= calEl.offsetWidth;
                            break;
                        case 'right':
                            left += this.offsetWidth;
                            break;
                        case 'bottom':
                            top += this.offsetHeight;
                            break;
                    }
                    if (top + calEl.offsetHeight > viewPort.t + viewPort.h) {
                        top = pos.top - calEl.offsetHeight;
                    }
                    if (top < viewPort.t) {
                        top = pos.top + this.offsetHeight + calEl.offsetHeight;
                    }
                    if (left + calEl.offsetWidth > viewPort.l + viewPort.w) {
                        left = pos.left - calEl.offsetWidth;
                    }
                    if (left < viewPort.l) {
                        left = pos.left + this.offsetWidth
                    }
                    cal.css({
                        visibility: 'visible',
                        display: 'block',
                        top: top + 'px',
                        left: left + 'px'
                    });
                    if (options.onShow.apply(this, [cal.get(0)]) != false) {
                        cal.show();
                    }
                    $(document).bind('mousedown', {
                        cal: cal,
                        trigger: this
                    }, hide);
                }
                return false;
            },
            hide = function(ev) {
                if (ev.target != ev.data.trigger && !isChildOf(ev.data.cal.get(0), ev.target, ev.data.cal.get(0))) {
                    if (ev.data.cal.data('datepicker').onHide.apply(this, [ev.data.cal.get(0)]) != false) {
                        ev.data.cal.hide();
                    }
                    $(document).unbind('mousedown', hide);
                }
            };
        return {
            init: function(options) {
                options = $.extend({}, defaults, options || {});
                extendDate(options);
                options.calendars = Math.max(1, parseInt(options.calendars, 10) || 1);
                options.mode = /single|multiple|range/.test(options.mode) ? options.mode : 'single';
                return this.each(function() {
                    if (!$(this).data('datepicker')) {
                        options.el = this;
                        if (options.date.constructor == String) {
                            options.date = parseDate(options.date, options.format);
                            options.date.setHours(0, 0, 0, 0);
                        }
                        if (options.mode != 'single') {
                            if (options.date.constructor != Array) {
                                options.date = [options.date.valueOf()];
                                if (options.mode == 'range') {
                                    options.date.push(((new Date(options.date[0])).setHours(23, 59, 59, 0)).valueOf());
                                }
                            } else {
                                for (var i = 0; i < options.date.length; i++) {
                                    options.date[i] = (parseDate(options.date[i], options.format).setHours(0, 0, 0, 0)).valueOf();
                                }
                                if (options.mode == 'range') {
                                    options.date[1] = ((new Date(options.date[1])).setHours(23, 59, 59, 0)).valueOf();
                                }
                            }
                        } else {
                            options.date = options.date.valueOf();
                        }
                        if (!options.current) {
                            options.current = new Date();
                        } else {
                            options.current = parseDate(options.current, options.format);
                        }
                        options.current.setDate(1);
                        options.current.setHours(0, 0, 0, 0);
                        var id = 'datepicker_' + parseInt(Math.random() * 1000),
                            cnt;
                        options.id = id;
                        $(this).data('datepickerId', options.id);
                        var cal = $(tpl.wrapper).attr('id', id).bind('click', click).data('datepicker', options);
                        if (options.className) {
                            cal.addClass(options.className);
                        }
                        var html = '';
                        for (var i = 0; i < options.calendars; i++) {
                            cnt = options.starts;
                            if (i > 0) {
                                html += tpl.space;
                            }
                            html += tmpl(tpl.head.join(''), {
                                week: options.locale.weekMin,
                                prev: options.prev,
                                next: options.next,
                                day1: options.locale.daysMin[(cnt++) % 7],
                                day2: options.locale.daysMin[(cnt++) % 7],
                                day3: options.locale.daysMin[(cnt++) % 7],
                                day4: options.locale.daysMin[(cnt++) % 7],
                                day5: options.locale.daysMin[(cnt++) % 7],
                                day6: options.locale.daysMin[(cnt++) % 7],
                                day7: options.locale.daysMin[(cnt++) % 7]
                            });
                        }
                        cal
                            .find('tr:first').append(html)
                            .find('table').addClass(views[options.view]);
                        fill(cal.get(0));
                        if (options.flat) {
                            cal.appendTo(this).show().css('position', 'relative');
                            layout(cal.get(0));
                        } else {
                            cal.appendTo(document.body);
                            $(this).bind(options.eventName, show);
                        }
                    }
                });
            },
            showPicker: function() {
                return this.each(function() {
                    if ($(this).data('datepickerId')) {
                        show.apply(this);
                    }
                });
            },
            hidePicker: function() {
                return this.each(function() {
                    if ($(this).data('datepickerId')) {
                        $('#' + $(this).data('datepickerId')).hide();
                    }
                });
            },
            setDate: function(date, shiftTo) {
                return this.each(function() {
                    if ($(this).data('datepickerId')) {
                        var cal = $('#' + $(this).data('datepickerId'));
                        var options = cal.data('datepicker');
                        options.date = date;
                        if (options.date.constructor == String) {
                            options.date = parseDate(options.date, options.format);
                            options.date.setHours(0, 0, 0, 0);
                        }
                        if (options.mode != 'single') {
                            if (options.date.constructor != Array) {
                                options.date = [options.date.valueOf()];
                                if (options.mode == 'range') {
                                    options.date.push(((new Date(options.date[0])).setHours(23, 59, 59, 0)).valueOf());
                                }
                            } else {
                                for (var i = 0; i < options.date.length; i++) {
                                    options.date[i] = (parseDate(options.date[i], options.format).setHours(0, 0, 0, 0)).valueOf();
                                }
                                if (options.mode == 'range') {
                                    options.date[1] = ((new Date(options.date[1])).setHours(23, 59, 59, 0)).valueOf();
                                }
                            }
                        } else {
                            options.date = options.date.valueOf();
                        }
                        if (shiftTo) {
                            options.current = new Date(options.mode != 'single' ? options.date[0] : options.date);
                        }
                        fill(cal.get(0));
                    }
                });
            },
            getDate: function(formated) {
                if (this.size() > 0) {
                    return prepareDate($('#' + $(this).data('datepickerId')).data('datepicker'))[formated ? 0 : 1];
                }
            },
            clear: function() {
                return this.each(function() {
                    if ($(this).data('datepickerId')) {
                        var cal = $('#' + $(this).data('datepickerId'));
                        var options = cal.data('datepicker');
                        if (options.mode != 'single') {
                            options.date = [];
                            fill(cal.get(0));
                        }
                    }
                });
            },
            fixLayout: function() {
                return this.each(function() {
                    if ($(this).data('datepickerId')) {
                        var cal = $('#' + $(this).data('datepickerId'));
                        var options = cal.data('datepicker');
                        if (options.flat) {
                            layout(cal.get(0));
                        }
                    }
                });
            }
        };
    }();
    $.fn.extend({
        DatePicker: DatePicker.init,
        DatePickerHide: DatePicker.hidePicker,
        DatePickerShow: DatePicker.showPicker,
        DatePickerSetDate: DatePicker.setDate,
        DatePickerGetDate: DatePicker.getDate,
        DatePickerClear: DatePicker.clear,
        DatePickerLayout: DatePicker.fixLayout
    });
})(jQuery);