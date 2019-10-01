var ModalContainerComponent = require('./ModalContainerComponent').default;
var PreferencesModal = require('./PreferencesModal').default;
class ModalWindowComponent extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            isShowingModal: false,
            component: '',
            compProps: {},
            title: '',
            styleContainer:'',
            onCloseCallback: false
        };
        this.showModalComponent = this.showModalComponent.bind(this);
    }

    onCloseModal() {
        if ( this.state.onCloseCallback) {
            this.state.onCloseCallback();
        }
        this.setState({
            isShowingModal: false,
            component: '',
            compProps: {},
            title: '',
            styleContainer:'',
            onCloseCallback: false
        });
    }

    showModalComponent(component, props, title, style, onCloseCallback) {
        this.setState({
            isShowingModal: true,
            component: component,
            compProps: props,
            title: title,
            styleContainer: style,
            onCloseCallback: onCloseCallback
        });
    }

    componentWillMount() {

    }

    componentDidMount() {

    }

    componentWillUnmount() {

    }
    allowHTML(string) {
        return { __html: string };
    }

    render() {
        return <div> {
            this.state.isShowingModal &&
            <ModalContainerComponent onClose={this.onCloseModal.bind(this)}
                                     title={this.state.title} styleContainer={this.state.styleContainer}>
                <this.state.component {...this.state.compProps}/>
            </ModalContainerComponent>
        }
        </div>;
    }
}

export default ModalWindowComponent ;
