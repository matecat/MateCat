UI = null;

UI = {
    render: function(firstLoad) {
        this.isWebkit = $.browser.webkit;
        this.isFirefox = $.browser.mozilla;
        this.body = $('body');
        this.dmp = new diff_match_patch();
        this.firstLoad = firstLoad;
        if(firstLoad) this.startRender = true;
        this.initSegNum = 200;
        this.moreSegNum = 50;
        this.loadingMore = false;
        this.infiniteScroll = true;
        this.noMoreSegmentsAfter = false;
        this.noMoreSegmentsBefore = false;
        this.segmentIdToRestore = false;
        this.blockButtons = false;
        this.blockOpenSegment = false;
		this.downOpts = {
			offset: '130%'
		};
		this.upOpts = {
			offset: '-40%'
		};

        this.taglockEnabled = (Loader.detect('taglock'))? Loader.detect('taglock') : 0;
        this.debug = Loader.detect('debug');
	
        UI.detectStartSegment();
        UI.getSegments();
    },

    init: function() {
       
        this.initStart = new Date();
        if(this.debug) console.log('Render time: ' + (this.initStart - renderStart));
        this.numMatchesResults = 2;
        this.numSegments = $('section').length;
        this.editarea = '';
        this.byButton = false;
        this.notYetOpened = true;
        this.pendingScroll = 0;
        this.firstScroll = true;
        this.blockGetMoreSegments = true;
		setTimeout(function(){
			UI.blockGetMoreSegments = false;
		},1000);
//        this.heavy = ($('section').length > 200)? true : false;
        this.detectFirstLast();
        this.reinitMMShortcuts();
	
        // SET EVENTS
        
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
            if(UI.segmentIdToRestore) {
                UI.reloadToSegment(UI.segmentIdToRestore);
            } else {
                UI.gotoOpenSegment();
            }
        }).bind('keydown','Meta+left', function(e){ 
            e.preventDefault();
            if(UI.segmentIdToRestore) {
                UI.reloadToSegment(UI.segmentIdToRestore);
            } else {
                UI.gotoOpenSegment();
            }
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
        }).on('click','a.status',function(e) {
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
        }).on('click','.editarea',function(e,operation) {
        	if(typeof operation == 'undefined') operation = 'clicking';
            this.onclickEditarea = new Date();
            UI.notYetOpened = false;
            if((!$(this).is(UI.editarea))||(UI.editarea == '')||(!UI.body.hasClass('editing'))) {
                if(operation == 'moving') {
					if((UI.lastOperation == 'moving')&&(UI.recentMoving)) {
						UI.segmentToOpen = segment;
						UI.blockOpenSegment = true;
						
//						console.log('ctrl+down troppo vicini');
//						console.log(UI.segmentToOpen);
					} else {
						UI.blockOpenSegment = false;
					}

					UI.recentMoving = true;
					clearTimeout(UI.recentMovingTimeout);
		            UI.recentMovingTimeout = setTimeout(function(){
						UI.recentMoving = false;
		            },1000);

				} else {
					UI.blockOpenSegment = false;
				}
				UI.lastOperation = operation;
                UI.openSegment(this);
//                if(!UI.blockOpenSegment) UI.openSegment(this);
                if(operation != 'moving') UI.scrollSegment($('#segment-'+$(this).data('sid')));
            }
//            console.log('UI.lastOperation = ' + UI.lastOperation);    
            if(UI.debug) console.log('Total onclick Editarea: ' + ( (new Date()) - this.onclickEditarea));
        }).on('click','a.translated',function(e) {
            e.preventDefault();
            if(UI.blockButtons) {
                if(UI.segmentIsLoaded(UI.nextSegmentId) || UI.nextSegmentId=='' ) {
                } else {
                    if(!UI.noMoreSegmentsAfter) {
                        UI.reloadWarning();					
                    }
                }
                return;
            }
            UI.blockButtons = true;

            UI.setStatusButtons(this);
            $(".editarea", UI.nextSegment).click();
            UI.changeStatus(this,'translated',0);
            UI.changeStatusStop = new Date();
            UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;
            if(UI.segmentIsLoaded(UI.nextSegmentId) || UI.nextSegmentId=='' ) {
                if(UI.debug) console.log('next segment is loaded');
            } else {
                if(!UI.noMoreSegmentsAfter) {
                    if(UI.debug) console.log('next segment is not loaded');
                    if(typeof UI.nextSegmentId == 'undefined') return false;
                    UI.reloadWarning();	
                } else {
                	return false;
                }
            }
        }).on('click','a.approved',function(e) {          
            UI.setStatusButtons(this);
            $(".editarea", UI.nextSegment).click();
            //	        if(!UI.blockButtons) $(".editarea", UI.nextSegment).click();

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
        }).on('click','.alert .close',function(e) {          
            e.preventDefault();
            $('section .alert').remove();
        }).on('paste','.editarea',function(e) {          
            if(!UI.isWebkit) {
                $('#temptextarea').remove();
                //				alert('Paste in this area is temporarily enabled only for Chrome browser');
                UI.editarea.after('<div class="alert"><a href="#" class="close"></a><strong style="font-size:160%">Sorry!</strong><br /><p>This functionality is not supported on your browser yet.</p></div>');
                UI.editarea.after('<textarea style="display: none" id="temptextarea"></textarea>');
                $('#temptextarea').focus();
                return false;
            }
            $('#placeHolder').remove();
            var node = document.createElement("div");
            node.setAttribute('id','placeHolder');
            insertNodeAtCursor(node);
            handlepaste(this, event);

        //			UI.editarea.after('<textarea style="display: none" id="temptextarea"></textarea>');
			



        /*
			$('#temptextarea').focus();
			sanitizePaste = setTimeout(function(){
				console.log($('#temptextarea').val());
			},100);
*/
        //            e.preventDefault();
        //            console.log($(this).html());
        //            console.log($(this).text());
        //        	UI.currentEditareaContent = $(this).html();
        //            console.log(UI.currentEditareaContent);
        //			sanitizePaste = setTimeout(function(){

        //				console.log(UI.currentSegmentId);
        //				console.log(getCaretCharacterOffsetWithin(document.getElementById("segment-"+UI.currentSegmentId+"-editarea"));


        /*
				var text1 = UI.currentEditareaContent;
				var text2 = UI.editarea.html();
				UI.dmp.Diff_Timeout = 1;
				var ms_start = (new Date()).getTime();
				var d = UI.dmp.diff_main(text1, text2);
				var ms_end = (new Date()).getTime();
				var ds = UI.dmp.diff_prettyHtml(d);
				console.log('DS: '+ds);
//				var patt=/\<ins style\=\"background\:\#e6ffe6\;\"\>(.*)\<\/ins\>/;
				var patt=/<span>(.*)<\/span><ins style\=\"background\:\#e6ffe6\;\">(.*)<\/ins><span>(.*)<\/span>/;
				var result = patt.exec(ds);
//				console.log('PRIMA: '+result[1]);
				console.log('INCOLLATO: '+result[2]);
//				console.log('DOPO: '+result[3]);
//				var nuovo = result[1]+htmlEncode(result[2])+result[3];
				UI.editarea.after('<textarea style="display: none" id="temptextarea"></textarea>');
				$('#temptextarea').val(result[2]);
				var nuovo = result[1]+htmlDecode(htmlEncode($('#temptextarea').val()))+result[3];

//				var coso = text2.replace(result[1],'CENSURA');
				UI.editarea.html(nuovo);
				$('#temptextarea').remove();
//document.getElementById('outputdiv').innerHTML = ds + '<BR>Time: ' + (ms_end - ms_start) / 1000 + 's'; 
*/


        //			},100);
        }).on('keyup paste','.editarea',function(e) {          
            }).on('click','a.close',function(e) {          
            e.preventDefault();
            UI.closeSegment(UI.currentSegment,1);
        });

        UI.toSegment = true;
        UI.gotoSegment(this.startSegmentId);

        $(".end-message-box a.close").on('click',function(e) {          
            e.preventDefault();
            UI.body.removeClass('justdone');
        })

        this.checkIfFinishedFirst();

        $("section .close").bind('keydown','Shift+tab', function(e){ 
            e.preventDefault();
            $(this).parents('section').find('a.translated').focus();
        })      

        $("a.translated").bind('keydown','tab', function(e){ 
            e.preventDefault();
            $(this).parents('section').find('.close').focus();
        })      
        //		UI.arrangeFilename(this.currentArticle);
        this.initEnd = new Date();
        this.initTime = this.initEnd - this.initStart;
        if(this.debug) console.log('Init time: ' + this.initTime);

    },

    activateSegment: function() {
        this.createFooter(this.currentSegment);
        this.createButtons();
        this.createHeader();
    },

    appendTime: function() {
        var t = new Date();
        return '&time='+t.getTime();
    },

    arrangeFilename: function(article,t,tit) {
    /*
		var a = article.find(".filename h2");
		console.log(a);
		console.log(article.hasClass('loading'));
		console.log(a.text());
		console.log(a.height());		
*/
    /*
		console.log(a);
		console.log(a.text());
		console.log(a.height());		
		if(a.height() > 25) {
			var b = a[0];
			if(!tit) {b.title = b.innerHTML};
			var puntini = (tit)? '' : ' ...';
			a.text(b.innerHTML + puntini);
			var p = b.innerHTML.split(' ');//alert('ddd');
			var search = p[p.length-2];
			var ind = b.innerHTML.lastIndexOf(search);
			var before = b.innerHTML.substring(0, ind-1);
			var after = b.innerHTML.substring(ind+search.length);
			var finished = before + after;
			a.text(finished);
			this.riduciTesto(article,1,1);
		}
*/

    /*
		var a = o.find("a");
		if(a.height() > 25) {
			var b = a[0];
			if(!tit) {b.title = b.innerHTML};
			var puntini = (tit)? '' : ' ...';
			a.text(b.innerHTML + puntini);
			var p = b.innerHTML.split(' ');//alert('ddd');
			var search = p[p.length-2];
			var ind = b.innerHTML.lastIndexOf(search);
			var before = b.innerHTML.substring(0, ind-1);
			var after = b.innerHTML.substring(ind+search.length);
			var finished = before + after;
			a.text(finished);
			this.riduciTesto(o,1,1);
		}
*/
    },

    changeStatus: function(ob,status,byStatus) {
        var segment = (byStatus)? $(ob).parents("section") : $('#'+$(ob).data('segmentid'));
        $('.percentuage',segment).removeClass('visible');
        this.setContribution(segment,status,byStatus);
        this.setTranslation(segment,status);
    },

    checkIfFinished: function(closing) {
        if(((this.progress_perc!=this.done_percentage)&&(this.progress_perc == '100'))||((closing)&&(this.progress_perc == '100'))) {
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

    chooseSuggestion: function(w) {
        this.copySuggestionInEditarea(this.currentSegment,$('.editor ul[data-item='+w+'] li.b .translation').text(),$('.editor .editarea'),$('.editor ul[data-item='+w+'] ul.graysmall-details .percent').text());
        this.editarea.focus().effect("highlight", {}, 1000);		
		this.placeCaretAtEnd(document.getElementById($(editarea).attr('id')));

    },

    closeSegment: function(segment,byButton) {
        if((typeof segment =='undefined')||(typeof UI.toSegment !='undefined')) {
            this.toSegment = undefined;
            return true;
        }

        var closeStart = new Date();
        this.deActivateSegment(byButton);

        this.lastOpenedEditarea.attr('contenteditable','false');
        this.body.removeClass('editing');
        $(segment).removeClass("editor");
        //		$('#downloadProject').focus();
        if(!this.opening) {
            this.checkIfFinished(1);
        }
    },

    copySource: function() {
        //var source_val = $.trim($(".source",this.currentSegment).data('original'));
           
        var source_val = $.trim($(".source",this.currentSegment).text());
        // Test
        //source_val = source_val.replace(/&quot;/g,'"');
        
        // Attention I use .text to obtain a entity conversion, by I ignore the quote conversion done before adding to the data-original
        // I hope it still works.
    
        $(".editarea",this.currentSegment).text(source_val).keyup().focus();
        $(".editarea",this.currentSegment).effect("highlight", {}, 1000);
    },

    copySuggestionInEditarea: function(segment,translation,editarea,match,decode) {
        if (typeof(decode)=="undefined"){
            decode=false;
        }
        percentageClass = this.getPercentuageClass(match);

        if($.trim(translation) != '') {
           
            //ANTONIO 20121205    	editarea.text(translation).addClass('fromSuggestion');

            if (decode){
                translation=htmlDecode(translation);
            }
            editarea.text(translation).addClass('fromSuggestion');
            $('.percentuage',segment).text(match).removeClass('per-orange per-green per-blue per-yellow').addClass(percentageClass).addClass('visible');
        }
//		this.placeCaretAtEnd(document.getElementById($(editarea).attr('id')));
    },

    copyToNextIfSame: function(nextSegment) {
        if($('.source',this.currentSegment).data('original') == $('.source',nextSegment).data('original')) {
            if($('.editarea',nextSegment).hasClass('fromSuggestion')) {
                $('.editarea',nextSegment).text(this.editarea.text());
            }
        }
    },

    createButtons: function() {
        var disabled = (this.currentSegment.hasClass('loaded'))? '' : ' disabled="disabled"';
        var buttons = '<li><a id="segment-'+this.currentSegmentId+'-copysource" href="#" class="btn copysource" data-segmentid="segment-'+this.currentSegmentId+'" title="Copy source to target"></a><p>CTRL+RIGHT</p></li><li style="margin-right:-20px"><a id="segment-'+this.currentSegmentId+'-button-translated" data-segmentid="segment-'+this.currentSegmentId+'" href="#" class="translated"'+disabled+' >TRANSLATED</a><p>CTRL+ENTER</p></li>';
        $('#segment-'+this.currentSegmentId+'-buttons').append(buttons);
    },

    createFooter: function(segment) {
        if($('.footer', segment).text() != '') return false;
        var footer = '<ul class="submenu"><li class="active" id="segment-'+this.currentSegmentId+'-tm"><a tabindex="-1" href="#">Translation matches</a></li></ul><div class="tab sub-editor matches" id="segment-'+this.currentSegmentId+'-matches"><div class="overflow"></div></div>';
        $('.footer', segment).html(footer);
    },

    createHeader: function() {
        if ($('h2.percentuage', this.currentSegment).length){
            return;
        }
        var header = '<h2 title="" class="percentuage"><span></span></h2><a href="#" id="segment-'+this.currentSegmentId+'-close" class="close" title="Close this segment"></a>';
        $('#'+this.currentSegment.attr('id')+'-header').html(header);
    },

    createStatusMenu: function(statusMenu) {
        $("ul.statusmenu").empty().hide();
        var menu = '<li class="arrow"><span class="arrow-mcolor"></span></li><li><a href="#" class="f" data-sid="segment-'+this.currentSegmentId+'" title="set draft as status">DRAFT</a></li><li><a href="#" class="d" data-sid="segment-'+this.currentSegmentId+'" title="set translated as status">TRANSLATED</a></li><li><a href="#" class="a" data-sid="segment-'+this.currentSegmentId+'" title="set approved as status">APPROVED</a></li><li><a href="#" class="r" data-sid="segment-'+this.currentSegmentId+'" title="set rejected as status">REJECTED</a></li>';
        statusMenu.html(menu).show();
    },

    deActivateSegment: function(byButton) {
        this.removeButtons(byButton);
        this.removeHeader(byButton);
        this.removeFooter(byButton);
    },

    detectRefSegId: function(where) {
        var step = this.moreSegNum;
        var seg = (where == 'after')? $('section').last() : (where == 'before')? $('section').first() : '';
        var segId = (seg.length)? seg.attr('id').split('-')[1] : 0;
        return segId;
    },
    
    detectStartSegment: function() {
        var hash = window.location.hash.substr(1);
        this.startSegmentId = (hash)? hash : config.last_opened_segment;
    },

    getContribution: function(segment,next) {
// prova per anticipare l'indent
/*
        var isActiveSegment = $(segment).hasClass('editor');
        var editarea = $('.editarea', segment);
        var editareaLength = editarea.text().length;
        if(isActiveSegment) {
            editarea.removeClass("indent");
        } else {
            if (editareaLength==0) editarea.addClass("indent");
        }
*/


        var n = (next)? $('#segment-'+this.nextSegmentId) : $(segment);
        if($(n).hasClass('loaded')) {
            if(next) {
                this.nextIsLoaded = true;
            } else {
                this.currentIsLoaded = true;
            }
            if(this.currentIsLoaded ) this.blockButtons = false;
            if(this.currentSegmentId == this.nextSegmentId) this.blockButtons = false;
            return false;
        }
        if((!n.length)&&(next)) {
            return false;
        }
        var id = n.attr('id');
        var id_segment = id.split('-')[1];
        
        var txt = $('.source',n).text();
        txt=view2rawxliff(txt);
        // Attention: As for copysource, what is the correct file format in attributes? I am assuming html encoded and "=>&quot;
        //txt = txt.replace(/&quot;/g,'"');
        
        if(!next) {
            $(".loader",n).addClass('loader_on')
        }
     
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
                UI.renderContributions(d,this);
                UI.blockButtons = false;

                if (d.data.matches==0){
                    $(".sbm > .matches", this).hide();
                } else {
                    $('.submenu li.matches a span', this).text('('+d.data.matches.length+')');
                }
            }
        });
    },

    getMoreSegments: function(where) {
        if((where == 'after')&&(this.noMoreSegmentsAfter)) return;
        if((where == 'before')&&(this.noMoreSegmentsBefore)) return;   		
        if(this.loadingMore) {
            return;
        }
        this.loadingMore = true;
		
        var segId = this.detectRefSegId(where);
        
        if(where == 'before') {
	        $("section").each(function(){
	            if($(this).offset().top > $(window).scrollTop()) {
	            	UI.segMoving = $(this).attr('id').split('-')[1];
	            	return false;
	            }
	        })
        }
  	
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
                step : 50,
                segment: segId,
                where: where
            },
            type: 'POST',            
            dataType: 'json',
            success: function(d){
                where = d.data['where'];
                if(typeof d.data['files'] != 'undefined') {
                    var numsegToAdd = 0;
                    $.each(d.data['files'], function() {
                        numsegToAdd = numsegToAdd + this.segments.length;
                    });
                    UI.renderSegments(d.data['files'],where,false);
                   
                    // if getting segments before, UI points to the segment triggering the event 
                    if((where == 'before')&&(numsegToAdd)) {
                        UI.scrollSegment($('#segment-'+UI.segMoving));
                    }
            		
                    // check if there is a segment to restore (to open) in the newly loaded segments
                    if(UI.segmentIdToRestore) {
                        if($('#segment-'+UI.segmentIdToRestore).length) {
                            $('#segment-'+UI.segmentIdToRestore+' .editarea').trigger('click');
                            UI.body.removeClass('virtualEditing');
                            UI.segmentIdToRestore = false;
                        }
                    }
                }
                if(where == 'after') {
                }
                if(d.data['files'].length == 0) {
                    if(where == 'after') UI.noMoreSegmentsAfter = true;
                    if(where == 'before') UI.noMoreSegmentsBefore = true;
                }
                $('#outer').removeClass('loading loadingBefore');
                UI.loadingMore = false;
				UI.setWaypoints();

            }
        });
    },

    getNextSegment: function(segment,status) {
        var seg = this.currentSegment;
        var rules = (status =='untranslated')? 'section.status-draft, section.status-rejected, section.status-new' : 'section.status-'+status;
        var n = $(seg).nextAll(rules).first();
        if(!n.length) {
            n = $(seg).parents('article').next().find(rules).first();
        }
        if(n.length) {
            this.nextSegmentId = $(n).attr('id').split('-')[1];
        } else {
            this.nextSegmentId = 0;
        }
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
                if(typeof this.startSegmentId == 'undefined') this.startSegmentId = startSegmentId;
                if(typeof d.data['files'] != 'undefined') UI.renderSegments(d.data['files'],where,true);
                $('#outer').removeClass('loading loadingBefore');
                UI.loadingMore = false;
                UI.setWaypoints();
            }
        });
    },

    detectAdjacentSegment: function(segment,direction,times) { // currently unused
		if(!times) times = 1;
		if(direction == 'down') {
        	var adjacent = segment.next();
			if(!adjacent.is('section')) adjacent = this.currentArticle.next().find('section:first');
		} else {
	        var adjacent = segment.prev();
			if(!adjacent.is('section')) adjacent = $('.editor').parents('article').prev().find('section:last');
		}

        if(adjacent.length) {
            if(times == 1) {
            	return adjacent;
            } else {
            	this.detectAdjacentSegment(adjacent,direction,times-1);
            }
        } else {
        }
    },

    detectFirstLast: function() {
        var s = $('section');
        this.firstSegment = s.first();
        this.lastSegment = s.last();
    },

    gotoNextSegment: function() {
        var next = $('.editor').next();
        if(next.is('section')) {
        	this.scrollSegment(next);
            $('.editarea',next).trigger("click", "moving");
        } else {
            next = this.currentArticle.next().find('section:first');
            if(next.length) {
        		this.scrollSegment(next);
                $('.editarea',next).trigger("click", "moving");
            }
        };
//        this.scrollSegment(next);
    },

    gotoOpenSegment: function() {
        this.scrollSegment(this.currentSegment);
    },	

    gotoPreviousSegment: function() {
        var prev = $('.editor').prev();
        if(prev.is('section')) {
            $('.editarea',prev).click();
        } else {
            prev = $('.editor').parents('article').prev().find('section:last');
            if(prev.length) {
                $('.editarea',prev).click();
            } else {
                this.topReached();
            }
        };
        this.scrollSegment(prev);
    },

    gotoSegment: function(id){
        var el=$("#segment-"+id+"-target").find(".editarea");
        $(el).click();
    },

    justSelecting: function() {
        if(window.getSelection().isCollapsed) return false;
        var selContainer = $(window.getSelection().getRangeAt(0).startContainer.parentNode);
        return ((selContainer.hasClass('editarea'))&&(!selContainer.is(UI.editarea)));
    },
	
    millisecondsToTime: function(milli) {
        var milliseconds = milli % 1000;
        var seconds = Math.round((milli / 1000) % 60);
        var minutes = Math.floor((milli / (60 * 1000)) % 60);
        return [minutes, seconds];
    },

    openSegment: function(editarea) {
        this.openSegmentStart = new Date();
        if(!this.byButton) {
            if(this.justSelecting()) return;
        }
        this.byButton = false;
        this.editarea = $(editarea);

        // current and last opened object reference caching
        this.lastOpenedSegment = this.currentSegment;
        this.lastOpenedEditarea = $('.editarea',this.currentSegment);

        this.currentSegmentId = this.lastOpenedSegmentId = this.editarea.data('sid');
		
        this.currentSegment = segment = $('#segment-'+this.currentSegmentId);
        this.currentArticle = segment.parent();
        this.activateSegment();
		
        this.getNextSegment(this.currentSegment,'untranslated');
        this.setCurrentSegment(segment);

        this.focusEditarea = setTimeout(function(){
            UI.editarea.focus();
            clearTimeout(UI.focusEditarea);
        },100);
        this.currentIsLoaded = false;
        this.nextIsLoaded = false;
        this.getContribution(segment,0);
        this.opening = true;
        if(!(this.currentSegment.is(this.lastOpenedSegment))) this.closeSegment(this.lastOpenedSegment,0);
        this.opening = false;
        this.body.addClass('editing');

        segment.addClass("editor");
        this.editarea.attr('contenteditable','true');
        this.editStart = new Date();
        $(editarea).removeClass("indent");
        this.getContribution(segment,1);
        if(this.debug) console.log('close/open time: ' + ( (new Date()) - this.openSegmentStart));
    },

    placeCaretAtEnd: function(el) {
	    el.focus();
	    if (typeof window.getSelection != "undefined"
	            && typeof document.createRange != "undefined") {
	        var range = document.createRange();
	        range.selectNodeContents(el);
	        range.collapse(false);
	        var sel = window.getSelection();
	        sel.removeAllRanges();
	        sel.addRange(range);
	    } else if (typeof document.body.createTextRange != "undefined") {
	        var textRange = document.body.createTextRange();
	        textRange.moveToElementText(el);
	        textRange.collapse(false);
	        textRange.select();
	    }
	},

    reinitMMShortcuts: function(a) {
		$('body').unbind('keydown.alt1').unbind('keydown.alt2').unbind('keydown.alt3').unbind('keydown.alt4').unbind('keydown.alt5');
        $("body, .editarea").bind('keydown.alt1','Alt+1', function(e){ 
            e.preventDefault();
            if(e.which != 97) {
                UI.chooseSuggestion('1');
            }
        }).bind('keydown.alt2','Alt+2', function(e){ 
            e.preventDefault();
            if(e.which != 98) {
                UI.chooseSuggestion('2');
            }
        }).bind('keydown.alt3','Alt+3', function(e){ 
            e.preventDefault();
            if(e.which != 99) {
                UI.chooseSuggestion('3');
            }            
        }).bind('keydown.alt4','Alt+4', function(e){ 
            e.preventDefault();
            if(e.which != 100) {
                UI.chooseSuggestion('4');
            }            
        }).bind('keydown.alt5','Alt+5', function(e){ 
            e.preventDefault();
            if(e.which != 101) {
                UI.chooseSuggestion('5');
            }            
        })
    },

    reloadToSegment: function(segmentId) {
        this.infiniteScroll = false;
        config.last_opened_segment = segmentId;
        window.location.hash = segmentId;
        $('#outer').empty();
        this.render(false);
    },
	
    reloadWarning: function() {
        var m = confirm('The next untranslated segment is outside the current view.');
        if(m) {
            this.infiniteScroll = false;
            config.last_opened_segment = this.nextSegmentId;
            window.location.hash = this.nextSegmentId;
            $('#outer').empty();
            this.render(false);
        }            	
    },

    removeButtons: function(byButton) {
        var segment = (byButton)? this.currentSegment : this.lastOpenedSegment;
        $('#'+segment.attr('id')+'-buttons').empty();
    },

    removeFooter: function(byButton) {
    },

    removeHeader: function(byButton) {
        var segment = (byButton)? this.currentSegment : this.lastOpenedSegment;
        $('#'+segment.attr('id')+'-header').empty();
    },

    removeStatusMenu: function(statusMenu) {
        statusMenu.empty().hide();
    },

    renderContributions: function(d,segment) {
        var isActiveSegment = $(segment).hasClass('editor');
        var editarea = $('.editarea', segment);
        if(d.data.matches.length) {
            var editareaLength = editarea.text().length;
            if(isActiveSegment) {
                editarea.removeClass("indent");
            } else {
                if (editareaLength==0) editarea.addClass("indent");
            }
//			console.log('indent: ' + editarea.hasClass('indent'));
            var translation = d.data.matches[0].translation;
            var perc_t=$(".percentuage",segment).attr("title");
            
            $(".percentuage",segment).attr("title",''+perc_t + "Created by " + d.data.matches[0].created_by);
            var match = d.data.matches[0].match;
 
            if (editareaLength==0){
                UI.copySuggestionInEditarea(segment,translation,editarea,match,true);
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
                // Attention Bug: We are mixing the view mode and the raw data mode.
                // before doing a enanched view you will need to add a data-original tag
                $('.sub-editor .overflow',segment).append('<ul class="graysmall" data-item="'+(index+1)+'" data-id="'+this.id+'"><li >'+((disabled)?'':' <a id="'+segment_id+'-tm-'+this.id+'-delete" href="#" class="trash" title="delete this row"></a>')+'<span id="'+segment_id+'-tm-'+this.id+'-source" class="suggestion_source">'+this.segment+'</span></li><li class="b"><span class="graysmall-message">ALT+'+(index+1)+'</span><span id="'+segment_id+'-tm-'+this.id+'-translation" class="translation">'+this.translation+'</span></li><ul class="graysmall-details"><li class="percent ' + cl_suggestion + '">'+(this.match)+'</li><li>'+this['last_update_date']+'</li><li class="graydesc">Source: <span class="bold">'+cb+'</span></li></ul></ul>');
            });
            UI.setDeleteSuggestion(segment);	    

            $('.translated',segment).removeAttr('disabled');
            $('.draft',segment).removeAttr('disabled');
        } else {
            if(UI.debug) console.log('no matches');
            $(segment).removeClass('loaded').addClass('loaded');
            $('.sub-editor .overflow',segment).append('<ul class="graysmall message"><li>Sorry. Can\'t help you this time. Check the language pair if you feel this is weird.</li></ul>');  				
        }
    },

    renderSegments: function(files,where,starting) {
        $.each(files, function() {
            var newFile = '';
            var fs = this['file_stats'];
            var fid = fs['ID_FILE'];
            var articleToAdd = ((where=='center')||(!$('#file-'+fid).length))? true : false;

            if(articleToAdd) {
                filenametoshow=truncate_filename(this.filename,40);
                newFile +=	'<article id="file-' + fid + '" class="loading">'+
                '	<ul class="projectbar" data-job="job-' + this.jid + '">'+
                '		<li class="filename">'+
                '			<form class="download" action="/" method="post">'+
                '				<input type=hidden name="action" value="downloadFile">'+
                '				<input type=hidden name="id_job" value="' + this.jid + '">'+
                '				<input type=hidden name="id_file" value="' + fid + '">'+
                '				<input type=hidden name="filename" value="' + this.filename + '">'+
                '				<input type=hidden name="password" value="' + config.password + '">'+
                '				<input title="Download file" name="submit" type="submit" value="" class="downloadfile" id="file-' + fid + '-download">'+
                '			</form>'+
                '			<h2 title="' + this.filename + '">' + filenametoshow + '</div>'+
                '		</li>'+
                '		<li style="text-align:center;text-indent:-20px">'+
                '			<strong>' + this.source + '</strong> [<span class="source-lang">' + this.source_code + '</span>]&nbsp;>&nbsp;<strong>' + this.target + '</strong> [<span class="target-lang">' + this.target_code + '</span>]'+
                '		</li>'+
                '		<li class="wordcounter">'+
                '			Eq. words: <strong>' + fs['TOTAL_FORMATTED'] + '</strong>'+
                '			Draft: <strong>' + fs['DRAFT_FORMATTED'] + '</strong>'+
                '			<span id="rejected" class="hidden">Rejected: <strong>' + fs['REJECTED_FORMATTED'] + '</strong></span>'+
                '		</li>'+
                '	</ul>';
            }

            var t = config.time_to_edit_enabled;
            $.each(this.segments, function(index) {
                var escapedSegment = htmlEncode(this.segment.replace(/\"/g,"&quot;"));
                newFile += '<section id="segment-' + this.sid + '" class="status-' + ((!this.status)?'new':this.status.toLowerCase()) + '">'+
							
                '	<a tabindex="-1" href="#' + this.sid + '"></a>'+
                '	<span class="number">' + this.sid + '</span>'+
							
                '	<div class="body">'+
													
                '		<div class="header toggle" id="segment-' + this.sid + '-header">' +
                '                 <h2 title="" class="percentuage"><span></span></h2><a href="#" id="segment-'+this.sid+'-close" class="close" title="Close this segment"></a>'+
                '               </div>'+
                '		<div class="text">'+
							
                '			<div class="wrap">'+
							
                '				<div class="source item" id="segment-' + this.sid + '-source" data-original="' + escapedSegment + '">'+ this.segment +'</div> <!-- .source -->'+
							
                '				<div class="target item" id="segment-' + this.sid + '-target">'+
							
                '					<span class="hide toggle"> '+
                '						<a href="#" class="warning normalTip exampleTip" title="Warning: as">!</a>'+
                '					</span>'+
                '					<div class="textarea-container">'+
                '						<span class="loader"></span>'+
                '						<div class="editarea invisible" contenteditable="false" id="segment-' + this.sid + '-editarea" data-sid="' + this.sid + '">' + ((!this.translation)?'':this.translation) + '</div>'+
                '					</div> <!-- .textarea-container -->'+
                '				</div> <!-- .target -->'+
                '			</div> <!-- .wrap -->'+
							
                '						<ul class="buttons toggle provissima" id="segment-' + this.sid + '-buttons"></ul>'+
							
							
                '			<div class="status-container">'+
                '				<a href=# title="' + ((!this.status)?'Change segment status':this.status.toLowerCase()+', click to change it') + '" class="status" id="segment-' + this.sid + '-changestatus"></a>'+
                '			</div> <!-- .status-container -->'+
							
                '		</div> <!-- .text -->'+
                '		<div class="timetoedit" data-raw_time_to_edit="' + this.time_to_edit + '">'+
                ((t)?'			<span class=edit-min>' + this.parsed_time_to_edit[1] + '</span>m:':'')+
                ((t)?'			<span class=edit-sec>' + this.parsed_time_to_edit[2] + '</span>s':'')+
                '		</div>'+
							
                '		<div class="footer toggle"></div> <!-- .footer -->     '+         
							
                '	</div> <!-- .body -->'+
							
                '	<ul class="statusmenu"></ul>'+
							
                '</section> ';
            })

            if(articleToAdd) {
                newFile +=	'</article>';
            }

            if(articleToAdd) {
                if(where == 'before') {
                    if(typeof lastArticleAdded != 'undefined') {
                        $('#file-'+fid).after(newFile);
                    } else {
                        $('article').first().before(newFile);
                    }
                    lastArticleAdded = fid;
                } else if(where == 'after') {
                    $('article').last().after(newFile);
                } else if(where == 'center') {
                    $('#outer').append(newFile);
                }     
            } else {
                if(where == 'before') {
                    $('#file-'+fid).prepend(newFile);
                } else if(where == 'after') {
                    $('#file-'+fid).append(newFile);
                }   				
            }
            
            if(articleToAdd) {
            // fit_text_to_container($('.filenameshow',$('#file-'+fid)));
            }
        })

        if(starting) {
            this.init();
        }
    },

    scrollSegment: function(segment) {
//        console.log('scrollSegment');
        var spread = 23;
        var current = this.currentSegment;
        var previousSegment = $(segment).prev('section');

        if(!previousSegment.length) {
            previousSegment = $(segment);
            spread = 103;
        }
        var destination = "#"+previousSegment.attr('id');
        var destinationTop = $(destination).offset().top;
        if(this.firstScroll) {
        	destinationTop = destinationTop + 100;
        	this.firstScroll = false;
        }

        if($(current).length){ // if there is an open segment
            if($(segment).offset().top > $(current).offset().top) { // if segment to open is below the current segment
                if(!current.is($(segment).prev())) { // if segment to open is not the immediate follower of the current segment
                    var diff = (this.firstLoad)? ($(current).height()-200+120) : 20;
                    destinationTop = destinationTop - diff;
                } else { // if segment to open is the immediate follower of the current segment
                    destinationTop = destinationTop - spread;
                }
            } else { // if segment to open is above the current segment
                destinationTop = destinationTop - spread;
            }		
        } else { // if no segment is opened
            destinationTop = destinationTop - spread;
        }	

        $("html,body").animate({
            scrollTop: destinationTop-20
        }, 500 );
    },	        

    segmentIsLoaded: function(segmentId) {
        if($('#segment-'+segmentId).length) {
            return true;
        } else {
            return false;
        }
    },
	
    setContribution: function(segment,status,byStatus) {
        if((status=='draft')||(status=='rejected')) return false;
        var source = $('.source',segment).text();
        source = view2rawxliff(source);
        // Attention: to be modified when we will be able to lock tags.
        var target = $('.editarea',segment).text();
        if((target == '')&&(byStatus)) {
            alert('Cannot change status on an empty segment. Add a translation first!');
        }
        if(target == '') {
            return false;
        }
        target = view2rawxliff(target);
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

    setCurrentSegment: function(segment,closed) {
        var id_segment = this.currentSegmentId;
        if(closed) {
            id_segment = 0;
            UI.currentSegment = undefined;
        } else {
            setTimeout(function(){
                var hash_value = window.location.hash;
                window.location.hash = UI.currentSegmentId
            },300);
        }
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
            }
        });
    },

    setDeleteSuggestion: function(segment) {
        $('.sub-editor .overflow a.trash',segment).click(function(e) {
            e.preventDefault();
            var ul = $(this).parents('.graysmall');

            source = $('.suggestion_source',ul).text();
            source=view2rawxliff(source);
            target = $('.translation',ul).text();
            target=view2rawxliff(target);
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
                    if(UI.debug) console.log('match deleted');

                    $(".editor .matches .graysmall").each(function(index){
                        $(this).find('.graysmall-message').text('ALT+'+(index+1));
                        $(this).attr('data-item',index+1);
                        UI.reinitMMShortcuts();
                    })

                }
            });
        });
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

    setStatus: function(segment,status) {
        segment.removeClass("status-draft status-translated status-approved status-rejected status-new").addClass("status-"+status);
    },
    
    setStatusButtons: function(button) {
        this.editStop = new Date();
        var segment = this.currentSegment;
        tte = $('.timetoedit',segment);
        this.editTime = this.editStop - this.editStart;
        this.totalTime = this.editTime + tte.data('raw_time_to_edit');
        var editedTime = this.millisecondsToTime(this.totalTime);
        if(config.time_to_edit_enabled) {
            var editSec = $('.timetoedit .edit-sec',segment);
            var editMin = $('.timetoedit .edit-min',segment);
            editMin.text(this.zerofill(editedTime[0],2));
            editSec.text(this.zerofill(editedTime[1],2));
        }
        tte.data('raw_time_to_edit', this.totalTime);
        var statusSwitcher = $(".status",segment);
        statusSwitcher.removeClass("col-approved col-rejected col-done col-draft");
        var statusToGo = ($(button).hasClass('translated'))? 'untranslated' : '';
        var nextSegment = $('#segment-'+this.nextSegmentId);
        this.nextSegment = nextSegment;
        if(!nextSegment.length) {
            $(".editor:visible").find(".close").click();
            $('.downloadtr-button').focus();
            return false;
        };
        this.buttonClickStop = new Date();
        this.copyToNextIfSame(nextSegment);
        this.byButton = true;
    },

    setTranslation: function(segment,status) {
        var info=$(segment).attr('id').split('-');
        var id_segment = info[1];
        var file = $(segment).parents('article');
        var status = status;
        // Attention, to be modified when we will lock tags
        var translation = $('.editarea',segment).text();

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

    setWaypoints: function() {
        this.firstSegment.waypoint('remove');
        this.lastSegment.waypoint('remove');
        this.detectFirstLast();
		this.lastSegment.waypoint(function(event, direction) {
			if (direction === 'down') {
				UI.lastSegment.waypoint('remove');
	            if(UI.infiniteScroll) {
	            	if(!UI.blockGetMoreSegments) {
	            		UI.blockGetMoreSegments = true;
	            		UI.getMoreSegments('after');
						setTimeout(function(){
							UI.blockGetMoreSegments = false;
						},1000);
	            	}
	            }
			}
		}, UI.downOpts);

		this.firstSegment.waypoint(function(event, direction) {
			if (direction === 'up') {
				UI.firstSegment.waypoint('remove');
				UI.getMoreSegments('before');
			}
		}, UI.upOpts);
    },

    tagLock: function() {
    //		see http://jsfiddle.net/gxkEz/
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

    topReached: function() {
        var jumpto = $(this.currentSegment).offset().top;
        $("html,body").animate({
            scrollTop: 0
        }, 200 ).animate({
            scrollTop: jumpto-50
        }, 200 );
    },

    zerofill: function(i,l,s) {
        var o = i.toString();
        if (!s) {
            s = '0';
        }
        while (o.length < l) {
            o = s + o;
        }
        return o;
    }
}

function htmlEncode(value){
    if (value) {
        return jQuery('<div />').text(value).html();
    } else {
        return '';
    }
}
 
function htmlDecode(value) {
    if (value) {
        return $('<div />').html(value).text();
    } else {
        return '';
    }
}

function utf8_to_b64(str) { // currently unused
    return window.btoa(unescape(encodeURIComponent(str)));
}
 
function b64_to_utf8(str) { // currently unused
    return decodeURIComponent(escape(window.atob(str)));
}


// START Get clipboard data at paste event (SEE http://stackoverflow.com/a/6804718)
function handlepaste (elem, e) {
    var savedcontent = elem.innerHTML;
    /*
    var sel, range;

    if (window.getSelection && document.createRange) {                    
        range = document.createRange();
        sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    } else if (document.body.createTextRange) {
        range = document.body.createTextRange();
        range.moveToElementText(placeholder);
        range.select();
    }
    console.log('range:');
    console.log(range);
*/



    if (e && e.clipboardData && e.clipboardData.getData) {// Webkit - get data from clipboard, put into editdiv, cleanup, then cancel event
        if (/text\/html/.test(e.clipboardData.types)) {
            elem.innerHTML = e.clipboardData.getData('text/plain');
        }
        else if (/text\/plain/.test(e.clipboardData.types)) {
            elem.innerHTML = e.clipboardData.getData('text/plain');
        }
        else {
            elem.innerHTML = "";
        }
        waitforpastedata(elem, savedcontent);
        if (e.preventDefault) {
            e.stopPropagation();
            e.preventDefault();
        }
        return false;
    }
    else {// Everything else - empty editdiv and allow browser to paste content into it, then cleanup
        elem.innerHTML = "";
        waitforpastedata(elem, savedcontent);
        return true;
    }
}

function waitforpastedata (elem, savedcontent) {
    if (elem.childNodes && elem.childNodes.length > 0) {
        processpaste(elem, savedcontent);
    }
    else {
        that = {
            e: elem,
            s: savedcontent
        }
        that.callself = function () {
            waitforpastedata(that.e, that.s)
        }
        setTimeout(that.callself,20);
    }
}

function processpaste (elem, savedcontent) {
    pasteddata = elem.innerHTML;
    //^^Alternatively loop through dom (elem.childNodes or elem.getElementsByTagName) here
    //	console.log(elem.id);
    elem.innerHTML = savedcontent;
    //	elem.appendChild(savedcontent);
    // Do whatever with gathered data;
    $('#placeHolder').before(pasteddata);
    focusOnPlaceholder();
    $('#placeHolder').remove();
}
// END Get clipboard data at paste event

function focusOnPlaceholder() {
    var placeholder = document.getElementById('placeHolder');
    if( !placeholder ) return;
    var sel, range;

    if (window.getSelection && document.createRange) {                    
        range = document.createRange();
        range.selectNodeContents(placeholder);
        range.collapse(true);
        sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    } else if (document.body.createTextRange) {
        range = document.body.createTextRange();
        range.moveToElementText(placeholder);
        range.select();
    }
}

function truncate_filename(n, len) {
    var ext = n.substring(n.lastIndexOf(".") + 1, n.length).toLowerCase();
    var filename = n.replace('.'+ext,'');
    if(filename.length <= len) {
        return n;
    }
    filename = filename.substr(0, len) + (n.length > len ? '[...]' : '');
    return filename + '.' + ext;
}

function fit_text_to_container(container,child){
    if (typeof(child)!='undefined'){
        a=$(child,container).text();
    }else{
        a=container.text();
    }
    w=container.width(); //forse non serve

    first_half=a[0];
    last_index=a.length-1;
    last_half=a[last_index];

    if (typeof(child)!='undefined'){
        $(child,container).text(first_half+"..."+last_half);
    }else{
        container.text(first_half+"..."+last_half);
    }
    
    h=container.height();
    hh=$(child,container).height();
    
    for (var i=1 ; i< a.length; i=i+1){
        old_first_half=first_half;
        old_last_half=last_half;

        first_half=first_half+ a[i];
        last_half=a[last_index-i] + last_half;

        if (typeof(child)!='undefined'){
            $(child,container).text(first_half+"..."+last_half);
        }else{
            container.text(first_half+"..."+last_half);
        }
        h2=container.height();
        
        if (h2>h){
            if (typeof(child)!='undefined'){
                $(child,container).text(old_first_half+"..."+last_half);
            }else{
                container.text(old_first_half+"..."+last_half);
            }
            h2=$(container).height();
            
            if (h2>h){ 
                if (typeof(child)!='undefined'){
                    $(child,container).text(old_first_half+"..."+old_last_half);
                }else{
                    container.text(old_first_half+"..."+old_last_half);
                }
            }
            break;
        }
        if ($(child,container).text()==a){
            break;
        }
    }
}

function insertNodeAtCursor(node) {
    var range, html;
    if (window.getSelection && window.getSelection().getRangeAt) {
        range = window.getSelection().getRangeAt(0);
        range.insertNode(node);
    } else if (document.selection && document.selection.createRange) {
        range = document.selection.createRange();
        html = (node.nodeType == 3) ? node.data : node.outerHTML;
        range.pasteHTML(html);
    }
}

$(document).ready(function(){
    fit_text_to_container($("#pname"));
    UI.render(true);
});


$(window).resize(function(){
    });

/* FORMATTING FUNCTION  TO TEST */

var LTPLACEHOLDER = "##LESSTHAN##";
var GTPLACEHOLDER= "##GREATERTHAN##";
var re_lt = new RegExp(LTPLACEHOLDER,"g"); 
var re_gt = new RegExp(GTPLACEHOLDER,"g"); 
// test jsfiddle http://jsfiddle.net/YgKDu/

function placehold_xliff_tags(segment) {
    segment = segment.replace(/<(g\s*.*?)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER);
    segment = segment.replace(/<(\/g)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER);    
    segment = segment.replace(/<(x.*?\/?)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER);
    segment = segment.replace(/<(bx.*?\/?])>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    segment = segment.replace(/<(ex.*?\/?)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    segment = segment.replace(/<(bpt\s*.*?)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    segment = segment.replace(/<(\/bpt)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    segment = segment.replace(/<(ept\s*.*?)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    segment = segment.replace(/<(\/ept)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    segment = segment.replace(/<(ph\s*.*?)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    segment = segment.replace(/<(\/ph)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    segment = segment.replace(/<(it\s*.*?)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    segment = segment.replace(/<(\/ph)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    segment = segment.replace(/<(it\s*.*?)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    segment = segment.replace(/<(\/it)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    segment = segment.replace(/<(mrk\s*.*?)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    segment = segment.replace(/<(\/mrk)>/gi, LTPLACEHOLDER+"$1"+GTPLACEHOLDER,segment);
    return segment;
}

function restore_xliff_tags(segment){
    segment = segment.replace(re_lt,"<");
    segment = segment.replace(re_gt,">");
    return segment;
}

function restore_xliff_tags_for_view(segment){
    segment = segment.replace(re_lt,"&lt;");
    segment = segment.replace(re_gt,"&gt;");
    return segment;
}

function view2rawxliff(segment){
    // input : <g id="43">bang & olufsen < 3 </g> <x id="33"/>; --> valore della funzione .text() in cat.js su source, target, source suggestion,target suggestion
    // output : <g id="43"> bang &amp; olufsen are &gt; 555 </g> <x/>

    // caso controverso <g id="4" x="&lt; dfsd &gt;"> 
    //segment=htmlDecode(segment);
    segment = placehold_xliff_tags (segment);
    segment = htmlEncode(segment);
    segment = restore_xliff_tags(segment);	

    return segment;
}


function rawxliff2view(segment){ // currently unused
    // input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
    // output : &lt;g id="43"&gt;bang & < 3 olufsen &lt;/g&gt;;  &lt;x id="33"/&gt;
    segment = placehold_xliff_tags (segment);
    segment = htmlDecode(segment);	
    segment = segment.replace(/<(.*?)>/i, "&lt;$1&gt;");
    segment = restore_xliff_tags_for_view(segment);		// li rendering avviene via concat o via funzione html()
    return segment;
}

function rawxliff2rawview(segment){ // currently unused
    // input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
    segment = placehold_xliff_tags (segment);
    segment = htmlDecode(segment);		
    segment = restore_xliff_tags_for_view(segment);		
    return segment;
}


