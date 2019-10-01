/*
 Component: ui.glossary
 */

if (true)
(function($, UI, _, root, undefined) {

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
    function findInclusiveMatches( matches ) {
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
    }

    $.extend( UI, {
        /**
         * To retrieve the glossary matches in the segment and add them to the footer
         * @param segment
         * @param entireSegment
         * @param next
         * @returns {boolean}
         */
        cachedGlossaryData: {},
        glossaryMatchesErrors: ['g', 'G'],
        getGlossary: function ( segment, entireSegment, next ) {
            var txt;
            if ( !_.isUndefined(next) ) {
                var segmentToLookForGlossary ;
                if ( next === 0 ) {
                    segmentToLookForGlossary = new UI.Segment( segment ) ;
                }
                else if ( next == 1 ) {
                    segmentToLookForGlossary = UI.Segment.find( this.nextSegmentId ) ;
                }
                else if ( next == 2 && this.nextUntranslatedSegmentId != 0 && this.nextUntranslatedSegmentId != this.nextSegmentId ) {
                    segmentToLookForGlossary = UI.Segment.find( this.nextUntranslatedSegmentId ) ;
                }

                if ( !segmentToLookForGlossary ) {
                    return ; // for whatever reason, the segment to get the glossay for was not found.
                }

                segment = segmentToLookForGlossary.el ;
            }

            //remove unwanted dom elements and get the text content
            var tmpSeg = $( '.text .source', segment ).clone();
            $( 'span', tmpSeg ).remove();
            txt = tmpSeg.text() ;

            if ( _.isUndefined(txt) || (txt === '') ) return false;
            setTimeout(function (  ) {
                SegmentActions.renderSegmentGlossary(UI.getSegmentId(segment), txt);
            });
        },

        cacheGlossaryData: function ( matches, sid ) {

            if ( UI.currentSegmentId == sid && matches) {
                UI.cachedGlossaryData.sid = matches;
            }
        },

        /**
         * Mark the glossary matches in the source
         * @param segmentToMark
         * @param matchesObj
         */
        markGlossaryItemsInSource: function (segmentToMark, matchesObj ) {

            if ( ! Object.size( matchesObj ) ) return ;

            var segment = (segmentToMark);
            var container = $('.source', segment ) ;

            root.QaCheckGlossary.enabled() && root.QaCheckGlossary.removeUnusedGlossaryMarks( container );

            var cleanString = container.html();

            var intervals = [];
            var matches = [];
            $.each( matchesObj, function ( index ) {
                if (this[0].raw_segment) {
                    matches.push( this[0].raw_segment );
                } else if (this[0].segment) {
                    matches.push( this[0].segment );
                }
            } );

            var matchesToRemove = findInclusiveMatches( matches ) ;
            matches = matches.sort(function(a, b){
                return b.length - a.length;
            });
            $.each( matches, function ( index, k ) {
                var glossaryTerm_noPlaceholders = UI.decodePlaceholdersToText( k, true );
                if ( UI.glossaryMatchesErrors.indexOf(glossaryTerm_noPlaceholders) !== -1 ) return;
                if ( matchesToRemove.indexOf( glossaryTerm_noPlaceholders ) != -1 ) return true ;

                var glossaryTerm_escaped = glossaryTerm_noPlaceholders
                        .replace( /<\//gi, '<\\/' )
                        .replace( /\(/gi, '\\(' )
                        .replace( /\)/gi, '\\)' );

                var re = new RegExp( '\b'+ glossaryTerm_escaped.trim() + '\b', "gi" );

                //If source languace is Cyrillic or CJK
                if ( cleanString.match(/[\w\u0430-\u044f]+/ig) || config.isCJK) {
                    re = new RegExp( glossaryTerm_escaped.trim(), "gi" );
                }
                var regexInTags = new RegExp( "<[^>]*?("+glossaryTerm_escaped.trim()+")[^>]*?>" , "gi" );

                var glossaryTerm_marked = cleanString.replace( re, '<mark>' + glossaryTerm_noPlaceholders + '</mark>' );

                if ( glossaryTerm_marked.indexOf( '<mark>' ) == -1 ) return;

                //find all glossary matches within tags
                //later we will ignore them
                var matchInTags = regexInTags.exec(cleanString);
                var intervalForTags = [];

                while(matchInTags) {

                    //regex start index matches the beginning of the tag.
                    //so we add the position of the tag
                    var elemIndex = matchInTags.index ;

                    // create an object containing the start and end position of the tag where the glossary match appear
                    // into the initial string
                    int = {
                        startPos: elemIndex,
                        endPos: elemIndex + matchInTags[0].length
                    };

                    intervalForTags.push( int );
                    matchInTags = regexInTags.exec(cleanString);
                }

                //find all glossary matches
                var match = re.exec(cleanString);
                //Check if glossary term break a marker EX: &lt;g id="3"&gt;
                if ((glossaryTerm_escaped.toLocaleLowerCase() == 'lt' || glossaryTerm_escaped.toLocaleLowerCase() == 'gt') && UI.hasSourceOrTargetTags(segment)) {
                    return;
                }
                while(match) {
                    //check if this glossary element was found into a tag.
                    var matchInTag = intervalForTags.filter(
                            function(elem){
                                return match.index >= elem.startPos && match.index <= elem.endPos;
                            }
                    );

                    //if found, then this match must be ignored
                    if(matchInTag.length > 0) {
                        match = re.exec(cleanString);
                        continue;
                    }

                    int = {
                        startPos: match.index,
                        endPos: match.index + match[0].length
                    };

                    intervals.push( int );
                    match = re.exec(cleanString);
                }
            } );

            UI.intervalsUnion = [];
            UI.checkIntervalsUnions( intervals );
            UI.startGlossaryMark = '<mark class="inGlossary">';
            UI.endGlossaryMark = '</mark>';
            var markLength = UI.startGlossaryMark.length + UI.endGlossaryMark.length;
            var sourceString = container.html();
            if ( sourceString ) {
                $.each( UI.intervalsUnion, function ( index ) {
                    if ( this === UI.lastIntervalUnionAnalysed ) return;
                    UI.lastIntervalUnionAnalysed = this;
                    added = markLength * index;
                    sourceString = sourceString.splice( this.startPos + added, 0, UI.startGlossaryMark );
                    sourceString = sourceString.splice( this.endPos + added + UI.startGlossaryMark.length, 0, UI.endGlossaryMark );
                    SegmentActions.replaceSourceText(UI.getSegmentId(segment) , UI.getSegmentFileId(segment), sourceString);
                } );
            }
            UI.lastIntervalUnionAnalysed = null;
            setTimeout(function () {
                $( '.editor .source mark mark' ).each( function () {
                    $( this ).replaceWith( $( this ).html() );
                } );
            }, 100);
            $(document).trigger('glossarySourceMarked', { segment :  new UI.Segment( segment ) } );

        },
        removeGlossaryMarksFormSource: function (segment) {
            segment.find( '.source mark.inGlossary' ).each( function () {
                $( this ).replaceWith( $( this ).html() );
            } );
            SegmentActions.replaceSourceText(UI.getSegmentId(segment) , UI.getSegmentFileId(segment), segment.find('.source').html());

        },
        removeGlossaryMarksFormAllSources: function () {
            //Todo: Find a way to communicate to all segments that they have to remove glossary tags
            $( 'section mark.inGlossary' ).each( function () {
                $( this ).replaceWith( $( this ).html() );
            } );
        },

        checkIntervalsUnions: function ( intervals ) {
            UI.endedIntervalAnalysis = false;
            var smallest = UI.smallestInterval( intervals );
            $.each( intervals, function ( indice ) {
                if ( this === smallest ) smallestIndex = indice;
            } );
            var mod = 0;
            $.each( intervals, function ( i ) {
                if ( i != smallestIndex ) {
                    if ( (smallest.startPos <= this.startPos) && (smallest.endPos >= this.startPos) ) { // this item is to be merged to the smallest
                        mod++;
                        intervals.splice( i, 1 );
                        UI.checkIntervalsUnions( intervals );
                    }
                }
            } );
            if ( UI.endedIntervalAnalysis ) {
                if ( !intervals.length ) return false;
                UI.checkIntervalsUnions( intervals );
                return false;
            }
            if ( smallest.startPos < 1000000 ) {
                UI.intervalsUnion.push( smallest );
            }

            //throws exception when it is undefined
            ( typeof smallestIndex == 'undefined' ? smallestIndex = 0 : null );
            intervals.splice( smallestIndex, 1 );
            if ( !intervals.length ) return false;
            if ( !mod ) UI.checkIntervalsUnions( intervals );
            UI.endedIntervalAnalysis = true;
            return false;
        },

        smallestInterval: function ( ar ) {
            smallest = {
                startPos: 1000000,
                endPos: 2000000
            };
            $.each( ar, function () {
                if ( this.startPos < smallest.startPos ) smallest = this;
            } );
            return smallest;
        },

        copyGlossaryItemInEditarea: function ( translation ) {
            UI.saveInUndoStack('paste');
            var range = window.getSelection().getRangeAt( 0 );
            var clonedElem = $( '.editor .editarea').clone();
            var nodeInsert = clonedElem.find( '.focusOut' );
            if ( nodeInsert.length === 0) {
                clonedElem.append(translation);
            } else {
                nodeInsert = nodeInsert.first();
                nodeInsert.before( translation + '<span class="tempCopyGlossaryPlaceholder"></span>' ).remove();
            }
            SegmentActions.highlightEditarea(UI.currentSegment.find(".editarea").data("sid"));
            SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(this.editarea), UI.getSegmentFileId(this.editarea), clonedElem.html());
            setTimeout(function (  ) {

                var tempCopyGlossPlaceholder = UI.editarea.find( '.tempCopyGlossaryPlaceholder' );
                var node = tempCopyGlossPlaceholder[0];
                setCursorAfterNode( range, node );
                tempCopyGlossPlaceholder.remove();
            });
        },
        openSegmentGlossaryTab: function ( $segment ) {
            $segment.find('.tab-switcher-gl').click();
        }

    } );

})(jQuery, UI, _, window);
