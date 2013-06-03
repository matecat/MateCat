$(document).ready(function(){
//    if(isFiltered());
    fitText($('.breadcrumbs'),$('#pname'),30);

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

    $('td.delete a').click(function(e) {    
        e.preventDefault();
//		var m = confirm('You are cancelling a job! \nDo you want to proceed?');
//		if(m) {
			doRequest({
				data: {
					action:		"cancelJobs",
					res: 		"job",
					id:			$(this).parents('tr').data('jid')
				},
				context: $(this).parents('tr.row'),
				success: function(d){
					if(d.data == 'OK') {
						$(this).addClass('disabled');
						$('.message').html('A job has been cancelled. <a href="#" data-jid="' + $(this).data('jid')+ '">Undo</a>').show();
						setTimeout(function(){
							$('.message').hide();
						},5000);
					}
				}
			});
//		}
    });

    $('a.cancel-project').click(function(e) {    
        e.preventDefault();
		var m = confirm('You are cancelling all the jobs in this project! \nDo you want to proceed?');
		if(m) {
			doRequest({
				data: {
					action:		"cancelJobs",
					res: 		"prj",
					id: 		$(this).parents('.article').data('pid')
				},
				context: $(this).parents('.article'),
				success: function(d){
					if(d.data == 'OK') $(this).find('.tablestats tr.row').addClass('disabled');
				}
			});
		}
    });

    $('a.archive-project').click(function(e) {    
        e.preventDefault();
		var m = confirm('You are archiving all the jobs in this project! \nDo you want to proceed?');
		if(m) {
			doRequest({
				data: {
					action:		"archiveJobs",
					res: 		"prj",
					id: 		$(this).parents('.article').data('pid')
				},
				context: $(this).parents('.article'),
				success: function(d){
					if(d.data == 'OK') $(this).find('.tablestats tr.row').addClass('archived');
				}
			});
		}
    });
    
    $('.buttons .change').click(function(e) {    
        e.preventDefault();
		var m = confirm('You are changing the password for this job. \nThe current link will not work anymore! \nDo you want to proceed?');
		if(m) {
			doRequest({
				data: {
					action:		"changePassword",
					res: 		"job",
					id: 		$(this).parents('tr').data('jid')
				},
				context: $(this).parents('td'),
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

    });
    $('.meter a').click(function(e) {    
        e.preventDefault();
    });


    $('.cancel-project').click(function(e){
        e.preventDefault();
    })

    $('.tablefilter label').click(function(e){
        $(this).parent().find('input').click();
    })

    $('.project-filter.cancelled input').click(function(e){
        var project = $(this).parents('.article');
        project.toggleClass('showDisabled');
    })
/*
    $('.project-filter.archived input').click(function(e){
        var project = $(this).parents('.article');
        project.toggleClass('showArchived');
    })
*/
    $('header .filter').click(function(e) {    
        e.preventDefault();
        $('.searchbox').toggle();
        $('body').toggleClass('filterOpen');
    });


    $('.searchbox #exec-filter').click(function(e) {    
        e.preventDefault();
        var query = '';
        if($('#search-projectname').val() != '') query += 'pn=' + $('#search-projectname').val() + '&';
        if($('#select-source').val() != '') query += 'source=' + $('#select-source').val() + '&';
        if($('#select-target').val() != '') query += 'target=' + $('#select-target').val() + '&';
        if($('#only-completed').is(':checked')) query += 'onlycompleted=1&';
        if($('#show-archived').is(':checked')) query += 'showarchived=1&';
        if($('#show-cancelled').is(':checked')) query += 'showcancelled=1&';

        var bpath = window.location.href.split('?')[0];
        location.href = bpath + '?filter=1&' + query;
        
    });

    $('.searchbox #clear-filter').click(function(e) {    
        e.preventDefault();
        var bpath = window.location.href.split('?')[0];
        location.href = bpath;
    });


});

function doRequest(req) {
    var setup = {
        url:      config.hostpath + '?action=' + req.data.action,
        data:     req.data,
        type:     'POST',
        dataType: 'json'
    };

    // Callbacks
    if (typeof req.success === 'function') setup.success = req.success;
    if (typeof req.complete === 'function') setup.complete = req.complete;
    if (typeof req.context != 'undefined') setup.context = req.context;

    $.ajax(setup);
}

function detect(a) {
	if (window.location.href.indexOf('?') == -1) return;
	var vars = window.location.href.split('?')[1].split('&');
	var vals = new Array();
	for (var i=0; i<vars.length; i++) {
		vals[i] = {name:vars[i].split('=')[0],value:vars[i].split('=')[1]};
	}
	for (var j=0; j<vals.length; j++) {
		if (vals[j].name==a) {return vals[j].value;}
	
	}
	return;
}
/*
function isFiltered() {
	return (window.location.search != '');
}
*/