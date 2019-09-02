
let GlossaryUtils = {

    startGlossaryMark: '<mark class="inGlossary">',
    endGlossaryMark: '</mark>',

    /**
     * This function returns an array of strings that are contained already contained in other strings.
     *
     * Example:
     *      input ['canestro', 'cane', 'gatto']
     *      returns [ 'cane' ]
     *
     * @param matches
     * @returns {Array}
     */
    findInclusiveMatches( matches ) {
        var inclusiveMatches = [] ;
        $.each( matches, function ( index ) {
            $.each( matches, function ( ind ) {
                if ( index != ind ) {
                    if ( _.startsWith( matches[index], this ) ) {
                        inclusiveMatches.push( this );
                    }
                }
            } );
        } );
        return inclusiveMatches ;
    },

    /**
     * Mark the glossary matches in text
     * @param text
     * @param matchesObj
     */
    markGlossaryItemsInText ( text, matchesObj, sid ) {

        let cleanString = text;

        let intervals = [];
        let matches = [];
        $.each( matchesObj, function ( index ) {
            if (this[0].raw_segment) {
                matches.push( this[0].raw_segment );
            } else if (this[0].segment) {
                matches.push( this[0].segment );
            }
        } );

        let matchesToRemove = this.findInclusiveMatches( matches ) ;
        matches = matches.sort(function(a, b){
            return b.length - a.length;
        });
        $.each( matches, function ( index, k ) {
            let glossaryTerm_noPlaceholders = UI.decodePlaceholdersToText( k, true );

            if ( matchesToRemove.indexOf( glossaryTerm_noPlaceholders ) != -1 ) return true ;

            let glossaryTerm_escaped = glossaryTerm_noPlaceholders
                .replace( /<\//gi, '<\\/' )
                .replace( /\(/gi, '\\(' )
                .replace( /\)/gi, '\\)' );

            let re = new RegExp( '\b'+ glossaryTerm_escaped.trim() + '\b', "gi" );

            //If source languace is Cyrillic or CJK
            if ( cleanString.match(/[\w\u0430-\u044f]+/ig) || config.isCJK) {
                re = new RegExp( glossaryTerm_escaped.trim(), "gi" );
            }
            let regexInTags = new RegExp( "<[^>]*?("+glossaryTerm_escaped.trim()+")[^>]*?>" , "gi" );

            let glossaryTerm_marked = cleanString.replace( re, '<mark>' + glossaryTerm_noPlaceholders + '</mark>' );

            if ( glossaryTerm_marked.indexOf( '<mark>' ) == -1 ) return;

            //find all glossary matches within tags
            //later we will ignore them
            let matchInTags = regexInTags.exec(cleanString);
            let intervalForTags = [];

            while(matchInTags) {

                //regex start index matches the beginning of the tag.
                //so we add the position of the tag
                let elemIndex = matchInTags.index ;

                // create an object containing the start and end position of the tag where the glossary match appear
                // into the initial string
                let int = {
                    startPos: elemIndex,
                    endPos: elemIndex + matchInTags[0].length
                };

                intervalForTags.push( int );
                matchInTags = regexInTags.exec(cleanString);
            }

            //find all glossary matches
            let match = re.exec(cleanString);
            //Check if glossary term break a marker EX: &lt;g id="3"&gt;
            if ((glossaryTerm_escaped.toLocaleLowerCase() == 'lt' || glossaryTerm_escaped.toLocaleLowerCase() == 'gt') && UI.hasSourceOrTargetTags(segment)) {
                return;
            }
            while(match) {
                //check if this glossary element was found into a tag.
                let matchInTag = intervalForTags.filter(
                    function(elem){
                        return match.index >= elem.startPos && match.index <= elem.endPos;
                    }
                );

                //if found, then this match must be ignored
                if(matchInTag.length > 0) {
                    match = re.exec(cleanString);
                    continue;
                }

                let int = {
                    startPos: match.index,
                    endPos: match.index + match[0].length
                };

                intervals.push( int );
                match = re.exec(cleanString);
            }
        } );

        this.intervalsUnion = [];
        this.checkIntervalsUnions( intervals );
        
        let markLength = this.startGlossaryMark.length + this.endGlossaryMark.length;
        let sourceString = text;
        let sourceReturn = text;
        if ( sourceString ) {
            $.each( this.intervalsUnion, function ( index ) {
                if ( this === GlossaryUtils.lastIntervalUnionAnalysed ) return;
                GlossaryUtils.lastIntervalUnionAnalysed = this;
                let added = markLength * index;
                sourceString = sourceString.splice( this.startPos + added, 0, GlossaryUtils.startGlossaryMark );
                sourceString = sourceString.splice( this.endPos + added + GlossaryUtils.startGlossaryMark.length, 0, GlossaryUtils.endGlossaryMark );
                sourceReturn = sourceString;
            } );
        }
        GlossaryUtils.lastIntervalUnionAnalysed = null;

        return sourceReturn;

    },


    checkIntervalsUnions: function ( intervals ) {
        let smallestIndex;
        GlossaryUtils.endedIntervalAnalysis = false;
        let smallest = GlossaryUtils.smallestInterval( intervals );
        $.each( intervals, function ( indice ) {
            if ( this === smallest ) smallestIndex = indice;
        } );
        let mod = 0;
        $.each( intervals, function ( i ) {
            if ( i !== smallestIndex ) {
                if ( (smallest.startPos <= this.startPos) && (smallest.endPos >= this.startPos) ) { // this item is to be merged to the smallest
                    mod++;
                    intervals.splice( i, 1 );
                    GlossaryUtils.checkIntervalsUnions( intervals );
                }
            }
        } );
        if ( GlossaryUtils.endedIntervalAnalysis ) {
            if ( !intervals.length ) return false;
            GlossaryUtils.checkIntervalsUnions( intervals );
            return false;
        }
        if ( smallest.startPos < 1000000 ) {
            GlossaryUtils.intervalsUnion.push( smallest );
        }

        //throws exception when it is undefined
        ( typeof smallestIndex === 'undefined' ? smallestIndex = 0 : null );
        intervals.splice( smallestIndex, 1 );
        if ( !intervals.length ) return false;
        if ( !mod ) GlossaryUtils.checkIntervalsUnions( intervals );
        GlossaryUtils.endedIntervalAnalysis = true;
        return false;
    },

    smallestInterval: function ( ar ) {
        let smallest = {
            startPos: 1000000,
            endPos: 2000000
        };
        $.each( ar, function () {
            if ( this.startPos < smallest.startPos ) smallest = this;
        } );
        return smallest;
    },

    copyGlossaryItemInEditarea: function ( translation , segment) {
        UI.saveInUndoStack('paste');
        // var range = window.getSelection().getRangeAt( 0 );
        var clonedElem = $( '.editor .editarea').clone();
        var nodeInsert = clonedElem.find( '.focusOut' );
        if ( nodeInsert.length === 0) {
            clonedElem.append(translation);
        } else {
            nodeInsert = nodeInsert.first();
            nodeInsert.before( translation + '<span class="tempCopyGlossaryPlaceholder"></span>' ).remove();
        }
        SegmentActions.highlightEditarea(segment.sid);
        SegmentActions.replaceEditAreaTextContent(segment.sid, null, clonedElem.html());
        setTimeout(function (  ) {

            var tempCopyGlossPlaceholder = UI.editarea.find( '.tempCopyGlossaryPlaceholder' );
            // var node = tempCopyGlossPlaceholder[0];
            // setCursorAfterNode( range, node );
            tempCopyGlossPlaceholder.remove();
        });
    },
    storeGlossaryData: function (sid, matches) {
        matches = _.chain(Object.keys(matches)).map(function (item) {
            return matches[item];
        }).flatten().value();

        // find current segment record
        let record = MateCat.db.segments.by('sid', sid);
        if (record) {
            record.glossary_matches = matches;
            MateCat.db.segments.update(record);
        }
    },
};

module.exports = GlossaryUtils;
