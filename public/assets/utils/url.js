var url = {
    base_url: function (module) {
        // var url_host = 'anomali';
        var url_protocol = window.location.protocol;
        // var url_protocol = 'http://10.252.89.118:8000';
        return url_protocol + '/' + module + '/';
        // DEVELOPMENT
        // return url_protocol + '/public/' + module + '/';
        // LOCAL
        // return url_protocol + '/sales-report-pharma-dev/public/' + module + '/';
        // return url_protocol + '/';
        //  return url_protocol + '/' + url_host + '/' + module + '/'; //if framework
        //  return url_protocol + '/' + url_host + '/' + module; //if native
    },

    base_url_socket: function (port, module = "") {
        // var url_host = 'anomali';
        var url_protocol = window.location.protocol;
        // return url_protocol + '//localhost:' + port + '/'+ module; //development
        return url_protocol + "//10.11.1.17:" + port + "/" + module; //production
    },

    path_url: function () {
        var url_protocol = window.location.protocol;
        return url_protocol + "/";
    },
};
