/*
	Project:		Matecat, 2012
	Component:		Loader
*/

Loader = {
	
	components: new Array (
		'common',
		'cat'
	),

	forkComponents: new Array (
	),
		
	libraries: new Array (
        'jquery',
        'jquery-ui-1.8.20.custom.min',
        'jquery.hotkeys',
        'jquery.cookie',
        'jquery-fieldselection.min',
        'diff_match_patch',
        'waypoints',
        'rangy-core',
        'rangy-selectionsaverestore'
	),
	
	include: function(f,p,b) {
		document.write('<script type="text/javascript" src="' + b + p + f + '?build=' + config.build_number + '"></script>');
    },

	includeStyle: function(f,p,b) {
		document.write('<link rel="stylesheet" type="text/css" href="' + b + p + f + '?build=' + config.build_number + '" media="screen" />');
    },

	detect: function(a) {
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
	},

	start: function() {
		var l = this.libraries;
		var c = this.detect('fork')? this.forkComponents : this.components;
		this.basePath = config.basepath+'public/js/';
		for (var i = 0; i < l.length; i++) this.include(l[i] + '.js', 'lib/', this.basePath);
		for (var i = 0; i < c.length; i++) this.include(c[i] + '.js', '', this.basePath);

		if(this.detect('log')) {
			this.include('log.js', 'lib/casmacat/', this.basePath);
		}
		if(this.detect('replay')) {
			this.include('replay.js', 'lib/casmacat/', this.basePath);
		}
	}
}

Loader.start();
