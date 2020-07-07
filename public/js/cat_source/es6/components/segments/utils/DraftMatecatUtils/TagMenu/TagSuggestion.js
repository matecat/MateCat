import React, {Component} from 'react';

const TagSuggestion = React.forwardRef((props,ref) => {

    let tagStyle = '';
    if(props.suggestion.data.openTagId){
        tagStyle = 'tag-close';
    }else if(props.suggestion.data.closeTagId){
        tagStyle = 'tag-open';
    }else{
        tagStyle = 'tag-selfclosed';
    }

    return (
        <div
            className={`tag-menu-suggestion ${props.isFocused ?  `active` : ''}`}
            onMouseDown={ (e) => {
                e.preventDefault();
                props.onTagClick(props.suggestion);
            } }
            style={props.isFocused ? {fontWeight: '700'} : null}
            tabIndex={props.tabIndex}
            ref={ref}
        >
            <div className={"tag-menu-suggestion-item"}>
                {props.suggestion ?
                    (<div className={"tag-container"}>
                        <div
                            className={`tag ${tagStyle}`}>
                            <span className={`tag-placeholder`}>{props.suggestion.data.placeholder}</span>
                        </div>
                    </div>)
                    : 'No tags'}
                <span className={`place-here-tips`}>Place here</span>
            </div>
        </div>
    );
});

export default TagSuggestion;
