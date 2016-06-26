

var buttons = React.createClass({
    getInitialState: function() {
        return {
            status : this.props.status.toUpperCase(),
            fixedButtonDisabled : this.isToDisableButton(),
            anyRebuttedIssue : this.anyRebuttedIssue()
        }
    },

    handleSegmentUpdate : function(data) {
        if ( this.props.sid == data.sid ) {
            this.setState( { status : data.status.toUpperCase() } );
        }
    },

    anyRebuttedIssue : function( data ) {
        var issuesRebutted = MateCat.db.segment_translation_issues.find( {
            '$and': [
                { id_segment: this.props.sid  },
                {
                    rebutted_at: {  '$ne': null }
                }
            ]
        } );

        return !!( issuesRebutted && issuesRebutted.length ) ;
    },

    updateButtonToShow : function( data ) {
        this.setState( { anyRebuttedIssue : this.anyRebuttedIssue() } );
    },

    componentDidMount: function() {
        MateCat.db.addListener('segments', ['insert', 'update'], this.handleSegmentUpdate );
        MateCat.db.addListener('segment_translation_issues', ['insert', 'update', 'delete'], this.updateButtonToShow );

        if ( this.state.status == 'REJECTED' ) {
            var el = UI.Segment.findEl(this.props.sid);

            el.on( 'modified:true fixedButton:enable', { component: this }, function( event ) {
                if( event.data && event.data.component ) {
                    event.data.component.enable();
                }
            });

            el.on( 'modified:false', { component: this }, function( event ) {
                if( event.data && event.data.component ) {
                    event.data.component.disable();
                }
            });
        }
    },

    componentWillUnmount: function() {
        MateCat.db.removeListener('segments', ['insert', 'update'], this.handleSegmentUpdate );
        MateCat.db.removeListener('segment_translation_issues', ['insert', 'update', 'delete'], this.updateButtonToShow );

        var el = UI.Segment.findEl(this.props.sid);

        el.off( 'modified:true fixedButton:enable' );
        el.off( 'modified:false' );
    },

    render : function() {

        if ( this.state.anyRebuttedIssue ) {
            return  <div className="react-buttons">
                <MC.SegmentRebuttedButton status={this.state.status} sid={this.props.sid} />
            </div>
        } else {
            return <div className="react-buttons">
                <MC.SegmentFixedButton status={this.state.status} sid={this.props.sid} disabled={this.state.fixedButtonDisabled}  />
            </div>
        }
    },

    enable: function() {
        this.setState( { fixedButtonDisabled: false } );
    },

    disable: function() {
        this.setState( { fixedButtonDisabled: true } );
    },

    isToDisableButton: function() {
        var status = this.props.status.toUpperCase();

        if( this.state && this.state.status ) {
            status = this.state.status;
        }

        if ( status != 'REJECTED' || this.isSegmentModified() ) {
            return false;
        }

        return true;
    },

    isSegmentModified: function() {
        var el = UI.Segment.findEl(this.props.sid);

        var isModified = el.data('modified');

        if( isModified === true ) {
            return true;
        }

        return false;
    }
});

export default buttons;
