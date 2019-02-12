/**
 * React Component .

 */
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
class SegmentFooterMultiMatches extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            matches: []
        };
        this.parseMatches = this.parseMatches.bind(this);
    }

    parseMatches(ev, message) {
        if (this.props.segment.original_sid === message.data.id_segment) {
            var matchesProcessed = this.processContributions(message.data.matches);
            if (this._isMounted) {
                this.setState({
                    matches: matchesProcessed
                });
            }
        }
    }

    processContributions(matches) {
        var self = this;
        var matchesProcessed = [];
        // SegmentActions.createFooter(this.props.id_segment);
        $.each(matches, function(index) {
            if ( _.isUndefined(this.segment) || (this.segment === '') || (this.translation === '') ) return false;
            var item = {};
            item.id = this.id;
            item.disabled = (this.id == '0') ? true : false;
            item.cb = this.created_by;
            item.segment = this.segment;
            if ("sentence_confidence" in this &&
                (
                    this.sentence_confidence !== "" &&
                    this.sentence_confidence !== 0 &&
                    this.sentence_confidence != "0" &&
                    this.sentence_confidence !== null &&
                    this.sentence_confidence !== false &&
                    typeof this.sentence_confidence != 'undefined'
                )
            ) {
                item.suggestion_info = "Quality: <b>" + this.sentence_confidence + "</b>";
            } else if (this.match != 'MT') {
                item.suggestion_info = this.last_update_date;
            } else {
                item.suggestion_info = '';
            }

            item.percentClass = UI.getPercentuageClass(this.match);
            item.percentText = this.match;

            // Attention Bug: We are mixing the view mode and the raw data mode.
            // before doing a enanched  view you will need to add a data-original tag
            //
            item.suggestionDecodedHtml = UI.transformTextForLockTags(UI.decodePlaceholdersToText(this.segment));
            item.translationDecodedHtml = UI.transformTextForLockTags(UI.decodePlaceholdersToText( this.translation));
            item.sourceDiff = item.suggestionDecodedHtml;
            if (this.match !== "MT" && parseInt(this.match) > 74) {
                let sourceDecoded = UI.removePhTagsWithEquivTextIntoText( self.props.segment.segment );
                let matchDecoded = UI.removePhTagsWithEquivTextIntoText( this.segment );
                let diff_obj = UI.execDiff( matchDecoded, sourceDecoded );
                item.sourceDiff =  UI.dmp.diff_prettyHtml( diff_obj ) ;
                item.sourceDiff = item.sourceDiff.replace(/&amp;/g, "&");
            }
            if ( !_.isUndefined(this.tm_properties) ) {
                item.tm_properties = this.tm_properties;
            }

            matchesProcessed.push(item);

        });
        return matchesProcessed;
    }

    getMatchInfo(match) {
        return <ul className="graysmall-details">
            <li className={'percent ' + match.percentClass}>
                {match.percentText}
            </li>
            <li>
                {match.suggestion_info}
            </li>
            <li className="graydesc">
                Target:
                <span className="bold"> en-US</span>
            </li>
            <li className="graydesc">
                Source:
                <span className="bold"> {match.cb}</span>
            </li>
        </ul>;
    }

    suggestionDblClick(match, index) {
        UI.editarea.focus();
        UI.disableTPOnSegment();
        setTimeout( () => {
            SegmentActions.replaceEditAreaTextContent(this.props.segment.sid, this.props.segment.fid, match.translationDecodedHtml);
            SegmentActions.highlightEditarea(this.props.id_segment);
        }, 200);
    }

    componentDidMount() {
        this._isMounted = true;
        SegmentActions.getCrossLanguageMatches(this.props.segment.sid);
        $( document ).on( 'sse:contribution', this.parseMatches);
    }

    componentWillUnmount() {
        this._isMounted = false;
        $( document ).off( 'sse:contribution', this.parseMatches);
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        if ( this.state.matches.length > 0 ) {
            var matches = [];
            var self = this;
            this.state.matches.forEach(function (match, index) {
                var item =
                    <ul key={match.id}
                        className="suggestion-item crosslang-item graysmall"
                        data-item={(index + 1)}
                        data-id={match.id}
                        data-original= {match.segment}
                        onDoubleClick = {self.suggestionDblClick.bind(self, match, index+1)}>
                        <li className="sugg-source" >
                            <span
                                id={self.props.id_segment + '-tm-' + match.id + '-source'}
                                className="suggestion_source"
                                dangerouslySetInnerHTML={ self.allowHTML(match.sourceDiff) } >
                            </span>
                        </li>
                        <li className="b sugg-target">
                            <span
                                id={self.props.id_segment + '-tm-' + match.id + '-translation'}
                                className="translation"
                                dangerouslySetInnerHTML={ self.allowHTML(match.translationDecodedHtml) }>
                            </span>
                        </li>
                        {self.getMatchInfo(match)}
                    </ul>;
                matches.push(item);
            });
        }
        return (
            <div
                key={"container_" + this.props.code}
                className={"tab sub-editor "+ this.props.active_class + " " + this.props.tab_class}
                id={"segment-" + this.props.id_segment + " " + this.props.tab_class}>
                <div className="overflow">
                    { !_.isUndefined(matches) && matches.length > 0 ? (
                        matches
                    ): (
                        <span className="loader loader_on"/>
                    )}

                </div>
                <div className="engine-errors"></div>
            </div>
        )
    }
}

export default SegmentFooterMultiMatches;
