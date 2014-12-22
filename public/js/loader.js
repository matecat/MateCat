/*
	Project:		Matecat, 2012
	Component:		Loader
*/

Loader = {
	concatSources: false, // set to true if you want to load all the js source components instead of the whole cat.js
//	components: new Array (
//		'common',
//		'cat'
//	),
	source_components: new Array (
		'ui.core', 
		'ui.init',
		'ui.render',
		'ui.events',
		'ui.contribution',
		'ui.tags',
		'ui.concordance',
		'ui.glossary',
		'ui.search',
		'functions',
		'ui.customization'
	),
    other_components: new Array (
        'tm'
    ),
	forkComponents: new Array (
	),
	libraries: new Array (
		'jquery-1.11.0.min',
//		'jquery',
        'jquery-ui',
		'jquery.hotkeys.min',
//		'jquery-migrate-1.2.1',
		'jquery.cookie',
        'jquery.tablesorter-fork-mottie',
//        'jquery.dataTables.min',
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
		var s = this.source_components;
        var o = this.other_components;
//		var c = this.detect('fork')? this.forkComponents : this.components;
		this.basePath = config.basepath+'public/js/';
		for (var i = 0; i < l.length; i++) this.include(l[i] + '.js', 'lib/', this.basePath);
		this.include('common.js', '', this.basePath);
		if(this.concatSources) {
			for (var i = 0; i < s.length; i++) this.include(s[i] + '.js', 'cat_source/', this.basePath);
            for (var i = 0; i < o.length; i++) this.include(o[i] + '.js', '', this.basePath);
		} else {
			this.include('cat.js', '', this.basePath);
		}
		
//		for (var i = 0; i < c.length; i++) this.include(c[i] + '.js', '', this.basePath);

//		if(this.detect('log')) {
//			this.include('log.js', 'lib/casmacat/', this.basePath);
//		}
//		if(this.detect('replay')) {
//			this.include('replay.js', 'lib/casmacat/', this.basePath);
//		}
	}
}

Loader.start();
