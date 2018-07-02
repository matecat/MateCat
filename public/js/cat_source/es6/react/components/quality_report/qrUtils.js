import ReactDom from "react-dom";
import QualityReport from "./QualityReport";

let QrUtils = {
    init: function () {
        ReactDom.render(React.createElement(QualityReport), document.getElementById('qr-root'));

    },


};


$(document).ready(function(){
    QrUtils.init();
});