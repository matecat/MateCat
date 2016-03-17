

export default React.createClass({

    getInitialState : function() {
        return {
            category : null, 
            severity : '',
            submitDisabled : true,
        }; 
    },
    autoShortLabel : function(label) {
        return label; 
    },

    issueCategories : function() {
        return JSON.parse(config.lqa_nested_categories).categories ; 
    },

    severitySelected : function( category, event ) {
        var severity = $(ReactDOM.findDOMNode( event.target )).val() ;

        this.setState({
            category : category, 
            severity : severity,
            submitDisabled : severity == ''
        }); 
    },

    buttonClasses : function() {
        return classnames({
            'mc-button' : true,
            'blue-button' : true, 
            'disabled' : this.state.submitDisabled
        });
    },

    sendClick : function() {
        if ( this.state.submitDisabled ) {
            return; 
        }

        this.setState({ submitDone: true, submitDisabled : true });

        var message = $('textarea', ReactDOM.findDOMNode( this )).val();

        ReviewImproved.submitIssue(this.props.sid, {
            'id_category'         : this.state.category.id,
            'severity'            : this.state.severity,
            'target_text'         : this.props.selection.selected_string, 
            'start_node'          : this.props.selection.start_node, 
            'start_offset'        : this.props.selection.start_offset, 
            'end_node'            : this.props.selection.end_node,
            'end_offset'          : this.props.selection.end_offset, 
            'comment'             : message,
        },
        { done : this.props.submitIssueCallback }
        ); 
    },

    render : function() {
        var categoryComponents = []; 
        var withSeverities = 0;
        
        this.issueCategories().forEach(function(category, i) {
            var selectedValue = "";

            if ( this.state.category && this.state.category.id == category.id ) {
                selectedValue = this.state.severity ;
            }

            var k = 'category-selector-' + i ;

            if (category.severities != null) {
                withSeverities++ ;
            }

            categoryComponents.push(
                <ReviewIssueCategorySelector 
                    key={k}
                    focus={withSeverities == 1}
                    severitySelected={this.severitySelected} 
                    selectedValue={selectedValue}
                    nested={false} category={category} />);

            if ( category.subcategories.length > 0 ) {
                category.subcategories.forEach( function(category, ii) {
                    if (category.severities != null) {
                        withSeverities++ ;
                    }
                    var key = '' + i + '-' + ii;
                    var kk = 'category-selector-' + key ;
                    var selectedValue = "";

                    if ( this.state.category && this.state.category.id == category.id ) {
                        selectedValue = this.state.severity ;
                    }

                    categoryComponents.push(
                        <ReviewIssueCategorySelector 
                            key={kk}
                            focus={withSeverities == 1}
                            selectedValue={selectedValue}
                            severitySelected={this.severitySelected}
                            nested={true}
                            category={category}  />
                    );
                }.bind(this) ); 
            }
        }.bind(this)); 

        var buttonLabel = (this.state.submitDone ? 'Sending...' : 'Send'); 

        return <div className="review-issue-selection-panel">

        <h3>Error selection</h3> 
       

        <p>You selected "<span className="error-selection-highlight">{this.props.selection.selected_string}</span>" from segment {this.props.sid}</p>
        <h4>Select issue type</h4>
        <table className="review-issue-category-list">
        <tbody>
            {categoryComponents}
        </tbody>
        </table> 


        <div className="review-issue-terminal">
            <textarea data-minheight="40" data-maxheight="90"
                className="mc-textinput mc-textarea mc-resizable-textarea"
                placeholder="Write a comment..."
                />

            <div className="review-issue-buttons-right">
                <button onClick={this.sendClick}
                    className={this.buttonClasses()}>{buttonLabel}</button>
            </div>
        </div>
        </div> 
    }
});
