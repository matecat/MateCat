

let TEXT_UTILS =  {


    prepareTextToSend: function (text) {
        var div =  document.createElement('div');
        var $div = $(div);
        $div.html(text);
        $div = UI.transformPlaceholdersHtml($div);

        $div.find('span.space-marker').replaceWith(' ');
        $div.find('span.rangySelectionBoundary').remove();
        $div = UI.encodeTagsWithHtmlAttribute($div);
        return $div.text();
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
        source = source.replace( /&lt;(\/)*(g|x|bx|ex|bpt|ept|ph|it|mrk).*?&gt;/gi, function (match, group1, group2) {
            if ( _.isUndefined(phTagsObject[group2]) ) {
                phTagsObject[group2] = match;
            }
            return '<' + Base64.encode(group2) +'> ';
        });

        target = target.replace( /&lt;(\/)*(g|x|bx|ex|bpt|ept|ph|it|mrk).*?&gt;/gi, function (match, gruop1, group2) {
            if ( _.isUndefined(phTagsObject[group2]) ) {
                phTagsObject[group2] = match;
            }
            return '<'+ Base64.encode(group2) +'> ';
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
module.exports =  TEXT_UTILS;
