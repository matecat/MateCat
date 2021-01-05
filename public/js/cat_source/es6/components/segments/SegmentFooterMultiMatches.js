/**
 * React Component .

 */
import React  from 'react';
import Immutable  from 'immutable';
import TagUtils from "../../utils/tagUtils"
import TextUtils from "../../utils/textUtils"
import TranslationMatches from "./utils/translationMatches";

class SegmentFooterMultiMatches extends React.Component {

    constructor(props) {
        super(props);
        // this.state = {
        //     matches: (this.props.segment.cl_contributions) ? this.processContributions(this.props.segment.cl_contributions) : undefined
        // };
        this.parseMatches = this.parseMatches.bind(this);
    }

    parseMatches(sid, fid, matches) {
        if ( this.props.segment.sid === sid ) {
            var matchesProcessed = this.processContributions(matches);
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
            if ( _.isUndefined(this.segment) || (this.segment === '') || (this.translation === '') ) return true;
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

            item.percentClass = TranslationMatches.getPercentuageClass(this.match);
            item.percentText = this.match;

            // Attention Bug: We are mixing the view mode and the raw data mode.
            // before doing a enhanced  view you will need to add a data-original tag
            //
            item.suggestionDecodedHtml = TagUtils.matchTag(TagUtils.decodeHtmlInTag(TagUtils.decodePlaceholdersToTextSimple(this.segment)));
            item.translationDecodedHtml = TagUtils.matchTag(TagUtils.decodeHtmlInTag(TagUtils.decodePlaceholdersToTextSimple( this.translation)));
            item.sourceDiff = item.suggestionDecodedHtml;

            if (this.match !== "MT" && parseInt(this.match) > 74 && parseInt(this.match) < 100) {
                // Clean text without tag and create tagsMap to replace tag after exec_diff
                const {text: matchDecoded, tagsMap: matchTagsMap} = TagUtils.cleanTextFromTag( this.segment );
                const {text: sourceDecoded, tagsMap: sourceTagsMap} = TagUtils.cleanTextFromTag( self.props.segment.segment );
                let diff_obj = TextUtils.execDiff( matchDecoded, sourceDecoded );

                let totalLength = 0;
                // --- Replace all mapped tags back inside the string
                diff_obj.forEach((diffItem, index) =>{
                    if(diffItem[0] <= 0){
                        let includedTags = [];
                        let newTotalLength = totalLength + diffItem[1].length;
                        let firstLoopTotalLength = newTotalLength;
                        // sort tags by offset because next check is executed consecutively
                        matchTagsMap.sort((a, b) => {return a.offset-b.offset});
                        // get every tag included inside the original string slice
                        matchTagsMap.forEach((tag) => {
                            // offset+1 is for prepended Unicode Character 'ZERO WIDTH SPACE'
                            if(tag.offset+1 <= firstLoopTotalLength && tag.offset+1 >= firstLoopTotalLength - diffItem[1].length){
                                // add tag reference to work array
                                includedTags.push(tag);
                                // add tag's length (tag.offset is computed on the dirty string with all tags)
                                firstLoopTotalLength += tag.match.length
                            }
                        })
                        includedTags.forEach((includedTag) => {
                            const relativeTagOffset = diffItem[1].length - (newTotalLength - (includedTag.offset+1))
                            const strBefore = diffItem[1].slice(0 ,relativeTagOffset);
                            const strAfter = diffItem[1].slice(relativeTagOffset);
                            // insert tag
                            const newString = strBefore + includedTag.match + strAfter
                            // update total parsed length of the temp string
                            newTotalLength += includedTag.match.length
                            // update item inside diff_obj
                            diffItem[1] = newString;
                        })
                        // update total parsed length of the complete string
                        totalLength += diffItem[1].length;
                    }
                })

                item.sourceDiff =  TextUtils.diffMatchPatch.diff_prettyHtml( diff_obj ) ;
                item.sourceDiff = item.sourceDiff.replace(/&amp;/g, "&");
                item.sourceDiff = TagUtils.matchTag(TagUtils.decodeHtmlInTag(TagUtils.decodePlaceholdersToTextSimple(item.sourceDiff)))
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
                Source:
                <span className="bold" style={{fontSize: '14px'}}> {match.cb}</span>
            </li>
            <li className="graydesc">
                Target:
                <span className="bold" style={{fontSize: '14px'}}> {match.target}</span>
            </li>
        </ul>;
    }

    suggestionDblClick(match, index) {
        SegmentActions.setFocusOnEditArea();
        SegmentActions.disableTPOnSegment(this.props.segment);
        setTimeout( () => {
            SegmentActions.replaceEditAreaTextContent(this.props.segment.sid, match.translation);
        }, 200);
    }

    componentDidMount() {
        this._isMounted = true;
    }

    componentWillUnmount() {
        this._isMounted = false;
    }

    allowHTML(string) {
        return { __html: string };
    }

    shouldComponentUpdate(nextProps, nextState) {
        return ( (!_.isUndefined(nextProps.segment.cl_contributions) || !_.isUndefined(this.props.segment.cl_contributions)) &&
            ( (!_.isUndefined(nextProps.segment.cl_contributions) && _.isUndefined(this.props.segment.cl_contributions)) ||
            !Immutable.fromJS(this.props.segment.cl_contributions).equals(Immutable.fromJS(nextProps.segment.cl_contributions)) ) ) ||
            this.props.active_class !== nextProps.active_class ||
                this.props.tab_class !== nextProps.tab_class
    }

    render() {
        var matches = [];
        if (this.props.segment.cl_contributions && this.props.segment.cl_contributions.matches && this.props.segment.cl_contributions.matches.length > 0) {
            let tpmMatches = this.processContributions(this.props.segment.cl_contributions.matches);
            var self = this;
            tpmMatches.forEach(function (match, index) {
                var item =
                    <ul key={match.id + index}
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
        } else if (this.props.segment.cl_contributions && this.props.segment.cl_contributions.matches && this.props.segment.cl_contributions.matches.length === 0 ){
            if((config.mt_enabled)&&(!config.id_translator)) {
                matches.push( <ul key={0} className="graysmall message">
                    <li>There are no matches for this segment in the languages you have selected. Please, contact <a href="mailto:support@matecat.com">support@matecat.com</a> if you think this is an error.</li>
                </ul>);
            } else {
                matches.push( <ul key={0} className="graysmall message">
                    <li>There are no matches for this segment in the languages you have selected.</li>
                </ul>);
            }
        }
        return (
            <div
                key={"container_" + this.props.code}
                className={"tab sub-editor "+ this.props.active_class + " " + this.props.tab_class}
                id={"segment-" + this.props.id_segment +'-'+ this.props.tab_class}>
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
