/**
 * React Component .

 */
var React = require( 'react' );
var SegmentConstants = require( '../../constants/SegmentConstants' );
var SegmentStore = require( '../../stores/SegmentStore' );

class SegmentFooterTabConcordance extends React.Component {

    constructor( props ) {
        super( props );
        this.state = {
            noResults: false,
            results: [],
            source: '',
            target: '',
            extended: false
        }
    }

    componentWillUnmount() {
        SegmentStore.removeListener( SegmentConstants.FIND_CONCORDANCE, this.findConcordance );
    }

    componentDidMount() {
        SegmentStore.addListener( SegmentConstants.FIND_CONCORDANCE, this.findConcordance.bind( this ) );
    }

    allowHTML( string ) {
        return {__html: string};
    }

    findConcordance(sid, data) {
        var self =  this;
        if (this.props.id_segment == sid) {
            if ( data.inTarget === 1) {
                this.setState( {
                    source: '',
                    target: data.text,
                    results: []
                } );
            } else {
                this.setState( {
                    source: data.text,
                    target: '',
                    results: []
                } );
            }
            this.searchSubmit();
        }
    }

    sourceChange( event ) {
        this.setState( {
            source: event.target.value,
            target: '',
            results: []
        } );

    }

    targetChange( event ) {
        this.setState( {
            source: '',
            target: event.target.value,
            results: []
        } );

    }

    getConcordance( query, type ) {
        //type 0 = source, 1 = target
        $( '.cc-search', UI.currentSegment ).addClass( 'loading' );
        $( '.sub-editor.concordances .overflow .results', this.currentSegment ).empty();
        let txt = view2rawxliff( query );
        let self = this;
        APP.doRequest( {
            data: {
                action: 'getContribution',
                is_concordance: 1,
                from_target: type,
                id_segment: UI.currentSegmentId,
                text: txt,
                id_job: config.job_id,
                num_results: UI.numMatchesResults,
                id_translator: config.id_translator,
                password: config.password
            },
            error: function () {
                UI.failedConnection( this, 'getConcordance' );
            },
            success: function ( d ) {
                self.renderConcordances( d, type );
            }
        } );
    }

    allowHTML( string ) {
        return {__html: string};
    }

    renderConcordances( d, in_target ) {
        let self = this;
        var segment = UI.currentSegment;
        var segment_id = UI.currentSegmentId;
        $( '.sub-editor.concordances .overflow .results', segment ).empty();
        $( '.sub-editor.concordances .overflow .message', segment ).remove();
        let array = [];
        if ( d.data.matches.length ) {
            $.each( d.data.matches, function ( index ) {
                if ( (this.segment === '') || (this.translation === '') )
                    return;
                var prime = (index < UI.numDisplayContributionMatches) ? ' prime' : '';

                // var disabled = (this.id == '0') ? true : false;
                var cb = this.created_by;
                var cl_suggestion = UI.getPercentuageClass( this.match );

                var leftTxt = (in_target) ? this.translation : this.segment;
                leftTxt = UI.decodePlaceholdersToText( leftTxt );
                leftTxt = leftTxt.replace( /\#\{/gi, "<mark>" );
                leftTxt = leftTxt.replace( /\}\#/gi, "</mark>" );

                var rightTxt = (in_target) ? this.segment : this.translation;
                rightTxt = UI.decodePlaceholdersToText( rightTxt );
                rightTxt = rightTxt.replace( /\#\{/gi, "<mark>" );
                rightTxt = rightTxt.replace( /\}\#/gi, "</mark>" );

                var element = <ul key={index} className={["graysmall",
                    prime].join( ' ' )} data-item={index + 1} data-id={this.id}>
                    <li className={"sugg-source"}>
                        <span id={segment_id + '-tm-' + this.id + '-source'} className={"suggestion_source"} dangerouslySetInnerHTML={self.allowHTML( leftTxt )}/>
                    </li>
                    <li className={"b sugg-target"}>
                        <span id={segment_id + "-tm-" + this.id + "-translation"} className={"translation"} dangerouslySetInnerHTML={self.allowHTML( rightTxt )}/>
                        {/*{(disabled) ? (null) : (<span id={segment_id + "-tm-" + this.id + "-delete"} className="trash" title="delete this row"></span>)}*/}

                    </li>
                    <ul className={"graysmall-details"}>
                        <li>{this.last_update_date}</li>
                        <li className={"graydesc"}>Source: <span className={"bold"}> {cb}</span></li>
                    </ul>
                </ul>;
                array.push( element );
            } );

            this.setState( {
                results: array,
                extended: false,
                noResults: false
            } )


        } else {
            this.setState( {
                noResults: true,
                extended: false,
                results: []
            } )
        }

        $( '.cc-search', this.currentSegment ).removeClass( 'loading' );
    }


    searchSubmit( event ) {
        (event) ? event.preventDefault() : '';
        if ( this.state.source.length > 0 ) {
            this.getConcordance( this.state.source, 0 );

        } else if ( this.state.target.length > 0 ) {
            this.getConcordance( this.state.target, 1 );
        }
    }

    toggleExtendend() {
        if ( this.state.extended ) {
            $( '.sub-editor.concordances' ).removeClass( 'extended' );
            $( '.sub-editor.concordances .overflow' ).removeAttr( 'style' );
            UI.custom.extended_concordance = false;
            UI.saveCustomization();
        } else {
            $( '.sub-editor.concordances .overflow' ).css( 'height', $( '.sub-editor.concordances' ).height() + 'px' );
            $( '.sub-editor.concordances' ).addClass( 'extended' );
            UI.custom.extended_concordance = true;
            UI.saveCustomization();
        }
        this.setState( {
            extended: !this.state.extended
        } );
    }


    render() {
        let html = '',
            results = '',
            extended = '';
        extended = <a className={"more"} onClick={this.toggleExtendend.bind( this )}>{this.state.extended ? 'Fewer' : 'More'}</a>;

        if ( config.tms_enabled ) {
            html = <div className="cc-search">
                <form onSubmit={this.searchSubmit.bind( this )}>
                    <input type="text" className="input search-source" onChange={this.sourceChange.bind( this )}
                           value={this.state.source}/>
                    <input type="text" className="input search-target" onChange={this.targetChange.bind( this )}
                           value={this.state.target}/>
                    <input type="submit" value="" style={{display: "none"}}/>
                </form>
            </div>;
        } else {
            html = <ul className={"graysmall message"}>
                <li>Concordance is not available when the TM feature is disabled</li>
            </ul>;
        }

        if ( this.state.results.length > 0 && !this.state.noResults ) {
            results = this.state.results;
        }
        if ( this.state.noResults ) {
            results = <ul className={"graysmall message"}>
                <li>Can't find any matches. Check the language combination.</li>
            </ul>
        }

        return (

            <div key={"container_" + this.props.code} className={"tab sub-editor " + this.props.active_class + " " + this.props.tab_class }
                 id={"segment-" + this.props.id_segment + " " + this.props.tab_class}>
                <div className="overflow">
                    {html}
                    <div className="results">
                        {results}
                    </div>
                </div>
                <br className="clear"/>
                {this.state.results.length > 0 ? extended : null}
            </div>
        )
    }
}

export default SegmentFooterTabConcordance;