/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

UI = {
    init: function() {
	
        this.initStart = new Date();
 		this.numMatchesResults = 2;

		$(document).ready(function() {
		    UI.findEmptySegment();
		})
        
        $("body, li.target textarea").bind('keydown','Ctrl+return', function(e){
            e.preventDefault();
            $('.editor .Translated').click();
        }).bind('keydown','Ctrl+down', function(e){ 
            e.preventDefault();
            $('.editor .draft').click();
        });

        $("input.Translated").bind('keydown','tab', function(e){ 
            e.preventDefault();
            $(this).parents('section').find('textarea').focus();
        })      
 
        $('.sbm').tabify();
        $(".sbm a").click(function() {
            return false
        });
        jQuery('textarea').trigger('update');

        $("div.notification-box").mouseup(function() {
            return false;
        });

        $(document).mouseup(function(e) {
            if($(e.target).parent("a.m-notification").length==0) {
                $(".m-notification").removeClass("menu-open");
                $("fieldset#signin_menu").hide();
            }
        });	

        $("li.target textarea").mousedown(function(e) {
            e.preventDefault();
            return false;
/*
            if($(e.target).parent("a.m-notification").length==0) {
                $(".m-notification").removeClass("menu-open");
                $("fieldset#signin_menu").hide();
            }
*/
        });
        
        $(".search-icon, .search-on").click(function(e) {          
            e.preventDefault();
            $("div#search").toggle();
        });
        	  
        //overlay

        $(".x-stats").click(function(e) {          
            $(".stats").toggle();
        });


 		$("article").on('click','div.comments span.corner',function(e) {          
            e.preventDefault();
            $(".comment-area").hide();
            $(".h-notification").show();

            $("article").animate({
                width: '76%'
            }).addClass("maincomment");
			var segment = $(this).parents("section");
			var commentArea =  $(".comment-area", segment);
			commentArea.addClass("openarea").show("slide", {
                direction: "left"
            }, 400);;
            $(".text-c").focus();
            $(".c-close", segment).hide();
         }).on('click','a.x-com',function(e) {  
			e.preventDefault();
 			var segment = $(this).parents("section");
			var commentArea =  $(".comment-area", segment);

			commentArea.removeClass("openarea").hide("slide", {
                direction: "left"
            }, 400);
            $(".h-notification", segment).show();
            $("article").removeClass("maincomment").animate({
                width: '90.5%'
            }).removeClass("maincomment");
        }).on('click','a.number',function(e) {  
			e.preventDefault();
			e.stopPropagation();
			return false;
         });

 		$("article").on('click','a.status',function(e) {          
            e.preventDefault();
            e.stopPropagation();
  			var segment = $(this).parents("section");
			var statusMenu = $("ul.statusmenu", segment);
			var isVisible = statusMenu.is(":visible");
            $("ul.statusmenu:visible").hide();
            if (isVisible){
                return null;
            }            
            statusMenu.toggle();
 			var autoCloseStatusMenu = $('html').bind("click.vediamo", function(event) {
				$("ul.statusmenu").hide();
				$('html').unbind('click.vediamo');
			});
        });
        
		$(".joblink").click(function(e) {          
            e.preventDefault();
        	$(".joblist").toggle();
        	return false;
        });

        $(".statslink").click(function(e) {    
            e.preventDefault();
            e.stopPropagation();
            $(".stats").toggle();
        });

        $('html').click(function() {
            $(".menucolor").hide();
        });


 		$("article").on('click','a.percentuage',function(e) {          
            e.preventDefault();
            e.stopPropagation();
        }).on('click','li.target textarea',function(e) {

//			alert('click');
			$(".statusmenu:visible").hide();
			// da riprendere
//			if(typeof UI.currentSegmentOb != 'undefined') UI.currentSegmentOb.removeClass('active');
			UI.currentSegmentOb = segment = $(this).parents("section");
			UI.scrollSegment(segment);
			// da riprendere
//			segment.addClass('active');
			$(this).removeClass("indent"); // vediamo come rimuoverne la necessità

			if (!($("div.sub-editor.matches .graysmall",segment).length)){     
				UI.getContribution(segment);
			}
			if ( $(segment).find(".toggle").is(":visible")){return null}

            $("section.editor").find(".close").click();
            $("div.grayed").toggle();

            $(segment).addClass("editor");
            $(this).focus();
            UI.editStart = new Date();
            $(this).caretTo(0);
            $(".toggle",segment).show("blind", {
                direction: "vertical"
            }, 250);
            UI.getContribution(segment,1);
            UI.startTextareaAutoresize(this);
        }).on('click','input.draft, input.Translated, input.approved',function(e) {
            UI.editStop = new Date();
            UI.editTime = UI.editStop - UI.editStart;
  			var segment = $(this).parents("section");
            var statusSwitcher = $("a.status",segment);
//            $("li.target textarea").addClass("grayed-text");
            statusSwitcher.removeClass("col-approved col-notapproved col-done col-draft");
            var nextSegment = UI.getNextSegment(segment);
            if(!nextSegment.length) {
            	$(".editor:visible").find(".close").click();
            	return false;
            };
            $("li.target textarea", nextSegment).click();
            return false;
        }).on('click','input.Translated',function(e) {
        	UI.changeStatus(this,'translated');
        }).on('click','input.draft',function(e) {          
         	UI.changeStatus(this,'draft');
        }).on('click','input.approved',function(e) {          
        	UI.changeStatus(this,'approved');
        }).on('click','a.d, a.a, a.r, a.f',function(e) {          
            var segment = $(this).parents("section");
            $("a.status",segment).removeClass("col-approved col-notapproved col-done col-draft");
            $("ul.statusmenu",segment).toggle();
            return false;
        }).on('click','a.d',function(e) {          
         	UI.changeStatus(this,'translated');
        }).on('click','a.a',function(e) {          
         	UI.changeStatus(this,'approved');
        }).on('click','a.r',function(e) {          
        	UI.changeStatus(this,'notapproved');
        }).on('click','a.f',function(e) {          
        	UI.changeStatus(this,'draft');
        }).on('click','a.copysource',function(e) {   
            var segment = $(this).parents("section");
            var source_val = $.trim($("li.source > span.original",segment).text());
            $("li.target textarea",segment).val(source_val).keyup().focus();
            $("li.target textarea",segment).effect("highlight", {}, 1000);
            return false;
        }).on('click','.tagmenu, .warning, .viewer, .notification-box li a',function(e) {          
            return false;
        }).on('click','a.close',function(e) {          
            e.preventDefault();
            var segment = $(this).parents("section");
            $(".toggle",segment).hide("blind", {
                direction: "vertical"
            },250);
            $("div.grayed").toggle();
            var textarea = $("li.target textarea",segment);
//            textarea.removeClass("grayed-text");
            UI.endTextareaAutoresize(textarea);
            $(".toggle",segment).promise().done(function(){
                $(segment).removeClass("editor").find(".editable_textarea").find("button").click(); // a che serve editable_textarea?
            })
        }).on('click','input.con-submit',function(e) {          
            var segment = $(this).parents("section");
            UI.addSegmentComment(segment);
        }).on('dblclick','ul.graysmall',function(e) {
            var segment = $(this).parents("section");
			$('textarea',segment).text($('li.b',this).text());
        });

		this.initTargetHeight();
        this.initEnd = new Date();
        this.initTime = this.initEnd - this.initStart;

        console.log('init time: ' + this.initTime);

    },

    getPercentuageClass: function (match){
        var percentageClass="";
        m_parse=parseInt(match);
        if (!isNaN(m_parse)){
            match=m_parse;
        }
        
        switch (true){
            case (match==100):
                percentageClass="per-green";
                break;
            case (match==101):
                percentageClass="per-blue";
                break;
            case(match>0 && match <=99):
                 percentageClass="per-orange";
                break;
            case (match=="MT"):
                percentageClass="per-yellow";
                break;
            default :
                percentageClass="";
        }
        return percentageClass;
    },

    getContribution: function(currentSegment,next) {
        next = (typeof next == 'undefined')? 0 : 1;
        if(next){
        	var n = this.getNextSegment(currentSegment);
        } else {
        	var n = $(currentSegment);        
        }
        if($(n).hasClass('loaded')) return false;
        if((!n.length)&&(next)) return false;

        var id = n.attr('id');
        var id_segment = id.split('-')[1];
        var txt = $('.source .original',n).text();
        if(!next) {
        	$(".loader",n).addClass('loader_on')
        	$(".percentuage",n).hide();
        }
		
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
            complete: function (d){
                $(".loader",n).removeClass('loader_on');
            },
            success: function(d){
 				var isActiveSegment = $(this).hasClass('editor');
	  			var textarea = $('li.target textarea', this);
	  			var translation = d.data.matches[0].translation;
	  			var textareaLength = textarea.val().length;
                if (textareaLength==0){
                    if($.trim(translation) != '') {
                    	textarea.text(translation);
                    }
                }
                if(isActiveSegment) {
	                textarea.removeClass("indent").caretTo(0);
	 			} else {
	 				if (textareaLength==0) textarea.addClass("indent");
	 			}

                var match = d.data.matches[0].match;
                percentageClass = UI.getPercentuageClass(match);
                
                $('.percentuage', this).text(match).addClass(percentageClass).show();
                var _this = this;
                $(_this).removeClass('loaded').addClass('loaded');
                $('.sub-editor .overflow',_this).empty();
                
                var valid=0;
                $.each(d.data.matches, function() {                    
                    cb= this['created-by'];                    
                    cl_suggestion=UI.getPercentuageClass(this['match']);
                    $('.sub-editor .overflow',_this).append('<ul class="graysmall"><li>'+this.segment+'</li><li class="b">'+this.translation+'</li><ul class="graysmall-details"><li class="' + cl_suggestion + '">'+(this.match)+'</li><li>'+this['last-update-date']+'</li><li class="graydesc">Source: <span class="bold">'+cb+'</span></li></ul></ul>');
                });
                if (d.data.matches==0){
                    $(".sbm > .matches", _this).hide();
                } else {
                    $('.submenu li.matches a span', this).text('('+d.data.matches.length+')');
                }
                
            }
        });
    },

	getNextSegment: function(currentSegment) {
        var n = $(currentSegment).nextAll('section').first() || $(currentSegment).parents('article').next().find('section').first();
		if(typeof n == 'undefined') return false;
        if(!$(currentSegment).nextAll('section').length) {
    		n = $(currentSegment).parents('article').next().find('section').first();
        };
        return n;
 	},
 	
	setContribution: function(segment) {
        var source = $('.source .original',segment).text();
        var target = $('li.target textarea',segment).val();
        if(target == '') return false;
        var languages = $(segment).parents('article').find('.languages');
        var source_lang = $('.source-lang',languages).text();
        var target_lang = $('.target-lang',languages).text();
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
        var info=$(segment).attr('id').split('-');
        var id_segment = info[1];
        var file = $(segment).parents('article');
        var id_job = $('div.projectbar',file).data('job').split('-')[1];
        var status = status;
        var translation = $('li.target textarea',segment).val();
        if(translation == '') return false;
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
         		if(d.data == 'OK') {
					$("a.status",segment).addClass("col-"+status);
         		};
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

    changeStatus: function(ob,status) {
        var segment = $(ob).parents("section");
        UI.setContribution(segment);
        UI.setTranslation(segment,status);
    },

    findEmptySegment: function() {
        var found=false;
        $("li.target textarea").each(function(){
            var textarea = $(this);
			if (textarea.text()=="" && found==false){
                found=true;
				UI.currentSegmentOb = textarea.parents("section");
                textarea.click();
                UI.createTextareaClone();
            }
        })
    },

    initTargetHeight: function() {
		var targetHeight = 60;
    	$('li.source').each(function(){
    		var sourceHeight = $(this).height();
			if(sourceHeight > targetHeight) {
				$('textarea',$(this).next()).css('height',sourceHeight+'px')
			}
 	   	});

/*
    	$('section textarea').each(function(){
    		var textarea = $(this);
    		var sourceHeight = textarea.parent().prev().height();
    		if(sourceHeight > targetHeight) {
    			textarea.css('height',sourceHeight+'px')
    		}
 	   	});
*/
/*
        var ta = $('section textarea')[0];
        var shadow = $('<div id="shadowTextarea"></div>').css({
            position:   'absolute',
            top:        -10000,
            left:       -10000,
            width:      $(ta).width(),
            fontSize:   $(ta).css('fontSize'),
            fontFamily: $(ta).css('fontFamily'),
            lineHeight: $(ta).css('lineHeight'),
            resize:     'none'
        }).appendTo(document.body);
    	$('section textarea').each(function(){
     		var tx = $(this).val();
     		shadow.html(tx);
      		var hh = shadow.height();
     		if(hh < 30) hh = 30;
    		$(this).css('height', hh)

    		var targetHeight = hh;
    		var sourceHeight = $(this).parent().prev().height();
    		if(sourceHeight > targetHeight) {
    			$(this).css('height',sourceHeight+'px')
    		}
 	   	});
*/
    },

    createTextareaClone: function() {
        var ta = $('section.editor textarea');
        var shadowActive = $('<div id="shadowActiveTextarea"></div>').css({
            position:   'absolute',
            top:        -10000,
            left:       -10000,
            width:      $(ta).width(),
            fontSize:   $(ta).css('fontSize'),
            fontFamily: $(ta).css('fontFamily'),
            lineHeight: $(ta).css('lineHeight'),
            resize:     'none'
        }).appendTo(document.body);
    },

	startTextareaAutoresize: function(textarea) {
		$(textarea).bind('keyup.activeTextarea', function() {
     		var shadow = $('#shadowActiveTextarea');
     		var tx = $(this).val();
     		shadow.html(tx);
      		var hh = shadow.height();
      		if(hh < 30) hh = 30;
    		$(this).css('height', hh);
		});
	},

	endTextareaAutoresize: function(textarea) {
		$(textarea).unbind('keyup.activeTextarea');
	},

	scrollSegment: function(segment) {
		var spread = 20;
		var current = $('section.editor');
		var previousSegment = $(segment).prev('section');
		if(!previousSegment.length) {
			previousSegment = $(segment);
			spread = 30;
		};
		var destination = "#"+previousSegment.attr('id');
		var destinationTop = $(destination).offset().top;
		if($(current).length){console.log('a');
			if($(segment).offset().top > $(current).offset().top) {console.log('b');
				if(!current.is($(segment).prev())) {console.log('c');
					destinationTop = destinationTop - $('section.editor').height() + $(segment).height() - spread;
				} else {console.log('d');
					destinationTop = destinationTop - spread;
				}
			} else {console.log('e');
				destinationTop = destinationTop - spread;
			}		
		} else {console.log('f');
			destinationTop = destinationTop - spread;
		}	
		$("html:not(:animated),body:not(:animated)").animate({ scrollTop: destinationTop-20}, 500 );
	}	        

}

$(document).ready(function(){
    UI.init();
});


$(window).resize(function(){
	UI.initTargetHeight();
});

