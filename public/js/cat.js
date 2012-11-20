UI = null;

UI = {
    render: function(firstLoad) {
		this.isWebkit = $.browser.webkit;
		this.firstLoad = firstLoad;
		this.initSegNum = 200;
		this.moreSegNum = 50;
		this.loadingMore = false;
		this.infiniteScroll = true;
		this.fork = (Loader.detect('fork'))? Loader.detect('fork').substring(0,1) : false;
		UI.detectStartSegment();
		UI.getSegments();
    },
    
    init: function() {
		this.initStart = new Date();
		console.log('Render time: ' + (this.initStart - renderStart));
		this.numMatchesResults = 2;
		this.numSegments = $('section').length;
		this.body = $('body');
		this.editarea = '';
        this.byButton = false;
		this.heavy = ($('section').length > 200)? true : false;


		
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

        $("header .filter").click(function(e){ 
            e.preventDefault();
            UI.body.toggleClass('filtering');
        })      

		 $(".replace").click(function(e){ 
            e.preventDefault();
            UI.body.toggleClass('replace-box');
						
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
            $("#search").toggle();
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

            UI.createStatusMenu(statusMenu);   
            statusMenu.show();
 			var autoCloseStatusMenu = $('html').bind("click.vediamo", function(event) {
				$("ul.statusmenu").hide();
				$('html').unbind('click.vediamo');
				UI.removeStatusMenu(statusMenu);
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
        	this.onclickEditarea = new Date();
	        if((!$(this).is(UI.editarea))||(UI.editarea == '')||(!UI.body.hasClass('editing'))) {
	        	UI.openSegment(this); 
	        }    
			console.log('Total onclick Editarea: ' + ( (new Date()) - this.onclickEditarea));
/*
        }).on('click','a.draft, a.translated, a.approved',function(e) {

       		console.log('UI.nextSegmentId:');
       		console.log(UI.nextSegmentId);
       		if(UI.segmentIsLoaded(UI.nextSegmentId)) {
       			UI.scrollSegment1($('#segment-'+UI.nextSegmentId));
       		} else {
       			var m = confirm('The next untranslated segment is outside the current view.');
       			var l = location;
//       			if(m) location.href = l.host+'/'+l.pathname+'/#'+UI.nextSegmentId;
       			if(m) {
					config.last_opened_segment = UI.nextSegmentId;
					$('#outer').empty();
//					return false;
					


//       				window.location = 'http://matecat.translated.home/translate/Master_File.docx_en-GB_it-IT.sdlxliff/en-fr/1009-7qddvmp2/#582536';
//       				return true;
       			}
       		}
       		console.log('dopo il return');
       		UI.setStatusButtons(this);
*/
        }).on('click','a.translated',function(e) {
            e.preventDefault();
       		if(UI.segmentIsLoaded(UI.nextSegmentId)) {
       			console.log('next segment is loaded');
       			UI.scrollSegment($('#segment-'+UI.nextSegmentId));
	       		UI.setStatusButtons(this);
	        	UI.changeStatus(this,'translated',0);
	            UI.changeStatusStop = new Date();
	            UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;
	        } else {
       			console.log('next segment is not loaded');
       			var m = confirm('The next untranslated segment is outside the current view.');
       			if(m) {
					UI.infiniteScroll = false;
					config.last_opened_segment = UI.nextSegmentId;
					window.location.hash = UI.nextSegmentId;
					$('#outer').empty();
					UI.render(false);
       			}
       		}
        }).on('click','a.draft',function(e) {   
       		UI.setStatusButtons(this);
         	UI.changeStatus(this,'draft',0);
            UI.changeStatusStop = new Date();
            UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;
        }).on('click','a.approved',function(e) {          
       		UI.setStatusButtons(this);
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
            UI.closeSegment(UI.currentSegment,1);
        });

    	UI.toSegment = true;
	 	UI.gotoSegment(this.startSegmentId);
/*
        if(this.startSegmentId) {
	    	UI.toSegment = true;
        	UI.gotoSegment(this.startSegmentId);
        } else {
	    	UI.gotoSegment($('section').first().attr('id').split('-')[1]);
        }
*/

/*        
        $("article").on('click','input.con-submit',function(e) {          
            var segment = $(this).parents("section");
            UI.addSegmentComment(segment);
        });
*/
 		$(".end-message-box a.close").on('click',function(e) {          
            e.preventDefault();
            UI.body.removeClass('justdone');
        })

		$(window).scroll(function() {
		   if($(window).scrollTop() + $(window).height() > ($(document).height())*0.95) {
		       if(UI.infiniteScroll) UI.getMoreSegments('after');
		   }
		   if($(window).scrollTop() == 0) {
		       if(UI.infiniteScroll) UI.getMoreSegments('before');
		   }
		});

        this.checkIfFinishedFirst();

        $("section .close").bind('keydown','Shift+tab', function(e){ 
            e.preventDefault();
            $(this).parents('section').find('a.translated').focus();
        })      

        $("a.translated").bind('keydown','tab', function(e){ 
            e.preventDefault();
            $(this).parents('section').find('.close').focus();
        })      



        this.initEnd = new Date();
        this.initTime = this.initEnd - this.initStart;
        console.log('Init time: ' + this.initTime);

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
      
        var n = (next)? this.nextSegmentId : $(segment);

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
//        		return false;

        $.ajax({
            url: config.basepath + '?action=getContribution'+this.appendTime(),
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
        		return false;
	            UI.renderContributions(d,this);
                if (d.data.matches==0){
                    $(".sbm > .matches", this).hide();
                } else {
                    $('.submenu li.matches a span', this).text('('+d.data.matches.length+')');
                }
            }
        });
    },

	setDeleteSuggestion: function(segment) {
        $('.sub-editor .overflow a.trash',segment).click(function(e) {
		    e.preventDefault();
		    var ul = $(this).parents('.graysmall');

 			source = $('.suggestion_source',ul).text();
		    target = $('.translation',ul).text();

		    ul.remove();
			$.ajax({
			    url: config.basepath+'?'+UI.appendTime(),
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
 	},

	renderContributions: function(d,segment) {
		var isActiveSegment = $(segment).hasClass('editor');
		var editarea = $('.target .editarea', segment);
		if(d.data.matches.length) {
  			var translation = d.data.matches[0].translation;
			var perc_t=$(".percentuage",segment).attr("title");
            $(".percentuage",segment).attr("title",''+perc_t + "Created by " + d.data.matches[0].created_by);
            var match = d.data.matches[0].match;
  			var editareaLength = editarea.text().length;

            if (editareaLength==0){
                UI.copySuggestionInEditarea(segment,translation,editarea,match);
            }
            if(isActiveSegment) {
                editarea.removeClass("indent");
 			} else {
 				if (editareaLength==0) editarea.addClass("indent");
 			}


            var segment_id = segment.attr('id');
            $(segment).removeClass('loaded').addClass('loaded');
            $('.sub-editor .overflow',segment).empty();
            
            $.each(d.data.matches, function(index) {
            	var disabled = (this.id=='0')? true : false;                 
                cb= this['created_by'];                    
                cl_suggestion=UI.getPercentuageClass(this['match']);
            
                if(!$('.sub-editor',segment).length) {
					UI.createFooter(segment);
                }
                $('.sub-editor .overflow',segment).append('<ul class="graysmall" data-item="'+(index+1)+'" data-id="'+this.id+'"><li >'+((disabled)?'':' <a id="'+segment_id+'-tm-'+this.id+'-delete" href="#" class="trash" title="delete this row"></a>')+'<span id="'+segment_id+'-tm-'+this.id+'-source" class="suggestion_source">'+this.segment+'</span></li><li class="b"><span class="graysmall-message">ALT+'+(index+1)+'</span><span id="'+segment_id+'-tm-'+this.id+'-translation" class="translation">'+this.translation+'</span></li><ul class="graysmall-details"><li class="percent ' + cl_suggestion + '">'+(this.match)+'</li><li>'+this['last_update_date']+'</li><li class="graydesc">Source: <span class="bold">'+cb+'</span></li></ul></ul>');
            });
            UI.setDeleteSuggestion(segment);
            $('.translated',segment).removeAttr('disabled');
            $('.draft',segment).removeAttr('disabled');
		} else {
			console.log('no matches');
            $(segment).removeClass('loaded').addClass('loaded');
			$('.sub-editor .overflow',segment).append('<ul class="graysmall message"><li>Sorry. Can\'t help you this time. Check the language pair if you feel this is weird.</li></ul>');  				
		}

 	},
 	
	getNextSegment: function(segment,status) {
		return $('#segment-'+this.nextSegmentId);
/*
		var seg = segment;
		var addStatus = (typeof status == 'undefined')? '' : (status == 'untranslated')? '.status-draft, .status-rejected, .status-new' : '';

		var n = this.getNextSibling(seg,addStatus);
		if(typeof n == 'undefined') return false;
        if(!$(seg).nextAll('section').length) {
    		n = $(seg).parents('article').next().find('section'+addStatus).first();
        };
        return n;
*/
 	},

	getNextSibling: function(seg,addStatus) {
		var next = $(seg).nextAll('section'+addStatus).first();
//		var next = (this.isWebkit)? $('#'+$(seg).attr('id')+' ~ section'+addStatus, $(seg).parents('article')).first() : $(seg).nextAll('section'+addStatus).first();
		return next;
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
            url: config.basepath + '?action=setContribution'+this.appendTime(),
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
            url: config.basepath + '?action=setTranslation'+this.appendTime(),
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
        this.editTime = this.editStop - this.editStart;
        var editedTime = this.millisecondsToTime(this.editTime);
		var segment = this.currentSegment;
		if(config.time_to_edit_enabled) {
	        var editSec = $('.timetoedit .edit-sec',segment);
	        var editMin = $('.timetoedit .edit-min',segment);
        	editMin.text(this.zerofill(editedTime[0],2));
       		editSec.text(this.zerofill(editedTime[1],2));
			$('.timetoedit',segment).data('raw_time_to_edit', this.editTime);
   		}
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
        this.copyToNextIfSame(nextSegment);
        this.byButton = true;
        console.log('NEXT SEGMENT:');
        console.log(nextSegment);
        $(".editarea", nextSegment).click();
    },
    
    changeStatus: function(ob,status,byStatus) {
    	var segment = (byStatus)? $(ob).parents("section") : $('#'+$(ob).data('segmentid'));
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

        this.done_percentage = this.progress_perc;

    	$('.approved-bar',   m).css('width', a_perc + '%').attr('title','Approved ' + a_perc_formatted + '%');
    	$('.translated-bar', m).css('width', t_perc + '%').attr('title','Translated ' + t_perc_formatted + '%');
    	$('.draft-bar',      m).css('width', d_perc + '%').attr('title','Draft ' + d_perc_formatted + '%');
    	$('.rejected-bar',   m).css('width', r_perc + '%').attr('title','Rejected ' + r_perc_formatted + '%');
    
		$('#stat-progress').html(this.progress_perc);
	
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
        	editarea.text(translation).addClass('fromSuggestion');

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

	justSelecting: function() {
		if(window.getSelection().isCollapsed) return false;
		var selContainer = $(window.getSelection().getRangeAt(0).startContainer.parentNode);
		return ((selContainer.hasClass('editarea'))&&(!selContainer.is(UI.editarea)));
	},
	
	openSegment: function(editarea) {
        this.openSegmentStart = new Date();
//        console.log('this.byButton:');
//        console.log(this.byButton);
        if(!this.byButton) {
        	if(this.justSelecting()) return;
        }
        this.byButton = false;
        this.editarea = $(editarea);

//		if(!window.getSelection().isCollapsed) return false;

		// current and last opened object reference caching
		this.lastOpenedSegment = this.currentSegment;
//		this.lastOpenedEditarea = this.editarea;
		this.lastOpenedEditarea = $('.editarea',this.currentSegment);

        this.currentSegmentId = this.lastOpenedSegmentId = this.editarea.data('sid');
		
		this.currentSegment = segment = $('#segment-'+this.currentSegmentId);
		this.currentArticle = segment.parent();
		this.activateSegment();
//console.log('this.currentSegmentId: '+this.currentSegmentId);
        this.setCurrentSegment(segment);

		this.focusEditarea = setTimeout(function(){UI.editarea.focus();clearTimeout(UI.focusEditarea)},100);

		$(editarea).removeClass("indent");

		this.getContribution(segment,0);

        this.opening = true;
        this.closeSegment(this.lastOpenedSegment,0);
        this.opening = false;
        this.body.addClass('editing');

        segment.addClass("editor");
        this.editarea.attr('contenteditable','true');

        this.editStart = new Date();

        this.getContribution(segment,1);

		console.log('close/open time: ' + ( (new Date()) - this.openSegmentStart));
	},
	
	closeSegment: function(segment,byButton) {
        if((typeof segment =='undefined')||(typeof UI.toSegment !='undefined')) {
        	UI.toSegment = undefined;
        	return true;
        }

        var closeStart = new Date();
		this.deActivateSegment(byButton);

        this.lastOpenedEditarea.attr('contenteditable','false');
        this.body.removeClass('editing');
		$(segment).removeClass("editor");
		$('#downloadProject').focus();
		if(!this.opening) {
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
        $(el).click();
    },

	gotoNextSegment: function() {
        var next = $('.editor').next();
        if(next.is('section')) {
        	$('.editarea',next).click();
        } else {
        	next = this.currentArticle.next().find('section:first');
        	if(next.length) {
        		$('.editarea',next).click();
        	}
        };
	},

	gotoOpenSegment: function() {
		this.scrollSegment(this.currentSegment);
	},	

	setCurrentSegment: function(segment,closed) {
		this.nextSegmentId = segment.next();
        var id_segment = this.currentSegmentId;
        if(closed) {
            id_segment = 0;
            UI.currentSegment = undefined;
        } else {
			setTimeout(function(){
//				console.log('UI.currentSegmentId:'+UI.currentSegmentId);
				window.location.hash = UI.currentSegmentId
			},300);        }
        var file = this.currentArticle;
        $.ajax({
            url: config.basepath + '?action=setCurrentSegment'+this.appendTime(),
            data: {
                action: 'setCurrentSegment',
                id_segment: id_segment,
                id_job: config.job_id
            },
            type: 'POST',
            success: function(d){
            	UI.nextSegmentId = d.nextSegmentId;
            }
        });
	},

	reinitMMShortcuts: function(a) {
		$('body').unbind('keydown');
		$("body, .target .editarea").bind('keydown','Alt+1', function(e){ 
            e.preventDefault();
            if(e.which != 97) {
            	UI.chooseSuggestion('1');
            }
        }).bind('keydown','Alt+2', function(e){ 
            e.preventDefault();
            if(e.which != 98) {
            	UI.chooseSuggestion('2');
            }
        }).bind('keydown','Alt+3', function(e){ 
            e.preventDefault();
            if(e.which != 99) {
            	UI.chooseSuggestion('3');
            }            
        }).bind('keydown','Alt+4', function(e){ 
            e.preventDefault();
            if(e.which != 100) {
            	UI.chooseSuggestion('4');
            }            
        }).bind('keydown','Alt+5', function(e){ 
            e.preventDefault();
            if(e.which != 101) {
            	UI.chooseSuggestion('5');
            }            
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
console.log('segment:');
console.log(segment);
		var spread = 23;
		var current = this.currentSegment;
		var previousSegment = $(segment).prev('section');
		console.log('previousSegment:');
		console.log(previousSegment);
		if(!previousSegment.length) {
			console.log('!previousSegment.length');
			previousSegment = $(segment);
			spread = 33;
		};
		console.log('previousSegment after:');
		console.log(previousSegment);
		var destination = "#"+previousSegment.attr('id');
		console.log('destination:');
		console.log(destination);
		var destinationTop = $(destination).offset().top;
		console.log('destinationTop:');
		console.log(destinationTop);
		console.log('current:');
		console.log(current);
		console.log('segment:');
		console.log(segment);
		console.log('$(segment).prev():');
		console.log($(segment).prev());
		console.log('current.is($(segment).prev()):');
		if(typeof current != 'undefined') console.log(current.is($(segment).prev()));
						
		if($(current).length){ // if there is an open segment
			if($(segment).offset().top > $(current).offset().top) { // if segment to open is below the current segment
				if(!current.is($(segment).prev())) { // if segment to open is not the immediate follower of the current segment
					console.log('segment to open is below but not the immediate follower of the current segment');
					console.log('destinationTop: '+destinationTop);
					console.log('$(destination).height(): '+$(destination).height());
					console.log('$(current).height(): '+$(current).height());
					console.log(this.firstLoad);
					var diff = (this.firstLoad)? ($(current).height()-200+120) : 20; 
//					destinationTop = destinationTop - this.currentSegment.height() + $(segment).height() - spread;
					console.log('diff: '+diff);
					destinationTop = destinationTop - diff;
				} else { // if segment to open is the immediate follower of the current segment
					console.log('segment to open is the immediate follower of the current segment');
//					console.log('destinationTop: '+destinationTop);
					destinationTop = destinationTop - spread;
				}
			} else { // if segment to open is above the current segment
				console.log('segment to open is above the current segment');
				console.log('destinationTop: '+destinationTop);
				destinationTop = destinationTop - spread;
			}		
		} else { // if no segment is opened
			console.log('no segment is opened');
			console.log('destinationTop: '+destinationTop);

			destinationTop = destinationTop - spread;
			if(UI.isWebkit) {
				console.log('webkit');
				console.log('destinationTop: '+destinationTop);
			}
		}	
console.log('SCROLL');

        $("html,body").animate({
	        scrollTop: destinationTop-20
        }, 500 );

	},	        

	activateSegment: function() {
		this.createFooter(this.currentSegment);
		this.createButtons();
		this.createHeader();
	},

	deActivateSegment: function(byButton) {
		this.removeButtons(byButton);
		this.removeHeader(byButton);
		this.removeFooter(byButton);
	},
			     
	createButtons: function() {
	    var disabled = (this.currentSegment.hasClass('loaded'))? '' : ' disabled="disabled"';
		var buttons = '<li><a id="segment-'+this.currentSegmentId+'-copysource" href="#" class="btn copysource" data-segmentid="segment-'+this.currentSegmentId+'" title="Copy source to target"></a><p>CTRL+RIGHT</p></li><li><a id="segment-'+this.currentSegmentId+'-button-draft" data-segmentid="segment-'+this.currentSegmentId+'" href="#" class="draft" '+disabled+' >DRAFT</a></li><li style="margin-right:-20px"><a id="segment-'+this.currentSegmentId+'-button-translated" data-segmentid="segment-'+this.currentSegmentId+'" href="#" class="translated"'+disabled+' >TRANSLATED</a><p>CTRL+ENTER</p></li>';
//		var buttons = '<li><a id="segment-'+this.currentSegmentId+'-copysource" href="#" class="btn copysource" data-segmentid="segment-'+this.currentSegmentId+'" title="Copy source to target"></a><p>CTRL+RIGHT</p></li><li style="margin-right:-20px"><a id="segment-'+this.currentSegmentId+'-button-translated" data-segmentid="segment-'+this.currentSegmentId+'" href="#" class="translated"'+disabled+' >TRANSLATED</a><p>CTRL+ENTER</p></li>';

		$('#segment-'+this.currentSegmentId+'-buttons').append(buttons);
	},

	removeButtons: function(byButton) {
		var segment = (byButton)? this.currentSegment : this.lastOpenedSegment;
		$('#'+segment.attr('id')+'-buttons').empty();
	},

	createHeader: function() {
		var header = '<h2 title="" class="percentuage"><span></span></h2><a href="#" id="segment-'+this.currentSegmentId+'-close" class="close" title="Close this segment"></a>';
		$('#'+this.currentSegment.attr('id')+'-header').html(header);
	},

	removeHeader: function(byButton) {
		var segment = (byButton)? this.currentSegment : this.lastOpenedSegment;
		$('#'+segment.attr('id')+'-header').empty();
	},

	createFooter: function(segment) {
		if($('.footer', segment).text() != '') return false;
		var footer = '<ul class="submenu"><li class="active" id="segment-'+this.currentSegmentId+'-tm"><a tabindex="-1" href="#">Translation matches</a></li></ul><div class="cl"></div><div class="tab sub-editor matches" id="segment-'+this.currentSegmentId+'-matches"><div class="overflow"><div class="cl"></div></div></div>';
		$('.footer', segment).html(footer);
	},

	removeFooter: function(byButton) {
//		$('.footer', this.lastOpenedSegment).empty();
	},

	createStatusMenu: function(statusMenu) {
		$("ul.statusmenu").empty().hide();
		var menu = '<li class="arrow"><span class="arrow-mcolor"></span></li><li><a href="#" class="f" data-sid="segment-'+this.currentSegmentId+'" title="set draft as status">DRAFT</a></li><li><a href="#" class="d" data-sid="segment-'+this.currentSegmentId+'" title="set translated as status">TRANSLATED</a></li><li><a href="#" class="a" data-sid="segment-'+this.currentSegmentId+'" title="set approved as status">APPROVED</a></li><li><a href="#" class="r" data-sid="segment-'+this.currentSegmentId+'" title="set rejected as status">REJECTED</a></li>';
		statusMenu.html(menu).show();
	},

	removeStatusMenu: function(statusMenu) {
		statusMenu.empty().hide();
	},

    detectStartSegment: function() {
        var hash = window.location.hash.substr(1);
        console.log('hash: '+hash);
        console.log('config.last_opened_segment: '+config.last_opened_segment);
        this.startSegmentId = (hash)? hash : config.last_opened_segment;
        console.log('this.startSegmentId: '+this.startSegmentId);
    },

    getSegments: function() {
    	where = (this.startSegmentId)? 'center' : 'after';
    	var step = this.initSegNum;
 		$('#outer').addClass('loading');
   		
        $.ajax({
            url: config.basepath + '?action=getSegments'+this.appendTime(),
            data: {
                action: 'getSegments',
                jid: config.job_id,
                password: config.password,
                step : step,
                segment: this.startSegmentId,
                where: where
            },
            type: 'POST',            
            dataType: 'json',
            success: function(d){
            	where = d.data['where'];
            	$.each(d.data['files'], function() {
            		startSegmentId = this['segments'][0]['sid'];
            	})
            	console.log(startSegmentId) ;
	           	if(typeof this.startSegmentId == 'undefined') this.startSegmentId = startSegmentId;
            	if(typeof d.data['files'] != 'undefined') UI.renderSegments(d.data['files'],where,true);
   				$('#outer').removeClass('loading loadingBefore');
				UI.loadingMore = false;
            }
        });
    },

    getMoreSegments: function(where) {
    	if(this.loadingMore) {
    		return;
    	}
		this.loadingMore = true;
		
//    	if(location.hash) where = 'center';
    	var step = this.moreSegNum;
    	var seg = (where == 'after')? $('section').last() : (where == 'before')? $('section').first() : '';

    	var segId = (seg.length)? seg.attr('id').split('-')[1] : 0;
    	
   		if(where == 'before') {
   			$('#outer').addClass('loadingBefore');
   		} else if(where == 'after') {
   			$('#outer').addClass('loading');
   		}
   		
        $.ajax({
            url: config.basepath + '?action=getSegments'+this.appendTime(),
            data: {
                action: 'getSegments',
                jid: config.job_id,
                password: config.password,
                step : step,
                segment: segId,
                where: where
            },
            type: 'POST',            
            dataType: 'json',
            success: function(d){
            	where = d.data['where'];
            	if(typeof d.data['files'] != 'undefined') {
            		UI.renderSegments(d.data['files'],where,false);
            	}
   				$('#outer').removeClass('loading loadingBefore');
				UI.loadingMore = false;
            }
        });
    },

	renderSegments: function(files,where,starting) {
		var newFile = '';

        $.each(files, function() {
        	var fs = this['file_stats'];
        	var fid = fs['ID_FILE'];
//        	console.log(this);
			if(where=='center') newFile +=	'<article id="file-' + fid + '" class="loading">'+
						'	<ul class="projectbar" data-job="job-' + this.jid + '">'+
						'		<li>'+
						'			<h2>' + this.filename + '</h2>'+
						'			<form class="download" action="/" method="post">'+
						'				<input type=hidden name="action" value="downloadFile">'+
						'				<input type=hidden name="id_job" value="' + this.jid + '">'+
						'				<input type=hidden name="id_file" value="' + fid + '">'+
						'				<input type=hidden name="filename" value="' + this.filename + '">'+
						'				<input title="Download file" name="submit" type="submit" value="" class="downloadfile" id="file-' + fid + '-download">'+
						'			</form>'+
						'		</li>'+
						'		<li style="text-align:center;text-indent:-50px">'+
						'			<strong>' + this.source + '</strong> [<span class="source-lang">' + this.source_code + '</span>]&nbsp;>&nbsp;<strong>' + this.target + '</strong> [<span class="target-lang">' + this.target_code + '</span>]'+
						'		</li>'+
						'		<li class="wordcounter">'+
						'			Eq. words: <strong>' + fs['TOTAL_FORMATTED'] + '</strong>'+
						'			Draft: <strong>' + fs['DRAFT_FORMATTED'] + '</strong>'+
						'			<span id="rejected" class="hidden">Rejected: <strong>' + fs['REJECTED_FORMATTED'] + '</strong></span>'+
						'		</li>'+
						'	</ul>';

			var t = config.time_to_edit_enabled;
	        $.each(this.segments, function(index) {
				newFile +=	'<section id="segment-' + this.sid + '" class="status-' + ((!this.status)?'new':this.status.toLowerCase()) + '">'+
							
							'	<a tabindex="-1" href="#' + this.sid + '"></a>'+
							'	<span class="number">' + this.sid + '</span>'+
							
							'	<div class="body">'+
													
							'		<div class="header toggle" id="segment-' + this.sid + '-header"></div>'+
							'		<div class="text">'+
							
							'			<div class="wrap">'+
							
							'				<div class="source item" id="segment-' + this.sid + '-source" data-original="' + this.segment + '">'+
							'					<span class="original">' + this.segment + '</span>'+
							'					' + this.segment +
							'				</div> <!-- .source -->'+
							
							'				<div class="target item" id="segment-' + this.sid + '-target">'+
							
							'					<div class="status" title="' + ((!this.status)?'new':this.status.toLowerCase()) + '"></div>'+
							'					<span class="hide toggle"> '+
							'						<a href="#" class="warning normalTip exampleTip" title="Warning: as">!</a>'+
							'					</span>'+
							'					<div class="textarea-container">'+
							'						<span class="loader"></span>'+
							'						<div class="editarea invisible" contenteditable="false" id="segment-' + this.sid + '-editarea" data-sid="' + this.sid + '">' + ((!this.translation)?'':this.translation) + '</div>'+
							'						<ul class="buttons toggle provissima" id="segment-' + this.sid + '-buttons"></ul>'+
							'					</div> <!-- .textarea-container -->'+
							
							'				</div> <!-- .target -->'+
							
							'			</div> <!-- .wrap -->'+
							
							'			<div class="status-container">'+
							'				<a href=# title="' + ((!this.status)?'Change segment status':this.status.toLowerCase()+', click to change it') + '" class="status" id="segment-' + this.sid + '-changestatus"></a>'+
							'			</div> <!-- .status-container -->'+
							
							'		</div> <!-- .text -->'+
						((t)?'		<div class="timetoedit" data-raw_time_to_edit="0">':'')+
						((t)?'			<span class=edit-min>' + this.parsed_time_to_edit[1] + '</span>m:':'')+
						((t)?'			<span class=edit-sec>' + this.parsed_time_to_edit[2] + '</span>s':'')+
						((t)?'		</div>':'')+
							
							'		<div class="footer toggle"></div> <!-- .footer -->     '+         
							
							'	</div> <!-- .body -->'+
							
							'	<ul class="statusmenu"></ul>'+
							
							'</section> ';
	        })

						
			newFile +=	'	<div class="cl"></div>'+
						'</article>';
        })

   		if(where == 'before') {
   			$('article .projectbar').first().after(newFile);
   		} else if(where == 'after') {
        	$('article').first().append(newFile);
   		} else if(where == 'center') {
        	$('#outer').append(newFile);
   		}        

        if(starting) {
	    	console.log('this.startSegmentId: '+this.startSegmentId);
	    	this.scrollSegment($('#segment-' + this.startSegmentId));
        	this.init();
        }
		
	},

	segmentIsLoaded: function(segmentId) {
		if($('#segment-'+segmentId).length) {
			return true;
		} else {
			return false;
		}
	},

	appendTime: function() {
    	var t = new Date();
    	return '&time='+t.getTime();
	}

}







$(document).ready(function(){
    UI.render(true);
//    UI.init();
});


$(window).resize(function(){
});

