import React from 'react';
import TagSuggestion from "./TagSuggestion";

const TagBox = (props) => {
    // props.suggestions = { missingTag: [...], sourceTag: [...]}
    const lastIndex = props.suggestions.missingTags ? props.suggestions.missingTags.length : 0;

    const missingSuggestions =  props.suggestions.missingTags ?  props.suggestions.missingTags.map((suggestion, index) => (
        <TagSuggestion
            key={index}
            suggestion={suggestion}
            onTagClick={props.onTagClick}
            isFocused={props.focusedTagIndex === index}
        />
    )) : null;

    const suggestions = props.suggestions.sourceTags ? props.suggestions.sourceTags.map((suggestion, index) => (
        <TagSuggestion
            key={index + lastIndex}
            suggestion={suggestion}
            onTagClick={props.onTagClick}
            isFocused={props.focusedTagIndex === (index + lastIndex)}
        />
    )) : null;



    const popoverOpen = Object.assign({}, props.popoverPosition, styles.popoverOpen)

    return (
        <div style={props.displayPopover ? popoverOpen : styles.popoverClosed}>
            <div>
                <div style={styles.headTagList}>Missing source
                    <div className={"tag-container"}>
                        <span
                            className={`tag tag-selfclosed tag-mismatch-error`}>
                            tags
                        </span>
                    </div>
                    in target
                </div>
                {missingSuggestions}
            </div>
            <div>
                <div style={styles.headTagList}>All
                    <div className={"tag-container"}>
                        <span
                            className={`tag tag-selfclosed`}>
                            tags
                        </span>
                    </div>
                    available
                </div>
                {suggestions}
            </div>
        </div>
    );
};

const styles = {
    popoverOpen: {
        position: 'absolute',
        background: '#fff',
        border: '1px solid #dadada',
        cursor: 'pointer',
        zIndex: 1,
        borderRadius: '5px',
        boxSizing: 'border-box',
        maxWidth: '300px',
        boxShadow: '0 1px 2px 0 rgba(0, 0, 0, 0.3)',
        padding: '0 12px',
    },
    popoverClosed: {
        display: 'none',
        position: 'absolute',
        background: 'white',
        border: '2px solid #e2e2e2',
        cursor: 'pointer',
        zIndex: 1,
        borderRadius: '2px',
        width: '18rem',
    },
    headTagList: {
        width: '100%',
        padding: '1rem 1.14rem',
        fontWeight: '700',
        lineHeight: '17px',
        borderTop: '1px solid #e9e3e8',
        borderBottom: '1px solid #e0e3e8',
        background: '#fff',
        alignItems: 'center',
        cursor:'default'
    },
    styleTagMismatch: {
        backgroundColor: '#fdeae2',
        boxShadow: 'inset 0 0 0 2px #f9dcd1',
        padding: '4px 8px',
        margin: '0 5px',
        color: '#E1565A',
        borderRadius: '7px',
        fontSize: '15px',
        fontStyle: 'italic',
    },
    styleTag: {
        display: 'inline-flex',
        background: '#F2F4F7',
        boxShadow: 'inset 0 0 0 2px #E5E9F1',
        borderRadius: '7px',
        fontSize: '15px',
        color: '#767676',
        fontStyle: 'italic',
        padding: '4px 8px',
        verticalAlign: 'middle',
        margin: '0 5px',
    }


};

export default TagBox;
