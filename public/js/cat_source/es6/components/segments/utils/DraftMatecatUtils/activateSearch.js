import SearchHighlight from "../../SearchHighLight/SearchHighLight.component";
import CompoundDecorator from "../CompoundDecorator";
import {CompositeDecorator, EditorState} from 'draft-js';
import * as DraftMatecatConstants from "./editorConstants";
import _ from "lodash";

const activateSearch = (editorState, decoratorStructure, text, params, occurrencesInSegment, currentIndex, tagRange) => {

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
    let decorators = decoratorStructure.slice();
    let occurrencesClone = _.cloneDeep(occurrencesInSegment);
    _.remove(decorators, (decorator) => decorator.name === DraftMatecatConstants.SEARCH_DECORATOR);
    decorators.push( generateSearchDecorator( search, occurrencesClone, params, currentIndex, tagRange) );
     const newDecorator = new CompositeDecorator( decorators );
    //const newDecorator = new CompoundDecorator( decorators );
    return {
        editorState: EditorState.set( editorState, {decorator: newDecorator} ),
        decorators: decorators
    }
};

export default activateSearch;