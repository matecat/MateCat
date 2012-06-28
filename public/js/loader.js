/*
	Project:		Matecat, 2012
	Component:		Loader
*/

Loader = {
	
	components: new Array (
		'cat'
	),
	
	libraries: new Array (
		'jquery',
		'jquery-ui-1.8.20.custom.min',
		'jquery.tabify',
		'jquery.hotkeys',
//		'smoothscroll',
		'jquery.autogrow-textarea',
		'jquery.atooltip',
                'jquery.caret'
	),
	
	include: function(f,p,b) {
		document.write('<script type="text/javascript" src="' + b + p + f + '"></script>');
    },

	includeStyle: function(f,p,b) {
		document.write('<link rel="stylesheet" type="text/css" href="' + b + p + f + '" media="screen" />');
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
		var c = this.components;
		this.basePath = 'public/js/';
		for (var i = 0; i < l.length; i++) this.include(l[i] + '.js', 'lib/', this.basePath);
		for (var i = 0; i < c.length; i++) this.include(c[i] + '.js', '', this.basePath);
	}
}

Loader.start();