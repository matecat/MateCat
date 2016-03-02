

export default React.createClass({

    getInitialState : function() {
        return {
            category : null, 
            severity : null 
        }; 
    },
    autoShortLabel : function(label) {
        return label; 
    },

    issueCategories : function() {
        return JSON.parse(config.lqa_nested_categories).categories ; 
    },

    severitySelected : function( category, severity ) {
        this.setState({
            category : category, 
            severity : severity
        }); 
    },

    handleCommentChange : function( event ) {
        this.setState({
            message : event.target.value 
        }); 
    },

    buttonClasses : function() {
        var severitySet = this.state.severity != null ; 
        var categorySet = this.state.category != null; 
        var disabled = this.state.submitDone || !(categorySet && severitySet) ;

        return classnames({
            'mc-button' : true,
            'blue-button' : true, 
            'disabled' : disabled 
        });
    },

    sendClick : function() {
        if ( this.state.submitDone ) {
            return; 
        }

        this.setState({ submitDone : true }); 

        ReviewImproved.submitIssue(this.props.sid, {
            'id_category'         : this.state.category.id,
            'severity'            : this.state.severity.label, 
            'target_text'         : this.props.selection.selected_string, 
            'start_node'          : this.props.selection.start_node, 
            'start_offset'        : this.props.selection.start_offset, 
            'end_node'            : this.props.selection.end_node,
            'end_offset'          : this.props.selection.end_offset, 
            'comment'             : this.state.message, 
        },
        { done : this.props.submitIssueCallback }
        ); 
    },

    render : function() {
        var categoryComponents = []; 
        
        this.issueCategories().forEach(function(category, i) {
            categoryComponents.push(
                <ReviewIssueCategorySelector 
                    severitySelected={this.severitySelected} 
                    nested={false} category={category} key={i} />); 

            if ( category.subcategories.length > 0 ) {
                category.subcategories.forEach( function(category, ii) {
                    var key = i + '-' + ii;
                    categoryComponents.push(
                        <ReviewIssueCategorySelector 
                        severitySelected={this.severitySelected}
                            nested={true} category={category} key={key} />
                    );
                }.bind(this) ); 
            }
        }.bind(this)); 

        var buttonLabel = (this.state.submitDone ? 'Sending...' : 'Send'); 

        return <div className="review-issue-selection-panel">

        <div className="title">Error selection</div> 
        <div className="subtitle">Select issue type</div>

        <table className="review-issue-category-list">
        <tbody>
            {categoryComponents}
        </tbody>
        </table> 


        <div className="review-issue-terminal">
            <textarea data-minheight="40" data-maxheight="90"
                className="mc-textinput mc-textarea mc-resizable-textarea"
                        placeholder="Write a comment..."
                        onChange={this.handleCommentChange} />

            <div className="review-issue-buttons-right">
                <a onClick={this.sendClick} 
                    className={this.buttonClasses()} >{buttonLabel}</a>
            </div>
        </div>
        </div> 
    }
});
