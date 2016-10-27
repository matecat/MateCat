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

    function storeGlossaryData( segment, data ) {
        // before we can store this data we need to transpose this into a flat array
        var matches = _.chain( Object.keys( data ) ).map( function(item) {
            return  data[ item ]  ;
        }).flatten().value();

        // find current segment record
        var record = MateCat.db.segments.by('sid', segment.absId );
        record.glossary_matches = matches ;
        MateCat.db.segments.update( record ) ;
    }

    $.extend( UI, {
        deleteGlossaryItem: function ( item ) {
            APP.doRequest( {
                data: {
                    action: 'glossary',
                    exec: 'delete',
                    segment: item.find( '.suggestion_source' ).text(),
                    translation: item.find( '.translation' ).text(),
                    id_job: config.job_id,
                    password: config.password
                },
                error: function () {
                    UI.failedConnection( 0, 'deleteGlossaryItem' );
                }
            } );
            dad = $( item ).prevAll( '.glossary-item' ).first();
            $( item ).remove();
            if ( ($( dad ).next().hasClass( 'glossary-item' )) || (!$( dad ).next().length) ) {
                $( dad ).remove();
                numLabel = $( '.tab-switcher-gl a .number', UI.currentSegment );
                num = parseInt( numLabel.attr( 'data-num' ) ) - 1;
                if ( num ) {
                    $( numLabel ).text( '(' + num + ')' ).attr( 'data-num', num );
                } else {
                    $( numLabel ).text( '' ).attr( 'data-num', 0 );
                }
            }
        },

        getGlossary: function ( segment, entireSegment, next ) {
            var n, txt;
            if ( typeof next != 'undefined' ) {
                var segmentToLookForGlossary ;

                if ( entireSegment ) { // TODO: cryptic, explain this variable
                    if ( next === 0 ) {
                        segmentToLookForGlossary = new UI.Segment( segment ) ;
                    }
                    else if ( next == 1 ) {
                        segmentToLookForGlossary = UI.Segment.find( this.nextSegmentId ) ;
                    }
                    else if ( next == 2 && this.nextUntranslatedSegmentId != 0 && this.nextUntranslatedSegmentId != this.nextSegmentId ) {
                        segmentToLookForGlossary = UI.Segment.find( this.nextUntranslatedSegmentId ) ;
                    }

                } else {
                    // TODO: what to do with this case?
                }

                if ( !segmentToLookForGlossary ) {
                    return ; // for whatever reason, the segment to get the glossay for was not found.
                }

                n = segmentToLookForGlossary.el ;

            } else {
                n = segment;
            }


            $( '.gl-search', n ).addClass( 'loading' );
            if ( config.tms_enabled ) {
                $( '.sub-editor.glossary .overflow .results', n ).empty();
                $( '.sub-editor.glossary .overflow .graysmall.message', n ).empty();
            }
            txt = (entireSegment) ? htmlDecode( $( '.text .source', n ).attr( 'data-original' ) ) : view2rawxliff( $( '.gl-search .search-source', n ).text() );
            if ( (typeof txt == 'undefined') || (txt == '') ) return false;

            APP.doRequest( {
                data: {
                    action: 'glossary',
                    exec: 'get',
                    segment: txt,
                    automatic: entireSegment,
                    translation: null,
                    id_job: config.job_id,
                    password: config.password
                },
                context: [n,
                    next],
                error: function () {
                    UI.failedConnection( 0, 'glossary' );
                },
                success: function ( d ) {

                    if ( !$( n ).hasClass( 'glossary-loaded' ) ) {
                        UI.segmentQA( n );
                    }

                    $( n ).addClass( 'glossary-loaded' );

                    if ( typeof d.errors != 'undefined' && d.errors.length ) {
                        if ( d.errors[0].code == -1 ) {
                            UI.noGlossary = true;
                        }
                    }
                    n = this[0];
                    UI.processLoadedGlossary( d, this );
                    // I store for the current
                    if ( this[1] == 0 ) {
                        UI.cachedGlossaryData = d;
                    }
                    if ( !this[1] && (!UI.body.hasClass( 'searchActive' )) ) UI.markGlossaryItemsInSource( d );
                },
                complete: function () {
                    $( '.gl-search', UI.currentSegment ).removeClass( 'loading' );
                }
            } );
        },

        processLoadedGlossary: function ( d, context ) {
            var segment = new UI.Segment( context[0] ) ;

            storeGlossaryData( segment, d.data.matches ) ;

            // XXX: this variable `next` was intentionally left global, changing to local breaks glossary updates
            next = context[1];

            // TODO: refactor this to avoid timeout check
            if ( (next == 1) || (next == 2) ) { // is a prefetching
                if ( !$( '.footer .submenu', segment.el ).length ) { // footer has not yet been created
                    UI.currentSegment.on('afterFooterCreation', function () {
                        UI.processLoadedGlossary(d,context);
                    });
                }
            }
            var numMatches = Object.size( d.data.matches );
            var existingMatches = $( '.tab-switcher-gl a .number', segment.el ).data('num');
            numMatches = ( existingMatches && existingMatches > 0) ? existingMatches + numMatches : numMatches;
            if ( numMatches ) {
                UI.renderGlossary( d, segment.el );
                $( '.tab-switcher-gl a .number', segment.el ).text( ' (' + numMatches + ')' ).attr( 'data-num', numMatches );
            } else {
                $( '.tab-switcher-gl a .number', segment.el ).text( '' ).attr( 'data-num', 0 );
            }
        },

        markGlossaryItemsInSource: function ( d ) {

            if ( !d || ! Object.size( d.data.matches ) ) return ;

            var container = $('.source', UI.currentSegment ) ;

            root.QaCheckGlossary.enabled() && root.QaCheckGlossary.removeUnusedGlossaryMarks( container );

            var cleanString = container.html();

            var intervals = [];
            var matches = [];
            $.each( d.data.matches, function ( index ) {
                matches.push( this[0].raw_segment );
            } );

            var matchesToRemove = findInclusiveMatches( matches ) ;

            $.each( d.data.matches, function ( k ) {
                var glossaryTerm_noPlaceholders = UI.decodePlaceholdersToText( k, true );

                if ( matchesToRemove.indexOf( glossaryTerm_noPlaceholders ) != -1 ) return true ;

                var glossaryTerm_escaped = glossaryTerm_noPlaceholders
                        .replace( /<\//gi, '<\\/' )
                        .replace( /\(/gi, '\\(' )
                        .replace( /\)/gi, '\\)' );

                var re = new RegExp( glossaryTerm_escaped.trim(), "gi" );
                var regexInTags = new RegExp( "<[^>]*?("+glossaryTerm_escaped.trim()+")[^>]*?>" , "gi" );

                var glossaryTerm_marked = cleanString.replace( re, '<mark>' + glossaryTerm_noPlaceholders + '</mark>' );

                if ( glossaryTerm_marked.indexOf( '<mark>' ) == -1 ) return;

                //find all glossary matches within tags
                //later we will ignore them
                var matchInTags = regexInTags.exec(cleanString);
                var intervalForTags = [];

                while(matchInTags) {
                    //regex start index matches the beginning of the tag.
                    //so we add the position of the glossary entry into the glossary element
                    var elemIndex = matchInTags.index + matchInTags[0].indexOf(matchInTags[1]);

                    // create an object containing the start and end position of the current match
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

                while(match) {
                    //check if this glossary element was found into a tag.
                    var matchInTag = intervalForTags.filter(
                            function(elem){
                                return elem.startPos == match.index;
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
            markLength = UI.startGlossaryMark.length + UI.endGlossaryMark.length;
            sourceString = $( '.editor .source' ).html();

            $.each( UI.intervalsUnion, function ( index ) {
                if ( this === UI.lastIntervalUnionAnalysed ) return;
                UI.lastIntervalUnionAnalysed = this;
                added = markLength * index;
                sourceString = sourceString.splice( this.startPos + added, 0, UI.startGlossaryMark );
                sourceString = sourceString.splice( this.endPos + added + UI.startGlossaryMark.length, 0, UI.endGlossaryMark );
                $( '.editor .source' ).html( sourceString );
            } );
            UI.lastIntervalUnionAnalysed = null;

            $( '.editor .source mark mark' ).each( function () {
                $( this ).replaceWith( $( this ).html() );
            } );

            $(document).trigger('glossarySourceMarked', { segment :  new UI.Segment( UI.currentSegment ) } );

        },
        removeGlossaryMarksFormSource: function () {
            $( '.editor mark.inGlossary' ).each( function () {
                $( this ).replaceWith( $( this ).html() );
            } );
        },
        removeGlossaryMarksFormAllSources: function () {
            $( 'section mark.inGlossary' ).each( function () {
                $( this ).replaceWith( $( this ).html() );
            } );
        },

        checkIntervalsUnions: function ( intervals ) {
            UI.endedIntervalAnalysis = false;
            smallest = UI.smallestInterval( intervals );
            $.each( intervals, function ( indice ) {
                if ( this === smallest ) smallestIndex = indice;
            } );
            mod = 0;
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

        renderGlossary: function ( d, seg ) {
            var segment = seg;
            var segment_id = segment.attr( 'id' );
            $( '.sub-editor.glossary .overflow .message', segment ).remove();
            numRes = 0;

            if ( Object.size( d.data.matches ) ) {
                $.each( d.data.matches, function ( k ) {
                    numRes++;
                    $( '.sub-editor.glossary .overflow .results', segment ).append( '<div class="glossary-item"><span>' + k + '</span></div>' );
                    $.each( this, function ( index ) {
                        if ( (this.segment === '') || (this.translation === '') )
                            return;
                        var disabled = (this.id == '0') ? true : false;
                        cb = this.created_by;


                        var sourceNoteEmpty = (typeof this.source_note == 'undefined' || this.source_note == '');
                        var targetNoteEmpty = (typeof this.target_note == 'undefined' || this.target_note == '');

                        if ( sourceNoteEmpty && targetNoteEmpty ) {
                            this.comment = '';
                        }
                        else if ( !targetNoteEmpty ) {
                            this.comment = this.target_note;
                        }
                        else if ( !sourceNoteEmpty ) {
                            this.comment = this.source_note;
                        }

                        cl_suggestion = UI.getPercentuageClass( this.match );
                        var leftTxt = this.segment;
                        leftTxt = leftTxt.replace( /\#\{/gi, "<mark>" );
                        leftTxt = leftTxt.replace( /\}\#/gi, "</mark>" );
                        var rightTxt = this.translation;
                        rightTxt = rightTxt.replace( /\#\{/gi, "<mark>" );
                        rightTxt = rightTxt.replace( /\}\#/gi, "</mark>" );
                        var commentOriginal = this.comment;
                        if (commentOriginal) {
                            commentOriginal = commentOriginal.replace(/\#\{/gi, "<mark>");
                            commentOriginal = commentOriginal.replace(/\}\#/gi, "</mark>");
                        }
                        var addCommentHtml = '<div class="glossary-add-comment">' +
                            '<a href="#">Add a Comment</a>' +
                            '<div class="input gl-comment" contenteditable="true" ></div>' +
                            '</div>' ;
                        $( '.sub-editor.glossary .overflow .results', segment )
                                .append(
                                '<ul class="graysmall" data-item="' + (index + 1) + '" data-id="' + this.id + '">' +
                                '<li class="sugg-source">' +
                                '<div id="' + segment_id + '-tm-' + this.id + '-edit" class="switch-editing icon-edit" title="Edit"></div>' +
                                ((disabled) ? '' : ' <a id="' + segment_id + '-tm-' + this.id + '-delete" href="#" class="trash" title="delete this row"></a>') +
                                '<span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' +
                                UI.decodePlaceholdersToText( leftTxt, true ) +
                                '</span>' +
                                '</li>' +
                                '<li class="b sugg-target">' +
                                '<span id="' + segment_id + '-tm-' + this.id + '-translation" class="translation" data-original="'+ UI.decodePlaceholdersToText( rightTxt, true ) +'">' +
                                UI.decodePlaceholdersToText( rightTxt, true ) +
                                '</span>' +
                                '</li>' +
                                '<li class="details">' +
                                (( !this.comment || this.comment === '') ? addCommentHtml : '<div class="comment" data-original="'+ UI.decodePlaceholdersToText( commentOriginal, true ) +'">' + UI.decodePlaceholdersToText( commentOriginal, true ) + '</div>') +
                                '<ul class="graysmall-details">' +
                                '<li>' + this.last_update_date + '</li>' +
                                '<li class="graydesc">Source: <span class="bold">' + cb + '</span></li>' +
                                '</ul>' +
                                '</li>' +
                                '</ul>'
                        );
                    } );
                } );
                $( '.sub-editor.glossary .overflow .search-source, .sub-editor.glossary .overflow .search-target, .sub-editor.glossary .overflow .gl-comment', segment ).text( '' );
            } else {
                console.log( 'no matches' );
                $( '.sub-editor.glossary .overflow', segment ).append( '<ul class="graysmall message"><li>Sorry. Can\'t help you this time.</li></ul>' );
            }
        },
        setGlossaryItem: function () {
            var segment = UI.currentSegment.find( '.gl-search .search-source' ).text();
            var translation = UI.currentSegment.find( '.gl-search .search-target' ).text();
            var comment = UI.currentSegment.find( '.gl-search .gl-comment' ).text();
            if(segment.length === 0 ) {
                APP.alert({msg: 'Please insert a glossary term.'});
                return false;
            }
            $( '.gl-search', UI.currentSegment ).addClass( 'setting' );
            APP.doRequest( {
                data: {
                    action: 'glossary',
                    exec: 'set',
                    segment: segment,
                    translation: translation,
                    comment: comment,
                    id_job: config.job_id,
                    password: config.password
                },
                context: [UI.currentSegment,
                    next],
                error: function () {
                    UI.failedConnection( 0, 'glossary' );
                },
                success: function ( d ) {
                    if ( d.data.created_tm_key ) {
                        UI.footerMessage( 'A Private TM Key has been created for this job', this[0] );
                        UI.noGlossary = false;
                    } else {
                        msg = (d.errors.length) ? d.errors[0].message : 'A glossary item has been added';
                        UI.footerMessage( msg, this[0] );
                    }
                    UI.processLoadedGlossary( d, this );
                    UI.markGlossaryItemsInSource(d);
                },
                complete: function () {
                    $( '.gl-search', UI.currentSegment ).removeClass( 'setting' );
                }
            } );
        },
        copyGlossaryItemInEditarea: function ( item ) {
            translation = item.find( '.translation' ).text();
            $( '.editor .editarea .focusOut' ).before( translation + '<span class="tempCopyGlossaryPlaceholder"></span>' ).remove();
            this.lockTags( this.editarea );
            range = window.getSelection().getRangeAt( 0 );
            var tempCopyGlossPlaceholder = $( '.editor .editarea .tempCopyGlossaryPlaceholder' );
            node = tempCopyGlossPlaceholder[0];
            setCursorAfterNode( range, node );
            tempCopyGlossPlaceholder.remove();
            this.highlightEditarea();
        },
        updateGlossary: function (elem$) {
            var self = this;
            if (elem$.find('span.translation').hasClass('editing')) {
                elem$.find('span.translation, .details .comment').removeClass('editing').removeAttr('contenteditable');
                elem$.find('span.translation').html(elem$.find('span.translation').data('original'));
                elem$.find('.details .comment').html(elem$.find('.details .comment').data('original'));
                return;
            }
            var setGlossaryTargetAttributes = (function () {
                var glossaryDom = this;
                var id = glossaryDom.data('id');
                var suggestion = glossaryDom.find('.suggestion_source').text();
                var newTranslation = glossaryDom.find('.translation').text();
                var translation = glossaryDom.find('.translation').data('original');
                var comment = glossaryDom.find('.comment').text();
                self.updateGlossaryItem(id, suggestion, translation, newTranslation, comment);
                this.find('span.translation').data('original', newTranslation);
                this.find(".editing").removeClass('editing').removeAttr('contenteditable');
                this.find('span.translation').off('keypress focusout');
                this.find('.details .comment').off('keypress focusout');
            }).bind(elem$);
            this.editGlossaryItem(elem$.find('.details .comment'), setGlossaryTargetAttributes);
            this.editGlossaryItem(elem$.find('span.translation'), setGlossaryTargetAttributes);
        },
        addGlossaryComment: function (elem$) {
            var self = this;
            var glossaryDom = elem$.closest('.graysmall');
            var id = glossaryDom.data('id');
            var suggestion = glossaryDom.find('.suggestion_source').text();
            var translation = glossaryDom.find('.translation').data('original');
            var comment = elem$.text();
            this.updateGlossaryItem(id, suggestion, translation, null, comment).done(function (data) {
                elem$.closest('.graysmall').prev().remove();
                elem$.closest('.graysmall').remove();
                UI.processLoadedGlossary(data, UI.currentSegment);
            });
        },

        editGlossaryItem: function (elem$, callback) {
            elem$.addClass("editing").attr('contenteditable', true).focus();
            elem$.keypress(function(e) {
                e.stopPropagation();
                if(e.which == 13) {
                    callback.call();
                }
            });
        },

        updateGlossaryItem: function (idItem, segment, translation, newTranslation, comment) {
            var data = {
                action: 'glossary',
                exec: 'update',
                segment: segment,
                translation: translation,
                newsegment: segment,
                newtranslation: newTranslation,
                id_item: idItem,
                comment: comment,
                id_job: config.job_id,
                password: config.password
            };

            return  APP.doRequest( {
                data: data,
                context: [
                    UI.currentSegment,
                    next
                ],
                error: function () {
                    UI.failedConnection( 0, 'glossary' );
                },
                success: function (data) {
                    return data;
                }
            });
        }

    } );

})(jQuery, UI, _, window);
