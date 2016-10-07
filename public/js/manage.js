UI = null;

UI = {

    render: function(firstLoad) {
        this.isWebkit = $.browser.webkit;
        this.isChrome = $.browser.webkit && !!window.chrome;
        this.isFirefox = $.browser.mozilla;
        this.isSafari = $.browser.webkit && !window.chrome;
        this.body = $('body');
        this.firstLoad = firstLoad;
        this.pageStep = 25;

        var page = location.pathname.split('/')[2];
        this.page = ('undefined'==typeof(page)||page == '')? 1 : parseInt(page);

        filtersStrings = (location.hash != '')? location.hash.split('#')[1].split(',') : '';
        this.filters = {};
        $.each(filtersStrings, function() {
            var s = this.split('=');
            UI.filters[s[0]] = s[1];
        });
        this.isFiltered = !$.isEmptyObject(this.filters);
        if(this.isFiltered) {

            if(typeof this.filters.pn != 'undefined') {
                $('#search-projectname').val(this.filters.pn);
            };
        	
            if(typeof this.filters.source != 'undefined') {
                $('#select-source option').each(function(){
                    if($(this).attr('value') == UI.filters.source) {
                        $('#select-source option[selected=selected]').removeAttr('selected');
                        $(this).attr('selected','selected');
                    }
                })
            };

            if(typeof this.filters.target != 'undefined') {
                $('#select-target option').each(function(){
                    if($(this).attr('value') == UI.filters.target) {
                        $('#select-target option[selected=selected]').removeAttr('selected');
                        $(this).attr('selected','selected');
                    }
                })    	
            };

            if(typeof this.filters.status != 'undefined') {
                $('#select-status option[selected=selected]').removeAttr('selected');
                $('#select-status option[value='+this.filters.status+']').attr('selected','selected');
            } else {
                $('#select-status option[selected=selected]').removeAttr('selected');
                $('#select-status option[value=active]').attr('selected','selected');        		
            };

            if(typeof this.filters.onlycompleted != 'undefined') {
                $('#only-completed').attr('checked','checked');
            };

            this.body.addClass('filterOpen');

        } else {
        	this.body.removeClass('filterOpen filterActive');
	        UI.emptySearchbox();
        }
		var status = (typeof this.filters.status != 'undefined')? this.filters.status : 'active';
		this.body.attr('data-filter-status',status);
		this.getProjects('standard');
    },
    
    init: function() {

		this.body.on('click','.message a.undo',function(e) {  
	        e.preventDefault();
			UI.applyUndo();
	    }).bind('keydown','Meta+f', function(e){ 
            e.preventDefault();
	        $('body').addClass('filterOpen');
	        $('#search-projectname').focus();
        });

		$("#contentBox").on('mousedown','td.actions .change',function(e) {
            e.preventDefault();
            UI.changePassword('job',$(this).parents('tr'),0,0);
        }).on('mousedown','td.actions .cancel',function(e) {
	        e.preventDefault();
	        UI.changeJobsStatus('job',$(this).parents('tr'),'cancelled');
        }).on('mousedown','td.actions .revise',function(e) {
            e.preventDefault();
            var url = $(e.target).closest('.actions').data('revise-url');
			var win = window.open( url , '_blank' );
			win.focus();
        }).on('mousedown','td.actions .download-manage, td.actions .sdlxliff',function(e) {
            e.preventDefault();
            var win = window.open(
                $(this).attr('href'), '_blank'
            );
            win.focus();
	    }).on('mousedown','td.actions .archive',function(e) {
	        e.preventDefault();
	        UI.changeJobsStatus('job',$(this).parents('tr'),'archived');
	    }).on('mousedown','td.actions .resume',function(e) {
	        e.preventDefault();
	        UI.changeJobsStatus('job',$(this).parents('tr'),'active');
	    }).on('mousedown','td.actions .unarchive',function(e) {
	        e.preventDefault();
	        UI.changeJobsStatus('job',$(this).parents('tr'),'active');
        }).on('click','a.cancel-project',function(e) {
	        e.preventDefault();
	        UI.changeJobsStatus('prj',$(this).parents('.article'),'cancelled');		
	    }).on('click','a.archive-project',function(e) {
	        e.preventDefault();
	        UI.changeJobsStatus('prj',$(this).parents('.article'),'archived');		
	    }).on('click','a.resume-project',function(e) { 
	        e.preventDefault();
	        UI.changeJobsStatus('prj',$(this).parents('.article'),'active','cancelled');		
	    }).on('click','a.unarchive-project',function(e) { 
	        e.preventDefault();
	        UI.changeJobsStatus('prj',$(this).parents('.article'),'active','archived');
	    }).on('click','.meter a',function(e) {
	        e.preventDefault();
	    }).on('click','.pagination a',function(e) {
	        e.preventDefault();
			UI.page = $(this).data('page');
			UI.getProjects('page');
		});

	    $('header .filter').click(function(e) {    
	        e.preventDefault();
	        $('body').toggleClass('filterOpen');
	        $('#search-projectname').focus();
	    });

		$('#display').on('click','.status',function(e) {
	        e.preventDefault();
	        $('body').addClass('filterOpen');
	        $('#select-status').focus();
		}).on('click','.completed',function(e) {
	        e.preventDefault();
	        $('body').addClass('filterOpen');
	        $('#only-completed').focus();
		}).on('click','.pname',function(e) {
	        e.preventDefault();
	        $('body').addClass('filterOpen');
	        $('#search-projectname').focus();
		}).on('click','.selected-source',function(e) {
	        e.preventDefault();
	        $('body').addClass('filterOpen');
	        $('#select-source').focus();
		}).on('click','.selected-target',function(e) {
	        e.preventDefault();
	        $('body').addClass('filterOpen');
	        $('#select-target').focus();
		});
	
	    $('.searchbox #exec-filter').click(function(e) {    
	        e.preventDefault();
	        UI.applyFilter();
	    });
	
	    $('.searchbox #clear-filter').click(function(e) {    
	        e.preventDefault();
	        $('body').removeClass('filterOpen filterActive').attr('data-filter-status','active');
	        UI.filters = {};
	        UI.page = 1;
	        UI.emptySearchbox();
	        UI.getProjects('standard');
	    });

	    $('.searchbox #show-archived, .searchbox #show-cancelled').click(function(e) {   
	        if ($(this).is(':checked')) {
		        $('.searchbox #only-completed').removeAttr('checked');        	
	        }
	    });
	    $('.searchbox #only-completed').click(function(e) {    
	        if ($(this).is(':checked')) {
		        $('.searchbox #show-archived, .searchbox #show-cancelled').removeAttr('checked');        	
	        }
	    });



	},

	setDropDown: function(){

		//init dropdown events on every class
		new UI.DropDown( $( '.wrapper-dropdown-5' ) );

		//set control events
		$( '.actions' ).mouseleave( function(){
			$( '.wrapper-dropdown-5' ).removeClass( 'active' );
		} );

		$(document).click(function() {
			// all dropdowns
			$('.wrapper-dropdown-5').removeClass('active');
		});

	},

	DropDown: function(el){
		this.initEvents = function () {
			var obj = this;
            obj.dd.on( 'click', function ( event ) {
				$( this ).toggleClass( 'active' );
                event.preventDefault();
				event.stopPropagation();
			} );
		};
		this.dd = el;
		this.initEvents();
	},

    appendTime: function() {
        var t = new Date();
        return '&time='+t.getTime();
    },

    applyFilter: function() {
        if($('#search-projectname').val() != '') {
        	this.filters['pn'] = $('#search-projectname').val();
        } else {
        	delete this.filters['pn'];
        }

        if($('#select-source').val() != '') {
        	this.filters['source'] = $('#select-source').val();
        } else {
        	delete this.filters['source'];
        }

        if($('#select-target').val() != '') {
        	this.filters['target'] = $('#select-target').val();
        } else {
        	delete this.filters['target'];
        }

        if($('#select-status').val() != '') {
        	this.filters['status'] = $('#select-status').val();
        	this.body.attr('data-filter-status', $('#select-status').val());
        } else {
        	delete this.filters['status'];
        }

        if($('#only-completed').is(':checked')) {
        	this.filters['onlycompleted'] = 1;
        } else {
        	delete this.filters['onlycompleted'];
        }

        this.filters['filter'] = 1;

        this.page = 1;
		this.getProjects('filter');
		this.body.addClass('filterActive');
    },

    applyUndo: function() {
		var undo = $('.message a.undo');
		switch($(undo).data('operation')) {

			case 'changeStatus':
				$('.message').hide();
				var new_status = $(undo).data('status');
				var res = $(undo).data('res');
				var id = $(undo).data('id');
				var password = $(undo).data('password');
				var ob = (res=='job')? $('tr.row[data-jid=' + id + ']') : $('.article[data-pid=' + id + ']');
				var d = {
						action:		"changeJobsStatus",
						new_status: new_status,
						res: 		res,
						id:			id,
						password:   password,
		                page:		UI.page,
		                step:		UI.pageStep,
		                undo:		1
					}
				ar = $.extend(d,UI.filters);

				APP.doRequest({
					data: ar,
					context: ob,
					success: function(d){
						if(d.data == 'OK') {
							res = ($(this).hasClass('row'))? 'job':'prj';
							UI.changeJobsStatus_success(res,$(this),d,1);
						}
					}
				});

				break;

			case 'changePassword':
				$('.message').hide();
				var res = $(undo).data('res');
				var id = $(undo).data('id');
				var pwd = $(undo).data('password');
				var ob = (res=='job')? $('tr.row[data-jid=' + id + ']') : $('.article[data-pid=' + id + ']');
				UI.changePassword( res, ob, pwd, undo );

				break;

			default:
		}

    },

    balanceAction: function(res,ob,d,undo,project) {
        console.log('d prima: ', d);
		// check if the project have to be hidden
		filterStatus = this.body.attr('data-filter-status');
		rowsInFilter = $('.article[data-pid='+project.attr('data-pid')+'] tr.row[data-status='+filterStatus+']').length;
		if(!rowsInFilter) {
			project.addClass('invisible')
		} else {
			project.removeClass('invisible');
		}
		// check if there is need to append or delete items
		numItem = $('.article:not(.invisible)').length;
        console.log('numItem: ', numItem);
		if(numItem < this.pageStep) {
//            d.newItem = d.newItem || [];
			if(typeof d != 'undefined') this.renderProjects(d.newItem,'append');
		} else if(numItem > this.pageStep) {
			$('.article:not(.invisible)').last().remove();
		}

    },

    changeJobsStatus: function(res,ob,status,only_if) {
		console.log('ob: ', ob);
        if(typeof only_if == 'undefined') only_if = 0;

        if ( res == 'job' ) {
            UI.lastJobStatus = ob.data( 'status' );
            id = ob.data( 'jid' );
            password = ob.data( 'password' );
            console.log( 'password: ', password );

        } else {
            var arJobs = '';
            $( "tr.row", ob ).each( function () {
                arJobs += $( this ).data( 'jid' ) + "-" + $( this ).data( 'password' ) + ':' + $( this ).data( 'status' ) + ',';
            } );
            arJobs = arJobs.substring( 0, arJobs.length - 1 );
            UI.lastJobStatus = arJobs;
            id = ob.data( 'pid' );
            password = ob.data('password');
        }

        var d = {
				action:		"changeJobsStatus",
				new_status: status,
				res: 		res,
				id:			id,
                password:   password,
                page:		UI.page,
                step:		UI.pageStep,
                only_if:	only_if,
                undo:		0
			}
		ar = $.extend(d,UI.filters);

		APP.doRequest({
			data: ar,
			context: ob,
			success: function(d){
				if(d.data == 'OK') {console.log('dd: ', d);
					res = ($(this).hasClass('row'))? 'job':'prj';
					if(res=='prj') {
						UI.getProject(this.data('pid'));
					}
					UI.changeJobsStatus_success(res,$(this),d,0);
					UI.setPagination(d);
				}
			},
            error: function(d){
                document.location = '/';
            }
		});
    },

    changeJobsStatus_success: function(res,ob,d,undo) {
        if(res == 'job') {
			project = ob.parents('.article');
			if(undo) {
				ob.attr('data-status',d.status);
			} else {
				id = ob.data('jid');
				if(d.status == 'cancelled') {
					msg = 'A job has been cancelled.';
				} else if(d.status == 'archived') {
					msg = 'A job has been archived.';
				} else if(d.status == 'active') {
					msg = 'A job has been resumed as active.';
				}
				ob.attr('data-status',d.status).attr('data-password',ob.data('password'));
			}

		} else {
			project = ob;
			if(undo) {
				$.each(d.status.split(','), function() {
					var s = this.split(':');
                    var job_info = s[0].split('-'); //123-abc534f001
                    $( 'tr.row[data-jid=' + job_info[0] + ']' ).attr( 'data-status', s[1] );
				})
			} else {
				id = ob.data('pid');
				if(d.status == 'cancelled') {
					msg = 'All the jobs in a project has been cancelled.';
				} else if(d.status == 'archived') {
					msg = 'All the jobs in a project has been archived.';
				} else if(d.status == 'active') {
					msg = 'All the jobs in a project has been resumed as active.';
				}
				$('tr.row',project).each(function(){
					$(this).attr('data-status',d.status);
			    })
			}
		}
		if(!undo) {
			var token =  new Date();
            var resData = (res == 'prj') ? 'pid' : 'jid';
            $( '.message' ).attr( 'data-token', token.getTime() ).html( msg +
            ' <a href="#" class="undo" data-res="' + res +
            '" data-id="' + ob.data( resData ) +
            '" data-password="' + ob.data( 'password' ) +
            '" data-operation="changeStatus" data-status="' + ((res == 'prj') ? d.old_status : this.lastJobStatus) + '">Undo</a>' ).show();
			setTimeout(function(){
				$('.message[data-token='+token.getTime()+']').hide();
			},5000);
		}
		this.balanceAction(res,ob,d,undo,project);
    },

    changePassword: function(res,ob,pwd,undo) {
        if(typeof pwd == 'undefined') pwd = false;
        if(res=='job') {
        	id = ob.data('jid');
        	password = (pwd)? pwd : ob.data('password');
        }

        if( undo ){
            old_password = $(undo).data('old_password');
        } else {
            old_password = null;
        }

        APP.doRequest({
            data: {
                action:		    "changePassword",
                res: 		    res,
                id: 		    id,
                password: 	    password,
                old_password: 	old_password,
                undo:           ( typeof undo == 'object' )
            },
            context: ob,
            success: function(d){
                res = ($(this).hasClass('row'))? 'job':'prj';
                UI.changePassword_success(res,$(this),d,undo);
            }
        });
    },

    changePassword_success: function(res,ob,d,undo) {
		var jd = $(ob).find('.job-detail');
		var newPwd = d.password;
		uu = $('.urls .url',jd);
		uuh = uu.attr('href');
		uuhs = uuh.split('-');
		oldPwd = uuhs[uuhs.length-1];
		newHref = uuh.replace(oldPwd,newPwd);
		uu.attr('href',newHref);
		newCompressedHref = this.compressUrl(newHref);
		$('.urls .url',jd).text(newCompressedHref);
		$(jd).effect("highlight", {}, 1000);

		if(res == 'job') {
			ob.attr('data-password',d.password);
			if(undo) {
				msg = 'A job password has been restored.';
			} else {
				msg = 'A job password has been changed.';
			}

		} else {
		}

		if(!undo) {

            console.log(res);
            console.log(ob);
            console.log(d);
            console.log(undo);
            console.log(newPwd);
            console.log(oldPwd);

			var token =  new Date();
			var resData = (res == 'prj')? 'pid':'jid';
			$('.message').attr('data-token',token.getTime()).html(msg + ' <a href="#" class="undo" data-res="' + res + '" data-id="' + ob.data(resData)+ '" data-operation="changePassword" data-password="' + newPwd + '" data-old_password="' + oldPwd + '">Undo</a>').show();
			setTimeout(function(){
				$('.message[data-token='+token.getTime()+']').hide();
			},5000);
		}

    },

    compileDisplay: function() {
    	var status = (typeof this.filters.status != 'undefined')? this.filters.status : 'active';
    	var pname = (typeof this.filters.pn != 'undefined')? ' "<a class="pname" href="#">' + this.filters.pn + '</a>" in the name,' : '';
    	var source = (typeof this.filters.source != 'undefined')? ' <a class="selected-source" href="#">' + $('#select-source option[value='+this.filters.source+']').text() + '</a> as source language,' : '';
    	var target = (typeof this.filters.target != 'undefined')? ' <a class="selected-target" href="#">' + $('#select-target option[value='+this.filters.target+']').text() + '</a> as target language,' : '';
    	var completed = (typeof this.filters.onlycompleted != 'undefined')? ' <a class="completed">completed</a>' : '';
    	var ff = ((pname != '')||(source != '')||(target != ''))? ' having' : '';
    	var tt = 'Showing' + completed + ' <a class="status" href="#">' + status + '</a> projects' + ff + pname + source + target;
    	tt = tt.replace(/\,$/, '');
    	$('#display').html(tt);
	},

    compressUrl: function(url) {
		var arr = url.split('/');
		compressedUrl = config.hostpath + '/translate/.../' + arr[4];
		return compressedUrl;
	},

    emptySearchbox: function() {
        $('#search-projectname').val('');
        $('#select-source option[selected=selected]').removeAttr('selected');
        $('#select-source option').first().attr('selected','selected');
        $('#select-target option[selected=selected]').removeAttr('selected');
        $('#select-target option').first().attr('selected','selected');
        $('#select-status option[selected=selected]').removeAttr('selected');
        $('#select-status option').first().attr('selected','selected');
    },

    filters2hash: function() {
		var hash = '#';
		$.each(this.filters, function(key,value) {
			hash += key + '=' + value + ',';
		})
		hash = hash.substring(0, hash.length - 1);
		return hash;
    },

    getProject: function(id) {
		var d = {
                action: 'getProjects',
                project: id,
                page:	UI.page
			}
		ar = $.extend(d,UI.filters);

		APP.doRequest({
			data: ar,
			success: function(d){
				data = $.parseJSON(d.data);

                if( typeof d.errors != 'undefined' && d.errors.length ){
                    window.location = '/';
                }

				UI.renderProjects(data,'single');
				UI.setTablesorter();

				//init dropdown events on every class
				UI.setDropDown();

			},
            error: function(d){
                window.location = '/';
            }
		});
	},

    getProjects: function(what) {
		UI.body.addClass('loading');
		var d = {
                action: 'getProjects',
                page:	UI.page
			}
		ar = $.extend(d,UI.filters);

		APP.doRequest({
			data: ar,
			success: function(d){
				UI.body.removeClass('loading');
				data = $.parseJSON(d.data);

                if( typeof d.errors != 'undefined' && d.errors.length ){
                    window.location = '/';
                }

                UI.pageStep = d.pageStep;

				UI.setPagination(d);
				UI.renderProjects(data,'all');
				if((d.pnumber - UI.pageStep) > 0) UI.renderPagination(d.page,0,d.pnumber);
				UI.setTablesorter();
				var stateObj = { page: d.page };

				if(what == 'filter') {
					history.pushState(stateObj, "page "+d.page, d.page+UI.filters2hash());
				} else if(what == 'page') {
					history.pushState(stateObj, "page "+d.page, d.page+UI.filters2hash());
				} else {
					history.replaceState(stateObj, "page "+d.page, d.page+UI.filters2hash());
				}
				UI.compileDisplay();

					//UI.outsourceElements = $( ".missing-outsource-data" );
					//UI.getOutsourceQuotes();

				UI.setDropDown();

		        $("html,body").animate({
		            scrollTop: 0
		        }, 500 );
			},
            error: function(d){
                window.location = '/';
            }
		});
	},

    renderPagination: function(page,top,pnumber) {
    	page = parseInt(page);

    	var prevLink = (page>1)? '<a href="#" data-page="' + (page-1) + '">&lt;</a>' : '';
    	var aroundBefore = (page==1)? '<strong>1</strong>' : (page==2)? '<a href="#" data-page="1">1</a><strong>2</strong>' : (page==3)? '<a href="#" data-page="1">1</a><a href="#" data-page="2">2</a><strong>3</strong>' : (page==4)? '<a href="#" data-page="1">1</a><a href="#" data-page="2">2</a><a href="#" data-page="3">3</a><strong>4</strong>' : '<a href="#" data-page="1">1</a>...<a href="#" data-page="'+(page-2)+'">'+(page-2)+'</a><a href="#" data-page="'+(page-1)+'">'+(page-1)+'</a><strong>'+page+'</strong>';
    	var pages = Math.floor(pnumber/UI.pageStep)+1;
     	var nextLink = (page<pages)? '<a href="#" data-page="' + (page+1) + '">&gt;</a>' : '';
    	var aroundAfter = (page==pages)? '' : (page==pages-1)? '<a href="#" data-page="'+(page+1)+'">'+(page+1)+'</a>' : (page==pages-2)? '<a href="#" data-page="'+(page+1)+'">'+(page+1)+'</a><a href="#" data-page="'+(page+2)+'">'+(page+2)+'</a>' : (page==pages-3)? '<a href="#" data-page="'+(page+1)+'">'+(page+1)+'</a><a href="#" data-page="'+(page+2)+'">'+(page+2)+'</a><a href="#" data-page="'+(page+3)+'">'+(page+3)+'</a>' : '<a href="#" data-page="'+(page+1)+'">'+(page+1)+'</a><a href="#" data-page="'+(page+2)+'">'+(page+2)+'</a>...<a href="#" data-page="'+(pages)+'">'+(pages)+'</a>';

     	var fullLink = prevLink + aroundBefore + aroundAfter + nextLink;

	   	if(top) {
    		if($('.pagination.top').length) {
    			$('.pagination.top').html(fullLink);
    		} else {
    			$('#contentBox h1').after('<div class="pagination top">'+fullLink+'</div>');
    		}
    	} else {
    		if($('.pagination.bottom').length) {
    			$('.pagination.bottom').html(fullLink);
    		} else {
    			$('#contentBox').append('<div class="pagination bottom">'+fullLink+'</div>');
    		}
    	}

	},

    renderProjects: function(d,action) {
        this.retrieveTime = new Date();
        var projects = '';
        $.each(d, function() {
            var project = this;
            var newProject = '';

			newProject += '<div data-pid="'+this.id+'" data-password="' + this.password + '" class="article">'+
	            '	<div class="head">'+
		        '	    <h2>'+this.name+'</h2>'+
		        '	    <div class="project-details">';

            if(config.v_analysis){
                newProject += '			<span class="id-project" title="Project ID">'+this.id+'</span> - <a target="_blank" href="/analyze/'+project.name+'/'+this.id+'-' + this.password + '" title="Volume Analysis">'+this.tm_analysis+' Payable words</a>';
            }

            newProject += '			<a href="#" title="Cancel project" class="cancel-project"></a>'+
		        '	    	<a href="#" title="Archive project" class="archive-project"></a>'+
		        '			<a href="#" title="Resume project" class="resume-project"></a>'+
		        '	    	<a href="#" title="Unarchive project" class="unarchive-project"></a>'+
		        '	    	<a href="/activityLog/' + this.id + '/' + this.password + '" title="Activity Log" class="activity-log" target="_blank"></a>'+
		        '		</div>'+
	            '	</div>'+
	            '	<div class="field">'+
	            '		<h3>Machine Translation:</h3>'+
	            '		<span class="value">' + this.mt_engine_name + '</span>'+
	            '	</div>';

//            if (this.private_tm_key!==''){
//
//                     newProject += '	<div class="field">'+
//	            '		<h3>Private TM Key:</h3>'+
//	            '		<span class="value">'+this.private_tm_key+'</span>'+
//	            '	</div>';
//            }

		      newProject += '    <table class="tablestats continue tablesorter" width="100%" border="0" cellspacing="0" cellpadding="0" id="project-'+this.id+'">'+
		        '        <thead>'+
			    '            <tr>'+
			    '                <th class="create-date header">Create Date</th>'+
			    '                <th class="job-detail">Job</th>'+
			    '                <th class="private-tm-key">Private TM Key</th>';

            if(config.v_analysis){
                newProject += '                <th class="words header">Words</th>';
            }

            newProject += '                <th class="progress header">Progress</th>'+
				'	<!-- th class="progress header">Outsource</th -->' +
			    '                <th class="actions">Actions</th>'+
			    '            </tr>'+
		        '        </thead>'+
				'		<tbody>';

    		$.each(this.jobs, function() {
            var use_prefix = (APP.objectSize(this) > 1)? true : false;
            var index = 0;

            $.each(this, function() {
                index++;

                var private_tm_keys = '';
                var possibly_different_review_password = ( typeof this.review_password == 'undefined' ? this.password : this.review_password );

                this.private_tm_key = $.parseJSON(this.private_tm_key);
                $.each(this.private_tm_key, function(i, tm_key){
                    private_tm_keys +=  "<span class='key'>"    + tm_key.key  + "</span>"+
                                        "<span class='rgrant'>" + tm_key.r    + "</span>"+
                                        "<span class='wgrant'>" + tm_key.w    + "</span><br class='clear'/>";
                });

                var chunk_id = this.id+( ( use_prefix ) ? '-' + index : '' ) ;
                var translate_url = '/translate/'+project.name+'/'+this.source+'-'+this.target+'/'+ chunk_id +'-'+this.password  ;
                var revise_url = '/revise/'+project.name+'/'+this.source+'-'+this.target+'/'+ chunk_id +'-'+ possibly_different_review_password  ;

		        var newJob = '    <tr class="row " data-jid="'+this.id+'" data-status="'+this.status+'" data-password="'+this.password+'">'+
		            '        <td class="create-date" data-date="'+this.create_date+'">'+this.formatted_create_date+'</td>'+
		            '        <td class="job-detail">'+
		            '        	<span class="urls">'+
		            '        		<div class="jobdata">'+ chunk_id + '<span class="langs">' + this.sourceTxt+'&nbsp;&gt;&nbsp;'+this.targetTxt +'</span></div>'+
		            '        		<a class="url" target="_blank" href="' + translate_url +'">'+config.hostpath+'/translate/.../'+ chunk_id +'-'+this.password+'</a>'+
		            '        	</span>'+
		            '        </td>'+
		            '        <td class="tm-key">'+
		                    	private_tm_keys +
		            '        </td>';
                if(config.v_analysis){
                    newJob += '        <td class="words">'+this.stats.TOTAL_FORMATTED+'</td>';
                }


                newJob += '        <td class="progress">'+
				    '            <div class="meter">'+
				    '                <a href="#" class="approved-bar" title="Approved '+this.stats.APPROVED_PERC_FORMATTED+'%" style="width:'+this.stats.APPROVED_PERC+'%"></a>'+
				    '                <a href="#" class="translated-bar" title="Translated '+this.stats.TRANSLATED_PERC_FORMATTED+'%" style="width:'+this.stats.TRANSLATED_PERC+'%"></a>'+
				    '                <a href="#" class="rejected-bar" title="Rejected '+this.stats.REJECTED_PERC_FORMATTED+'%" style="width:'+this.stats.REJECTED_PERC+'%"></a>'+
				    '                <a href="#" class="draft-bar" title="Draft '+this.stats.DRAFT_PERC_FORMATTED+'%" style="width:'+this.stats.DRAFT_PERC+'%"></a>'+
				    '            </div>'+
		            '        </td>'+
		            '        <td class="actions" data-revise-url="' + revise_url + '">'+
		            '			<div id="dd' + index + '" class="wrapper-dropdown-5" tabindex="1">&nbsp;'+
    				'				<ul class="dropdown">'+
    				'					<li><a class="change" href="#" title="Change job password"><span class="icon-refresh"></span>Change Password</a></li>'+
        			'					<li><a class="cancel" href="#" title="Cancel Job"><span class="icon-trash-o"></span>Cancel</a></li>'+
				// '						<li><a class="revise" href="#" title="Revise Job"><span class="icon-edit"></span>Revise</a></li>'+
        			'					<li><a class="archive" href="#" title="Archive Job"><span class="icon-drawer"></span>Archive</a></li>'+
        			'					<li><a class="resume" href="#" title="Resume Job"><span class="icon-trash-o noticon"></span>Resume</a></li>'+
        			'					<li><a class="unarchive" href="#" title="Unarchive Job"><span class="noticon icon-drawer"></span>Unarchive</a></li>'+
					(this.show_download_xliff ? '            		<li><a class="sdlxliff" target="_blank" href="/SDLXLIFF/' + this.id + '/' + this.password + '/' + project.name + '.zip" title="Export XLIFF"><span class="icon-download"></span>Export XLIFF</a></li>' : '')+
					'					<li><a target="_blank" href="/TMX/' + this.id + '/' + this.password + '" class="download-manage"><span class="icon-download"></span>Export TMX</a></li>'+
					(config.enable_omegat ? '					<li><a target="_blank" href="/?action=downloadFile&id_job=' + this.id + '&password=' + this.password + '&id_file=&filename=' + project.name + '.zip&download_type=omegat&forceXliff=1" class="download-manage"><span class="icon-download"></span>Export OmegaT</a></li>' : '')+
        			'				</ul>'+
        			'			</div><input type="button" class="btn pull-right revise" value="Revise">'+
		            '        </td>'+
		            '    </tr>';


				newProject += newJob;
            })
        });

			newProject +='		</tbody>'+	
	        '    </table>'+
            '</div>';

    		projects += newProject;
        });
        if(action == 'append') {
	        $('#projects').append(projects);
        } else if(action == 'single') {
            $( '.article[data-pid=' + d[0].id + ']' ).replaceWith( projects );
        } else {
	        if(projects == '') projects = '<p class="article msg">No projects found for these filter parameters.<p>';
	        $('#projects').html(projects);        	        	
        }

        //fit Text for long project names
        $(".article").each(function() {
            APP.fitText( $( '.head', $( this ) ), $( '.head h2', $( this ) ), 78, 50 );
        });

    }, // renderProjects

    setPagination: function(d) {
		if((d.pnumber - d.pageStep) > 0) {
			this.renderPagination(d.page,1,d.pnumber);
		} else {
			$('.pagination').empty();
		}
	},
	
    setTablesorter: function() {
	    $(".tablesorter").tablesorter({
	        textExtraction: function(node) { 
	            // extract data from markup and return it  
	            if($(node).hasClass('create-date')) {
	            	return $(node).data('date');
	            } else {
	            	return $(node).text();
	            }
	        }, 
	        headers: { 
	            1: { 
	                sorter: false 
	            }, 
	            4: { 
	                sorter: false 
	            },
				5: {
					sorter: false
				}
	        }			    	
	    });
    },

    getOutsourceQuotes: function() {
        if ( UI.outsourceElements.length == 0 ) {
            return;
        }

		var tableElement = $( UI.outsourceElements[0] );
		UI.outsourceElements.splice(0, 1);

		var pid_data = tableElement.parents( "table" ).attr( "id" ).split( "-" );
		var pid = pid_data[ 1 ];

		var url_data = $( "div[data-pid='" + pid + "'] div.project-details > a" ).attr( "href" ).split( "/" );
		var psw_data = url_data[ url_data.length - 1 ].split( "-" );
		var psw = psw_data[ 1 ];

		var jid = tableElement.parent( "tr" ).attr( "data-jid" );
		var jsw = tableElement.parent( "tr" ).attr( "data-password" );

		if ( $( "div[data-pid='" + pid + "'] div.project-details > a" ).text().charAt( 0 ) == '0' )
		{
			tableElement.html( "0 words found.<br/>Unable to quote." );
			tableElement.removeClass( "missing-outsource-data" );

			//UI.getOutsourceQuotes();
			return;
		}
	
		$.ajax({
			async: true,
	  		type: "POST",
			url : "/?action=outsourceTo",
			data:
			{
				action: 'outsourceTo',
				pid: pid,
				ppassword: psw,
				jobs:
				[{
					jid: jid,
					jpassword: jsw
				}]
			},
			success : function ( data )
			{
				if ( ( data.data[0]["price"] > 0 ) && ( data.data[0]["delivery_date"] != "" ) )
				{
					var price = parseFloat( data.data[0]["price_currency"] ).toFixed( 2 );
					var date = new Date( data.data[0]["delivery_date"] );
					var delivery = "<b>" + date.getDate() + "/" + ( date.getMonth() + 1 ) + "</b> at <b>" + date.getHours() + ":" + ( ( date.getMinutes() != 0 ) ? date.getMinutes() : "00" ) + "</b>";

                    if ( data.data[0].currency == "EUR" ) {
                        var currency = "€"
                    } else {
                        var currency = data.data[0].currency;
                    }

					var form = 	"<form class='submit-outsource-data' action='http://signin.translated.net/' method='POST' target='_blank'>" +
                               		"<input type='hidden' name='url_ok' value='" + data.return_url.url_ok + "'>" +
                            		"<input type='hidden' name='url_ko' value='" + data.return_url.url_ko + "'>" +
                                    "<input type='hidden' name='data_key' value='" + jid + "-" + jsw + "'>" +
									"<input type='hidden' name='quote_data' value='" + JSON.stringify( data.data ) + "'>" +
 	                            	"<button type='submit' class='outsource-btn'><span class='outsource-price'> " + currency + " " + price + "</span><span class='outsource-delivery'><strong>Delivery</strong><br> " + delivery + "</span></button>" +
                                "</form>";

					tableElement.html( form );
					tableElement.removeClass( "missing-outsource-data" );

					//UI.getOutsourceQuotes();
				}
			}
		});
	}

} // UI

var monthNames = [ "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December" ];

var dayNames = [ "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];


function setBrowserHistoryBehavior() {
	window.onpopstate = function(e) {
		e.preventDefault();
		if(UI.firstLoad) {
			UI.firstLoad = false;
			return;
		}
		UI.render(false);
	};
}


$(document).ready(function(){
    setBrowserHistoryBehavior();
    UI.render(true);
    UI.init();
});
