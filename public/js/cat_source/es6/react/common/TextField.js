export default class TextField extends React.Component {

    constructor(props) {
        super(props);
        this.shouldDisplayError = this.shouldDisplayError.bind(this);
        this.spanStyle = {
            color: 'red',
            fontSize: '14px'
        }
    }

    shouldDisplayError() {
        return this.props.showError && this.props.errorText != "";
    }

    render() {
        var errorHtml = '';
        var type = 'text' ;

        if ( this.props.type ) {
            type = this.props.type ;
        }

        if (this.shouldDisplayError()) {
            errorHtml = <div className="validation-error">
                <span style={this.spanStyle} className="text">{this.props.errorText}</span>
            </div>
        }

        return (
            <div>
                <input type={type} placeholder={this.props.placeholder}
                       value={this.props.text} name={this.props.name} onChange={this.props.onFieldChanged} className={this.props.classes}/>
                {errorHtml}
            </div>
        );
    }
}

TextField.propTypes = {
    showError: React.PropTypes.bool.isRequired,
    onFieldChanged: React.PropTypes.func.isRequired
};

