var LTPLACEHOLDER = "##LESSTHAN##";
var GTPLACEHOLDER= "##GREATERTHAN##";
var re_lt = new RegExp(LTPLACEHOLDER,"g"); 
var re_gt = new RegExp(GTPLACEHOLDER,"g"); 
// test jsfiddle http://jsfiddle.net/YgKDu/

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

 function placehold_xliff_tags (segment){
 		console.log ("before placehold " + segment);
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
		console.log ("after placehold " + segment);
	return segment;
 }

 function restore_xliff_tags(segment){

	//console.log("a");
	//console.log(segment);
	segment = segment.replace(re_lt,"<");
	segment = segment.replace(re_gt,">");
	//console.log("b");
	return segment;
}

 function restore_xliff_tags_for_wiew(segment){
	segment = segment.replace(re_lt,"&lt;");
	segment = segment.replace(re_gt,"&gt;");
	return segment;
}

 function view2rawxliff(segment){
	// input : <g id="43">bang & olufsen < 3 </g> <x id="33"/>; --> valore della funzione .text() in cat.js su source, target, source suggestion,target suggestion
	// output : <g> bang &amp; olufsen are > 555 </g> <x/>
	// caso controverso <g id="4" x="&lt; dfsd &gt;">
	//segment=htmlDecode(segment);
	console.log ("decoded" + segment);
	segment = placehold_xliff_tags (segment);
	segment = htmlEncode(segment);
	segment = restore_xliff_tags(segment);

	return segment;
}


 function rawxliff2view(segment){
	// input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
	// output : &lt;g id="43"&gt;bang & < 3 olufsen &lt;/g&gt;;  &lt;x id="33"/&gt;
	segment = placehold_xliff_tags (segment);
	segment = htmlDecode(segment);
	segment = segment.replace(/<(.*?)>/i, "&lt;$1&gt;");
	segment = restore_xliff_tags_for_wiew(segment);		// li rendering avviene via concat o via funzione html()
	return segment;
}

function rawxliff2rawview(segment){
	// input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
	segment = placehold_xliff_tags (segment);
	segment = htmlDecode(segment);
	segment = restore_xliff_tags_for_wiew(segment);
	return segment;
}




