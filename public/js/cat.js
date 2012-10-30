UI = null;

UI = {
    init: function() {
	
        this.initStart = new Date();
 		this.numMatchesResults = 2;
 		this.numSegments = $('section').length;
        this.body = $('body');
        this.editarea = '';

		$(document).ready(function() {
            var hash=window.location.hash.substr(1);
            if (hash!="" && $("#segment-"+hash).length>0){
              UI.gotoSegment(hash);
            }else if(config.last_opened_segment == '') {
		    	UI.findEmptySegment();
		    } else if(config.last_opened_segment == 0) {
		    	
		    } else if($('#segment-' + config.last_opened_segment).length == 0) {
            	UI.gotoSegment($('section.status-draft, section.status-rejected, section.status-new').first().attr('id').split('-')[1]);
		    }
		    else {
		    	UI.findLastOpenedSegment();
		    }
		})
		
      	this.reinitMMShortcuts();
        $("body").bind('keydown','Ctrl+return', function(e){
            e.preventDefault();
            $('.editor .translated').click();
        }).bind('keydown','Meta+return', function(e){ 
            e.preventDefault();
            $('.editor .translated').click();
        }).bind('keydown','Ctrl+pageup', function(e){ 
            e.preventDefault();
            alert('pageup');;
        }).bind('keydown','Ctrl+down', function(e){ 
            e.preventDefault();
            e.stopPropagation();
            UI.gotoNextSegment();
        }).bind('keydown','Meta+down', function(e){ 
            e.preventDefault();
            e.stopPropagation();
            UI.gotoNextSegment();
        }).bind('keydown','Ctrl+up', function(e){ 
            e.preventDefault();
            e.stopPropagation();
            UI.gotoPreviousSegment();
        }).bind('keydown','Meta+up', function(e){ 
            e.preventDefault();
            e.stopPropagation();
            UI.gotoPreviousSegment();
        }).bind('keydown','Ctrl+left', function(e){ 
            e.preventDefault();
            UI.gotoOpenSegment();
        }).bind('keydown','Meta+left', function(e){ 
            e.preventDefault();
            UI.gotoOpenSegment();
        }).bind('keydown','Ctrl+right', function(e){ 
            e.preventDefault();
            UI.copySource();
        }).bind('keydown','Meta+right', function(e){ 
            e.preventDefault();
            UI.copySource();
        })

        $("input.translated").bind('keydown','tab', function(e){ 
            e.preventDefault();
            $(this).parents('section').find('.editarea').focus();
        })      

        $("header .filter").click(function(e){ 
            e.preventDefault();
            UI.body.toggleClass('filtering');
        })      

        $(".target .editarea").bind('keydown','Shift+tab', function(e){ 
            e.preventDefault();
            $(this).parents('section').find('input.translated').focus();
        })      
 
        $('.sbm').tabify();
        $(".sbm a").click(function() {
            return false
        });
        jQuery('.editarea').trigger('update');

        $("div.notification-box").mouseup(function() {
            return false;
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

        $(".x-stats").click(function(e) {          
            $(".stats").toggle();
        });

		// for future comments implementation
/*
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
        })
*/        
        $("article").on('click','a.number',function(e) {  
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
        }).on('click','.target .editarea',function(e) {
	        if((!$(this).is(UI.editarea))||(UI.editarea == '')||(!UI.body.hasClass('editing'))) {
	        	UI.openSegment(this); 
	        }    
        }).on('click','input.draft, input.translated, input.approved',function(e) {
        	UI.setStatusButtons(this);
            return false;
/**/
        }).on('click','input.translated',function(e) {
//        	UI.copyToNextIfSame();
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
            $("a.status",segment).removeClass("col-approved col-rejected col-done col-draft");
            $("ul.statusmenu",segment).toggle();
            return false;
        }).on('click','a.d',function(e) {          
         	UI.changeStatus(this,'translated',1);
        }).on('click','a.a',function(e) {          
         	UI.changeStatus(this,'approved',1);
        }).on('click','a.r',function(e) {          
        	UI.changeStatus(this,'rejected',1);
        }).on('click','a.f',function(e) {          
        	UI.changeStatus(this,'draft',1);
        }).on('click','a.copysource',function(e) {   
            e.preventDefault();
            UI.copySource();
        }).on('click','.tagmenu, .warning, .viewer, .notification-box li a',function(e) {          
            return false;
        }).on('click','a.close',function(e) {          
            e.preventDefault();
            UI.closeSegment(UI.currentSegment);
        });
/*        
        $("article").on('click','input.con-submit',function(e) {          
            var segment = $(this).parents("section");
            UI.addSegmentComment(segment);
        });
*/
 		$(".grayed").on('click',function(e) {          
            e.preventDefault();
            UI.body.removeClass('justdone');
        })

        this.checkIfFinishedFirst();

        this.initEnd = new Date();
        this.initTime = this.initEnd - this.initStart;
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

    getContribution: function(segment,next) {
      
        var n = (next)? this.getNextSegment(segment,'untranslated') : $(segment);

        if($(n).hasClass('loaded')) {
        	return false;
        }
        if((!n.length)&&(next)) return false;
        var id = n.attr('id');
        var id_segment = id.split('-')[1];
        var txt = $('.original',n).text();
        
        if(!next) {
        	$(".loader",n).addClass('loader_on')
        }
        $.ajax({
            url: config.basepath + '?action=getContribution',
            data: {
                action: 'getContribution',
                id_segment: id_segment,
                text: txt,
                id_job: config.job_id,
                num_results: this.numMatchesResults,
                id_translator : config.id_translator
            },
            type: 'POST',            
            dataType: 'json',
            context: $('#'+id),
            complete: function (d){
                $(".loader",n).removeClass('loader_on');
            },
            success: function(d){
 				var isActiveSegment = $(this).hasClass('editor');
	  			var editarea = $('.target .editarea', this);
	            var _this = this;

	  			if(d.data.matches.length) {
		  			var translation = d.data.matches[0].translation;
					var perc_t=$(".percentuage",_this).attr("title");
		                        $(".percentuage",_this).attr("title",perc_t + " " + d.data.matches[0].created_by);

	                var match = d.data.matches[0].match;
		  			var editareaLength = editarea.text().length;

	                if (editareaLength==0){
	                    UI.copySuggestionInEditarea(this,translation,editarea,match);
	                }
	                if(isActiveSegment) {
		                editarea.removeClass("indent");
		 			} else {
		 				if (editareaLength==0) editarea.addClass("indent");
		 			}
	

	                var segment_id = _this.attr('id');
	                $(_this).removeClass('loaded').addClass('loaded');
	                $('.sub-editor .overflow',_this).empty();
	                
	                $.each(d.data.matches, function(index) {
	                	var disabled = (this.id=='0')? true : false;                 
                        cb= this['created_by'];   
	                    cl_suggestion=UI.getPercentuageClass(this['match']);
                    
                        $('.sub-editor .overflow',_this).append('<ul class="graysmall" data-item="'+(index+1)+'" data-id="'+this.id+'"><li >'+((disabled)?'':' <a id="'+segment_id+'-tm-'+this.id+'-delete" href="#" class="trash" title="delete this row"></a>')+'<span id="'+segment_id+'-tm-'+this.id+'-source" class="suggestion_source">'+this.segment+'</span></li><li class="b"><span class="graysmall-message">ALT+'+(index+1)+'</span><span id="'+segment_id+'-tm-'+this.id+'-translation" class="translation">'+this.translation+'</span></li><ul class="graysmall-details"><li class="percent ' + cl_suggestion + '">'+(this.match)+'</li><li>'+this['last_update_date']+'</li><li class="graydesc">Source: <span class="bold">'+cb+'</span></li></ul></ul>');
	                });
	                $('.sub-editor .overflow a.trash',_this).click(function(e) {
	        		    e.preventDefault();
	        		    var ul = $(this).parents('.graysmall');

             			source = $('.suggestion_source',ul).html();
	        		    target = $('.translation',ul).html();

	        		    ul.remove();
						$.ajax({
						    url: config.basepath,
						    data: {
						        action: 'deleteContribution',
						        source_lang: config.source_lang,
						        target_lang: config.target_lang,
						        seg: source,
                                tra: target,
                                id_translator : config.id_translator
						    },
						    type: 'POST',
						    dataType: 'json',
						    complete: function (d){
						    },
						    success: function(d){
						    	console.log('match deleted');
						    	$(".editor .matches .graysmall").each(function(index){
						    		$(this).find('.graysmall-message').text('ALT+'+(index+1));
						    		$(this).attr('data-item',index+1);
						    		UI.reinitMMShortcuts();
								})
						    }
						});
	                });

	                $('.translated',this).removeAttr('disabled');
	                $('.draft',this).removeAttr('disabled');
	  			} else {
	  				console.log('no matches');
	                $(_this).removeClass('loaded').addClass('loaded');
					$('.sub-editor .overflow',_this).append('<ul class="graysmall message"><li>Sorry. Can\'t help you this time. Check the language pair if you feel this is weird.</li></ul>');  				
	  			}

                if (d.data.matches==0){
                    $(".sbm > .matches", _this).hide();
                } else {
                    $('.submenu li.matches a span', this).text('('+d.data.matches.length+')');
                }
            }
        });
    },

	getNextSegment: function(segment,status) {
		var seg = segment;
		var addStatus = (typeof status == 'undefined')? '' : (status == 'untranslated')? '.status-draft, section.status-rejected, section.status-new' : '';
        var n = $(seg).nextAll('section'+addStatus).first() || $(seg).parents('article').next().find('section'+addStatus).first();
		if(typeof n == 'undefined') return false;
        if(!$(seg).nextAll('section').length) {
    		n = $(seg).parents('article').next().find('section'+addStatus).first();
        };
        return n;
 	},
 	
	setContribution: function(segment,status,byStatus) {
		if((status=='draft')||(status=='rejected')) return false;
        var source = $('.source .original',segment).text();
        var target = $('.target .editarea',segment).html();
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
                source_lang: config.source_lang,
                target_lang: config.target_lang,
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
        var status = status;
        var translation = $('.target .editarea',segment).html();

        if(translation == '') return false;
        var time_to_edit = UI.editTime;
        var id_translator = config.id_translator;
        $.ajax({
            url: config.basepath + '?action=setTranslation',
            data: {
                action: 'setTranslation',
                id_segment: id_segment,
                id_job: config.job_id,
                id_first_file: file.attr('id').split('-')[1],
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
         			UI.setDownloadStatus(d.stats);
         			UI.setProgress(d.stats);
         		};
            }
        });
    },
/*
	// for future implementation

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
*/

    setStatusButtons: function(button) {
        this.editStop = new Date();
        this.lastEditTime = this.editStop - this.editStart;
		var segment = this.currentSegment;
        var editSec = $('.timetoedit .edit-sec',segment);
        var editMin = $('.timetoedit .edit-min',segment);
        this.previousEditTime = $('.timetoedit',segment).data('raw_time_to_edit');
        this.editTime = this.previousEditTime + this.lastEditTime;
        var editedTime = this.millisecondsToTime(this.editTime);
        editMin.text(this.zerofill(editedTime[0],2));
        editSec.text(this.zerofill(editedTime[1],2));
        $('.timetoedit',segment).data('raw_time_to_edit', this.editTime)
        var statusSwitcher = $(".status",segment);

        statusSwitcher.removeClass("col-approved col-rejected col-done col-draft");
        var statusToGo = ($(button).hasClass('translated'))? 'untranslated' : '';
        var nextSegment = this.getNextSegment(segment,statusToGo);
        if(!nextSegment.length) {
        	$(".editor:visible").find(".close").click();
        	$('.downloadtr-button').focus();
        	return false;
        };
        this.buttonClickStop = new Date();
        this.clickingButtonOperations = this.buttonClickStop - this.editStop;
        this.copyToNextIfSame(nextSegment);
        $(".target .editarea", nextSegment).click();

/*
        var s = $(this);
        var newStatus = (s.hasClass('translated'))? 'translated' : (s.hasClass('draft'))? 'draft' : (s.hasClass('approved'))? 'approved':'';

    	this.changeStatus(this,newStatus,0);
        this.changeStatusStop = new Date();
        this.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;
        console.log(this.changeStatusOperations);
*/
    },
    
    changeStatus: function(ob,status,byStatus) {
        var segment = $(ob).parents("section");
        $('.percentuage',segment).removeClass('visible');
        this.setContribution(segment,status,byStatus);
        this.setTranslation(segment,status);
    },

    setStatus: function(segment,status) {
		segment.removeClass("status-draft status-translated status-approved status-rejected").addClass("status-"+status);
    },
    
    setDownloadStatus: function(stats) {
    	var t = 'approved';
    	if(parseFloat(stats.TRANSLATED)) t = 'translated';
    	if(parseFloat(stats.DRAFT)) t = 'draft';
    	if(parseFloat(stats.REJECTED)) t = 'draft';
    	$('.downloadtr-button').removeClass("draft translated approved").addClass(t);
    },

    setProgress: function(stats) {
    	var s = stats;
    	m = $('footer .meter');
    	var status = 'approved';
    	var total = s.TOTAL;
    	var t_perc = s.TRANSLATED_PERC;
    	var a_perc = s.APPROVED_PERC;
    	var d_perc = s.DRAFT_PERC;
    	var r_perc = s.REJECTED_PERC;	

    	var t_perc_formatted = s.TRANSLATED_PERC_FORMATTED;
    	var a_perc_formatted = s.APPROVED_PERC_FORMATTED;
    	var d_perc_formatted = s.DRAFT_PERC_FORMATTED;
    	var r_perc_formatted = s.REJECTED_PERC_FORMATTED;
    	

    	var d_formatted = s.DRAFT_FORMATTED;
    	var r_formatted = s.REJECTED_FORMATTED;
    	var t_formatted = s.TODO_FORMATTED;
    	
    	var wph 		= s.WORDS_PER_HOUR;
    	var completion  = s.ESTIMATED_COMPLETION;
        UI.progress_perc = Math.floor(s.APPROVED_PERC + s.TRANSLATED_PERC);
        this.checkIfFinished();

        UI.done_percentage = UI.progress_perc;

    	$('.approved-bar',   m).css('width', a_perc + '%').attr('title','Approved ' + a_perc_formatted + '%');
    	$('.translated-bar', m).css('width', t_perc + '%').attr('title','Translated ' + t_perc_formatted + '%');
    	$('.draft-bar',      m).css('width', d_perc + '%').attr('title','Draft ' + d_perc_formatted + '%');
    	$('.rejected-bar',   m).css('width', r_perc + '%').attr('title','Rejected ' + r_perc_formatted + '%');
    
	$('#stat-progress').html(UI.progress_perc);
	
    	$('#stat-todo strong').html(t_formatted);
    	$('#stat-wph strong').html(wph);
    	$('#stat-completion strong').html(completion);
    },

    checkIfFinished: function(closing) {
        if(((UI.progress_perc!=UI.done_percentage)&&(UI.progress_perc == '100'))||((closing)&&(UI.progress_perc == '100'))) {
        	this.body.addClass('justdone');
        } else {
        	this.body.removeClass('justdone');
        }    	
    },

    checkIfFinishedFirst: function() {
        if($('section').length == $('section.status-translated, section.status-approved').length) {
        	this.body.addClass('justdone');
        }
    },


    setFileProgress: function(stats) {
    	var s = stats;
    	var total = s.TOTAL_FORMATTED;
    	var id_file = s.ID_FILE;
    	var d_formatted = s.DRAFT_FORMATTED;
    	var r_formatted = s.REJECTED_FORMATTED;
		
		var file = $('#file-'+id_file);
		$('.file-eqwords strong',file).text(total);
		$('.file-draft strong',file).text(d_formatted);
		$('.file-rejected strong',file).text(r_formatted);

    },

    findEmptySegment: function() {
        var found=false;
        $(".target .editarea").each(function(){
            var editarea = $(this);
			if (editarea.text()=="" && found==false) {
                found=true;
				UI.currentSegment = editarea.parents("section");
                editarea.click();
            }
        })

    },

    findLastOpenedSegment: function() {
    	var editarea = $('#segment-' + config.last_opened_segment + ' .editarea');
 		UI.currentSegment = editarea.parents("section");
    	editarea.click();
    },

    copySuggestionInEditarea: function(segment,translation,editarea,match) {
	    percentageClass = this.getPercentuageClass(match);

        if($.trim(translation) != '') {
        	editarea.html(translation).addClass('fromSuggestion');

        	$('.percentuage',segment).text(match).removeClass('per-orange per-green per-blue per-yellow').addClass(percentageClass).addClass('visible');
        }
 	},

	millisecondsToTime: function(milli) {
      var milliseconds = milli % 1000;
      var seconds = Math.round((milli / 1000) % 60);
      var minutes = Math.floor((milli / (60 * 1000)) % 60);
      return [minutes, seconds];
	},

	zerofill: function(i,l,s) {
		var o = i.toString();
		if (!s) { s = '0'; }
		while (o.length < l) {
			o = s + o;
		}
		return o;
	},
	
	openSegment: function(editarea) {
        this.openSegmentStart = new Date();
        this.editarea = $(editarea);

		if(!window.getSelection().isCollapsed) return false;
		$(".statusmenu:visible").hide();

		// current and last opened object reference caching
		this.lastOpenedSegment = this.currentSegment;
		this.lastOpenedEditarea = this.editarea;
		
		this.currentSegment = segment = $(editarea).parents("section");
        this.currentSegmentId = segment.attr('id').split('-')[1];
		this.currentArticle = segment.parent();

		this.scrollSegment(segment);
		$(editarea).removeClass("indent");
		this.getContribution(segment,0);

        this.opening = true;
        this.closeSegment(this.lastOpenedSegment);
        this.opening = false;
        this.body.addClass('editing');

        segment.addClass("editor");
        this.editarea.focus();
        this.editStart = new Date();

        this.getContribution(segment,1);
        this.openSegmentStop = new Date();

        this.closeOpenSegmentOperations = this.openSegmentStop - this.openSegmentStart;
        console.log('close/open time: ' + this.closeOpenSegmentOperations);
       

        if((typeof this.clickingButtonOperations == 'undefined')||(typeof this.clickingButtonOperations == 'null')) this.clickingButtonOperations = 0;
        if((typeof this.changeStatusOperations == 'undefined')||(typeof this.changeStatusOperations == 'null')) this.changeStatusOperations = 0;
        this.clickingButtonOperations = this.changeStatusOperations = this.closeOpenSegmentOperations =  undefined;
        this.setCurrentSegment(segment);
	},
	
	closeSegment: function(segment) {
        if(typeof segment =='undefined') return true;
        var closeStart = new Date();
        this.body.removeClass('editing');
		$(segment).removeClass("editor");
		if(!this.opening) {
			this.setCurrentSegment(segment,1);
			this.checkIfFinished(1);
		}
	},

	gotoPreviousSegment: function() {
        var prev = $('.editor').prev();
        if(prev.is('section')) {
        	$('.target .editarea',prev).click();
        } else {
        	prev = $('.editor').parents('article').prev().find('section:last');
        	if(prev.length) {
        		$('.target .editarea',prev).click();
        	} else {
        		this.topReached();
        	}
        };
	},

    gotoSegment: function(id){
        var el=$("#segment-"+id+"-target").find(".editarea");
        el.click();
        
    },
	gotoNextSegment: function() {
        var next = $('.editor').next();
        console.log(next);
        if(next.is('section')) {
        	$('.target .editarea',next).click();
        } else {
        	next = $('.editor').parents('article').next().find('section:first');
        	if(next.length) {
        		$('.target .editarea',next).click();
        	} else {
        	}
        };
	},

	gotoOpenSegment: function() {
		this.scrollSegment(this.currentSegment);
	},	

	setCurrentSegment: function(segment,closed) {
        var id_segment = segment.attr('id').split('-')[1];
        if(closed) {
            id_segment = 0;
        }else{
            window.location.hash=id_segment;
        }
        var file = this.currentArticle;
        $.ajax({
            url: config.basepath + '?action=setCurrentSegment',
            data: {
                action: 'setCurrentSegment',
                id_segment: id_segment,
                id_job: config.job_id
            },
            type: 'POST',
            success: function(d){
            }
        });
	},

	reinitMMShortcuts: function(a) {
		$('body').unbind('keydown');
		$("body, .target .editarea").bind('keydown','Alt+1', function(e){ 
            e.preventDefault();
            UI.chooseSuggestion('1');
        }).bind('keydown','Alt+2', function(e){ 
            e.preventDefault();
            UI.chooseSuggestion('2');
        }).bind('keydown','Alt+3', function(e){ 
            e.preventDefault();
            UI.chooseSuggestion('3');
        }).bind('keydown','Alt+4', function(e){ 
            e.preventDefault();
            UI.chooseSuggestion('4');
        }).bind('keydown','Alt+5', function(e){ 
            e.preventDefault();
            UI.chooseSuggestion('5');
        })
	},

	chooseSuggestion: function(w) {
        UI.copySuggestionInEditarea(UI.currentSegment,$('.editor ul[data-item='+w+'] li.b .translation').text(),$('.editor .target .editarea'),$('.editor ul[data-item='+w+'] ul.graysmall-details .percent').text());
		UI.editarea.focus().effect("highlight", {}, 1000);		
	},

	topReached: function() {
		var jumpto = $(this.currentSegment).offset().top;
        $("html,body").animate({
            scrollTop: 0
        }, 200 ).animate({
            scrollTop: jumpto-50
            }, 200 );
	},

	copySource: function() {
        var source_val = $.trim($(".source > span.original",this.currentSegment).text());
        $(".target .editarea",this.currentSegment).text(source_val).keyup().focus();
        $(".target .editarea",this.currentSegment).effect("highlight", {}, 1000);
	},

	copyToNextIfSame: function(nextSegment) {
		if($('.source',this.currentSegment).data('original') == $('.source',nextSegment).data('original')) {
			if($('.editarea',nextSegment).hasClass('fromSuggestion')) {
				$('.editarea',nextSegment).text(this.editarea.text());
			}
		}
	},
				
	scrollSegment: function(segment) {
		var spread = 23;
		var current = $('section.editor');
		var previousSegment = $(segment).prev('section');
		if(!previousSegment.length) {
			previousSegment = $(segment);
			spread = 33;
		};
		var destination = "#"+previousSegment.attr('id');
		var destinationTop = $(destination).offset().top;
		if($(current).length){
			if($(segment).offset().top > $(current).offset().top) {
				if(!current.is($(segment).prev())) {
					destinationTop = destinationTop - $('section.editor').height() + $(segment).height() - spread;
				} else {
					destinationTop = destinationTop - spread;
				}
			} else {
				destinationTop = destinationTop - spread;
			}		
		} else {
			destinationTop = destinationTop - spread;
		}	
        $("html,body").animate({
	        scrollTop: destinationTop-20
        }, 500 );
	}	        

}

$(document).ready(function(){
    UI.init();
});


$(window).resize(function(){
});

$('#segment-' + config.last_opened_segment).ready(function() {
	if((config.last_opened_segment != '')&&(config.last_opened_segment != 0)) {
    	var target = ($('#segment-' + config.last_opened_segment).length)? $('#segment-' + config.last_opened_segment) : $('section.status-draft, section.status-rejected, section.status-new').first();
    	UI.scrollSegment(target);
	}
});
