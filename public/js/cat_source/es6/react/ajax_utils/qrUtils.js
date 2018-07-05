

let QUALITY_REPORT =  {

    getSegmentsFiles() {

        let data = {
            // jid: 841,
            password: "5ec5f5a60ca2",
            step: 30,
            segment: "756469",
            where: 'after'
        };
        return $.ajax({
            data: data,
            type: "POST",
            url : "/?action=getSegments"
        });
    }
}

export default QUALITY_REPORT ;