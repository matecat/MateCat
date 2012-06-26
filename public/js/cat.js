/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

UI = {
    init: function() {
        /*
		$(document).bind('keydown','Ctrl+return', function(e){
			$(document).find(".editor").find(".Translated").click();
		});
*/
        this.numMatchesResults = 2;
        this.initSegments();

        $(document).ready(function() {
            $(".target-textarea").first().click();
        });

        $("body, .target-textarea").bind('keydown','Ctrl+return', function(e){ 
            e.preventDefault();
            $('.editor .Translated').click();
        });

        $('.sbm').tabify();
        $(".sbm a").click(function() {
            return false
        });
        jQuery('textarea').trigger('update');
        $('textarea').autogrow();
	
        $("div.notification-box").mouseup(function() {
            return false
        });

        $(document).mouseup(function(e) {
            if($(e.target).parent("a.m-notification").length==0) {
                $(".m-notification").removeClass("menu-open");
                $("fieldset#signin_menu").hide();
            }
        });	

        $(".search-icon, .search-on").click(function(e) {          
            e.preventDefault();
            $("div#search").toggle();
        });
                
        //overlay
	
        $(".more").click(function(){
            $('.boxoverlay').fadeIn('fast');
            $('#box').fadeIn('fast');
            $('div.stats').hide('');
        });

        $("#close-overlay").click(function(){
            $('.boxoverlay').fadeOut('fast');
            $('#box').fadeOut('slow');
        });

        $(".preview-link").click(function(){
            $(".preview").addClass("heightprev");
        });

        $(".x-stats").click(function(e) {          
            $(".stats").toggle();
        });

        /*
		$(".corner").click(function(e) {          
			e.preventDefault();
			$(this).parents(".ed").find(".h-notification").removeClass("c-close");
			$(this).parents(".ed").find(".h-notification").show("slide", {
				direction: "left"
				}, 650);
			$(this).parents(".ed").find(".comment-area").addClass("openarea");
			$(this).parents(".ed").find(".c-toggle").show("fold", {
				direction: "vertical"
				}, 650);
			$(".text-c").focus();
			$(".main").animate({width: '71%'});	
			$(".main").addClass("maincomment");
		});

  */

        $(".corner").click(function(e) {          
            e.preventDefault();
            //			console.log($(".h-notification"));
            $(".comment-area").hide();	
            $(".h-notification").show();

            $(".main").animate({
                width: '74%'
            });	
            $(".main").addClass("maincomment");	
            $(this).parents(".ed").find(".comment-area").addClass("openarea");
            $(this).parents(".ed").find(".comment-area").show("slide", {
                direction: "left"
            }, 400);
            $(".text-c").focus();
            $(this).parents(".ed").find(".c-close").hide();
        });

        $(".x-com").click(function(e) {          
            e.preventDefault();
            $(this).parents(".ed").find(".comment-area").removeClass("openarea");
            $(this).parents(".ed").find(".comment-area").hide("slide", {
                direction: "left"
            }, 400);
            $(this).parents(".ed").find(".h-notification").show();
            $(".main").removeClass("maincomment");
            $(".main").animate({
                width: '90.5%'
            });	
            $(".main").removeClass("maincomment");
        });

        $(".x-addcom").click(function(e) {          
            e.preventDefault();
            $(this).parents(".ed").find(".h-notification").show();
            $(".main").removeClass("maincomment");
            $(".main").animate({
                width: '88.1%'
            });	
            $(".main").removeClass("maincomment");
        });

        $(".addsuggestion").click(function(e) {          
            e.preventDefault();
            $(this).parents(".ed").find(".addline-suggestion").slideDown('fast', function() {
                // Animation complete.
                });
        });
		
        $(".status").click(function(e) {         
            e.preventDefault();
            e.stopPropagation();
            var isVisible=$(this).parents(".ed").find(".menucolor").is(":visible");
            $(".menucolor:visible").hide();
            if (isVisible){
                return null;
            }
            $(this).parents(".ed").find(".menucolor").toggle();
        });

        $(".m-notification").click(function(e) {          
            e.preventDefault();
            e.stopPropagation();

            var isVisible=$(".notification-box").is(":visible");

            if (isVisible){
                $(".notification-box").hide();
                return null;
            }
            $("div.notification-box").toggle();
            $(".m-notification").toggleClass("menu-open");
        });

        $(".joblink").click(function(e) {          
            $(".joblist").toggle();
        });

        $(".statslink").click(function(e) {          
            e.preventDefault();
            e.stopPropagation();
            $(".stats").toggle();
        });

        $('html').click(function() {
            $(".menucolor").hide();
        // 	 $(".notification-box").hide();
        });

        $(".con-menubtn").click(function(e) {          
            e.preventDefault();
            $("ul#col-menu").toggle();
        });

        $(".percentuage").click(function(e) {          
            e.preventDefault();
            e.stopPropagation();
        });

        $(".smart-suggestion-target").hide();
        $(".smart-suggestion-source").each(function(e) { 
            $(this).hover(function(e){
                $(this).hide();
                $(this).siblings(".smart-suggestion-target").show().attr("style","background-color:#3297fd;color:#fff");
            });
        });

        $(".smart-suggestion-target").each(function(e) { 
            $(this).mouseleave(function(e){
                $(this).hide();
                $(this).siblings(".smart-suggestion-source").show();
            })
        });

        $(".target-textarea").click(function(e) {

            //			console.log($(this).parents(".ed").offset().top);
            //			$('body').animate({
            //				scrollTop: (($(this).parents(".ed").offset().top)+200)
            //			}, 200);

            e.preventDefault();
            e.stopPropagation();
            $(".menucolor:visible").hide();

            var anchor=($(this).parents(".ed")).prev();//.find(".number");
            var anchor2=anchor.find(".number");

            //console.log(anchor2);
            $(this).removeClass("white_text");
            var segment = $(this).parents(".ed");
            if(!$(".target-textarea",segment).val().length) UI.getContribution(segment);

            if ( $(segment).find(".toggle").is(":visible")){
                return null
            }

            $(".editor:visible").find(".x").click();
            $(".target-textarea").addClass("grayed-text");

            $("div.grayed").toggle();	

            /* console.log ($(this).parents(".ed"));*/
            $(this).parents(".ed").addClass("editor");
            $(this).focus();
            UI.editStart = new Date();
            $(this).parents(".ed").find(".toggle").show("blind", {
                direction: "vertical"
            }, 250);
            //			UI.getSegmentComments(segment);
            //			if(!$(".target-textarea",segment).val().length) UI.getContribution(segment);
            UI.getNextContribution(segment);

        //			$(this).parents(".ed").scrollMinimal();
        //  $(this).removeClass("editor-click");
        })

        $(".search-icon").click(function(){
            $(".main").addClass("main-searched");
        })
        $(".search-on").click(function(){
            $(".main").removeClass("main-searched");
        })
	
        $(".draft, .Translated, .approved").click(function(){
            UI.editStop = new Date();
            UI.editTime = UI.editStop - UI.editStart;
            var s = $(this).parents(".ed");
            var st = $(".status",s);
            $(".target-textarea").addClass("grayed-text");
            //			$(this).parents(".ed").find(".x").click();
            st.removeClass("col-approved col-notapproved col-done col-draft");
            var n = s.next().next();
            $(n).find(".target-textarea").click();
            return false;
        //			$(n).parents(".main").scrollTo( $(n), 800 );
        })

        $(".Translated").click(function(){
            var s = $(this).parents(".ed");
            UI.setContribution(s);
            UI.setTranslation(s,'translated');
            s.find(".status").addClass("col-translated");
        })

        $(".draft").click(function(){
            var s = $(this).parents(".ed");
            UI.setContribution(s);
            UI.setTranslation(s,'draft');
            s.find(".status").addClass("col-draft");
        })

        $(".approved").click(function(){
            var s = $(this).parents(".ed");
            UI.setContribution(s);
            UI.setTranslation(s,'approved');
            s.find(".status").addClass("col-approved");
        })

        $(".d, .a, .r, .f").click(function(){
            $(this).parents(".ed").find(".status").removeClass("col-approved col-notapproved col-done col-draft");
            $(this).parents(".ed").find(".menucolor").toggle();
            return false;
        })


        $(".d").click(function(){
            $(this).parents(".ed").find(".status").addClass("col-translated");
        })

        $(".a").click(function(){
            $(this).parents(".ed").find(".status").addClass("col-approved");
        })

        $(".r").click(function(){
            $(this).parents(".ed").find(".status").addClass("col-notapproved");
        })

        $(".f").click(function(){
            $(this).parents(".ed").find(".status").addClass("col-draft");
        })

        $(".copysource").click(function(){
            //console.log ("cp");
            var source_val = $.trim($(this).parents(".ed").find("li.source > .original").text());
            //console.log(source_val)
            $(this).parents(".ed").find("li.target>textarea").val(source_val).keyup().focus();
            $(this).parents(".ed").find("li.target>textarea").effect("highlight", {}, 1000);
            return false;
        })
        $(".tagmenu, .warning, .viewer, .notification-box li a").click(function(){
            return false;
        })

        $(".x").click(function(e) {          
            e.preventDefault();
            $(this).parents(".ed").find(".toggle").hide("blind", {
                direction: "vertical"
            },250);
            $("div.grayed").toggle();
            $(".target-textarea").removeClass("grayed-text");
            //$(this).parents(".ed").removeClass("editor").find(".editable_textarea").find("button").promise(function(){
            $(this).parents(".ed").find(".toggle").promise().done(function(){
                $(this).parents(".ed").removeClass("editor").find(".editable_textarea").find("button").click();
            })
        });

        $(".con-submit").bind("click",function(e) {          
            //			e.preventDefault();
            var segment = $(this).parents(".ed");
            UI.addSegmentComment(segment);

        /*
			$(this).parents(".ed").find(".toggle").hide("blind", {
				direction: "vertical"
				},250);
			$("div.grayed").toggle();
			$(".target-textarea").removeClass("grayed-text");
			//$(this).parents(".ed").removeClass("editor").find(".editable_textarea").find("button").promise(function(){
			$(this).parents(".ed").find(".toggle").promise().done(function(){
				$(this).parents(".ed").removeClass("editor").find(".editable_textarea").find("button").click();
			})
*/
        });

    /*
		var w = workarea;
		var t = toolarea;
		this.wa = w;
		this.ta = t;
		this.wa.defaultView = w.views[w.active_view_id];
		if(this.wa.defaultView.type == 0) this.dashboard = this.wa.defaultView;

		// init Notifications
		this.notifications();

		// init Tenants
		this.tenantInit();

		// init Workarea
		this.workareaInit();
*/
    },

    getContribution: function(currentSegment) {
        var n = $(currentSegment);
        if($(n).hasClass('loaded')) return false;
        var id = n.attr('id');
        var id_segment = id.split('-')[1];
        var txt = $('.source .original',n).text();
        $.ajax({
            url: config.basepath + '?action=getContribution',
            data: {
                action: 'getContribution',
                id_segment: id_segment,
                text: txt,
                num_results: this.numMatchesResults
            },
            type: 'POST',
            dataType: 'json',
            context: $('#'+id),
            success: function(d){
                $('.target-textarea', this).text(d.data.matches[0].translation);
                $('.percentuage', this).text(d.data.matches[0].match).removeClass("hide");
                var tt = this;
                $(tt).addClass('loaded');
                $('.sub-editor .overflow',tt).empty();
                
                var valid=0;
                $.each(d.data.matches, function() {                    
                    cb= this['created-by'];
                    if (cb!='MT'){
                        valid=valid+1;
                        $('.sub-editor .overflow',tt).append('<ul class="graysmall"><li>'+this.segment+'</li><li class="b">'+this.translation+'</li><ul class="graysmall-details"><li class="graygreen">'+(this.match)+'</li><li>'+this['last-update-date']+'</li><li>Created by '+cb+'</li></ul></ul>');
                    }                  		
                });
                if (valid==0){
                    $(".sbm > .matches", tt).hide();
                }else{
                    $('.submenu li.matches a span', this).text('('+valid+')');
                }
            }
        });
    },

    getNextContribution: function(currentSegment) {
        var n = $(currentSegment).nextAll('.ed').first() || $(currentSegment).parents('.main').next().find('.ed').first();
        if(!$(currentSegment).nextAll('.ed').length) {
            n = $(currentSegment).parents('.main').next().find('.ed').first()
        };
        if($(n).hasClass('loaded')) return false;
        var id = n.attr('id');
        var id_segment = id.split('-')[1];
        var txt = $('.source .original',n).text();
        $.ajax({
            url: config.basepath + '?action=getContribution',
            data: {
                action: 'getContribution',
                id_segment: id_segment,
                text: txt,
                num_results: this.numMatchesResults
            },
            type: 'POST',
            dataType: 'json',
            context: $('#'+id),
            success: function(d){
                console.log(d.data);
                $('.target-textarea', this).text(d.data.matches[0].translation).addClass("white_text");
                $('.percentuage', this).text(d.data.matches[0].match).removeClass("hide");
                var tt = this;
                $(tt).addClass('loaded');
                $('.sub-editor .overflow',tt).empty();
                
                var valid =0;
                $.each(d.data.matches, function() {  
                    
                    cb= this['created-by'];
                    if (cb!='MT'){
                        valid=valid+1;
                        $('.sub-editor .overflow',tt).append('<ul class="graysmall"><li>'+this.segment+'</li><li class="b">'+this.translation+'</li><ul class="graysmall-details"><li class="graygreen">'+(this.match)+'</li><li>'+this['last-update-date']+'</li><li>Created by '+cb+'</li></ul></ul>');
                    }	                    
                });
                if (valid==0){
                    $(".sbm .matches", tt).hide();
                }else{
                    $('.submenu li.matches a span', this).text('('+valid+')');
                }
            }
        });
    },
	
    setContribution: function(segment) {
        var source = $('.source .original',segment).text();
        var target = $('.target-textarea',segment).val();
        var l = $(segment).parents('.main').find('.languages');
        var source_lang = $('.source-lang',l).text();
        var target_lang = $('.target-lang',l).text();
        var id_translator = config.id_translator;
        var private_translator = config.private_translator;
        var id_customer = config.id_customer;
        var private_customer = config.private_customer;
        $.ajax({
            url: config.basepath + '?action=setContribution',
            data: {
                action: 'setContribution',
                source: source,
                target: target,
                source_lang: source_lang,
                target_lang: target_lang,
                id_translator: id_translator,
                private_translator: private_translator,
                id_customer: id_customer,
                private_customer: private_customer
            },
            type: 'POST',
            dataType: 'json',
            success: function(d){
            }
        });
    },

    setTranslation: function(segment,status) {
        var id_segment = $(segment).attr('id').split('-')[1];
        var id_job = $('.id-job').text();
        var status = status;
        var translation = $('.target-textarea',segment).val();
        var time_to_edit = UI.editTime;
        var id_translator = config.id_translator;
        $.ajax({
            url: config.basepath + '?action=setTranslation',
            data: {
                action: 'setTranslation',
                id_segment: id_segment,
                id_job: id_job,
                status: status,
                translation: translation,
                time_to_edit: time_to_edit,
                id_translator: id_translator
            },
            type: 'POST',
            dataType: 'json',
            success: function(d){
            }
        });
    },

    getSegmentComments: function(segment) {
        var id_segment = $(segment).attr('id').split('-')[1];
        var id_translator = config.id_translator;
        $.ajax({
            url: config.basepath + '?action=getSegmentComment',
            data: {
                action: 'getSegmentComment',
                id_segment: id_segment,
                id_translator: id_translator
            },
            type: 'POST',
            dataType: 'json',
            context: segment,
            success: function(d){
                $('.numcomments',this).text(d.data.length);
                $.each(d.data, function() {
                    $('.comment-area ul .newcomment',segment).before('<li><p><strong>'+this.author+'</strong><span class="date">'+this.date+'</span><br />'+this.text+'</p></li>');
                });
            }
        });
    },

    addSegmentComment: function(segment) {
        var id_segment = $(segment).attr('id').split('-')[1];
        var id_translator = config.id_translator;
        var text = $('.newcomment textarea',segment).val();
        $.ajax({
            url: config.basepath + '?action=addSegmentComment',
            data: {
                action: 'addSegmentComment',
                id_segment: id_segment,
                id_translator: id_translator,
                text: text
            },
            type: 'POST',
            dataType: 'json',
            success: function(d){
            }
        });
    },

    initSegments: function() {
    /*
		this.segments = [];
		$('.main .ed').each(function() {
			UI.createSegment(this);
		});
*/
    },

    createSegment: function(o) {
    /*
		o.number = $('.number',o);
		this.segments.push(o);
*/
    }

}

$(document).ready(function(){
    UI.init();
});



	
jQuery.fn.scrollMinimal = function(smooth) {
    var cTop = this.offset().top;
    var cHeight = this.outerHeight(true);
    var windowTop = $(window).scrollTop();
    var visibleHeight = $(window).height();

    if (cTop < windowTop) {	

        if (smooth) {
            $('body').animate({
                'scrollTop': cTop
            }, 'slow', 'swing');
        } else {
            $(window).scrollTop(cTop);
        }
    } else if (cTop + cHeight > windowTop + visibleHeight) {
        if (smooth) {
            $('body').animate({
                'scrollTop': cTop - visibleHeight + cHeight
            }, 'slow', 'swing');
        } else {
            $(window).scrollTop(cTop - visibleHeight + cHeight);
        }
    }
};
/*  
*/