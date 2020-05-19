const TagSuggestion = (props) => {

    const hoverStyle = (e) => {
        e.target.style.background = 'rgba(0, 0, 0, 0.05)';
        e.target.style.color = '#0055b8';

    };

    const normalStyle = (e) => {
        e.target.style.background = 'transparent';
        e.target.style.color = '#767676';
    };

    return (
        <div
            onMouseDown={ () => props.onTagClick(props.suggestion) }
            style={props.isFocused ? tagSuggestionActive : tagSuggestion}
            onMouseOver={hoverStyle}
            onMouseLeave={normalStyle}
        >
            {props.suggestion ? props.suggestion.data.placeholder : 'No tags'}
        </div>
    );
};

const tagSuggestion = {
        color: '#767676',
        textOverflow: 'ellipsis',
        overflow: 'hidden',
        padding: '12px 16px',
        width: '100%',
        fontSize: '14px',
        whiteSpace: 'nowrap',
        cursor: 'pointer',

};

const tagSuggestionActive = {
    ...tagSuggestion,
    fontWeight: '700',
    color:'#0055b8',
    background: 'rgba(0, 0, 0, 0.05)'
};

export default TagSuggestion;
