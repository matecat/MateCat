/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

UI = {
    init: function() {
	
        this.initStart = new Date();
 		this.numMatchesResults = 2;
 		this.numSegments = $('section').length;
 		this.heavy = (this.numSegments > 500)? true : false;

		$(document).ready(function() {
		    if(config.last_opened_segment == '') {
		    	UI.findEmptySegment();
		    } else if(config.last_opened_segment == 0) {
		    	
		    } else {
		    	UI.findLastOpenedSegment();
		    }
/*
		    if(config.last_opened_segment != '') {
		    	UI.findLastOpenedSegment();
		    } else {
		    	UI.findEmptySegment();
		    }
*/
		})

      
        $("body, .target textarea").bind('keydown','Ctrl+return', function(e){
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
    
/*
		var preventTargetModification = $('li.target textarea').bind("keydown.modification", function(e) {
            e.preventDefault();
		});
*/     
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
        }).on('click','.target textarea',function(e) {
            UI.openSegment(this);     
        }).on('click','input.draft, input.Translated, input.approved',function(e) {
            UI.editStop = new Date();
            UI.editTime = UI.editStop - UI.editStart;
  			var segment = $(this).parents("section");
            var statusSwitcher = $(".status",segment);

            statusSwitcher.removeClass("col-approved col-notapproved col-done col-draft");
            var nextSegment = UI.getNextSegment(segment);
            if(!nextSegment.length) {
            	$(".editor:visible").find(".close").click();
            	return false;
            };
            UI.buttonClickStop = new Date();
            UI.clickingButtonOperations = UI.buttonClickStop - UI.editStop;
            $(".target textarea", nextSegment).click();
//            console.log('UI.editTime: ' + UI.editTime);
//            console.log('UI.buttonClickStop: ' + UI.buttonClickStop + '; UI.editStop: ' + UI.editStop);
//            console.log('operations after clicking a button but before autoclicking on the next textarea: ' + UI.clickingButtonOperations);
            return false;
        }).on('click','input.Translated',function(e) {
        	UI.changeStatus(this,'translated',0);
            UI.changeStatusStop = new Date();
            UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;
        }).on('click','input.draft',function(e) {          
         	UI.changeStatus(this,'draft',0);
            UI.changeStatusStop = new Date();
            UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;
        }).on('click','input.approved',function(e) {          
        	UI.changeStatus(this,'approved',0);
            UI.changeStatusStop = new Date();
            UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;
        }).on('click','a.d, a.a, a.r, a.f',function(e) {          
            var segment = $(this).parents("section");
            $("a.status",segment).removeClass("col-approved col-notapproved col-done col-draft");
            $("ul.statusmenu",segment).toggle();
            return false;
        }).on('click','a.d',function(e) {          
         	UI.changeStatus(this,'translated',1);
        }).on('click','a.a',function(e) {          
         	UI.changeStatus(this,'approved',1);
        }).on('click','a.r',function(e) {          
        	UI.changeStatus(this,'notapproved',1);
        }).on('click','a.f',function(e) {          
        	UI.changeStatus(this,'draft',1);
        }).on('click','a.copysource',function(e) {   
            var segment = $(this).parents("section");
            var source_val = $.trim($(".source > span.original",segment).text());
            $(".target textarea",segment).val(source_val).keyup().focus();
            $(".target textarea",segment).effect("highlight", {}, 1000);
            return false;
        }).on('click','.tagmenu, .warning, .viewer, .notification-box li a',function(e) {          
            return false;
        }).on('click','a.close',function(e) {          
            e.preventDefault();
            UI.closeSegment(UI.currentSegmentOb);
        }).on('click','input.con-submit',function(e) {          
            var segment = $(this).parents("section");
            UI.addSegmentComment(segment);
        }).on('dblclick','ul.graysmall',function(e) {
            var segment = $(this).parents("section");
			$('textarea',segment).val($('li.b',this).text()).focus();
        });


		this.checkStatusCompleteness();
        $('#fileDownload').append('<input type="hidden" name="file_id" value="' + $('article').attr('id').split('-')[1] + '" />');
 		$(".downloadtr-button").on('click',function(e) {          

            e.preventDefault();

			window.open('/?action=downloadFile','mywindow','left=20,top=20,width=500,height=500,toolbar=1,resizable=0');

/*
        	var id_job = $('div.projectbar').data('job').split('-')[1];

	        $.ajax({
	            url: config.basepath + '?action=downloadFile',
	            data: {
                	action: 'downloadFile',
	            	id_job: id_job
	            },
	            type: 'POST',
	            success: function(d){
	            	console.log('success');
	            }
	        });
*/
        })

//		this.initTargetHeight();
//        this.progressiveTargetHeight(0);
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
//        console.log(id);
        var id_segment = id.split('-')[1];
        var txt = $('.source .original',n).text();
        var file = $(currentSegment).parents('article');
        var id_job = $('div.projectbar',file).data('job').split('-')[1];
        
        
        
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
                id_job: id_job,
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
	  			var textarea = $('.target textarea', this);
	  			if(!d.data.matches.length) return true;
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
                    $('.sub-editor .overflow',_this).append('<ul class="graysmall"><li><a href="#" class="trash" title="delete this row"></a>'+this.segment+'</li><li class="b">'+this.translation+'</li><ul class="graysmall-details"><li class="' + cl_suggestion + '">'+(this.match)+'</li><li>'+this['last-update-date']+'</li><li class="graydesc">Source: <span class="bold">'+cb+'</span></li></ul></ul>');
                });
                if (d.data.matches==0){
                    $(".sbm > .matches", _this).hide();
                } else {
                    $('.submenu li.matches a span', this).text('('+d.data.matches.length+')');
                }
                $('.Translated',this).removeAttr('disabled');
                $('.draft',this).removeAttr('disabled');
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
 	
	setContribution: function(segment,byStatus) {
        var source = $('.source .original',segment).text();
        var target = $('.target textarea',segment).val();
        if(target == '') return false;
//        if((target == '')&&(!byStatus)) return false;
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
        var translation = $('.target textarea',segment).val();
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
         			UI.setStatus(segment,status);
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

    changeStatus: function(ob,status,byStatus) {
        var segment = $(ob).parents("section");
        this.setContribution(segment,byStatus);
        this.setTranslation(segment,status);
    },

    setStatus: function(segment,status) {
		segment.removeClass("status-draft status-translated status-approved status-notapproved").addClass("status-"+status);
		this.checkStatusCompleteness();
    },
    
    checkStatusCompleteness: function() {
    	var t = 'approved';
    	if($('section.status-translated').length) t = 'translated';
    	if($('section.status-draft').length) t = 'draft';
    	$('.downloadtr-button').removeClass("draft translated approved").addClass(t);
    },
    
    findEmptySegment: function() {
/*
		$("li.target textarea").each(function(){
			var textarea = $(this);
			if (textarea.text()=="") {
				UI.currentSegmentOb = textarea.parents("section");
				textarea.click();
				UI.createTextareaClone();
				return 0;
			}
		})
*/

        var found=false;
        $(".target textarea").each(function(){
            var textarea = $(this);
			if (textarea.text()=="" && found==false){
                found=true;
				UI.currentSegmentOb = textarea.parents("section");
                textarea.click();
                UI.createTextareaClone();
            }
        })

    },

    findLastOpenedSegment: function() {
    	var textarea = $('#segment-' + config.last_opened_segment + ' textarea');
 		UI.currentSegmentOb = textarea.parents("section");
//	   	console.log(textarea);
    	textarea.click();
        UI.createTextareaClone();
    },

    initTargetHeight: function() {
		var targetHeight = 60;
    	$('.source').each(function(){
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

    progressiveTargetHeight: function(from) {
		var targetHeight = 60;
    	var segments = $(".source").length;
		var numSegments = 1;
		for(i=from;i<(from+numSegments);i++) {
			var ss = $(".source")[i];
			if(typeof ss == 'undefined') {
				console.log('progressive TargetHeight time: ' + (new Date()-UI.initStart));
				return false;
			}

    		var sourceHeight = $(ss).height();
			if(sourceHeight > targetHeight) {
				$('textarea',$(ss).next()).css('height',sourceHeight+'px')
			}

//			console.log($(".source")[i].id + ', from: ' + from);
		}
//		if(typeof $(".source")[i] == 'undefined') return false;
		clearTimeout(UI.timerTest);
		UI.timerTest = setTimeout("UI.progressiveTargetHeight("+(from+numSegments)+")", 50);
//		UI.progressiveTargetHeight(from+numSegments);
		
/*
    	$('.source').each(function(){
    		var sourceHeight = $(this).height();
			if(sourceHeight > targetHeight) {
				$('textarea',$(this).next()).css('height',sourceHeight+'px')
			}
 	   	});
*/
    },

    setTargetHeight: function(textarea) {
		var targetHeight = $(textarea).height();
		var sourceHeight = $(textarea).parents('.target').prev().height();
		if(sourceHeight > targetHeight) {
			$(textarea).css('min-height',sourceHeight+'px')
		}
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
		this.setTargetHeight(textarea);
		this.textareaAutoresize(textarea);
		$(textarea).bind('keyup.activeTextarea', function() {
			UI.textareaAutoresize(this);
		});
	},

	textareaAutoresize: function(textarea) {
 		var shadow = $('#shadowActiveTextarea');
 		var tx = $(textarea).val().replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/&/g, '&amp;')
                            .replace(/\n/g, '<br/>');
 		shadow.html(tx);
  		var hh = shadow.height();
  		if(hh < 30) hh = 30;
  		if(tx.match("<br/>$")) {
  			hh = hh + 30;
  		};
		$(textarea).css('height', hh);
	},
	
	endTextareaAutoresize: function(textarea) {
		$(textarea).unbind('keyup.activeTextarea');
	},

	openSegment: function(textarea) {
        this.openSegmentStart = new Date();

		if($(textarea).getSelection().length) return false;
		$(".statusmenu:visible").hide();
//        console.log('prova1: ' + ((new Date())-this.openSegmentStart));


		// da riprendere
//			if(typeof UI.currentSegmentOb != 'undefined') UI.currentSegmentOb.removeClass('active');

		// current and last opened object reference caching
		this.lastOpenedSegmentOb = this.currentSegmentOb;
		this.lastOpenedArticleOb = this.currentArticleOb;
		this.lastOpenedTextarea = this.textarea;
		
		this.currentSegmentOb = segment = $(textarea).parents("section");
		this.currentArticleOb = segment.parent();
        this.textarea = $(".target textarea",segment);

		this.scrollSegment(segment);
//        console.log('prova2: ' + ((new Date())-this.openSegmentStart));

		// da riprendere
//			segment.addClass('active');
		$(textarea).removeClass("indent"); // vediamo come rimuoverne la necessità

		if (!($("div.sub-editor.matches .graysmall",segment).length)){     
			this.getContribution(segment);
		}
//        console.log('prova3: ' + ((new Date())-this.openSegmentStart));

		if ( $(segment).find(".toggle").is(":visible")){return null}
//        console.log('   prova3.1: ' + ((new Date())-this.openSegmentStart));

//            UI.lastOpenedSegmentOb.find(".close").click();
        UI.opening = true;
        this.closeSegment(this.lastOpenedSegmentOb);
        UI.opening = false;
//        console.log('   prova3.2: ' + ((new Date())-this.openSegmentStart));
        $("div.grayed").show();
//        console.log('prova4: ' + ((new Date())-this.openSegmentStart));

        $(segment).addClass("editor");
        $(textarea).focus();
        this.editStart = new Date();
        $(textarea).caretTo(0);

        if(this.heavy) {
        	$(".toggle",segment).show();
        } else {
        	$(".toggle",segment).show();
/*
            $(".toggle",segment).show("blind", {
                direction: "vertical"
            }, 250);
*/
       }
//        console.log('prova5: ' + ((new Date())-this.openSegmentStart));
        this.getContribution(segment,1);
        this.startTextareaAutoresize(textarea);
        segment.parent().addClass('open');
        this.openSegmentStop = new Date();

        this.closeOpenSegmentOperations = this.openSegmentStop - this.openSegmentStart;
        if((typeof this.clickingButtonOperations == 'undefined')||(typeof this.clickingButtonOperations == 'null')) this.clickingButtonOperations = 0;
//            console.log('clicking button operations: ' + this.clickingButtonOperations);
        if((typeof this.changeStatusOperations == 'undefined')||(typeof this.changeStatusOperations == 'null')) this.changeStatusOperations = 0;
//            console.log('change status operations: ' + this.changeStatusOperations);
        console.log('segment close/open time: ' + this.closeOpenSegmentOperations);
//            console.log('total time: ' + (this.clickingButtonOperations + this.changeStatusOperations + this.closeOpenSegmentOperations));
        this.clickingButtonOperations = this.changeStatusOperations = this.closeOpenSegmentOperations =  undefined;
        this.setCurrentSegment(segment);
	},
	
	closeSegment: function(segment) {
        if(typeof segment =='undefined') return true;
        var closeStart = new Date();
//        var segment = this.lastOpenedSegmentOb;
        segment.parent().removeClass('open');

        if(this.heavy) {
        	$(".toggle",segment).hide();
        } else {
        	$(".toggle",segment).hide();
/*
            $(".toggle",segment).hide("blind", {
                direction: "vertical"
            },250);
*/
        }

        $("div.grayed").hide();
        var textarea = this.lastOpenedTextarea;
        this.endTextareaAutoresize(textarea);
		$(segment).removeClass("editor");
		if(!UI.opening) this.setCurrentSegment(segment,1);


/*
        $(".toggle",segment).promise().done(function(){
            console.log('sto per rimuovere la classe editor al segmento '+segment.attr('id'));
            $(segment).removeClass("editor");
//                $(segment).removeClass("editor").find(".editable_textarea").find("button").click(); // a che serve editable_textarea?
        })
*/
//        console.log('tempo di close: ' + ((new Date())-closeStart));
	},
	
	test: function(n) {
//			$("section.editor").find(".close");
        var start = new Date();
        var segment = $('#segment-99560');
		for(i=0;i<=n-1;i++) {
			$(".editor").find(".close");
		}
        var stop = new Date();
        console.log($(".editor").find(".close"));
        console.log(n + ' iterations in ' + (stop-start) + 'ms');
	},

	setCurrentSegment: function(segment,closed) {
        var id_segment = segment.attr('id').split('-')[1];
        if(closed) id_segment = 0;
        var file = this.currentArticleOb;
        var id_job = $('div.projectbar',file).data('job').split('-')[1];
        $.ajax({
            url: config.basepath + '?action=setCurrentSegment',
            data: {
                action: 'setCurrentSegment',
                id_segment: id_segment,
                id_job: id_job
            },
            type: 'POST',
            success: function(d){
            }
        });
	},
	
	scrollSegment: function(segment) {
//		console.log(segment);
		var spread = 23;
		var current = $('section.editor');
		var previousSegment = $(segment).prev('section');
		if(!previousSegment.length) {
			previousSegment = $(segment);
			spread = 33;
		};
		var destination = "#"+previousSegment.attr('id');
		var destinationTop = $(destination).offset().top;
		if($(current).length){//console.log('a');
			if($(segment).offset().top > $(current).offset().top) {//console.log('b');
				if(!current.is($(segment).prev())) {//console.log('c');
					destinationTop = destinationTop - $('section.editor').height() + $(segment).height() - spread;
				} else {//console.log('d');
					destinationTop = destinationTop - spread;
				}
			} else {//console.log('e');
				destinationTop = destinationTop - spread;
			}		
		} else {//console.log('f');
			destinationTop = destinationTop - spread;
		}	
//		console.log(segment.position().top + ' - ' + $(window).scrollTop());
		$("html:not(:animated),body:not(:animated)").animate({ scrollTop: destinationTop-20}, 500 );
	}	        

}

$(document).ready(function(){
    UI.init();
});


$(window).resize(function(){
//	UI.initTargetHeight();
});

$('#segment-' + config.last_opened_segment).ready(function() {
	if((config.last_opened_segment != '')&&(config.last_opened_segment != 0)) {
    	UI.scrollSegment($('#segment-' + config.last_opened_segment));
	}
});
