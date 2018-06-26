/**
 * React Component .

 */
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
class SegmentFooterTabMatches extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            matches: []
        };
        this.suggestionShortcutLabel = 'CTRL+';
        this.setContributions = this.setContributions.bind(this);
        this.processContributions = this.processContributions.bind(this);
        this.chooseSuggestion = this.chooseSuggestion.bind(this);
    }

    setContributions(sid, matches, fieldTest){
        if ( this.props.id_segment == sid ) {
            var matchesProcessed = this.processContributions(matches, fieldTest);
            if (this._isMounted) {
                this.setState({
                    matches: matchesProcessed
                });
            }
        }
    }

    processContributions(matches, fieldTest) {
        var self = this;
        var matchesProcessed = [];
        // SegmentActions.createFooter(this.props.id_segment);
        $.each(matches, function(index) {
            if ((this.segment === '') || (this.translation === '')) return false;
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


            if (typeof fieldTest == 'undefined') {
                item.percentClass = UI.getPercentuageClass(this.match);
                item.percentText = this.match;
            } else {
                item.quality = parseInt(this.quality);
                item.percentClass = (this.quality > 98)? 'per-green' : (this.quality == 98)? 'per-red' : 'per-gray';
                item.percentText = 'MT';
            }

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

    chooseSuggestion(sid, index) {
        if (this.props.id_segment === sid) {
            this.suggestionDblClick(this.state.matches, index);
        }
    }

    suggestionDblClick(match, index) {
        var self = this;
        var ulDataItem = '.editor .tab.matches ul[data-item=';
        UI.setChosenSuggestion(index);
        UI.editarea.focus();
        UI.disableTPOnSegment();
        setTimeout(function () {
            UI.copySuggestionInEditarea(UI.currentSegment, $(ulDataItem + index + '] li.b .translation').html(),
                $('.editor .editarea'), $(ulDataItem + index + '] ul.graysmall-details .percent').text(), false, false, index, $(ulDataItem + index + '] li.graydesc .bold').text());
            SegmentActions.highlightEditarea(self.props.id_segment);
        }, 0);
    }

    deleteSuggestion(match, index) {
        var source, target;
        var matches = this.state.matches;
        source = htmlDecode( match.segment );
        var ul = $('.suggestion-item[data-id="'+ match.id +'"]');
        if( config.brPlaceholdEnabled ){
            target = UI.postProcessEditarea( ul, '.translation' );
        } else {
            target = $('.translation', ul).text();
        }
        target = view2rawxliff(target);
        source = view2rawxliff(source);
        matches.splice(index, 1);
        UI.setDeleteSuggestion(source, target);
        this.setState({
            matches: matches
        });
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
                Source:
                <span className="bold"> {match.cb}</span>
            </li>
        </ul>;
    }

    componentDidMount() {
        this._isMounted = true;
        SegmentStore.addListener(SegmentConstants.SET_CONTRIBUTIONS, this.setContributions);
        SegmentStore.addListener(SegmentConstants.CHOOSE_CONTRIBUTION, this.chooseSuggestion);
    }

    componentWillUnmount() {
        this._isMounted = false;
        SegmentStore.removeListener(SegmentConstants.SET_CONTRIBUTIONS, this.setContributions);
        SegmentStore.removeListener(SegmentConstants.CHOOSE_CONTRIBUTION, this.chooseSuggestion);
    }

    /**
     * Do not delete, overwritten by plugin
     */
    componentDidUpdate() {}

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        if ( this.state.matches.length > 0 ) {
            var matches = [];
            var self = this;
            this.state.matches.forEach(function (match, index) {
                var trashIcon = (match.disabled) ? '' : <span id={self.props.id_segment +'-tm-' + match.id + '-delete'}
                                                           className="trash"
                                                           title="delete this row"
                                                           onClick = { self.deleteSuggestion.bind(self, match, index)}/>;
                var item =
                    <ul key={match.id}
                        className="suggestion-item graysmall"
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
                            <span className="graysmall-message"> {self.suggestionShortcutLabel + (index + 1)}
                            </span>
                            <span
                                id={self.props.id_segment + '-tm-' + match.id + '-translation'}
                                className="translation"
                                dangerouslySetInnerHTML={ self.allowHTML(match.translationDecodedHtml) }>
                            </span>
                            {trashIcon}
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
                {matches}
            </div>
            <div className="engine-errors"></div>
        </div>
        )
    }
}

export default SegmentFooterTabMatches;
