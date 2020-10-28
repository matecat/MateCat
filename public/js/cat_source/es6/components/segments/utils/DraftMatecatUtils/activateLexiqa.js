import LexiqaHighlight from "../../LexiqaHighlight/LexiqaHighlight.component";
import CompoundDecorator from "../CompoundDecorator";
import {CompositeDecorator, EditorState} from 'draft-js';
import * as DraftMatecatConstants from "./editorConstants";
import _ from "lodash";
import canDecorateRange from "./canDecorateRange";

const activateLexiqa = (editorState, decoratorStructure, lexiqaWarnings, sid, isSource, getUpdatedSegmentInfo) => {

    const generateLexiqaDecorator = (warnings, sid, isSource, decoratorName) => {
        return {
            name: DraftMatecatConstants.LEXIQA_DECORATOR,
            strategy: (contentBlock, callback, contentState) => {
                _.each(warnings, (warn)=>{
                    if(warn.blockKey === contentBlock.getKey()){
                        const canDecorate = canDecorateRange(warn.start, warn.end, contentBlock, contentState, decoratorName);
                        if(canDecorate) callback(warn.start, warn.end);
                    }
                });
            },
            component: LexiqaHighlight,
            props: {
                warnings,
                sid,
                isSource,
                getUpdatedSegmentInfo
            }
        };
    };
    let decorators = decoratorStructure.slice();
    _.remove(decorators, (decorator) => decorator.name === DraftMatecatConstants.LEXIQA_DECORATOR);
    decorators.push( generateLexiqaDecorator( lexiqaWarnings, sid, isSource, DraftMatecatConstants.LEXIQA_DECORATOR ) );
    //const newDecorator = new CompoundDecorator( decorators );
    const newDecorator = new CompositeDecorator( decorators );


    // Remove focus on source to avoid cursor jumping at beginning of target
    if(isSource){
        editorState = EditorState.acceptSelection(editorState, editorState.getSelection().merge({
            hasFocus: false
        }));
    }
    return {
        editorState: EditorState.set( editorState, {decorator: newDecorator} ),
        decorators: decorators
    }
};

export default activateLexiqa;