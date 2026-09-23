let Database = {
    init: () => {
        let db = new PouchDB('anomali');
        return db;
    },

    destroy: () => {
        let db = Database.init();
        db.destroy().then(function (response) {
            // success
        }).catch(function (err) {
            console.log(err);
        });
    },

    removeDoc: (doc_param) => {
        let db = Database.init();
        db.get(doc_param).then(function (doc) {
            console.log('dokumen dihapus');
            return db.remove(doc);
        }).then(function (result) {
            // handle result
            // console.log();
        }).catch(function (err) {
            console.log(err);
        });
    },

    getTokenApi: (callback) => {
        let db = Database.init();
        let token = "";
        db.get('token').then(function (doc) {            
            token = doc.title;
            callback(token);
        });
    }

};

$(function () {
    Database.init();
});
