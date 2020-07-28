import React, {Component} from 'react';
import {getStyleForType} from "../tagModel";


const TagSuggestion = React.forwardRef((props,ref) => {

    const tagStyle = getStyleForType(props.suggestion.type);

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
