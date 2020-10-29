import SearchHighlight from "../../SearchHighLight/SearchHighLight.component";
import CompoundDecorator from "../CompoundDecorator";
import {CompositeDecorator, EditorState} from 'draft-js';
import * as DraftMatecatConstants from "./editorConstants";
import _ from "lodash";

const activateSearch = (text, params, occurrencesInSegment, currentIndex, tagRange) => {

    const generateSearchDecorator = (highlightTerm, occurrences, params, currentIndex, tagRange) => {
        let regex = SearchUtils.getSearchRegExp(highlightTerm, params.ingnoreCase, params.exactMatch);
        return {
            name: DraftMatecatConstants.SEARCH_DECORATOR,
            strategy: (contentBlock, callback) => {
                if (highlightTerm !== '') {
                    findWithRegex(regex, contentBlock, occurrences, tagRange, callback);
                }
            },
            component: SearchHighlight,
            props: {
                occurrences,
                currentIndex,
                tagRange
            }
        };
    };

    const findWithRegex = (regex, contentBlock, occurrences, tagRange, callback) => {
        const text = contentBlock.getText();
        let matchArr, start, end;
        let index = 0;
        while ((matchArr = regex.exec(text)) !== null) {
            start = matchArr.index;
            end = start + matchArr[0].length;
            if ( occurrences[index] ) {
                occurrences[index].start = start;
            }
            callback(start, end);
            index++;
        }
    };

    let search = text;
    let occurrencesClone = _.cloneDeep(occurrencesInSegment);
    return generateSearchDecorator( search, occurrencesClone, params, currentIndex, tagRange);
};

export default activateSearch;