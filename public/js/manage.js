UI = null;

UI = {
	
    render: function(firstLoad) {
        this.isWebkit = $.browser.webkit;
        this.isChrome = $.browser.webkit && !!window.chrome;
        this.isFirefox = $.browser.mozilla;
        this.isSafari = $.browser.webkit && !window.chrome;
        this.body = $('body');
        this.firstLoad = firstLoad;
//        if(firstLoad) this.startRender = true;
        this.pageStep = 100;

		this.isMac = (navigator.platform == 'MacIntel')? true : false;
        
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
	    })
		
		$("#contentBox").on('click','td.actions a.cancel',function(e) {  
	        e.preventDefault();
	        UI.changeJobsStatus('job',$(this).parents('tr'),'cancelled');
	    }).on('click','td.actions a.archive',function(e) {  
	        e.preventDefault();
	        UI.changeJobsStatus('job',$(this).parents('tr'),'archived');
	    }).on('click','td.actions a.resume',function(e) {  
	        e.preventDefault();
	        UI.changeJobsStatus('job',$(this).parents('tr'),'active');
	    }).on('click','td.actions a.unarchive',function(e) {  
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
	    }).on('click','td.actions a.change',function(e) {;
	        e.preventDefault();
	        UI.changePassword('job',$(this).parents('tr'),0,0);
/*
			var m = confirm('You are changing the password for this job. \nThe current link will not work anymore! \nDo you want to proceed?');
			if(m) {
				UI.doRequest({
					data: {
						action:		"changePassword",
						res: 		"job",
						id: 		$(this).parents('tr').data('jid')
					},
					context: $(this).parents('tr.row').find('.job-detail'),
					success: function(d){
						var newPwd = d.password;
						uu = $('.urls .url',this);
						uuh = uu.attr('href');
						uuhs = uuh.split('-');
						oldPwd = uuhs[uuhs.length-1];
						newHref = uuh.replace(oldPwd,newPwd);
						uu.attr('href',newHref);
						$('.urls .url',this).text(config.hostpath + newHref);
						$(this).effect("highlight", {}, 1000);
	
					}
				});
			}
*/	
	    }).on('click','.meter a',function(e) {
	        e.preventDefault();
/*
	    }).on('click','.tablefilter label',function(e) {	
	        $(this).parent().find('input').click();
*/
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
				var ob = (res=='job')? $('tr.row[data-jid=' + id + ']') : $('.article[data-pid=' + id + ']');
				var d = {
						action:		"changeJobsStatus",
						new_status: new_status,
						res: 		res,
						id:			id,
		                page:		UI.page,
		                step:		UI.pageStep,
		                undo:		1
					}
				ar = $.extend(d,UI.filters);
				
				UI.doRequest({
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
				UI.changePassword(res,ob,pwd,1);

				break;

			default:
		}


    },
    
    balanceAction: function(res,ob,d,undo,project) {
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
    	if(numItem < this.pageStep) {
    		this.renderProjects(d.newItem,'append');
    	} else if(numItem > this.pageStep) {
    		$('.article:not(.invisible)').last().remove();
    	}

    },

    changeJobsStatus: function(res,ob,status,only_if) {
        console.log('status: '+status);
        if(typeof only_if == 'undefined') only_if = 0;
        if(res=='job') {
        	UI.lastJobStatus = ob.data('status');
        	id = ob.data('jid');
        } else {
		    var arJobs = '';
		    $("tr.row",ob).each(function(){
		        arJobs += $(this).data('jid')+':'+$(this).data('status')+',';
		    })
		    arJobs = arJobs.substring(0, arJobs.length - 1);
		    UI.lastJobStatus = arJobs;
		    id = ob.data('pid');
        }
		var d = {
				action:		"changeJobsStatus",
				new_status: status,
				res: 		res,
				id:			id,
                page:		UI.page,
                step:		UI.pageStep,
                only_if:	only_if,
                undo:		0
			}
		ar = $.extend(d,UI.filters);

		UI.doRequest({
			data: ar,
			context: ob,
			success: function(d){
				if(d.data == 'OK') {
					res = ($(this).hasClass('row'))? 'job':'prj';
					if(res=='prj') {
						UI.getProject(this.data('pid'));

/*
        				filterStatus = (this.body.attr('data-filter-status'));
        				if(filterStatus=='active') {
//        					if(status)
        				} else if(status == UI.filters['status']) {
							UI.getProject(this.data('pid'))
						}
*/
					}
					UI.changeJobsStatus_success(res,$(this),d,0);
					UI.setPagination(d);
				}
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
//					setHas = true;
//					dataName = 'hascancelled';
					msg = 'A job has been cancelled.';
				} else if(d.status == 'archived') {
//					setHas = true;
//					dataName = 'hasarchived';
					msg = 'A job has been archived.';
				} else if(d.status == 'active') {
//					setHas = false;
//					dataName = '';
					msg = 'A job has been resumed as active.';
				}
				ob.attr('data-status',d.status);
//				if(setHas) project.attr('data-'+dataName,1);
			}

		} else {
			project = ob;
			if(undo) {
				console.log(d.status);
				$.each(d.status.split(','), function() {
					var s = this.split(':');
					$('tr.row[data-jid='+s[0]+']').attr('data-status',s[1]);
				})
			} else {
				id = ob.data('pid');
				if(d.status == 'cancelled') {
//					setHas = true;
//					dataName = 'hascancelled';
					msg = 'All the jobs in a project has been cancelled.';
				} else if(d.status == 'archived') {
//					setHas = true;
//					dataName = 'hasarchived';
					msg = 'All the jobs in a project has been archived.';
				} else if(d.status == 'active') {
//					setHas = false;
//					dataName = '';
					msg = 'All the jobs in a project has been resumed as active.';
				}	
				$('tr.row',project).each(function(){
					$(this).attr('data-status',d.status);
//					if(setHas) project.attr('data-'+dataName,1);
			    })
			}
		}
		if(!undo) {
			var token =  new Date();
			var resData = (res == 'prj')? 'pid':'jid';
			$('.message').attr('data-token',token.getTime()).html(msg + ' <a href="#" class="undo" data-res="' + res + '" data-id="' + ob.data(resData)+ '" data-operation="changeStatus" data-status="' + ((res == 'prj')? d.old_status : this.lastJobStatus) + '">Undo</a>').show();
			console.log($('.message').html());
			setTimeout(function(){
				$('.message[data-token='+token.getTime()+']').hide();
			},5000);
		}
		this.balanceAction(res,ob,d,undo,project);
//		this.verifyProjectHasCancelled(project);
//		this.verifyProjectHasArchived(project);

    },

    changePassword: function(res,ob,pwd,undo) {
        if(typeof pwd == 'undefined') pwd = false;
        console.log(pwd);
        if(res=='job') {
        	id = ob.data('jid');
        	password = (pwd)? pwd : '';
        } else {
        }
				console.log('ecco');

		UI.doRequest({
			data: {
				action:		"changePassword",
				res: 		res,
				id: 		id,
				password: 	password
			},
			context: ob,
			success: function(d){
				res = ($(this).hasClass('row'))? 'job':'prj';
				UI.changePassword_success(res,$(this),d,undo);
			}
		});
    },

    changePassword_success: function(res,ob,d,undo) {
		console.log('dd');
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

		console.log('undo:');
		console.log(undo);
		if(!undo) {
			var token =  new Date();
			var resData = (res == 'prj')? 'pid':'jid';
			$('.message').attr('data-token',token.getTime()).html(msg + ' <a href="#" class="undo" data-res="' + res + '" data-id="' + ob.data(resData)+ '" data-operation="changePassword" data-password="' + oldPwd + '">Undo</a>').show();
			console.log($('.message').html());
			setTimeout(function(){
				$('.message[data-token='+token.getTime()+']').hide();
			},5000);
		}

    },

    compileDisplay: function() {
    	var status = (typeof this.filters.status != 'undefined')? this.filters.status : 'active';
    	var pname = (typeof this.filters.pn != 'undefined')? ' "<strong>' + this.filters.pn + '</strong>" in the name,' : '';
    	var source = (typeof this.filters.source != 'undefined')? ' <strong>' + $('#select-source option[value='+this.filters.source+']').text() + '</strong> as source language,' : '';
    	var target = (typeof this.filters.target != 'undefined')? ' <strong>' + $('#select-target option[value='+this.filters.target+']').text() + '</strong> as target language,' : '';
    	var completed = (typeof this.filters.onlycompleted != 'undefined')? ' <strong>completed</strong>' : '';
    	var ff = ((pname != '')||(source != '')||(target != ''))? ' having' : '';
    	var tt = 'showing' + completed + ' <strong class="status">' + status + '</strong> projects' + ff + pname + source + target;
    	tt = tt.replace(/\,$/, '');
    	$('#display').html(tt);
	},

    compressUrl: function(url) {
		var arr = url.split('/');
		compressedUrl = config.hostpath + '/translate/.../' + arr[4];
		return compressedUrl;
	},

	doRequest: function(req) {
        var setup = {
            url:      config.basepath + '?action=' + req.data.action + this.appendTime(),
            data:     req.data,
            type:     'POST',
            dataType: 'json'
        };

        // Callbacks
        if (typeof req.success === 'function') setup.success = req.success;
        if (typeof req.complete === 'function') setup.complete = req.complete;
        if (typeof req.context != 'undefined') setup.context = req.context;

        $.ajax(setup);
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

    formatDate: function(tt) {
    	var t = UI.retrieveTime;
    	var d = new Date(tt);
    	
//    	console.log(UI.retrieveTime.toDateString());
//    	console.log(d.toDateString());
/*
		var options = {year: "numeric", month: "short", day: "numeric"};
    	prova = d.toLocaleDateString("en-US", options);
    	console.log(prova);
*/
    	if(d.getDate() == t.getDate()) {
    		txtDay = 'Today';
    	} else if(d.getDate() == t.getDate()-1) {
    		txtDay = 'Yesterday';
    	} else if((d.getFullYear()==t.getFullYear())&&(d.getMonth()==t.getMonth())) {
    		txtDay = monthNames[d.getMonth()] + ' ' + d.getDate() + ' ' + dayNames[d.getDay()];
    	} else {
    		txtDay = ((d.getFullYear()==t.getFullYear())? '' : d.getFullYear()) + ' ' + monthNames[d.getMonth()] + ' ' + d.getDate();
    	}
    	h = d.getHours();
     	m = d.getMinutes();
   		formattedData =  txtDay + ', ' + ((h<10)? '0':'') + h +':' + ((m<10)? '0':'') + m;
//    	formattedData = d.getFullYear() + ' ' + monthNames[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getHours() + ':' + d.getMinutes();
//    	today = 
    	return formattedData;
	},

    getProject: function(id) {
		var d = {
                action: 'getProjects',
                project: id,
                page:	UI.page
			}
		ar = $.extend(d,UI.filters);
		
		this.doRequest({
			data: ar,
			success: function(d){
				data = $.parseJSON(d.data);
				UI.renderProjects(data,'single');
				UI.setTablesorter();
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
		
		this.doRequest({
			data: ar,
			success: function(d){
				UI.body.removeClass('loading');
				data = $.parseJSON(d.data);
				UI.setPagination(d);
				UI.renderProjects(data,'all');
				if((d.pnumber - UI.pageStep) > 0) UI.renderPagination(d.page,0,d.pnumber);
				UI.setTablesorter();
				var stateObj = { page: d.page };
//				history.pushState(stateObj, "page "+d.page, d.page+location.hash);
				// memo: se sto solo facendo un filtro devo usare pushState, altrimenti replaceState
				if(what == 'filter') {
					history.pushState(stateObj, "page "+d.page, d.page+UI.filters2hash());
				} else if(what == 'page') {
					history.pushState(stateObj, "page "+d.page, d.page+UI.filters2hash());
				} else {
					history.replaceState(stateObj, "page "+d.page, d.page+UI.filters2hash());
				}
				UI.compileDisplay();
			}
		});
	},

    setPagination: function(d) {
		if((d.pnumber - UI.pageStep) > 0) {
			this.renderPagination(d.page,1,d.pnumber);
		} else {
			$('.pagination').empty();
		}
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

			newProject += '<div data-pid="'+this.id+'" class="article">'+
	            '	<div class="head">'+
		        '	    <h2>'+this.name+'</h2>'+
		        '	    <div class="project-details">'+
		        '			<span class="id-project" title="Project ID">'+this.id+'</span> - <a target="_blank" href="/analyze/'+project.name+'/'+this.id+'-'+this.password+'" title="Volume Analysis">'+parseInt(this.tm_analysis)+' Payable words</a>'+
		        '			<a href="#" title="Cancel project" class="cancel-project"></a>'+
		        '	    	<a href="#" title="Archive project" class="archive-project"></a>'+
		        '			<a href="#" title="Resume project" class="resume-project"></a>'+
		        '	    	<a href="#" title="Unarchive project" class="unarchive-project"></a>'+
		        '		</div>'+
	            '	</div>'+
	            '	<div class="field">'+
	            '		<h3>Machine Translation:</h3>'+
	            '		<span class="value">MyMemory (All Pairs)</span>'+
	            '	</div>'+
		        '    <table class="tablestats continue tablesorter" width="100%" border="0" cellspacing="0" cellpadding="0" id="project-'+this.id+'">'+
		        '        <thead>'+
			    '            <tr>'+
			    '                <th class="create-date header">Create Date</th>'+
			    '                <th class="job-detail">Job</th>'+
			    '                <th class="words header">Payable Words</th>'+
			    '                <th class="progress header">Progress</th>'+
			    '                <th class="actions">Actions</th>'+
			    '            </tr>'+
		        '        </thead>'+
	
				'		<tbody>';
    		$.each(this.jobs, function() {
//    			console.log(this);
        		var newJob = '';


		        newJob += '    <tr class="row " data-jid="'+this.id+'" data-status="'+this.status+'" data-password="'+this.password+'">'+
		            '        <td class="create-date" data-date="'+this.create_date+'">'+UI.formatDate(this.create_date)+'</td>'+
		            '        <td class="job-detail">'+
		            '        	<span class="urls">'+
		            '        		<div class="langs">'+this.sourceTxt+'&nbsp;&gt;&nbsp;'+this.targetTxt+'</div>'+
		            '        		<a class="url" target="_blank" href="/translate/'+project.name+'/'+this.source+'-'+this.target+'/'+this.id+'-'+this.password+'">'+config.hostpath+'/translate/.../'+this.id+'-'+this.password+'</a>'+
		            '        	</span>'+
		            '        </td>'+
		            '        <td class="words">'+this.stats.TOTAL_FORMATTED+'</td>'+
		            '        <td class="progress">'+
				    '            <div class="meter">'+
				
				    '                <a href="#" class="approved-bar" title="Approved '+this.stats.APPROVED_PERC_FORMATTED+'%" style="width:'+this.stats.APPROVED_PERC+'%"></a>'+
				    '                <a href="#" class="translated-bar" title="Translated '+this.stats.TRANSLATED_PERC_FORMATTED+'%" style="width:'+this.stats.TRANSLATED_PERC+'%"></a>'+
				    '                <a href="#" class="rejected-bar" title="Rejected '+this.stats.REJECTED_PERC_FORMATTED+'%" style="width:'+this.stats.REJECTED_PERC+'%"></a>'+
				    '                <a href="#" class="draft-bar" title="Draft '+this.stats.DRAFT_PERC_FORMATTED+'%" style="width:'+this.stats.DRAFT_PERC+'%"></a>'+
				    '            </div>'+
		            '        </td>'+
		            '        <td class="actions">'+
		            '            <a class="change" href="#" title="Change job password">Change</a>'+
		            '            <a class="cancel" href="#" title="Cancel Job">Cancel</a>'+
		            '            <a class="archive" href="#" title="Archive Job">Archive</a>'+
		            '            <a class="resume" href="#" title="Resume Job">Resume</a>'+
		            '            <a class="unarchive" href="#" title="Unarchive Job">Unarchive</a>'+
		            '        </td>'+
		            '    </tr>';

				newProject += newJob;
    		});


			newProject +='		</tbody>'+	
	        '    </table>'+
            '</div>';
    		
    		projects += newProject;
//			$('#contentBox').append(newProject);
     	
        });
        console.log('action: ' + action);
        if(action == 'append') {
	        $('#projects').append(projects);  	
        } else if(action == 'single') {
        	$('.article[data-pid='+d[0].id+']').replaceWith(projects);
//        	console.log(d[0].id);
        } else {
	        if(projects == '') projects = '<p class="article msg">No projects found for these filter parameters.<p>';
	        $('#projects').html(projects);        	        	
        }


    }, // renderProjects
	
    setTablesorter: function() {
	    $(".tablesorter").tablesorter({
	        textExtraction: function(node) { 
	            // extract data from markup and return it  
	            if($(node).hasClass('progress')) {
	            	var n = $(node).find('.translated-bar').attr('title').split(' ')[1];
	            	return n.substring(0, n.length - 1);
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
	            } 
	        }			    	
	    });
    }
/*
    verifyProjectHasArchived: function(project) {
		hasArchived = ($('tr[data-status=archived]',project).length)? 1 : 0;
		$(project).attr('data-hasarchived',hasArchived);
    },

    verifyProjectHasCancelled: function(project) {
		hasCancelled = ($('tr[data-status=cancelled]',project).length)? 1 : 0;
		$(project).attr('data-hascancelled',hasCancelled);
    }
*/
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

