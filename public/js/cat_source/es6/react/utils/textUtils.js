

let TAGS_UTILS =  {

    /**
     * Called when a Segment string returned by server has to be visualized, it replace placeholders with tags
     * @param str
     * @returns {XML|string}
     */
    decodePlaceholdersToText: function (str) {
        // if(!UI.hiddenTextEnabled) return str;
        let _str = str;
        // if(UI.markSpacesEnabled) {
        //     if(jumpSpacesEncode) {
        //         _str = this.encodeSpacesAsPlaceholders(htmlDecode(_str), true);
        //     }
        // }

        _str = _str.replace( config.lfPlaceholderRegex, '<span class="monad marker softReturn ' + config.lfPlaceholderClass +'" contenteditable="false"><br /></span>' )
            .replace( config.crPlaceholderRegex, '<span class="monad marker softReturn' + config.crPlaceholderClass +'" contenteditable="false"><br /></span>' )
            .replace( config.crlfPlaceholderRegex, '<br class="' + config.crlfPlaceholderClass +'" />' )
            .replace( config.tabPlaceholderRegex, '<span class="tab-marker monad marker ' + config.tabPlaceholderClass +'" contenteditable="false">&#8677;</span>' )
            .replace( config.nbspPlaceholderRegex, '<span class="nbsp-marker monad marker ' + config.nbspPlaceholderClass +'" contenteditable="false">&nbsp;</span>' )
            .replace(/(<\/span\>)$/gi, "</span><br class=\"end\">"); // For rangy cursor after a monad marker

        return _str;
    },

    transformTextForLockTags: function ( tx ) {
        var brTx1 = "<_plh_ contenteditable=\"false\" class=\"locked style-tag \">$1</_plh_>";
        var brTx2 =  "<span contenteditable=\"false\" class=\"locked style-tag\">$1</span>";

        tx = tx.replace( /&amp;/gi, "&" )
            .replace( /<span/gi, "<_plh_" )
            .replace( /<\/span/gi, "</_plh_" )
            .replace( /&lt;/gi, "<" )
            .replace( /(<(ph.*?)\s*?\/&gt;)/gi, brTx1 )
            .replace( /(<(g|x|bx|ex|bpt|ept|ph.*?|it|mrk)\sid[^<“]*?&gt;)/gi, brTx1 )
            .replace( /(<(ph.*?)\sid[^<“]*?\/>)/gi, brTx1 )
            .replace( /</gi, "&lt;" )
            .replace( /\&lt;_plh_/gi, "<span" )
            .replace( /\&lt;\/_plh_/gi, "</span" )
            .replace( /\&lt;lxqwarning/gi, "<lxqwarning" )
            .replace( /\&lt;\/lxqwarning/gi, "</lxqwarning" )
            .replace( /\&lt;div\>/gi, "<div>" )
            .replace( /\&lt;\/div\>/gi, "</div>" )
            .replace( /\&lt;br\>/gi, "<br />" )
            .replace( /\&lt;br \/>/gi, "<br />" )
            .replace( /\&lt;mark /gi, "<mark " )
            .replace( /\&lt;\/mark/gi, "</mark" )
            .replace( /\&lt;ins /gi, "<ins " ) // For translation conflicts tab
            .replace( /\&lt;\/ins/gi, "</ins" ) // For translation conflicts tab
            .replace( /\&lt;del /gi, "<del " ) // For translation conflicts tab
            .replace( /\&lt;\/del/gi, "</del" ) // For translation conflicts tab
            .replace( /\&lt;br class=["\'](.*?)["\'][\s]*[\/]*(\&gt;|\>)/gi, '<br class="$1" />' )
            .replace( /(&lt;\s*\/\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*&gt;)/gi, brTx2 );


        tx = tx.replace( /(<span contenteditable="false" class="[^"]*"\>)(:?<span contenteditable="false" class="[^"]*"\>)(.*?)(<\/span\>){2}/gi, "$1$3</span>" );
        tx = tx.replace( /(<\/span\>)$(\s){0,}/gi, "</span> " );
        tx = this.transformTagsWithHtmlAttribute(tx);
        // tx = tx.replace( /(<\/span\>\s)$/gi, "</span><br class=\"end\">" );  // This to show the cursor after the last tag, moved to editarea component
        return tx;
    },
    /**
     * Used to transform special ph tags that may contain html within the equiv-text attribute.
     * Example: &lt;ph id="2" equiv-text="base64:Jmx0O3NwYW4gY2xhc3M9JnF1b3Q7c3BhbmNsYXNzJnF1b3Q7IGlkPSZxdW90OzEwMDAmcXVvdDsgJmd0Ow=="/&gt;
     * The attribute is encoded in base64
     * @param tx
     * @returns {*}
     */
    transformTagsWithHtmlAttribute: function (tx) {
        try {
            let base64Array=[];
            let phIDs =[];
            tx = tx.replace( /&quot;/gi, '"' );

            tx = tx.replace( /&lt;ph.*?id="(.*?)"/gi, function (match, text) {
                phIDs.push(text);
                return match;
            });

            tx = tx.replace( /&lt;ph.*?equiv-text="base64:.*?"(.*?\/&gt;)/gi, function (match, text) {
                return match.replace(text, "<span contenteditable='false' class='locked tag-html-container-close' contenteditable='false'>\"" + text + "</span>");
            });
            tx = tx.replace( /base64:(.*?)"/gi , function (match, text) {
                base64Array.push(text);
                let id = phIDs.shift();
                return "<span contenteditable='false' class='locked inside-attribute' contenteditable='false' data-original='base64:" + text+ "'><a>("+ id + ")</a>" + Base64.decode(text) + "</span>";
            });
            tx = tx.replace( /(&lt;ph.*?equiv-text=")/gi, function (match, text) {
                let base = base64Array.shift();
                return "<span contenteditable='false' class='locked tag-html-container-open' contenteditable='false'>" + text + "base64:" + base + "</span>";
            });
            // delete(base64Array);
            return tx;
        } catch (e) {
            console.error("Error parsing tag ph in transformTagsWithHtmlAttribute function");
        }


    },
    getDiffHtml: function(source, target) {
        let dmp = new diff_match_patch();
        /*
        There are problems when you delete or add a tag next to another, the algorithm that makes the diff fails to recognize the tags,
        they come out of the function broken.
        Before passing them to the function that makes the diff we replace all the tags with placeholders and we keep a map of the tags
        indexed with the id of the tags.
         */
        var phTagsObject = {};
        var diff;
        source = source.replace( /&lt;(g|x|bx|ex|bpt|ept|ph|it|mrk).*?id="(.*?)".*?\/&gt;/gi, function (match, group1, group2) {
            if ( group2 && _.isUndefined(phTagsObject[group2]) ) {
                phTagsObject[group2] = match;
            }
            if ( !_.isUndefined(group2) ) {
                return '<'+ Base64.encode(group2) +'> ';
            } else {
                return match;
            }
        });

        target = target.replace( /&lt;(g|x|bx|ex|bpt|ept|ph|it|mrk).*?id="(.*?)".*?\/&gt;/gi, function (match, gruop1, group2) {
            if ( group2 && _.isUndefined(phTagsObject[group2]) ) {
                phTagsObject[group2] = match;
            }
            if ( !_.isUndefined(group2) ) {
                return '<'+ Base64.encode(group2) +'> ';
            } else {
                return match;
            }
        });

        diff   = dmp.diff_main(
            this.replacePlaceholder(source.replace(/&nbsp; /g, '  ').replace(/&nbsp;/g, '')),
            this.replacePlaceholder(target.replace(/&nbsp; /g, '  ').replace(/&nbsp;/g, ''))
        );

        dmp.diff_cleanupSemantic( diff ) ;

        /*
        Before adding spans to identify added or subtracted portions we need to check and fix broken tags
         */
        diff = this.setUnclosedTagsInDiff(diff);
        var diffTxt = '';
        var self = this;
        $.each(diff, function (index, text) {
            text[1] = text[1].replace( /<(.*?)>/gi, function (match, text) {
                try {
                    var decodedText = Base64.decode( text );
                    if ( !_.isUndefined( phTagsObject[ decodedText ] ) ) {
                        return phTagsObject[ decodedText ];
                    }
                    return match;
                } catch ( e ) {
                    return match;
                }

            });
            var rootElem;
            var newElem;
            if ( self.htmlDecode(text[1]) === " " ) {
                text[1] = "&nbsp;";
            }

            if(text[0] === -1) {
                rootElem = $( document.createElement( 'div' ) );
                newElem = $.parseHTML( '<span class="deleted"/>' );
                $( newElem ).text( self.htmlDecode(text[1]) );
                rootElem.append( newElem );
                diffTxt += $( rootElem ).html();
            } else if(text[0] === 1) {
                rootElem = $( document.createElement( 'div' ) );
                newElem = $.parseHTML( '<span class="added"/>' );
                $( newElem ).text( self.htmlDecode(text[1]) );
                rootElem.append( newElem );
                diffTxt += $( rootElem ).html();
            } else {
                diffTxt += text[1];
            }
        });

        return this.restorePlaceholders(diffTxt) ;
    },

    /**
     *This function takes in the array that exits the UI.dmp.diff_main function and parses the array elements to see if they contain broken tags.
     * The array is of the type:
     *
     * [0, "text"],
     * [-1, "deletedText"]
     * [1, "addedText"]
     *
     * For each element of the array in the first position there is 0, 1, -1 which indicate if the text is equal, added, removed
     */
    setUnclosedTagsInDiff: function(array) {

        /*
        Function to understand if an element contains broken tags
         */
        var thereAreIncompletedTagsInDiff = function ( text ) {
            return (text.indexOf('<') > -1 || text.indexOf('>') > -1) &&
                ( (text.split("<").length - 1) !== (text.split(">").length - 1) ||  text.indexOf('<') >= text.indexOf('>'))
        };
        /*
        Function to understand if an element contains broken tags where the opening part is missing
         */
        var thereAreCloseTags = function ( text ) {
            return thereAreIncompletedTagsInDiff(text) && ( ( (item[1].split("<").length - 1) < (item[1].split(">").length - 1) ) ||
                ( item[1].indexOf('>') > -1 && item[1].indexOf('>') < item[1].indexOf('<')))
        };
        /*
        Function to understand if an element contains broken tags where the closing part is missing
         */
        var thereAreOpenTags = function ( text ) {
            return thereAreIncompletedTagsInDiff(text) && ( ( (item[1].split("<").length - 1) < (item[1].split(">").length - 1) ) ||
                ( item[1].indexOf('<') > -1 && item[1].indexOf('>') > item[1].indexOf('<')))
        };
        var i;
        var indexTemp;
        var adding = false;
        var tagToMoveOpen = "";
        var tagToMoveClose = "";
        for (i = 0; i < array.length; i++) {
            var item = array[i];
            var thereAreUnclosedTags =  thereAreIncompletedTagsInDiff(item[1]);
            if ( !adding && item[0] === 0) {
                if (thereAreUnclosedTags) {
                    tagToMoveOpen = item[1].substr(item[1].lastIndexOf('<'), item[1].length + 1);
                    array[i][1] = item[1].substr(0, item[1].lastIndexOf('<'));
                    indexTemp = i;
                    adding = true;
                }
            } else if (adding && item[0] === 0){
                if ( thereAreUnclosedTags && thereAreCloseTags(item[1]) ) {
                    tagToMoveClose = item[1].substr( 0, item[1].indexOf( '>' ) + 1 );
                    tagToMoveOpen = "";
                    array[i][1] = item[1].substr( item[1].indexOf( '>' ) + 1, item[1].length + 1 );
                    i = indexTemp;
                } else{
                    if ( thereAreUnclosedTags && thereAreOpenTags(item[1]) ) {
                        i = i-1; //There are more unclosed tags, restart from here
                    }
                    indexTemp = 0;
                    adding = false;
                    tagToMoveOpen = "";
                    tagToMoveClose = "";

                }
            } else if (adding) {
                array[i][1] = tagToMoveOpen + item[1] + tagToMoveClose;
            }
        }
        return array;
    },

    transformDiffArrayToHtml: function(diff) {
        let diffTxt = '';
        let self = this;
        $.each(diff, function (index) {
            if(this[0] == -1) {
                let rootElem = $( document.createElement( 'div' ) );
                let newElem = $.parseHTML( '<span class="deleted"/>' );
                $( newElem ).text( self.htmlDecode(this[1]) );
                rootElem.append( newElem );
                diffTxt += $( rootElem ).html();
            } else if(this[0] == 1) {
                let rootElem = $( document.createElement( 'div' ) );
                let newElem = $.parseHTML( '<span class="added"/>' );
                $( newElem ).text( self.htmlDecode(this[1]) );
                rootElem.append( newElem );
                diffTxt += $( rootElem ).html();
            } else {
                diffTxt += this[1];
            }
        });
        return this.restorePlaceholders(diffTxt);
    },
    replacePlaceholder: function(string) {
        return  string
            .replace( config.lfPlaceholderRegex, "softReturnMonad")
            .replace( config.crPlaceholderRegex, "crPlaceholder" )
            .replace( config.crlfPlaceholderRegex, "brMarker" )
            .replace( config.tabPlaceholderRegex, "tabMarkerMonad" )
            .replace( config.nbspPlaceholderRegex, "nbspPlMark" )
    },

    restorePlaceholders: function(string) {
        return string
            .replace(/softReturnMonad/g , config.lfPlaceholder)
            .replace(/crPlaceholder/g,  config.crPlaceholder)
            .replace(/brMarker/g,  config.crlfPlaceholder )
            .replace(/tabMarkerMonad/g, config.tabPlaceholder)
            .replace(/nbspPlMark/g, config.nbspPlaceholder)
    },
    htmlEncode: function(value) {
        if (value) {
            return $('<div />').text(value).html();
        } else {
            return '';
        }
    },
    htmlDecode: function(value) {
        if (value) {
            return $('<div />').html(value).text();
        } else {
            return '';
        }
    }
};

export default TAGS_UTILS ;