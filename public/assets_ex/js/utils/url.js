var url = {
    base_url: function (module) {
        // var url_host = 'anomali';
        var url_protocol = window.location.protocol;
        // return url_protocol + '/' + module + '/';
         return url_protocol + '/simi/public/' + module + '/'; //if framework
        //  return url_protocol + '/' + url_host + '/' + module; //if native
    },

    base_url_socket: function (port, module = '') {
        // var url_host = 'anomali';
        var url_protocol = window.location.protocol;
        // return url_protocol + '//localhost:' + port + '/'+ module; //development
        return url_protocol + '//10.11.1.17:' + port + '/'+ module; //production
    },

    path_url: function () {
        var url_protocol = window.location.protocol;
        return url_protocol + '/';
    },
};
